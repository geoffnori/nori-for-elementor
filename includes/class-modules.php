<?php
/**
 * Front-end modules: small CSS/JS (and optional PHP) bundles that can be switched on or off,
 * each with its own settings. Only enabled modules load.
 *
 * Settings reach the front end two ways, as declared in library/modules.json:
 * "css" settings become CSS custom properties on :root, "js" settings are passed to
 * the module script as NoriFE.config[moduleKey].
 *
 * @package Nori_For_Elementor
 */

namespace Nori\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Modules {

	const OPTION          = 'nori_fe_modules';
	const SETTINGS_OPTION = 'nori_fe_module_settings';

	/** A color setting linked to a variable: "var(accent)" (a Nori color, by key) or "var(e-gv-1a2b3c4)" (any Elementor color). */
	const LINK_PATTERN = '/^var\(([a-z0-9-]+)\)$/';

	/** @var Library */
	private $library;

	/** @var Variables_Sync|null */
	private $variables = null;

	public function __construct( Library $library ) {
		$this->library = $library;
	}

	/** Lets settings be written through to the Elementor variables they are tied to. */
	public function set_variables( Variables_Sync $variables ) {
		$this->variables = $variables;
	}

	/** @return array[] Module definitions keyed by module key. */
	public function all() {
		return $this->library->modules();
	}

	/** @return array[] Exclusive groups keyed by group key. */
	public function groups() {
		return $this->library->module_groups();
	}

	/** @return string[] Enabled module keys. */
	public function enabled() {
		$enabled = get_option( self::OPTION, array() );
		$enabled = is_array( $enabled ) ? $enabled : array();
		return array_values( array_intersect( $enabled, array_keys( $this->all() ) ) );
	}

	public function is_enabled( $key ) {
		return in_array( $key, $this->enabled(), true );
	}

	/**
	 * Other modules in the same exclusive group as $key.
	 *
	 * @return string[]
	 */
	public function rivals( $key ) {
		$modules = $this->all();
		$group   = $modules[ $key ]['group'] ?? null;
		if ( ! $group ) {
			return array();
		}
		$rivals = array();
		foreach ( $modules as $other => $module ) {
			if ( $other !== $key && ( $module['group'] ?? null ) === $group ) {
				$rivals[] = $other;
			}
		}
		return $rivals;
	}

	/**
	 * Turn a module on or off. Turning one on switches off the others in its group.
	 *
	 * @return string[] Modules that were switched off because they conflict.
	 */
	public function set_enabled( $key, $on ) {
		if ( ! isset( $this->all()[ $key ] ) ) {
			return array();
		}
		$enabled  = $this->enabled();
		$disabled = array();
		if ( $on ) {
			foreach ( $this->rivals( $key ) as $rival ) {
				if ( in_array( $rival, $enabled, true ) ) {
					$disabled[] = $rival;
				}
			}
			$enabled = array_values( array_unique( array_merge( array_diff( $enabled, $disabled ), array( $key ) ) ) );
		} else {
			$enabled = array_values( array_diff( $enabled, array( $key ) ) );
		}
		update_option( self::OPTION, $enabled );
		return $disabled;
	}

	/* ------------------------------------------------------------------
	 * Color settings: a variable or a custom color
	 * --------------------------------------------------------------- */

	/** Color settings offer a variable picker; Dark Mode's own palette ("plain") stays a plain color. */
	public static function pickable( array $field ) {
		return 'color' === $field['type'] && empty( $field['plain'] );
	}

	/** The variable a color value points at ("accent", "e-gv-1a2b3c4"), or null for a custom color. */
	public static function link_ref( $value ) {
		return is_string( $value ) && preg_match( self::LINK_PATTERN, $value, $m ) ? $m[1] : null;
	}

	/** A field's default value: its linked variable if it has one, otherwise its color. */
	public static function field_default( array $field ) {
		return ! empty( $field['link'] ) && self::pickable( $field ) ? 'var(' . $field['link'] . ')' : $field['default'];
	}

