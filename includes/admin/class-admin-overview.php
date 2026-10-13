<?php
/**
 * Home data for the app: site health, front-end footprint, the export, and the changelog.
 *
 * @package Nori_For_Elementor
 */

namespace Nori\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Overview {

	/** @var Plugin */
	private $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/* ------------------------------------------------------------------
	 * Data
	 * --------------------------------------------------------------- */

	/** Every check, with a status (ok, warn, info, error) and a plain-language fix. */
	public function health() {
		$checks = array();
		$wp     = get_bloginfo( 'version' );
		$el     = defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : null;

		$checks[] = array(
			'label'  => __( 'WordPress', 'nori-for-elementor' ),
			'value'  => $wp,
			'status' => version_compare( $wp, '6.5', '>=' ) ? 'ok' : 'error',
			'fix'    => __( 'Nori needs WordPress 6.5 or newer.', 'nori-for-elementor' ),
		);
		$checks[] = array(
			'label'  => __( 'PHP', 'nori-for-elementor' ),
			'value'  => PHP_VERSION,
			'status' => version_compare( PHP_VERSION, '7.4', '>=' ) ? 'ok' : 'error',
			'fix'    => __( 'Ask your host for PHP 7.4 or newer (8.2+ recommended).', 'nori-for-elementor' ),
		);
		$checks[] = array(
			'label'  => __( 'Elementor', 'nori-for-elementor' ),
			'value'  => $el ? $el : __( 'Not active', 'nori-for-elementor' ),
			'status' => $el && version_compare( $el, NORI_FE_MIN_ELEMENTOR, '>=' ) ? 'ok' : 'error',
			/* translators: %s: minimum Elementor version. */
			'fix'    => sprintf( __( 'Install and activate Elementor %s or newer.', 'nori-for-elementor' ), NORI_FE_MIN_ELEMENTOR ),
		);
		$checks[] = array(
			'label'  => __( 'Atomic editor', 'nori-for-elementor' ),
			'value'  => Plugin::atomic_available() ? __( 'On', 'nori-for-elementor' ) : __( 'Off', 'nori-for-elementor' ),
			'status' => Plugin::atomic_available() ? 'ok' : 'warn',
			'fix'    => __( 'Classes and variables need Elementor\'s Atomic (version 4) editor. Turn it on in Elementor > Settings. Modules work either way.', 'nori-for-elementor' ),
		);
		$can = current_user_can( 'elementor_global_classes_update_class' );
		$checks[] = array(
			'label'  => __( 'Permission to edit classes', 'nori-for-elementor' ),
			'value'  => $can ? __( 'Yes', 'nori-for-elementor' ) : __( 'Not yet', 'nori-for-elementor' ),
			'status' => $can ? 'ok' : 'warn',
			'fix'    => __( 'Open any Elementor screen in WP Admin once so Elementor finishes its update, then come back.', 'nori-for-elementor' ),
		);
		$pro = class_exists( '\\Elementor\\Utils' ) && \Elementor\Utils::has_pro();
		$checks[] = array(
			'label'  => __( 'Elementor Pro', 'nori-for-elementor' ),
			'value'  => $pro ? __( 'Active', 'nori-for-elementor' ) : __( 'Not active', 'nori-for-elementor' ),
			'status' => $pro ? 'ok' : 'info',
			'fix'    => __( 'Optional. With Pro, the border radius set and module sizes and timings also become variables you can edit in Elementor.', 'nori-for-elementor' ),
		);
		$list = $this->plugin->api->list_classes();
		if ( ! is_wp_error( $list ) && Plugin::atomic_available() ) {
			$n        = count( $list );
			$checks[] = array(
				'label'  => __( 'Global classes on this site', 'nori-for-elementor' ),
				/* translators: %d: number of classes. */
				'value'  => sprintf( __( '%d of 1000', 'nori-for-elementor' ), $n ),
				'status' => $n > 900 ? 'warn' : 'ok',
				'fix'    => __( 'Elementor allows up to 1000 global classes. Delete unused ones before adding more.', 'nori-for-elementor' ),
			);
		}
		if ( $this->plugin->modules->is_enabled( 'font-preconnect' ) && '1' === get_option( 'elementor_local_google_fonts' ) ) {
			$checks[] = array(
				'label'  => __( 'Font Preconnect', 'nori-for-elementor' ),
				'value'  => __( 'Google Fonts skipped', 'nori-for-elementor' ),
				'status' => 'info',
				'fix'    => __( 'Elementor loads Google Fonts from your own site, so there is nothing to connect to early. That\'s fine.', 'nori-for-elementor' ),
			);
		}
		return $checks;
	}

