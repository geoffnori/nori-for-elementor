<?php
/**
 * A site-wide header and footer built from Nori's templates, kept as one global copy.
 *
 * With Elementor Pro they're Theme Builder templates (type header / footer) set to show on the
 * entire site, so Pro manages them like any other Theme Builder part. Without Pro, Nori saves them
 * as Elementor templates (Templates > Saved Templates) and shows them on every page itself when the
 * site uses the Hello Elementor theme. Either way there is one header and one footer to edit, in
 * Elementor, and every page shows the change.
 *
 * @package Nori_For_Elementor
 */

namespace Nori\Elementor;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Site_Parts {

	/** Option: [ 'header' => post id, 'footer' => post id ] made by Nori. */
	const OPTION = 'nori_fe_site_parts';

	/** Theme Nori can show its header and footer in without Pro. */
	const THEME = 'hello-elementor';

	/** @var Templates */
	private $templates;

	public function __construct( Templates $templates ) {
		$this->templates = $templates;
	}

	public function register_hooks() {
		add_action( 'wp', array( $this, 'maybe_take_over_theme' ) );
	}

	/** The names they get in Elementor (Theme Builder or Saved Templates). */
	public static function title( $type ) {
		return 'header' === $type ? __( 'Main Header NP', 'nori-for-elementor' ) : __( 'Main Footer NP', 'nori-for-elementor' );
	}

	/**
	 * 0.14.0 named them "Nori header" and "Nori footer". Rename Nori's own to the current names, unless
	 * the site renamed them to something else.
	 */
	public function rename_old_titles() {
		$old = array(
			'header' => 'Nori header',
			'footer' => 'Nori footer',
		);
		foreach ( (array) get_option( self::OPTION, array() ) as $type => $id ) {
			$post = isset( $old[ $type ] ) ? get_post( (int) $id ) : null;
			if ( $post && $old[ $type ] === $post->post_title ) {
				wp_update_post(
					array(
						'ID'         => $post->ID,
						'post_title' => self::title( $type ),
					)
				);
			}
		}
	}

	/** Elementor Pro's Theme Builder is available. */
	public static function theme_builder() {
		return class_exists( '\ElementorPro\Modules\ThemeBuilder\Module' ) && function_exists( 'elementor_theme_do_location' );
	}

	public static function hello_theme() {
		return self::THEME === get_template();
	}

	/**
	 * Where things stand, for the admin card.
	 *
	 * @return array{mode: string, parts: array, can_show: bool, other: array}
	 */
	public function status() {
		$saved = (array) get_option( self::OPTION, array() );
		$parts = array();
		foreach ( array( 'header', 'footer' ) as $type ) {
			$id = (int) ( $saved[ $type ] ?? 0 );
			$p  = $id ? get_post( $id ) : null;
			if ( $p && 'trash' !== $p->post_status ) {
				$parts[ $type ] = array(
					'id'       => $id,
					'title'    => $p->post_title,
					'status'   => $p->post_status,
					'edit_url' => Templates::edit_url( $id ),
					'live'     => self::theme_builder() ? (bool) get_post_meta( $id, '_elementor_conditions', true ) : 'publish' === $p->post_status,
				);
			}
		}
		return array(
			'style'    => Templates::site_style(),
			'mode'     => self::theme_builder() ? 'theme-builder' : 'nori',
			'parts'    => $parts,
			'can_show' => self::theme_builder() || self::hello_theme(),
			'other'    => self::theme_builder() ? $this->other_theme_builder_parts() : array(),
		);
	}