	/** Is this setting currently a custom color (so it lives in its own Elementor variable)? */
	private function is_custom( array $field, $value ) {
		return ! self::pickable( $field ) || null === self::link_ref( $value );
	}

	/**
	 * The CSS reference for a linked color: the custom property Elementor prints for that variable,
	 * with a color to fall back on.
	 */
	public function link_css( $ref, $fallback ) {
		$lib = $this->library->variables();
		if ( isset( $lib[ $ref ] ) ) {
			$record = State::get( 'variables', $ref );
			$name   = ( $record && ! empty( $record['id'] ) && preg_match( '/^[a-z0-9-]+$/i', (string) $record['id'] ) ) ? $record['id'] : $ref . '-np';
			return 'var(--' . $name . ',' . ( sanitize_hex_color( (string) $lib[ $ref ]['value'] ) ? $lib[ $ref ]['value'] : $fallback ) . ')';
		}
		return 'var(--' . $ref . ',' . $fallback . ')';
	}

	/**
	 * For class styles: the variable a module-setting variable should point at instead, when that
	 * setting is linked to another variable (ripple-text => on-accent). Null when it's a custom color.
	 */
	public function class_link( $variable_key ) {
		foreach ( $this->all() as $key => $module ) {
			foreach ( (array) ( $module['settings'] ?? array() ) as $field ) {
				if ( ( $field['variable'] ?? '' ) === $variable_key && self::pickable( $field ) ) {
					return self::link_ref( $this->settings( $key )[ $field['key'] ] ?? '' );
				}
			}
		}
		return null;
	}

	/** Module-setting variable keys that aren't in use because their setting is linked to another variable. */
	public function unused_variable_keys( $key = null ) {
		$out = array();
		foreach ( $key ? array( $key => $this->all()[ $key ] ?? array() ) : $this->all() as $k => $module ) {
			$values = $this->settings( $k );
			foreach ( (array) ( $module['settings'] ?? array() ) as $field ) {
				if ( ! empty( $field['variable'] ) && ! $this->is_custom( $field, $values[ $field['key'] ] ?? '' ) ) {
					$out[] = $field['variable'];
				}
			}
		}
		return $out;
	}

	/** @return string[] Library class keys the module uses. */
	public function class_keys( $key ) {
		$module = $this->all()[ $key ] ?? array();
		return wp_list_pluck( (array) ( $module['classes'] ?? array() ), 'key' );
	}

	/** @return string[] Library variable keys the module's settings create in Elementor. */
	public function variable_keys( $key ) {
		$module = $this->all()[ $key ] ?? array();
		$values = $this->settings( $key );
		$out    = array();
		foreach ( (array) ( $module['settings'] ?? array() ) as $field ) {
			// A color linked to another variable needs no variable of its own.
			if ( ! empty( $field['variable'] ) && $this->is_custom( $field, $values[ $field['key'] ] ?? '' ) ) {
				$out[] = $field['variable'];
			}
		}
		return $out;
	}

	/**
	 * The module's settings as Elementor variable values, keyed by variable key.
	 *
	 * @param array|null $values Setting values (defaults to the saved ones).
	 * @return array<string, string>
	 */
	public function variable_values( $key, $values = null ) {
		$module = $this->all()[ $key ] ?? array();
		$values = null === $values ? $this->settings( $key ) : $values;
		$out    = array();
		foreach ( (array) ( $module['settings'] ?? array() ) as $field ) {
			if ( ! empty( $field['variable'] ) && $this->is_custom( $field, $values[ $field['key'] ] ) ) {
				$out[ $field['variable'] ] = Library::variable_value( $field, $values[ $field['key'] ] );
			}
		}
		return $out;
	}

