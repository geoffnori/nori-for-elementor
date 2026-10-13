<?php
/**
 * Imports library variables into Elementor, reports whether they were edited, and resets them.
 *
 * @package Nori_For_Elementor
 */

namespace Nori\Elementor;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Variables_Sync {

	const STATUS_MISSING = 'missing';
	const STATUS_DEFAULT = 'default';
	const STATUS_EDITED  = 'edited';
	const STATUS_DELETED = 'deleted';
	/** Size variables are an Elementor Pro feature; without Pro, Elementor hides them. */
	const STATUS_NEEDS_PRO = 'needs_pro';

	/** Option: key => [ id, label ] of each live variable, for Dark Mode's CSS. */
	const NAMES_OPTION = 'nori_fe_variable_names';

	/** @var Library */
	private $library;

	/** @var Elementor_Api */
	private $api;

	public function __construct( Library $library, Elementor_Api $api ) {
		$this->library = $library;
		$this->api     = $api;
	}

	private function elementor_variables() {
		return $this->api->list_variables();
	}

	public static function type_available( $type ) {
		if ( 'global-size-variable' !== $type ) {
			return true;
		}
		return class_exists( '\\Elementor\\Utils' ) && method_exists( '\\Elementor\\Utils', 'has_pro' ) && \Elementor\Utils::has_pro();
	}

	/**
	 * Find the Elementor variable for a library key: first by the saved link, then by label.
	 *
	 * @return array{0: string|null, 1: array|null} [ id, data ]
	 */
	private function locate( $key, array $library_var, array $existing ) {
		$record = State::get( 'variables', $key );
		if ( $record && isset( $existing[ $record['id'] ] ) ) {
			return array( $record['id'], $existing[ $record['id'] ] );
		}
		foreach ( $existing as $id => $data ) {
			if ( ( $data['label'] ?? '' ) === $library_var['label'] && empty( $data['deleted_at'] ) && empty( $data['deleted'] ) ) {
				return array( (string) $id, $data );
			}
		}
		return array( null, null );
	}

	/**
	 * @return array|WP_Error Map of key => [ status, id, value, default ].
	 */
	public function status() {
		$existing = $this->elementor_variables();
		if ( is_wp_error( $existing ) ) {
			return $existing;
		}

		$out   = array();
		$names = array();
		foreach ( $this->library->variables() as $key => $var ) {
			list( $id, $data ) = $this->locate( $key, $var, $existing );
			$status            = self::STATUS_MISSING;
			if ( ! self::type_available( $var['type'] ) ) {
				$status = self::STATUS_NEEDS_PRO;
			} elseif ( $data ) {
				if ( ! empty( $data['deleted_at'] ) || ! empty( $data['deleted'] ) ) {
					$status = self::STATUS_DELETED;
				} else {
					$status = $this->same_value( self::plain( $data['value'] ?? '' ), $var['value'] ) ? self::STATUS_DEFAULT : self::STATUS_EDITED;
				}
			}
			$out[ $key ] = array(
				'status'  => $status,
				'id'      => $id,
				'value'   => isset( $data['value'] ) ? self::plain( $data['value'] ) : null,
				'default' => $var['value'],
			);
			if ( $id && in_array( $status, array( self::STATUS_DEFAULT, self::STATUS_EDITED ), true ) && ! empty( $data['label'] ) ) {
				$names[ $key ] = array( (string) $id, (string) $data['label'] );
			}
		}
		// Dark Mode overrides these CSS names on the front end, where asking Elementor is too slow.
		if ( get_option( self::NAMES_OPTION ) !== $names ) {
			update_option( self::NAMES_OPTION, $names, false );
		}
		return $out;
	}

	/**
	 * The CSS custom properties Elementor prints for a variable: --<id> and --<label>.
	 *
	 * @return string[]
	 */
	public static function css_names( $key ) {
		$names = (array) get_option( self::NAMES_OPTION, array() );
		$out   = array();
		if ( ! empty( $names[ $key ] ) ) {
			foreach ( $names[ $key ] as $n ) {
				if ( preg_match( '/^[A-Za-z0-9_-]+$/', $n ) ) {
					$out[] = '--' . $n;
				}
			}
		} else {
			$record = State::get( 'variables', $key );
			if ( $record && ! empty( $record['id'] ) && preg_match( '/^[A-Za-z0-9_-]+$/', $record['id'] ) ) {
				$out[] = '--' . $record['id'];
			}
			$out[] = '--' . $key . '-np';
		}
		return array_values( array_unique( $out ) );
	}

