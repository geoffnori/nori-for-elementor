<?php
/**
 * Read-only access to the bundled class, variable and module library.
 *
 * @package Nori_For_Elementor
 */

namespace Nori\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Library {

	/** @var string */
	private $dir;

	/** @var array|null */
	private $classes = null;

	/** @var array|null */
	private $variables = null;

	/** @var array|null */
	private $modules = null;

	/** @var array|null */
	private $module_groups = null;

	/** @var array|null */
	private $module_categories = null;

	/** @var array|null How modules behave together (pairs, plus the meaning of each kind). */
	private $module_pairs = null;

	/** @var callable|null Maps a module-setting variable key to the variable it links to (or null). */
	private $link_resolver = null;

	/** Class styles that point at a module setting's variable follow that setting's chosen variable. */
	public function set_link_resolver( callable $resolver ) {
		$this->link_resolver = $resolver;
		$this->classes       = null;
	}

	private function resolve_links( $styles ) {
		if ( is_array( $styles ) ) {
			foreach ( $styles as $k => $v ) {
				$styles[ $k ] = $this->resolve_links( $v );
			}
			return $styles;
		}
		if ( is_string( $styles ) && preg_match( '/^var\(([a-z0-9-]+)\)$/', $styles, $m ) ) {
			$vars = $this->variables();
			if ( ! empty( $vars[ $m[1] ]['module'] ) ) {
				$to = call_user_func( $this->link_resolver, $m[1] );
				if ( $to ) {
					return 'var(' . $to . ')';
				}
			}
		}
		return $styles;
	}

	public function __construct( $dir ) {
		$this->dir = trailingslashit( $dir );
	}

	private function load( $file ) {
		$path = $this->dir . $file;
		if ( ! is_readable( $path ) ) {
			return array();
		}
		$data = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local bundled file.
		return is_array( $data ) ? $data : array();
	}

	/** @return array[] Groups, each with 'classes'. */
	public function class_groups() {
		if ( null === $this->classes ) {
			$this->classes = $this->load( 'classes.json' );
			// The type classes follow the Type scale settings (Classes > Type scale).
			$type = Design::type_styles();
			foreach ( $this->classes['groups'] ?? array() as $g => $group ) {
				foreach ( $group['classes'] as $c => $class ) {
					if ( isset( $type[ $class['key'] ] ) ) {
						$this->classes['groups'][ $g ]['classes'][ $c ]['styles'] = $type[ $class['key'] ];
					}
					if ( $this->link_resolver && ! empty( $class['module'] ) ) {
						$this->classes['groups'][ $g ]['classes'][ $c ]['styles'] = $this->resolve_links( $this->classes['groups'][ $g ]['classes'][ $c ]['styles'] ?? array() );
					}
				}
			}
		}
		return $this->classes['groups'] ?? array();
	}

	/** Forget the loaded classes, so the next read picks up new Type scale settings. */
	public function reload_classes() {
		$this->classes = null;
	}

	/** @return array[] Groups, each with 'variables'. */
	public function variable_groups() {
		if ( null === $this->variables ) {
			$this->variables = $this->load( 'variables.json' );
		}
		return $this->variables['groups'] ?? array();
	}

	/** @return array[] Module definitions keyed by module key. */
	public function modules() {
		if ( null === $this->modules ) {
			$data                = $this->load( 'modules.json' );
			$this->module_groups     = (array) ( $data['groups'] ?? array() );
			$this->module_categories = (array) ( $data['categories'] ?? array() );
			$this->module_pairs      = array(
				'pairs' => (array) ( $data['pairs'] ?? array() ),
				'help'  => (array) ( $data['pairs_help'] ?? array() ),
			);
			$this->modules       = array();
			foreach ( $data['modules'] ?? array() as $module ) {
				$this->modules[ $module['key'] ] = $module;
			}
		}
		return $this->modules;
	}

	/** @return array[] Module categories for the admin screen, in order (key, label, description, modules, group?). */
	public function module_categories() {
		$this->modules();
		return $this->module_categories;
	}

