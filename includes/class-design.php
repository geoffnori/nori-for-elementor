<?php
/**
 * Design system settings: the type scale, brand presets and the dark palette.
 *
 * Everything here ends up as ordinary Elementor global classes and variables (or, for dark mode,
 * as overrides of those same variables), so nothing competes with Elementor's own styles.
 *
 * @package Nori_For_Elementor
 */

namespace Nori\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Design {

	const TYPE_OPTION  = 'nori_fe_type_scale';
	const BRAND_OPTION = 'nori_fe_brand';

	/** The site's own design: "light" (the usual) or "dark" (then Dark Mode's other values are light). */
	const BASE_OPTION = 'nori_fe_design_base';

	/** The starter color variables, in the order presets list them. */
	const COLOR_KEYS = array( 'primary', 'secondary', 'text', 'accent', 'on-accent', 'background', 'surface', 'line', 'muted', 'band', 'on-band' );

	/**
	 * Colors worked out from the others (presets don't list them): line and muted from text and
	 * background, and the band (the dark block behind footers and calls to action) with its text.
	 */
	const TONE_KEYS = array( 'line', 'muted', 'band', 'on-band' );

	/** Dark Mode module setting for each color variable. */
	const DARK_SETTINGS = array(
		'primary'    => 'darkPrimary',
		'secondary'  => 'darkSecondary',
		'text'       => 'darkText',
		'accent'     => 'darkAccent',
		'on-accent'  => 'darkOnAccent',
		'background' => 'darkBackground',
		'surface'    => 'darkSurface',
		'line'       => 'darkLine',
		'muted'      => 'darkMuted',
		'band'       => 'darkBand',
		'on-band'    => 'darkOnBand',
	);

	/** "light" or "dark": what the site's own colors (the Elementor variables) are. */
	public static function base() {
		return 'dark' === get_option( self::BASE_OPTION, 'light' ) ? 'dark' : 'light';
	}

	/* ------------------------------------------------------------------
	 * Type scale
	 * --------------------------------------------------------------- */

	const RATIOS = array(
		'1.125' => 'Major second (1.125): calm, close sizes',
		'1.2'   => 'Minor third (1.2): balanced',
		'1.25'  => 'Major third (1.25): clear steps',
		'1.333' => 'Perfect fourth (1.333): bold headings',
	);

	const WEIGHTS = array( '500', '600', '700', '800' );

	/**
	 * key, step on the scale, line height, letter spacing (em), weight ("h" = heading weight), transform.
	 * Keep in step with TYPE_STEPS in tools/build-library.py.
	 */
	public static function type_steps() {
		return array(
			array( 'type-display', 7.5, 1.05, -0.03, 'h', null ),
			array( 'type-h1', 6, 1.1, -0.02, 'h', null ),
			array( 'type-h2', 5, 1.15, -0.015, 'h', null ),
			array( 'type-h3', 4, 1.2, -0.01, 'h', null ),
			array( 'type-h4', 3, 1.25, -0.005, 'h', null ),
			array( 'type-h5', 2, 1.3, 0, 'h', null ),
			array( 'type-h6', 1, 1.35, 0, 'h', null ),
			array( 'type-subtitle', 1.5, 1.4, -0.005, '400', null ),
			array( 'type-lead', 1, 1.6, 0, '400', null ),
			array( 'type-body', 0, 1.6, 0, '400', null ),
			array( 'type-small', -1, 1.5, 0, '400', null ),
			array( 'type-caption', -1.5, 1.45, 0.01, '400', null ),
			array( 'type-overline', -1, 1.3, 0.12, '700', 'uppercase' ),
			array( 'type-label', -0.5, 1.3, 0.01, '600', null ),
		);
	}

	public static function type_defaults() {
		return array(
			'base'   => 16,
			'ratio'  => '1.25',
			'weight' => '700',
		);
	}

	public static function sanitize_type( $in ) {
		$d   = self::type_defaults();
		$in  = is_array( $in ) ? $in : array();
		$out = $d;
		if ( isset( $in['base'] ) && is_numeric( $in['base'] ) ) {
			$out['base'] = (int) max( 12, min( 24, (int) $in['base'] ) );
		}
		if ( isset( $in['ratio'] ) && isset( self::RATIOS[ (string) $in['ratio'] ] ) ) {
			$out['ratio'] = (string) $in['ratio'];
		}
		if ( isset( $in['weight'] ) && in_array( (string) $in['weight'], self::WEIGHTS, true ) ) {
			$out['weight'] = (string) $in['weight'];
		}
		return $out;
	}

	public static function type_settings() {
		return self::sanitize_type( get_option( self::TYPE_OPTION, array() ) );
	}

	/** Font size in px for a step: base x ratio^step, rounded, never under 12px. */
	public static function type_px( $base, $ratio, $step ) {
		return max( 12, (int) floor( $base * pow( $ratio, $step ) + 0.5 ) );
	}

	private static function num( $n ) {
		// Same text as Python's str() for these values (1.1, -0.015, 0.12).
		return rtrim( rtrim( sprintf( '%.4F', $n ), '0' ), '.' );
	}