	/**
	 * Settings as the admin screen should show them: where a setting lives in an Elementor
	 * variable, the value from Elementor wins, since it may have been fine-tuned in the editor.
	 *
	 * @param array $status Output of Variables_Sync::status().
	 * @return array{values: array, tuned: array<string, string>} tuned = setting key => value in Elementor.
	 */
	public function admin_settings( $key, array $status ) {
		$module = $this->all()[ $key ] ?? array();
		$values = $this->settings( $key );
		$tuned  = array();
		foreach ( (array) ( $module['settings'] ?? array() ) as $field ) {
			$row = empty( $field['variable'] ) || ! $this->is_custom( $field, $values[ $field['key'] ] ) ? null : ( $status[ $field['variable'] ] ?? null );
			if ( ! $row || ! in_array( $row['status'], array( Variables_Sync::STATUS_DEFAULT, Variables_Sync::STATUS_EDITED ), true ) ) {
				continue;
			}
			$live = (string) $row['value'];
			if ( strtolower( $live ) === strtolower( Library::variable_value( $field, $values[ $field['key'] ] ) ) ) {
				continue;
			}
			$tuned[ $field['key'] ] = $live;
			$parsed                 = self::parse_variable( $field, $live );
			if ( null !== $parsed ) {
				$values[ $field['key'] ] = $parsed;
			}
		}
		return array(
			'values' => $values,
			'tuned'  => $tuned,
		);
	}

	/** Read an Elementor variable value back into a setting value, or null if it doesn't fit the control. */
	public static function parse_variable( array $field, $value ) {
		$value = trim( (string) $value );
		if ( 'color' === $field['type'] ) {
			$hex = sanitize_hex_color( $value );
			return $hex ? $hex : null;
		}
		if ( 'range' !== $field['type'] || ! preg_match( '/^(-?[\d.]+)\s*([a-z%]*)$/i', $value, $m ) ) {
			return null;
		}
		$number = (float) $m[1];
		$unit   = strtolower( $m[2] );
		$want   = $field['unit'] ?? '';
		if ( 'ms' === $want && 's' === $unit ) {
			$number *= 1000;
		} elseif ( 's' === $want && 'ms' === $unit ) {
			$number /= 1000;
		} elseif ( '' !== $unit && $unit !== $want ) {
			return null;
		}
		return 0 + round( max( (float) $field['min'], min( (float) $field['max'], $number ) ), 3 );
	}

	/* ------------------------------------------------------------------
	 * Settings
	 * --------------------------------------------------------------- */

	/** @return array<string, mixed> Saved values merged over the defaults. */
	public function settings( $key ) {
		$module = $this->all()[ $key ] ?? null;
		if ( ! $module ) {
			return array();
		}
		$saved  = get_option( self::SETTINGS_OPTION, array() );
		$saved  = is_array( $saved ) && isset( $saved[ $key ] ) && is_array( $saved[ $key ] ) ? $saved[ $key ] : array();
		$values = array();
		foreach ( (array) ( $module['settings'] ?? array() ) as $field ) {
			$values[ $field['key'] ] = array_key_exists( $field['key'], $saved ) ? $saved[ $field['key'] ] : self::field_default( $field );
		}
		return $values;
	}

	/**
	 * Save a module's settings from raw input. Unknown keys are dropped, numbers are clamped.
	 */
	public function save_settings( $key, array $input ) {
		$module = $this->all()[ $key ] ?? null;
		if ( ! $module ) {
			return;
		}
		$clean = array();
		foreach ( (array) ( $module['settings'] ?? array() ) as $field ) {
			$clean[ $field['key'] ] = $this->sanitize_field( $field, self::posted_value( $field, $input ) );
		}
		$all         = get_option( self::SETTINGS_OPTION, array() );
		$all         = is_array( $all ) ? $all : array();
		$all[ $key ] = $clean;
		update_option( self::SETTINGS_OPTION, $all );
		$this->push_variables( $key );
	}

	/**
	 * A color picker posts the chosen variable, or "custom" plus the color (in "<key>__custom").
	 * Anything else is posted as is.
	 */
	public static function posted_value( array $field, array $input ) {
		$value = $input[ $field['key'] ] ?? null;
		if ( 'custom' === $value && self::pickable( $field ) ) {
			$value = $input[ $field['key'] . '__custom' ] ?? $field['default'];
		}
		return $value;
	}

