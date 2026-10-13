<?php
/**
 * HubSpot Bridge admin: create, check and delete bridges (admin-ajax), and the state the app's
 * HubSpot panel draws. Bridges save on their own, separately from the app's save bar.
 *
 * @package Nori_For_Elementor
 */

namespace Nori\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_HubSpot {

	const NONCE = 'nori_fe_hubspot';

	/** @var Plugin */
	private $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	public function register_hooks() {
		foreach ( array( 'read', 'parse', 'create', 'refresh', 'update', 'delete', 'fixclass' ) as $op ) {
			add_action( 'wp_ajax_nori_fe_hs_' . $op, array( $this, 'ajax_' . $op ) );
		}
	}

	/* ------------------------------------------------------------------
	 * AJAX
	 * --------------------------------------------------------------- */

	private function guard() {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! current_user_can( Admin::CAP ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do that.', 'nori-for-elementor' ) ), 403 );
		}
	}

	private function post( $key ) {
		// Raw on purpose: some values are JSON or a pasted embed code (with <script> tags), which
		// sanitize_text_field() would break. Every caller parses, validates or sanitizes what it uses.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified in guard(); see above.
		return isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
	}

	/** Fields sent as JSON from the editor in the panel. */
	private function posted_fields() {
		$raw = json_decode( (string) $this->post( 'fields' ), true );
		return HubSpot_Bridges::sanitize_fields( is_array( $raw ) ? $raw : array() );
	}

	/** The form's privacy settings, read by the browser and sent as JSON. */
	private function posted_consent() {
		$raw = json_decode( (string) $this->post( 'consent' ), true );
		return HubSpot_Bridges::sanitize_consent( is_array( $raw ) ? $raw : null );
	}

	/** Paste of an embed code: find the ids and try to read the fields from HubSpot. */
	public function ajax_read() {
		$this->guard();
		// The browser sends just the ids it found (security filters on some hosts block posted HTML);
		// the raw embed text is the fallback.
		$ids = '' !== (string) $this->post( 'portal' )
			? HubSpot_Bridges::parse_embed( 'portalId: "' . preg_replace( '/\D/', '', (string) $this->post( 'portal' ) ) . '", formId: "' . sanitize_text_field( (string) $this->post( 'form' ) ) . '", region: "' . sanitize_key( (string) $this->post( 'region' ) ) . '"' )
			: HubSpot_Bridges::parse_embed( (string) $this->post( 'embed' ) );
		if ( is_wp_error( $ids ) ) {
			wp_send_json_error( array( 'message' => $ids->get_error_message() ) );
		}
		$def = $this->plugin->hubspot->fetch_definition( $ids['portal'], $ids['form'], $ids['region'] );
		wp_send_json_success(
			array(
				'ids'        => $ids,
				'definition'  => HubSpot_Bridges::definition_url( $ids['portal'], $ids['form'], $ids['region'] ),
				'definitions' => HubSpot_Bridges::definition_urls( $ids['portal'], $ids['form'], $ids['region'] ),
				'name'        => is_wp_error( $def ) ? '' : $def['name'],
				'fields'      => is_wp_error( $def ) ? array() : $def['fields'],
				'consent'     => is_wp_error( $def ) ? null : $def['consent'],
				'fetchError' => is_wp_error( $def ) ? $def->get_error_message() : '',
			)
		);
	}

	/** The browser read the HubSpot definition itself (when this server couldn't reach HubSpot). */
	public function ajax_parse() {
		$this->guard();
		$data = json_decode( (string) $this->post( 'json' ), true );
		if ( ! is_array( $data ) ) {
			wp_send_json_error( array( 'message' => __( 'HubSpot\'s answer wasn\'t readable.', 'nori-for-elementor' ) ) );
		}
		wp_send_json_success( HubSpot_Bridges::parse_definition( $data ) );
	}

