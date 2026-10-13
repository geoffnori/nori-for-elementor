<?php
/**
 * Nori: Mobile Menu, server side. Marks the page when the menu button should show on tablets too (so
 * the CSS needs no JavaScript to pick the breakpoint) and gives the script its translated labels.
 *
 * @package Nori_For_Elementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'body_class',
	function ( $classes ) {
		$settings = \Nori\Elementor\Plugin::instance()->modules->settings( 'mobile-menu' );
		if ( 'tablet' === ( $settings['breakpoint'] ?? '' ) ) {
			$classes[] = 'nori-menu-tablet';
		}
		return $classes;
	}
);

add_filter(
	'nori_fe_js_config',
	function ( $config, $key ) {
		if ( 'mobile-menu' !== $key ) {
			return $config;
		}
		$config['openLabel']  = __( 'Open menu', 'nori-for-elementor' );
		$config['closeLabel'] = __( 'Close menu', 'nori-for-elementor' );
		$config['menuLabel']  = __( 'Menu', 'nori-for-elementor' );
		return $config;
	},
	10,
	2
);