	/**
	 * The type classes' styles for a scale: desktop sizes on the ratio, tablet and mobile on a gentler
	 * ratio (so headings shrink more than body text). Body text and smaller keep one size.
	 *
	 * @return array<string, array> Class key => styles.
	 */
	public static function type_styles( ?array $s = null ) {
		$s     = self::sanitize_type( null === $s ? self::type_settings() : $s );
		$base  = (int) $s['base'];
		$ratio = (float) $s['ratio'];
		$out   = array();
		foreach ( self::type_steps() as $row ) {
			list( $key, $step, $lh, $ls, $weight, $transform ) = $row;
			$desktop = array(
				'font-size'   => self::type_px( $base, $ratio, $step ) . 'px',
				'line-height' => self::num( $lh ) . 'em',
				'font-weight' => 'h' === $weight ? $s['weight'] : $weight,
			);
			if ( $ls ) {
				$desktop['letter-spacing'] = self::num( $ls ) . 'em';
			}
			if ( $transform ) {
				$desktop['text-transform'] = $transform;
			}
			$styles = array( 'desktop' => $desktop );
			if ( $step > 0 ) {
				foreach ( array( 'tablet' => 0.8, 'mobile' => 0.6 ) as $bp => $k ) {
					$size = self::type_px( $base, 1 + ( $ratio - 1 ) * $k, $step );
					if ( $size !== self::type_px( $base, $ratio, $step ) ) {
						$styles[ $bp ] = array( 'font-size' => $size . 'px' );
					}
				}
			}
			$out[ $key ] = $styles;
		}
		return $out;
	}

	/* ------------------------------------------------------------------
	 * Brand presets
	 * --------------------------------------------------------------- */

	/**
	 * Presets: light values for the starter colors, and the dark values Dark Mode switches to.
	 * Each accent is light enough for dark text, so the primary button reads in both modes.
	 */
	public static function presets() {
		$p = array(
			'nori'     => array( 'Nori lime', '#111827', '#4B5563', '#111827', '#D6FF00', '#111827', '#FFFFFF', '#F8F9FA', '#F5F5F5', '#A3A3A3', '#F5F5F5', '#D6FF00', '#111827', '#121212', '#1C1C1C' ),
			'ocean'    => array( 'Ocean', '#0C4A6E', '#075985', '#0F172A', '#7DD3FC', '#0C1A2B', '#FFFFFF', '#F0F9FF', '#E0F2FE', '#BAE6FD', '#E2E8F0', '#7DD3FC', '#0C1A2B', '#0B1220', '#111A2E' ),
			'ember'    => array( 'Ember', '#1C1917', '#57534E', '#1C1917', '#FB923C', '#1C1917', '#FFFFFF', '#FAF7F5', '#FAFAF9', '#D6D3D1', '#F5F5F4', '#FB923C', '#1C1917', '#141110', '#1F1B19' ),
			'forest'   => array( 'Forest', '#14301F', '#2F5D43', '#102418', '#86EFAC', '#0B2014', '#FFFFFF', '#F2F8F4', '#ECFDF3', '#BBF7D0', '#E7F5EC', '#86EFAC', '#0B2014', '#0C1611', '#13221A' ),
			'grape'    => array( 'Grape', '#2E1065', '#5B21B6', '#1E1B2E', '#C4B5FD', '#1E1B2E', '#FFFFFF', '#F7F5FF', '#F5F3FF', '#DDD6FE', '#EDE9FE', '#C4B5FD', '#1E1B2E', '#120E1F', '#1B1530' ),
			'graphite' => array( 'Graphite', '#18181B', '#52525B', '#18181B', '#FACC15', '#18181B', '#FFFFFF', '#F4F4F5', '#FAFAFA', '#A1A1AA', '#F4F4F5', '#FACC15', '#18181B', '#111113', '#1C1C1F' ),
		);
		$base = array_diff( self::COLOR_KEYS, self::TONE_KEYS );
		$out  = array();
		foreach ( $p as $key => $row ) {
			$out[ $key ] = array(
				'label' => $row[0],
				'light' => self::with_tones( array_combine( $base, array_slice( $row, 1, 7 ) ) ),
				'dark'  => self::with_tones( array_combine( $base, array_slice( $row, 8, 7 ) ) ),
			);
		}
		return $out;
	}

	/**
	 * A light and dark palette from one brand color (and optionally the dark "ink" color).
	 *
	 * @return array{light: array, dark: array}
	 */
	public static function derive( $accent, $ink = '' ) {
		$accent = self::hex( $accent ) ? self::hex( $accent ) : '#D6FF00';
		$ink    = self::hex( $ink ) ? self::hex( $ink ) : self::mix( '#111827', $accent, 0.12 );
		$light  = array(
			'primary'    => $ink,
			'secondary'  => self::mix( $ink, '#FFFFFF', 0.32 ),
			'text'       => $ink,
			'accent'     => $accent,
			'on-accent'  => self::readable_on( $accent, $ink ),
			'background' => '#FFFFFF',
			'surface'    => self::mix( '#FFFFFF', $accent, 0.06 ),
		);
		$paper  = self::mix( '#F5F5F5', $accent, 0.04 );
		$dark   = array(
			'primary'    => $paper,
			'secondary'  => self::mix( $paper, '#121212', 0.35 ),
			'text'       => $paper,
			'accent'     => $accent,
			'on-accent'  => self::readable_on( $accent, $ink ),
			'background' => self::mix( '#121212', $accent, 0.03 ),
			'surface'    => self::mix( '#1C1C1C', $accent, 0.05 ),
		);
		return array(
			'light' => self::with_tones( $light ),
			'dark'  => self::with_tones( $dark ),
		);
	}

