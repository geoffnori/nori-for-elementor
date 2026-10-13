<?php
/**
 * Modules: their categories, and saving one category's switches, settings, pick-one choices,
 * class deletions and module colors.
 *
 * @package Nori_For_Elementor
 */

namespace Nori\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Modules_Screen {

	/** @var Plugin */
	private $plugin;

	/** @var array */
	private $var_status = array();

	/** @var array|null Color variables for the pickers: [ 'nori' => [...], 'yours' => [...] ]. */
	private $color_groups = null;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/** @return array[] Categories keyed by key, each with the module definitions it holds. */
	public function categories() {
		$all  = $this->plugin->modules->all();
		$out  = array();
		$seen = array();
		foreach ( $this->plugin->library->module_categories() as $cat ) {
			$cat['items'] = array();
			foreach ( (array) $cat['modules'] as $key ) {
				if ( isset( $all[ $key ] ) ) {
					$cat['items'][ $key ] = $all[ $key ];
					$seen[ $key ]         = true;
				}
			}
			$out[ $cat['key'] ] = $cat;
		}
		$rest = array_diff_key( $all, $seen );
		if ( $rest ) {
			$out['other'] = array(
				'key'         => 'other',
				'label'       => __( 'Other', 'nori-for-elementor' ),
				'description' => '',
				'items'       => $rest,
			);
		}
		return $out;
	}

	/* ------------------------------------------------------------------
	 * Save
	 * --------------------------------------------------------------- */

