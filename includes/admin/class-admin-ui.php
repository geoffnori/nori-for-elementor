<?php
/**
 * Small shared helpers for the Nori admin.
 *
 * @package Nori_For_Elementor
 */

namespace Nori\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_UI {

	const SLUG = 'nori-for-elementor';

	/** The app's own address (no tab: the app keeps its place in the URL hash). */
	public static function app_url() {
		return add_query_arg( 'page', self::SLUG, admin_url( 'admin.php' ) );
	}

	/** A short, human-readable version of a class's styles: "gap: 14px · tablet 12px". */
	public static function spec( array $styles ) {
		$parts = array();
		foreach ( $styles as $variant => $props ) {
			$bits = array();
			foreach ( (array) $props as $prop => $value ) {
				$bits[] = $prop . ': ' . self::spec_value( $value );
				if ( count( $bits ) >= 3 ) {
					$bits[] = '…';
					break;
				}
			}
			if ( ! $bits ) {
				continue;
			}
			$label   = 'desktop' === $variant ? '' : str_replace( array( 'desktop:', ':' ), array( '', ' ' ), $variant ) . ' ';
			$parts[] = $label . implode( '; ', $bits );
			if ( count( $parts ) >= 3 ) {
				break;
			}
		}
		return implode( '  ·  ', $parts );
	}

	private static function spec_value( $value ) {
		if ( is_scalar( $value ) ) {
			return preg_replace( '/^var\(([a-z0-9-]+)\)$/', '$1-np', (string) $value );
		}
		if ( is_array( $value ) ) {
			if ( isset( $value['block-start'] ) || isset( $value['inline-start'] ) || isset( $value['block-end'] ) ) {
				return implode( ' ', array_map( 'strval', array_values( array_filter( $value, 'is_scalar' ) ) ) );
			}
			return '…';
		}
		return '';
	}
}
