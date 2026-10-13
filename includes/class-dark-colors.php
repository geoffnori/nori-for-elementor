<?php
/**
 * Dark versions of the user's own Elementor colors (anything besides Nori's starter colors,
 * which the Dark Mode palette covers).
 *
 * Every color variable in Elementor can get a dark partner: a custom color, or another variable
 * (brand-red uses brand-red-light in dark mode). Nothing is set by default, so a color added in
 * Elementor stays the same in dark mode until the user picks something. Like the palette, it only
 * redefines the variable's CSS names under html[data-nori-theme="dark"]; Elementor's styles are
 * never rewritten.
 *
 * Stored by variable id (stable when a variable is renamed), with its label, since Elementor prints
 * each variable as both --<id> and --<label>. Labels are refreshed whenever Nori reads the variables.
 *
 * @package Nori_For_Elementor
 */

namespace Nori\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dark_Colors {

	const OPTION = 'nori_fe_dark_colors';

	const ID_PATTERN = '/^e-gv-[a-z0-9]+$/';

	/** @return array<string, array{to: string, label: string}> Variable id => its dark partner. */
	public static function all() {
		$all = get_option( self::OPTION, array() );
		return is_array( $all ) ? $all : array();
	}

	/**
	 * Ids of Nori's starter color variables (their dark values are the Dark Mode palette).
	 *
	 * @param array $vstatus Variables_Sync::status().
	 * @return array<string, string> id => library key.
	 */
	public static function palette_ids( array $vstatus ) {
		$out = array();
		foreach ( Design::COLOR_KEYS as $key ) {
			$row = $vstatus[ $key ] ?? null;
			if ( $row && ! empty( $row['id'] ) && in_array( $row['status'], array( Variables_Sync::STATUS_DEFAULT, Variables_Sync::STATUS_EDITED ), true ) ) {
				$out[ (string) $row['id'] ] = $key;
			}
		}
		return $out;
	}

	/**
	 * Save the choices posted from the Dark Mode card: dark_colors[<id>][to] is "same", "custom" (with
	 * [custom] holding the color) or "var(<id or Nori key>)".
	 *
	 * @param array $posted  Raw post.
	 * @param array $choices Variables_Sync::color_choices() (id => label, value).
	 * @param array $palette palette_ids().
	 * @return int Number of colors whose dark partner changed.
	 */
	public static function save( array $posted, array $choices, array $palette ) {
		$before = self::all();
		$after  = $before;
		foreach ( $posted as $id => $row ) {
			$id = strtolower( (string) $id );
			if ( ! preg_match( self::ID_PATTERN, $id ) || ! isset( $choices[ $id ] ) || isset( $palette[ $id ] ) || ! is_array( $row ) ) {
				continue;
			}
			$to  = strtolower( sanitize_text_field( (string) ( $row['to'] ?? 'same' ) ) );
			$val = null;
			if ( 'custom' === $to ) {
				$val = sanitize_hex_color( (string) ( $row['custom'] ?? '' ) );
			} elseif ( preg_match( '/^var\(([a-z0-9-]+)\)$/', $to, $m ) ) {
				$ref = $m[1];
				if ( in_array( $ref, Design::COLOR_KEYS, true ) ) {
					$val = 'var(' . $ref . ')';
				} elseif ( $ref !== $id && isset( $choices[ $ref ] ) ) {
					$val = 'var(' . $ref . ')';
				}
			}
			if ( $val ) {
				$after[ $id ] = array( 'to' => $val, 'label' => $choices[ $id ]['label'] );
			} else {
				unset( $after[ $id ] );
			}
		}
		// A color may follow another variable, but not one that itself follows a variable: no chains, no loops.
		foreach ( $after as $id => $row ) {
			if ( preg_match( '/^var\((e-gv-[a-z0-9]+)\)$/', $row['to'], $m ) && isset( $after[ $m[1] ] ) && 0 === strpos( $after[ $m[1] ]['to'], 'var(' ) ) {
				unset( $after[ $id ] );
			}
		}
		if ( $after === $before ) {
			return 0;
		}
		update_option( self::OPTION, $after, false );
		$changed = 0;
		foreach ( array_unique( array_merge( array_keys( $before ), array_keys( $after ) ) ) as $id ) {
			$changed += ( $before[ $id ]['to'] ?? '' ) !== ( $after[ $id ]['to'] ?? '' ) ? 1 : 0;
		}
		return $changed;
	}