	/**
	 * Write the module's settings to its Elementor variables. A custom color gets its own variable
	 * (created if needed); a color linked to another variable doesn't need one, so Nori removes the one
	 * it made. Class styles that point at these follow, through the nori_fe_settings_saved action.
	 */
	private function push_variables( $key ) {
		if ( ! $this->variables ) {
			return;
		}
		if ( $this->variable_keys( $key ) && $this->is_enabled( $key ) ) {
			$this->variables->import( $this->variable_keys( $key ), $this->variable_values( $key ) );
			$this->variables->set_values( $this->variable_values( $key ) );
		}
		$unused = $this->unused_variable_keys( $key );
		if ( $unused ) {
			$this->variables->remove( $unused );
		}
		/** Lets Nori's classes follow the new settings (and let go of removed variables). */
		do_action( 'nori_fe_settings_saved', $key );
	}

	/**
	 * Move untouched settings to new defaults after an update. A setting whose saved value (or
	 * Elementor variable) still equals one of its "previous_defaults" was never customized, so it
	 * follows the new default. Custom values are left alone.
	 *
	 * @return string[] Variable keys that were updated in Elementor.
	 */
	public function migrate_defaults() {
		$all     = get_option( self::SETTINGS_OPTION, array() );
		$all     = is_array( $all ) ? $all : array();
		$changed = false;
		$vars    = array();
		$linked  = array(); // Own variables of settings that now link to another variable by default.
		$links_done = (bool) get_option( 'nori_fe_links_migrated' );
		if ( ! $links_done ) {
			update_option( 'nori_fe_links_migrated', 1, false );
		}
		foreach ( $this->all() as $key => $module ) {
			foreach ( (array) ( $module['settings'] ?? array() ) as $field ) {
				$old = array_map( 'strtolower', (array) ( $field['previous_defaults'] ?? array() ) );
				// A color that now links to a variable used to default to its plain color.
				// Once only (0.10.0), so a custom color the user picks later is never taken back.
				$links = ! $links_done && ! empty( $field['link'] ) && self::pickable( $field );
				if ( $links ) {
					$old[] = strtolower( (string) $field['default'] );
				}
				if ( ! $old ) {
					continue;
				}
				if ( isset( $all[ $key ][ $field['key'] ] ) && in_array( strtolower( (string) $all[ $key ][ $field['key'] ] ), $old, true ) ) {
					unset( $all[ $key ][ $field['key'] ] );
					$changed = true;
				}
				if ( empty( $field['variable'] ) ) {
					continue;
				}
				if ( $links ) {
					if ( ! isset( $all[ $key ][ $field['key'] ] ) ) {
						$linked[ $field['variable'] ] = array( $key, $field['key'], $old );
					}
				} else {
					$vars[ $field['variable'] ] = array( $old, Library::variable_value( $field, $field['default'] ) );
				}
			}
		}
		if ( ( ! $vars && ! $linked ) || ! $this->variables ) {
			if ( $changed ) {
				update_option( self::SETTINGS_OPTION, $all );
			}
			return array();
		}
		$status = $this->variables->status();
		if ( is_wp_error( $status ) ) {
			if ( $changed ) {
				update_option( self::SETTINGS_OPTION, $all );
			}
			return array();
		}
		// Linked by default now: an untouched own variable goes; one fine-tuned in Elementor keeps
		// its color, as a custom color, so nothing the user chose changes.
		$remove = array();
		foreach ( $linked as $var => $info ) {
			$row = $status[ $var ] ?? null;
			if ( ! $row || ! in_array( $row['status'], array( Variables_Sync::STATUS_DEFAULT, Variables_Sync::STATUS_EDITED ), true ) ) {
				continue;
			}
			$live = strtolower( (string) $row['value'] );
			if ( in_array( $live, $info[2], true ) ) {
				$remove[] = $var;
			} elseif ( sanitize_hex_color( $live ) ) {
				$all[ $info[0] ][ $info[1] ] = $live;
				$changed                     = true;
			}
		}
		if ( $changed ) {
			update_option( self::SETTINGS_OPTION, $all );
		}
		if ( $remove ) {
			$this->variables->remove( $remove );
		}
		$update = array();
		foreach ( $vars as $var => $info ) {
			$live = strtolower( (string) ( $status[ $var ]['value'] ?? '' ) );
			if ( '' !== $live && in_array( $live, $info[0], true ) ) {
				$update[ $var ] = $info[1];
			}
		}
		return $update ? $this->variables->set_values( $update, $status ) : array();
	}

