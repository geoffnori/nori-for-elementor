<?php
/**
 * Turns the library's friendly CSS values into Elementor's typed Atomic props.
 *
 * The conversion walks Elementor's own style schema at runtime, so the typed
 * keys (e.g. "size", "dimensions", "border-radius-v2") always match whatever
 * Elementor version is installed. Anything the schema can't express is kept
 * as the class variant's custom CSS, so nothing is silently dropped.
 *
 * @package Nori_For_Elementor
 */

namespace Nori\Elementor;

use Elementor\Modules\AtomicWidgets\PropTypes\Base\Array_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Base\Object_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Contracts\Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Primitives\Boolean_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Primitives\Number_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Size_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Union_Prop_Type;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Style_Converter {

	/** Library value like "var(primary)" points at a Nori variable by key. */
	const VAR_PATTERN = '/^var\(\s*([a-z0-9_-]+)\s*\)$/i';

	/** @var array<string, Prop_Type> */
	private $schema;

	/**
	 * Variables by library key: [ 'id' => string|null, 'type' => string, 'value' => string ].
	 *
	 * @var array<string, array>
	 */
	private $variables;

	public function __construct( array $schema, array $variables = [] ) {
		$this->schema    = $schema;
		$this->variables = $variables;
	}

	/**
	 * Convert a library "styles" map into Elementor class variants.
	 *
	 * @param array $styles Map of "breakpoint" or "breakpoint:state" => [ css-prop => friendly value ].
	 * @return array{variants: array, fallback: array} Fallback lists props that went to custom CSS.
	 */
	public function to_variants( array $styles ): array {
		$variants = [];
		$fallback = [];

		foreach ( $styles as $target => $css ) {
			$parts      = explode( ':', (string) $target, 2 );
			$breakpoint = $parts[0] ? $parts[0] : 'desktop';
			$state      = $parts[1] ?? null;

			$converted = $this->convert_props( (array) $css );
			$variant   = [
				'meta'  => [
					'breakpoint' => $breakpoint,
					'state'      => $state,
				],
				'props' => $converted['props'],
			];

			if ( '' !== $converted['custom_css'] ) {
				$variant['custom_css'] = [ 'raw' => base64_encode( $converted['custom_css'] ) ]; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Elementor stores class custom CSS base64-encoded.
				foreach ( $converted['fallback'] as $prop ) {
					$fallback[] = $target . ' ' . $prop;
				}
			}

			$variants[] = $variant;
		}

		return [
			'variants' => $variants,
			'fallback' => $fallback,
		];
	}

	/**
	 * @return array{props: array, custom_css: string, fallback: string[]}
	 */
	public function convert_props( array $css ): array {
		$css      = $this->merge_logical_margins( $css );
		$props    = [];
		$custom   = [];
		$fallback = [];

		foreach ( $css as $prop => $value ) {
			$typed = isset( $this->schema[ $prop ] ) ? $this->convert( $this->schema[ $prop ], $value ) : null;

			if ( null === $typed ) {
				$declaration = $this->to_declaration( $prop, $value );
				if ( null !== $declaration ) {
					$custom[]   = $declaration;
					$fallback[] = $prop;
				}
				continue;
			}

			$props[ $prop ] = $typed;
		}

		return [
			'props'      => $props,
			'custom_css' => implode( "\n", $custom ),
			'fallback'   => $fallback,
		];
	}

	/**
	 * Convert one friendly value to the typed shape of $type, or null if it can't be expressed.
	 *
	 * @param Prop_Type $type  Target prop type from the schema.
	 * @param mixed     $value Friendly value.
	 * @return array|null
	 */
	public function convert( Prop_Type $type, $value ) {
		if ( null === $value ) {
			return null;
		}

		// Objects handle var() themselves (e.g. background: "var(primary)" becomes its color field).
		if ( is_string( $value ) && ! ( $type instanceof Object_Prop_Type ) && preg_match( self::VAR_PATTERN, $value, $m ) ) {
			return $this->convert_variable( $type, strtolower( $m[1] ) );
		}

		if ( $type instanceof Union_Prop_Type ) {
			foreach ( $type->get_prop_types() as $sub_type ) {
				$converted = $this->convert( $sub_type, $value );
				if ( null !== $converted ) {
					return $converted;
				}
			}
			return null;
		}

		if ( $type instanceof Size_Prop_Type ) {
			$size = $this->parse_size( $value );
			return null === $size ? null : $this->checked( $type, $type::generate( $size ) );
		}

		if ( $type instanceof Object_Prop_Type ) {
			return $this->convert_object( $type, $value );
		}

		if ( $type instanceof Array_Prop_Type ) {
			if ( ! is_array( $value ) || ! $this->is_list( $value ) ) {
				return null;
			}
			$items = [];
			foreach ( $value as $item ) {
				$converted = $this->convert( $type->get_item_type(), $item );
				if ( null === $converted ) {
					return null;
				}
				$items[] = $converted;
			}
			return $this->checked( $type, $type::generate( $items ) );
		}

		return $this->convert_plain( $type, $value );
	}

	private function convert_object( Object_Prop_Type $type, $value ) {
		$shape = $type->get_shape();

		// A plain color for an object with a color field (e.g. background: "#111827").
		if ( is_string( $value ) && isset( $shape['color'] ) ) {
			$value = [ 'color' => $value ];
		}

		if ( ! is_array( $value ) || $this->is_list( $value ) ) {
			return null;
		}

		$out = [];
		foreach ( $value as $key => $sub_value ) {
			if ( ! isset( $shape[ $key ] ) ) {
				return null; // Unknown field: this isn't the right shape.
			}
			if ( null === $sub_value ) {
				continue;
			}
			$converted = $this->convert( $shape[ $key ], $sub_value );
			if ( null === $converted ) {
				return null;
			}
			$out[ $key ] = $converted;
		}

		if ( empty( $out ) ) {
			return null;
		}

		return $this->checked( $type, $type::generate( $out ) );
	}

	private function convert_plain( Prop_Type $type, $value ) {
		if ( ! method_exists( $type, 'generate' ) ) {
			return null;
		}

		if ( $type instanceof Number_Prop_Type ) {
			if ( ! is_numeric( $value ) ) {
				return null;
			}
			$value = 0 + $value;
		} elseif ( $type instanceof Boolean_Prop_Type ) {
			if ( ! is_bool( $value ) ) {
				return null;
			}
		} elseif ( is_int( $value ) || is_float( $value ) ) {
			$value = (string) $value;
		} elseif ( ! is_string( $value ) ) {
			return null;
		}

		return $this->checked( $type, $type::generate( $value ) );
	}

	private function convert_variable( Prop_Type $type, string $key ) {
		if ( ! isset( $this->variables[ $key ] ) ) {
			return null;
		}

		$variable = $this->variables[ $key ];

		// Reference the Elementor variable when this prop accepts that variable type.
		if ( ! empty( $variable['id'] ) && $type instanceof Union_Prop_Type ) {
			$var_type = $type->get_prop_type( $variable['type'] );
			if ( $var_type && method_exists( $var_type, 'generate' ) ) {
				$ref = $var_type::generate( $variable['id'] );
				if ( $var_type->validate( $ref ) ) {
					return $ref;
				}
			}
		}

		// Otherwise use the variable's default value directly.
		return $this->convert( $type, $variable['value'] );
	}

	/**
	 * "12px" => [12, px]; "100%"; "-1px"; "1.5rem"; "200ms"; "auto"; 0 => [0, px].
	 */
	public function parse_size( $value ) {
		if ( is_int( $value ) || is_float( $value ) ) {
			return [
				'size' => $value,
				'unit' => 'px',
			];
		}
		if ( ! is_string( $value ) ) {
			return null;
		}
		$value = trim( $value );
		if ( 'auto' === strtolower( $value ) ) {
			return [
				'size' => '',
				'unit' => 'auto',
			];
		}
		if ( ! preg_match( '/^(-?\d*\.?\d+)\s*(px|%|em|rem|vw|vh|ch|vmin|vmax|s|ms|deg|rad|grad|turn|fr)?$/i', $value, $m ) ) {
			return null;
		}
		$number = 0 + $m[1];
		return [
			'size' => $number,
			'unit' => isset( $m[2] ) && '' !== $m[2] ? strtolower( $m[2] ) : 'px',
		];
	}

	/**
	 * Fold margin-inline-start/end etc. into a single margin dimensions value.
	 */
	private function merge_logical_margins( array $css ): array {
		foreach ( [ 'margin', 'padding' ] as $box ) {
			$sides = [];
			foreach ( [ 'block-start', 'block-end', 'inline-start', 'inline-end' ] as $side ) {
				$long = $box . '-' . $side;
				if ( array_key_exists( $long, $css ) ) {
					$sides[ $side ] = $css[ $long ];
					unset( $css[ $long ] );
				}
			}
			if ( $sides ) {
				$existing    = isset( $css[ $box ] ) && is_array( $css[ $box ] ) ? $css[ $box ] : [];
				$css[ $box ] = array_merge( $existing, $sides );
			}
		}
		return $css;
	}

	private function to_declaration( string $prop, $value ) {
		if ( is_string( $value ) || is_int( $value ) || is_float( $value ) ) {
			$value = (string) $value;
		} elseif ( is_array( $value ) && ! $this->is_list( $value ) && $this->all_scalar( $value ) && in_array( $prop, [ 'margin', 'padding', 'border-width' ], true ) ) {
			$value = implode( ' ', [ $value['block-start'] ?? '0', $value['inline-end'] ?? '0', $value['block-end'] ?? '0', $value['inline-start'] ?? '0' ] );
		} else {
			return null;
		}

		// Keep custom CSS to a safe character set: no braces, semicolons or tags.
		if ( ! preg_match( '/^[a-z-]+$/', $prop ) || preg_match( '/[{};<>]/', $value ) ) {
			return null;
		}

		return $prop . ': ' . $value . ';';
	}

	private function checked( Prop_Type $type, array $typed ) {
		return $type->validate( $typed ) ? $typed : null;
	}

	private function is_list( array $value ): bool {
		return [] === $value || array_keys( $value ) === range( 0, count( $value ) - 1 );
	}

	private function all_scalar( array $value ): bool {
		foreach ( $value as $v ) {
			if ( ! is_scalar( $v ) ) {
				return false;
			}
		}
		return true;
	}
}
