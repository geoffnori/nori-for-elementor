<?php
/**
 * Form Spam Protection, server side.
 *
 * Every entry must carry a signed pass ("form token") that the visitor's browser fetched from this
 * site while the page was open. The server checks it before Elementor processes the entry:
 *
 *   - no pass: a bot posting straight to WordPress, without loading the page
 *   - a pass that wasn't signed here, has expired, or was already used
 *   - too fast: sent sooner after the pass was issued than the minimum time
 *
 * then, if they are switched on, too many entries from one visitor in 10 minutes, and too many links.
 * The pass is fetched over admin-ajax (Elementor's own forms use it, so it is never switched off like
 * the REST API sometimes is) and only after the page has loaded, so cached pages work.
 *
 * Blocked entries are counted, with a short log (time, reason, page) and no form content. Visitors'
 * IP addresses are never stored: the rate limit keys on a salted hash, kept for 10 minutes.
 *
 * @package Nori_For_Elementor
 */

namespace Nori\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Form_Guard {

	const LOG_OPTION   = 'nori_fe_spam_log';
	const TOKEN_FIELD  = 'nori_ft';
	const HONEYPOT     = 'nori_hp';
	const TOKEN_LIFE   = 7200;        // A pass works for 2 hours (the page refreshes it before then).
	const RATE_WINDOW  = 600;         // Rate limit window: 10 minutes.
	const LOG_SIZE     = 50;
	const LOG_DAYS     = 30;
	const LOG_PER_VISITOR = 20;
	const ATOMIC_AJAX  = 'elementor_pro_atomic_forms_send_form';

	/** Why an entry was blocked, as shown in WP Admin. */
	public static function reasons() {
		return array(
			'honeypot'   => __( 'Filled in the hidden field', 'nori-for-elementor' ),
			'too_fast'   => __( 'Sent too fast', 'nori-for-elementor' ),
			'no_token'   => __( 'Sent without opening the page', 'nori-for-elementor' ),
			'bad_token'  => __( 'Forged or expired pass', 'nori-for-elementor' ),
			'reused'     => __( 'Pass used twice', 'nori-for-elementor' ),
			'rate_limit' => __( 'Too many entries', 'nori-for-elementor' ),
			'links'      => __( 'Too many links', 'nori-for-elementor' ),
		);
	}

	/** @var callable Returns the module settings. */
	private $settings;

	public function __construct( callable $settings ) {
		$this->settings = $settings;
	}

	public function register_hooks() {
		add_action( 'wp_ajax_nori_fe_form_token', array( $this, 'ajax_token' ) );
		add_action( 'wp_ajax_nopriv_nori_fe_form_token', array( $this, 'ajax_token' ) );
		add_action( 'wp_ajax_nori_fe_spam_report', array( $this, 'ajax_report' ) );
		add_action( 'wp_ajax_nopriv_nori_fe_spam_report', array( $this, 'ajax_report' ) );
		// Elementor Pro classic forms.
		add_action( 'elementor_pro/forms/validation', array( $this, 'check_classic' ), 10, 2 );
		// Elementor Pro Atomic forms: before Elementor's own handler (priority 10) runs.
		add_action( 'wp_ajax_' . self::ATOMIC_AJAX, array( $this, 'check_atomic' ), 1 );
		add_action( 'wp_ajax_nopriv_' . self::ATOMIC_AJAX, array( $this, 'check_atomic' ), 1 );
	}

	private function setting( $key, $fallback ) {
		$s = call_user_func( $this->settings );
		return array_key_exists( $key, $s ) ? $s[ $key ] : $fallback;
	}

	/* ------------------------------------------------------------------
	 * The pass
	 * --------------------------------------------------------------- */

	private static function sign( $payload ) {
		return substr( hash_hmac( 'sha256', $payload, wp_salt( 'nonce' ) . '|nori-form-token' ), 0, 32 );
	}