	public function reset_settings( $key ) {
		$all = get_option( self::SETTINGS_OPTION, array() );
		if ( is_array( $all ) && isset( $all[ $key ] ) ) {
			unset( $all[ $key ] );
			update_option( self::SETTINGS_OPTION, $all );
		}
		$this->push_variables( $key );
	}

	private function sanitize_field( array $field, $value ) {
		switch ( $field['type'] ) {
			case 'toggle':
				return ! empty( $value ) && '0' !== $value;
			case 'color':
				$ref = self::pickable( $field ) ? self::link_ref( is_string( $value ) ? strtolower( trim( $value ) ) : '' ) : null;
				if ( null !== $ref ) {
					$lib = $this->library->variables();
					$ok  = ( isset( $lib[ $ref ] ) && empty( $lib[ $ref ]['module'] ) && 'global-color-variable' === $lib[ $ref ]['type'] ) || preg_match( '/^e-gv-[a-z0-9]+$/', $ref );
					return $ok ? 'var(' . $ref . ')' : self::field_default( $field );
				}
				$color = sanitize_hex_color( is_string( $value ) ? $value : '' );
				return $color ? $color : self::field_default( $field );
			case 'select':
				$value = is_string( $value ) ? sanitize_key( $value ) : '';
				return isset( $field['options'][ $value ] ) ? $value : $field['default'];
			case 'range':
			default:
				if ( ! is_numeric( $value ) ) {
					return $field['default'];
				}
				$value = max( (float) $field['min'], min( (float) $field['max'], (float) $value ) );
				return 0 + round( $value, 3 );
		}
	}

	/* ------------------------------------------------------------------
	 * Front end
	 * --------------------------------------------------------------- */