	/** Keep the stored labels current (the user may rename a variable in Elementor). */
	public static function refresh_labels( array $choices ) {
		$all     = self::all();
		$changed = false;
		foreach ( $all as $id => $row ) {
			if ( isset( $choices[ $id ] ) && $choices[ $id ]['label'] !== $row['label'] ) {
				$all[ $id ]['label'] = $choices[ $id ]['label'];
				$changed             = true;
			}
		}
		if ( $changed ) {
			update_option( self::OPTION, $all, false );
		}
	}

	/** Remove every dark partner (Reset in the Dark Mode card posts "same" for each instead). */
	public static function clear() {
		delete_option( self::OPTION );
	}

	/** The CSS declarations for dark mode: "--e-gv-1a2b3c4:#F87171;--brand-red:#F87171;". */
	public static function css() {
		$out = '';
		foreach ( self::all() as $id => $row ) {
			if ( ! preg_match( self::ID_PATTERN, (string) $id ) ) {
				continue;
			}
			$to    = (string) ( $row['to'] ?? '' );
			$value = sanitize_hex_color( $to );
			if ( ! $value && preg_match( '/^var\(([a-z0-9-]+)\)$/', $to, $m ) ) {
				$names = in_array( $m[1], Design::COLOR_KEYS, true ) ? Variables_Sync::css_names( $m[1] ) : ( preg_match( self::ID_PATTERN, $m[1] ) ? array( '--' . $m[1] ) : array() );
				$value = $names ? 'var(' . $names[0] . ')' : '';
			}
			if ( ! $value ) {
				continue;
			}
			$names = array( '--' . $id );
			if ( ! empty( $row['label'] ) && preg_match( '/^[A-Za-z0-9_-]+$/', (string) $row['label'] ) ) {
				$names[] = '--' . $row['label'];
			}
			foreach ( array_unique( $names ) as $name ) {
				$out .= $name . ':' . $value . ';';
			}
		}
		return $out;
	}

	/** For exports: by label, since ids differ per site. */
	public static function export( array $choices ) {
		$out = array();
		foreach ( self::all() as $id => $row ) {
			$to = $row['to'];
			if ( preg_match( '/^var\((e-gv-[a-z0-9]+)\)$/', $to, $m ) ) {
				if ( ! isset( $choices[ $m[1] ] ) ) {
					continue;
				}
				$to = 'var(label:' . $choices[ $m[1] ]['label'] . ')';
			}
			$out[ $choices[ $id ]['label'] ?? $row['label'] ] = $to;
		}
		return $out;
	}

	/**
	 * For imports: label => dark partner, matched to this site's variables by label. Colors this site
	 * doesn't have are skipped.
	 *
	 * @return int How many were set.
	 */
	public static function import( array $by_label, array $choices, array $palette ) {
		$ids = array();
		foreach ( $choices as $id => $c ) {
			$ids[ $c['label'] ] = $id;
		}
		$post = array();
		foreach ( $by_label as $label => $to ) {
			$label = (string) $label;
			$to    = (string) $to;
			if ( ! isset( $ids[ $label ] ) ) {
				continue;
			}
			if ( preg_match( '/^var\(label:(.+)\)$/', $to, $m ) ) {
				if ( ! isset( $ids[ $m[1] ] ) ) {
					continue;
				}
				$to = 'var(' . $ids[ $m[1] ] . ')';
			}
			$hex                    = sanitize_hex_color( $to );
			$post[ $ids[ $label ] ] = $hex ? array( 'to' => 'custom', 'custom' => $hex ) : array( 'to' => $to );
		}
		return $post ? self::save( $post, $choices, $palette ) : 0;
	}
}