	/** A new pass: "<issued, in ms>.<random>.<signature>". */
	public static function issue( $now_ms = null ) {
		$payload = (string) (int) floor( null === $now_ms ? microtime( true ) * 1000 : $now_ms ) . '.' . wp_generate_password( 12, false );
		return $payload . '.' . self::sign( $payload );
	}

	/**
	 * @return string '' when the pass is good, otherwise the reason (no_token, bad_token, too_fast, reused).
	 */
	public function verify( $token, $consume = true, $now_ms = null ) {
		$token = is_string( $token ) ? trim( $token ) : '';
		if ( '' === $token ) {
			return 'no_token';
		}
		if ( ! preg_match( '/^(\d{12,14})\.([A-Za-z0-9]{12})\.([a-f0-9]{32})$/', $token, $m ) || ! hash_equals( self::sign( $m[1] . '.' . $m[2] ), $m[3] ) ) {
			return 'bad_token';
		}
		$now_ms = null === $now_ms ? microtime( true ) * 1000 : $now_ms;
		$age_ms = $now_ms - (float) $m[1];
		if ( $age_ms > self::TOKEN_LIFE * 1000 || $age_ms < -60000 ) {
			return 'bad_token';
		}
		$min = (int) $this->setting( 'minTime', 1200 );
		if ( $min > 0 && $age_ms < $min ) {
			return 'too_fast';
		}
		$used = 'nori_fe_ft_' . md5( $m[2] );
		if ( get_transient( $used ) ) {
			return 'reused';
		}
		if ( $consume ) {
			set_transient( $used, 1, self::TOKEN_LIFE );
		}
		return '';
	}

	public function ajax_token() {
		nocache_headers();
		wp_send_json_success( array( 'token' => self::issue() ) );
	}

	/* ------------------------------------------------------------------
	 * Checks
	 * --------------------------------------------------------------- */