	public function register_hooks() {
		foreach ( $this->enabled() as $key ) {
			$module = $this->all()[ $key ];
			if ( ! empty( $module['php'] ) ) {
				$file = NORI_FE_DIR . 'modules/' . $key . '/' . basename( $module['php'] );
				if ( is_readable( $file ) ) {
					require_once $file;
				}
			}
		}
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/** CSS custom properties for one module's settings, e.g. "--marquee-speed:35s;". */
	public function css_vars( $key ) {
		$module = $this->all()[ $key ] ?? array();
		$values = $this->settings( $key );
		$out    = '';
		foreach ( (array) ( $module['settings'] ?? array() ) as $field ) {
			if ( empty( $field['css'] ) ) {
				continue;
			}
			$value = $values[ $field['key'] ];
			$ref   = self::pickable( $field ) ? self::link_ref( $value ) : null;
			if ( null !== $ref ) {
				$out .= $field['css'] . ':' . $this->link_css( $ref, $field['default'] ) . ';';
				continue;
			}
			if ( 'range' === $field['type'] ) {
				$value = $value . ( in_array( $field['unit'] ?? '', array( 'ms', 'px', 's', '%', 'deg' ), true ) ? $field['unit'] : '' );
			}
			// A setting tied to an Elementor variable follows that variable, so a change made in the
			// Elementor editor shows up on the site. The saved setting is the fallback (for example on
			// pages without Elementor's global styles, or if the variable was deleted).
			if ( ! empty( $field['variable'] ) ) {
				$record = State::get( 'variables', $field['variable'] );
				if ( $record && ! empty( $record['id'] ) && preg_match( '/^[a-z0-9-]+$/i', (string) $record['id'] ) ) {
					$value = 'var(--' . $record['id'] . ',' . $value . ')';
				}
			}
			$out .= $field['css'] . ':' . $value . ';';
		}
		return $out;
	}

	/** JS config for one module's settings. */
	public function js_config( $key ) {
		$module = $this->all()[ $key ] ?? array();
		$values = $this->settings( $key );
		$config = array();
		foreach ( (array) ( $module['settings'] ?? array() ) as $field ) {
			if ( ! empty( $field['js'] ) ) {
				$config[ $field['key'] ] = $values[ $field['key'] ];
			}
		}
		/** Lets a module add data its script needs beyond its settings (for example the HubSpot bridges). */
		return (array) apply_filters( 'nori_fe_js_config', $config, $key );
	}

	public function enqueue() {
		$enabled = $this->enabled();
		if ( empty( $enabled ) ) {
			return;
		}

		$needs_core = false;
		foreach ( $enabled as $key ) {
			if ( ! empty( $this->all()[ $key ]['js'] ) ) {
				$needs_core = true;
			}
		}

		$vars   = '';
		$config = array();
		foreach ( $enabled as $key ) {
			$vars .= $this->css_vars( $key );
			$js    = $this->js_config( $key );
			if ( $js ) {
				$config[ $key ] = $js;
			}
		}

		if ( $needs_core ) {
			// Loads in <head> so the "nori-js" flag is set before first paint.
			wp_enqueue_script( 'nori-fe-core', NORI_FE_URL . 'assets/js/core.js', array(), NORI_FE_VERSION, false );
			wp_add_inline_script( 'nori-fe-core', 'window.NoriFEConfig=' . wp_json_encode( (object) $config ) . ';', 'before' );
		}

		// One small stylesheet handle carries every module's CSS variables.
		wp_register_style( 'nori-fe-settings', false, array(), NORI_FE_VERSION );
		wp_enqueue_style( 'nori-fe-settings' );
		if ( '' !== $vars ) {
			wp_add_inline_style( 'nori-fe-settings', ':root{' . $vars . '}' );
		}

		$shared_done = array();
		foreach ( $enabled as $key ) {
			$module = $this->all()[ $key ];
			$deps   = $needs_core ? array( 'nori-fe-core' ) : array();

			foreach ( (array) ( $module['shared_css'] ?? array() ) as $path ) {
				$handle = 'nori-fe-shared-' . sanitize_key( basename( $path, '.css' ) );
				if ( ! isset( $shared_done[ $handle ] ) ) {
					wp_enqueue_style( $handle, NORI_FE_URL . ltrim( $path, '/' ), array(), NORI_FE_VERSION );
					$shared_done[ $handle ] = true;
				}
			}
			// Shared scripts (like the carousel engine) load once, before the modules that use them.
			foreach ( (array) ( $module['shared_js'] ?? array() ) as $path ) {
				$handle = 'nori-fe-shared-' . sanitize_key( basename( $path, '.js' ) ) . '-js';
				if ( ! isset( $shared_done[ $handle ] ) ) {
					wp_enqueue_script( $handle, NORI_FE_URL . ltrim( $path, '/' ), $needs_core ? array( 'nori-fe-core' ) : array(), NORI_FE_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
					$shared_done[ $handle ] = true;
				}
				$deps[] = $handle;
			}
			foreach ( (array) ( $module['vendor_js'] ?? array() ) as $i => $path ) {
				$handle = 'nori-fe-' . $key . '-vendor-' . $i;
				wp_enqueue_script( $handle, NORI_FE_URL . ltrim( $path, '/' ), array(), NORI_FE_VERSION, array( 'in_footer' => true ) );
				$deps[] = $handle;
			}
			foreach ( (array) ( $module['css'] ?? array() ) as $i => $file ) {
				wp_enqueue_style( 'nori-fe-' . $key . ( $i ? '-' . $i : '' ), NORI_FE_URL . 'modules/' . $key . '/' . basename( $file ), array( 'nori-fe-settings' ), NORI_FE_VERSION );
			}
			foreach ( (array) ( $module['js'] ?? array() ) as $i => $file ) {
				wp_enqueue_script(
					'nori-fe-' . $key . ( $i ? '-' . $i : '' ),
					NORI_FE_URL . 'modules/' . $key . '/' . basename( $file ),
					$deps,
					NORI_FE_VERSION,
					array(
						'in_footer' => true,
						'strategy'  => 'defer',
					)
				);
			}
		}
	}
}
