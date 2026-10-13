<?php
/**
 * Export a site's Nori setup to JSON and import it on another site.
 *
 * The file carries module switches and settings, the values of Nori's variables, and which
 * Nori classes are in Elementor, with the full styles of the ones that were edited.
 * Variable references inside class styles are stored by name (not by the site-specific id),
 * so they reconnect to the matching variables on the site that imports the file.
 *
 * Only Nori's own classes, variables and settings are ever touched.
 *
 * @package Nori_For_Elementor
 */

namespace Nori\Elementor;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Transfer {

	const FORMAT       = 'nori-for-elementor';
	const FILE_VERSION = 1;
	const VAR_TYPES    = array( 'global-color-variable', 'global-size-variable', 'global-font-variable', 'global-custom-size-variable' );

	/** @var Plugin */
	private $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/* ------------------------------------------------------------------
	 * Export
	 * --------------------------------------------------------------- */

	/** @return array The export as data (encode with wp_json_encode). */
	public function export() {
		$data = array(
			'format'   => self::FORMAT,
			'version'  => self::FILE_VERSION,
			'nori'     => NORI_FE_VERSION,
			'site'     => wp_parse_url( home_url(), PHP_URL_HOST ),
			'exported' => gmdate( 'c' ),
			'modules'   => array(),
			'variables' => array(),
			'classes'   => array(),
			'hubspot'   => array(),
			'design'    => array(
				'type_scale' => Design::type_settings(),
				'brand'      => (array) get_option( Design::BRAND_OPTION, array() ),
				'base'       => Design::base(),
				// The dark versions of the user's own colors, by variable name (ids differ per site).
				'dark_colors' => Dark_Colors::export( $this->plugin->variables->color_choices() ),
			),
		);

		// HubSpot bridges: only public HubSpot ids and field names (no secrets exist).
		foreach ( $this->plugin->hubspot->all() as $bridge ) {
			$data['hubspot'][] = array_intersect_key( $bridge, array_flip( array( 'id', 'name', 'portal', 'form', 'region', 'fields' ) ) );
		}

		// A color linked to one of the user's own Elementor variables goes by its name: ids differ per site.
		$colors = null;
		foreach ( array_keys( $this->plugin->modules->all() ) as $key ) {
			$settings = $this->plugin->modules->settings( $key );
			foreach ( $settings as $k => $v ) {
				$ref = Modules::link_ref( $v );
				if ( $ref && 0 === strpos( $ref, 'e-gv-' ) ) {
					$colors         = null === $colors ? $this->plugin->variables->color_choices() : $colors;
					$settings[ $k ] = isset( $colors[ $ref ] ) ? 'var(label:' . $colors[ $ref ]['label'] . ')' : $v;
				}
			}
			$data['modules'][ $key ] = array(
				'enabled'  => $this->plugin->modules->is_enabled( $key ),
				'settings' => $settings,
			);
		}

		$vars = $this->plugin->variables->status();
		if ( ! is_wp_error( $vars ) ) {
			foreach ( $vars as $key => $row ) {
				if ( in_array( $row['status'], array( 'default', 'edited' ), true ) ) {
					$data['variables'][ $key ] = $row['value'];
				}
			}
		}

		if ( Plugin::atomic_available() ) {
			$status = $this->plugin->classes->status();
			if ( ! is_wp_error( $status ) ) {
				$edited = array_filter( $status, fn( $r ) => 'edited' === $r['status'] );
				$items  = $edited ? $this->plugin->api->get_classes( wp_list_pluck( $edited, 'id' ) ) : array();
				$names  = $this->variable_names();
				foreach ( $status as $key => $row ) {
					if ( 'missing' === $row['status'] ) {
						continue;
					}
					$entry = array(
						'status' => $row['status'],
						'label'  => $row['label'],
					);
					if ( 'edited' === $row['status'] && ! is_wp_error( $items ) && ! empty( $items[ $row['id'] ]['variants'] ) ) {
						$entry['variants'] = $this->to_portable( $items[ $row['id'] ]['variants'], $names );
					}
					$data['classes'][ $key ] = $entry;
				}
			}
		}
		return $data;
	}

