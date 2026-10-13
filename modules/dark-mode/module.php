<?php
/**
 * Dark Mode: switches Nori's color variables to their dark values.
 *
 * Elementor prints each global variable as a CSS custom property on :root (both --<id> and
 * --<label>). Dark mode redefines those same properties under html[data-nori-theme="dark"], which
 * outranks :root, so every class and element that uses a Nori color follows. Nothing else is
 * touched: no Elementor styles are rewritten, no markup is changed, and the Elementor editor
 * always shows the light version, so what you edit is what you designed. The user's own Elementor
 * colors can get a dark partner too (see Dark_Colors); until then they stay the same.
 *
 * @package Nori_For_Elementor
 */

namespace Nori\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** True in Elementor's editor preview, where dark mode stays off. */
function nori_fe_dark_is_editor() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only check of Elementor's own preview flag.
	if ( isset( $_GET['elementor-preview'] ) ) {
		return true;
	}
	return class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->preview ) && \Elementor\Plugin::$instance->preview->is_preview_mode();
}

/**
 * The CSS that switches the variables to their other values.
 *
 * On a light site (the usual) the other values are dark and apply under data-nori-theme="dark". On a
 * site whose own design is dark (Colors & dark mode > Dark site with a light switch) the Elementor
 * variables already hold the dark design, the settings hold its light version, and those apply under
 * data-nori-theme="light". The attribute always names what the visitor sees, so hide-in-dark-np and
 * hide-in-light-np keep meaning what they say.
 */
function nori_fe_dark_css( array $s ) {
	$alt  = 'dark' === Design::base() ? 'light' : 'dark';
	$own  = 'dark' === $alt ? 'light' : 'dark';
	$decl = 'color-scheme:' . $alt . ';';
	foreach ( Design::DARK_SETTINGS as $key => $setting ) {
		$value = Design::hex( $s[ $setting ] ?? '' );
		if ( ! $value ) {
			continue;
		}
		foreach ( Variables_Sync::css_names( $key ) as $name ) {
			$decl .= $name . ':' . $value . ';';
		}
	}
	// The user's own Elementor colors that were given a second version (Colors & dark mode, "Your colors").
	$decl .= Dark_Colors::css();
	$css   = 'html[data-nori-theme="' . $alt . '"]{' . $decl . '}html[data-nori-theme="' . $own . '"]{color-scheme:' . $own . ';}';
	if ( ! empty( $s['pageBackground'] ) ) {
		$bg   = Design::hex( $s['darkBackground'] ?? '' );
		$text = Design::hex( $s['darkText'] ?? '' );
		if ( $bg && $text ) {
			$css .= 'html[data-nori-theme="' . $alt . '"] body{background-color:' . $bg . ';color:' . $text . ';}';
		}
	}
	return $css;
}

add_action(
	'wp_head',
	function () {
		if ( nori_fe_dark_is_editor() ) {
			return;
		}
		$s        = Plugin::instance()->modules->settings( 'dark-mode' );
		$default  = in_array( $s['default'] ?? 'system', array( 'system', 'light', 'dark' ), true ) ? $s['default'] : 'system';
		$remember = ! empty( $s['remember'] );
		// Runs before the page paints, so a dark-mode visitor never sees a flash of the light page.
		$js = '(function(d,w){var m=' . wp_json_encode( $default ) . ';try{var s=' . ( $remember ? 'w.localStorage.getItem("nori-theme")' : 'null' ) . ';if(s==="dark"||s==="light"){m=s;}}catch(e){}'
			. 'if(m==="system"){m=w.matchMedia&&w.matchMedia("(prefers-color-scheme: dark)").matches?"dark":"light";}'
			. 'd.documentElement.setAttribute("data-nori-theme",m);})(document,window);';
		if ( function_exists( 'wp_print_inline_script_tag' ) ) {
			wp_print_inline_script_tag( $js, array( 'id' => 'nori-fe-theme' ) );
		} else {
			echo '<script id="nori-fe-theme">' . $js . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from sanitized settings above.
		}
	},
	1
);

// The dark palette, as an inline style on its own handle (printed in <head>, before the page paints).
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( nori_fe_dark_is_editor() ) {
			return;
		}
		$css = nori_fe_dark_css( Plugin::instance()->modules->settings( 'dark-mode' ) );
		if ( '' === $css ) {
			return;
		}
		wp_register_style( 'nori-fe-dark-mode', false, array(), NORI_FE_VERSION );
		wp_enqueue_style( 'nori-fe-dark-mode' );
		wp_add_inline_style( 'nori-fe-dark-mode', $css );
	},
	20
);
