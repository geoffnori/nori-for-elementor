<?php
/**
 * Full reset: puts Nori back the way a fresh install starts.
 *
 * It deletes every Nori class and variable from Elementor (Elementor strips the classes from
 * every element that uses them), switches every module off with default settings, puts the type
 * scale, brand and dark colors back to the factory defaults, deletes the HubSpot bridges, clears
 * the spam log and moves Nori's site header and footer to the trash. Pages made from templates
 * and the site's own classes and variables stay.
 *
 * It only runs with a backup: the admin first downloads an "Export Nori Theme JSON" file through
 * backup(), which hands out a one-time token for this user, valid for 30 minutes.
 *
 * @package Nori_For_Elementor
 */

namespace Nori\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Reset {

	const TOKEN_LIFE = 1800;

	/** Options a reset deletes (a fresh install has none of them). The plugin version and migration flags stay. */
	const OPTIONS = array(
		State::OPTION,
		Modules::OPTION,
		Modules::SETTINGS_OPTION,
		Design::TYPE_OPTION,
		Design::BRAND_OPTION,
		Design::BASE_OPTION,
		Dark_Colors::OPTION,
		Variables_Sync::NAMES_OPTION,
		HubSpot_Bridges::OPTION,
		HubSpot_Bridges::REPORTS,
		Form_Guard::LOG_OPTION,
		Site_Parts::OPTION,
	);

	/** @var Plugin */
	private $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	private static function token_key() {
		return 'nori_fe_reset_' . get_current_user_id();
	}

	/**
	 * What a reset would remove, for the confirm screen.
	 *
	 * @param array $cstatus Classes_Sync::status() (already fetched by the app).
	 * @param array $vstatus Variables_Sync::status().
	 */
	public function summary( array $cstatus, array $vstatus ) {
		$live_vars = array_filter( $vstatus, fn( $r ) => in_array( $r['status'], array( Variables_Sync::STATUS_DEFAULT, Variables_Sync::STATUS_EDITED ), true ) );
		$parts     = $this->plugin->site_parts->status()['parts'];
		$log       = Form_Guard::read_log();
		return array(
			'classes'   => count( array_filter( $cstatus, fn( $r ) => Classes_Sync::STATUS_MISSING !== $r['status'] ) ),
			'variables' => count( $live_vars ),
			'modules'   => count( $this->plugin->modules->enabled() ),
			'bridges'   => count( $this->plugin->hubspot->all() ),
			'parts'     => count( $parts ),
			'spam'      => count( $log['recent'] ),
		);
	}

	/**
	 * The backup step: a fresh export plus the token that lets run() go ahead.
	 *
	 * @param string $json The export as JSON (Admin_Overview::export()).
	 * @return array{json: string, token: string, filename: string}
	 */
	public function backup( $json ) {
		$token = wp_generate_password( 32, false );
		set_transient(
			self::token_key(),
			array(
				'token' => wp_hash( $token ),
				'time'  => time(),
				'size'  => strlen( $json ),
			),
			self::TOKEN_LIFE
		);
		return array(
			'json'     => $json,
			'token'    => $token,
			'filename' => self::filename( 'backup' ),
		);
	}