	/** @return array{0: string, 1: string} Message and notice type. */
	public function save( $cat_key, array $post ) {
		$cats = $this->categories();
		if ( ! isset( $cats[ $cat_key ] ) ) {
			return array( '', 'success' );
		}
		$cat     = $cats[ $cat_key ];
		$mods    = $this->plugin->modules;
		$keys    = array_keys( $cat['items'] );
		$parts   = array();
		$error   = false;
		$labels  = wp_list_pluck( $cat['items'], 'label' );

		// 1. Which modules are on.
		if ( ! empty( $cat['group'] ) ) {
			$rivals = array_keys( array_filter( $cat['items'], fn( $m ) => ( $m['group'] ?? '' ) === $cat['group'] ) );
			$choice = isset( $post['choice'] ) ? sanitize_key( $post['choice'] ) : '';
			$want   = in_array( $choice, $rivals, true ) ? array( $choice ) : array();
			// Modules in the category that aren't rivals are switched on and off on their own.
			$others = array_diff( $keys, $rivals );
			$want   = array_merge( $want, isset( $post['enabled'] ) ? array_values( array_intersect( array_map( 'sanitize_key', (array) $post['enabled'] ), $others ) ) : array() );
		} else {
			$want = isset( $post['enabled'] ) ? array_values( array_intersect( array_map( 'sanitize_key', (array) $post['enabled'] ), $keys ) ) : array();
		}
		$turned_on  = array();
		$turned_off = array();
		foreach ( $keys as $key ) {
			$was = $mods->is_enabled( $key );
			$now = in_array( $key, $want, true );
			if ( $was !== $now ) {
				$mods->set_enabled( $key, $now );
				if ( $now ) {
					$turned_on[] = $key;
				} else {
					$turned_off[] = $key;
				}
			}
		}

		// 2. Settings (sanitized field by field against each module's schema in Modules::save_settings()).
		$settings = isset( $post['settings'] ) && is_array( $post['settings'] ) ? $post['settings'] : array();
		$saved    = 0;
		foreach ( $keys as $key ) {
			$fields = (array) ( $cat['items'][ $key ]['settings'] ?? array() );
			if ( ! $fields || ! isset( $settings[ $key ] ) || ! is_array( $settings[ $key ] ) ) {
				continue;
			}
			$input = map_deep( $settings[ $key ], 'sanitize_text_field' );
			foreach ( $fields as $field ) {
				if ( 'toggle' === $field['type'] && ! isset( $input[ $field['key'] ] ) ) {
					$input[ $field['key'] ] = '0'; // Unchecked boxes aren't posted.
				}
			}
			foreach ( $fields as $field ) {
				if ( Modules::pickable( $field ) ) {
					$input[ $field['key'] ] = Modules::posted_value( $field, $input );
				}
			}
			$posted = $this->normalize( array_map( fn( $v ) => is_string( $v ) ? strtolower( $v ) : $v, $input ), $fields );
			if ( $posted !== $this->posted_baseline( $key, $fields ) ) {
				$mods->save_settings( $key, $input );
				$saved++;
			}
		}

		// 2b. Dark versions of the user's own colors (Dark Mode card).
		$dark_changed = 0;
		if ( in_array( 'dark-mode', $keys, true ) && isset( $post['dark_colors'] ) && is_array( $post['dark_colors'] ) ) {
			$vstatus      = $this->plugin->variables->status();
			$dark_changed = Dark_Colors::save( wp_unslash( $post['dark_colors'] ), $this->plugin->variables->color_choices(), Dark_Colors::palette_ids( is_wp_error( $vstatus ) ? array() : $vstatus ) );
		}
		if ( $dark_changed ) {
			/* translators: %d: number of colors. */
			$parts[] = sprintf( _n( 'Dark version updated for %d color.', 'Dark versions updated for %d colors.', $dark_changed, 'nori-for-elementor' ), $dark_changed );
		}

		// 3. Modules switched on get their variables and classes.
		foreach ( $turned_on as $key ) {
			if ( $mods->variable_keys( $key ) ) {
				$this->plugin->variables->import( $mods->variable_keys( $key ), $mods->variable_values( $key ) );
			}
			if ( $mods->class_keys( $key ) && Plugin::atomic_available() ) {
				$r = $this->plugin->classes->import( $mods->class_keys( $key ) );
				if ( $r['errors'] ) {
					$parts[] = reset( $r['errors'] );
					$error   = true;
				}
			}
		}

		// 4. Class deletions.
		$delete  = isset( $post['delete_classes'] ) ? array_values( array_intersect( array_map( 'sanitize_key', (array) $post['delete_classes'] ), $keys ) ) : array();
		$removed = 0;
		foreach ( $delete as $key ) {
			$r        = $this->plugin->classes->remove( $mods->class_keys( $key ) );
			$removed += count( $r['done'] );
			if ( $r['errors'] ) {
				$parts[] = reset( $r['errors'] );
				$error   = true;
			}
		}

		if ( $turned_on ) {
			/* translators: %s: list of module names. */
			$parts[] = sprintf( __( 'Turned on: %s. Their classes and variables are in Elementor.', 'nori-for-elementor' ), implode( ', ', array_map( fn( $k ) => $labels[ $k ], $turned_on ) ) );
		}
		if ( $turned_off ) {
			/* translators: %s: list of module names. */
			$parts[] = sprintf( __( 'Turned off: %s. Their classes stay on your elements but do nothing until you switch them back on.', 'nori-for-elementor' ), implode( ', ', array_map( fn( $k ) => $labels[ $k ], $turned_off ) ) );
		}
		if ( $saved ) {
			/* translators: %d: number of modules. */
			$parts[] = sprintf( _n( 'Settings saved for %d module.', 'Settings saved for %d modules.', $saved, 'nori-for-elementor' ), $saved );
		}
		if ( $removed ) {
			/* translators: %d: number of classes. */
			$parts[] = sprintf( _n( 'Deleted %d class from Elementor and every element that used it.', 'Deleted %d classes from Elementor and every element that used them.', $removed, 'nori-for-elementor' ), $removed );
		}
		if ( ! $parts ) {
			$parts[] = __( 'Nothing to change.', 'nori-for-elementor' );
		}
		return array( implode( ' ', $parts ), $error ? 'error' : 'success' );
	}

	/** The values the form showed, in the same shape as a post, so unchanged modules aren't rewritten. */
	private function posted_baseline( $key, array $fields ) {
		$status = $this->plugin->variables->status();
		$shown  = $this->plugin->modules->admin_settings( $key, is_wp_error( $status ) ? array() : $status );
		$out    = array();
		foreach ( $fields as $field ) {
			$v = $shown['values'][ $field['key'] ];
			if ( 'toggle' === $field['type'] ) {
				$v = $v ? '1' : '0';
			} elseif ( 'color' === $field['type'] ) {
				$v = strtolower( (string) $v );
			}
			$out[ $field['key'] ] = (string) $v;
		}
		return $out;
	}

	private function normalize( array $values, array $fields ) {
		$out = array();
		foreach ( $fields as $field ) {
			$out[ $field['key'] ] = (string) ( $values[ $field['key'] ] ?? '' );
		}
		return $out;
	}
}