	public function ajax_create() {
		$this->guard();
		$bridge = $this->plugin->hubspot->create(
			array(
				'portal' => $this->post( 'portal' ),
				'form'   => $this->post( 'form' ),
				'region' => $this->post( 'region' ),
				'name'   => $this->post( 'name' ),
				'fields'  => $this->posted_fields(),
				'consent' => $this->posted_consent(),
			)
		);
		if ( is_wp_error( $bridge ) ) {
			wp_send_json_error( array( 'message' => $bridge->get_error_message() ) );
		}
		wp_send_json_success( array( 'id' => $bridge['id'] ) );
	}

	/** Read an existing bridge's fields from HubSpot again. */
	public function ajax_refresh() {
		$this->guard();
		$bridge = $this->plugin->hubspot->get( sanitize_key( (string) $this->post( 'id' ) ) );
		if ( ! $bridge ) {
			wp_send_json_error( array( 'message' => __( 'That bridge no longer exists.', 'nori-for-elementor' ) ) );
		}
		$def = $this->plugin->hubspot->fetch_definition( $bridge['portal'], $bridge['form'], $bridge['region'] );
		if ( is_wp_error( $def ) ) {
			wp_send_json_success(
				array(
					'fetchError' => $def->get_error_message(),
					'definition'  => HubSpot_Bridges::definition_url( $bridge['portal'], $bridge['form'], $bridge['region'] ),
					'definitions' => HubSpot_Bridges::definition_urls( $bridge['portal'], $bridge['form'], $bridge['region'] ),
				)
			);
		}
		$done = $this->plugin->hubspot->update( $bridge['id'], $def['fields'], '' !== $def['name'] ? $def['name'] : null, $def['consent'] );
		if ( is_wp_error( $done ) ) {
			wp_send_json_error( array( 'message' => $done->get_error_message() ) );
		}
		wp_send_json_success( array( 'updated' => true ) );
	}

	/** Save an edited field list (typed in, or read by the browser). */
	public function ajax_update() {
		$this->guard();
		$name = $this->post( 'name' );
		$consent = '' === (string) $this->post( 'consent' ) ? false : $this->posted_consent();
		$done    = $this->plugin->hubspot->update( sanitize_key( (string) $this->post( 'id' ) ), $this->posted_fields(), '' === $name ? null : $name, $consent );
		if ( is_wp_error( $done ) ) {
			wp_send_json_error( array( 'message' => $done->get_error_message() ) );
		}
		wp_send_json_success( array( 'updated' => true ) );
	}

	public function ajax_delete() {
		$this->guard();
		$done = $this->plugin->hubspot->delete( sanitize_key( (string) $this->post( 'id' ) ) );
		if ( is_wp_error( $done ) ) {
			wp_send_json_error( array( 'message' => $done->get_error_message() ) );
		}
		wp_send_json_success( array( 'deleted' => true ) );
	}

	public function ajax_fixclass() {
		$this->guard();
		$bridge = $this->plugin->hubspot->get( sanitize_key( (string) $this->post( 'id' ) ) );
		$done   = $bridge ? $this->plugin->hubspot->ensure_class( $bridge ) : new \WP_Error( 'x', __( 'That bridge no longer exists.', 'nori-for-elementor' ) );
		if ( is_wp_error( $done ) ) {
			wp_send_json_error( array( 'message' => $done->get_error_message() ) );
		}
		wp_send_json_success( array( 'fixed' => true ) );
	}

	/* ------------------------------------------------------------------
	 * Panel
	 * --------------------------------------------------------------- */