	public static function filename( $kind = '' ) {
		$host = sanitize_title( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		return 'nori-theme-' . $host . ( $kind ? '-' . $kind : '' ) . '-' . gmdate( 'Y-m-d' ) . '.json';
	}

	/** True when the token came from this user's backup in the last 30 minutes. */
	public function valid_token( $token ) {
		$saved = get_transient( self::token_key() );
		return is_array( $saved ) && is_string( $token ) && '' !== $token
			&& hash_equals( (string) $saved['token'], wp_hash( $token ) )
			&& time() - (int) $saved['time'] <= self::TOKEN_LIFE;
	}

	/**
	 * Reset everything. Each step runs even when an earlier one fails, so as much as possible is
	 * reset; failures come back in 'errors'.
	 *
	 * @return array{done: string[], errors: string[]}|\WP_Error
	 */
	public function run( $token ) {
		if ( ! $this->valid_token( $token ) ) {
			return new \WP_Error( 'nori_fe_reset_backup', __( 'Download the backup first (Export Nori Theme JSON). A backup is good for 30 minutes.', 'nori-for-elementor' ) );
		}
		delete_transient( self::token_key() ); // One reset per backup.

		$done   = array();
		$errors = array();
		$add    = function ( $n, $text ) use ( &$done ) {
			if ( $n ) {
				$done[] = $text;
			}
		};
		$atomic = Plugin::atomic_available();

		// 1. HubSpot bridges (each deletes its own class).
		$bridges = 0;
		foreach ( array_keys( $this->plugin->hubspot->all() ) as $id ) {
			$r = $this->plugin->hubspot->delete( $id );
			if ( is_wp_error( $r ) ) {
				$errors[] = $r->get_error_message();
			} else {
				++$bridges;
			}
		}
		/* translators: %d: number of HubSpot bridges. */
		$add( $bridges, sprintf( _n( '%d HubSpot bridge deleted', '%d HubSpot bridges deleted', $bridges, 'nori-for-elementor' ), $bridges ) );

		// 2. Every Nori class, module classes included.
		if ( $atomic ) {
			$r = $this->plugin->classes->remove( array_keys( $this->plugin->library->classes() ) );
			$errors = array_merge( $errors, array_values( array_unique( $r['errors'] ) ) );
			$n = count( $r['done'] );
			/* translators: %d: number of classes. */
			$add( $n, sprintf( _n( '%d class deleted from Elementor', '%d classes deleted from Elementor', $n, 'nori-for-elementor' ), $n ) );
		}

		// 3. Every Nori variable: back to its factory value first, so adding it again later
		// (Elementor restores soft-deleted variables) starts from the default, then delete.
		$keys = array_keys( $this->plugin->library->variables() );
		$this->plugin->variables->reset( $keys );
		$r = $this->plugin->variables->remove( $keys );
		$errors = array_merge( $errors, array_values( array_unique( $r['errors'] ) ) );
		$n = count( $r['done'] );
		/* translators: %d: number of variables. */
		$add( $n, sprintf( _n( '%d variable deleted from Elementor', '%d variables deleted from Elementor', $n, 'nori-for-elementor' ), $n ) );

		// 4. Nori's header and footer go to the trash (they can be restored from there).
		$parts = 0;
		foreach ( $this->plugin->site_parts->status()['parts'] as $part ) {
			if ( wp_trash_post( (int) $part['id'] ) ) {
				++$parts;
			}
		}
		if ( $parts ) {
			$done[] = __( 'header and footer moved to the trash', 'nori-for-elementor' );
		}

		// 5. Modules, settings, type scale, brand, dark colors, logs and Nori's own records.
		$modules = count( $this->plugin->modules->enabled() );
		foreach ( self::OPTIONS as $option ) {
			delete_option( $option );
		}
		$preview = (int) get_option( 'nori_fe_template_preview_page' );
		if ( $preview && 'private' === get_post_status( $preview ) ) {
			wp_delete_post( $preview, true );
		}
		delete_option( 'nori_fe_template_preview_page' );
		delete_transient( 'nori_fe_import_' . get_current_user_id() );
		delete_transient( 'nori_fe_gzip_sizes' );
		/* translators: %d: number of modules. */
		$add( $modules, sprintf( _n( '%d module switched off', '%d modules switched off', $modules, 'nori-for-elementor' ), $modules ) );
		$done[] = __( 'colors, type scale and module settings back to the defaults', 'nori-for-elementor' );

		$this->plugin->library->reload_classes();
		do_action( 'nori_fe_reset_done' );

		return array(
			'done'   => $done,
			'errors' => $errors,
		);
	}
}
