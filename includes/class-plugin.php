<?php
/**
 * Bootstraps the plugin once Elementor is available.
 *
 * @package Nori_For_Elementor
 */

namespace Nori\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Plugin {

	/** @var Plugin|null */
	private static $instance = null;

	/** @var Library */
	public $library;

	/** @var Elementor_Api */
	public $api;

	/** @var Variables_Sync */
	public $variables;

	/** @var Classes_Sync */
	public $classes;

	/** @var Modules */
	public $modules;

	/** @var HubSpot_Bridges */
	public $hubspot;

	/** @var Templates */
	public $templates;

	/** @var Site_Parts */
	public $site_parts;

	/** @var Site_Tools */
	public $site_tools;

	public static function boot() {
		if ( null !== self::$instance ) {
			return;
		}

		// Translations load on their own (WordPress 4.6+), so there's no load_plugin_textdomain() call.

		if ( ! self::requirements_met() ) {
			// The message is translated when the notice prints: translating on plugins_loaded is too early (WordPress 6.7+ warns).
			add_action(
				'admin_notices',
				function () {
					printf( '<div class="notice notice-error"><p><strong>Nori for Elementor:</strong> %s</p></div>', esc_html( self::requirements_problem() ) );
				}
			);
			return;
		}

		self::$instance = new self();
	}

	public static function instance() {
		return self::$instance;
	}

	/** True when Elementor is active and new enough. */
	public static function requirements_met() {
		return defined( 'ELEMENTOR_VERSION' ) && version_compare( ELEMENTOR_VERSION, NORI_FE_MIN_ELEMENTOR, '>=' );
	}