	/**
	 * Everything the admin app shows for the HubSpot Bridge, with its sentences already written.
	 *
	 * @return array{nonce: string, ajax: string, atomicForms: bool, bridges: array[]}
	 */
	public function app_state() {
		$hs     = $this->plugin->hubspot;
		$atomic = Plugin::atomic_available();
		$out    = array();
		foreach ( $hs->all() as $bridge ) {
			$in_el    = $atomic && $hs->class_in_elementor( $bridge['id'] );
			$rows     = array_merge( $bridge['fields'], HubSpot_Bridges::consent_fields( $bridge['consent'] ?? null ) );
			$required = array_filter( $rows, fn( $f ) => $f['required'] && ! $f['hidden'] );
			$pages    = array();
			foreach ( $in_el ? $hs->check_pages( $bridge ) : array() as $page ) {
				foreach ( $page['forms'] as $form ) {
					$ok      = ! $form['missing_required'];
					$pages[] = array(
						'title' => '' !== $page['title'] ? $page['title'] : '#' . $page['post_id'],
						'edit'  => $page['edit'],
						'ok'    => $ok,
						/* translators: %d: number of matched fields. */
						'text'  => $ok ? sprintf( _n( 'All required fields found (%d field matched).', 'All required fields found (%d fields matched).', count( $form['matched'] ), 'nori-for-elementor' ), count( $form['matched'] ) )
							/* translators: %s: list of HubSpot field names. */
							: sprintf( __( 'Missing required: %s. HubSpot will reject every entry from this form until it has an input with that ID (select the input, Settings › ID).', 'nori-for-elementor' ), implode( ', ', $form['missing_required'] ) ),
					);
				}
			}
			$auto    = array();
			$blocked = array();
			foreach ( $bridge['fields'] as $f ) {
				if ( $f['hidden'] ) {
					$auto[] = $f['name'] . ( '' !== ( $f['default'] ?? '' ) ? ' = ' . $f['default'] : '' );
				}
				if ( 'file' === $f['type'] && $f['required'] && ! $f['hidden'] ) {
					$blocked[] = $f['name'];
				}
			}
			$c      = $bridge['consent'] ?? null;
			$notice = $c ? ( 'li' === $c['mode'] ? $c['text'] : ( $c['implicit'] ? $c['processing'] : '' ) ) : '';
			$out[]  = array(
				'id'       => $bridge['id'],
				'name'     => $bridge['name'],
				'portal'   => $bridge['portal'],
				'form'     => $bridge['form'],
				'region'   => $bridge['region'],
				'class'    => HubSpot_Bridges::class_label( $bridge['id'] ),
				'inEl'     => $in_el,
				'fields'   => $bridge['fields'],
				'consent'  => $c,
				'required' => count( $required ),
				'rows'     => array_map(
					function ( $f ) {
						return array(
							'name'   => $f['name'],
							'label'  => $f['label'],
							'atomic' => $this->atomic_field( $f ),
							'need'   => $f['hidden'] ? ( '' !== ( $f['default'] ?? '' ) ? 'auto' : 'url' ) : ( $f['required'] ? 'required' : 'optional' ),
						);
					},
					$rows
				),
				/* translators: %s: list of hidden HubSpot fields. */
				'hidden'   => $auto ? sprintf( __( 'Hidden fields are filled in for you, no input needed: preset values are sent as set in HubSpot, and tracking fields (like utm_source or gclid) take their value from the page address when it has one. %s', 'nori-for-elementor' ), implode( ', ', $auto ) ) : '',
				/* translators: %s: list of HubSpot field names. */
				'blocked'  => $blocked ? sprintf( __( 'This HubSpot form requires a file upload (%s), which HubSpot doesn\'t accept through the bridge. Make that field optional in HubSpot, or the bridge can\'t send this form.', 'nori-for-elementor' ), implode( ', ', $blocked ) ) : '',
				'notice'   => (string) $notice,
				'pages'    => $pages,
				'report'   => $this->report_lines( $hs->report( $bridge['id'] ) ),
			);
		}
		return array(
			'nonce'       => wp_create_nonce( self::NONCE ),
			'ajax'        => admin_url( 'admin-ajax.php' ),
			'atomicForms' => HubSpot_Bridges::atomic_forms_available(),
			'bridges'     => $out,
		);
	}

