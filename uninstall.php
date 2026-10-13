<?php
/**
 * Removes Nori's own settings. Classes and variables you imported stay in Elementor,
 * because your pages use them.
 *
 * @package Nori_For_Elementor
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'nori_fe_state' );
delete_option( 'nori_fe_modules' );
delete_option( 'nori_fe_module_settings' );
delete_option( 'nori_fe_version' );
delete_option( 'nori_fe_hubspot_bridges' );
delete_option( 'nori_fe_hubspot_reports' );
delete_option( 'nori_fe_type_scale' );
delete_option( 'nori_fe_brand' );
delete_option( 'nori_fe_design_base' );
delete_option( 'nori_fe_variable_names' );
delete_option( 'nori_fe_links_migrated' );
delete_option( 'nori_fe_dark_colors' );
delete_option( 'nori_fe_spam_log' );
delete_transient( 'nori_fe_gzip_sizes' );
delete_site_transient( 'nori_fe_latest' ); // Left by versions before 0.23.0, which checked the website for updates.

// The private page Nori used for template previews (pages made from templates are yours and stay).
$nori_fe_preview = (int) get_option( 'nori_fe_template_preview_page' );
if ( $nori_fe_preview && 'private' === get_post_status( $nori_fe_preview ) ) {
	wp_delete_post( $nori_fe_preview, true );
}
delete_option( 'nori_fe_template_preview_page' );
delete_option( 'nori_fe_site_parts' ); // The header and footer templates themselves are yours and stay.

// Short-lived transients (spam passes, rate limits, HubSpot report throttles, pending imports).
// They expire on their own; this just doesn't leave them behind.
global $wpdb;
foreach ( array( 'nori_fe_ft_', 'nori_fe_rl_', 'nori_fe_hsr_', 'nori_fe_import_', 'nori_fe_reset_' ) as $nori_fe_prefix ) {
	$nori_fe_like = $wpdb->esc_like( '_transient_' . $nori_fe_prefix ) . '%';
	$nori_fe_tlike = $wpdb->esc_like( '_transient_timeout_' . $nori_fe_prefix ) . '%';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one-off cleanup on uninstall; there is no API to delete transients by prefix.
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $nori_fe_like, $nori_fe_tlike ) );
}