	/** Size variables can come back as { size, unit }; everything else is a string. */
	public static function plain( $value ) {
		if ( is_array( $value ) ) {
			$unit = $value['unit'] ?? '';
			return (string) ( $value['size'] ?? '' ) . ( 'custom' === $unit ? '' : $unit );
		}
		return (string) $value;
	}

	private function same_value( $a, $b ) {
		return strtolower( trim( (string) $a ) ) === strtolower( trim( (string) $b ) );
	}

	/**
	 * Import library variables that aren't in Elementor yet. Existing ones are never changed.
	 *
	 * @param string[]             $keys   Library keys (empty = all).
	 * @param array<string,string> $values Optional starting values by key (module settings use this).
	 * @return array{done: string[], skipped: string[], errors: array}
	 */
	public function import( array $keys = array(), array $values = array() ) {
		$status = $this->status();
		if ( is_wp_error( $status ) ) {
			return array( 'done' => array(), 'skipped' => array(), 'errors' => array( 'all' => $status->get_error_message() ) );
		}

		$library = $this->library->variables();
		$keys    = $keys ? array_intersect( $keys, array_keys( $library ) ) : array_keys( $library );
		$result  = array( 'done' => array(), 'skipped' => array(), 'errors' => array() );

		foreach ( $keys as $key ) {
			$var = $library[ $key ];
			$row = $status[ $key ];

			if ( self::STATUS_NEEDS_PRO === $row['status'] ) {
				$result['skipped'][] = $key;
			} elseif ( self::STATUS_MISSING === $row['status'] ) {
				$created = $this->api->create_variable( $var['type'], $var['label'], $values[ $key ] ?? $var['value'] );
				if ( is_wp_error( $created ) || empty( $created['id'] ) ) {
					$result['errors'][ $key ] = is_wp_error( $created ) ? $created->get_error_message() : __( 'No id returned.', 'nori-for-elementor' );
					continue;
				}
				State::set( 'variables', $key, array( 'id' => (string) $created['id'] ) );
				$result['done'][] = $key;
			} elseif ( self::STATUS_DELETED === $row['status'] ) {
				$restored = $this->api->restore_variable( $row['id'] );
				if ( is_wp_error( $restored ) ) {
					$result['errors'][ $key ] = $restored->get_error_message();
					continue;
				}
				State::set( 'variables', $key, array( 'id' => $row['id'] ) );
				$result['done'][] = $key;
			} else {
				// Already there (maybe edited): link it, leave the value alone.
				State::set( 'variables', $key, array( 'id' => $row['id'] ) );
				$result['skipped'][] = $key;
			}
		}

		return $result;
	}

	/**
	 * Put edited variables back to the library default.
	 *
	 * @param string[] $keys Library keys.
	 */
	public function reset( array $keys ) {
		$status  = $this->status();
		$library = $this->library->variables();
		$result  = array( 'done' => array(), 'skipped' => array(), 'errors' => array() );
		if ( is_wp_error( $status ) ) {
			$result['errors']['all'] = $status->get_error_message();
			return $result;
		}

		foreach ( array_intersect( $keys, array_keys( $library ) ) as $key ) {
			$row = $status[ $key ];
			if ( self::STATUS_EDITED !== $row['status'] ) {
				$result['skipped'][] = $key;
				continue;
			}
			$updated = $this->api->update_variable( $row['id'], $library[ $key ]['label'], $library[ $key ]['value'] );
			if ( is_wp_error( $updated ) ) {
				$result['errors'][ $key ] = $updated->get_error_message();
				continue;
			}
			$result['done'][] = $key;
		}

		return $result;
	}

