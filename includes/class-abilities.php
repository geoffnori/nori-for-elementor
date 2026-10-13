<?php
/**
 * Nori's abilities: actions an AI agent (or any client of WordPress's Abilities API) can run on this
 * site, like listing the page templates, making a draft page from them, or setting the brand colors.
 *
 * Nothing here talks to an AI service and there's no AI screen. The abilities only register when
 * the Abilities API exists (WordPress 6.9+), every one needs an administrator, and they're reached
 * through the REST API or, with the MCP Adapter plugin installed, through Nori's own MCP server at
 * /wp-json/nori/mcp (or the adapter's default server).
 *
 * @package Nori_For_Elementor
 */

namespace Nori\Elementor;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Abilities {

	const CATEGORY = 'nori';

	/** Nori's MCP server: /wp-json/{namespace}/{route}. */
	const MCP_NAMESPACE = 'nori';
	const MCP_ROUTE     = 'mcp';

	/** Site types, as the Colors & dark mode screen names them: [ own design, Dark Mode switch on ]. */
	const SITE_TYPES = array(
		'light'                  => array( 'light', false ),
		'light-with-dark-switch' => array( 'light', true ),
		'dark'                   => array( 'dark', false ),
		'dark-with-light-switch' => array( 'dark', true ),
	);

	/** @var Plugin */
	private $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/** The abilities exist only where WordPress has the Abilities API. */
	public static function available() {
		return function_exists( 'wp_register_ability' ) && function_exists( 'wp_register_ability_category' );
	}

	public function register_hooks() {
		if ( ! self::available() ) {
			return;
		}
		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
		add_action( 'mcp_adapter_init', array( $this, 'register_mcp_server' ) );
	}

	/** Ability names, in the order an agent usually needs them. */
	public static function names() {
		return array( 'nori/site-status', 'nori/list-templates', 'nori/list-modules', 'nori/add-image', 'nori/site-setup', 'nori/apply-brand', 'nori/set-typography', 'nori/create-page', 'nori/get-pages', 'nori/update-page', 'nori/set-seo', 'nori/set-modules' );
	}

