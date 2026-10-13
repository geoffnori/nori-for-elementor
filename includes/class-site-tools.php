<?php
/**
 * What the site-building abilities need beyond Nori's own design system: bringing images into the
 * media library, the site's fonts (Elementor's Site Settings), and a read-only look at the stack Nori
 * runs on (Elementor, Elementor Pro, Hello Elementor and Nori itself).
 *
 * @package Nori_For_Elementor
 */

namespace Nori\Elementor;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Site_Tools {

	/** Media library meta that remembers where an image came from, so the same one isn't added twice. */
	const SOURCE_META = '_nori_fe_source';

	const MAX_IMAGE_BYTES = 10485760;

	/** The software nori/site-status reports on. */
	public static function software() {
		return array(
			'elementor'          => array( 'plugin', 'elementor/elementor.php', 'Elementor' ),
			'elementor-pro'      => array( 'plugin', 'elementor-pro/elementor-pro.php', 'Elementor Pro' ),
			'hello-elementor'    => array( 'theme', 'hello-elementor', 'Hello Elementor' ),
			'nori-for-elementor' => array( 'plugin', plugin_basename( NORI_FE_FILE ), 'Nori for Elementor' ),
		);
	}

	private static function admin_includes() {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	/* ------------------------------------------------------------------
	 * Images
	 * --------------------------------------------------------------- */

	/**
	 * Add an image to the media library from a web address or base64 data. The same source gives
	 * back the image added before. use_as: '' , 'logo' or 'site_icon'.
	 *
	 * @return array|WP_Error
	 */
	public function add_image( $url, $data, $filename, $alt, $title, $use_as ) {
		self::admin_includes();
		$url  = trim( (string) $url );
		$data = (string) $data;
		if ( ( '' === $url ) === ( '' === $data ) ) {
			return new WP_Error( 'nori_image_input', __( 'Give either url (an https address of the image) or data (the image as base64), not both.', 'nori-for-elementor' ) );
		}
		if ( '' !== $url && ( 'https' !== wp_parse_url( $url, PHP_URL_SCHEME ) || ! wp_http_validate_url( $url ) ) ) {
			return new WP_Error( 'nori_image_input', __( 'url must be a public https address.', 'nori-for-elementor' ) );
		}
		if ( '' !== $data ) {
			$data = preg_replace( '#^data:[^,]*,#', '', $data );
			$data = base64_decode( preg_replace( '/\s+/', '', $data ), true );
			if ( false === $data || '' === $data ) {
				return new WP_Error( 'nori_image_input', __( 'data isn\'t valid base64.', 'nori-for-elementor' ) );
			}
			if ( strlen( $data ) > self::MAX_IMAGE_BYTES ) {
				return new WP_Error( 'nori_image_size', __( 'The image is over 10 MB.', 'nori-for-elementor' ) );
			}
		}
		$source = '' !== $url ? 'url:' . $url : 'sha1:' . sha1( $data );

		$id     = $this->image_from_source( $source );
		$reused = (bool) $id;
		if ( ! $id ) {
			if ( '' !== $url ) {
				$tmp = download_url( $url, 30 );
				if ( is_wp_error( $tmp ) ) {
					/* translators: %s: error message. */
					return new WP_Error( 'nori_image_download', sprintf( __( 'Couldn\'t download the image: %s', 'nori-for-elementor' ), $tmp->get_error_message() ) );
				}
			} else {
				$tmp = wp_tempnam( 'nori-image' );
				file_put_contents( $tmp, $data ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			}
			if ( filesize( $tmp ) > self::MAX_IMAGE_BYTES ) {
				wp_delete_file( $tmp );
				return new WP_Error( 'nori_image_size', __( 'The image is over 10 MB.', 'nori-for-elementor' ) );
			}
			$info = @getimagesize( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$exts = array(
				'image/jpeg' => 'jpg',
				'image/png'  => 'png',
				'image/gif'  => 'gif',
				'image/webp' => 'webp',
				'image/avif' => 'avif',
			);
			if ( ! $info || ! isset( $exts[ $info['mime'] ] ) ) {
				wp_delete_file( $tmp );
				return new WP_Error( 'nori_image_type', __( 'That isn\'t a JPEG, PNG, WebP, AVIF or GIF image. (SVG isn\'t accepted: give a PNG or WebP of the logo instead.)', 'nori-for-elementor' ) );
			}
			$name = sanitize_file_name( (string) $filename );
			if ( '' === $name && '' !== $url ) {
				$name = sanitize_file_name( wp_basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ) );
			}
			$name = preg_replace( '/\.[A-Za-z0-9]+$/', '', $name );
			$name = ( '' !== $name ? $name : 'image' ) . '.' . $exts[ $info['mime'] ];

			$id = media_handle_sideload(
				array(
					'name'     => $name,
					'tmp_name' => $tmp,
				),
				0,
				'' !== trim( (string) $title ) ? sanitize_text_field( (string) $title ) : null
			);
			if ( is_wp_error( $id ) ) {
				wp_delete_file( $tmp );
				return $id;
			}
			update_post_meta( $id, self::SOURCE_META, $source );
		}

		if ( null !== $alt ) {
			update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( (string) $alt ) );
		}
		if ( $reused && '' !== trim( (string) $title ) ) {
			wp_update_post(
				array(
					'ID'         => $id,
					'post_title' => sanitize_text_field( (string) $title ),
				)
			);
		}

		$meta  = wp_get_attachment_metadata( $id );
		$w     = (int) ( $meta['width'] ?? 0 );
		$h     = (int) ( $meta['height'] ?? 0 );
		$notes = array();
		if ( 'logo' === $use_as ) {
			set_theme_mod( 'custom_logo', $id );
			if ( Plugin::instance()->site_parts->status()['parts'] ) {
				$notes[] = __( 'The site logo is set. A Nori header made before keeps the logo it was made with: make it again with nori-site-setup (header_footer with replace ["header"]) only if the site owner agrees.', 'nori-for-elementor' );
			}
		} elseif ( 'site_icon' === $use_as ) {
			update_option( 'site_icon', $id );
			if ( $w !== $h || $w < 512 ) {
				/* translators: 1: width, 2: height. */
				$notes[] = sprintf( __( 'The site icon is set, but it\'s %1$d×%2$d. Browsers want a square of at least 512×512.', 'nori-for-elementor' ), $w, $h );
			}
		}

		return array(
			'id'      => (int) $id,
			'url'     => (string) wp_get_attachment_url( $id ),
			'width'   => $w,
			'height'  => $h,
			'alt'     => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
			'reused'  => $reused,
			'used_as' => (string) $use_as,
			'notes'   => $notes,
		);
	}

	private function image_from_source( $source ) {
		$found = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => self::SOURCE_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $source, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		return $found && wp_attachment_is_image( $found[0] ) ? (int) $found[0] : 0;
	}

	/* ------------------------------------------------------------------
	 * Fonts (Elementor Site Settings > Typography)
	 * --------------------------------------------------------------- */

	const HEADINGS = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' );

	private static function kit() {
		if ( ! class_exists( '\Elementor\Plugin' ) || empty( \Elementor\Plugin::$instance->kits_manager ) ) {
			return null;
		}
		$id = (int) \Elementor\Plugin::$instance->kits_manager->get_active_id();
		// Uncached, so settings saved earlier in this request are seen.
		return $id ? \Elementor\Plugin::$instance->documents->get( $id, false ) : null;
	}

	/** A font family as Elementor lists it ('inter' => 'Inter'), or '' when it isn't one. */
	public static function font_name( $name ) {
		$name = trim( (string) $name );
		if ( '' === $name || ! class_exists( '\Elementor\Fonts' ) ) {
			return '';
		}
		foreach ( array_keys( \Elementor\Fonts::get_fonts() ) as $font ) {
			if ( 0 === strcasecmp( $font, $name ) ) {
				return $font;
			}
		}
		return '';
	}

	/** The heading and body fonts Elementor's Site Settings have now ('' = the theme's). */
	public function fonts() {
		$kit = self::kit();
		$s   = $kit ? (array) $kit->get_settings() : array();
		return array(
			'heading_font' => (string) ( $s['h1_typography_font_family'] ?? '' ),
			'body_font'    => (string) ( $s['body_typography_font_family'] ?? '' ),
		);
	}

	/**
	 * Set the heading and body fonts in Elementor's Site Settings: body text, H1–H6, and Elementor's
	 * global fonts (Primary and Secondary follow the headings, Text and Accent the body).
	 *
	 * @return array|WP_Error What changed.
	 */
	public function set_fonts( $heading, $body ) {
		$kit = self::kit();
		if ( ! $kit ) {
			return new WP_Error( 'nori_fonts_kit', __( 'Elementor\'s Site Settings aren\'t available, so the fonts can\'t be set.', 'nori-for-elementor' ) );
		}
		$s       = (array) $kit->get_settings();
		$changed = array();
		if ( '' !== $body ) {
			$s['body_typography_typography']  = 'custom';
			$s['body_typography_font_family'] = $body;
			$changed[]                        = 'body';
		}
		if ( '' !== $heading ) {
			foreach ( self::HEADINGS as $h ) {
				$s[ $h . '_typography_typography' ]  = 'custom';
				$s[ $h . '_typography_font_family' ] = $heading;
			}
			$changed[] = 'headings';
		}
		$follow = array(
			'primary'   => $heading,
			'secondary' => $heading,
			'text'      => $body,
			'accent'    => $body,
		);
		foreach ( (array) ( $s['system_typography'] ?? array() ) as $i => $t ) {
			$font = $follow[ $t['_id'] ?? '' ] ?? '';
			if ( '' !== $font ) {
				$s['system_typography'][ $i ]['typography_typography']  = 'custom';
				$s['system_typography'][ $i ]['typography_font_family'] = $font;
			}
		}
		$kit->update_settings( $s );
		\Elementor\Plugin::$instance->files_manager->clear_cache();
		return $changed;
	}

	/* ------------------------------------------------------------------
	 * SEO (Yoast SEO's own fields)
	 * --------------------------------------------------------------- */

	public static function yoast_active() {
		return class_exists( '\WPSEO_Meta' ) && class_exists( '\WPSEO_Options' );
	}

	/**
	 * Set search and sharing details through Yoast SEO: per page the SEO title, meta description,
	 * social image and focus keyphrase; for the site the organization, its logo and the default
	 * social image. Nothing is printed by Nori: Yoast outputs the tags.
	 *
	 * @param array $site  { organization?, logo?, default_image? }
	 * @param array $pages [ { id, title?, description?, image?, focus_keyphrase? } ]
	 * @return array|WP_Error
	 */
	public function set_seo( array $site, array $pages ) {
		if ( ! self::yoast_active() ) {
			return new WP_Error( 'nori_seo_yoast', __( 'Yoast SEO isn\'t active. Install and activate Yoast SEO (free) first: Nori writes the titles, descriptions and images into it.', 'nori-for-elementor' ) );
		}
		// Check everything first, so a mistake changes nothing.
		foreach ( array( 'logo', 'default_image' ) as $k ) {
			if ( isset( $site[ $k ] ) && ! wp_attachment_is_image( (int) $site[ $k ] ) ) {
				/* translators: %s: field name. */
				return new WP_Error( 'nori_seo_input', sprintf( __( 'site.%s must be the id of an image in the media library.', 'nori-for-elementor' ), $k ) );
			}
		}
		foreach ( $pages as $i => $p ) {
			$post = get_post( (int) ( $p['id'] ?? 0 ) );
			if ( ! $post || ! in_array( $post->post_type, array( 'page', 'post' ), true ) || 'trash' === $post->post_status ) {
				/* translators: %d: position in the list. */
				return new WP_Error( 'nori_seo_input', sprintf( __( 'pages[%d].id must be the id of a page or post.', 'nori-for-elementor' ), $i ) );
			}
			if ( isset( $p['image'] ) && ! wp_attachment_is_image( (int) $p['image'] ) ) {
				/* translators: %d: position in the list. */
				return new WP_Error( 'nori_seo_input', sprintf( __( 'pages[%d].image must be the id of an image in the media library.', 'nori-for-elementor' ), $i ) );
			}
		}

		$out_site = array();
		if ( isset( $site['organization'] ) && '' !== trim( (string) $site['organization'] ) ) {
			\WPSEO_Options::set( 'company_or_person', 'company' );
			\WPSEO_Options::set( 'company_name', sanitize_text_field( (string) $site['organization'] ) );
			$out_site['organization'] = sanitize_text_field( (string) $site['organization'] );
		}
		if ( isset( $site['logo'] ) ) {
			\WPSEO_Options::set( 'company_logo', (string) wp_get_attachment_url( (int) $site['logo'] ) );
			\WPSEO_Options::set( 'company_logo_id', (int) $site['logo'] );
			$out_site['logo'] = (string) wp_get_attachment_url( (int) $site['logo'] );
		}
		if ( isset( $site['default_image'] ) ) {
			\WPSEO_Options::set( 'og_default_image', (string) wp_get_attachment_url( (int) $site['default_image'] ) );
			\WPSEO_Options::set( 'og_default_image_id', (int) $site['default_image'] );
			\WPSEO_Options::set( 'opengraph', true );
			$out_site['default_image'] = (string) wp_get_attachment_url( (int) $site['default_image'] );
		}

		$out_pages = array();
		foreach ( $pages as $p ) {
			$id   = (int) $p['id'];
			$warn = array();
			if ( isset( $p['title'] ) ) {
				$title = sanitize_text_field( (string) $p['title'] );
				\WPSEO_Meta::set_value( 'title', $title, $id );
				\WPSEO_Meta::set_value( 'opengraph-title', $title, $id );
				if ( mb_strlen( $title ) > 60 ) {
					/* translators: %d: length. */
					$warn[] = sprintf( __( 'The title is %d characters; search results cut it off after about 60.', 'nori-for-elementor' ), mb_strlen( $title ) );
				}
			}
			if ( isset( $p['description'] ) ) {
				$desc = sanitize_text_field( (string) $p['description'] );
				\WPSEO_Meta::set_value( 'metadesc', $desc, $id );
				\WPSEO_Meta::set_value( 'opengraph-description', $desc, $id );
				$len = mb_strlen( $desc );
				if ( $len > 160 || $len < 70 ) {
					/* translators: %d: length. */
					$warn[] = sprintf( __( 'The description is %d characters; 70 to 160 shows best in search results.', 'nori-for-elementor' ), $len );
				}
			}
			if ( isset( $p['image'] ) ) {
				$img = (int) $p['image'];
				\WPSEO_Meta::set_value( 'opengraph-image', (string) wp_get_attachment_url( $img ), $id );
				\WPSEO_Meta::set_value( 'opengraph-image-id', (string) $img, $id );
				$meta = wp_get_attachment_metadata( $img );
				if ( (int) ( $meta['width'] ?? 0 ) < 1200 || (int) ( $meta['height'] ?? 0 ) < 630 ) {
					$warn[] = __( 'The social image is smaller than 1200×630, so it may look blurry when shared.', 'nori-for-elementor' );
				}
			}
			if ( isset( $p['focus_keyphrase'] ) ) {
				\WPSEO_Meta::set_value( 'focuskw', sanitize_text_field( (string) $p['focus_keyphrase'] ), $id );
			}
			$out_pages[] = array(
				'id'          => $id,
				'title'       => (string) \WPSEO_Meta::get_value( 'title', $id ),
				'description' => (string) \WPSEO_Meta::get_value( 'metadesc', $id ),
				'image'       => (string) \WPSEO_Meta::get_value( 'opengraph-image', $id ),
				'warnings'    => $warn,
			);
		}

		$notes = array();
		if ( '0' === (string) get_option( 'blog_public' ) ) {
			$notes[] = __( 'Search engines are asked not to index this site (Settings > Reading). That\'s usual on a staging site; switch it off when the site goes live.', 'nori-for-elementor' );
		}
		if ( ! get_option( 'site_icon' ) ) {
			$notes[] = __( 'The site has no icon (favicon): add one with nori/add-image and use_as site_icon.', 'nori-for-elementor' );
		}
		return array(
			'site'  => (object) $out_site,
			'pages' => $out_pages,
			'notes' => $notes,
		);
	}

	/* ------------------------------------------------------------------
	 * Software: what's installed (read only)
	 * --------------------------------------------------------------- */

	/**
	 * The stack: WordPress, PHP, and for each piece of software whether it's installed and active,
	 * and its version. Read only, from this site: nothing is fetched, installed or updated.
	 */
	public function status() {
		self::admin_includes();
		$plugins = get_plugins();
		$out     = array();
		foreach ( self::software() as $key => list( $kind, $id, $label ) ) {
			if ( 'theme' === $kind ) {
				$theme  = wp_get_theme( $id );
				$exists = $theme->exists();
				$row    = array(
					'name'      => $label,
					'kind'      => 'theme',
					'installed' => $exists,
					'active'    => $exists && get_stylesheet() === $id,
					'version'   => $exists ? (string) $theme->get( 'Version' ) : '',
				);
			} else {
				$exists = isset( $plugins[ $id ] );
				$row    = array(
					'name'      => $label,
					'kind'      => 'plugin',
					'installed' => $exists,
					'active'    => $exists && is_plugin_active( $id ),
					'version'   => 'nori-for-elementor' === $key ? NORI_FE_VERSION : ( $exists ? (string) $plugins[ $id ]['Version'] : '' ),
				);
			}
			$out[ $key ] = $row;
		}
		if ( $out['elementor-pro']['active'] && class_exists( '\ElementorPro\License\API' ) && method_exists( '\ElementorPro\License\API', 'is_license_active' ) ) {
			$out['elementor-pro']['license_active'] = (bool) \ElementorPro\License\API::is_license_active();
		}
		return array(
			'wordpress' => get_bloginfo( 'version' ),
			'php'       => PHP_VERSION,
			'atomic'    => Plugin::atomic_available(),
			'software'  => $out,
		);
	}
}