	/** Theme Builder headers and footers that aren't Nori's and are already shown somewhere. */
	private function other_theme_builder_parts() {
		$saved = (array) get_option( self::OPTION, array() );
		$out   = array();
		foreach ( array( 'header', 'footer' ) as $type ) {
			$ids = get_posts(
				array(
					'post_type'      => 'elementor_library',
					'post_status'    => 'publish',
					'posts_per_page' => 20,
					'fields'         => 'ids',
					'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- admin card, small.
						array(
							'key'   => '_elementor_template_type',
							'value' => $type,
						),
					),
				)
			);
			foreach ( $ids as $id ) {
				if ( (int) $id !== (int) ( $saved[ $type ] ?? 0 ) && get_post_meta( $id, '_elementor_conditions', true ) ) {
					$out[ $type ][] = (int) $id;
				}
			}
		}
		return $out;
	}

	/**
	 * Make the header and footer (whichever is missing). Content comes from the site's own settings,
	 * with sample text for the rest.
	 *
	 * @return array|WP_Error { made: string[], kept: string[], notes: string[] }
	 */
	/**
	 * Make the site header and footer that are missing.
	 *
	 * @param array $content Slot values per part: [ 'header' => [ menu, phone, cta, logo… ], 'footer' => [ … ] ],
	 *                       over the business name, email and legal line from the site.
	 * @param array  $replace Parts to make again even though they exist ('header', 'footer'). The old one
	 *                        goes to the trash, so it can be restored.
	 * @param string $style   The look to make them in (a key of Templates::styles()); '' keeps the site's.
	 * @return array|WP_Error { made: string[], kept: string[], replaced: string[], notes: string[] }
	 */
	public function create( array $content = array(), array $replace = array(), $style = '' ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'nori_site_parts', __( 'Only administrators can add a site header and footer.', 'nori-for-elementor' ) );
		}
		$status = $this->status();
		$saved  = (array) get_option( self::OPTION, array() );
		$style  = isset( Templates::styles()[ $style ] ) ? $style : Templates::site_style();
		$made     = array();
		$kept     = array();
		$replaced = array();
		$notes    = array();
		$name   = get_bloginfo( 'name' );
		$common = array(
			'business_name' => $name ? $name : null,
			'email'         => get_option( 'admin_email' ),
			'legal_line'    => '© ' . gmdate( 'Y' ) . ( $name ? ' ' . $name : '' ),
			// The site's logo (Customizer or Site Identity), so a header made later still shows it.
			'logo'          => wp_attachment_is_image( (int) get_theme_mod( 'custom_logo' ) ) ? (int) get_theme_mod( 'custom_logo' ) : null,
		);
		$common = array_filter( $common );
		// A new header has the phone menu button: switch on the module that runs it.
		$making_header = ! isset( $status['parts']['header'] ) || in_array( 'header', $replace, true );
		$modules       = Plugin::instance()->modules;
		if ( $making_header && ! $modules->is_enabled( 'mobile-menu' ) ) {
			$r = ( new Admin( Plugin::instance() ) )->app()->save( array( 'modules' => array( 'enabled' => array( 'mobile-menu' => true ) ) ) );
			if ( $r['errors'] ) {
				$notes[] = reset( $r['errors'] );
			} else {
				$notes[] = __( 'Mobile Menu is on, so phones get a menu button.', 'nori-for-elementor' );
			}
		}
		foreach ( array(
			'header' => 'site-header',
			'footer' => 'site-footer',
		) as $type => $key ) {
			$redo = isset( $status['parts'][ $type ] ) && in_array( $type, $replace, true );
			if ( isset( $status['parts'][ $type ] ) && ! $redo ) {
				$kept[] = $type;
				continue;
			}
			$tpl    = $this->templates->get( $key );
			$values = array_merge( array_intersect_key( $common, (array) ( $tpl['slots'] ?? array() ) ), (array) ( $content[ $type ] ?? array() ) );
			$built  = $this->templates->build( $key, $style, $values, array( 'no_anchor' => true ) );
			if ( is_wp_error( $built ) ) {
				return $built;
			}
			if ( $redo ) {
				wp_trash_post( $status['parts'][ $type ]['id'] );
				$replaced[] = $type;
			}
			$id = self::theme_builder() ? $this->create_theme_builder_part( $type, $built['elements'], $status['other'][ $type ] ?? array(), $notes ) : $this->create_library_part( $type, $built['elements'] );
			if ( is_wp_error( $id ) ) {
				return $id;
			}
			$saved[ $type ]  = $id;
			$saved['style'] = $style;
			update_option( self::OPTION, $saved, false );
			$made[] = $type;
		}
		if ( ! self::theme_builder() && ! self::hello_theme() && $made ) {
			$notes[] = __( 'Your theme isn\'t Hello Elementor, so Nori can\'t show them on your pages yet. Switch to Hello Elementor, or use Elementor Pro\'s Theme Builder.', 'nori-for-elementor' );
		}
		return array(
			'made'     => $made,
			'kept'     => $kept,
			'replaced' => $replaced,
			'notes'    => $notes,
		);
	}

	/** Elementor Pro: a Theme Builder header or footer, shown on the entire site. */
	private function create_theme_builder_part( $type, array $elements, array $others, array &$notes ) {
		// Theme Builder already wraps a header in <header> and a footer in <footer>; one landmark is enough.
		if ( isset( $elements[0]['settings']['tag']['value'] ) ) {
			$elements[0]['settings']['tag']['value'] = 'div';
		}
		$doc = \Elementor\Plugin::$instance->documents->create(
			$type,
			array(
				'post_title'  => self::title( $type ),
				'post_status' => 'publish',
			)
		);
		if ( is_wp_error( $doc ) || ! $doc ) {
			return is_wp_error( $doc ) ? $doc : new WP_Error( 'nori_site_parts', __( 'Elementor could not create the Theme Builder template.', 'nori-for-elementor' ) );
		}
		$doc->save( array( 'elements' => $elements ) );
		$id = (int) $doc->get_main_id();
		if ( $others ) {
			// The site already shows another header or footer: don't take it over without asking.
			$notes[] = 'header' === $type
				? __( 'You already have a header in Theme Builder, so Nori\'s header was saved without display conditions. Set it to Entire Site in Theme Builder when you want to use it.', 'nori-for-elementor' )
				: __( 'You already have a footer in Theme Builder, so Nori\'s footer was saved without display conditions. Set it to Entire Site in Theme Builder when you want to use it.', 'nori-for-elementor' );
			return $id;
		}
		$module = \ElementorPro\Modules\ThemeBuilder\Module::instance();
		if ( method_exists( $module, 'get_conditions_manager' ) ) {
			// Each condition is { type, name, sub_name, sub_id }, joined into "include/general".
			$module->get_conditions_manager()->save_conditions(
				$id,
				array(
					array(
						'type' => 'include',
						'name' => 'general',
					),
				)
			);
		} else {
			update_post_meta( $id, '_elementor_conditions', array( 'include/general' ) );
		}
		return $id;
	}

	/** Without Pro: a saved Elementor template that Nori shows on every page. */
	private function create_library_part( $type, array $elements ) {
		$doc = \Elementor\Plugin::$instance->documents->create(
			'section',
			array(
				'post_title'  => self::title( $type ),
				'post_status' => 'publish',
			)
		);
		if ( is_wp_error( $doc ) || ! $doc ) {
			return is_wp_error( $doc ) ? $doc : new WP_Error( 'nori_site_parts', __( 'Elementor could not create the template.', 'nori-for-elementor' ) );
		}
		$doc->save( array( 'elements' => $elements ) );
		return (int) $doc->get_main_id();
	}

	/* ------------------------------------------------------------------
	 * Showing them without Pro
	 * --------------------------------------------------------------- */

	/** The part to show for a type, or 0. Only without Pro (Theme Builder shows its own). */
	private function part_id( $type ) {
		if ( self::theme_builder() ) {
			return 0;
		}
		$id = (int) ( ( (array) get_option( self::OPTION, array() ) )[ $type ] ?? 0 );
		return $id && 'publish' === get_post_status( $id ) ? $id : 0;
	}

	public function maybe_take_over_theme() {
		if ( is_admin() || self::theme_builder() || ! self::hello_theme() ) {
			return;
		}
		// Elementor Canvas pages have no header or footer; Elementor's own editor previews stay as they are.
		if ( is_singular() && 'elementor_canvas' === get_page_template_slug() ) {
			return;
		}
		if ( is_singular( 'elementor_library' ) || isset( $_GET['elementor-preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only check of Elementor's preview flag.
			return;
		}
		$header = $this->part_id( 'header' );
		$footer = $this->part_id( 'footer' );
		if ( ! $header && ! $footer ) {
			return;
		}
		// Hello shows its own header and footer through one switch, so both of Hello's give way.
		add_filter( 'hello_elementor_header_footer', '__return_false' );
		if ( $header ) {
			add_action( 'elementor/frontend/after_enqueue_styles', fn() => do_action( 'elementor/post/render', $header ) );
			add_action( 'wp_enqueue_scripts', fn() => $this->enqueue( $header ), 20 );
			add_filter( 'hello_elementor_enable_skip_link', '__return_false' );
			add_action(
				'wp_body_open',
				function () use ( $header ) {
					echo '<a class="skip-link screen-reader-text" href="#content">' . esc_html__( 'Skip to content', 'nori-for-elementor' ) . '</a>';
					$this->render( $header );
				},
				20
			);
		}
		if ( $footer ) {
			add_action( 'elementor/frontend/after_enqueue_styles', fn() => do_action( 'elementor/post/render', $footer ) );
			add_action( 'wp_enqueue_scripts', fn() => $this->enqueue( $footer ), 20 );
			add_action( 'get_footer', fn() => $this->render( $footer ), 5 );
		}
	}

	/**
	 * Load the part's Elementor CSS in the head, so the header doesn't flash unstyled. Atomic local
	 * styles are printed for the posts Elementor is told it renders on this page, so the part joins
	 * that list just before Elementor prints them.
	 */
	private function enqueue( $id ) {
		if ( class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
			\Elementor\Core\Files\CSS\Post::create( $id )->enqueue();
		}
	}

	private function render( $id ) {
		echo \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( $id, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Elementor's own rendered content.
	}
}
