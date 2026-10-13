<?php
/**
 * Plugin Name:       Nori for Elementor
 * Plugin URI:        https://noriforelement.com/
 * Description:       Editable Atomic global classes and variables, plus lightweight animation and interaction modules, for Elementor 4.
 * Version:           0.24.4
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Requires Plugins:  elementor
 * Author:            Geoff Nori
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       nori-for-elementor
 *
 * @package Nori_For_Elementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NORI_FE_VERSION', '0.24.4' );
define( 'NORI_FE_FILE', __FILE__ );
define( 'NORI_FE_DIR', plugin_dir_path( __FILE__ ) );
define( 'NORI_FE_URL', plugin_dir_url( __FILE__ ) );
define( 'NORI_FE_MIN_ELEMENTOR', '4.0.0' );

require_once NORI_FE_DIR . 'includes/class-plugin.php';

add_action( 'plugins_loaded', array( 'Nori\Elementor\Plugin', 'boot' ) );