	/** Map of Elementor variable id => [ key (Nori) or null, label ]. */
	private function variable_names() {
		$out = array();
		$all = $this->plugin->api->list_variables();
		if ( is_wp_error( $all ) ) {
			return $out;
		}
		foreach ( $all as $id => $v ) {
			$out[ (string) $id ] = array( null, $v['label'] ?? '' );
		}
		$status = $this->plugin->variables->status();
		if ( ! is_wp_error( $status ) ) {
			foreach ( $status as $key => $row ) {
				if ( $row['id'] && isset( $out[ $row['id'] ] ) ) {
					$out[ $row['id'] ][0] = $key;
				}
			}
		}
		return $out;
	}

	/** Replace site-specific variable ids with "nori:key" or "label:name". */
	private function to_portable( $value, array $names ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		if ( isset( $value['$$type'], $value['value'] ) && in_array( $value['$$type'], self::VAR_TYPES, true ) && is_string( $value['value'] ) ) {
			$ref            = $names[ $value['value'] ] ?? null;
			$value['value'] = $ref ? ( $ref[0] ? 'nori:' . $ref[0] : 'label:' . $ref[1] ) : 'label:';
			return $value;
		}
		foreach ( $value as $k => $v ) {
			$value[ $k ] = $this->to_portable( $v, $names );
		}
		return $value;
	}

	/* ------------------------------------------------------------------
	 * Import
	 * --------------------------------------------------------------- */

