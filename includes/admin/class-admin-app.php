<?php
/**
 * The Nori admin app: one screen with a sidebar, where every change waits in one save bar.
 *
 * The page itself is a single root element. Everything it shows comes from state() (printed into
 * the page, and returned again after each save), and every change goes through one REST call,
 * save(), which hands each part to the code that stores it.
 *
 * @package Nori_For_Elementor
 */

namespace Nori\Elementor;

use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_App {

	const REST_NS = 'nori/v1';

	/** @var Plugin */
	private $plugin;

	/** @var Admin_Design */
	private $design;

	/** @var Admin_Modules_Screen */
	private $modules_screen;

	/** @var Admin_Overview */
	private $overview;

	/** @var Admin_HubSpot */
	private $hubspot;

	/** @var Admin_Templates */
	private $templates;

	public function __construct( Plugin $plugin, Admin_Design $design, Admin_Modules_Screen $modules_screen, Admin_Overview $overview, Admin_HubSpot $hubspot, Admin_Templates $templates ) {
		$this->plugin         = $plugin;
		$this->design         = $design;
		$this->modules_screen = $modules_screen;
		$this->overview       = $overview;
		$this->hubspot        = $hubspot;
		$this->templates      = $templates;
	}

	public function routes() {
		$can = fn() => current_user_can( Admin::CAP );
		register_rest_route(
			self::REST_NS,
			'/admin/state',
			array(
				'methods'             => 'GET',
				'callback'            => fn() => rest_ensure_response( $this->state() ),
				'permission_callback' => $can,
			)
		);
		register_rest_route(
			self::REST_NS,
			'/admin/save',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_save' ),
				'permission_callback' => $can,
			)
		);
		foreach ( array(
			'template'       => 'rest_template',
			'site-parts'     => 'rest_site_parts',
			'import'         => 'rest_import',
			'import/apply'   => 'rest_import_apply',
			'import/cancel'  => 'rest_import_cancel',
			'reset/backup'   => 'rest_reset_backup',
			'reset/run'      => 'rest_reset_run',
		) as $route => $cb ) {
			register_rest_route(
				self::REST_NS,
				'/admin/' . $route,
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, $cb ),
					'permission_callback' => $can,
				)
			);
		}
		register_rest_route(
			self::REST_NS,
			'/admin/derive',
			array(
				'methods'             => 'POST',
				'callback'            => function ( WP_REST_Request $r ) {
					return rest_ensure_response( Design::derive( (string) $r->get_param( 'accent' ), (string) $r->get_param( 'ink' ) ) );
				},
				'permission_callback' => $can,
			)
		);
	}

	/* ------------------------------------------------------------------
	 * State
	 * --------------------------------------------------------------- */

	/** Everything the app shows. */
	public function state() {
		$atomic = Plugin::atomic_available();
		if ( $atomic ) {
			// Variables deleted anywhere (here or in Elementor) leave their value in the classes that used them.
			$this->plugin->classes->detach_deleted_variables();
		}
		$cstatus = $atomic ? $this->plugin->classes->status() : array();
		$cerror  = is_wp_error( $cstatus ) ? $cstatus->get_error_message() : '';
		$cstatus = is_wp_error( $cstatus ) ? array() : $cstatus;
		$vstatus = $this->plugin->variables->status();
		$verror  = is_wp_error( $vstatus ) ? $vstatus->get_error_message() : '';
		$vstatus = is_wp_error( $vstatus ) ? array() : $vstatus;
		$usage   = $cstatus ? $this->plugin->classes->usage( $cstatus ) : array();

		return array(
			'site'      => $this->site_info( $atomic, $cerror, $verror ),
			'classes'   => $this->classes_state( $cstatus, $usage, $vstatus ),
			'variables' => $this->variables_state( $vstatus ),
			'design'    => $this->design_state( $vstatus ),
			'modules'   => $this->modules_state( $cstatus, $usage, $vstatus ),
			'colors'    => $this->colors_state( $vstatus ),
			'home'      => $this->home_state( $cstatus ),
			'forms'     => array(
				'hubspot' => $this->hubspot->app_state(),
				'spam'    => Admin_Spam::app_state(),
			),
			'templates' => $this->templates_state(),
			'tools'     => $this->tools_state( $cstatus, $vstatus ),
		);
	}

	/* ------------------------------------------------------------------
	 * Templates, header and footer
	 * --------------------------------------------------------------- */

	/** The classes and variables a page template's sections use. */
	private function page_uses( array $page ) {
		$classes = array();
		$vars    = array();
		$walk    = function ( $node ) use ( &$walk, &$classes, &$vars ) {
			if ( is_array( $node ) ) {
				if ( isset( $node['classes'] ) && is_array( $node['classes'] ) ) {
					foreach ( $node['classes'] as $c ) {
						if ( is_string( $c ) ) {
							$classes[ $c ] = true;
						}
					}
				}
				foreach ( $node as $v ) {
					$walk( $v );
				}
			} elseif ( is_string( $node ) && preg_match_all( '/var\(([a-z0-9-]+)\)/', $node, $m ) ) {
				foreach ( $m[1] as $v ) {
					$vars[ $v ] = true;
				}
			}
		};
		foreach ( (array) ( $page['sections'] ?? array() ) as $section ) {
			$tpl = $this->plugin->templates->get( $section['key'] ?? '' );
			if ( ! $tpl ) {
				continue;
			}
			$variant = $section['variant'] ?? '';
			if ( ! empty( $tpl['variants'] ) ) {
				$variant = isset( $tpl['variants'][ $variant ] ) ? $variant : array_key_first( $tpl['variants'] );
				$walk( $tpl['variants'][ $variant ]['tree'] ?? array() );
			} else {
				$walk( $tpl['tree'] ?? array() );
			}
		}
		return array( array_keys( $classes ), array_keys( $vars ) );
	}

	private function templates_state() {
		$pages = array();
		foreach ( $this->plugin->templates->pages() as $key => $p ) {
			list( $classes, $vars ) = $this->page_uses( $p );
			$pages[] = array(
				'key'         => $key,
				'style'       => $p['style'] ?? '',
				'label'       => $p['label'] ?? $key,
				'description' => $p['description'] ?? '',
				'shot'        => Templates::screenshot_url( $key ),
				'classes'     => $classes,
				'vars'        => $vars,
			);
		}
		$status = $this->plugin->site_parts->status();
		$parts  = array();
		foreach ( $status['parts'] as $type => $part ) {
			$parts[] = array(
				'type'  => $type,
				'title' => $part['title'],
				'live'  => (bool) $part['live'],
				'edit'  => $part['edit_url'],
			);
		}
		$note = '';
		if ( ! $status['can_show'] ) {
			/* translators: %s: theme name. */
			$note = sprintf( __( 'Without Elementor Pro, Nori shows its header and footer with the Hello Elementor theme. Your theme (%s) keeps its own until you switch.', 'nori-for-elementor' ), wp_get_theme()->get( 'Name' ) );
		} elseif ( 'theme-builder' !== $status['mode'] ) {
			$note = __( 'They replace the Hello Elementor theme\'s own header and footer. Pages using Elementor Canvas show neither.', 'nori-for-elementor' );
		}
		$styles = array();
		foreach ( Templates::styles() as $key => $st ) {
			$styles[] = array(
				'key'         => $key,
				'label'       => $st['label'] ?? $key,
				'description' => $st['description'] ?? '',
				'partsShot'   => Templates::screenshot_url( 'site-parts-' . $key ),
			);
		}
		return array(
			'styles' => $styles,
			'style'  => Templates::site_style(),
			'pages'  => $pages,
			'parts'  => array(
				'mode'  => $status['mode'],
				'style' => $status['style'],
				'items' => $parts,
				'note'  => $note,
			),
		);
	}

	public function rest_template( WP_REST_Request $r ) {
		$res          = $this->templates->create_page( array( 'page_template' => (string) $r->get_param( 'page' ) ) );
		$res['state'] = $this->state();
		return rest_ensure_response( $res );
	}

	public function rest_site_parts( WP_REST_Request $r ) {
		$res          = $this->templates->create_site_parts( sanitize_key( (string) $r->get_param( 'style' ) ), (bool) $r->get_param( 'replace' ) );
		$res['state'] = $this->state();
		return rest_ensure_response( $res );
	}

	/* ------------------------------------------------------------------
	 * Import and export
	 * --------------------------------------------------------------- */

	private function import_key() {
		return 'nori_fe_import_' . get_current_user_id();
	}

	private function tools_state( array $cstatus, array $vstatus ) {
		$export = $this->overview->export( $cstatus );
		$data   = get_transient( $this->import_key() );
		return array(
			'json'     => $export['json'],
			'text'     => $export['text'],
			'filename' => Reset::filename(),
			'import'   => is_array( $data ) ? $this->import_view( $data ) : null,
			'reset'    => ( new Reset( $this->plugin ) )->summary( $cstatus, $vstatus ),
		);
	}

	/* ------------------------------------------------------------------
	 * Full reset
	 * --------------------------------------------------------------- */

	/** Step 1 of a reset: a fresh Nori Theme JSON to download, and the token that allows step 2. */
	public function rest_reset_backup() {
		$status = Plugin::atomic_available() ? $this->plugin->classes->status() : array();
		$export = $this->overview->export( is_wp_error( $status ) ? array() : $status );
		return rest_ensure_response( ( new Reset( $this->plugin ) )->backup( $export['json'] ) );
	}

	/** Step 2: reset everything, only with a token from step 1 and the typed confirmation. */
	public function rest_reset_run( WP_REST_Request $r ) {
		if ( 'RESET' !== (string) $r->get_param( 'confirm' ) ) {
			return rest_ensure_response(
				array(
					'type'    => 'error',
					'message' => __( 'Type RESET to confirm.', 'nori-for-elementor' ),
				)
			);
		}
		$res = ( new Reset( $this->plugin ) )->run( (string) $r->get_param( 'token' ) );
		if ( is_wp_error( $res ) ) {
			return rest_ensure_response(
				array(
					'type'    => 'error',
					'message' => $res->get_error_message(),
				)
			);
		}
		/* translators: %s: list of what was reset. */
		$msg = sprintf( __( 'Nori is back to its defaults: %s.', 'nori-for-elementor' ), implode( ', ', $res['done'] ) );
		if ( $res['errors'] ) {
			$msg .= ' ' . __( 'Some parts could not be reset:', 'nori-for-elementor' ) . ' ' . implode( ' ', array_unique( $res['errors'] ) );
		}
		return rest_ensure_response(
			array(
				'type'    => $res['errors'] ? 'error' : 'success',
				'message' => $msg,
				'state'   => $this->state(),
			)
		);
	}

	/** What an import would change, in words. */
	private function import_view( array $data ) {
		$plan    = ( new Transfer( $this->plugin ) )->plan( $data );
		$modules = $this->plugin->modules->all();
		$vars    = $this->plugin->library->variables();
		$classes = $this->plugin->library->classes();
		$names   = array(
			'module' => fn( $k ) => $modules[ $k ]['label'] ?? $k,
			'var'    => fn( $k ) => $vars[ $k ]['label'] ?? $k,
			'class'  => fn( $k ) => '.' . ( is_string( $data['classes'][ $k ]['label'] ?? null ) ? $data['classes'][ $k ]['label'] : ( $classes[ $k ]['label'] ?? $k ) ),
			'bridge' => fn( $k ) => '.' . HubSpot_Bridges::class_label( $k ),
		);
		$defs    = array(
			array( 'modules_on', 'module', __( 'Switch on', 'nori-for-elementor' ) ),
			array( 'modules_off', 'module', __( 'Switch off', 'nori-for-elementor' ) ),
			array( 'settings', 'module', __( 'Update settings', 'nori-for-elementor' ) ),
			array( 'vars_add', 'var', __( 'Add variables', 'nori-for-elementor' ) ),
			array( 'vars_set', 'var', __( 'Change variable values', 'nori-for-elementor' ) ),
			array( 'classes_add', 'class', __( 'Add classes', 'nori-for-elementor' ) ),
			array( 'classes_edit', 'class', __( 'Bring over edited classes', 'nori-for-elementor' ) ),
			array( 'bridges_add', 'bridge', __( 'Add HubSpot bridges', 'nori-for-elementor' ) ),
			array( 'kept', 'class', __( 'Keep (edited here, default in the file)', 'nori-for-elementor' ) ),
		);
		$rows    = array();
		$changes = count( $plan['conflicts'] );
		foreach ( $defs as $d ) {
			if ( ! $plan[ $d[0] ] ) {
				continue;
			}
			if ( 'kept' !== $d[0] ) {
				$changes += count( $plan[ $d[0] ] );
			}
			$rows[] = array(
				'label'  => $d[2],
				'count'  => count( $plan[ $d[0] ] ),
				'tokens' => array_map( $names[ $d[1] ], array_slice( $plan[ $d[0] ], 0, 24 ) ),
			);
		}
		if ( ! empty( $plan['type_scale'] ) ) {
			$t = $data['design']['type_scale'];
			$rows[] = array(
				'label'  => __( 'Change the type scale', 'nori-for-elementor' ),
				'count'  => 1,
				/* translators: 1: body size in px, 2: ratio, 3: heading weight. */
				'tokens' => array( sprintf( __( '%1$dpx body, ratio %2$s, headings %3$s', 'nori-for-elementor' ), $t['base'], $t['ratio'], $t['weight'] ) ),
			);
			++$changes;
		}
		return array(
			/* translators: %s: site the export came from. */
			'title'     => $data['site'] ? sprintf( __( 'Setup from %s', 'nori-for-elementor' ), $data['site'] ) : __( 'Imported setup', 'nori-for-elementor' ),
			/* translators: 1: Nori version, 2: date. */
			'sub'       => sprintf( __( 'Exported from Nori %1$s on %2$s. Nothing has changed yet.', 'nori-for-elementor' ), $data['nori'] ? $data['nori'] : '?', $data['exported'] ? mysql2date( get_option( 'date_format' ), $data['exported'] ) : '?' ),
			'rows'      => $rows,
			'conflicts' => array_map( $names['class'], $plan['conflicts'] ),
			'warnings'  => $plan['warnings'],
			'changes'   => $changes,
		);
	}

	public function rest_import( WP_REST_Request $r ) {
		$data = ( new Transfer( $this->plugin ) )->parse( (string) $r->get_param( 'json' ) );
		if ( is_wp_error( $data ) ) {
			return rest_ensure_response(
				array(
					'type'    => 'error',
					'message' => $data->get_error_message(),
				)
			);
		}
		set_transient( $this->import_key(), $data, 30 * MINUTE_IN_SECONDS );
		return rest_ensure_response(
			array(
				'type'  => 'success',
				'state' => $this->state(),
			)
		);
	}

	public function rest_import_apply( WP_REST_Request $r ) {
		$data = get_transient( $this->import_key() );
		delete_transient( $this->import_key() );
		if ( ! is_array( $data ) ) {
			return rest_ensure_response(
				array(
					'type'    => 'error',
					'message' => __( 'That import expired. Choose the file again.', 'nori-for-elementor' ),
					'state'   => $this->state(),
				)
			);
		}
		$res = ( new Transfer( $this->plugin ) )->apply( $data, 'import' === $r->get_param( 'conflicts' ) ? 'import' : 'keep' );
		/* translators: %s: list of what was imported. */
		$msg = $res['done'] ? sprintf( __( 'Import finished: %s.', 'nori-for-elementor' ), implode( ', ', $res['done'] ) ) : __( 'Import finished. Nothing needed to change.', 'nori-for-elementor' );
		if ( $res['warnings'] ) {
			$msg .= ' ' . implode( ' ', $res['warnings'] );
		}
		if ( $res['errors'] ) {
			$msg .= ' ' . implode( ' ', array_unique( $res['errors'] ) );
		}
		return rest_ensure_response(
			array(
				'type'    => $res['errors'] ? 'error' : 'success',
				'message' => $msg,
				'state'   => $this->state(),
			)
		);
	}

	public function rest_import_cancel() {
		delete_transient( $this->import_key() );
		return rest_ensure_response(
			array(
				'type'    => 'success',
				'message' => __( 'Import cancelled. Nothing was changed.', 'nori-for-elementor' ),
				'state'   => $this->state(),
			)
		);
	}

	private function site_info( $atomic, $cerror, $verror ) {
		return array(
			'version'    => NORI_FE_VERSION,
			'atomic'     => $atomic,
			'pro'        => class_exists( '\\Elementor\\Utils' ) && \Elementor\Utils::has_pro(),
			'canClasses' => current_user_can( 'elementor_global_classes_update_class' ),
			'errors'     => array_values( array_filter( array( $cerror, $verror ) ) ),
			'siteUrl'    => Admin::SITE_URL,
			'support'    => Admin::SUPPORT_URL,
			'pageUrl'    => Admin_UI::app_url(),
			'host'       => (string) wp_parse_url( home_url(), PHP_URL_HOST ),
		);
	}

	private function classes_state( array $status, array $usage, array $vstatus ) {
		$groups = array();
		$items  = array();
		foreach ( $this->plugin->library->class_groups() as $group ) {
			$groups[] = array(
				'key'         => $group['key'],
				'label'       => $group['label'],
				'description' => $group['description'] ?? '',
			);
			foreach ( $group['classes'] as $class ) {
				$row  = $status[ $class['key'] ] ?? null;
				$vars = array();
				foreach ( $this->plugin->library->class_variables( $class['key'] ) as $vk ) {
					$vars[] = $vk;
				}
				$items[] = array(
					'key'         => $class['key'],
					'label'       => $row ? $row['label'] : $class['label'],
					'group'       => $group['key'],
					'description' => $class['description'] ?? '',
					'spec'        => Admin_UI::spec( (array) ( $class['styles'] ?? array() ) ),
					'styles'      => $this->plain_styles( (array) ( $class['styles'] ?? array() ) ),
					'module'      => $class['module'] ?? null,
					'status'      => $row ? $row['status'] : 'missing',
					'usage'       => (int) ( $usage[ $class['key'] ] ?? 0 ),
					'vars'        => $vars,
				);
			}
		}
		return array(
			'groups' => $groups,
			'items'  => $items,
		);
	}

	/** Styles as short strings for the detail table (box values joined, nested values summarized). */
	private function plain_styles( array $styles ) {
		$out = array();
		foreach ( $styles as $variant => $props ) {
			foreach ( (array) $props as $prop => $value ) {
				if ( is_scalar( $value ) ) {
					$out[ $variant ][ $prop ] = (string) $value;
				} elseif ( is_array( $value ) && ( isset( $value['block-start'] ) || isset( $value['inline-start'] ) || isset( $value['block-end'] ) ) ) {
					$out[ $variant ][ $prop ] = implode( ' ', array_map( 'strval', array_values( array_filter( $value, 'is_scalar' ) ) ) );
				} else {
					$out[ $variant ][ $prop ] = '…';
				}
			}
		}
		return $out;
	}

	private function variables_state( array $vstatus ) {
		$groups  = array();
		$items   = array();
		$used_by = array();
		foreach ( $this->plugin->library->classes() as $ck => $c ) {
			foreach ( $this->plugin->library->class_variables( $ck ) as $vk ) {
				$used_by[ $vk ][] = $ck;
			}
		}
		foreach ( $this->plugin->library->variable_groups() as $group ) {
			$groups[] = array(
				'key'         => $group['key'],
				'label'       => $group['label'],
				'description' => $group['description'] ?? '',
			);
			foreach ( $group['variables'] as $var ) {
				$row     = $vstatus[ $var['key'] ] ?? array();
				$items[] = array(
					'key'         => $var['key'],
					'label'       => $var['label'],
					'group'       => $group['key'],
					'type'        => 'global-color-variable' === $var['type'] ? 'color' : 'size',
					'description' => $var['description'] ?? '',
					'default'     => $var['value'],
					'value'       => isset( $row['value'] ) && null !== $row['value'] ? (string) $row['value'] : (string) $var['value'],
					'status'      => $row['status'] ?? 'missing',
					'usedBy'      => $used_by[ $var['key'] ] ?? array(),
				);
			}
		}
		return array(
			'groups' => $groups,
			'items'  => $items,
		);
	}

	private function design_state( array $vstatus ) {
		$live = array();
		foreach ( Design::COLOR_KEYS as $k ) {
			$live[ $k ] = $vstatus[ $k ]['value'] ?? null;
		}
		$brand = (array) get_option( Design::BRAND_OPTION, array() );
		return array(
			'type'     => Design::type_settings(),
			'ratios'   => Design::RATIOS,
			'weights'  => Design::WEIGHTS,
			'steps'    => array_map( fn( $r ) => array( $r[0], $r[1], $r[2], $r[4], $r[5] ), Design::type_steps() ),
			'presets'  => Design::presets(),
			'preset'   => Design::matching_preset( $live ),
			'brand'    => array(
				'preset' => (string) ( $brand['preset'] ?? '' ),
				'accent' => Design::hex( $brand['accent'] ?? '' ) ? Design::hex( $brand['accent'] ) : '#D6FF00',
				'ink'    => Design::hex( $brand['ink'] ?? '' ) ? Design::hex( $brand['ink'] ) : '#111827',
			),
			'colorKeys' => Design::COLOR_KEYS,
			'darkKeys'  => Design::DARK_SETTINGS,
			'base'      => Design::base(),
		);
	}

	private function modules_state( array $cstatus, array $usage, array $vstatus ) {
		$items = array();
		foreach ( $this->plugin->modules->all() as $key => $m ) {
			$shown  = $this->plugin->modules->admin_settings( $key, $vstatus );
			$fields = array();
			foreach ( (array) ( $m['settings'] ?? array() ) as $f ) {
				$fields[] = array_merge( array_intersect_key( $f, array_flip( array( 'key', 'label', 'type', 'min', 'max', 'step', 'unit', 'default', 'help', 'options', 'presets', 'link', 'variable', 'plain' ) ) ), array( 'default' => Modules::field_default( $f ) ) );
			}
			$classes = array();
			foreach ( (array) ( $m['classes'] ?? array() ) as $c ) {
				$row       = $cstatus[ $c['key'] ] ?? null;
				$classes[] = array(
					'key'    => $c['key'],
					'role'   => $c['role'] ?? '',
					'status' => $row ? $row['status'] : 'missing',
					'usage'  => (int) ( $usage[ $c['key'] ] ?? 0 ),
				);
			}
			$items[ $key ] = array(
				'key'      => $key,
				'label'    => $m['label'],
				'summary'  => $m['summary'] ?? '',
				'how'      => $m['how'] ?? '',
				'explain'  => array_values( (array) ( $m['explain'] ?? array() ) ),
				'pairs'    => $this->plugin->library->module_pairs( $key ),
				'panel'    => $m['panel'] ?? '',
				'on'       => $this->plugin->modules->is_enabled( $key ),
				'fields'   => $fields,
				'values'   => $shown['values'],
				'defaults' => array_combine( wp_list_pluck( $fields, 'key' ), wp_list_pluck( $fields, 'default' ) ) ?: array(),
				'tuned'    => $shown['tuned'],
				'classes'  => $classes,
				'varState' => array_map( fn( $f ) => empty( $f['variable'] ) ? '' : ( $vstatus[ $f['variable'] ]['status'] ?? 'missing' ), array_column( $fields, null, 'key' ) ),
			);
		}
		$cats = array();
		foreach ( $this->modules_screen->categories() as $key => $c ) {
			$cats[] = array(
				'key'         => $key,
				'label'       => $c['label'],
				'description' => $c['description'] ?? '',
				'pick'        => ! empty( $c['group'] ),
				// In a pick-one category, only the modules in its group are rivals; others (Split Reveal
				// in Scroll animation) are switched on and off on their own.
				'pickModules' => empty( $c['group'] ) ? array() : array_keys( array_filter( $c['items'], fn( $m ) => ( $m['group'] ?? '' ) === $c['group'] ) ),
				'modules'     => array_keys( $c['items'] ),
			);
		}
		return array(
			'items'      => $items,
			'categories' => $cats,
			'pairKinds'  => $this->plugin->library->module_pair_kinds(),
			'localFonts' => '1' === get_option( 'elementor_local_google_fonts' ),
		);
	}

	/**
	 * Colors for the pickers (Nori's and the user's own) and the user's colors' dark partners.
	 */
	private function colors_state( array $vstatus ) {
		$choices = $this->plugin->variables->color_choices();
		Dark_Colors::refresh_labels( $choices );
		$palette = Dark_Colors::palette_ids( $vstatus );
		$saved   = Dark_Colors::all();
		$module  = array();
		$nori_id = array();
		foreach ( $this->plugin->library->variables() as $vk => $var ) {
			$row = $vstatus[ $vk ] ?? null;
			if ( $row && ! empty( $row['id'] ) && in_array( $row['status'], array( 'default', 'edited' ), true ) ) {
				$nori_id[ (string) $row['id'] ] = $vk;
				if ( ! empty( $var['module'] ) ) {
					$module[ (string) $row['id'] ] = $vk;
				}
			}
		}
		$mine = array();
		foreach ( $choices as $id => $c ) {
			if ( isset( $palette[ $id ] ) || ! preg_match( Dark_Colors::ID_PATTERN, (string) $id ) ) {
				continue;
			}
			if ( isset( $nori_id[ $id ] ) && ! isset( $module[ $id ] ) ) {
				continue; // A Nori starter color.
			}
			$mine[] = array(
				'id'     => (string) $id,
				'label'  => $c['label'],
				'value'  => sanitize_hex_color( $c['value'] ) ? strtoupper( $c['value'] ) : '',
				'to'     => (string) ( $saved[ $id ]['to'] ?? 'same' ),
				'module' => isset( $module[ $id ] ),
			);
		}
		usort( $mine, fn( $a, $b ) => strcasecmp( $a['label'], $b['label'] ) );
		return array( 'mine' => $mine );
	}

	private function home_state( array $cstatus ) {
		$pages = array();
		if ( $cstatus && class_exists( '\\Elementor\\Modules\\GlobalClasses\\Global_Classes_Relations' ) ) {
			$relations = new \Elementor\Modules\GlobalClasses\Global_Classes_Relations();
			$count     = array();
			foreach ( $cstatus as $row ) {
				if ( empty( $row['id'] ) || 'missing' === $row['status'] ) {
					continue;
				}
				foreach ( (array) $relations->get_posts_by_style( $row['id'] ) as $post_id ) {
					$st = get_post_status( $post_id );
					if ( $st && 'trash' !== $st ) {
						$count[ (int) $post_id ] = ( $count[ (int) $post_id ] ?? 0 ) + 1;
					}
				}
			}
			arsort( $count );
			foreach ( $count as $id => $n ) {
				$type    = get_post_type_object( get_post_type( $id ) );
				$pages[] = array(
					'title' => get_the_title( $id ) ? get_the_title( $id ) : __( '(no title)', 'nori-for-elementor' ),
					'type'  => $type ? $type->labels->singular_name : '',
					'count' => $n,
					'edit'  => Templates::edit_url( $id ),
				);
			}
		}
		$spam = null;
		if ( $this->plugin->modules->is_enabled( 'form-protection' ) ) {
			$t    = Form_Guard::totals( 7 );
			$spam = array( 'blocked' => (int) $t['blocked'] );
		}
		$changelog = array();
		foreach ( $this->overview->changelog() as $ver => $entries ) {
			$changelog[] = array(
				'version' => $ver,
				'entries' => $entries,
			);
		}
		return array(
			'health'    => $this->overview->health(),
			'footprint' => $this->overview->footprint(),
			'pages'     => $pages,
			'spam'      => $spam,
			'changelog' => $changelog,
		);
	}

	/* ------------------------------------------------------------------
	 * Save
	 * --------------------------------------------------------------- */

	public function rest_save( WP_REST_Request $request ) {
		$changes = (array) $request->get_json_params();
		$result  = $this->save( $changes );
		$result['state'] = $this->state();
		return rest_ensure_response( $result );
	}

	/**
	 * Apply one batch of changes from the app.
	 *
	 * @param array $c {
	 *     @type string $base      "light" or "dark".
	 *     @type array  $brand     [ preset, accent, ink ]: what the colors were made from.
	 *     @type array  $type      [ base, ratio, weight ].
	 *     @type array  $variables [ add[], reset[], delete[], values{key: value} ].
	 *     @type array  $classes   [ add[], reset[], delete[] ].
	 *     @type array  $modules   [ enabled{key: bool}, settings{key: {field: value}}, deleteClasses[], darkColors{id: {to, custom}} ].
	 * }
	 * @return array{messages: string[], errors: string[]}
	 */
	public function save( array $c ) {
		$msgs = array();
		$errs = array();
		$note = function ( $r ) use ( &$msgs, &$errs ) {
			if ( ! $r || ! $r[0] ) {
				return;
			}
			if ( 'error' === $r[1] ) {
				$errs[] = $r[0];
			} else {
				$msgs[] = $r[0];
			}
		};

		// 1. The site's own design, and what the colors came from.
		if ( isset( $c['base'] ) ) {
			$base = 'dark' === $c['base'] ? 'dark' : 'light';
			if ( Design::base() !== $base ) {
				update_option( Design::BASE_OPTION, $base, false );
				$msgs[] = 'dark' === $base ? __( 'Your site is set as a dark design.', 'nori-for-elementor' ) : __( 'Your site is set as a light design.', 'nori-for-elementor' );
			}
		}
		if ( isset( $c['brand'] ) && is_array( $c['brand'] ) ) {
			$preset = sanitize_key( (string) ( $c['brand']['preset'] ?? '' ) );
			if ( 'custom' === $preset ) {
				update_option(
					Design::BRAND_OPTION,
					array(
						'preset' => 'custom',
						'accent' => Design::hex( $c['brand']['accent'] ?? '' ),
						'ink'    => Design::hex( $c['brand']['ink'] ?? '' ),
					),
					false
				);
			} elseif ( isset( Design::presets()[ $preset ] ) ) {
				update_option( Design::BRAND_OPTION, array( 'preset' => $preset ), false );
			}
		}

		// 2. Type scale first, so classes added in this same save use it.
		if ( isset( $c['type'] ) && is_array( $c['type'] ) ) {
			$note(
				$this->design->save_type_scale(
					array(
						'type_base'   => $c['type']['base'] ?? null,
						'type_ratio'  => $c['type']['ratio'] ?? null,
						'type_weight' => $c['type']['weight'] ?? null,
					)
				)
			);
		}

		// 3. Variables: add, set values (adding any that are missing with that value), reset, delete.
		if ( isset( $c['variables'] ) && is_array( $c['variables'] ) ) {
			$this->save_variables( $c['variables'], $msgs, $errs );
		}

		// 4. Classes.
		if ( isset( $c['classes'] ) && is_array( $c['classes'] ) ) {
			$this->save_classes( $c['classes'], $msgs, $errs );
		}

		// 5. Modules, one category at a time.
		if ( isset( $c['modules'] ) && is_array( $c['modules'] ) ) {
			$this->save_modules( $c['modules'], $note );
		}

		return array(
			'messages' => $msgs,
			'errors'   => $errs,
		);
	}

	private static function keys( $list ) {
		return array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) $list ) ) ) );
	}

	private function save_variables( array $v, array &$msgs, array &$errs ) {
		$lib    = $this->plugin->library->variables();
		$sync   = $this->plugin->variables;
		$delete = array_values( array_intersect( self::keys( $v['delete'] ?? array() ), array_keys( $lib ) ) );
		$reset  = array_values( array_diff( array_intersect( self::keys( $v['reset'] ?? array() ), array_keys( $lib ) ), $delete ) );
		$add    = array_values( array_diff( array_intersect( self::keys( $v['add'] ?? array() ), array_keys( $lib ) ), $delete ) );
		$values = array();
		foreach ( (array) ( $v['values'] ?? array() ) as $key => $value ) {
			$key = sanitize_key( $key );
			if ( ! isset( $lib[ $key ] ) || in_array( $key, $delete, true ) ) {
				continue;
			}
			$value = sanitize_text_field( (string) $value );
			if ( 'global-color-variable' === $lib[ $key ]['type'] ) {
				$value = Design::hex( $value );
			}
			if ( $value ) {
				$values[ $key ] = $value;
			}
		}
		if ( $add || $values ) {
			$r = $sync->import( array_values( array_unique( array_merge( $add, array_keys( $values ) ) ) ), $values );
			if ( $r['done'] ) {
				/* translators: %d: number of variables. */
				$msgs[] = sprintf( _n( 'Added %d variable to Elementor.', 'Added %d variables to Elementor.', count( $r['done'] ), 'nori-for-elementor' ), count( $r['done'] ) );
			}
			if ( $r['errors'] ) {
				$errs[] = reset( $r['errors'] );
			}
		}
		if ( $values ) {
			$set = $sync->set_values( $values );
			if ( $set ) {
				/* translators: %d: number of variables. */
				$msgs[] = sprintf( _n( 'Updated %d variable value.', 'Updated %d variable values.', count( $set ), 'nori-for-elementor' ), count( $set ) );
			}
		}
		if ( $reset ) {
			$r = $sync->reset( $reset );
			if ( $r['done'] ) {
				/* translators: %d: number of variables. */
				$msgs[] = sprintf( _n( 'Reset %d variable to its default.', 'Reset %d variables to their defaults.', count( $r['done'] ), 'nori-for-elementor' ), count( $r['done'] ) );
			}
			if ( $r['errors'] ) {
				$errs[] = reset( $r['errors'] );
			}
		}
		if ( $delete ) {
			$r = $sync->remove( $delete );
			if ( $r['done'] ) {
				/* translators: %d: number of variables. */
				$msgs[] = sprintf( _n( 'Deleted %d variable from Elementor.', 'Deleted %d variables from Elementor.', count( $r['done'] ), 'nori-for-elementor' ), count( $r['done'] ) );
				if ( Plugin::atomic_available() ) {
					$detached = $this->plugin->classes->detach_deleted_variables();
					if ( $detached ) {
						/* translators: %d: number of classes. */
						$msgs[] = sprintf( _n( '%d class that used them keeps the value as a plain number and now shows as edited.', '%d classes that used them keep the value as a plain number and now show as edited.', count( $detached ), 'nori-for-elementor' ), count( $detached ) );
					}
				}
			}
			if ( $r['errors'] ) {
				$errs[] = reset( $r['errors'] );
			}
		}
	}

	private function save_classes( array $c, array &$msgs, array &$errs ) {
		$lib    = $this->plugin->library->classes();
		$delete = array_values( array_intersect( self::keys( $c['delete'] ?? array() ), array_keys( $lib ) ) );
		$reset  = array_values( array_diff( array_intersect( self::keys( $c['reset'] ?? array() ), array_keys( $lib ) ), $delete ) );
		$add    = array_values( array_diff( array_intersect( self::keys( $c['add'] ?? array() ), array_keys( $lib ) ), $delete ) );
		if ( ! $delete && ! $reset && ! $add ) {
			return;
		}
		if ( ! Plugin::atomic_available() ) {
			$errs[] = __( 'Elementor\'s Atomic editor is not active, so classes can\'t be changed yet.', 'nori-for-elementor' );
			return;
		}
		$sync = $this->plugin->classes;
		foreach ( array(
			/* translators: %d: number of classes. */
			array( $add, 'import', _n_noop( 'Added %d class to Elementor.', 'Added %d classes to Elementor.', 'nori-for-elementor' ) ),
			/* translators: %d: number of classes. */
			array( $reset, 'reset', _n_noop( 'Reset %d class to its default look.', 'Reset %d classes to their default look.', 'nori-for-elementor' ) ),
			/* translators: %d: number of classes. */
			array( $delete, 'remove', _n_noop( 'Deleted %d class from Elementor and every element that used it.', 'Deleted %d classes from Elementor and every element that used them.', 'nori-for-elementor' ) ),
		) as $step ) {
			if ( ! $step[0] ) {
				continue;
			}
			$r = $sync->{$step[1]}( $step[0] );
			if ( $r['done'] ) {
				$msgs[] = sprintf( translate_nooped_plural( $step[2], count( $r['done'] ), 'nori-for-elementor' ), count( $r['done'] ) );
			}
			if ( ! empty( $r['variables'] ) ) {
				/* translators: %s: list of variable names. */
				$msgs[] = sprintf( __( 'Also added the variables they use: %s.', 'nori-for-elementor' ), implode( ', ', array_map( fn( $k ) => $this->plugin->library->variables()[ $k ]['label'], $r['variables'] ) ) );
			}
			if ( $r['errors'] ) {
				$errs[] = reset( $r['errors'] );
			}
		}
	}

	private function save_modules( array $m, callable $note ) {
		$enabled  = (array) ( $m['enabled'] ?? array() );
		$settings = (array) ( $m['settings'] ?? array() );
		$delete   = self::keys( $m['deleteClasses'] ?? array() );
		$dark     = isset( $m['darkColors'] ) && is_array( $m['darkColors'] ) ? $m['darkColors'] : null;
		foreach ( $this->modules_screen->categories() as $cat_key => $cat ) {
			$keys    = array_keys( $cat['items'] );
			$touched = array_intersect( $keys, array_merge( array_keys( $enabled ), array_keys( $settings ), $delete ) ) || ( null !== $dark && in_array( 'dark-mode', $keys, true ) );
			if ( ! $touched ) {
				continue;
			}
			$want = array();
			foreach ( $keys as $key ) {
				$on = array_key_exists( $key, $enabled ) ? ! empty( $enabled[ $key ] ) : $this->plugin->modules->is_enabled( $key );
				if ( $on ) {
					$want[] = $key;
				}
			}
			$post = array(
				'enabled'        => $want,
				'choice'         => $want ? reset( $want ) : 'none',
				'settings'       => array_intersect_key( $settings, array_flip( $keys ) ),
				'delete_classes' => array_values( array_intersect( $delete, $keys ) ),
			);
			// Toggles: the module save reads form-style fields, so send them as "1" or "0".
			foreach ( $post['settings'] as $key => $values ) {
				$post['settings'][ $key ] = array_map( fn( $v ) => is_bool( $v ) ? ( $v ? '1' : '0' ) : ( is_scalar( $v ) ? (string) $v : '' ), (array) $values );
			}
			if ( null !== $dark && in_array( 'dark-mode', $keys, true ) ) {
				$post['dark_colors'] = $dark;
			}
			$note( $this->modules_screen->save( $cat_key, $post ) );
		}
	}
}
