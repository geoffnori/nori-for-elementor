<?php
/**
 * Page templates and the site header and footer: making a draft page from a template, and the
 * header and footer (Theme Builder with Elementor Pro, Nori's own global templates without).
 *
 * @package Nori_For_Elementor
 */

namespace Nori\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Templates {

	/** @var Plugin */
	private $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/* ------------------------------------------------------------------
	 * Actions
	 * --------------------------------------------------------------- */

	/** @return array{message: string, type: string, links?: array} */
	public function create_page( array $post ) {
		$key = sanitize_key( (string) ( $post['page_template'] ?? '' ) );
		$p   = $this->plugin->templates->page( $key );
		if ( ! $p ) {
			return array( 'message' => __( 'Choose a page template first.', 'nori-for-elementor' ), 'type' => 'error' );
		}
		$made = $this->plugin->templates->create_from_page( $key );
		if ( is_wp_error( $made ) ) {
			return array( 'message' => $made->get_error_message(), 'type' => 'error' );
		}
		/* translators: %s: page template name. */
		$message = sprintf( __( 'Made a draft page from %s.', 'nori-for-elementor' ), $p['label'] ?? $key );
		if ( $made['warnings'] ) {
			$message .= ' ' . implode( ' ', $made['warnings'] );
		}
		if ( ! empty( $made['motion'] ) ) {
			$message .= ' ' . implode( ' ', $made['motion'] );
		}
		if ( ! $this->plugin->site_parts->status()['parts'] ) {
			$message .= ' ' . __( 'Add the site header and footer too, so the page has them.', 'nori-for-elementor' );
		}
		return array(
			'message' => $message,
			'type'    => 'success',
			'links'   => array(
				__( 'Edit in Elementor', 'nori-for-elementor' ) => Templates::edit_url( $made['id'] ),
				__( 'Preview', 'nori-for-elementor' )           => (string) get_preview_post_link( $made['id'] ),
			),
		);
	}

	/** @return array{message: string, type: string, links?: array} */
	/**
	 * @param string $style   The look (a key of Templates::styles()); '' keeps the site's.
	 * @param bool   $replace Make both again in that style (the old ones go to the trash).
	 */
	public function create_site_parts( $style = '', $replace = false ) {
		$r = $this->plugin->site_parts->create( array(), $replace ? array( 'header', 'footer' ) : array(), $style );
		if ( is_wp_error( $r ) ) {
			return array( 'message' => $r->get_error_message(), 'type' => 'error' );
		}
		if ( $r['replaced'] && $r['made'] ) {
			/* translators: %s: style name. */
			$switched = sprintf( __( 'Made the site header and footer again in the %s style. The old ones are in the trash.', 'nori-for-elementor' ), Templates::styles()[ Templates::site_style() ]['label'] ?? '' );
		}
		if ( ! $r['made'] ) {
			return array( 'message' => __( 'The site header and footer are already there.', 'nori-for-elementor' ), 'type' => 'success' );
		}
		$names   = array(
			'header' => __( 'header', 'nori-for-elementor' ),
			'footer' => __( 'footer', 'nori-for-elementor' ),
		);
		$made    = array_map( fn( $t ) => $names[ $t ], $r['made'] );
		$where   = 'theme-builder' === $this->plugin->site_parts->status()['mode'] ? __( 'in Theme Builder', 'nori-for-elementor' ) : __( 'as Elementor templates', 'nori-for-elementor' );
		/* translators: 1: "header and footer" (or one of them), 2: where they were saved. */
		$message = isset( $switched ) ? $switched : sprintf( __( 'Made the site %1$s %2$s.', 'nori-for-elementor' ), implode( ' ' . __( 'and', 'nori-for-elementor' ) . ' ', $made ), $where );
		if ( $r['notes'] ) {
			$message .= ' ' . implode( ' ', $r['notes'] );
		}
		$links = array();
		foreach ( $this->plugin->site_parts->status()['parts'] as $type => $part ) {
			/* translators: %s: "header" or "footer". */
			$links[ sprintf( __( 'Edit the %s', 'nori-for-elementor' ), $names[ $type ] ) ] = $part['edit_url'];
		}
		return array(
			'message' => $message,
			'type'    => 'success',
			'links'   => $links,
		);
	}
}