	/** The last-submission report as sentences: [ [ ok, text ], ... ]. */
	private function report_lines( $report ) {
		if ( ! $report ) {
			return array( array( null, __( 'No test entries yet. Send one from your live page while logged in and the result shows up here. Visitors\' entries go to HubSpot as usual and aren\'t recorded here.', 'nori-for-elementor' ) ) );
		}
		$ago = human_time_diff( (int) $report['time'], time() );
		if ( $report['ok'] ) {
			/* translators: %s: time ago, like "5 mins". */
			$out  = array( array( true, sprintf( __( 'Last test entry was accepted by HubSpot %s ago.', 'nori-for-elementor' ), $ago ) ) );
			$left = array();
			foreach ( (array) ( $report['notes'] ?? array() ) as $n ) {
				if ( 'FIELD_NOT_IN_FORM_DEFINITION' === $n['type'] && preg_match( '/Left out ([A-Za-z0-9_.\-]+)/', $n['message'], $m ) ) {
					$left[] = $m[1];
				}
			}
			if ( $left ) {
				/* translators: %s: list of field names. */
				$out[] = array( false, sprintf( __( 'HubSpot\'s form has no field named %s, so the bridge left it out and sent the entry anyway. Use Edit fields to remove it, or check its HubSpot internal name.', 'nori-for-elementor' ), implode( ', ', $left ) ) );
			}
			return $out;
		}
		$bits = array();
		foreach ( (array) $report['errors'] as $e ) {
			$bits[] = trim( $e['type'] . ': ' . $e['message'], ': ' );
		}
		/* translators: 1: time ago, 2: error details. */
		$out = array( array( false, sprintf( __( 'Last test entry was rejected %1$s ago. %2$s', 'nori-for-elementor' ), $ago, implode( ' · ', $bits ) ) ) );
		if ( ! empty( $report['last_ok'] ) ) {
			/* translators: %s: time ago. */
			$out[] = array( null, sprintf( __( 'The last accepted entry was %s ago.', 'nori-for-elementor' ), human_time_diff( (int) $report['last_ok'], time() ) ) );
		}
		return $out;
	}

	/** Which Atomic form field holds a HubSpot field of this type. */
	private function atomic_field( array $f ) {
		$map = array(
			'text'            => __( 'Input (type Text)', 'nori-for-elementor' ),
			'email'           => __( 'Input (type Email)', 'nori-for-elementor' ),
			'phonenumber'     => __( 'Input (type Tel or Text)', 'nori-for-elementor' ),
			'number'          => __( 'Input (type Number)', 'nori-for-elementor' ),
			'textarea'        => __( 'Textarea', 'nori-for-elementor' ),
			'select'          => __( 'Select', 'nori-for-elementor' ),
			'radio'           => __( 'Radio buttons (same name, values as below)', 'nori-for-elementor' ),
			'checkbox'        => __( 'Checkboxes (same name, values as below)', 'nori-for-elementor' ),
			'booleancheckbox' => __( 'Checkbox', 'nori-for-elementor' ),
			'date'            => __( 'Date picker', 'nori-for-elementor' ),
			'file'            => __( 'Not supported: HubSpot doesn\'t take files through the bridge', 'nori-for-elementor' ),
			'hidden'          => __( 'None needed (sent for you)', 'nori-for-elementor' ),
			'consent'         => __( 'Checkbox, with the HubSpot text as its label', 'nori-for-elementor' ),
		);
		$label = $map[ $f['type'] ] ?? $map['text'];
		if ( $f['options'] && in_array( $f['type'], array( 'select', 'radio', 'checkbox' ), true ) ) {
			/* translators: %s: list of option values. */
			$label .= '. ' . sprintf( __( 'Option values: %s', 'nori-for-elementor' ), implode( ', ', array_slice( wp_list_pluck( $f['options'], 'value' ), 0, 8 ) ) . ( count( $f['options'] ) > 8 ? '…' : '' ) );
		}
		return $label;
	}
}
