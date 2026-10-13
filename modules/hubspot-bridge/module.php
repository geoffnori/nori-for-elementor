<?php
/**
 * Nori: HubSpot Bridge, server side. Passes the bridges to the front-end script and takes
 * short status reports back ("HubSpot accepted the entry" or its error type), so WP Admin can
 * show whether a bridge is working. No form values are ever sent to or stored by WordPress.
 *
 * @package Nori_For_Elementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'nori_fe_js_config',
	function ( $config, $key ) {
		if ( Nori\Elementor\HubSpot_Bridges::MODULE !== $key ) {
			return $config;
		}
		$config['bridges'] = Nori\Elementor\Plugin::instance()->hubspot->js_config();
		if ( current_user_can( 'edit_posts' ) ) {
			$config['report'] = esc_url_raw( rest_url( 'nori/v1/hubspot-report' ) );
			$config['nonce']  = wp_create_nonce( 'wp_rest' );
		}
		return $config;
	},
	10,
	2
);

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'nori/v1',
			'/hubspot-report',
			array(
				'methods'             => 'POST',
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
				'args'                => array(
					'bridge' => array(
						'type'     => 'string',
						'required' => true,
						'pattern'  => '^[a-z0-9]{5}$',
					),
					'ok'     => array(
						'type'     => 'boolean',
						'required' => true,
					),
					'errors' => array(
						'type'    => 'array',
						'default' => array(),
					),
				),
				'callback'            => function ( WP_REST_Request $request ) {
					$saved = Nori\Elementor\Plugin::instance()->hubspot->record_report(
						(string) $request['bridge'],
						(bool) $request['ok'],
						(array) $request['errors']
					);
					return rest_ensure_response( array( 'saved' => $saved ) );
				},
			)
		);
	}
);