	/**
	 * Delete library variables from Elementor. Elements that used them fall back to no value,
	 * so Nori's own classes are re-converted with literal values if they are reset later.
	 *
	 * @param string[] $keys Library keys.
	 * @return array{done: string[], skipped: string[], errors: array}
	 */
	public function remove( array $keys ) {
		$result = array( 'done' => array(), 'skipped' => array(), 'errors' => array() );
		$status = $this->status();
		if ( is_wp_error( $status ) ) {
			$result['errors']['all'] = $status->get_error_message();
			return $result;
		}
		foreach ( array_intersect( $keys, array_keys( $status ) ) as $key ) {
			$row = $status[ $key ];
			if ( ! in_array( $row['status'], array( self::STATUS_DEFAULT, self::STATUS_EDITED ), true ) ) {
				$result['skipped'][] = $key;
				continue;
			}
			$deleted = $this->api->delete_variable( $row['id'] );
			if ( is_wp_error( $deleted ) ) {
				$result['errors'][ $key ] = $deleted->get_error_message();
				continue;
			}
			$result['done'][] = $key;
		}
		return $result;
	}

	/**
	 * Set the value of variables that are live in Elementor. Missing ones are left alone.
	 *
	 * @param array<string,string> $values Map of key => value.
	 * @param array|null           $status Output of status(), if already fetched.
	 * @return string[] Keys that were updated.
	 */
	public function set_values( array $values, $status = null ) {
		$status  = $status ? $status : $this->status();
		$library = $this->library->variables();
		$done    = array();
		if ( is_wp_error( $status ) ) {
			return $done;
		}
		foreach ( $values as $key => $value ) {
			$row = $status[ $key ] ?? null;
			if ( ! $row || ! isset( $library[ $key ] ) || ! in_array( $row['status'], array( self::STATUS_DEFAULT, self::STATUS_EDITED ), true ) ) {
				continue;
			}
			if ( $this->same_value( $row['value'] ?? '', $value ) ) {
				continue;
			}
			$label   = $this->current_label( $row['id'] ) ?? $library[ $key ]['label'];
			$updated = $this->api->update_variable( $row['id'], $label, $value );
			if ( ! is_wp_error( $updated ) ) {
				$done[] = $key;
			}
		}
		return $done;
	}

	/** The variable's label in Elementor now (the user may have renamed it). */
	private function current_label( $id ) {
		$existing = $this->elementor_variables();
		return is_wp_error( $existing ) ? null : ( $existing[ $id ]['label'] ?? null );
	}

	/**
	 * Every live color variable in Elementor, Nori's and the user's: id => [ label, value ].
	 *
	 * @return array<string, array{label: string, value: string}>
	 */
	public function color_choices() {
		$existing = $this->elementor_variables();
		$out      = array();
		if ( is_wp_error( $existing ) ) {
			return $out;
		}
		foreach ( $existing as $id => $data ) {
			if ( ! empty( $data['deleted_at'] ) || ! empty( $data['deleted'] ) || 'global-color-variable' !== ( $data['type'] ?? '' ) ) {
				continue;
			}
			$out[ (string) $id ] = array(
				'label' => (string) ( $data['label'] ?? $id ),
				'value' => self::plain( $data['value'] ?? '' ),
			);
		}
		return $out;
	}

	/**
	 * Variables the class converter can reference: key => [ id|null, type, value ].
	 */
	public function reference_map() {
		$status = $this->status();
		$map    = array();
		foreach ( $this->library->variables() as $key => $var ) {
			$row         = is_wp_error( $status ) ? null : $status[ $key ];
			$usable      = $row && in_array( $row['status'], array( self::STATUS_DEFAULT, self::STATUS_EDITED ), true );
			$map[ $key ] = array(
				'id'    => $usable ? $row['id'] : null,
				'type'  => $var['type'],
				'value' => $var['value'],
			);
		}
		// Any Elementor color variable by its id, for class styles that link to one of the user's colors.
		foreach ( $this->color_choices() as $id => $c ) {
			$map[ strtolower( $id ) ] = array( 'id' => $id, 'type' => 'global-color-variable', 'value' => $c['value'] );
		}
		return $map;
	}
}
