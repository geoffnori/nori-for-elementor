<?php
/**
 * Nori: Font Preconnect. Opens the connection to your font provider early so text renders sooner.
 * Google Fonts is skipped when Elementor is set to load Google Fonts from your own server.
 *
 * @package Nori_For_Elementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'wp_resource_hints',
	function ( $urls, $relation_type ) {
		if ( 'preconnect' !== $relation_type ) {
			return $urls;
		}
		$settings = \Nori\Elementor\Plugin::instance()->modules->settings( 'font-preconnect' );
		if ( ! empty( $settings['google'] ) && '1' !== get_option( 'elementor_local_google_fonts' ) ) {
			$urls[] = 'https://fonts.googleapis.com';
			$urls[] = array(
				'href'        => 'https://fonts.gstatic.com',
				'crossorigin' => 'anonymous',
			);
		}
		if ( ! empty( $settings['typekit'] ) ) {
			$urls[] = 'https://use.typekit.net';
			$urls[] = array(
				'href'        => 'https://p.typekit.net',
				'crossorigin' => 'anonymous',
			);
		}
		return $urls;
	},
	10,
	2
);