	public function register_category() {
		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'Nori for Elementor', 'nori-for-elementor' ),
				'description' => __( 'Build Elementor pages from Nori\'s templates and set up the design system: brand colors, fonts, images, dark mode and the site type.', 'nori-for-elementor' ),
			)
		);
	}

	/** Who may run them: the same people who can open Nori in WP Admin. */
	public function can() {
		return current_user_can( Admin::CAP );
	}

	/** Shared meta: public, so the REST API and MCP can reach them (each still needs an administrator). */
	private static function meta( array $annotations ) {
		return array(
			'annotations'  => $annotations,
			'public'       => true,
			'show_in_rest' => true,
			'mcp'          => array( 'public' => true ),
		);
	}

	public function register_abilities() {
		wp_register_ability(
			'nori/list-templates',
			array(
				'label'               => __( 'List Nori templates', 'nori-for-elementor' ),
				'description'         => __( 'Lists Nori\'s styles (Studio, Bento, Billboard and Lens), the page templates in each (whole pages) and the section templates (hero, services, FAQ and more), with every section\'s variants and the content slots it takes: type, whether it\'s required, limits and an example value. Also lists the brand presets, the site types, the icon names and what the site already knows about itself. Read this before creating pages.', 'nori-for-elementor' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'examples' => array(
							'type'        => 'boolean',
							'default'     => true,
							'description' => __( 'Include an example value for each slot (the template\'s sample text).', 'nori-for-elementor' ),
						),
					),
					'additionalProperties' => false,
					'default'              => array(),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'styles'     => array( 'type' => 'object' ),
						'pages'      => array( 'type' => 'array' ),
						'sections'   => array( 'type' => 'object' ),
						'slot_types' => array( 'type' => 'object' ),
						'icons'      => array( 'type' => 'array' ),
						'presets'    => array( 'type' => 'object' ),
						'site_types' => array( 'type' => 'object' ),
						'site'       => array( 'type' => 'object' ),
					),
				),
				'execute_callback'    => array( $this, 'list_templates' ),
				'permission_callback' => array( $this, 'can' ),
				'meta'                => self::meta(
					array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					)
				),
			)
		);

		wp_register_ability(
			'nori/create-page',
			array(
				'label'               => __( 'Create a page from Nori templates', 'nori-for-elementor' ),
				'description'         => __( 'Creates an Elementor page (a draft unless you say otherwise) from a Nori page template, or from a list of section templates in the order you give. Fill each section\'s slots with content for the business; anything left out keeps the template\'s sample text (except reviews: a testimonials section without real items is left out), and the business name and email come from the site. Every element is a normal Elementor Atomic element styled with Nori classes and variables, so it stays easy to edit. Returns the page id, its address on the site (for menus and buttons) and its edit, preview and view links.', 'nori-for-elementor' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'template' => array(
							'type'        => 'string',
							'description' => __( 'A page template key from nori/list-templates (for example studio-home or bento-home). Use this or sections, not both.', 'nori-for-elementor' ),
						),
						'sections' => array(
							'type'        => 'array',
							'description' => __( 'Section templates in page order, to build a page of your own. Use this or template, not both.', 'nori-for-elementor' ),
							'minItems'    => 1,
							'maxItems'    => 20,
							'items'       => array(
								'type'                 => 'object',
								'properties'           => array(
									'key'     => array( 'type' => 'string', 'description' => __( 'Section template key, like hero or faq.', 'nori-for-elementor' ) ),
									'variant' => array( 'type' => 'string', 'description' => __( 'One of the section\'s variants, named after the styles (studio, bento, billboard, lens; heroes also have studio-page, bento-page, billboard-page and lens-page for inner pages). Leave out to use the site\'s style.', 'nori-for-elementor' ) ),
									'content' => array( 'type' => 'object', 'description' => __( 'Slot values for this section, by slot name.', 'nori-for-elementor' ) ),
								),
								'required'             => array( 'key' ),
								'additionalProperties' => false,
							),
						),
						'content'  => array(
							'type'        => 'array',
							'description' => __( 'With template: slot values for each of its sections, in the template\'s section order (use {} for a section you leave as it is).', 'nori-for-elementor' ),
							'items'       => array( 'type' => 'object' ),
						),
						'title'    => array(
							'type'        => 'string',
							'description' => __( 'The page title. Required with sections; with template it defaults to the template\'s title.', 'nori-for-elementor' ),
						),
						'status'   => array(
							'type'        => 'string',
							'enum'        => array( 'draft', 'private', 'publish' ),
							'default'     => 'draft',
							'description' => __( 'Leave as draft unless the site owner asked to publish.', 'nori-for-elementor' ),
						),
						'samples'  => array(
							'type'        => 'boolean',
							'default'     => true,
							'description' => __( 'Fill slots you leave out with the template\'s sample text. With false, optional slots you leave out are left empty.', 'nori-for-elementor' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'          => array( 'type' => 'integer' ),
						'title'       => array( 'type' => 'string' ),
						'status'      => array( 'type' => 'string' ),
						'edit_url'    => array( 'type' => 'string' ),
						'preview_url' => array( 'type' => 'string' ),
						'view_url'    => array( 'type' => 'string' ),
						'path'        => array(
							'type'        => 'string',
							'description' => __( 'The page\'s address on the site once it\'s published, like /contact/, for menus and buttons.', 'nori-for-elementor' ),
						),
						'sections'    => array( 'type' => 'array' ),
						'warnings'    => array( 'type' => 'array' ),
						'notes'       => array( 'type' => 'array' ),
					),
				),
				'execute_callback'    => array( $this, 'create_page' ),
				'permission_callback' => array( $this, 'can' ),
				'meta'                => self::meta(
					array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					)
				),
			)
		);

		wp_register_ability(
			'nori/apply-brand',
			array(
				'label'               => __( 'Set the brand colors and site type', 'nori-for-elementor' ),
				'description'         => __( 'Sets the eleven Nori color variables in Elementor from a brand preset, or from the business\'s own brand color (and optionally a dark ink color), with any exact colors from a style guide in colors, and sets what kind of site it is: light, light with a dark switch, dark, or dark with a light switch. The second version of each color (dark, or light on a dark site) is set too, and Dark Mode is switched on or off to match. Pages update right away, since every Nori class uses these variables.', 'nori-for-elementor' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'preset'    => array(
							'type'        => 'string',
							'enum'        => array_merge( array_keys( Design::presets() ), array( 'custom' ) ),
							'description' => __( 'A brand preset from nori/list-templates, or custom to build the colors from accent (and ink).', 'nori-for-elementor' ),
						),
						'accent'    => array(
							'type'        => 'string',
							'pattern'     => '^#[0-9A-Fa-f]{6}$',
							'description' => __( 'The brand color, as #RRGGBB. Giving it without a preset means custom.', 'nori-for-elementor' ),
						),
						'ink'       => array(
							'type'        => 'string',
							'pattern'     => '^#[0-9A-Fa-f]{6}$',
							'description' => __( 'Optional dark color for headings, text and the dark button, as #RRGGBB. Nori works one out from the accent if you leave it out.', 'nori-for-elementor' ),
						),
						'site_type' => array(
							'type'        => 'string',
							'enum'        => array_keys( self::SITE_TYPES ),
							'description' => __( 'What kind of site this is. Leave out to keep the current one.', 'nori-for-elementor' ),
						),
						'colors'    => array(
							'type'                 => 'object',
							'description'          => __( 'Exact colors from the owner\'s style guide, as #RRGGBB, by Nori color: primary (headings), secondary, text, accent, on-accent (text on the accent), background (the page), surface (cards and tiles), line (borders), muted (quieter text), band (dark strips like the footer) and on-band. They replace the worked-out values for the site\'s own colors (the second set for a switch is still worked out). Give only the ones the style guide names.', 'nori-for-elementor' ),
							'properties'           => array_fill_keys( Design::COLOR_KEYS, array( 'type' => 'string', 'pattern' => '^#[0-9A-Fa-f]{6}$' ) ),
							'additionalProperties' => false,
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'preset'        => array( 'type' => 'string' ),
						'site_type'     => array( 'type' => 'string' ),
						'dark_mode'     => array( 'type' => 'boolean' ),
						'colors'        => array( 'type' => 'object' ),
						'second_colors' => array( 'type' => 'object' ),
						'messages'      => array( 'type' => 'array' ),
					),
				),
				'execute_callback'    => array( $this, 'apply_brand' ),
				'permission_callback' => array( $this, 'can' ),
				'meta'                => self::meta(
					array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					)
				),
			)
		);

		wp_register_ability(
			'nori/list-modules',
			array(
				'label'               => __( 'List Nori modules', 'nori-for-elementor' ),
				'description'         => __( 'Lists Nori\'s modules (smooth scroll, scroll animations, button effects, marquee, carousels, lightbox, dark mode, form spam protection and more): what each does, whether it\'s on, which modules exclude each other, how it behaves next to other modules (works_with: pick-one, takes-turns, steps-aside, teams-up or fine, explained in pair_kinds), the classes it adds to Elementor with where each goes, and its settings with their type, range, options, default and current value.', 'nori-for-elementor' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(),
					'additionalProperties' => false,
					'default'              => array(),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'modules'     => array( 'type' => 'array' ),
						'pair_kinds'  => array( 'type' => 'object' ),
						'value_rules' => array( 'type' => 'object' ),
					),
				),
				'execute_callback'    => array( $this, 'list_modules' ),
				'permission_callback' => array( $this, 'can' ),
				'meta'                => self::meta(
					array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					)
				),
			)
		);

		wp_register_ability(
			'nori/set-modules',
			array(
				'label'               => __( 'Switch Nori modules on or off and change their settings', 'nori-for-elementor' ),
				'description'         => __( 'Switches modules on or off and changes their settings, by module key from nori/list-modules. Switching a module on adds its classes to the Elementor class library (it doesn\'t put them on any element). Switching one on that excludes another (like the two scroll animations) switches the other off. Switching off keeps the classes in Elementor. Settings you leave out keep their current value.', 'nori-for-elementor' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'modules'  => array(
							'type'                 => 'object',
							'description'          => __( 'Module key => true to switch it on, false to switch it off.', 'nori-for-elementor' ),
							'additionalProperties' => array( 'type' => 'boolean' ),
						),
						'settings' => array(
							'type'                 => 'object',
							'description'          => __( 'Module key => { setting key => value }.', 'nori-for-elementor' ),
							'additionalProperties' => array( 'type' => 'object' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'on'       => array( 'type' => 'array' ),
						'changed'  => array( 'type' => 'object' ),
						'messages' => array( 'type' => 'array' ),
					),
				),
				'execute_callback'    => array( $this, 'set_modules' ),
				'permission_callback' => array( $this, 'can' ),
				'meta'                => self::meta(
					array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					)
				),
			)
		);

		wp_register_ability(
			'nori/site-setup',
			array(
				'label'               => __( 'Set up the site', 'nori-for-elementor' ),
				'description'         => __( 'Sets up what every page shares. Each part is optional: starter adds Nori\'s starter design system to Elementor (the starter color, radius and spacing variables and the layout, spacing, button and type classes); header_footer makes the site header and footer (one for the whole site) with the menu, phone, button and footer details you give; site_title, tagline and logo set WordPress\'s own; front_page makes a page the home page. An existing header or footer is kept unless you name it in replace (the old one goes to the trash).', 'nori-for-elementor' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'starter'       => array(
							'type'        => 'boolean',
							'description' => __( 'Add the starter variables and classes that aren\'t in Elementor yet.', 'nori-for-elementor' ),
						),
						'header_footer' => array(
							'type'                 => 'object',
							'description'          => __( 'Make the site header and footer. Slot values for each, as in nori/list-templates (sections site-header and site-footer); the business name and email come from the site. Give the business\'s real phone, address and hours: left out, they stay empty. Use {} for both to keep the sample menu and button.', 'nori-for-elementor' ),
							'properties'           => array(
								'header'  => array( 'type' => 'object' ),
								'footer'  => array( 'type' => 'object' ),
								'style'   => array(
									'type'        => 'string',
									'enum'        => array_keys( Templates::styles() ),
									'description' => __( 'The look to make them in: the same style as the page templates you use. Sections made later without a variant follow it.', 'nori-for-elementor' ),
								),
								'replace' => array(
									'type'        => 'array',
									'items'       => array(
										'type' => 'string',
										'enum' => array( 'header', 'footer' ),
									),
									'description' => __( 'Make these again even if they exist. Only when the site owner asked: edits made to the old one are lost (it goes to the trash).', 'nori-for-elementor' ),
								),
							),
							'additionalProperties' => false,
						),
						'site_title'    => array(
							'type'      => 'string',
							'maxLength' => 200,
						),
						'tagline'       => array(
							'type'      => 'string',
							'maxLength' => 300,
						),
						'logo'          => array(
							'type'        => 'integer',
							'description' => __( 'A media library image id to use as the site logo (also in the header).', 'nori-for-elementor' ),
						),
						'front_page'    => array(
							'type'        => 'integer',
							'description' => __( 'The id of a page (for example from nori/create-page) to show as the home page.', 'nori-for-elementor' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'done'          => array( 'type' => 'array' ),
						'header_footer' => array( 'type' => array( 'object', 'null' ) ),
						'notes'         => array( 'type' => 'array' ),
					),
				),
				'execute_callback'    => array( $this, 'site_setup' ),
				'permission_callback' => array( $this, 'can' ),
				'meta'                => self::meta(
					array(
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => false,
					)
				),
			)
		);

		wp_register_ability(
			'nori/site-status',
			array(
				'label'               => __( 'Check what the site runs on', 'nori-for-elementor' ),
				'description'         => __( 'Read only: says which versions of WordPress, PHP, Elementor, Elementor Pro, the Hello Elementor theme and Nori the site runs, whether each is installed and active, and whether Elementor\'s Atomic editor is on. Run it first. If something is missing, tell the site owner.', 'nori-for-elementor' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(),
					'additionalProperties' => false,
					'default'              => array(),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'wordpress' => array( 'type' => 'string' ),
						'php'       => array( 'type' => 'string' ),
						'atomic'    => array( 'type' => 'boolean' ),
						'software'  => array( 'type' => 'object' ),
						'notes'     => array( 'type' => 'array' ),
					),
				),
				'execute_callback'    => array( $this, 'site_status' ),
				'permission_callback' => array( $this, 'can' ),
				'meta'                => self::meta(
					array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					)
				),
			)
		);

		wp_register_ability(
			'nori/add-image',
			array(
				'label'               => __( 'Add an image to the media library', 'nori-for-elementor' ),
				'description'         => __( 'Adds an image (JPEG, PNG, WebP, AVIF or GIF, up to 10 MB) to the media library from an https address or base64 data, with alt text, and returns its id for image slots in nori/create-page and for the logo in nori/site-setup. use_as logo makes it the site logo; site_icon makes it the browser tab icon (a square of at least 512×512). The same address or data gives back the image added before.', 'nori-for-elementor' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'url'      => array(
							'type'        => 'string',
							'description' => __( 'The image\'s public https address. Use this or data.', 'nori-for-elementor' ),
						),
						'data'     => array(
							'type'        => 'string',
							'description' => __( 'The image file as base64 (a data: URL works too), for an image the owner gave you. Use this or url.', 'nori-for-elementor' ),
						),
						'filename' => array(
							'type'        => 'string',
							'maxLength'   => 120,
							'description' => __( 'A file name for the media library, like juniper-bakery-logo. The right extension is added.', 'nori-for-elementor' ),
						),
						'alt'      => array(
							'type'        => 'string',
							'maxLength'   => 300,
							'description' => __( 'What the image shows, for people who can\'t see it. For a logo: the business name.', 'nori-for-elementor' ),
						),
						'title'    => array(
							'type'      => 'string',
							'maxLength' => 200,
						),
						'use_as'   => array(
							'type' => 'string',
							'enum' => array( 'logo', 'site_icon' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'      => array( 'type' => 'integer' ),
						'url'     => array( 'type' => 'string' ),
						'width'   => array( 'type' => 'integer' ),
						'height'  => array( 'type' => 'integer' ),
						'alt'     => array( 'type' => 'string' ),
						'reused'  => array( 'type' => 'boolean' ),
						'used_as' => array( 'type' => 'string' ),
						'notes'   => array( 'type' => 'array' ),
					),
				),
				'execute_callback'    => array( $this, 'add_image' ),
				'permission_callback' => array( $this, 'can' ),
				'meta'                => self::meta(
					array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					)
				),
			)
		);

		wp_register_ability(
			'nori/set-typography',
			array(
				'label'               => __( 'Set the fonts and type scale', 'nori-for-elementor' ),
				'description'         => __( 'Sets the site\'s heading and body fonts (in Elementor\'s Site Settings, so every page and Elementor\'s global fonts follow) and Nori\'s type scale: the body text size, how much bigger each heading step is, and the heading weight. Fonts are Google Fonts or system font family names as Elementor lists them, like Inter, DM Sans or Playfair Display. Each part is optional.', 'nori-for-elementor' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'heading_font'   => array(
							'type'        => 'string',
							'description' => __( 'Font family for headings.', 'nori-for-elementor' ),
						),
						'body_font'      => array(
							'type'        => 'string',
							'description' => __( 'Font family for body text, buttons and everything else.', 'nori-for-elementor' ),
						),
						'base'           => array(
							'type'        => 'integer',
							'minimum'     => 12,
							'maximum'     => 24,
							'description' => __( 'Body text size in px (16 is usual; 17 or 18 for a roomy, editorial feel).', 'nori-for-elementor' ),
						),
						'ratio'          => array(
							'type'        => 'string',
							'enum'        => array_map( 'strval', array_keys( Design::RATIOS ) ),
							'description' => __( 'How much bigger each step is: 1.125 calm, 1.2 balanced, 1.25 clear (the default), 1.333 bold headings.', 'nori-for-elementor' ),
						),
						'heading_weight' => array(
							'type'        => 'string',
							'enum'        => Design::WEIGHTS,
							'description' => __( 'Heading weight. Pick one the heading font has.', 'nori-for-elementor' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'heading_font' => array( 'type' => 'string' ),
						'body_font'    => array( 'type' => 'string' ),
						'type_scale'   => array( 'type' => 'object' ),
						'messages'     => array( 'type' => 'array' ),
					),
				),
				'execute_callback'    => array( $this, 'set_typography' ),
				'permission_callback' => array( $this, 'can' ),
				'meta'                => self::meta(
					array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					)
				),
			)
		);

		wp_register_ability(
			'nori/set-seo',
			array(
				'label'               => __( 'Set search and sharing details (Yoast SEO)', 'nori-for-elementor' ),
				'description'         => __( 'Writes each page\'s SEO title, meta description, social sharing image and focus keyphrase into Yoast SEO, and the site\'s organization name, logo and default sharing image. Needs Yoast SEO (free) active. Titles of 60 characters or less and descriptions of 70 to 160 show best; sharing images should be 1200×630 (add them with nori/add-image).', 'nori-for-elementor' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'site'  => array(
							'type'                 => 'object',
							'properties'           => array(
								'organization'  => array(
									'type'        => 'string',
									'maxLength'   => 200,
									'description' => __( 'The business name, for Google\'s knowledge panel.', 'nori-for-elementor' ),
								),
								'logo'          => array(
									'type'        => 'integer',
									'description' => __( 'Media library id of the business logo (at least 112×112).', 'nori-for-elementor' ),
								),
								'default_image' => array(
									'type'        => 'integer',
									'description' => __( 'Media library id of the sharing image for pages without their own (1200×630).', 'nori-for-elementor' ),
								),
							),
							'additionalProperties' => false,
						),
						'pages' => array(
							'type'  => 'array',
							'items' => array(
								'type'                 => 'object',
								'properties'           => array(
									'id'              => array( 'type' => 'integer' ),
									'title'           => array(
										'type'        => 'string',
										'maxLength'   => 120,
										'description' => __( 'The title in search results and browser tabs, like "Handmade Dice for D&D | Dice Fiend". Plain text.', 'nori-for-elementor' ),
									),
									'description'     => array(
										'type'        => 'string',
										'maxLength'   => 320,
										'description' => __( 'One or two sentences about the page for search results, with what people search for.', 'nori-for-elementor' ),
									),
									'image'           => array(
										'type'        => 'integer',
										'description' => __( 'Media library id of the page\'s sharing image (1200×630).', 'nori-for-elementor' ),
									),
									'focus_keyphrase' => array( 'type' => 'string', 'maxLength' => 100 ),
								),
								'required'             => array( 'id' ),
								'additionalProperties' => false,
							),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'site'  => array( 'type' => 'object' ),
						'pages' => array( 'type' => 'array' ),
						'notes' => array( 'type' => 'array' ),
					),
				),
				'execute_callback'    => array( $this, 'set_seo' ),
				'permission_callback' => array( $this, 'can' ),
				'meta'                => self::meta(
					array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					)
				),
			)
		);

		wp_register_ability(
			'nori/get-pages',
			array(
				'label'               => __( 'List pages, or show one page\'s sections', 'nori-for-elementor' ),
				'description'         => __( 'Without id: every page with its id, title, status, address, whether it\'s the home page and whether Nori made it. With id: that page\'s sections in order (position, section template, variant and the content it was built with) and its Yoast SEO title and description. Read this before changing a page with nori/update-page.', 'nori-for-elementor' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id' => array( 'type' => 'integer' ),
					),
					'additionalProperties' => false,
					'default'              => array(),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'pages'    => array( 'type' => 'array' ),
						'sections' => array( 'type' => 'array' ),
					),
				),
				'execute_callback'    => array( $this, 'get_pages' ),
				'permission_callback' => array( $this, 'can' ),
				'meta'                => self::meta(
					array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					)
				),
			)
		);

		wp_register_ability(
			'nori/update-page',
			array(
				'label'               => __( 'Change a page in place', 'nori-for-elementor' ),
				'description'         => __( 'Changes an existing page without making a new one: it keeps its id, address, status and SEO. Change some sections (set: new content or variant for the section at a position, merged over what it has; remove: positions; add: new sections after a position, 0 for the top). Sections you don\'t name keep everything, including edits made in Elementor. Or rebuild the whole page from a template or sections, like nori/create-page (needed for pages made before Nori 0.20). Also changes the title or status. Positions are from nori/get-pages.', 'nori-for-elementor' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'       => array( 'type' => 'integer' ),
						'set'      => array(
							'type'  => 'array',
							'items' => array(
								'type'                 => 'object',
								'properties'           => array(
									'position' => array( 'type' => 'integer', 'minimum' => 1 ),
									'variant'  => array( 'type' => 'string' ),
									'content'  => array(
										'type'        => 'object',
										'description' => __( 'Slot values to change; slots you leave out keep their value. A list slot is replaced as a whole.', 'nori-for-elementor' ),
									),
								),
								'required'             => array( 'position' ),
								'additionalProperties' => false,
							),
						),
						'remove'   => array(
							'type'  => 'array',
							'items' => array( 'type' => 'integer', 'minimum' => 1 ),
						),
						'add'      => array(
							'type'  => 'array',
							'items' => array(
								'type'                 => 'object',
								'properties'           => array(
									'after'   => array( 'type' => 'integer', 'minimum' => 0 ),
									'key'     => array( 'type' => 'string' ),
									'variant' => array( 'type' => 'string' ),
									'content' => array( 'type' => 'object' ),
								),
								'required'             => array( 'after', 'key' ),
								'additionalProperties' => false,
							),
						),
						'template' => array( 'type' => 'string' ),
						'content'  => array(
							'type'  => 'array',
							'items' => array( 'type' => 'object' ),
						),
						'sections' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'object' ),
						),
						'title'    => array( 'type' => 'string' ),
						'status'   => array(
							'type' => 'string',
							'enum' => array( 'draft', 'private', 'publish' ),
						),
						'samples'  => array( 'type' => 'boolean' ),
					),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'       => array( 'type' => 'integer' ),
						'path'     => array( 'type' => 'string' ),
						'sections' => array( 'type' => 'array' ),
						'warnings' => array( 'type' => 'array' ),
						'notes'    => array( 'type' => 'array' ),
					),
				),
				'execute_callback'    => array( $this, 'update_page' ),
				'permission_callback' => array( $this, 'can' ),
				'meta'                => self::meta(
					array(
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => false,
					)
				),
			)
		);
	}

	/**
	 * Nori's own MCP server, with the abilities as direct tools. Runs when an MCP adapter does: the MCP
	 * Adapter plugin, or Elementor's own MCP switched on. Administrators only (an application password works).
	 *
	 * @param object $adapter \WP\MCP\Core\McpAdapter.
	 */
	public function register_mcp_server( $adapter ) {
		$transport = '\WP\MCP\Transport\HttpTransport';
		if ( ! is_object( $adapter ) || ! method_exists( $adapter, 'create_server' ) || ! class_exists( $transport ) ) {
			return;
		}
		$adapter->create_server(
			'nori',
			self::MCP_NAMESPACE,
			self::MCP_ROUTE,
			__( 'Nori for Elementor', 'nori-for-elementor' ),
			self::instructions(),
			NORI_FE_VERSION,
			array( $transport ),
			null,
			null,
			self::names(),
			array(),
			array(),
			array( $this, 'can' )
		);
	}

	/** What an AI connecting to Nori's MCP server is told first. */
	public static function instructions() {
		return __( 'Nori builds Elementor pages from ready-made templates and sets up the site\'s design system. Work in this order. 0. nori-site-status (read only; if Elementor Pro, the Hello Elementor theme or Elementor\'s Atomic editor is missing, tell the owner). 1. nori-list-templates (the four styles, Studio, Bento, Billboard and Lens, the page templates in each, sections and their content slots, brand presets, site types, what the site knows) and, if motion or effects come up, nori-list-modules. Pick one style for the whole site: Studio is quiet and editorial, Bento is friendly and bold, Billboard is poster-sized type with full-width video (the business name fills the width with video inside the letters, so it suits short names), Lens is refined and full width with video moments. Billboard and Lens have video slots: pass the value sample (an abstract loop that ships with Nori; with samples false a video slot you leave out stays empty) unless the owner gives you the https address of their own MP4. 2. nori-add-image for the owner\'s logo with use_as logo and use_as site_icon (the favicon), and an image for every image slot: the owner\'s photos, or images you generate that show the work or the place, never people posing as the business\'s staff or customers. Never leave a placeholder image; leave out a section that needs photos you can\'t provide (like team). 3. nori-site-setup with starter true, the site title and tagline, the logo id, and header_footer with that style and the business\'s menu, phone and footer details. 4. nori-apply-brand for the colors and the kind of site (light, dark, with or without a switch); when the owner\'s style guide gives exact colors (page background, cards, borders, muted text), pass them in colors, and nori-set-typography for the style guide\'s fonts and type scale; with no fonts from the owner, use the style\'s suggested pair from nori-list-templates (styles.<style>.fonts: heading_font, body_font, heading_weight, ratio). 5. nori-create-page for each page, using that style\'s page templates (like studio-home) or sections in that style, filling the slots with the business\'s own words. 6. nori-site-setup with front_page set to the home page. 7. nori-set-seo for every page (SEO title, meta description, a 1200×630 sharing image) and the site (organization, logo, default sharing image). 8. nori-set-modules for any effects the owner wants. Pages are drafts unless the site owner asked to publish. Use only facts the owner gave you: no made-up reviews, ratings, numbers or names; leave a slot\'s sample in place rather than invent one, and tell the owner what still needs their real details. Everything is normal Elementor Atomic elements with Nori classes, so the owner can edit it all in Elementor; to change a page after it exists, read it with nori-get-pages and change it with nori-update-page (never delete and recreate a page: that loses its address and SEO); for fine visual tweaks, Elementor\'s own MCP tools if they are connected.', 'nori-for-elementor' );
	}

	/* ------------------------------------------------------------------
	 * nori/list-templates
	 * --------------------------------------------------------------- */

	public function list_templates( $input = array() ) {
		$examples  = ! is_array( $input ) || ! array_key_exists( 'examples', $input ) || ! empty( $input['examples'] );
		$templates = $this->plugin->templates;
		$sections  = array();
		foreach ( $templates->all() as $key => $t ) {
			$slots = array();
			foreach ( (array) ( $t['slots'] ?? array() ) as $skey => $s ) {
				$sample = $s['sample'] ?? null;
				unset( $s['sample'] );
				if ( $examples && null !== $sample ) {
					$s['example'] = $sample;
				}
				$slots[ $skey ] = $s;
			}
			$variants = array();
			foreach ( (array) ( $t['variants'] ?? array() ) as $vkey => $v ) {
				$variants[ $vkey ] = $v['label'] ?? $vkey;
			}
			$sections[ $key ] = array(
				'label'       => $t['label'] ?? $key,
				'group'       => $t['group'] ?? 'page',
				'description' => $t['description'] ?? '',
				'variants'    => $variants,
				'slots'       => $slots,
			);
		}
		$pages = array();
		foreach ( $templates->pages() as $key => $p ) {
			$pages[] = array(
				'key'         => $key,
				'style'       => $p['style'] ?? '',
				'label'       => $p['label'] ?? $key,
				'title'       => $p['title'] ?? '',
				'description' => $p['description'] ?? '',
				'sections'    => array_map(
					fn( $s ) => array(
						'key'     => $s['key'] ?? '',
						'variant' => $s['variant'] ?? (string) array_key_first( (array) ( $templates->get( $s['key'] ?? '' )['variants'] ?? array() ) ),
					),
					array_values( $p['sections'] )
				),
			);
		}
		$presets = array();
		foreach ( Design::presets() as $key => $p ) {
			$presets[ $key ] = array(
				'label'  => $p['label'],
				'accent' => $p['light']['accent'],
				'light'  => $p['light'],
				'dark'   => $p['dark'],
			);
		}
		$brand = (array) get_option( Design::BRAND_OPTION, array() );
		return array(
			'styles'     => array_map( fn( $st ) => array_filter( array( 'label' => $st['label'] ?? '', 'description' => $st['description'] ?? '', 'fonts' => $st['fonts'] ?? null ) ), Templates::styles() ),
			'pages'      => $pages,
			'sections'   => $sections,
			'slot_types' => array(
				'text'     => __( 'One line of plain text.', 'nori-for-elementor' ),
				'textarea' => __( 'Plain text; line breaks are kept.', 'nori-for-elementor' ),
				'url'      => __( 'A link: https://…, mailto:, tel:, #anchor or a path on this site like /contact/.', 'nori-for-elementor' ),
				'link'     => __( 'A button or link: {"label": "…", "url": "…"} (url as for url).', 'nori-for-elementor' ),
				'icon'     => __( 'An icon name from icons.', 'nori-for-elementor' ),
				'image'    => __( 'A media library attachment id (add an image with nori/add-image first). Leave out for a placeholder.', 'nori-for-elementor' ),
				'list'     => __( 'An array of objects with the slot\'s fields; min and max say how many.', 'nori-for-elementor' ),
			),
			'icons'      => Templates::icons(),
			'presets'    => $presets,
			'site_types' => array(
				'light'                  => __( 'One set of light colors. No switch.', 'nori-for-elementor' ),
				'light-with-dark-switch' => __( 'Light design; visitors can switch to dark with any element that has theme-toggle-np.', 'nori-for-elementor' ),
				'dark'                   => __( 'Dark colors, no switch.', 'nori-for-elementor' ),
				'dark-with-light-switch' => __( 'Dark design; visitors can switch to a light version.', 'nori-for-elementor' ),
			),
			'site'       => array(
				'business_name' => get_bloginfo( 'name' ),
				'email'         => (string) get_option( 'admin_email' ),
				'site_type'     => $this->site_type(),
				'preset'        => (string) ( $brand['preset'] ?? '' ),
				'header_footer' => (bool) $this->plugin->site_parts->status()['parts'],
				'style'         => Templates::site_style(),
				'atomic'        => Plugin::atomic_available(),
				'typography'    => array_merge( $this->plugin->site_tools->fonts(), array( 'type_scale' => Design::type_settings() ) ),
			),
		);
	}

	/* ------------------------------------------------------------------
	 * nori/create-page
	 * --------------------------------------------------------------- */

	/** Sections that only make sense with the owner's real content: left out when it isn't given. */
	const NEEDS_REAL = array( 'testimonials' => 'items' );

	/**
	 * The sections a create or update call asks for (template + content, or sections), with the site's
	 * details filled in and sections that need real content left out when it isn't given.
	 *
	 * @return array|WP_Error [ sections, default title, notes ]
	 */
	private function sections_from_input( array $input ) {
		$templates = $this->plugin->templates;
		$has_tpl   = isset( $input['template'] ) && '' !== $input['template'];
		$notes     = array();
		if ( $has_tpl ) {
			$key = sanitize_key( (string) $input['template'] );
			$p   = $templates->page( $key );
			if ( ! $p ) {
				/* translators: %s: list of page template keys. */
				return new WP_Error( 'nori_ability_template', sprintf( __( 'There is no page template with that key. Use one of: %s.', 'nori-for-elementor' ), implode( ', ', array_keys( $templates->pages() ) ) ) );
			}
			$sections = $templates->page_sections( $key, array_values( array_map( fn( $c ) => is_array( $c ) ? $c : array(), (array) ( $input['content'] ?? array() ) ) ) );
			$title    = (string) ( $p['title'] ?? $p['label'] ?? $key );
		} else {
			$details  = Templates::site_details();
			$sections = array();
			foreach ( array_values( (array) ( $input['sections'] ?? array() ) ) as $i => $s ) {
				$skey = sanitize_key( (string) ( $s['key'] ?? '' ) );
				$tpl  = $templates->get( $skey );
				if ( ! $tpl ) {
					/* translators: 1: position in the list, 2: list of section keys. */
					return new WP_Error( 'nori_ability_section', sprintf( __( 'Section %1$d isn\'t a Nori section template. Use one of: %2$s.', 'nori-for-elementor' ), $i + 1, implode( ', ', array_keys( $templates->all() ) ) ) );
				}
				$sections[] = array(
					'key'     => $skey,
					'variant' => sanitize_key( (string) ( $s['variant'] ?? '' ) ),
					'content' => array_merge( array_intersect_key( $details, (array) ( $tpl['slots'] ?? array() ) ), is_array( $s['content'] ?? null ) ? $s['content'] : array() ),
				);
			}
			$title = '';
		}
		return array( $this->drop_unsupplied( $sections, $notes ), $title, $notes );
	}

	/** Leave out sections that need the owner's real content (reviews) when none was given. */
	private function drop_unsupplied( array $sections, array &$notes ) {
		$out = array();
		foreach ( $sections as $s ) {
			$slot = self::NEEDS_REAL[ $s['key'] ] ?? null;
			if ( $slot && empty( $s['content'][ $slot ] ) ) {
				/* translators: %s: section name. */
				$notes[] = sprintf( __( 'Left out the %s section: no real ones were given. Add it with nori/update-page when the owner has some.', 'nori-for-elementor' ), $this->plugin->templates->get( $s['key'] )['label'] ?? $s['key'] );
				continue;
			}
			$out[] = $s;
		}
		return $out;
	}

	/** What create-page and update-page answer with. */
	private function page_answer( $id, array $warnings, array $notes ) {
		$post   = get_post( $id );
		$record = $this->plugin->templates->page_record( $id );
		if ( ! $this->plugin->site_parts->status()['parts'] ) {
			$notes[] = __( 'The site has no Nori header and footer yet: make them with nori/site-setup (header_footer).', 'nori-for-elementor' );
		}
		return array(
			'id'          => (int) $id,
			'title'       => $post ? $post->post_title : '',
			'status'      => $post ? $post->post_status : '',
			'edit_url'    => Templates::edit_url( $id ),
			'preview_url' => (string) get_preview_post_link( $id ),
			'view_url'    => (string) get_permalink( $id ),
			'path'        => '/' . trim( (string) get_page_uri( $id ), '/' ) . '/',
			'sections'    => array_map(
				fn( $i, $s ) => array(
					'position' => $i + 1,
					'key'      => $s['key'],
					'variant'  => $s['variant'],
				),
				array_keys( (array) ( $record['sections'] ?? array() ) ),
				(array) ( $record['sections'] ?? array() )
			),
			'warnings'    => array_values( $warnings ),
			'notes'       => array_values( $notes ),
		);
	}

	public function create_page( $input = array() ) {
		$input    = is_array( $input ) ? $input : array();
		$has_tpl  = isset( $input['template'] ) && '' !== $input['template'];
		$has_secs = ! empty( $input['sections'] );
		if ( $has_tpl === $has_secs ) {
			return new WP_Error( 'nori_ability_input', __( 'Give either template (a page template key) or sections (a list of section templates), not both.', 'nori-for-elementor' ) );
		}
		$args  = array(
			'status'  => in_array( $input['status'] ?? 'draft', array( 'draft', 'private', 'publish' ), true ) ? ( $input['status'] ?? 'draft' ) : 'draft',
			'samples' => ! array_key_exists( 'samples', $input ) || ! empty( $input['samples'] ),
		);
		$found = $this->sections_from_input( $input );
		if ( is_wp_error( $found ) ) {
			return $found;
		}
		list( $sections, $default_title, $notes ) = $found;
		$title = trim( (string) ( $input['title'] ?? '' ) );
		$title = '' !== $title ? $title : $default_title;
		if ( '' === $title ) {
			return new WP_Error( 'nori_ability_input', __( 'Give the page a title.', 'nori-for-elementor' ) );
		}
		if ( ! $sections ) {
			return new WP_Error( 'nori_ability_input', __( 'Nothing is left to put on the page.', 'nori-for-elementor' ) );
		}
		$made = $this->plugin->templates->create_page( $title, $sections, $args );
		if ( is_wp_error( $made ) ) {
			return $made;
		}
		return $this->page_answer( $made['id'], (array) $made['warnings'], array_merge( $notes, (array) ( $made['motion'] ?? array() ) ) );
	}

	/* ------------------------------------------------------------------
	 * nori/update-page, nori/get-pages
	 * --------------------------------------------------------------- */

	public function update_page( $input = array() ) {
		$input = is_array( $input ) ? $input : array();
		$id    = (int) ( $input['id'] ?? 0 );
		$post  = get_post( $id );
		if ( ! $post || 'page' !== $post->post_type || 'trash' === $post->post_status ) {
			return new WP_Error( 'nori_ability_input', __( 'id must be the id of a page (see nori/get-pages).', 'nori-for-elementor' ) );
		}
		$whole = ( isset( $input['template'] ) && '' !== $input['template'] ) || ! empty( $input['sections'] );
		$parts = ! empty( $input['set'] ) || ! empty( $input['remove'] ) || ! empty( $input['add'] );
		if ( ( isset( $input['template'] ) && '' !== $input['template'] ) && ! empty( $input['sections'] ) ) {
			return new WP_Error( 'nori_ability_input', __( 'Give template or sections, not both.', 'nori-for-elementor' ) );
		}
		if ( $whole && $parts ) {
			return new WP_Error( 'nori_ability_input', __( 'Either rebuild the whole page (template or sections) or change some sections (set, remove, add), not both.', 'nori-for-elementor' ) );
		}
		$args = array_key_exists( 'samples', $input ) ? array( 'samples' => ! empty( $input['samples'] ) ) : array();
		$notes = array();
		$r     = null;
		if ( $whole ) {
			$found = $this->sections_from_input( $input );
			if ( is_wp_error( $found ) ) {
				return $found;
			}
			list( $sections, , $notes ) = $found;
			if ( ! $sections ) {
				return new WP_Error( 'nori_ability_input', __( 'Nothing is left to put on the page.', 'nori-for-elementor' ) );
			}
			$r = $this->plugin->templates->update_page( $id, array( 'sections' => $sections ), $args );
		} elseif ( $parts ) {
			$set = array();
			foreach ( (array) ( $input['set'] ?? array() ) as $s ) {
				$set[ (int) ( $s['position'] ?? 0 ) ] = array_intersect_key( (array) $s, array_flip( array( 'variant', 'content' ) ) );
			}
			$add = array();
			foreach ( (array) ( $input['add'] ?? array() ) as $a ) {
				$key  = sanitize_key( (string) ( $a['key'] ?? '' ) );
				$tpl  = $this->plugin->templates->get( $key );
				$kept = $this->drop_unsupplied(
					array(
						array(
							'key'     => $key,
							'variant' => sanitize_key( (string) ( $a['variant'] ?? '' ) ),
							'content' => array_merge( array_intersect_key( Templates::site_details(), (array) ( $tpl['slots'] ?? array() ) ), (array) ( $a['content'] ?? array() ) ),
						),
					),
					$notes
				);
				if ( $kept ) {
					$add[] = $kept[0] + array( 'after' => (int) ( $a['after'] ?? 0 ) );
				}
			}
			$r = $this->plugin->templates->update_page(
				$id,
				array(
					'set'    => $set,
					'remove' => array_map( 'intval', (array) ( $input['remove'] ?? array() ) ),
					'add'    => $add,
				),
				$args
			);
		} elseif ( ! isset( $input['title'] ) && ! isset( $input['status'] ) ) {
			return new WP_Error( 'nori_ability_input', __( 'Nothing to change: give template or sections (the whole page), set, remove or add (some sections), title or status.', 'nori-for-elementor' ) );
		}
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		// Title and status; the page's address stays as it is.
		$post_update = array( 'ID' => $id );
		if ( isset( $input['title'] ) && '' !== trim( (string) $input['title'] ) ) {
			$post_update['post_title'] = sanitize_text_field( (string) $input['title'] );
		}
		if ( isset( $input['status'] ) && in_array( $input['status'], array( 'draft', 'private', 'publish' ), true ) ) {
			$post_update['post_status'] = $input['status'];
		}
		if ( count( $post_update ) > 1 ) {
			wp_update_post( $post_update );
		}
		return $this->page_answer( $id, (array) ( $r['warnings'] ?? array() ), array_merge( $notes, (array) ( $r['motion'] ?? array() ) ) );
	}

	public function get_pages( $input = array() ) {
		$input = is_array( $input ) ? $input : array();
		$front = (int) get_option( 'page_on_front' );
		if ( ! empty( $input['id'] ) ) {
			$id   = (int) $input['id'];
			$post = get_post( $id );
			if ( ! $post || 'page' !== $post->post_type ) {
				return new WP_Error( 'nori_ability_input', __( 'id must be the id of a page.', 'nori-for-elementor' ) );
			}
			$record = $this->plugin->templates->page_record( $id );
			$out    = array(
				'id'         => $id,
				'title'      => $post->post_title,
				'status'     => $post->post_status,
				'path'       => '/' . trim( (string) get_page_uri( $id ), '/' ) . '/',
				'front_page' => $id === $front,
				'by_nori'    => (bool) $record,
				'sections'   => array_map(
					fn( $i, $s ) => array(
						'position' => $i + 1,
						'key'      => $s['key'],
						'variant'  => $s['variant'],
						'content'  => (object) $s['content'],
					),
					array_keys( (array) ( $record['sections'] ?? array() ) ),
					(array) ( $record['sections'] ?? array() )
				),
			);
			if ( Site_Tools::yoast_active() ) {
				$out['seo'] = array(
					'title'       => (string) \WPSEO_Meta::get_value( 'title', $id ),
					'description' => (string) \WPSEO_Meta::get_value( 'metadesc', $id ),
					'image'       => (string) \WPSEO_Meta::get_value( 'opengraph-image', $id ),
				);
			}
			return $out;
		}
		$pages = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => 200,
				'orderby'        => 'menu_order title',
				'order'          => 'ASC',
			)
		);
		return array(
			'pages' => array_map(
				fn( $p ) => array(
					'id'         => $p->ID,
					'title'      => $p->post_title,
					'status'     => $p->post_status,
					'path'       => '/' . trim( (string) get_page_uri( $p->ID ), '/' ) . '/',
					'front_page' => $p->ID === $front,
					'by_nori'    => (bool) $this->plugin->templates->page_record( $p->ID ),
				),
				$pages
			),
		);
	}

	/* ------------------------------------------------------------------
	 * nori/apply-brand
	 * --------------------------------------------------------------- */

	/** The site type as the abilities name it. */
	public function site_type() {
		$dark   = 'dark' === Design::base();
		$switch = $this->plugin->modules->is_enabled( 'dark-mode' );
		foreach ( self::SITE_TYPES as $name => $t ) {
			if ( ( 'dark' === $t[0] ) === $dark && $t[1] === $switch ) {
				return $name;
			}
		}
		return 'light';
	}

	public function apply_brand( $input = array() ) {
		$input  = is_array( $input ) ? $input : array();
		$preset = isset( $input['preset'] ) ? sanitize_key( (string) $input['preset'] ) : '';
		$accent = Design::hex( $input['accent'] ?? '' );
		$ink    = Design::hex( $input['ink'] ?? '' );
		if ( '' === $preset && $accent ) {
			$preset = 'custom';
		}
		$type  = isset( $input['site_type'] ) ? (string) $input['site_type'] : '';
		$exact = array();
		foreach ( (array) ( $input['colors'] ?? array() ) as $k => $v ) {
			if ( ! in_array( $k, Design::COLOR_KEYS, true ) || ! Design::hex( $v ) ) {
				/* translators: 1: a color key, 2: list of color keys. */
				return new WP_Error( 'nori_ability_input', sprintf( __( 'colors.%1$s: give one of %2$s, each as #RRGGBB.', 'nori-for-elementor' ), sanitize_key( (string) $k ), implode( ', ', Design::COLOR_KEYS ) ) );
			}
			$exact[ $k ] = strtoupper( Design::hex( $v ) );
		}
		if ( '' === $preset && '' === $type && ! $exact ) {
			return new WP_Error( 'nori_ability_input', __( 'Give a preset, a brand color (accent), colors or a site_type.', 'nori-for-elementor' ) );
		}
		if ( '' !== $type && ! isset( self::SITE_TYPES[ $type ] ) ) {
			/* translators: %s: list of site types. */
			return new WP_Error( 'nori_ability_input', sprintf( __( 'site_type must be one of: %s.', 'nori-for-elementor' ), implode( ', ', array_keys( self::SITE_TYPES ) ) ) );
		}
		$presets = Design::presets();
		if ( 'custom' === $preset && ! $accent ) {
			return new WP_Error( 'nori_ability_input', __( 'A custom brand needs accent, the brand color as #RRGGBB.', 'nori-for-elementor' ) );
		}
		if ( '' !== $preset && 'custom' !== $preset && ! isset( $presets[ $preset ] ) ) {
			/* translators: %s: list of preset keys. */
			return new WP_Error( 'nori_ability_input', sprintf( __( 'preset must be one of: %s, or custom.', 'nori-for-elementor' ), implode( ', ', array_keys( $presets ) ) ) );
		}

		$from      = $this->site_type();
		$to        = '' !== $type ? $type : $from;
		$to_dark   = 'dark' === self::SITE_TYPES[ $to ][0];
		$switch_on = self::SITE_TYPES[ $to ][1];

		// The two color sets: [ light, dark ].
		if ( 'custom' === $preset ) {
			$palette = Design::derive( $accent, $ink );
		} elseif ( '' !== $preset ) {
			$palette = array(
				'light' => $presets[ $preset ]['light'],
				'dark'  => $presets[ $preset ]['dark'],
			);
		} else {
			// No new colors: keep both sets and only swap which one is the site's own (like the Colors screen).
			$own     = $this->current_colors();
			$second  = $this->second_colors();
			$is_dark = 'dark' === self::SITE_TYPES[ $from ][0];
			$palette = array(
				'light' => $is_dark ? $second : $own,
				'dark'  => $is_dark ? $own : $second,
			);
		}
		$own    = array();
		$second = array();
		foreach ( Design::COLOR_KEYS as $k ) {
			$own[ $k ]    = strtoupper( (string) Design::hex( $palette[ $to_dark ? 'dark' : 'light' ][ $k ] ?? '' ) );
			$second[ $k ] = strtolower( (string) Design::hex( $palette[ $to_dark ? 'light' : 'dark' ][ $k ] ?? '' ) );
		}

		// The style guide's own colors win over the worked-out ones.
		$own = array_merge( $own, $exact );

		$dark_settings = $this->plugin->modules->settings( 'dark-mode' );
		foreach ( Design::DARK_SETTINGS as $k => $setting ) {
			$dark_settings[ $setting ] = $second[ $k ];
		}
		$batch = array(
			'base'      => $to_dark ? 'dark' : 'light',
			'variables' => array( 'values' => $own ),
			'modules'   => array(
				'enabled'  => array( 'dark-mode' => $switch_on ),
				'settings' => array( 'dark-mode' => $dark_settings ),
			),
		);
		if ( '' !== $preset ) {
			$batch['brand'] = array(
				'preset' => $preset,
				'accent' => $accent,
				'ink'    => $ink,
			);
		}
		$r = ( new Admin( $this->plugin ) )->app()->save( $batch );
		if ( $r['errors'] ) {
			return new WP_Error( 'nori_ability_save', implode( ' ', $r['errors'] ) );
		}
		$brand = (array) get_option( Design::BRAND_OPTION, array() );
		return array(
			'preset'        => (string) ( $brand['preset'] ?? '' ),
			'site_type'     => $this->site_type(),
			'dark_mode'     => $this->plugin->modules->is_enabled( 'dark-mode' ),
			'colors'        => $this->current_colors(),
			'second_colors' => $this->second_colors(),
			'messages'      => $r['messages'],
		);
	}

	/** The Nori colors as Elementor has them now (#RRGGBB). */
	private function current_colors() {
		$status = $this->plugin->variables->status();
		$out    = array();
		foreach ( Design::COLOR_KEYS as $k ) {
			$out[ $k ] = strtoupper( (string) Design::hex( $status[ $k ]['value'] ?? '' ) );
		}
		return $out;
	}

	/** Their second versions, from the Dark Mode module's settings. */
	private function second_colors() {
		$s   = $this->plugin->modules->settings( 'dark-mode' );
		$out = array();
		foreach ( Design::DARK_SETTINGS as $k => $setting ) {
			$out[ $k ] = strtoupper( (string) Design::hex( $s[ $setting ] ?? '' ) );
		}
		return $out;
	}

	/* ------------------------------------------------------------------
	 * nori/list-modules, nori/set-modules
	 * --------------------------------------------------------------- */

	/** Module key => category label, from the Modules screen's categories. */
	private function module_categories() {
		$out = array();
		foreach ( ( new Admin_Modules_Screen( $this->plugin ) )->categories() as $cat ) {
			foreach ( array_keys( $cat['items'] ) as $key ) {
				$out[ $key ] = $cat['label'];
			}
		}
		return $out;
	}

	public function list_modules() {
		$mods   = $this->plugin->modules;
		$cats   = $this->module_categories();
		$status = Plugin::atomic_available() ? $this->plugin->classes->status() : array();
		$status = is_wp_error( $status ) ? array() : $status;
		$out    = array();
		foreach ( $mods->all() as $key => $m ) {
			$values   = $mods->settings( $key );
			$settings = array();
			foreach ( (array) ( $m['settings'] ?? array() ) as $f ) {
				if ( ! empty( $f['plain'] ) ) {
					continue; // Dark Mode's palette: set with nori/apply-brand.
				}
				$settings[] = array_filter(
					array(
						'key'     => $f['key'],
						'label'   => $f['label'] ?? $f['key'],
						'type'    => $f['type'] ?? 'text',
						'min'     => $f['min'] ?? null,
						'max'     => $f['max'] ?? null,
						'step'    => $f['step'] ?? null,
						'unit'    => $f['unit'] ?? null,
						'options' => $f['options'] ?? null,
						'help'    => $f['help'] ?? null,
						'default' => Modules::field_default( $f ),
						'value'   => $values[ $f['key'] ] ?? null,
					),
					fn( $v ) => null !== $v
				);
			}
			$out[] = array(
				'key'        => $key,
				'label'      => $m['label'] ?? $key,
				'category'   => $cats[ $key ] ?? '',
				'summary'    => $m['summary'] ?? '',
				'on'         => $mods->is_enabled( $key ),
				'excludes'   => array_values( $mods->rivals( $key ) ),
				'works_with' => $this->plugin->library->module_pairs( $key ),
				'classes'    => array_map(
					fn( $c ) => array(
						'name'          => $c['key'] . '-np',
						'where'         => $c['role'] ?? '',
						'in_elementor'  => in_array( $status[ $c['key'] ]['status'] ?? 'missing', array( 'default', 'edited' ), true ),
					),
					(array) ( $m['classes'] ?? array() )
				),
				'settings'   => $settings,
			);
		}
		return array(
			'modules'     => $out,
			'pair_kinds'  => $this->plugin->library->module_pair_kinds(),
			'value_rules' => array(
				'range'  => __( 'A number between min and max, in steps of step.', 'nori-for-elementor' ),
				'toggle' => __( 'true or false.', 'nori-for-elementor' ),
				'select' => __( 'One of the keys of options.', 'nori-for-elementor' ),
				'color'  => __( 'var(<Nori color>) to follow one of the Nori colors (like var(accent)), or #RRGGBB for a fixed color.', 'nori-for-elementor' ),
			),
		);
	}

	public function set_modules( $input = array() ) {
		$input    = is_array( $input ) ? $input : array();
		$mods     = $this->plugin->modules;
		$all      = $mods->all();
		$want     = (array) ( $input['modules'] ?? array() );
		$settings = (array) ( $input['settings'] ?? array() );
		if ( ! $want && ! $settings ) {
			return new WP_Error( 'nori_ability_input', __( 'Give modules (key => true or false) or settings (key => { setting => value }).', 'nori-for-elementor' ) );
		}
		$unknown = array_diff( array_merge( array_keys( $want ), array_keys( $settings ) ), array_keys( $all ) );
		if ( $unknown ) {
			/* translators: 1: unknown keys, 2: valid keys. */
			return new WP_Error( 'nori_ability_input', sprintf( __( 'Unknown module: %1$s. Modules are: %2$s.', 'nori-for-elementor' ), implode( ', ', $unknown ), implode( ', ', array_keys( $all ) ) ) );
		}
		$on = array_keys( array_filter( $want ) );
		foreach ( $on as $i => $key ) {
			$clash = array_intersect( $mods->rivals( $key ), array_slice( $on, $i + 1 ) );
			if ( $clash ) {
				/* translators: 1: module, 2: module. */
				return new WP_Error( 'nori_ability_input', sprintf( __( '%1$s and %2$s can\'t both be on. Pick one.', 'nori-for-elementor' ), $key, reset( $clash ) ) );
			}
		}
		$enabled = array();
		foreach ( $want as $key => $value ) {
			$enabled[ $key ] = (bool) $value;
			if ( $value ) {
				foreach ( $mods->rivals( $key ) as $rival ) {
					$enabled[ $rival ] = false;
				}
			}
		}
		// Settings: each module's full set, the given ones over the current values.
		$full = array();
		foreach ( $settings as $key => $values ) {
			if ( ! is_array( $values ) ) {
				continue;
			}
			$fields = array();
			foreach ( (array) ( $all[ $key ]['settings'] ?? array() ) as $f ) {
				if ( empty( $f['plain'] ) ) {
					$fields[ $f['key'] ] = $f;
				}
			}
			$bad = array_diff( array_keys( $values ), array_keys( $fields ) );
			if ( $bad ) {
				/* translators: 1: module, 2: unknown setting keys, 3: valid setting keys. */
				return new WP_Error( 'nori_ability_input', sprintf( __( '%1$s has no setting %2$s. Its settings are: %3$s.', 'nori-for-elementor' ), $key, implode( ', ', $bad ), implode( ', ', array_keys( $fields ) ) ) );
			}
			foreach ( $values as $fkey => $v ) {
				$problem = $this->setting_problem( $fields[ $fkey ], $v );
				if ( $problem ) {
					/* translators: 1: module, 2: setting, 3: what's wrong. */
					return new WP_Error( 'nori_ability_input', sprintf( __( '%1$s › %2$s: %3$s', 'nori-for-elementor' ), $key, $fkey, $problem ) );
				}
			}
			$full[ $key ] = array_merge( $mods->settings( $key ), $values );
		}
		$before = array_fill_keys( array_keys( $all ), false );
		foreach ( array_keys( $all ) as $key ) {
			$before[ $key ] = $mods->is_enabled( $key );
		}
		$r = ( new Admin( $this->plugin ) )->app()->save(
			array(
				'modules' => array(
					'enabled'  => $enabled,
					'settings' => $full,
				),
			)
		);
		if ( $r['errors'] ) {
			return new WP_Error( 'nori_ability_save', implode( ' ', $r['errors'] ) );
		}
		$changed = array();
		foreach ( array_keys( $all ) as $key ) {
			if ( $before[ $key ] !== $mods->is_enabled( $key ) ) {
				$changed[ $key ] = $mods->is_enabled( $key ) ? 'on' : 'off';
			}
		}
		return array(
			'on'       => array_values( $mods->enabled() ),
			'changed'  => $changed,
			'messages' => $r['messages'],
		);
	}

	/** Why a value doesn't fit a module setting, or '' when it does. */
	private function setting_problem( array $f, $v ) {
		switch ( $f['type'] ?? 'text' ) {
			case 'range':
				if ( ! is_numeric( $v ) ) {
					return __( 'needs a number.', 'nori-for-elementor' );
				}
				if ( ( isset( $f['min'] ) && $v < $f['min'] ) || ( isset( $f['max'] ) && $v > $f['max'] ) ) {
					/* translators: 1: minimum, 2: maximum. */
					return sprintf( __( 'must be between %1$s and %2$s.', 'nori-for-elementor' ), $f['min'] ?? '', $f['max'] ?? '' );
				}
				return '';
			case 'toggle':
				return is_bool( $v ) || in_array( $v, array( 0, 1, '0', '1' ), true ) ? '' : __( 'needs true or false.', 'nori-for-elementor' );
			case 'select':
				/* translators: %s: list of options. */
				return isset( $f['options'][ (string) $v ] ) ? '' : sprintf( __( 'must be one of: %s.', 'nori-for-elementor' ), implode( ', ', array_keys( (array) $f['options'] ) ) );
			case 'color':
				$ref = Modules::link_ref( $v );
				if ( null !== $ref ) {
					return in_array( $ref, Design::COLOR_KEYS, true ) || 0 === strpos( $ref, 'e-gv-' ) ? '' : __( 'var() takes one of the Nori colors, like var(accent).', 'nori-for-elementor' );
				}
				return Design::hex( $v ) ? '' : __( 'needs #RRGGBB or var(<Nori color>).', 'nori-for-elementor' );
		}
		return '';
	}

	/* ------------------------------------------------------------------
	 * nori/site-status
	 * --------------------------------------------------------------- */

	public function site_status( $input = array() ) {
		$out   = $this->plugin->site_tools->status();
		$sw    = $out['software'];
		$notes = array();
		if ( ! $sw['elementor-pro']['active'] ) {
			$notes[] = __( 'Elementor Pro isn\'t active. Pages still work, but forms (and the HubSpot Bridge) need it. Ask the owner to install and license it.', 'nori-for-elementor' );
		} elseif ( isset( $sw['elementor-pro']['license_active'] ) && ! $sw['elementor-pro']['license_active'] ) {
			$notes[] = __( 'Elementor Pro has no active license. Ask the owner to connect it under Elementor > License.', 'nori-for-elementor' );
		}
		if ( ! $sw['hello-elementor']['active'] ) {
			$notes[] = __( 'The Hello Elementor theme isn\'t the active theme. Nori\'s header, footer and pages are made for it; ask the owner before switching themes.', 'nori-for-elementor' );
		}
		if ( ! $out['atomic'] ) {
			$notes[] = __( 'Elementor\'s Atomic editor is off. Nori\'s templates need it: Elementor > Settings > Features.', 'nori-for-elementor' );
		}
		$out['notes'] = $notes;
		return $out;
	}

	/* ------------------------------------------------------------------
	 * nori/set-seo
	 * --------------------------------------------------------------- */

	public function set_seo( $input = array() ) {
		$input = is_array( $input ) ? $input : array();
		if ( empty( $input['site'] ) && empty( $input['pages'] ) ) {
			return new WP_Error( 'nori_ability_input', __( 'Nothing to do: give site or pages.', 'nori-for-elementor' ) );
		}
		return $this->plugin->site_tools->set_seo( (array) ( $input['site'] ?? array() ), array_values( array_filter( (array) ( $input['pages'] ?? array() ), 'is_array' ) ) );
	}

	/* ------------------------------------------------------------------
	 * nori/add-image
	 * --------------------------------------------------------------- */

	public function add_image( $input = array() ) {
		$input = is_array( $input ) ? $input : array();
		return $this->plugin->site_tools->add_image(
			(string) ( $input['url'] ?? '' ),
			(string) ( $input['data'] ?? '' ),
			(string) ( $input['filename'] ?? '' ),
			array_key_exists( 'alt', $input ) ? (string) $input['alt'] : null,
			(string) ( $input['title'] ?? '' ),
			in_array( $input['use_as'] ?? '', array( 'logo', 'site_icon' ), true ) ? $input['use_as'] : ''
		);
	}

	/* ------------------------------------------------------------------
	 * nori/set-typography
	 * --------------------------------------------------------------- */

	public function set_typography( $input = array() ) {
		$input = is_array( $input ) ? $input : array();
		if ( ! array_intersect_key( $input, array_flip( array( 'heading_font', 'body_font', 'base', 'ratio', 'heading_weight' ) ) ) ) {
			return new WP_Error( 'nori_ability_input', __( 'Nothing to do: give heading_font, body_font, base, ratio or heading_weight.', 'nori-for-elementor' ) );
		}
		// Check everything first, so a mistake changes nothing.
		$fonts = array();
		foreach ( array( 'heading_font', 'body_font' ) as $k ) {
			$fonts[ $k ] = '';
			if ( isset( $input[ $k ] ) && '' !== trim( (string) $input[ $k ] ) ) {
				$fonts[ $k ] = Site_Tools::font_name( $input[ $k ] );
				if ( '' === $fonts[ $k ] ) {
					/* translators: %s: font name. */
					return new WP_Error( 'nori_ability_input', sprintf( __( '%s isn\'t a font Elementor knows. Use a Google Fonts family name exactly as Google lists it, like Inter, DM Sans or Playfair Display.', 'nori-for-elementor' ), sanitize_text_field( (string) $input[ $k ] ) ) );
				}
			}
		}
		if ( isset( $input['base'] ) && ( ! is_numeric( $input['base'] ) || $input['base'] < 12 || $input['base'] > 24 ) ) {
			return new WP_Error( 'nori_ability_input', __( 'base must be a number of px between 12 and 24.', 'nori-for-elementor' ) );
		}
		if ( isset( $input['ratio'] ) && ! isset( Design::RATIOS[ (string) $input['ratio'] ] ) ) {
			/* translators: %s: list of ratios. */
			return new WP_Error( 'nori_ability_input', sprintf( __( 'ratio must be one of: %s.', 'nori-for-elementor' ), implode( ', ', array_keys( Design::RATIOS ) ) ) );
		}
		if ( isset( $input['heading_weight'] ) && ! in_array( (string) $input['heading_weight'], Design::WEIGHTS, true ) ) {
			/* translators: %s: list of weights. */
			return new WP_Error( 'nori_ability_input', sprintf( __( 'heading_weight must be one of: %s.', 'nori-for-elementor' ), implode( ', ', Design::WEIGHTS ) ) );
		}

		$messages = array();
		if ( $fonts['heading_font'] || $fonts['body_font'] ) {
			$r = $this->plugin->site_tools->set_fonts( $fonts['heading_font'], $fonts['body_font'] );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
			$messages[] = __( 'Fonts set in Elementor\'s Site Settings.', 'nori-for-elementor' );
			if ( ! $this->plugin->modules->is_enabled( 'font-preconnect' ) ) {
				$messages[] = __( 'Tip: switch on the font-preconnect module (nori-set-modules) so Google Fonts load sooner.', 'nori-for-elementor' );
			}
		}
		if ( array_intersect_key( $input, array_flip( array( 'base', 'ratio', 'heading_weight' ) ) ) ) {
			$type = array_merge(
				Design::type_settings(),
				array_filter(
					array(
						'base'   => isset( $input['base'] ) ? (int) $input['base'] : null,
						'ratio'  => isset( $input['ratio'] ) ? (string) $input['ratio'] : null,
						'weight' => isset( $input['heading_weight'] ) ? (string) $input['heading_weight'] : null,
					),
					fn( $v ) => null !== $v
				)
			);
			$r = ( new Admin( $this->plugin ) )->app()->save( array( 'type' => $type ) );
			if ( $r['errors'] ) {
				return new WP_Error( 'nori_ability_save', implode( ' ', $r['errors'] ) );
			}
			$messages = array_merge( $messages, $r['messages'] );
		}
		return array_merge(
			$this->plugin->site_tools->fonts(),
			array(
				'type_scale' => Design::type_settings(),
				'messages'   => $messages,
			)
		);
	}

	/* ------------------------------------------------------------------
	 * nori/site-setup
	 * --------------------------------------------------------------- */

	public function site_setup( $input = array() ) {
		$input = is_array( $input ) ? $input : array();
		if ( ! array_intersect_key( $input, array_flip( array( 'starter', 'header_footer', 'site_title', 'tagline', 'logo', 'front_page' ) ) ) ) {
			return new WP_Error( 'nori_ability_input', __( 'Nothing to do: give starter, header_footer, site_title, tagline, logo or front_page.', 'nori-for-elementor' ) );
		}
		// Check everything first, so a mistake changes nothing.
		$logo = isset( $input['logo'] ) ? (int) $input['logo'] : 0;
		if ( isset( $input['logo'] ) && ! wp_attachment_is_image( $logo ) ) {
			return new WP_Error( 'nori_ability_input', __( 'logo must be the id of an image in the media library.', 'nori-for-elementor' ) );
		}
		$front = isset( $input['front_page'] ) ? get_post( (int) $input['front_page'] ) : null;
		if ( isset( $input['front_page'] ) && ( ! $front || 'page' !== $front->post_type || 'trash' === $front->post_status ) ) {
			return new WP_Error( 'nori_ability_input', __( 'front_page must be the id of a page.', 'nori-for-elementor' ) );
		}
		if ( ! empty( $input['starter'] ) && ! Plugin::atomic_available() ) {
			return new WP_Error( 'nori_ability_atomic', __( 'The starter design system needs Elementor\'s Atomic editor (Elementor 4) switched on.', 'nori-for-elementor' ) );
		}

		$done  = array();
		$notes = array();
		if ( isset( $input['site_title'] ) && '' !== trim( (string) $input['site_title'] ) ) {
			update_option( 'blogname', sanitize_text_field( (string) $input['site_title'] ) );
			$done[] = 'site_title';
		}
		if ( isset( $input['tagline'] ) ) {
			update_option( 'blogdescription', sanitize_text_field( (string) $input['tagline'] ) );
			$done[] = 'tagline';
		}
		if ( $logo ) {
			set_theme_mod( 'custom_logo', $logo );
			$done[] = 'logo';
		}

		if ( ! empty( $input['starter'] ) ) {
			$vars    = array_keys( array_filter( $this->plugin->library->variables(), fn( $v ) => empty( $v['module'] ) ) );
			$vstatus = $this->plugin->variables->status();
			$cstatus = $this->plugin->classes->status();
			if ( is_wp_error( $vstatus ) ) {
				return $vstatus;
			}
			if ( is_wp_error( $cstatus ) ) {
				return $cstatus;
			}
			$classes = array_keys( array_filter( $this->plugin->library->classes(), fn( $c ) => empty( $c['module'] ) ) );
			$gone    = fn( $st ) => in_array( $st, array( 'missing', 'deleted' ), true );
			$add_v   = array_values( array_filter( $vars, fn( $k ) => $gone( $vstatus[ $k ]['status'] ?? 'missing' ) ) );
			$add_c   = array_values( array_filter( $classes, fn( $k ) => $gone( $cstatus[ $k ]['status'] ?? 'missing' ) ) );
			if ( $add_v || $add_c ) {
				$r = ( new Admin( $this->plugin ) )->app()->save(
					array(
						'variables' => array( 'add' => $add_v ),
						'classes'   => array( 'add' => $add_c ),
					)
				);
				if ( $r['errors'] ) {
					return new WP_Error( 'nori_ability_save', implode( ' ', $r['errors'] ) );
				}
				$notes = array_merge( $notes, $r['messages'] );
			} else {
				$notes[] = __( 'The starter design system was already in Elementor.', 'nori-for-elementor' );
			}
			$done[] = 'starter';
		}

		$parts = null;
		if ( isset( $input['header_footer'] ) && is_array( $input['header_footer'] ) ) {
			$hf      = $input['header_footer'];
			// Contact details left out stay empty: the templates' sample phone, address and hours belong to a made-up business.
			$content = array(
				'header' => array_merge( array( 'phone' => '' ), is_array( $hf['header'] ?? null ) ? $hf['header'] : array() ),
				'footer' => array_merge(
					array(
						'phone'   => '',
						'address' => '',
						'hours'   => array(),
					),
					is_array( $hf['footer'] ?? null ) ? $hf['footer'] : array()
				),
			);
			if ( $logo && ! isset( $content['header']['logo'] ) ) {
				$content['header']['logo'] = $logo;
			}
			$r = $this->plugin->site_parts->create( $content, array_values( array_intersect( (array) ( $hf['replace'] ?? array() ), array( 'header', 'footer' ) ) ), sanitize_key( (string) ( $hf['style'] ?? '' ) ) );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
			$status = $this->plugin->site_parts->status();
			$parts  = array(
				'made'     => $r['made'],
				'kept'     => $r['kept'],
				'replaced' => $r['replaced'],
				'mode'     => $status['mode'],
				'parts'    => array_map(
					fn( $p ) => array(
						'id'       => $p['id'],
						'edit_url' => $p['edit_url'],
						'live'     => (bool) $p['live'],
					),
					$status['parts']
				),
			);
			$notes = array_merge( $notes, $r['notes'] );
			if ( $r['kept'] ) {
				$notes[] = __( 'An existing header or footer was kept as it is. Name it in replace only if the site owner wants it made again.', 'nori-for-elementor' );
			}
			$done[] = 'header_footer';
		}

		if ( $front ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $front->ID );
			$done[] = 'front_page';
			if ( 'publish' !== $front->post_status ) {
				$notes[] = __( 'The home page is still a draft, so visitors see it once it\'s published.', 'nori-for-elementor' );
			}
		}

		return array(
			'done'          => $done,
			'header_footer' => $parts,
			'notes'         => array_values( $notes ),
		);
	}
}