	/** @return array|WP_Error The export data, checked and trimmed to what this plugin knows. */
	public function parse( $json ) {
		$data = json_decode( trim( (string) $json ), true );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'nori_fe_import_json', __( 'That isn\'t valid JSON. Paste the JSON export (not the readable summary), or upload the .json file.', 'nori-for-elementor' ) );
		}
		if ( ( $data['format'] ?? '' ) !== self::FORMAT ) {
			return new WP_Error( 'nori_fe_import_format', __( 'This doesn\'t look like a Nori for Elementor export.', 'nori-for-elementor' ) );
		}
		if ( (int) ( $data['version'] ?? 0 ) > self::FILE_VERSION ) {
			return new WP_Error( 'nori_fe_import_version', __( 'This export was made by a newer version of Nori. Update Nori on this site first.', 'nori-for-elementor' ) );
		}
		$modules   = $this->plugin->modules->all();
		$variables = $this->plugin->library->variables();
		$classes   = $this->plugin->library->classes();
		// Colors linked to a variable by name (see export): this site's variable of that name, if any.
		$colors = null;
		foreach ( (array) ( $data['modules'] ?? array() ) as $mk => $m ) {
			foreach ( (array) ( $m['settings'] ?? array() ) as $k => $v ) {
				if ( is_string( $v ) && preg_match( '/^var\(label:(.+)\)$/', $v, $lm ) ) {
					$colors = null === $colors ? $this->plugin->variables->color_choices() : $colors;
					$found  = null;
					foreach ( $colors as $id => $c ) {
						if ( $c['label'] === $lm[1] ) {
							$found = $id;
						}
					}
					if ( $found ) {
						$data['modules'][ $mk ]['settings'][ $k ] = 'var(' . $found . ')';
					} else {
						unset( $data['modules'][ $mk ]['settings'][ $k ] ); // Not on this site: keep this site's setting.
					}
				}
			}
		}
		// Files exported before a class was renamed use its old key.
		foreach ( $this->plugin->library->renamed_keys() as $old => $new ) {
			if ( isset( $data['classes'][ $old ] ) && ! isset( $data['classes'][ $new ] ) ) {
				$data['classes'][ $new ] = $data['classes'][ $old ];
				unset( $data['classes'][ $old ] );
			}
		}
		return array(
			'nori'      => sanitize_text_field( (string) ( $data['nori'] ?? '' ) ),
			'site'      => sanitize_text_field( (string) ( $data['site'] ?? '' ) ),
			'exported'  => sanitize_text_field( (string) ( $data['exported'] ?? '' ) ),
			'modules'   => array_intersect_key( (array) ( $data['modules'] ?? array() ), $modules ),
			'variables' => array_filter( array_map( array( __CLASS__, 'css_value' ), array_intersect_key( (array) ( $data['variables'] ?? array() ), $variables ) ), 'strlen' ),
			'classes'   => array_intersect_key( (array) ( $data['classes'] ?? array() ), $classes ),
			'design'    => array(
				'type_scale' => isset( $data['design']['type_scale'] ) ? Design::sanitize_type( $data['design']['type_scale'] ) : null,
				'brand'      => isset( $data['design']['brand'] ) && is_array( $data['design']['brand'] ) ? array_filter(
					array(
						'preset' => sanitize_key( (string) ( $data['design']['brand']['preset'] ?? '' ) ),
						'accent' => Design::hex( $data['design']['brand']['accent'] ?? '' ),
						'ink'    => Design::hex( $data['design']['brand']['ink'] ?? '' ),
					)
				) : null,
				'base'        => isset( $data['design']['base'] ) && in_array( $data['design']['base'], array( 'light', 'dark' ), true ) ? $data['design']['base'] : null,
				'dark_colors' => isset( $data['design']['dark_colors'] ) && is_array( $data['design']['dark_colors'] ) ? array_filter(
					array_map( fn( $v ) => is_string( $v ) ? sanitize_text_field( $v ) : '', $data['design']['dark_colors'] ),
					fn( $v, $k ) => '' !== $v && is_string( $k ) && preg_match( '/^[A-Za-z0-9_-]{1,100}$/', $k ),
					ARRAY_FILTER_USE_BOTH
				) : array(),
			),
			'hubspot'   => array_values(
				array_filter(
					array_map(
						fn( $b ) => is_array( $b ) && preg_match( '/^[a-z0-9]{5}$/', (string) ( $b['id'] ?? '' ) ) ? array(
							'id'     => (string) $b['id'],
							'name'   => sanitize_text_field( (string) ( $b['name'] ?? '' ) ),
							'portal' => preg_replace( '/\D/', '', (string) ( $b['portal'] ?? '' ) ),
							'form'   => strtolower( sanitize_text_field( (string) ( $b['form'] ?? '' ) ) ),
							'region' => sanitize_key( (string) ( $b['region'] ?? 'na1' ) ),
							'fields' => HubSpot_Bridges::sanitize_fields( (array) ( $b['fields'] ?? array() ) ),
						) : null,
						array_slice( (array) ( $data['hubspot'] ?? array() ), 0, 50 )
					)
				)
			),
		);
	}

	/**
	 * What an import would change on this site, without changing anything.
	 *
	 * @return array Lists of keys per change, plus warnings.
	 */
	public function plan( array $data ) {
		$mods = $this->plugin->modules;
		$plan = array(
			'modules_on'   => array(),
			'modules_off'  => array(),
			'settings'     => array(),
			'vars_add'     => array(),
			'vars_set'     => array(),
			'vars_pro'     => array(),
			'classes_add'  => array(),
			'classes_edit' => array(),
			'conflicts'    => array(),
			'kept'         => array(),
			'bridges_add'  => array(),
			'type_scale'   => false,
			'warnings'     => array(),
		);
		if ( ! empty( $data['design']['type_scale'] ) && $data['design']['type_scale'] !== Design::type_settings() ) {
			$plan['type_scale'] = true;
		}

		$have = $this->plugin->hubspot->all();
		foreach ( (array) ( $data['hubspot'] ?? array() ) as $b ) {
			if ( ! isset( $have[ $b['id'] ] ) ) {
				$plan['bridges_add'][] = $b['id'];
			}
		}

		foreach ( $data['modules'] as $key => $m ) {
			$want = ! empty( $m['enabled'] );
			if ( $want && ! $mods->is_enabled( $key ) ) {
				$plan['modules_on'][] = $key;
			} elseif ( ! $want && $mods->is_enabled( $key ) ) {
				$plan['modules_off'][] = $key;
			}
			if ( isset( $m['settings'] ) && is_array( $m['settings'] ) ) {
				$now = $mods->settings( $key );
				foreach ( $now as $k => $v ) {
					if ( array_key_exists( $k, $m['settings'] ) && is_scalar( $m['settings'][ $k ] ) && is_scalar( $v ) && strtolower( (string) $m['settings'][ $k ] ) !== strtolower( (string) $v ) ) {
						$plan['settings'][] = $key;
						break;
					}
				}
			}
		}

		$vstatus = $this->plugin->variables->status();
		$vstatus = is_wp_error( $vstatus ) ? array() : $vstatus;
		foreach ( $data['variables'] as $key => $value ) {
			$row = $vstatus[ $key ] ?? null;
			if ( ! $row || 'needs_pro' === $row['status'] ) {
				$plan['vars_pro'][] = $key;
			} elseif ( in_array( $row['status'], array( 'missing', 'deleted' ), true ) ) {
				$plan['vars_add'][] = $key;
			} elseif ( strtolower( (string) $row['value'] ) !== strtolower( $value ) ) {
				$plan['vars_set'][] = $key;
			}
		}

		if ( $data['classes'] && ! Plugin::atomic_available() ) {
			$plan['warnings'][] = __( 'Elementor\'s Atomic editor isn\'t active on this site, so classes will be skipped.', 'nori-for-elementor' );
		} elseif ( $data['classes'] ) {
			$cstatus = $this->plugin->classes->status();
			$cstatus = is_wp_error( $cstatus ) ? array() : $cstatus;
			$edited  = array_filter( $cstatus, fn( $r ) => 'edited' === $r['status'] );
			$current = $edited ? $this->plugin->api->get_classes( wp_list_pluck( $edited, 'id' ) ) : array();
			$current = is_wp_error( $current ) ? array() : $current;
			$names   = $current ? $this->variable_names() : array();
			foreach ( $data['classes'] as $key => $c ) {
				$here     = $cstatus[ $key ]['status'] ?? 'missing';
				$imported = ! empty( $c['variants'] ) && 'edited' === ( $c['status'] ?? '' );
				if ( $imported && 'edited' === $here ) {
					$mine = $current[ $cstatus[ $key ]['id'] ]['variants'] ?? null;
					if ( null !== $mine && State::fingerprint( $this->to_portable( $mine, $names ) ) === State::fingerprint( (array) $c['variants'] ) ) {
						continue; // Already the same on both sites.
					}
				}
				if ( 'missing' === $here ) {
					$plan['classes_add'][] = $key;
					if ( $imported ) {
						$plan['classes_edit'][] = $key;
					}
				} elseif ( $imported && 'edited' === $here ) {
					$plan['conflicts'][] = $key;
				} elseif ( $imported ) {
					$plan['classes_edit'][] = $key;
				} elseif ( 'edited' === $here ) {
					$plan['kept'][] = $key;
				}
			}
		}
		if ( $plan['vars_pro'] ) {
			$plan['warnings'][] = __( 'Some size variables need Elementor Pro on this site, so they will be skipped. The matching module settings still come across.', 'nori-for-elementor' );
		}
		return $plan;
	}

	/**
	 * Apply an import.
	 *
	 * @param string $conflicts "keep" keeps this site's edited classes, "import" uses the file's.
	 * @return array{done: string[], errors: string[], warnings: string[]}
	 */
	public function apply( array $data, $conflicts = 'keep' ) {
		$plan  = $this->plan( $data );
		$mods  = $this->plugin->modules;
		$out   = array(
			'done'     => array(),
			'errors'   => array(),
			'warnings' => array(),
		);
		$add = function ( $n, $text ) use ( &$out ) {
			if ( $n ) {
				$out['done'][] = sprintf( $text, $n );
			}
		};

		// 1. Modules: switch off first, so a pick-one group ends up with the imported choice.
		foreach ( $plan['modules_off'] as $key ) {
			$mods->set_enabled( $key, false );
		}
		foreach ( $plan['modules_on'] as $key ) {
			$mods->set_enabled( $key, true );
		}
		foreach ( $data['modules'] as $key => $m ) {
			if ( isset( $m['settings'] ) && is_array( $m['settings'] ) && in_array( $key, $plan['settings'], true ) ) {
				$mods->save_settings( $key, array_merge( $mods->settings( $key ), $m['settings'] ) ); // Sanitized field by field.
			}
		}
		$add( count( $plan['modules_on'] ), /* translators: %d: number. */ _n( '%d module switched on', '%d modules switched on', count( $plan['modules_on'] ), 'nori-for-elementor' ) );
		$add( count( $plan['modules_off'] ), /* translators: %d: number. */ _n( '%d module switched off', '%d modules switched off', count( $plan['modules_off'] ), 'nori-for-elementor' ) );
		$add( count( $plan['settings'] ), /* translators: %d: number. */ _n( 'settings updated for %d module', 'settings updated for %d modules', count( $plan['settings'] ), 'nori-for-elementor' ) );

		// The type scale, before classes, so type classes are added (or updated) on the imported scale.
		if ( $plan['type_scale'] ) {
			update_option( Design::TYPE_OPTION, $data['design']['type_scale'] );
			$this->plugin->library->reload_classes();
			if ( Plugin::atomic_available() ) {
				$this->plugin->classes->upgrade_untouched();
			}
			$out['done'][] = __( 'type scale updated', 'nori-for-elementor' );
		}
		if ( ! empty( $data['design']['brand'] ) ) {
			update_option( Design::BRAND_OPTION, $data['design']['brand'], false );
		}
		if ( ! empty( $data['design']['base'] ) && Design::base() !== $data['design']['base'] ) {
			update_option( Design::BASE_OPTION, $data['design']['base'], false );
			$out['done'][] = 'dark' === $data['design']['base'] ? __( 'site set as a dark design', 'nori-for-elementor' ) : __( 'site set as a light design', 'nori-for-elementor' );
		}
		if ( ! empty( $data['design']['dark_colors'] ) ) {
			$vstatus = $this->plugin->variables->status();
			$n       = Dark_Colors::import( $data['design']['dark_colors'], $this->plugin->variables->color_choices(), Dark_Colors::palette_ids( is_wp_error( $vstatus ) ? array() : $vstatus ) );
			/* translators: %d: number. */
			$add( $n, _n( 'dark version set for %d color', 'dark versions set for %d colors', $n, 'nori-for-elementor' ) );
		}

		// 2. Variables, before classes, so class styles can point at them.
		if ( $plan['vars_add'] ) {
			$r = $this->plugin->variables->import( $plan['vars_add'], array_intersect_key( $data['variables'], array_flip( $plan['vars_add'] ) ) );
			$add( count( $r['done'] ), /* translators: %d: number. */ _n( '%d variable added', '%d variables added', count( $r['done'] ), 'nori-for-elementor' ) );
			$out['errors'] = array_merge( $out['errors'], array_values( $r['errors'] ) );
		}
		$set = $this->plugin->variables->set_values( $data['variables'] );
		$add( count( $set ), /* translators: %d: number. */ _n( '%d variable value set', '%d variable values set', count( $set ), 'nori-for-elementor' ) );

		// 3. HubSpot bridges (each creates its own class, with the same id as on the other site).
		$bridges = 0;
		foreach ( (array) ( $data['hubspot'] ?? array() ) as $b ) {
			if ( in_array( $b['id'], $plan['bridges_add'], true ) ) {
				$r = $this->plugin->hubspot->create( $b );
				if ( is_wp_error( $r ) ) {
					$out['errors'][] = $r->get_error_message();
				} else {
					$bridges++;
				}
			}
		}
		$add( $bridges, /* translators: %d: number. */ _n( '%d HubSpot bridge added', '%d HubSpot bridges added', $bridges, 'nori-for-elementor' ) );

		// 4. Classes.
		if ( Plugin::atomic_available() && $data['classes'] ) {
			if ( $plan['classes_add'] ) {
				$r = $this->plugin->classes->import( $plan['classes_add'] );
				$add( count( $r['done'] ), /* translators: %d: number. */ _n( '%d class added', '%d classes added', count( $r['done'] ), 'nori-for-elementor' ) );
				$out['errors'] = array_merge( $out['errors'], array_values( $r['errors'] ) );
			}
			$write = $plan['classes_edit'];
			if ( 'import' === $conflicts ) {
				$write = array_merge( $write, $plan['conflicts'] );
			}
			if ( $write ) {
				$ids     = $this->variable_ids();
				$by_key  = array();
				foreach ( $write as $key ) {
					$missing          = array();
					$by_key[ $key ] = array(
						'label'    => $data['classes'][ $key ]['label'] ?? '',
						'variants' => $this->from_portable( (array) $data['classes'][ $key ]['variants'], $ids, $missing ),
					);
					if ( $missing ) {
						/* translators: 1: class name, 2: list of variable names. */
						$out['warnings'][] = sprintf( __( '%1$s used variables this site doesn\'t have (%2$s); those properties were left out.', 'nori-for-elementor' ), '.' . ( $data['classes'][ $key ]['label'] ?? $key ), implode( ', ', array_unique( $missing ) ) );
					}
				}
				$r = $this->plugin->classes->write_variants( $by_key );
				$add( count( $r['done'] ), /* translators: %d: number. */ _n( '%d edited class brought over', '%d edited classes brought over', count( $r['done'] ), 'nori-for-elementor' ) );
				$out['errors'] = array_merge( $out['errors'], array_values( $r['errors'] ) );
			}
			if ( $plan['conflicts'] && 'keep' === $conflicts ) {
				/* translators: %d: number of classes. */
				$out['warnings'][] = sprintf( _n( '%d class edited on both sites kept this site\'s version.', '%d classes edited on both sites kept this site\'s version.', count( $plan['conflicts'] ), 'nori-for-elementor' ), count( $plan['conflicts'] ) );
			}
		}
		return $out;
	}

	/** Map of "nori:key" and "label:name" => this site's variable id. */
	private function variable_ids() {
		$out = array();
		$all = $this->plugin->api->list_variables();
		if ( ! is_wp_error( $all ) ) {
			foreach ( $all as $id => $v ) {
				if ( empty( $v['deleted'] ) && empty( $v['deleted_at'] ) ) {
					$out[ 'label:' . ( $v['label'] ?? '' ) ] = (string) $id;
				}
			}
		}
		$status = $this->plugin->variables->status();
		if ( ! is_wp_error( $status ) ) {
			foreach ( $status as $key => $row ) {
				if ( $row['id'] && in_array( $row['status'], array( 'default', 'edited' ), true ) ) {
					$out[ 'nori:' . $key ] = $row['id'];
				}
			}
		}
		return $out;
	}

	/** Turn portable variable references back into this site's ids; drop props that can't be matched. */
	private function from_portable( array $variants, array $ids, array &$missing ) {
		foreach ( $variants as $i => $variant ) {
			foreach ( (array) ( $variant['props'] ?? array() ) as $prop => $value ) {
				$ok = true;
				$variants[ $i ]['props'][ $prop ] = $this->resolve( $value, $ids, $ok, $missing );
				if ( ! $ok ) {
					unset( $variants[ $i ]['props'][ $prop ] );
				}
			}
		}
		return $variants;
	}

	private function resolve( $value, array $ids, &$ok, array &$missing ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		if ( isset( $value['$$type'], $value['value'] ) && in_array( $value['$$type'], self::VAR_TYPES, true ) && is_string( $value['value'] ) ) {
			if ( isset( $ids[ $value['value'] ] ) ) {
				$value['value'] = $ids[ $value['value'] ];
			} else {
				$ok        = false;
				$missing[] = preg_replace( '/^(nori|label):/', '', $value['value'] );
			}
			return $value;
		}
		foreach ( $value as $k => $v ) {
			$value[ $k ] = $this->resolve( $v, $ids, $ok, $missing );
		}
		return $value;
	}

	/**
	 * An imported variable value: a plain CSS value only (a color like #D6FF00 or rgba(...),
	 * a size like 14px or clamp(1rem, 2vw, 2rem)). Anything that could close the declaration
	 * or the rule (; { } < > quotes, backslashes, url()) is refused.
	 */
	public static function css_value( $v ) {
		if ( ! is_scalar( $v ) ) {
			return '';
		}
		$v = trim( sanitize_text_field( (string) $v ) );
		if ( strlen( $v ) > 120 || ! preg_match( '/^[#a-zA-Z0-9.%(),+*\/ -]+$/', $v ) || preg_match( '/url\s*\(|expression\s*\(/i', $v ) ) {
			return '';
		}
		return $v;
	}
}