	/**
	 * How one module behaves next to the others: each pair it's in, seen from its side.
	 *
	 * @param string $key Module key.
	 * @return array[] Each: with (the other module's key), kind (pick-one, takes-turns, steps-aside,
	 *                 teams-up, fine), text.
	 */
	public function module_pairs( $key ) {
		$this->modules();
		$out = array();
		foreach ( $this->module_pairs['pairs'] as $p ) {
			$mods = (array) ( $p['modules'] ?? array() );
			$i    = array_search( $key, $mods, true );
			if ( false === $i || 2 !== count( $mods ) ) {
				continue;
			}
			$out[] = array(
				'with' => $mods[ 1 - $i ],
				'kind' => $p['kind'] ?? 'fine',
				'text' => $p['text'] ?? '',
			);
		}
		return $out;
	}

	/** @return array<string, string> What each pair kind means, keyed by kind. */
	public function module_pair_kinds() {
		$this->modules();
		return $this->module_pairs['help'];
	}

	/** @return array[] Exclusive module groups keyed by group key (label, description). */
	public function module_groups() {
		$this->modules();
		return $this->module_groups;
	}

	/** @return array<string, array> Every class keyed by library key, with its group key added. */
	public function classes() {
		$all = array();
		foreach ( $this->class_groups() as $group ) {
			foreach ( $group['classes'] as $class ) {
				$class['group'] = $group['key'];
				$all[ $class['key'] ] = $class;
			}
		}
		return $all;
	}

	/**
	 * Every variable keyed by library key: the starter set, plus the variables that module
	 * settings create (those carry 'module' and 'setting' and are managed on the Modules tab).
	 *
	 * @return array<string, array>
	 */
	public function variables() {
		$all = array();
		foreach ( $this->variable_groups() as $group ) {
			foreach ( $group['variables'] as $variable ) {
				$variable['group'] = $group['key'];
				$all[ $variable['key'] ] = $variable;
			}
		}
		foreach ( $this->modules() as $module_key => $module ) {
			foreach ( (array) ( $module['settings'] ?? array() ) as $field ) {
				if ( empty( $field['variable'] ) ) {
					continue;
				}
				$all[ $field['variable'] ] = array(
					'key'         => $field['variable'],
					'label'       => $field['variable'] . '-np',
					'type'        => 'color' === $field['type'] ? 'global-color-variable' : 'global-size-variable',
					'value'       => self::variable_value( $field, $field['default'] ),
					'description' => $module['label'] . ': ' . $field['label'],
					'group'       => 'module',
					'module'      => $module_key,
					'setting'     => $field['key'],
				);
			}
		}
		return $all;
	}

	/** A module setting value as an Elementor variable value ("650ms", "#D6FF00"). */
	public static function variable_value( array $field, $value ) {
		if ( 'range' === $field['type'] ) {
			$unit = in_array( $field['unit'] ?? '', array( 'px', 'ms', 's' ), true ) ? $field['unit'] : '';
			return ( 0 + $value ) . $unit;
		}
		return (string) $value;
	}

	/** @return string[] Keys of classes Nori shipped before and has since removed. */
	public function retired_keys() {
		$this->class_groups();
		return array_values( (array) ( $this->classes['retired'] ?? array() ) );
	}

	/** @return array<string, string> Old key => new key, for classes that were renamed. */
	public function renamed_keys() {
		$this->class_groups();
		return array_map( 'strval', (array) ( $this->classes['renamed'] ?? array() ) );
	}

	/** @return string[] Library variable keys a class's styles point at, e.g. btn-primary uses primary and background. */
	public function class_variables( $key ) {
		$class = $this->classes()[ $key ] ?? null;
		if ( ! $class ) {
			return array();
		}
		preg_match_all( '/var\(([a-z0-9-]+)\)/', (string) wp_json_encode( $class['styles'] ?? array() ), $m );
		return array_values( array_intersect( array_unique( $m[1] ), array_keys( $this->variables() ) ) );
	}

	/** @return string[] Class keys in one group. */
	public function class_keys_in_group( $group_key ) {
		foreach ( $this->class_groups() as $group ) {
			if ( $group['key'] === $group_key ) {
				return wp_list_pluck( $group['classes'], 'key' );
			}
		}
		return array();
	}
}
