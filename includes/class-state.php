<?php
/**
 * Remembers which library items were imported and what they looked like at import.
 *
 * Elementor owns the actual classes and variables. This option only stores the
 * link (library key => Elementor id) and a fingerprint of the default, so the
 * plugin can tell "untouched" from "edited in Elementor".
 *
 * @package Nori_For_Elementor
 */

namespace Nori\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class State {

	const OPTION = 'nori_fe_state';

	public static function all() {
		$state = get_option( self::OPTION, array() );
		$state = is_array( $state ) ? $state : array();
		return wp_parse_args(
			$state,
			array(
				'classes'   => array(),
				'variables' => array(),
			)
		);
	}

	public static function get( $type, $key ) {
		$state = self::all();
		return $state[ $type ][ $key ] ?? null;
	}

	public static function set( $type, $key, array $record ) {
		$state                  = self::all();
		$state[ $type ][ $key ] = $record;
		update_option( self::OPTION, $state, false );
	}

	public static function forget( $type, $key ) {
		$state = self::all();
		unset( $state[ $type ][ $key ] );
		update_option( self::OPTION, $state, false );
	}

	/**
	 * Stable fingerprint of a class's variants or a variable's value.
	 */
	public static function fingerprint( $data ) {
		return md5( (string) wp_json_encode( self::canonical( $data ) ) );
	}

	private static function canonical( $data ) {
		if ( ! is_array( $data ) ) {
			// Treat 12 and 12.0 and "12" alike so harmless number formatting isn't an "edit".
			return is_numeric( $data ) ? 0 + $data : $data;
		}
		$is_list = array() === $data || array_keys( $data ) === range( 0, count( $data ) - 1 );
		$out     = array();
		foreach ( $data as $k => $v ) {
			// A missing field and an empty one (Elementor saves "custom_css": null) mean the same thing.
			if ( ! $is_list && null === $v ) {
				continue;
			}
			$out[ $k ] = self::canonical( $v );
		}
		if ( ! $is_list ) {
			ksort( $out );
		}
		return $out;
	}
}
