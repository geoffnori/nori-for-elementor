<?php
/**
 * Design saves: the type scale, and brand presets applied straight to the color variables.
 *
 * @package Nori_For_Elementor
 */

namespace Nori\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Design {

	/** @var Plugin */
	private $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/* ------------------------------------------------------------------
	 * Type scale
	 * --------------------------------------------------------------- */

	/** @return array{0: string, 1: string} */
	public function save_type_scale( array $post ) {
		$new = Design::sanitize_type(
			array(
				'base'   => $post['type_base'] ?? null,
				'ratio'  => $post['type_ratio'] ?? null,
				'weight' => $post['type_weight'] ?? null,
			)
		);
		update_option( Design::TYPE_OPTION, $new );
		$this->plugin->library->reload_classes();
		if ( ! Plugin::atomic_available() ) {
			return array( __( 'Type scale saved. The type classes will use it when you add them.', 'nori-for-elementor' ), 'success' );
		}
		$r      = $this->plugin->classes->upgrade_untouched();
		$status = $this->plugin->classes->status();
		$kept   = is_wp_error( $status ) ? array() : array_keys( array_filter( $status, fn( $row, $k ) => 0 === strpos( $k, 'type-' ) && 'edited' === $row['status'], ARRAY_FILTER_USE_BOTH ) );
		$done   = array_filter( $r['done'], fn( $k ) => 0 === strpos( $k, 'type-' ) );
		/* translators: %d: number of classes. */
		$msg = sprintf( _n( 'Type scale saved. Updated %d type class in Elementor.', 'Type scale saved. Updated %d type classes in Elementor.', count( $done ), 'nori-for-elementor' ), count( $done ) );
		if ( $kept ) {
			/* translators: %s: list of class names. */
			$msg .= ' ' . sprintf( __( 'Kept your edits on %s: use Reset to put them on the new scale.', 'nori-for-elementor' ), implode( ', ', array_map( fn( $k ) => $k . '-np', $kept ) ) );
		}
		if ( $r['errors'] ) {
			return array( $msg . ' ' . reset( $r['errors'] ), 'error' );
		}
		return array( $msg, 'success' );
	}

	/* ------------------------------------------------------------------
	 * Brand presets
	 * --------------------------------------------------------------- */

	/** @return array{0: string, 1: string} */
	public function apply_brand( array $post ) {
		$key = isset( $post['preset'] ) ? sanitize_key( (string) $post['preset'] ) : '';
		if ( 'custom' === $key ) {
			$accent = Design::hex( $post['brand_accent'] ?? '' );
			if ( ! $accent ) {
				return array( __( 'Pick a brand color first.', 'nori-for-elementor' ), 'error' );
			}
			$ink     = Design::hex( $post['brand_ink'] ?? '' );
			$palette = Design::derive( $accent, $ink );
			$name    = __( 'your brand colors', 'nori-for-elementor' );
			update_option( Design::BRAND_OPTION, array( 'preset' => 'custom', 'accent' => $accent, 'ink' => $ink ), false );
		} else {
			$presets = Design::presets();
			if ( ! isset( $presets[ $key ] ) ) {
				return array( __( 'Choose a preset first.', 'nori-for-elementor' ), 'error' );
			}
			$palette = array(
				'light' => $presets[ $key ]['light'],
				'dark'  => $presets[ $key ]['dark'],
			);
			$name    = $presets[ $key ]['label'];
			update_option( Design::BRAND_OPTION, array( 'preset' => $key ), false );
		}

		// Light: the Elementor variables themselves (missing ones are created with the preset's value).
		$added = $this->plugin->variables->import( Design::COLOR_KEYS, $palette['light'] );
		$set   = $this->plugin->variables->set_values( $palette['light'] );
		// Dark: the Dark Mode module's colors (kept even while the module is off).
		$dark = $this->plugin->modules->settings( 'dark-mode' );
		foreach ( Design::DARK_SETTINGS as $k => $setting ) {
			$dark[ $setting ] = $palette['dark'][ $k ];
		}
		$this->plugin->modules->save_settings( 'dark-mode', $dark );

		/* translators: 1: preset name, 2: number of variables. */
		$msg = sprintf( __( 'Applied %1$s: %2$d color variables updated in Elementor, and the Dark Mode colors set.', 'nori-for-elementor' ), $name, count( array_unique( array_merge( $added['done'], $set ) ) ) );
		if ( $added['errors'] ) {
			return array( $msg . ' ' . reset( $added['errors'] ), 'error' );
		}
		return array( $msg, 'success' );
	}
}