	/**
	 * Files each enabled module adds to the front end, with their sizes.
	 *
	 * @return array{rows: array[], total: int, gzip: int}
	 */
	/**
	 * Gzipped size of a plugin file, cached per plugin version (the files only change on update),
	 * so the Overview doesn't recompress every module on each admin request.
	 */
	private static function gzip_size( $path, $bytes ) {
		static $sizes = null;
		if ( null === $sizes ) {
			$sizes = get_transient( 'nori_fe_gzip_sizes' );
			$sizes = is_array( $sizes ) && ( $sizes['v'] ?? '' ) === NORI_FE_VERSION ? $sizes : array( 'v' => NORI_FE_VERSION );
		}
		$key = md5( $path . '|' . $bytes );
		if ( ! isset( $sizes[ $key ] ) ) {
			$sizes[ $key ] = function_exists( 'gzencode' ) ? strlen( gzencode( (string) file_get_contents( $path ), 9 ) ) : $bytes; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin file.
			set_transient( 'nori_fe_gzip_sizes', $sizes, WEEK_IN_SECONDS );
		}
		return (int) $sizes[ $key ];
	}

	public function footprint() {
		$rows  = array();
		$seen  = array();
		$total = 0;
		$gzip  = 0;
		$add   = function ( $owner, $path, $url_path ) use ( &$rows, &$seen, &$total, &$gzip ) {
			if ( isset( $seen[ $path ] ) || ! is_readable( $path ) ) {
				return;
			}
			$seen[ $path ] = true;
			$bytes         = (int) filesize( $path );
			$gz            = self::gzip_size( $path, $bytes );
			$total        += $bytes;
			$gzip         += $gz;
			$rows[]        = array(
				'owner' => $owner,
				'file'  => $url_path,
				'type'  => substr( $path, -3 ) === '.js' ? 'JS' : 'CSS',
				'bytes' => $bytes,
				'gzip'  => $gz,
			);
		};
		$modules = $this->plugin->modules->all();
		$enabled = $this->plugin->modules->enabled();
		$core    = false;
		foreach ( $enabled as $key ) {
			if ( ! empty( $modules[ $key ]['js'] ) ) {
				$core = true;
			}
		}
		if ( $core ) {
			$add( __( 'Shared', 'nori-for-elementor' ), NORI_FE_DIR . 'assets/js/core.js', 'assets/js/core.js' );
		}
		foreach ( $enabled as $key ) {
			$m = $modules[ $key ];
			foreach ( (array) ( $m['vendor_js'] ?? array() ) as $p ) {
				$add( $m['label'], NORI_FE_DIR . ltrim( $p, '/' ), ltrim( $p, '/' ) );
			}
			foreach ( (array) ( $m['shared_css'] ?? array() ) as $p ) {
				$add( __( 'Shared', 'nori-for-elementor' ), NORI_FE_DIR . ltrim( $p, '/' ), ltrim( $p, '/' ) );
			}
			foreach ( array_merge( (array) ( $m['css'] ?? array() ), (array) ( $m['js'] ?? array() ) ) as $f ) {
				$rel = 'modules/' . $key . '/' . basename( $f );
				$add( $m['label'], NORI_FE_DIR . $rel, $rel );
			}
		}
		return array(
			'rows'  => $rows,
			'total' => $total,
			'gzip'  => $gzip,
		);
	}

