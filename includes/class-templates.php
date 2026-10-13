<?php
/**
 * Section templates: ready-made Atomic sections built from Nori's classes and variables.
 *
 * A template is a JSON file in library/templates/ with named slots (headline, items, cta...) and
 * one element tree per variant. Building one fills the slots and turns the tree into ordinary
 * Elementor element data: Atomic flexboxes, grids, headings, paragraphs, images and icons, styled
 * by Nori's global classes plus a few local styles for layout. Nothing is locked: once inserted,
 * the section is a normal part of the page and can be edited freely in Elementor.
 *
 * Settings and styles are converted by walking Elementor's own schemas at runtime (like the class
 * library), and every element is checked with Elementor's parsers before anything is saved.
 *
 * @package Nori_For_Elementor
 */

namespace Nori\Elementor;

use Elementor\Modules\AtomicWidgets\Parsers\Props_Parser;
use Elementor\Modules\AtomicWidgets\Parsers\Style_Parser;
use Elementor\Modules\AtomicWidgets\Styles\Style_Schema;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Templates {

	/** Element types a template may use. */
	const ELEMENTS = array( 'e-flexbox', 'e-div-block', 'e-grid', 'e-heading', 'e-paragraph', 'e-svg', 'e-image', 'e-divider', 'e-accordion', 'e-accordion-item', 'e-accordion-item-header', 'e-accordion-item-title', 'e-accordion-item-icon', 'e-accordion-item-content', 'e-background-video', 'e-background-video-content', 'e-background-video-controls', 'e-background-video-play', 'e-background-video-pause' );

	/** The sample loop the page templates' video sections start with (slot value "sample"). */
	const SAMPLE_VIDEO = 'assets/templates/sample-loop.mp4';

	/** Text settings per widget: the template writes "text", Elementor calls it this. */
	const TEXT_PROP = array(
		'e-heading'   => 'title',
		'e-paragraph' => 'paragraph',
	);

	/** @var Library */
	private $library;

	/** @var Classes_Sync */
	private $classes;

	/** @var Variables_Sync */
	private $variables;

	/** @var array|null */
	private $all = null;

	/** @var Style_Converter|null */
	private $converter = null;

	/** @var array<string, string> Class key => Elementor id, for the build in progress. */
	private $class_ids = array();

	/** @var string[] Problems found while building. */
	private $errors = array();

	public function __construct( Library $library, Classes_Sync $classes, Variables_Sync $variables ) {
		$this->library   = $library;
		$this->classes   = $classes;
		$this->variables = $variables;
	}

	/* ------------------------------------------------------------------
	 * The library
	 * --------------------------------------------------------------- */

	/** @return array<string, array> Template key => definition, in display order. */
	public function all() {
		if ( null !== $this->all ) {
			return $this->all;
		}
		$this->all = array();
		$files     = glob( NORI_FE_DIR . 'library/templates/*.json' );
		foreach ( (array) $files as $file ) {
			$def = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin file.
			if ( is_array( $def ) && ! empty( $def['key'] ) && ! empty( $def['variants'] ) ) {
				$this->all[ $def['key'] ] = $def;
			}
		}
		uasort( $this->all, fn( $a, $b ) => ( $a['order'] ?? 50 ) <=> ( $b['order'] ?? 50 ) );
		return $this->all;
	}

	public function get( $key ) {
		return $this->all()[ $key ] ?? null;
	}

	/** Groups for the admin: key => label. */
	public static function groups() {
		return array(
			'site'  => __( 'Site parts', 'nori-for-elementor' ),
			'page'  => __( 'Page sections', 'nori-for-elementor' ),
			'proof' => __( 'Proof', 'nori-for-elementor' ),
		);
	}

	/** @return string[] Names of the built-in icons. */
	public static function icons() {
		$out = array();
		foreach ( (array) glob( NORI_FE_DIR . 'assets/icons/*.svg' ) as $file ) {
			$out[] = basename( $file, '.svg' );
		}
		sort( $out );
		return $out;
	}

	public static function icon_url( $name ) {
		return NORI_FE_URL . 'assets/icons/' . rawurlencode( $name ) . '.svg';
	}

	/**
	 * A description of every template for tools and AI agents: slots (with types, limits and
	 * whether they're required) and variants. No element trees.
	 */
	public function describe() {
		$out = array();
		foreach ( $this->all() as $key => $t ) {
			$variants = array();
			foreach ( $t['variants'] as $vkey => $v ) {
				$variants[ $vkey ] = $v['label'] ?? $vkey;
			}
			$slots = array();
			foreach ( (array) ( $t['slots'] ?? array() ) as $skey => $s ) {
				unset( $s['sample'] );
				$slots[ $skey ] = $s;
			}
			$out[ $key ] = array(
				'label'       => $t['label'] ?? $key,
				'group'       => $t['group'] ?? 'page',
				'description' => $t['description'] ?? '',
				'variants'    => $variants,
				'slots'       => $slots,
			);
		}
		return $out;
	}

	/* ------------------------------------------------------------------
	 * Page templates: whole pages made of sections
	 * --------------------------------------------------------------- */

	/** @var array|null */
	private $pages = null;

	/** @return array<string, array> Page template key => { label, description, title, sections[] }. */
	public function pages() {
		if ( null !== $this->pages ) {
			return $this->pages;
		}
		$this->pages = array();
		foreach ( (array) glob( NORI_FE_DIR . 'library/pages/*.json' ) as $file ) {
			$def = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin file.
			if ( is_array( $def ) && ! empty( $def['key'] ) && ! empty( $def['sections'] ) ) {
				$this->pages[ $def['key'] ] = $def;
			}
		}
		uasort( $this->pages, fn( $a, $b ) => ( $a['order'] ?? 50 ) <=> ( $b['order'] ?? 50 ) );
		return $this->pages;
	}

	public function page( $key ) {
		return $this->pages()[ $key ] ?? null;
	}

	/** @return array<string, array> Style key => { label, description }: the looks the templates come in. */
	public static function styles() {
		static $styles = null;
		if ( null === $styles ) {
			$styles = json_decode( (string) file_get_contents( NORI_FE_DIR . 'library/styles.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin file.
			$styles = is_array( $styles ) ? $styles : array();
		}
		return $styles;
	}

	/** The site's style: the one its header and footer were made in, or the first style. */
	public static function site_style() {
		$saved = (array) get_option( Site_Parts::OPTION, array() );
		$style = (string) ( $saved['style'] ?? '' );
		return isset( self::styles()[ $style ] ) ? $style : (string) array_key_first( self::styles() );
	}

	/** Page templates in one style. */
	public function pages_in( $style ) {
		return array_filter( $this->pages(), fn( $p ) => ( $p['style'] ?? '' ) === $style );
	}

	/** The screenshot shipped for a page template (or the site header and footer), if there is one. */
	public static function screenshot_url( $key ) {
		$file = 'assets/templates/' . sanitize_key( $key ) . '.jpg';
		return file_exists( NORI_FE_DIR . $file ) ? NORI_FE_URL . $file : '';
	}

	/**
	 * What the site already knows about itself, for slots of the same name: the business name from
	 * the site title, the email from WordPress's settings. Everything else keeps its sample text.
	 */
	public static function site_details() {
		$name = get_bloginfo( 'name' );
		return array_filter(
			array(
				'business_name' => $name,
				'email'         => get_option( 'admin_email' ),
				'legal_line'    => '© ' . gmdate( 'Y' ) . ( $name ? ' ' . $name : '' ),
			)
		);
	}

	/**
	 * Make a draft page from a page template.
	 *
	 * @param string $key   Page template key.
	 * @param array  $args  'title' to override the page title; 'content' => [ section index => slot values ]
	 *                      to change what the template fills in; anything create_page() takes.
	 * @return array|WP_Error See create_page().
	 */
	public function create_from_page( $key, array $args = array() ) {
		$p = $this->page( $key );
		if ( ! $p ) {
			return new WP_Error( 'nori_template', __( 'There is no page template with that name.', 'nori-for-elementor' ) );
		}
		$title = isset( $args['title'] ) && '' !== trim( (string) $args['title'] ) ? (string) $args['title'] : ( $p['title'] ?? $p['label'] ?? $key );
		return $this->create_page( $title, $this->page_sections( $key, (array) ( $args['content'] ?? array() ) ), $args );
	}

	/**
	 * A page template's sections, each with the site's details, the template's own content and the
	 * content given for it (by section index).
	 *
	 * @return array[] [ key, variant, content ] per section; [] for an unknown template.
	 */
	public function page_sections( $key, array $content = array() ) {
		$p = $this->page( $key );
		if ( ! $p ) {
			return array();
		}
		$details  = self::site_details();
		$sections = array();
		foreach ( array_values( $p['sections'] ) as $i => $s ) {
			$tpl        = $this->get( $s['key'] ?? '' );
			$sections[] = array(
				'key'     => $s['key'] ?? '',
				'variant' => $s['variant'] ?? '',
				'content' => array_merge(
					array_intersect_key( $details, (array) ( $tpl['slots'] ?? array() ) ),
					(array) ( $s['content'] ?? array() ),
					(array) ( $content[ $i ] ?? array() )
				),
			);
		}
		return $sections;
	}

	/* ------------------------------------------------------------------
	 * Content
	 * --------------------------------------------------------------- */

	/**
	 * The content a template is built with: what was given, cleaned per slot type, with the
	 * template's sample content for anything left out (when $samples is true).
	 *
	 * @return array|WP_Error
	 */
	public function content( $key, array $given = array(), $samples = true ) {
		$t = $this->get( $key );
		if ( ! $t ) {
			return new WP_Error( 'nori_template', __( 'There is no template with that name.', 'nori-for-elementor' ) );
		}
		$out     = array();
		$missing = array();
		foreach ( (array) ( $t['slots'] ?? array() ) as $skey => $slot ) {
			$value = array_key_exists( $skey, $given ) ? $this->clean( $slot, $given[ $skey ] ) : null;
			if ( $this->blank( $value ) && $samples && array_key_exists( 'sample', $slot ) && ! array_key_exists( $skey, $given ) ) {
				$value = $this->clean( $slot, $slot['sample'] );
			}
			if ( $this->blank( $value ) && ! array_key_exists( $skey, $given ) && 'business_name' === $skey ) {
				$value = self::site_details()['business_name'] ?? null; // The site title, when nothing else is given.
			}
			if ( $this->blank( $value ) && ! empty( $slot['required'] ) ) {
				$missing[] = $skey;
			}
			$out[ $skey ] = $value;
		}
		if ( $missing ) {
			/* translators: %s: list of slot names. */
			return new WP_Error( 'nori_template_missing', sprintf( __( 'This template needs: %s.', 'nori-for-elementor' ), implode( ', ', $missing ) ), array( 'missing' => $missing ) );
		}
		return $out;
	}

	private function blank( $value ) {
		return null === $value || '' === $value || array() === $value;
	}

	/** Clean one value for its slot type. */
	private function clean( array $slot, $value ) {
		$type = $slot['type'] ?? 'text';
		switch ( $type ) {
			case 'text':
				return is_scalar( $value ) ? trim( sanitize_text_field( (string) $value ) ) : null;
			case 'textarea':
				return is_scalar( $value ) ? trim( sanitize_textarea_field( (string) $value ) ) : null;
			case 'url':
				return is_scalar( $value ) ? self::clean_url( (string) $value ) : null;
			case 'icon':
				$name = is_scalar( $value ) ? sanitize_key( (string) $value ) : '';
				return in_array( $name, self::icons(), true ) ? $name : ( $slot['default'] ?? 'check' );
			case 'image':
				if ( is_numeric( $value ) && (int) $value > 0 ) {
					return array( 'id' => (int) $value );
				}
				if ( is_array( $value ) && ! empty( $value['id'] ) && is_numeric( $value['id'] ) ) {
					return array( 'id' => (int) $value['id'] );
				}
				$url = is_array( $value ) ? ( $value['url'] ?? '' ) : $value;
				$url = is_scalar( $url ) ? esc_url_raw( (string) $url, array( 'http', 'https' ) ) : '';
				return $url ? array( 'url' => $url ) : null;
			case 'video':
				// An attachment id, an https address, or "sample" for the loop that ships with Nori.
				if ( 'sample' === $value ) {
					return array( 'url' => NORI_FE_URL . self::SAMPLE_VIDEO );
				}
				if ( is_numeric( $value ) && (int) $value > 0 ) {
					return array( 'id' => (int) $value );
				}
				if ( is_array( $value ) && ! empty( $value['id'] ) && is_numeric( $value['id'] ) ) {
					return array( 'id' => (int) $value['id'] );
				}
				$url = is_array( $value ) ? ( $value['url'] ?? '' ) : $value;
				$url = is_scalar( $url ) ? esc_url_raw( (string) $url, array( 'http', 'https' ) ) : '';
				return $url ? array( 'url' => $url ) : null;
			case 'link':
				if ( ! is_array( $value ) ) {
					return null;
				}
				$label = isset( $value['label'] ) && is_scalar( $value['label'] ) ? trim( sanitize_text_field( (string) $value['label'] ) ) : '';
				$url   = isset( $value['url'] ) && is_scalar( $value['url'] ) ? self::clean_url( (string) $value['url'] ) : '';
				return '' === $label ? null : array(
					'label' => $label,
					'url'   => '' === $url ? '#' : $url,
				);
			case 'list':
				if ( ! is_array( $value ) ) {
					return null;
				}
				$fields = (array) ( $slot['fields'] ?? array() );
				$items  = array();
				foreach ( array_values( $value ) as $item ) {
					if ( ! is_array( $item ) ) {
						continue;
					}
					$row = array();
					foreach ( $fields as $fkey => $field ) {
						$row[ $fkey ] = array_key_exists( $fkey, $item ) ? $this->clean( $field, $item[ $fkey ] ) : null;
					}
					$items[] = $row;
				}
				if ( isset( $slot['max'] ) ) {
					$items = array_slice( $items, 0, (int) $slot['max'] );
				}
				return $items;
		}
		return null;
	}

	/** Links: http(s), mail, phone, a page anchor (#services) or a path on this site. */
	public static function clean_url( $url ) {
		$url = trim( $url );
		if ( '' === $url ) {
			return '';
		}
		if ( preg_match( '/^#[A-Za-z][A-Za-z0-9_-]*$/', $url ) || '#' === $url ) {
			return $url;
		}
		if ( '/' === $url[0] && ( ! isset( $url[1] ) || '/' !== $url[1] ) ) {
			return esc_url_raw( home_url( $url ) );
		}
		return (string) esc_url_raw( $url, array( 'http', 'https', 'mailto', 'tel' ) );
	}

	/* ------------------------------------------------------------------
	 * Building
	 * --------------------------------------------------------------- */

	/**
	 * Build a template into Elementor element data. Imports the Nori classes and color variables it
	 * uses first, so the section looks right straight away and stays connected to them.
	 *
	 * @param string $key     Template key.
	 * @param string $variant Variant key ('' = the site's style, or the first one).
	 * @param array  $content Slot values (see content()).
	 * @param array  $args    'samples' (bool, default true): fill what's left out with sample content.
	 * @return array|WP_Error { elements: array, variant: string, classes: string[], warnings: string[] }
	 */
	public function build( $key, $variant = '', array $content = array(), array $args = array() ) {
		if ( ! Plugin::atomic_available() ) {
			return new WP_Error( 'nori_template_atomic', __( 'Templates need Elementor\'s Atomic editor (Elementor 4).', 'nori-for-elementor' ) );
		}
		$t = $this->get( $key );
		if ( ! $t ) {
			return new WP_Error( 'nori_template', __( 'There is no template with that name.', 'nori-for-elementor' ) );
		}
		if ( '' === $variant && isset( $t['variants'][ self::site_style() ] ) ) {
			$variant = self::site_style(); // No variant asked for: the site's style.
		}
		if ( '' === $variant || ! isset( $t['variants'][ $variant ] ) ) {
			if ( '' !== $variant ) {
				/* translators: %s: variant name. */
				return new WP_Error( 'nori_template_variant', sprintf( __( 'This template has no "%s" variant.', 'nori-for-elementor' ), $variant ) );
			}
			$variant = (string) array_key_first( $t['variants'] );
		}
		$values = $this->content( $key, $content, $args['samples'] ?? true );
		if ( is_wp_error( $values ) ) {
			return $values;
		}

		$tree = (array) ( $t['variants'][ $variant ]['tree'] ?? array() );
		// "scroll-anim" stands for the site's scroll animation: Slide-Fade-Blur when it's on, else Exit-Fade-Up.
		$anim = Plugin::instance()->modules->is_enabled( 'slide-fade-blur' ) ? 'slide-fade-blur' : 'exit-fade-up';
		$tree = json_decode( str_replace( '"scroll-anim"', '"' . $anim . '"', (string) wp_json_encode( $tree ) ), true );

		// Classes and variables first, so references point at real Elementor ids.
		$class_keys = array();
		$var_keys   = array();
		$this->collect( $tree, $class_keys, $var_keys );
		$prepared = $this->prepare( array_keys( $class_keys ), array_keys( $var_keys ) );
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}

		$this->switch_on_modules( array_keys( $class_keys ) );

		$this->errors = array();
		$elements     = $this->render_nodes( $tree, array( 'content' => $values ) );
		if ( $elements && ! empty( $t['anchor'] ) && empty( $args['no_anchor'] ) ) {
			$this->set_anchor( $elements[0], (string) ( $args['anchor'] ?? $t['anchor'] ) );
		}
		$this->validate( $elements );
		if ( $this->errors ) {
			return new WP_Error( 'nori_template_invalid', __( 'Elementor did not accept this section.', 'nori-for-elementor' ) . ' ' . implode( ' ', array_slice( $this->errors, 0, 3 ) ), array( 'errors' => $this->errors ) );
		}
		return array(
			'elements' => $elements,
			'variant'  => $variant,
			'classes'  => array_keys( $class_keys ),
			'warnings' => $prepared,
		);
	}

	/** Every global class and var() variable a tree uses. */
	private function collect( array $nodes, array &$classes, array &$vars ) {
		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			if ( isset( $node['each'] ) ) {
				$this->collect( array( $node['node'] ?? array() ), $classes, $vars );
				continue;
			}
			foreach ( (array) ( $node['classes'] ?? array() ) as $c ) {
				$classes[ (string) $c ] = true;
			}
			if ( 'button' === ( $node['el'] ?? '' ) ) {
				if ( empty( $node['classes'] ) ) {
					$classes['btn-primary'] = true;
				}
			}
			preg_match_all( '/var\(([a-z0-9-]+)\)/', (string) wp_json_encode( $node['css'] ?? array() ), $m );
			foreach ( $m[1] as $v ) {
				$vars[ $v ] = true;
			}
			$this->collect( (array) ( $node['children'] ?? array() ), $classes, $vars );
		}
	}

	/**
	 * Import the classes and variables a template needs. Classes that already exist are linked and
	 * left alone (the site's edits stay). Returns warnings (e.g. size variables without Pro).
	 *
	 * @return string[]|WP_Error
	 */
	private function prepare( array $class_keys, array $var_keys ) {
		$library  = $this->library->classes();
		$unknown  = array_diff( $class_keys, array_keys( $library ) );
		$warnings = array();
		if ( $unknown ) {
			/* translators: %s: list of class names. */
			return new WP_Error( 'nori_template_class', sprintf( __( 'Unknown Nori classes in this template: %s.', 'nori-for-elementor' ), implode( ', ', $unknown ) ) );
		}
		$known_vars = array_intersect( $var_keys, array_keys( $this->library->variables() ) );
		if ( $known_vars ) {
			$this->variables->import( $known_vars );
		}
		if ( $class_keys ) {
			$r = $this->classes->import( $class_keys );
			if ( ! empty( $r['errors'] ) ) {
				return new WP_Error( 'nori_template_import', reset( $r['errors'] ) );
			}
		}
		$status = $this->classes->status();
		if ( is_wp_error( $status ) ) {
			return $status;
		}
		$this->class_ids = array();
		foreach ( $class_keys as $k ) {
			if ( empty( $status[ $k ]['id'] ) ) {
				/* translators: %s: class name. */
				$warnings[] = sprintf( __( '%s could not be added to Elementor, so it was left off.', 'nori-for-elementor' ), $k . '-np' );
				continue;
			}
			$this->class_ids[ $k ] = (string) $status[ $k ]['id'];
		}
		$this->converter = new Style_Converter( Style_Schema::get(), $this->variables->reference_map() );
		return $warnings;
	}

	/**
	 * Switch on the modules whose classes a template uses (Background Video, Fit Text...), so the
	 * section works as soon as it's on the page. Modules that are already on are left alone.
	 */
	private function switch_on_modules( array $class_keys ) {
		$mods = Plugin::instance()->modules;
		$on   = array();
		foreach ( $this->library->classes() as $key => $c ) {
			if ( in_array( $key, $class_keys, true ) && ! empty( $c['module'] ) && ! $mods->is_enabled( $c['module'] ) ) {
				$on[ $c['module'] ] = true;
			}
		}
		if ( $on ) {
			( new Admin( Plugin::instance() ) )->app()->save( array( 'modules' => array( 'enabled' => $on ) ) );
		}
	}

	/** @return array[] Elementor element data. */
	private function render_nodes( array $nodes, array $scope ) {
		$out = array();
		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			if ( isset( $node['each'] ) ) {
				$list = $this->lookup( (string) $node['each'], $scope );
				$as   = (string) ( $node['as'] ?? 'item' );
				$list = is_array( $list ) ? array_values( $list ) : array();
				// "from" and "limit" take part of the list, so the first item can be laid out on its own.
				$from = max( 0, (int) ( $node['from'] ?? 0 ) );
				$list = array_slice( $list, $from, isset( $node['limit'] ) ? max( 0, (int) $node['limit'] ) : null, true );
				$pos  = 0;
				foreach ( $list as $i => $item ) {
					$inner           = $scope;
					$inner[ $as ]    = $item;
					$inner['_index'] = $i;
					if ( isset( $node['fill'] ) ) {
						$inner = array_merge( $inner, self::fill_spans( (array) $node['fill'], $pos++, count( $list ) ) );
					}
					$out             = array_merge( $out, $this->render_nodes( array( $node['node'] ?? array() ), $inner ) );
				}
				continue;
			}
			if ( isset( $node['if'] ) && $this->blank( $this->lookup( (string) $node['if'], $scope ) ) ) {
				continue;
			}
			if ( isset( $node['unless'] ) && ! $this->blank( $this->lookup( (string) $node['unless'], $scope ) ) ) {
				continue;
			}
			$el = $this->render_node( $node, $scope );
			if ( $el ) {
				$out[] = $el;
			}
		}
		return $out;
	}

	/**
	 * Grid spans that leave no gaps, whatever the number of items: "_span", "_rowspan" (desktop) and
	 * "_span_m" (mobile) for the item at $pos of $n, for css like "grid-column": "{_span}".
	 * $fill: 'cols' (desktop columns), 'mobile_cols', 'height' and 'tall' ("_height" for one-row and stretched
 * items), and optionally 'beside' => [ cols, rows ]: an
	 * area next to a large first item that the first items fill before the full-width rows start.
	 */
	public static function fill_spans( array $fill, $pos, $n ) {
		$cols = max( 1, (int) ( $fill['cols'] ?? 3 ) );
		$row  = 1;
		if ( isset( $fill['beside'] ) ) {
			list( $bc, $br ) = array_map( 'intval', (array) $fill['beside'] );
			$cells           = $bc * $br;
			if ( $pos < min( $n, $cells ) ) {
				$in   = min( $n, $cells );
				$span = self::last_row_span( $pos, $in, $bc );
				// A single row beside the large item stretches to its full height.
				$row = (int) ceil( $in / $bc ) < $br ? $br : 1;
			} else {
				$span = self::last_row_span( $pos - $cells, $n - $cells, $cols );
			}
		} else {
			$span = self::last_row_span( $pos, $n, $cols );
		}
		return array(
			'_span'    => 'span ' . $span,
			'_rowspan' => 'span ' . $row,
			// 'height' for a normal tile; a tile stretched over several rows takes 'tall'.
			'_height'  => (string) ( $row > 1 ? ( $fill['tall'] ?? '100%' ) : ( $fill['height'] ?? 'auto' ) ),
			'_span_m'  => 'span ' . self::last_row_span( $pos, $n, max( 1, (int) ( $fill['mobile_cols'] ?? 2 ) ) ),
		);
	}

	/** How many columns the item at $pos spans so that the last of $n rows is full. */
	private static function last_row_span( $pos, $n, $cols ) {
		$left = $n % $cols;
		if ( 0 === $left || $pos < $n - $left ) {
			return 1;
		}
		$j = $pos - ( $n - $left );
		return intdiv( $cols, $left ) + ( $j < $cols % $left ? 1 : 0 );
	}

	private function render_node( array $node, array $scope ) {
		$type = (string) ( $node['el'] ?? '' );

		// A button is a flexbox link with a paragraph label (and an optional icon), so the label and
		// icon can each be styled and animated on their own.
		if ( 'button' === $type ) {
			$link = $this->fill( $node['link'] ?? '', $scope );
			if ( is_array( $link ) ) {
				$label = $link['label'] ?? '';
				$url   = $link['url'] ?? '#';
			} else {
				$label = $this->fill( $node['text'] ?? '', $scope );
				$url   = $link;
			}
			if ( $this->blank( $label ) ) {
				return null;
			}
			$children = array(
				array(
					'el'   => 'e-paragraph',
					'text' => $label,
					'css'  => array( 'desktop' => array( 'margin' => '0px' ) ),
				),
			);
			if ( ! empty( $node['icon'] ) ) {
				$children[] = array(
					'el'      => 'e-svg',
					'icon'    => $node['icon'],
					'css'     => array(
						'desktop' => array(
							'width'  => '18px',
							'height' => '18px',
						),
					),
				);
			}
			return $this->render_node(
				array(
					'el'       => 'e-flexbox',
					'label'    => $node['label'] ?? __( 'Button', 'nori-for-elementor' ),
					'tag'      => 'a',
					'link'     => is_string( $url ) && '' !== $url ? $url : '#',
					'classes'  => $node['classes'] ?? array( 'btn-primary' ),
					// Elementor gives flexboxes a full width; a button is as wide as its label.
					'css'      => array_replace_recursive( array( 'desktop' => array( 'flex-direction' => 'row', 'width' => 'auto' ) ), (array) ( $node['css'] ?? array() ) ),
					'children' => $children,
				),
				$scope
			);
		}

		if ( ! in_array( $type, self::ELEMENTS, true ) ) {
			/* translators: %s: element type. */
			$this->errors[] = sprintf( __( 'Unknown element %s.', 'nori-for-elementor' ), $type );
			return null;
		}

		$id       = self::new_id();
		$settings = array();
		$friendly = array();

		if ( isset( $node['tag'] ) ) {
			$friendly['tag'] = (string) $this->fill( $node['tag'], $scope );
		}
		if ( isset( self::TEXT_PROP[ $type ] ) && isset( $node['text'] ) ) {
			$text = $this->fill( $node['text'], $scope );
			if ( $this->blank( $text ) ) {
				return null;
			}
			$friendly[ self::TEXT_PROP[ $type ] ] = (string) $text;
		}
		if ( isset( $node['link'] ) ) {
			$url = $this->fill( $node['link'], $scope );
			if ( is_string( $url ) && '' !== $url ) {
				$friendly['link'] = array( 'destination' => $url );
			}
		}
		if ( 'e-svg' === $type ) {
			$icon = (string) $this->fill( $node['icon'] ?? 'check', $scope );
			$icon = in_array( $icon, self::icons(), true ) ? $icon : 'check';
			$friendly['svg'] = array( 'url' => self::icon_url( $icon ) );
		}
		if ( 'e-image' === $type ) {
			$img = $this->fill( $node['image'] ?? null, $scope );
			if ( is_array( $img ) && ( ! empty( $img['id'] ) || ! empty( $img['url'] ) ) ) {
				$src = ! empty( $img['id'] ) ? array( 'id' => (int) $img['id'] ) : array( 'url' => $img['url'] );
				if ( ! empty( $img['id'] ) && wp_get_attachment_url( (int) $img['id'] ) ) {
					$src['url'] = wp_get_attachment_url( (int) $img['id'] );
				}
				$friendly['image'] = array( 'src' => $src );
			}
			// No image: Elementor shows its placeholder, ready to be replaced in the editor.
		}
		if ( 'e-background-video' === $type && isset( $node['video'] ) ) {
			$vid = $this->fill( $node['video'], $scope );
			if ( is_array( $vid ) && ! empty( $vid['id'] ) && wp_get_attachment_url( (int) $vid['id'] ) ) {
				// Elementor plays the address; an attachment id alone isn't enough for its template.
				$friendly['source'] = array( 'url' => wp_get_attachment_url( (int) $vid['id'] ) );
			} elseif ( is_array( $vid ) && ! empty( $vid['url'] ) ) {
				$friendly['source'] = array( 'url' => $vid['url'] );
			}
			// No video: the section shows its background color until one is picked in the editor.
		}
		foreach ( (array) ( $node['settings'] ?? array() ) as $k => $v ) {
			$friendly[ $k ] = $this->fill( $v, $scope );
		}

		$class = $this->element_class( $type );
		$schema = $class ? $class::get_props_schema() : array();
		foreach ( $friendly as $k => $v ) {
			if ( ! isset( $schema[ $k ] ) ) {
				/* translators: 1: setting, 2: element type. */
				$this->errors[] = sprintf( __( '%1$s is not a setting of %2$s.', 'nori-for-elementor' ), $k, $type );
				continue;
			}
			$typed = $this->converter->convert( $schema[ $k ], $v );
			if ( null === $typed && 'image' === $k && isset( $v['src']['id'], $v['src']['url'] ) ) {
				// Elementor 4.3.4 and later take an image by id or by url, not both.
				$typed = $this->converter->convert( $schema[ $k ], array( 'src' => array( 'id' => $v['src']['id'] ) ) + $v );
			}
			if ( null === $typed ) {
				/* translators: 1: setting, 2: element type. */
				$this->errors[] = sprintf( __( 'The %1$s of %2$s could not be set.', 'nori-for-elementor' ), $k, $type );
				continue;
			}
			$settings[ $k ] = $typed;
		}

		// Global classes, plus one local style for this element's own layout.
		$class_ids = array();
		foreach ( (array) ( $node['classes'] ?? array() ) as $c ) {
			if ( isset( $this->class_ids[ $c ] ) ) {
				$class_ids[] = $this->class_ids[ $c ];
			}
		}
		$styles = array();
		if ( ! empty( $node['css'] ) ) {
			$style_id  = 'e-' . $id . '-' . substr( md5( $id . wp_rand() ), 0, 7 );
			$converted = $this->converter->to_variants( $this->fill_css( (array) $node['css'], $scope ) );
			if ( $converted['fallback'] ) {
				/* translators: %s: CSS properties. */
				$this->errors[] = sprintf( __( 'Elementor has no setting for: %s.', 'nori-for-elementor' ), implode( ', ', $converted['fallback'] ) );
			}
			$styles[ $style_id ] = array(
				'id'       => $style_id,
				'label'    => 'local',
				'type'     => 'class',
				'variants' => $converted['variants'],
			);
			array_unshift( $class_ids, $style_id );
		}
		if ( $class_ids ) {
			$settings['classes'] = array(
				'$$type' => 'classes',
				'value'  => $class_ids,
			);
		}

		$el = array(
			'id'       => $id,
			'elType'   => $class && $this->is_widget( $type ) ? 'widget' : $type,
			'settings' => $settings,
			'elements' => array(),
			'styles'   => $styles ? $styles : array(),
		);
		if ( $this->is_widget( $type ) ) {
			$el['widgetType'] = $type;
		} else {
			$el['isInner']  = false;
			$el['elements'] = $this->render_nodes( (array) ( $node['children'] ?? array() ), $scope );
		}
		if ( ! empty( $node['label'] ) ) {
			$el['editor_settings'] = array( 'title' => (string) $this->fill( $node['label'], $scope ) );
		}
		return $el;
	}

	/** Replace {slot} and {item.field} in a value. A value that is only "{x}" becomes x itself (arrays too). */
	private function fill( $value, array $scope ) {
		if ( is_array( $value ) ) {
			return array_map( fn( $v ) => $this->fill( $v, $scope ), $value );
		}
		if ( ! is_string( $value ) ) {
			return $value;
		}
		if ( preg_match( '/^\{([a-z0-9_.|]+)\}$/i', $value, $m ) ) {
			return $this->lookup( $m[1], $scope );
		}
		return preg_replace_callback(
			'/\{([a-z0-9_.|]+)\}/i',
			function ( $m ) use ( $scope ) {
				$v = $this->lookup( $m[1], $scope );
				return is_scalar( $v ) ? (string) $v : '';
			},
			$value
		);
	}

	/** Local styles may use slots too (e.g. a column count); var(...) is left for the converter. */
	private function fill_css( array $css, array $scope ) {
		foreach ( $css as $k => $v ) {
			$css[ $k ] = is_array( $v ) ? $this->fill_css( $v, $scope ) : ( is_string( $v ) && false !== strpos( $v, '{' ) ? $this->fill( $v, $scope ) : $v );
		}
		return $css;
	}

	/**
	 * "headline" or "cta_primary.label" from the content; "item.title" from the loop.
	 * "phone|tel" turns a phone number into a tel: link, "email|mailto" an address into a mailto: link,
	 * "_index|num" a loop position into 01, 02…, "name|initials" a name into its initials.
	 */
	private function lookup( $path, array $scope ) {
		$filter = '';
		if ( false !== strpos( $path, '|' ) ) {
			list( $path, $filter ) = explode( '|', $path, 2 );
		}
		$value = $this->lookup_path( $path, $scope );
		if ( 'tel' === $filter ) {
			$digits = is_scalar( $value ) ? preg_replace( '/[^0-9+]/', '', (string) $value ) : '';
			return '' === $digits ? '' : 'tel:' . $digits;
		}
		if ( 'num' === $filter ) {
			return is_numeric( $value ) ? sprintf( '%02d', (int) $value + 1 ) : '';
		}
		if ( 'count' === $filter ) {
			return is_array( $value ) ? count( $value ) : 0;
		}
		if ( 'initials' === $filter ) {
			$words = is_scalar( $value ) ? preg_split( '/\s+/', trim( (string) $value ) ) : array();
			return strtoupper( implode( '', array_map( fn( $w ) => mb_substr( $w, 0, 1 ), array_slice( array_filter( (array) $words ), 0, 2 ) ) ) );
		}
				if ( 'mailto' === $filter ) {
			$email = is_scalar( $value ) ? sanitize_email( (string) $value ) : '';
			return '' === $email ? '' : 'mailto:' . $email;
		}
		return $value;
	}

	private function lookup_path( $path, array $scope ) {
		$parts = explode( '.', $path );
		$first = array_shift( $parts );
		if ( array_key_exists( $first, $scope ) && 'content' !== $first ) {
			$value = $scope[ $first ];
		} else {
			$value = $scope['content'][ $first ] ?? null;
		}
		foreach ( $parts as $p ) {
			$value = is_array( $value ) && array_key_exists( $p, $value ) ? $value[ $p ] : null;
		}
		return $value;
	}

	private function set_anchor( array &$element, $anchor ) {
		$anchor = sanitize_title( $anchor );
		if ( '' === $anchor ) {
			return;
		}
		$type  = $element['widgetType'] ?? $element['elType'] ?? null;
		$class = $type ? $this->element_class( $type ) : null;
		if ( ! $class || ! method_exists( $class, 'get_props_schema' ) ) {
			return;
		}
		$schema = $class::get_props_schema();
		if ( isset( $schema['_cssid'] ) ) {
			$typed = $this->converter->convert( $schema['_cssid'], $anchor );
			if ( $typed ) {
				$element['settings']['_cssid'] = $typed;
			}
		}
	}

	private function is_widget( $type ) {
		return in_array( $type, array( 'e-heading', 'e-paragraph', 'e-svg', 'e-image', 'e-divider' ), true );
	}

	/** @return string|null The Elementor class for an element type. */
	private function element_class( $type ) {
		$el = \Elementor\Plugin::$instance;
		if ( $this->is_widget( $type ) ) {
			$w = $el->widgets_manager->get_widget_types( $type );
			return $w ? get_class( $w ) : null;
		}
		$e = $el->elements_manager->get_element_types( $type );
		return $e ? get_class( $e ) : null;
	}

	/** Elementor-style element id: 7 lowercase hex characters. */
	public static function new_id() {
		return substr( bin2hex( random_bytes( 4 ) ), 0, 7 );
	}

	/** Check settings and local styles with Elementor's own parsers. */
	private function validate( array $elements ) {
		$style_parser = Style_Parser::make( Style_Schema::get() );
		foreach ( $elements as $el ) {
			$type  = $el['widgetType'] ?? $el['elType'];
			$class = $this->element_class( $type );
			if ( ! $class ) {
				/* translators: %s: element type. */
				$this->errors[] = sprintf( __( 'Elementor has no %s element.', 'nori-for-elementor' ), $type );
				continue;
			}
			$result = Props_Parser::make( $class::get_props_schema() )->validate( $el['settings'] );
			if ( ! $result->is_valid() ) {
				$this->errors[] = $type . ': ' . $result->errors()->to_string();
			}
			foreach ( (array) $el['styles'] as $style ) {
				$r = $style_parser->parse( $style );
				if ( ! $r->is_valid() ) {
					$this->errors[] = $type . ' style: ' . $r->errors()->to_string();
				}
			}
			$this->validate( (array) ( $el['elements'] ?? array() ) );
		}
	}

	/* ------------------------------------------------------------------
	 * Pages
	 * --------------------------------------------------------------- */

	/** Pages built with Elementor that sections can be added to. */
	public static function elementor_pages() {
		return get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'meta_key'       => '_elementor_edit_mode', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- admin list, small.
				'meta_value'     => 'builder', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
	}

	/**
	 * Add built elements to an Elementor page.
	 *
	 * @param int    $post_id  Page.
	 * @param array  $elements From build().
	 * @param string $position 'end' or 'start'.
	 * @return true|WP_Error
	 */
	public function insert( $post_id, array $elements, $position = 'end' ) {
		$post = get_post( $post_id );
		if ( ! $post || ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'nori_template_page', __( 'That page can\'t be edited.', 'nori-for-elementor' ) );
		}
		$doc = \Elementor\Plugin::$instance->documents->get( $post_id, false );
		if ( ! $doc ) {
			return new WP_Error( 'nori_template_page', __( 'That page isn\'t an Elementor page.', 'nori-for-elementor' ) );
		}
		$existing = 'builder' === get_post_meta( $post_id, '_elementor_edit_mode', true ) ? (array) $doc->get_elements_data() : array();
		$all      = 'start' === $position ? array_merge( $elements, $existing ) : array_merge( $existing, $elements );
		update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
		$saved = $doc->save( array( 'elements' => $all ) );
		if ( false === $saved ) {
			return new WP_Error( 'nori_template_save', __( 'Elementor could not save the page.', 'nori-for-elementor' ) );
		}
		return true;
	}

	/**
	 * Create a draft page from one or more sections.
	 *
	 * @param string $title    Page title.
	 * @param array  $sections List of [ key, variant, content ].
	 * @param array  $args     'samples' (bool), 'status' (draft by default).
	 * @return array|WP_Error { id, edit_url, view_url, warnings }
	 */
	public function create_page( $title, array $sections, array $args = array() ) {
		if ( ! current_user_can( 'edit_pages' ) ) {
			return new WP_Error( 'nori_template_page', __( 'You can\'t create pages.', 'nori-for-elementor' ) );
		}
		$motion = $this->motion_defaults();
		$built  = $this->build_sections( $sections, $args );
		if ( is_wp_error( $built ) ) {
			return $built;
		}
		list( $elements, $warnings, $record, $has_site ) = $built;
		$status = in_array( $args['status'] ?? 'draft', array( 'draft', 'publish', 'private' ), true ) ? ( $args['status'] ?? 'draft' ) : 'draft';
		$id     = wp_insert_post(
			array(
				'post_title'  => sanitize_text_field( $title ),
				'post_name'   => sanitize_title( $title ),
				'post_type'   => 'page',
				'post_status' => $status,
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		// A page with Nori's own header and footer hides the theme's; otherwise it keeps them, full width.
		update_post_meta( $id, '_wp_page_template', $has_site ? 'elementor_canvas' : 'elementor_header_footer' );
		update_post_meta( $id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $id, '_elementor_template_type', 'wp-page' );
		$done = $this->insert( $id, $elements );
		if ( is_wp_error( $done ) ) {
			return $done;
		}
		$this->save_record( $id, $record, $args );
		return array(
			'id'       => $id,
			'edit_url' => self::edit_url( $id ),
			'view_url' => get_permalink( $id ),
			'warnings' => $warnings,
			'motion'   => $motion,
		);
	}

	/**
	 * What every page made from templates has: Smooth Scroll (Snappy, 0.5s) and a scroll animation.
	 * Switches on what's off: Smooth Scroll at 0.5s (a site that already has it keeps its own speed),
	 * and Exit-Fade-Up only when no scroll animation is on (Slide-Fade-Blur stays if it's the one).
	 *
	 * @return string[] What was switched on, to tell the user.
	 */
	public function motion_defaults() {
		$mods     = Plugin::instance()->modules;
		$on       = array();
		$settings = array();
		$notes    = array();
		if ( ! $mods->is_enabled( 'smooth-scroll' ) ) {
			$on['smooth-scroll']       = true;
			$settings['smooth-scroll'] = array_merge( $mods->settings( 'smooth-scroll' ), array( 'duration' => 0.5 ) );
			$notes[]                   = __( 'Smooth Scroll is now on (Snappy, 0.5s).', 'nori-for-elementor' );
		}
		if ( ! $mods->is_enabled( 'exit-fade-up' ) && ! $mods->is_enabled( 'slide-fade-blur' ) ) {
			$on['exit-fade-up'] = true;
			$notes[]            = __( 'Exit-Fade-Up is now on: sections fade in and drift up as visitors scroll.', 'nori-for-elementor' );
		}
		if ( ! $on ) {
			return array();
		}
		$r = ( new Admin( Plugin::instance() ) )->app()->save(
			array(
				'modules' => array(
					'enabled'  => $on,
					'settings' => $settings,
				),
			)
		);
		return $r['errors'] ? array( reset( $r['errors'] ) ) : $notes;
	}

	/** Post meta: the sections a page was built from, so it can be changed section by section later. */
	const SECTIONS_META = '_nori_fe_sections';

	/**
	 * Build sections into Elementor elements.
	 *
	 * @return array|WP_Error [ elements, warnings, record (per section: key, variant, content, ids), has_site ]
	 */
	private function build_sections( array $sections, array $args ) {
		$elements = array();
		$warnings = array();
		$record   = array();
		$has_site = false;
		foreach ( $sections as $s ) {
			$key     = (string) ( $s['key'] ?? $s[0] ?? '' );
			$variant = (string) ( $s['variant'] ?? $s[1] ?? '' );
			$content = (array) ( $s['content'] ?? $s[2] ?? array() );
			$built   = $this->build( $key, $variant, $content, $args );
			if ( is_wp_error( $built ) ) {
				return $built;
			}
			$elements = array_merge( $elements, $built['elements'] );
			$warnings = array_merge( $warnings, $built['warnings'] );
			$record[] = array(
				'key'     => $key,
				'variant' => $variant,
				'content' => $content,
				'ids'     => array_values( array_filter( array_map( fn( $e ) => (string) ( $e['id'] ?? '' ), $built['elements'] ) ) ),
			);
			$has_site = $has_site || 'site' === ( $this->get( $key )['group'] ?? '' );
		}
		return array( $elements, $warnings, $record, $has_site );
	}

	private function save_record( $post_id, array $record, array $args ) {
		update_post_meta(
			$post_id,
			self::SECTIONS_META,
			wp_slash(
				wp_json_encode(
					array(
						'samples'  => ! array_key_exists( 'samples', $args ) || ! empty( $args['samples'] ),
						'sections' => $record,
					)
				)
			)
		);
	}

	/** The sections a page was built from, or null for a page Nori didn't make (or made before 0.20). */
	public function page_record( $post_id ) {
		$raw = json_decode( (string) get_post_meta( $post_id, self::SECTIONS_META, true ), true );
		return is_array( $raw ) && isset( $raw['sections'] ) ? $raw : null;
	}

	/**
	 * Change a page Nori made, in place: same post, address, status and SEO fields.
	 *
	 * @param int   $post_id The page.
	 * @param array $change  Either 'sections' => [ key, variant, content ]… to rebuild the whole page, or
	 *                       'set' => [ position => [ variant?, content? (merged over the old) ] ],
	 *                       'remove' => [ positions ], 'add' => [ [ after, key, variant, content ] ]
	 *                       (positions count from 1; after 0 = at the top). Sections that aren't
	 *                       changed keep their Elementor elements exactly, edits made in Elementor included.
	 * @param array $args    'samples' (bool).
	 * @return array|WP_Error { warnings, sections (the new record) }
	 */
	public function update_page( $post_id, array $change, array $args = array() ) {
		$post = get_post( $post_id );
		if ( ! $post || 'page' !== $post->post_type || ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'nori_template_page', __( 'That page can\'t be edited.', 'nori-for-elementor' ) );
		}
		$doc = \Elementor\Plugin::$instance->documents->get( $post_id, false );
		if ( ! $doc ) {
			return new WP_Error( 'nori_template_page', __( 'That page isn\'t an Elementor page.', 'nori-for-elementor' ) );
		}
		$motion = $this->motion_defaults();
		$old    = $this->page_record( $post_id );
		if ( ! array_key_exists( 'samples', $args ) && $old ) {
			$args['samples'] = ! empty( $old['samples'] );
		}

		// The whole page.
		if ( isset( $change['sections'] ) ) {
			$built = $this->build_sections( (array) $change['sections'], $args );
			if ( is_wp_error( $built ) ) {
				return $built;
			}
			list( $elements, $warnings, $record ) = $built;
			if ( false === $doc->save( array( 'elements' => $elements ) ) ) {
				return new WP_Error( 'nori_template_save', __( 'Elementor could not save the page.', 'nori-for-elementor' ) );
			}
			$this->save_record( $post_id, $record, $args );
			return array(
				'warnings' => $warnings,
				'sections' => $record,
				'motion'   => $motion,
			);
		}

		if ( ! $old ) {
			return new WP_Error( 'nori_template_record', __( 'This page wasn\'t made by Nori 0.20 or later, so its sections aren\'t known: give the whole page (a template or sections) to rebuild it in place.', 'nori-for-elementor' ) );
		}
		$sections = $old['sections'];
		$n        = count( $sections );
		$set      = (array) ( $change['set'] ?? array() );
		$remove   = array_map( 'intval', (array) ( $change['remove'] ?? array() ) );
		$add      = (array) ( $change['add'] ?? array() );
		foreach ( array_merge( array_keys( $set ), $remove ) as $pos ) {
			if ( (int) $pos < 1 || (int) $pos > $n ) {
				/* translators: 1: position, 2: number of sections. */
				return new WP_Error( 'nori_template_position', sprintf( __( 'There is no section %1$d: the page has %2$d.', 'nori-for-elementor' ), (int) $pos, $n ) );
			}
		}
		foreach ( $add as $a ) {
			if ( (int) ( $a['after'] ?? 0 ) < 0 || (int) ( $a['after'] ?? 0 ) > $n || ! $this->get( (string) ( $a['key'] ?? '' ) ) ) {
				return new WP_Error( 'nori_template_position', __( 'Each added section needs a section template key and an "after" position from 0 (the top) to the number of sections.', 'nori-for-elementor' ) );
			}
		}

		// What each section becomes: null (removed), the old elements, or new ones.
		$warnings = array();
		$built    = array();
		foreach ( $set as $pos => $c ) {
			$s            = $sections[ (int) $pos - 1 ];
			$s['variant'] = isset( $c['variant'] ) ? (string) $c['variant'] : $s['variant'];
			$s['content'] = array_merge( (array) $s['content'], (array) ( $c['content'] ?? array() ) );
			$b            = $this->build_sections( array( $s ), $args );
			if ( is_wp_error( $b ) ) {
				return $b;
			}
			$built[ (int) $pos ] = $b;
			$warnings            = array_merge( $warnings, $b[1] );
		}
		$added = array();
		foreach ( $add as $a ) {
			$b = $this->build_sections( array( array( 'key' => (string) $a['key'], 'variant' => (string) ( $a['variant'] ?? '' ), 'content' => (array) ( $a['content'] ?? array() ) ) ), $args );
			if ( is_wp_error( $b ) ) {
				return $b;
			}
			$added[ (int) ( $a['after'] ?? 0 ) ][] = $b;
			$warnings                               = array_merge( $warnings, $b[1] );
		}

		// Walk the page as it is now, so elements added in Elementor stay where they are.
		$owner = array();
		foreach ( $sections as $i => $s ) {
			foreach ( (array) ( $s['ids'] ?? array() ) as $id ) {
				$owner[ $id ] = $i + 1;
			}
		}
		$elements = array();
		$record   = array();
		$emit     = function ( $after ) use ( &$elements, &$record, $added ) {
			foreach ( $added[ $after ] ?? array() as $b ) {
				$elements = array_merge( $elements, $b[0] );
				$record   = array_merge( $record, $b[2] );
			}
		};
		$emit( 0 );
		$seen = array();
		foreach ( (array) $doc->get_elements_data() as $el ) {
			$pos = $owner[ (string) ( $el['id'] ?? '' ) ] ?? 0;
			if ( ! $pos ) {
				$elements[] = $el;
				continue;
			}
			if ( isset( $seen[ $pos ] ) ) {
				if ( ! isset( $built[ $pos ] ) && ! in_array( $pos, $remove, true ) ) {
					$elements[] = $el;
				}
				continue;
			}
			$seen[ $pos ] = true;
			if ( isset( $built[ $pos ] ) ) {
				$elements = array_merge( $elements, $built[ $pos ][0] );
				$record   = array_merge( $record, $built[ $pos ][2] );
			} elseif ( ! in_array( $pos, $remove, true ) ) {
				$elements[] = $el;
				$record[]   = $sections[ $pos - 1 ];
			}
			$emit( $pos );
		}
		// Sections deleted in Elementor since: say so if they were to change, and still place what comes after them.
		for ( $pos = 1; $pos <= $n; $pos++ ) {
			if ( ! isset( $seen[ $pos ] ) ) {
				if ( isset( $built[ $pos ] ) ) {
					/* translators: %d: position. */
					return new WP_Error( 'nori_template_position', sprintf( __( 'Section %d was deleted in Elementor, so it can\'t be changed. Add it again instead.', 'nori-for-elementor' ), $pos ) );
				}
				$emit( $pos );
			}
		}
		if ( false === $doc->save( array( 'elements' => $elements ) ) ) {
			return new WP_Error( 'nori_template_save', __( 'Elementor could not save the page.', 'nori-for-elementor' ) );
		}
		$this->save_record( $post_id, $record, $args );
		return array(
			'warnings' => $warnings,
			'sections' => $record,
			'motion'   => $motion,
		);
	}

	public static function edit_url( $post_id ) {
		return admin_url( 'post.php?post=' . (int) $post_id . '&action=elementor' );
	}
}