	/**
	 * @return string Empty when everything the plugin needs is present.
	 */
	public static function requirements_problem() {
		if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
			return __( 'Elementor must be installed and active.', 'nori-for-elementor' );
		}
		if ( version_compare( ELEMENTOR_VERSION, NORI_FE_MIN_ELEMENTOR, '<' ) ) {
			/* translators: 1: required Elementor version, 2: installed version. */
			return sprintf( __( 'Elementor %1$s or newer is required (you have %2$s).', 'nori-for-elementor' ), NORI_FE_MIN_ELEMENTOR, ELEMENTOR_VERSION );
		}
		return '';
	}

	/**
	 * Atomic classes need Elementor's atomic style schema, which only exists when the
	 * Atomic editor is on. Modules work without it.
	 */
	public static function atomic_available() {
		return class_exists( '\Elementor\Modules\AtomicWidgets\Styles\Style_Schema' )
			&& class_exists( '\Elementor\Modules\GlobalClasses\Global_Classes_Rest_Api' );
	}

	private function __construct() {
		$includes = array( 'class-design', 'class-dark-colors', 'class-library', 'class-style-converter', 'class-elementor-api', 'class-state', 'class-variables-sync', 'class-classes-sync', 'class-modules', 'class-transfer', 'class-templates', 'class-site-parts', 'class-site-tools', 'class-hubspot-bridges', 'class-form-guard', 'class-reset', 'class-admin', 'class-abilities' );
		foreach ( $includes as $file ) {
			require_once NORI_FE_DIR . 'includes/' . $file . '.php';
		}

		$this->library   = new Library( NORI_FE_DIR . 'library/' );
		$this->api       = new Elementor_Api();
		$this->variables = new Variables_Sync( $this->library, $this->api );
		$this->classes   = new Classes_Sync( $this->library, $this->api, $this->variables );
		$this->modules   = new Modules( $this->library );
		$this->modules->set_variables( $this->variables );
		$this->library->set_link_resolver( array( $this->modules, 'class_link' ) );
		add_action( 'nori_fe_settings_saved', array( $this, 'refresh_module_classes' ) );
		$this->hubspot   = new HubSpot_Bridges( $this->api );
		$this->templates  = new Templates( $this->library, $this->classes, $this->variables );
		$this->site_parts = new Site_Parts( $this->templates );
		$this->site_tools = new Site_Tools();
		$this->site_parts->register_hooks();

		$this->modules->register_hooks();
		( new Abilities( $this ) )->register_hooks();

		// The admin app saves through the REST API, which doesn't run as an admin screen.
		add_action( 'rest_api_init', fn() => ( new Admin( $this ) )->register_rest() );
		if ( is_admin() ) {
			( new Admin( $this ) )->register_hooks();
			add_action( 'admin_init', array( $this, 'maybe_upgrade' ), 20 );
		}
	}

	/**
	 * Once per plugin version: create the Elementor variables that enabled modules now use,
	 * and update module and library classes the user never edited to the new defaults.
	 */
	public function maybe_upgrade() {
		if ( get_option( 'nori_fe_version' ) === NORI_FE_VERSION || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( self::atomic_available() && ! current_user_can( 'elementor_global_classes_update_class' ) ) {
			return; // Try again once Elementor has granted the class capability.
		}
		$this->sync_enabled_modules();
		update_option( 'nori_fe_version', NORI_FE_VERSION, false );
	}

	/** Make sure every enabled module's variables and classes exist, and untouched classes are current. */
	public function sync_enabled_modules() {
		$this->modules->migrate_defaults();
		$this->add_tone_colors();
		$this->site_parts->rename_old_titles();
		$this->library->reload_classes(); // Class styles follow the (possibly migrated) color settings.
		foreach ( $this->modules->enabled() as $key ) {
			if ( $this->modules->variable_keys( $key ) ) {
				$this->variables->import( $this->modules->variable_keys( $key ), $this->modules->variable_values( $key ) );
			}
		}
		if ( self::atomic_available() ) {
			$this->classes->apply_renames();
			$this->classes->remove_retired();
			$this->classes->upgrade_untouched();
			$this->classes->detach_deleted_variables();
			$this->classes->apply_order();
		}
	}

	/**
	 * line-np and muted-np (0.13.0), band-np and on-band-np (0.16.0) for sites that already use Nori's colors: made from the site's own
	 * text and background, so they match the brand already set. Dark Mode gets dark versions the same
	 * way, unless they were saved before. Only adds what's missing; never changes a color that exists.
	 */
	private function add_tone_colors() {
		$status = $this->variables->status();
		if ( is_wp_error( $status ) ) {
			return;
		}
		$live = fn( $k ) => isset( $status[ $k ] ) && in_array( $status[ $k ]['status'], array( Variables_Sync::STATUS_DEFAULT, Variables_Sync::STATUS_EDITED ), true );
		if ( ! $live( 'text' ) && ! $live( 'background' ) ) {
			return; // Nori's colors aren't in use yet; they'll come in with everything else.
		}
		$missing = array();
		foreach ( Design::TONE_KEYS as $k ) {
			if ( isset( $status[ $k ] ) && Variables_Sync::STATUS_MISSING === $status[ $k ]['status'] ) {
				$missing[] = $k;
			}
		}
		if ( $missing ) {
			$value = fn( $k, $fallback ) => Design::hex( $live( $k ) ? (string) $status[ $k ]['value'] : '' ) ? Design::hex( $status[ $k ]['value'] ) : $fallback;
			$tones = Design::tones( $value( 'text', '#111827' ), $value( 'background', '#FFFFFF' ), $value( 'surface', '' ), $value( 'primary', '' ) );
			$this->variables->import( $missing, $tones );
		}
		$saved = get_option( Modules::SETTINGS_OPTION, array() );
		$dark  = is_array( $saved ) && isset( $saved['dark-mode'] ) && is_array( $saved['dark-mode'] ) ? $saved['dark-mode'] : null;
		if ( null !== $dark && array_diff( array( 'darkLine', 'darkMuted', 'darkBand', 'darkOnBand' ), array_keys( $dark ) ) ) {
			$s     = $this->modules->settings( 'dark-mode' );
			$tones = Design::tones( $s['darkText'] ?? '', $s['darkBackground'] ?? '', $s['darkSurface'] ?? '', $s['darkPrimary'] ?? '' );
			foreach ( array( 'darkLine' => 'line', 'darkMuted' => 'muted', 'darkBand' => 'band', 'darkOnBand' => 'on-band' ) as $setting => $k ) {
				if ( ! array_key_exists( $setting, $dark ) ) {
					$s[ $setting ] = $tones[ $k ];
				}
			}
			$this->modules->save_settings( 'dark-mode', $s );
		}
	}

	/**
	 * After module settings change: untouched module classes follow the colors picked there
	 * (btn-overlay-fx-np's hover color follows the ripple text color). Edited classes keep their edits.
	 */
	public function refresh_module_classes() {
		if ( ! self::atomic_available() ) {
			return;
		}
		$this->library->reload_classes();
		$this->classes->upgrade_untouched();
		$this->classes->detach_deleted_variables();
	}
}