	/** A readable summary of this site's setup, for sharing or moving to another site. */
	public function export( $status ) {
		$lines   = array();
		$lines[] = 'Nori for Elementor ' . NORI_FE_VERSION . ' on ' . wp_parse_url( home_url(), PHP_URL_HOST );
		$lines[] = 'WordPress ' . get_bloginfo( 'version' ) . ', Elementor ' . ( defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : 'n/a' ) . ', PHP ' . PHP_VERSION;
		$lines[] = '';
		$lines[] = 'Modules on:';
		$modules = $this->plugin->modules->all();
		$data    = array(
			'nori'      => NORI_FE_VERSION,
			'modules'   => array(),
			'classes'   => array(),
			'variables' => array(),
		);
		foreach ( $this->plugin->modules->enabled() as $key ) {
			$settings = $this->plugin->modules->settings( $key );
			$pairs    = array();
			foreach ( $settings as $k => $v ) {
				$pairs[] = $k . '=' . ( is_bool( $v ) ? ( $v ? 'on' : 'off' ) : $v );
			}
			$lines[]                   = '- ' . $modules[ $key ]['label'] . ( $pairs ? ' (' . implode( ', ', $pairs ) . ')' : '' );
			$data['modules'][ $key ]   = $settings;
		}
		if ( ! $this->plugin->modules->enabled() ) {
			$lines[] = '- none';
		}
		if ( $status ) {
			$in     = array_filter( $status, fn( $r ) => 'missing' !== $r['status'] );
			$edited = array_filter( $status, fn( $r ) => 'edited' === $r['status'] );
			$lines[] = '';
			/* translators: 1: classes in Elementor, 2: edited classes. */
			$lines[] = sprintf( 'Classes in Elementor: %1$d (%2$d edited)', count( $in ), count( $edited ) );
			if ( $edited ) {
				$lines[] = 'Edited: ' . implode( ', ', array_map( fn( $r ) => '.' . $r['label'], $edited ) );
			}
			foreach ( $in as $key => $r ) {
				$data['classes'][ $key ] = $r['status'];
			}
		}
		$vars = $this->plugin->variables->status();
		if ( ! is_wp_error( $vars ) ) {
			$lib     = $this->plugin->library->variables();
			$live    = array_filter( $vars, fn( $r ) => in_array( $r['status'], array( 'default', 'edited' ), true ) );
			$lines[] = '';
			$lines[] = 'Variables in Elementor: ' . count( $live );
			foreach ( $live as $key => $r ) {
				$lines[]                   = '- ' . $lib[ $key ]['label'] . ': ' . $r['value'] . ( 'edited' === $r['status'] ? ' (edited)' : '' );
				$data['variables'][ $key ] = $r['value'];
			}
		}
		return array(
			'text' => implode( "\n", $lines ),
			'json' => wp_json_encode( ( new Transfer( $this->plugin ) )->export(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
		);
	}

	/** Parse the changelog out of readme.txt. @return array<string, string[]> version => entries. */
	public function changelog() {
		$file = NORI_FE_DIR . 'readme.txt';
		if ( ! is_readable( $file ) ) {
			return array();
		}
		$text = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin file.
		$pos  = strpos( $text, '== Changelog ==' );
		if ( false === $pos ) {
			return array();
		}
		$text = substr( $text, $pos + 15 );
		$end  = strpos( $text, "\n== " );
		$text = false === $end ? $text : substr( $text, 0, $end );
		$out  = array();
		$ver  = null;
		foreach ( preg_split( '/\r?\n/', $text ) as $line ) {
			if ( preg_match( '/^=\s*([^=]+?)\s*=$/', trim( $line ), $m ) ) {
				$ver         = $m[1];
				$out[ $ver ] = array();
			} elseif ( $ver && preg_match( '/^\*\s+(.*)$/', trim( $line ), $m ) ) {
				$out[ $ver ][] = $m[1];
			}
		}
		return $out;
	}
}