	/** The pass sent with the entry: a form field (classic forms) or a short-lived cookie (Atomic forms). */
	private static function posted_token() {
		// phpcs:disable WordPress.Security.NonceVerification -- spam check; Elementor verifies its own nonce.
		if ( isset( $_POST[ self::TOKEN_FIELD ] ) ) {
			return sanitize_text_field( wp_unslash( $_POST[ self::TOKEN_FIELD ] ) );
		}
		return isset( $_COOKIE[ self::TOKEN_FIELD ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::TOKEN_FIELD ] ) ) : '';
		// phpcs:enable
	}

	/** Links in the entry's text: "http://", "https://" and "www." each count. */
	public static function count_links( array $values ) {
		$n = 0;
		foreach ( $values as $v ) {
			if ( is_string( $v ) ) {
				$n += preg_match_all( '#(?:https?://|\bwww\.)\S#i', $v );
			}
		}
		return $n;
	}

	/**
	 * The visitor's address for rate limiting.
	 *
	 * REMOTE_ADDR, unless it is a private or reserved address (the site sits behind a local
	 * reverse proxy or load balancer): then the right-most public address in X-Forwarded-For,
	 * which is the one that proxy added (entries further left can be forged by the visitor).
	 * Sites behind a CDN that doesn't rewrite REMOTE_ADDR (Cloudflare without its plugin, etc.)
	 * can return the right address with the `nori_fe_visitor_ip` filter.
	 */
	public static function visitor_ip() {
		$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ip     = filter_var( $remote, FILTER_VALIDATE_IP ) ? $remote : '';
		$public = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
		if ( $ip && ! filter_var( $ip, FILTER_VALIDATE_IP, $public ) && ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$hops = array_reverse( array_map( 'trim', explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) ) ) );
			foreach ( $hops as $hop ) {
				if ( filter_var( $hop, FILTER_VALIDATE_IP, $public ) ) {
					$ip = $hop;
					break;
				}
			}
		}
		$filtered = apply_filters( 'nori_fe_visitor_ip', $ip, $remote );
		return is_string( $filtered ) && filter_var( $filtered, FILTER_VALIDATE_IP ) ? $filtered : $ip;
	}

	/** One bucket per visitor. IPv6 is grouped by /64, since one connection usually owns a whole /64. */
	private static function visitor_key() {
		$ip = self::visitor_ip();
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			$bin = inet_pton( $ip );
			$ip  = false === $bin ? $ip : bin2hex( substr( $bin, 0, 8 ) ) . '::/64';
		}
		return 'nori_fe_rl_' . md5( wp_salt( 'nonce' ) . '|' . $ip );
	}

	/**
	 * Every check, in order. Returns '' to let the entry through, or the reason it was blocked.
	 *
	 * @param string[] $values The entry's field values (for the link limit).
	 */
	public function check( $honeypot, $token, array $values ) {
		if ( '' !== trim( (string) $honeypot ) ) {
			return 'honeypot';
		}
		$why = $this->verify( $token );
		if ( '' !== $why ) {
			return $why;
		}
		$limit = (int) $this->setting( 'rateLimit', 5 );
		if ( $limit > 0 && ! current_user_can( 'edit_posts' ) ) {
			$key  = self::visitor_key();
			$seen = (int) get_transient( $key );
			if ( $seen >= $limit ) {
				return 'rate_limit';
			}
			set_transient( $key, $seen + 1, self::RATE_WINDOW );
		}
		$max = (string) $this->setting( 'maxLinks', '3' );
		if ( 'off' !== $max && self::count_links( $values ) > (int) $max ) {
			return 'links';
		}
		return '';
	}

	/** Blocks unless the module is in test mode, and logs either way. Returns true to block. */
	private function decide( $why, $kind ) {
		if ( '' === $why ) {
			return false;
		}
		$test = ! empty( $this->setting( 'logOnly', false ) );
		self::log( $why, $kind, $test );
		return ! $test;
	}

	public function check_classic( $record, $ajax_handler ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Elementor Pro verifies the form nonce before this hook.
		$hp     = isset( $_POST[ self::HONEYPOT ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::HONEYPOT ] ) ) : '';
		$values = array();
		foreach ( (array) $record->get( 'fields' ) as $f ) {
			$v        = $f['raw_value'] ?? $f['value'] ?? '';
			$values[] = is_array( $v ) ? implode( ' ', array_filter( $v, 'is_scalar' ) ) : (string) $v;
		}
		if ( $this->decide( $this->check( $hp, self::posted_token(), $values ), 'classic' ) ) {
			$ajax_handler->add_error_message( __( 'Your submission could not be sent. Please reload the page and try again.', 'nori-for-elementor' ) );
			$ajax_handler->is_success = false;
		}
	}

	public function check_atomic() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- spam check before Elementor's handler, which verifies its own nonce.
		$values = array();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- a nested array; each value is checked and sanitized below.
		$posted = isset( $_POST['form_fields'] ) && is_array( $_POST['form_fields'] ) ? wp_unslash( $_POST['form_fields'] ) : array();
		foreach ( $posted as $f ) {
			if ( is_array( $f ) && isset( $f['value'] ) && is_string( $f['value'] ) ) {
				$values[] = sanitize_textarea_field( $f['value'] );
			}
		}
		$hp = isset( $_POST[ self::HONEYPOT ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::HONEYPOT ] ) ) : '';
		// phpcs:enable
		if ( $this->decide( $this->check( $hp, self::posted_token(), $values ), 'atomic' ) ) {
			wp_send_json_error( array( 'message' => __( 'Your submission could not be sent. Please reload the page and try again.', 'nori-for-elementor' ) ), 400 );
		}
	}

	/* ------------------------------------------------------------------
	 * Log
	 * --------------------------------------------------------------- */

	/** The page the entry came from, as a path without the query (nothing the visitor typed). */
	private static function page() {
		$ref  = wp_get_referer();
		$path = $ref ? (string) wp_parse_url( $ref, PHP_URL_PATH ) : '';
		return substr( '' === $path ? '/' : $path, 0, 200 );
	}

	public static function log( $why, $kind, $test = false ) {
		if ( ! isset( self::reasons()[ $why ] ) ) {
			return;
		}
		// At most LOG_PER_VISITOR log writes per visitor per rate window, so a flood of junk posts
		// can't keep rewriting the option. Blocking itself is never throttled.
		$throttle = self::visitor_key() . '_l';
		$written  = (int) get_transient( $throttle );
		if ( $written >= self::LOG_PER_VISITOR ) {
			return;
		}
		set_transient( $throttle, $written + 1, self::RATE_WINDOW );
		$log   = self::read_log();
		$day   = gmdate( 'Y-m-d' );
		$key   = $test ? 'flagged' : 'blocked';
		$log['days'][ $day ][ $key ][ $why ] = (int) ( $log['days'][ $day ][ $key ][ $why ] ?? 0 ) + 1;
		ksort( $log['days'] );
		$log['days'] = array_slice( $log['days'], -self::LOG_DAYS, null, true );
		array_unshift(
			$log['recent'],
			array(
				'time' => time(),
				'why'  => $why,
				'kind' => in_array( $kind, array( 'classic', 'atomic', 'browser' ), true ) ? $kind : 'classic',
				'page' => self::page(),
				'test' => (bool) $test,
			)
		);
		$log['recent'] = array_slice( $log['recent'], 0, self::LOG_SIZE );
		update_option( self::LOG_OPTION, $log, false );
	}

	public static function read_log() {
		$log = get_option( self::LOG_OPTION, array() );
		$log = is_array( $log ) ? $log : array();
		return array(
			'days'   => is_array( $log['days'] ?? null ) ? $log['days'] : array(),
			'recent' => is_array( $log['recent'] ?? null ) ? $log['recent'] : array(),
		);
	}

	/** @return array{blocked: int, flagged: int, by_reason: array<string, int>} The last $days days (by_reason: blocked only). */
	public static function totals( $days = 7 ) {
		$from = gmdate( 'Y-m-d', time() - ( $days - 1 ) * DAY_IN_SECONDS );
		$out  = array( 'blocked' => 0, 'flagged' => 0, 'by_reason' => array() );
		foreach ( self::read_log()['days'] as $day => $row ) {
			if ( $day < $from ) {
				continue;
			}
			foreach ( array( 'blocked', 'flagged' ) as $k ) {
				foreach ( (array) ( $row[ $k ] ?? array() ) as $why => $n ) {
					$out[ $k ] += (int) $n;
					if ( 'blocked' === $k ) {
						$out['by_reason'][ $why ] = (int) ( $out['by_reason'][ $why ] ?? 0 ) + (int) $n;
					}
				}
			}
		}
		arsort( $out['by_reason'] );
		return $out;
	}

	public static function clear_log() {
		delete_option( self::LOG_OPTION );
	}

	/**
	 * The browser reports what it stopped before sending (honeypot, too fast), so the log is complete.
	 * At most one report per visitor every 10 seconds, and only known reasons.
	 */
	public function ajax_report() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- anonymous, rate limited, stores only a known reason.
		$why = isset( $_POST['why'] ) ? sanitize_key( wp_unslash( $_POST['why'] ) ) : '';
		if ( ! in_array( $why, array( 'honeypot', 'too_fast' ), true ) ) {
			wp_send_json_error( null, 400 );
		}
		$key = self::visitor_key() . '_r';
		if ( get_transient( $key ) ) {
			wp_send_json_success();
		}
		set_transient( $key, 1, 10 );
		self::log( $why, 'browser', ! empty( $this->setting( 'logOnly', false ) ) );
		wp_send_json_success();
	}
}
