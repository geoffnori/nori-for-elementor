<?php
/**
 * The Nori menu item in WP Admin. The screen is one app (assets/admin/app.js) with a sidebar and one
 * save bar; Admin_App gives it its state and saves through the REST API.
 *
 * @package Nori_For_Elementor
 */

namespace Nori\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once NORI_FE_DIR . 'includes/admin/class-admin-ui.php';
require_once NORI_FE_DIR . 'includes/admin/class-admin-modules-screen.php';
require_once NORI_FE_DIR . 'includes/admin/class-admin-overview.php';
require_once NORI_FE_DIR . 'includes/admin/class-admin-hubspot.php';
require_once NORI_FE_DIR . 'includes/admin/class-admin-spam.php';
require_once NORI_FE_DIR . 'includes/admin/class-admin-design.php';
require_once NORI_FE_DIR . 'includes/admin/class-admin-templates.php';
require_once NORI_FE_DIR . 'includes/admin/class-admin-app.php';

class Admin {

	const SLUG = Admin_UI::SLUG;
	const CAP  = 'manage_options';

	/** Where the plugin can be downloaded and read about. */
	const SITE_URL = 'https://noriforelement.com/';

	/** Nori is free; this is where people can chip in. A plain link: no Ko-fi script loads in WP Admin. */
	const SUPPORT_URL = 'https://ko-fi.com/E6H3280AYK';

	/** @var Plugin */
	private $plugin;

	/** @var Admin_Modules_Screen */
	private $modules_screen;

	/** @var Admin_Overview */
	private $overview;

	/** @var Admin_HubSpot */
	private $hubspot;

	/** @var Admin_Templates */
	private $templates;

	/** @var Admin_App */
	private $app;

	public function __construct( Plugin $plugin ) {
		$this->plugin         = $plugin;
		$this->modules_screen = new Admin_Modules_Screen( $plugin );
		$this->overview       = new Admin_Overview( $plugin );
		$this->hubspot        = new Admin_HubSpot( $plugin );
		$this->templates      = new Admin_Templates( $plugin );
		$this->app            = new Admin_App( $plugin, new Admin_Design( $plugin ), $this->modules_screen, $this->overview, $this->hubspot, $this->templates );
	}

	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		$this->hubspot->register_hooks();
		( new Admin_Spam() )->register_hooks();
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'admin_body_class', array( $this, 'body_class' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( NORI_FE_FILE ), array( $this, 'action_links' ) );
		add_filter( 'plugin_row_meta', array( $this, 'row_meta' ), 10, 2 );
	}

	/** The app's state and save, for code outside WP Admin (the abilities). */
	public function app() {
		return $this->app;
	}

	/** The app's REST routes (registered on every request, since REST calls aren't admin screens). */
	public function register_rest() {
		$this->app->routes();
	}

	public function menu() {
		add_menu_page(
			__( 'Nori for Elementor', 'nori-for-elementor' ),
			__( 'Nori', 'nori-for-elementor' ),
			self::CAP,
			self::SLUG,
			array( $this, 'render' ),
			self::menu_icon(),
			59
		);
	}

	/**
	 * The menu icon: a square with an N cut out. As an SVG data URI, WordPress recolors it to
	 * match the admin color scheme, like the built-in dashicons.
	 */
	private static function menu_icon() {
		$svg = (string) file_get_contents( NORI_FE_DIR . 'assets/admin/nori-menu-icon.svg' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin file.
		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- required format for menu icons.
	}

	public function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( Admin_UI::app_url() ) . '">' . esc_html__( 'Open', 'nori-for-elementor' ) . '</a>' );
		return $links;
	}

	/** Plugins screen: a support link under the description, next to "Visit plugin site". */
	public function row_meta( $links, $file ) {
		if ( plugin_basename( NORI_FE_FILE ) === $file ) {
			$links[] = '<a href="' . esc_url( self::SUPPORT_URL ) . '" target="_blank" rel="noopener">' . esc_html__( 'Support on Ko-fi', 'nori-for-elementor' ) . '</a>';
		}
		return $links;
	}

	private function is_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $screen && 'toplevel_page_' . self::SLUG === $screen->id;
	}

	public function body_class( $classes ) {
		return $this->is_screen() ? $classes . ' nori-admin' : $classes;
	}

	/** The app: one script, one stylesheet, and the state it starts from. */
	public function assets( $hook ) {
		if ( 'toplevel_page_' . self::SLUG !== $hook ) {
			return;
		}
		wp_enqueue_style( 'nori-fe-app', NORI_FE_URL . 'assets/admin/app.css', array(), NORI_FE_VERSION );
		wp_enqueue_script( 'nori-fe-app', NORI_FE_URL . 'assets/admin/app.js', array( 'wp-i18n' ), NORI_FE_VERSION, true );
		wp_set_script_translations( 'nori-fe-app', 'nori-for-elementor', NORI_FE_DIR . 'languages' );
		$boot = array(
			'rest'  => esc_url_raw( rest_url( Admin_App::REST_NS . '/admin/' ) ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
			'mark'  => NORI_FE_URL . 'assets/admin/nori-mark.svg',
			'state' => $this->app->state(),
		);
		wp_add_inline_script( 'nori-fe-app', 'window.NoriApp = ' . wp_json_encode( $boot ) . ';', 'before' );
	}

	/** One root element; the app draws everything in it. */
	public function render() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		echo '<div class="wrap nori-app-wrap"><h1 class="screen-reader-text">' . esc_html__( 'Nori for Elementor', 'nori-for-elementor' ) . '</h1><div id="nori-app"><noscript><p>' . esc_html__( 'Nori\'s admin needs JavaScript.', 'nori-for-elementor' ) . '</p></noscript></div></div>';
	}
}