	/**
	 * Line and muted colors for a text and background pair. The line is the text color at 12% over the
	 * background (14% when the background is dark, where faint lines vanish sooner). Muted text starts
	 * at 68% of the text color and moves closer to it until it reads at 4.5:1 on both the background
	 * and the surface.
	 *
	 * @return array{line: string, muted: string}
	 */
	public static function tones( $text, $background, $surface = '', $primary = '' ) {
		$text       = self::hex( $text ) ? self::hex( $text ) : '#111827';
		$background = self::hex( $background ) ? self::hex( $background ) : '#FFFFFF';
		$surface    = self::hex( $surface ) ? self::hex( $surface ) : $background;
		$primary    = self::hex( $primary ) ? self::hex( $primary ) : $text;
		$dark       = self::luminance( $background ) < 0.18;
		$amount     = 0.32;
		$muted      = self::mix( $text, $background, $amount );
		while ( $amount > 0 && min( self::contrast( $muted, $background ), self::contrast( $muted, $surface ) ) < 4.5 ) {
			$amount = max( 0, $amount - 0.04 );
			$muted  = self::mix( $text, $background, $amount );
		}
		// The band stays dark: the primary (or text) color on a light page, a lifted shade on a dark one.
		if ( $dark ) {
			$band    = self::mix( $background, $text, 0.07 );
			$on_band = $text;
		} else {
			$band    = self::contrast( $primary, $background ) >= 7 ? $primary : $text;
			$on_band = $background;
		}
		return array(
			'line'    => self::mix( $background, $text, $dark ? 0.14 : 0.12 ),
			'muted'   => $muted,
			'band'    => $band,
			'on-band' => $on_band,
		);
	}

	public static function with_tones( array $palette ) {
		return array_merge( $palette, self::tones( $palette['text'] ?? '', $palette['background'] ?? '', $palette['surface'] ?? '', $palette['primary'] ?? '' ) );
	}

	/** Which preset (if any) the live colors match. */
	public static function matching_preset( array $values ) {
		foreach ( self::presets() as $key => $p ) {
			$same = true;
			// Line and muted follow from the others, so tuning them doesn't make the preset "not current".
			foreach ( array_diff_key( $p['light'], array_flip( self::TONE_KEYS ) ) as $k => $v ) {
				if ( strtolower( (string) ( $values[ $k ] ?? '' ) ) !== strtolower( $v ) ) {
					$same = false;
					break;
				}
			}
			if ( $same ) {
				return $key;
			}
		}
		return '';
	}

	/* ------------------------------------------------------------------
	 * Color helpers
	 * --------------------------------------------------------------- */

	public static function hex( $value ) {
		$value = strtoupper( trim( (string) $value ) );
		if ( preg_match( '/^#([0-9A-F]{3})$/', $value, $m ) ) {
			$value = '#' . $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2];
		}
		return preg_match( '/^#[0-9A-F]{6}$/', $value ) ? $value : '';
	}

	private static function rgb( $hex ) {
		$hex = self::hex( $hex );
		return array( hexdec( substr( $hex, 1, 2 ) ), hexdec( substr( $hex, 3, 2 ) ), hexdec( substr( $hex, 5, 2 ) ) );
	}

	/** $a with $amount of $b mixed in. */
	public static function mix( $a, $b, $amount ) {
		$x = self::rgb( $a );
		$y = self::rgb( $b );
		$o = '#';
		for ( $i = 0; $i < 3; $i++ ) {
			$o .= sprintf( '%02X', (int) round( $x[ $i ] + ( $y[ $i ] - $x[ $i ] ) * $amount ) );
		}
		return $o;
	}

	public static function luminance( $hex ) {
		$c = array_map(
			function ( $v ) {
				$v /= 255;
				return $v <= 0.03928 ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 );
			},
			self::rgb( $hex )
		);
		return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
	}

	public static function contrast( $a, $b ) {
		$l1 = self::luminance( $a );
		$l2 = self::luminance( $b );
		return ( max( $l1, $l2 ) + 0.05 ) / ( min( $l1, $l2 ) + 0.05 );
	}

	/** The ink color if it reads on the background, otherwise white or near-black, whichever reads better. */
	public static function readable_on( $bg, $ink = '#111827' ) {
		if ( self::contrast( $bg, $ink ) >= 4.5 ) {
			return self::hex( $ink );
		}
		return self::contrast( $bg, '#FFFFFF' ) >= self::contrast( $bg, '#111111' ) ? '#FFFFFF' : '#111111';
	}
}
