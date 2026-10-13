<?php
/**
 * Nori: Form Spam Protection, server side (see includes/class-form-guard.php). Checks Elementor Pro
 * classic and Atomic form entries before Elementor processes them.
 *
 * @package Nori_For_Elementor
 */

namespace Nori\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

( new Form_Guard(
	function () {
		return Plugin::instance()->modules->settings( 'form-protection' );
	}
) )->register_hooks();

/** The front end needs admin-ajax's address to fetch the pass and report what it stopped. */
add_filter(
	'nori_fe_js_config',
	function ( $config, $key ) {
		if ( 'form-protection' === $key ) {
			$config['ajax'] = admin_url( 'admin-ajax.php' );
		}
		return $config;
	},
	10,
	2
);
