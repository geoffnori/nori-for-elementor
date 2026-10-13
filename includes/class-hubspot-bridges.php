<?php
/**
 * HubSpot Bridge: send Elementor Atomic form entries to a HubSpot form.
 *
 * A bridge links one HubSpot form (portal id, form id, region) to a unique Elementor class,
 * hs-bridge-{id}-np. Put the class on an Atomic form and, on submit, the browser sends the
 * form's fields to HubSpot's public Forms API first. Only when HubSpot accepts them does
 * Elementor run its own actions (email, collect submissions) and show its success message.
 *
 * HubSpot rejects a submission that is missing a required field or has a field the HubSpot
 * form doesn't define, so every bridge keeps the HubSpot form's field list. Nori reads it
 * from the ids in the embed code, the same way HubSpot's own embed script does.
 *
 * @package Nori_For_Elementor
 */

namespace Nori\Elementor;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HubSpot_Bridges {

	const OPTION  = 'nori_fe_hubspot_bridges';
	/** Error types a visitor's browser may report (HubSpot's, plus the bridge's own). Anything else is stored as OTHER. */
	const REPORT_TYPES = array( 'REQUIRED_FIELD', 'FIELD_NOT_IN_FORM_DEFINITION', 'INVALID_EMAIL', 'BLOCKED_EMAIL', 'INVALID_NUMBER', 'NUMBER_OUT_OF_RANGE', 'INVALID_PHONE', 'INPUT_TOO_LARGE', 'MAX_NUMBER_OF_SUBMITTED_VALUES_EXCEEDED', 'INVALID_LEGAL_OPTION_FORMAT', 'INVALID_METADATA', 'FORM_NOT_FOUND', 'HTTP', 'NETWORK' );
	const REPORTS = 'nori_fe_hubspot_reports';
	const MODULE  = 'hubspot-bridge';

	/** Atomic form field widgets that hold a value. */
	const FIELD_WIDGETS = array( 'e-form-input', 'e-form-textarea', 'e-form-select', 'e-form-checkbox', 'e-form-radio-button', 'e-form-date-picker', 'e-form-time-picker', 'e-form-file-upload' );

	/** HubSpot field types Nori knows how to fill. */
	const TYPES = array( 'text', 'textarea', 'number', 'select', 'radio', 'checkbox', 'booleancheckbox', 'date', 'file', 'phonenumber', 'email', 'hidden' );

	/** @var Elementor_Api */
	private $api;

	public function __construct( Elementor_Api $api ) {
		$this->api = $api;
	}

	/* ------------------------------------------------------------------
	 * Embed code and HubSpot form definition
	 * --------------------------------------------------------------- */

	/**
	 * Find the portal id, form id and region in anything HubSpot gives you: the classic embed
	 * (hbspt.forms.create), the newer embed (hs-form-frame), or the form's editor URL.
	 *
	 * @return array{portal: string, form: string, region: string}|WP_Error
	 */
	public static function parse_embed( $code ) {
		$code   = (string) $code;
		$portal = '';
		$form   = '';
		$region = '';

		if ( preg_match( '/portal[_-]?id["\']?\s*[:=]\s*["\']?(\d{3,12})/i', $code, $m ) ) {
			$portal = $m[1];
		} elseif ( preg_match( '#/forms/embed/(\d{3,12})\.js#', $code, $m ) ) {
			$portal = $m[1];
		} elseif ( preg_match( '#hubspot\.com/forms/(\d{3,12})/#', $code, $m ) ) {
			$portal = $m[1];
		}
		if ( preg_match( '/form[_-]?id["\']?\s*[:=]\s*["\']?([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})/i', $code, $m ) ) {
			$form = strtolower( $m[1] );
		} elseif ( preg_match( '#hubspot\.com/forms/\d{3,12}/(?:editor/)?([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})#i', $code, $m ) ) {
			$form = strtolower( $m[1] );
		}
		if ( preg_match( '/region["\']?\s*[:=]\s*["\']([a-z]{2}\d{1,2})["\']/i', $code, $m ) ) {
			$region = strtolower( $m[1] );
		} elseif ( preg_match( '#(?:js|forms|api|app)-([a-z]{2}\d{1,2})\.(?:hsforms|hubspot)#i', $code, $m ) ) {
			$region = strtolower( $m[1] );
		}

		if ( '' === $portal || '' === $form ) {
			return new WP_Error(
				'nori_fe_hs_embed',
				__( 'Nori couldn\'t find a HubSpot portal id and form id in that code. In HubSpot, open the form, choose Share > Embed code, copy all of it and paste it here. The form\'s editor URL works too.', 'nori-for-elementor' )
			);
		}
		return array(
			'portal' => $portal,
			'form'   => $form,
			'region' => '' === $region ? 'na1' : $region,
		);
	}

	/** Where HubSpot serves a portal's forms: the EU data center has its own hosts. */
	public static function hosts( $region ) {
		$region = sanitize_key( (string) $region );
		if ( '' === $region || 'na1' === $region ) {
			return array(
				'forms' => 'https://forms.hsforms.com',
				'api'   => 'https://api.hsforms.com',
			);
		}
		return array(
			'forms' => 'https://forms-' . $region . '.hsforms.com',
			'api'   => 'https://api-' . $region . '.hsforms.com',
		);
	}

	/** The public form definition HubSpot's classic embed loads (older forms; no API key needed). */
	public static function definition_url( $portal, $form, $region ) {
		return self::hosts( $region )['forms'] . '/embed/v3/form/' . rawurlencode( $portal ) . '/' . rawurlencode( $form ) . '/json';
	}

	/**
	 * Where HubSpot publishes a form's definition, newest embed first: forms made in HubSpot's
	 * newer editor only answer on /embed/v4/render-definition, older forms only on /embed/v3.
	 *
	 * @return string[]
	 */
	public static function definition_urls( $portal, $form, $region ) {
		return array(
			self::hosts( $region )['forms'] . '/embed/v4/render-definition/' . rawurlencode( $portal ) . '/' . rawurlencode( $form ),
			self::definition_url( $portal, $form, $region ),
		);
	}

	/** HubSpot's public Forms API endpoint for one form. */
	public static function submit_url( $portal, $form, $region ) {
		return self::hosts( $region )['api'] . '/submissions/v3/integration/submit/' . rawurlencode( $portal ) . '/' . rawurlencode( $form );
	}

	/**
	 * Download the HubSpot form's definition and read its fields.
	 *
	 * @return array{name: string, fields: array}|WP_Error
	 */
	public function fetch_definition( $portal, $form, $region ) {
		$last = null;
		foreach ( self::definition_urls( $portal, $form, $region ) as $url ) {
			$parsed = $this->fetch_one( $url );
			if ( ! is_wp_error( $parsed ) ) {
				return $parsed;
			}
			$last = $parsed;
		}
		return $last;
	}

	/** @return array|WP_Error */
	private function fetch_one( $url ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 10,
				// HubSpot serves this to browsers; plain server requests are sometimes refused (403).
				'user-agent' => 'Mozilla/5.0 (compatible; NoriForElementor/' . NORI_FE_VERSION . '; +' . home_url( '/' ) . ')',
				'headers'    => array(
					'Accept'  => 'application/json',
					'Referer' => home_url( '/' ),
					'Origin'  => untrailingslashit( home_url() ),
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'nori_fe_hs_fetch', $response->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( 200 !== $code || ! is_array( $data ) ) {
			/* translators: %d: HTTP status code. */
			return new WP_Error( 'nori_fe_hs_fetch', sprintf( __( 'HubSpot answered with status %d.', 'nori-for-elementor' ), $code ) );
		}
		$parsed = self::parse_definition( $data );
		if ( ! $parsed['fields'] ) {
			return new WP_Error( 'nori_fe_hs_fields', __( 'HubSpot sent the form, but Nori found no fields in it.', 'nori-for-elementor' ) );
		}
		return $parsed;
	}

	/**
	 * Read field names, labels, types and "required" from a HubSpot form definition. The
	 * layout differs between HubSpot's form versions, so this looks for field-shaped objects
	 * anywhere in the data instead of relying on one exact structure.
	 *
	 * @return array{name: string, fields: array}
	 */
	public static function parse_definition( array $data ) {
		$fields  = array();
		$consent = null;
		$v4types = array(
			'email'              => 'email',
			'singlelinetext'     => 'text',
			'multilinetext'      => 'textarea',
			'number'             => 'number',
			'phone'              => 'phonenumber',
			'dropdownselect'     => 'select',
			'radio'              => 'radio',
			'multiplecheckboxes' => 'checkbox',
			'singlecheckbox'     => 'booleancheckbox',
			'datepicker'         => 'date',
			'date'               => 'date',
			'file'               => 'file',
			'hidden'             => 'hidden',
		);
		$walk    = function ( $node ) use ( &$walk, &$fields, &$consent, $v4types ) {
			if ( ! is_array( $node ) ) {
				return;
			}
			// Newer HubSpot forms (embed v4): a field is a module with propertyReference "0-1/email".
			if ( isset( $node['propertyReference'] ) && is_string( $node['propertyReference'] ) && preg_match( '#^(\d+-\d+)/([A-Za-z0-9_.\-]{1,100})$#', $node['propertyReference'], $ref ) && ! isset( $fields[ $ref[2] ] ) ) {
				$type    = $v4types[ strtolower( (string) ( $node['type'] ?? '' ) ) ] ?? 'text';
				$options = array();
				foreach ( (array) ( $node['options'] ?? array() ) as $opt ) {
					if ( is_array( $opt ) && isset( $opt['value'] ) && is_scalar( $opt['value'] ) ) {
						$options[] = array(
							'value' => (string) $opt['value'],
							'label' => (string) ( $opt['label'] ?? $opt['value'] ),
						);
					}
				}
				$values             = isset( $node['values'] ) && is_array( $node['values'] ) ? implode( ';', array_filter( $node['values'], 'is_scalar' ) ) : ( is_scalar( $node['defaultValue'] ?? null ) ? (string) $node['defaultValue'] : '' );
				$fields[ $ref[2] ] = array(
					'name'     => $ref[2],
					'default'  => $values,
					'label'    => (string) ( $node['label'] ?? $ref[2] ),
					// A field HubSpot hides by a rule isn't required while hidden.
					'required' => ! empty( $node['required'] ) && empty( $node['conditionallyHidden'] ),
					'hidden'   => 'hidden' === $type,
					'type'     => $type,
					'object'   => $ref[1],
					'options'  => $options,
				);
			}
			// Newer forms: the privacy (GDPR) settings HubSpot's embed sends with every entry.
			if ( null === $consent && 'dataprivacy' === strtolower( (string) ( $node['type'] ?? '' ) ) ) {
				$consent = $node;
			}
			$is_field = isset( $node['name'] ) && is_string( $node['name'] )
				&& ( isset( $node['fieldType'] ) || isset( $node['required'] ) || isset( $node['objectTypeId'] ) );
			if ( $is_field && preg_match( '/^[A-Za-z0-9_.\-]{1,100}$/', $node['name'] ) && ! isset( $fields[ $node['name'] ] ) ) {
				$type    = strtolower( (string) ( $node['fieldType'] ?? $node['type'] ?? 'text' ) );
				$options = array();
				foreach ( (array) ( $node['options'] ?? array() ) as $opt ) {
					if ( is_array( $opt ) && isset( $opt['value'] ) && is_scalar( $opt['value'] ) ) {
						$options[] = array(
							'value' => (string) $opt['value'],
							'label' => (string) ( $opt['label'] ?? $opt['value'] ),
						);
					}
				}
				$default = $node['defaultValue'] ?? ( isset( $node['defaultValues'] ) && is_array( $node['defaultValues'] ) ? implode( ';', array_filter( $node['defaultValues'], 'is_scalar' ) ) : '' );
				$fields[ $node['name'] ] = array(
					'name'     => $node['name'],
					'default'  => is_scalar( $default ) ? (string) $default : '',
					'label'    => (string) ( $node['label'] ?? $node['name'] ),
					'required' => ! empty( $node['required'] ),
					'hidden'   => ! empty( $node['hidden'] ) || 'hidden' === $type,
					'type'     => $type,
					'object'   => isset( $node['objectTypeId'] ) && is_string( $node['objectTypeId'] ) ? $node['objectTypeId'] : '',
					'options'  => $options,
				);
			}
			foreach ( $node as $child ) {
				if ( is_array( $child ) ) {
					$walk( $child );
				}
			}
		};
		$walk( $data );

		$name = '';
		foreach ( array( $data['form']['name'] ?? null, $data['name'] ?? null ) as $candidate ) {
			if ( is_string( $candidate ) && '' !== trim( $candidate ) ) {
				$name = $candidate;
				break;
			}
		}
		return array(
			'name'    => $name,
			'fields'  => self::sanitize_fields( array_values( $fields ) ),
			'consent' => self::sanitize_consent( $consent ),
		);
	}

	/**
	 * A consent form's checkboxes as field rows (consent-to-process, consent-<subscription id>), so the
	 * bridge card and the page check list them like any other input the Atomic form needs.
	 *
	 * @param array|null $c Sanitized consent settings.
	 * @return array
	 */
	public static function consent_fields( $c ) {
		if ( ! is_array( $c ) || 'consent' !== ( $c['mode'] ?? '' ) ) {
			return array();
		}
		$rows = array();
		if ( empty( $c['implicit'] ) ) {
			$rows[] = array( 'name' => 'consent-to-process', 'label' => '' !== (string) ( $c['processing'] ?? '' ) ? (string) $c['processing'] : __( 'I agree to let this site store and process my personal data.', 'nori-for-elementor' ), 'required' => true, 'hidden' => false, 'type' => 'consent', 'options' => array(), 'default' => '' );
		}
		foreach ( (array) ( $c['subs'] ?? array() ) as $sub ) {
			$rows[] = array( 'name' => 'consent-' . $sub['id'], 'label' => '' !== (string) $sub['label'] ? (string) $sub['label'] : __( 'I agree to receive other communications.', 'nori-for-elementor' ), 'required' => ! empty( $sub['required'] ), 'hidden' => false, 'type' => 'consent', 'options' => array(), 'default' => '' );
		}
		return $rows;
	}

	/**
	 * Keep what the submit API's legalConsentOptions needs from a form's privacy module:
	 * "li" (legitimate interest: HubSpot records it with no checkbox) or "consent" (checkboxes).
	 *
	 * @return array|null
	 */
	public static function sanitize_consent( $c ) {
		if ( ! is_array( $c ) ) {
			return null;
		}
		$text = function ( $v ) {
			return substr( sanitize_text_field( wp_strip_all_tags( (string) $v ) ), 0, 1200 );
		};
		if ( isset( $c['mode'] ) ) { // Already clean (stored or posted back from the admin screen).
			$mode = 'li' === $c['mode'] ? 'li' : 'consent';
			$subs = array();
			foreach ( array_slice( (array) ( $c['subs'] ?? array() ), 0, 20 ) as $sub ) {
				if ( is_array( $sub ) && isset( $sub['id'] ) && is_numeric( $sub['id'] ) ) {
					$subs[] = array(
						'id'       => (int) $sub['id'],
						'label'    => $text( $sub['label'] ?? '' ),
						'required' => ! empty( $sub['required'] ) && 'false' !== $sub['required'],
					);
				}
			}
			return array(
				'mode'      => $mode,
				'basis'     => in_array( $c['basis'] ?? '', array( 'LEAD', 'CUSTOMER' ), true ) ? $c['basis'] : 'LEAD',
				'implicit'  => ! empty( $c['implicit'] ) && 'false' !== $c['implicit'],
				'subs'      => $subs,
				'text'      => $text( $c['text'] ?? '' ),
				'processing' => $text( $c['processing'] ?? '' ),
			);
		}
		$subs = array();
		foreach ( (array) ( $c['communicationConsentCheckboxes'] ?? array() ) as $box ) {
			if ( is_array( $box ) && isset( $box['communicationTypeId'] ) ) {
				$subs[] = array(
					'id'       => $box['communicationTypeId'],
					'label'    => $box['label'] ?? '',
					'required' => ! empty( $box['required'] ),
				);
			}
		}
		if ( ! $subs ) {
			foreach ( (array) ( $c['legitimateInterestSubscriptionTypes'] ?? array() ) as $id ) {
				$subs[] = array(
					'id'       => $id,
					'label'    => '',
					'required' => false,
				);
			}
		}
		return self::sanitize_consent(
			array(
				'mode'       => ! empty( $c['isLegitimateInterest'] ) ? 'li' : 'consent',
				// HubSpot's legal basis for legitimate interest: a prospect (LEAD) or an existing customer.
				'basis'      => false !== strpos( (string) ( $c['legitimateInterestLegalBasis'] ?? '' ), 'CLIENT' ) ? 'CUSTOMER' : 'LEAD',
				'implicit'   => 'IMPLICIT' === strtoupper( (string) ( $c['processingConsentType'] ?? '' ) ),
				'subs'       => $subs,
				'text'       => ! empty( $c['isLegitimateInterest'] ) ? ( $c['privacyPolicyText'] ?? '' ) : ( $c['communicationConsentText'] ?? '' ),
				'processing' => $c['processingConsentText'] ?? ( $c['processingConsentCheckboxLabel'] ?? '' ),
			)
		);
	}

	/** Clean a field list coming from HubSpot or from the admin screen. */
	public static function sanitize_fields( array $raw ) {
		$out  = array();
		$seen = array();
		foreach ( $raw as $f ) {
			if ( ! is_array( $f ) ) {
				continue;
			}
			$name = trim( (string) ( $f['name'] ?? '' ) );
			if ( ! preg_match( '/^[A-Za-z0-9_.\-]{1,100}$/', $name ) || isset( $seen[ strtolower( $name ) ] ) ) {
				continue;
			}
			$seen[ strtolower( $name ) ] = true;
			$type                        = strtolower( (string) ( $f['type'] ?? 'text' ) );
			$options                     = array();
			foreach ( array_slice( (array) ( $f['options'] ?? array() ), 0, 200 ) as $opt ) {
				if ( is_array( $opt ) && isset( $opt['value'] ) ) {
					$options[] = array(
						'value' => sanitize_text_field( (string) $opt['value'] ),
						'label' => sanitize_text_field( (string) ( $opt['label'] ?? $opt['value'] ) ),
					);
				}
			}
			$out[] = array(
				'name'     => $name,
				'label'    => sanitize_text_field( (string) ( $f['label'] ?? $name ) ),
				'default'  => substr( sanitize_text_field( (string) ( $f['default'] ?? '' ) ), 0, 500 ),
				'required' => ! empty( $f['required'] ) && 'false' !== $f['required'],
				'hidden'   => ! empty( $f['hidden'] ) && 'false' !== $f['hidden'],
				'type'     => in_array( $type, self::TYPES, true ) ? $type : 'text',
				'object'   => preg_match( '/^\d+-\d+$/', (string) ( $f['object'] ?? '' ) ) ? (string) $f['object'] : '',
				'options'  => $options,
			);
			if ( count( $out ) >= 100 ) {
				break;
			}
		}
		return $out;
	}

	/* ------------------------------------------------------------------
	 * Bridges
	 * --------------------------------------------------------------- */

	/** @return array<string, array> Bridges keyed by id, oldest first. */
	public function all() {
		$all = get_option( self::OPTION, array() );
		return is_array( $all ) ? $all : array();
	}

	public function get( $id ) {
		return $this->all()[ $id ] ?? null;
	}

	public static function class_label( $id ) {
		return 'hs-bridge-' . $id . '-np';
	}

	/** Stable Elementor class id for a bridge, so an export imported elsewhere lines up. */
	public static function class_id( $id ) {
		return 'g-' . substr( md5( 'nori-fe:hs-bridge:' . $id ), 0, 7 );
	}

	/**
	 * Create a bridge and its Elementor class.
	 *
	 * @param array $in portal, form, region, name, fields, and optionally id (from an import).
	 * @return array|WP_Error The bridge.
	 */
	public function create( array $in ) {
		$portal = preg_replace( '/\D/', '', (string) ( $in['portal'] ?? '' ) );
		$form   = strtolower( (string) ( $in['form'] ?? '' ) );
		$region = sanitize_key( (string) ( $in['region'] ?? 'na1' ) );
		if ( '' === $portal || ! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $form ) ) {
			return new WP_Error( 'nori_fe_hs_ids', __( 'That doesn\'t look like a HubSpot portal id and form id.', 'nori-for-elementor' ) );
		}
		$fields = self::sanitize_fields( (array) ( $in['fields'] ?? array() ) );
		if ( ! $fields ) {
			return new WP_Error( 'nori_fe_hs_nofields', __( 'Add at least one HubSpot field (for example email) before creating the bridge.', 'nori-for-elementor' ) );
		}
		$all = $this->all();
		$id  = isset( $in['id'] ) && preg_match( '/^[a-z0-9]{5}$/', (string) $in['id'] ) ? (string) $in['id'] : '';
		while ( '' === $id || isset( $all[ $id ] ) ) {
			if ( '' !== $id && isset( $all[ $id ] ) && ! empty( $in['id'] ) ) {
				return $all[ $id ]; // Importing a bridge that already exists here.
			}
			$id = strtolower( wp_generate_password( 5, false, false ) );
			if ( ! preg_match( '/^[a-z0-9]{5}$/', $id ) ) {
				$id = '';
			}
		}
		$bridge     = array(
			'consent' => self::sanitize_consent( $in['consent'] ?? null ),
			'id'      => $id,
			'name'    => sanitize_text_field( (string) ( $in['name'] ?? '' ) ),
			'portal'  => $portal,
			'form'    => $form,
			'region'  => '' === $region ? 'na1' : $region,
			'fields'  => $fields,
			'created' => time(),
		);
		$all[ $id ] = $bridge;
		update_option( self::OPTION, $all, false );
		$this->ensure_class( $bridge );
		return $bridge;
	}

	/** Replace a bridge's field list (and name), for example after reading it again from HubSpot. */
	public function update( $id, array $fields, $name = null, $consent = false ) {
		$all = $this->all();
		if ( ! isset( $all[ $id ] ) ) {
			return new WP_Error( 'nori_fe_hs_missing', __( 'That bridge no longer exists.', 'nori-for-elementor' ) );
		}
		$fields = self::sanitize_fields( $fields );
		if ( ! $fields ) {
			return new WP_Error( 'nori_fe_hs_nofields', __( 'A bridge needs at least one HubSpot field.', 'nori-for-elementor' ) );
		}
		$all[ $id ]['fields'] = $fields;
		if ( false !== $consent ) {
			$all[ $id ]['consent'] = self::sanitize_consent( $consent );
		}
		if ( null !== $name ) {
			$all[ $id ]['name'] = sanitize_text_field( (string) $name );
		}
		update_option( self::OPTION, $all, false );
		return $all[ $id ];
	}

	/** Delete a bridge and its Elementor class (Elementor removes the class from every element). */
	public function delete( $id ) {
		$all = $this->all();
		if ( ! isset( $all[ $id ] ) ) {
			return true;
		}
		if ( Plugin::atomic_available() && $this->class_in_elementor( $id ) ) {
			$list = $this->api->list_classes();
			if ( ! is_wp_error( $list ) ) {
				$done = $this->api->delete_classes( array( self::class_id( $id ) ), wp_list_pluck( $list, 'id' ) );
				if ( is_wp_error( $done ) ) {
					return $done;
				}
			}
		}
		unset( $all[ $id ] );
		update_option( self::OPTION, $all, false );
		$reports = get_option( self::REPORTS, array() );
		if ( is_array( $reports ) && isset( $reports[ $id ] ) ) {
			unset( $reports[ $id ] );
			update_option( self::REPORTS, $reports, false );
		}
		return true;
	}

	public function class_in_elementor( $id ) {
		$list = $this->api->list_classes();
		if ( is_wp_error( $list ) ) {
			return false;
		}
		return in_array( self::class_id( $id ), wp_list_pluck( $list, 'id' ), true );
	}

	/**
	 * Make sure the bridge's class exists in Elementor. It has no styles: it only marks the form.
	 *
	 * @return true|WP_Error
	 */
	public function ensure_class( array $bridge ) {
		if ( ! Plugin::atomic_available() ) {
			return new WP_Error( 'nori_fe_hs_atomic', __( 'Elementor\'s Atomic editor isn\'t active, so the bridge class can\'t be added yet.', 'nori-for-elementor' ) );
		}
		if ( ! current_user_can( 'elementor_global_classes_update_class' ) ) {
			return new WP_Error( 'nori_fe_no_class_cap', __( 'Your account can\'t edit Elementor global classes yet. Open any Elementor screen in WP Admin once, then try again.', 'nori-for-elementor' ) );
		}
		$list = $this->api->list_classes();
		if ( is_wp_error( $list ) ) {
			return $list;
		}
		$cid   = self::class_id( $bridge['id'] );
		$order = wp_list_pluck( $list, 'id' );
		if ( in_array( $cid, $order, true ) ) {
			return true;
		}
		$item = array(
			'id'       => $cid,
			'label'    => self::class_label( $bridge['id'] ),
			'type'     => 'class',
			'variants' => array(
				array(
					'meta'       => array(
						'breakpoint' => 'desktop',
						'state'      => null,
					),
					'props'      => array(),
					'custom_css' => null,
				),
			),
		);
		return $this->api->put_classes( array( $cid => $item ), array( $cid ), array(), $order );
	}

	/* ------------------------------------------------------------------
	 * Matching HubSpot fields to Atomic form inputs
	 * --------------------------------------------------------------- */

	private static function norm( $s ) {
		return preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $s ) );
	}

	/**
	 * How well one input identifier (its ID or name) matches a HubSpot field name.
	 * 3: the same name. 2: the same ID. 1: the ID ends with the field name (e-form-first-name
	 * matches firstname, contact-email matches email). 0: no match. The front-end script uses
	 * the same rule.
	 */
	public static function match_score( $field, $identifier, $is_name ) {
		$f  = self::norm( $field );
		$id = (string) $identifier;
		if ( '' === $f || '' === $id ) {
			return 0;
		}
		if ( self::norm( $id ) === $f ) {
			return $is_name ? 3 : 2;
		}
		$parts = array_values( array_filter( preg_split( '/[^a-z0-9]+/', strtolower( $id ) ) ) );
		$tail  = '';
		for ( $i = count( $parts ) - 1; $i >= 0; $i-- ) {
			$tail = $parts[ $i ] . $tail;
			if ( $tail === $f ) {
				return 1;
			}
			if ( strlen( $tail ) >= strlen( $f ) ) {
				break;
			}
		}
		return 0;
	}

	/**
	 * Match HubSpot fields to inputs. Longer field names claim inputs first, so companyname
	 * takes company-name before name can.
	 *
	 * @param array $fields Bridge fields.
	 * @param array $inputs List of [ id => string, name => string, label => string ].
	 * @return array{matched: array<string,int>, missing: string[], missing_required: string[], unused: int[]}
	 */
	public static function match( array $fields, array $inputs ) {
		$order = $fields;
		usort( $order, fn( $a, $b ) => strlen( self::norm( $b['name'] ) ) <=> strlen( self::norm( $a['name'] ) ) );
		$taken   = array();
		$matched = array();
		foreach ( $order as $field ) {
			$best  = -1;
			$score = 0;
			foreach ( $inputs as $i => $input ) {
				if ( isset( $taken[ $i ] ) ) {
					continue;
				}
				$s = max( self::match_score( $field['name'], $input['name'] ?? '', true ), self::match_score( $field['name'], $input['id'] ?? '', false ) );
				if ( $s > $score ) {
					$score = $s;
					$best  = $i;
				}
			}
			if ( $best >= 0 ) {
				$taken[ $best ]            = true;
				$matched[ $field['name'] ] = $best;
			}
		}
		$missing  = array();
		$required = array();
		foreach ( $fields as $field ) {
			if ( ! isset( $matched[ $field['name'] ] ) && empty( $field['hidden'] ) ) {
				$missing[] = $field['name'];
				if ( ! empty( $field['required'] ) ) {
					$required[] = $field['name'];
				}
			}
		}
		return array(
			'matched'          => $matched,
			'missing'          => $missing,
			'missing_required' => $required,
			'unused'           => array_values( array_diff( array_keys( $inputs ), array_keys( $taken ) ) ),
		);
	}

	/**
	 * Check every page that uses a bridge's class: are all required HubSpot fields in the form?
	 *
	 * @return array[] One row per page: [ post_id, title, edit, forms => [ match() results + inputs ] ].
	 */
	public function check_pages( array $bridge ) {
		$rows = array();
		if ( ! class_exists( '\\Elementor\\Modules\\GlobalClasses\\Global_Classes_Relations' ) ) {
			return $rows;
		}
		$cid       = self::class_id( $bridge['id'] );
		$relations = new \Elementor\Modules\GlobalClasses\Global_Classes_Relations();
		foreach ( array_unique( array_map( 'intval', (array) $relations->get_posts_by_style( $cid ) ) ) as $post_id ) {
			$status = get_post_status( $post_id );
			if ( ! $status || 'trash' === $status ) {
				continue;
			}
			$data = json_decode( (string) get_post_meta( $post_id, '_elementor_data', true ), true );
			if ( ! is_array( $data ) ) {
				continue;
			}
			$forms = array();
			foreach ( self::find_with_class( $data, $cid ) as $node ) {
				$inputs  = self::collect_inputs( $node );
				$forms[] = array_merge( self::match( array_merge( $bridge['fields'], self::consent_fields( $bridge['consent'] ?? null ) ), $inputs ), array( 'inputs' => $inputs ) );
			}
			if ( ! $forms ) {
				continue;
			}
			$rows[] = array(
				'post_id' => $post_id,
				'title'   => get_the_title( $post_id ),
				'edit'    => add_query_arg(
					array(
						'post'   => $post_id,
						'action' => 'elementor',
					),
					admin_url( 'post.php' )
				),
				'forms'   => $forms,
			);
		}
		return $rows;
	}

	private static function setting_value( $v ) {
		if ( is_array( $v ) && array_key_exists( 'value', $v ) ) {
			$v = $v['value'];
		}
		return is_scalar( $v ) ? (string) $v : '';
	}

	/** Elements (with their children) that carry the class. */
	private static function find_with_class( array $elements, $cid ) {
		$found = array();
		foreach ( $elements as $el ) {
			if ( ! is_array( $el ) ) {
				continue;
			}
			$classes = $el['settings']['classes']['value'] ?? ( $el['settings']['classes'] ?? array() );
			if ( is_array( $classes ) && in_array( $cid, $classes, true ) ) {
				$found[] = $el;
				continue;
			}
			if ( ! empty( $el['elements'] ) && is_array( $el['elements'] ) ) {
				$found = array_merge( $found, self::find_with_class( $el['elements'], $cid ) );
			}
		}
		return $found;
	}

	/** The Atomic form fields inside an element: their ID and any name-like setting. */
	private static function collect_inputs( array $node ) {
		$inputs = array();
		$walk   = function ( $el ) use ( &$walk, &$inputs ) {
			if ( ! is_array( $el ) ) {
				return;
			}
			$type = (string) ( $el['widgetType'] ?? $el['elType'] ?? '' );
			if ( in_array( $type, self::FIELD_WIDGETS, true ) ) {
				$settings = (array) ( $el['settings'] ?? array() );
				$name     = '';
				foreach ( $settings as $k => $v ) {
					if ( is_string( $k ) && preg_match( '/(^|[-_])name$/', $k ) && 'form-name' !== $k ) {
						$name = self::setting_value( $v );
						if ( '' !== $name ) {
							break;
						}
					}
				}
				$inputs[] = array(
					'id'     => self::setting_value( $settings['_cssid'] ?? '' ),
					'name'   => $name,
					'widget' => $type,
				);
			}
			foreach ( (array) ( $el['elements'] ?? array() ) as $child ) {
				$walk( $child );
			}
		};
		$walk( $node );
		return $inputs;
	}

	/* ------------------------------------------------------------------
	 * Front end and reports
	 * --------------------------------------------------------------- */

	/** What the front-end script needs: no secrets, only public HubSpot ids and field names. */
	public function js_config() {
		$out = array();
		foreach ( $this->all() as $bridge ) {
			$out[] = array(
				'id'     => $bridge['id'],
				'cls'    => self::class_label( $bridge['id'] ),
				'url'    => self::submit_url( $bridge['portal'], $bridge['form'], $bridge['region'] ),
				'c'      => $bridge['consent'] ?? null,
				'fields' => array_map(
					fn( $f ) => array(
						'n' => $f['name'],
						'r' => (bool) $f['required'],
						'h' => (bool) $f['hidden'],
						't' => $f['type'],
						'o' => $f['object'],
						'd' => $f['default'] ?? '',
					),
					$bridge['fields']
				),
			);
		}
		return $out;
	}

	/**
	 * Remember the last result the browser got from HubSpot for a bridge, to show in WP Admin.
	 * Public endpoint: only known bridges, short cleaned strings, and at most one write every
	 * 30 seconds per bridge.
	 */
	public function record_report( $id, $ok, array $errors ) {
		if ( ! $this->get( $id ) ) {
			return false;
		}
		// At most 10 reports per visitor per bridge every 10 minutes, so one visitor can't keep
		// overwriting the status (for example with fake "ok" reports that would hide real failures).
		$visitor = 'nori_fe_hsr_' . md5( wp_salt( 'nonce' ) . '|' . Form_Guard::visitor_ip() . '|' . $id );
		$sent    = (int) get_transient( $visitor );
		if ( $sent >= 10 ) {
			return false;
		}
		set_transient( $visitor, $sent + 1, 600 );
		$reports = get_option( self::REPORTS, array() );
		$reports = is_array( $reports ) ? $reports : array();
		$last    = $reports[ $id ] ?? array();
		// "ok" reports are throttled per bridge; error reports always get through.
		if ( $ok && ! empty( $last['time'] ) && time() - (int) $last['time'] < 30 ) {
			return false;
		}
		$clean = array();
		foreach ( array_slice( $errors, 0, 5 ) as $e ) {
			if ( ! is_array( $e ) ) {
				continue;
			}
			$message = sanitize_text_field( (string) ( $e['message'] ?? '' ) );
			$message = preg_replace( '/[^\s@\'"]+@[^\s@\'"]+/', '[email]', $message ); // Never keep what visitors typed.
			$type    = substr( preg_replace( '/[^A-Z_]/', '', strtoupper( (string) ( $e['errorType'] ?? $e['type'] ?? '' ) ) ), 0, 40 );
			$clean[] = array(
				'type'    => in_array( $type, self::REPORT_TYPES, true ) ? $type : 'OTHER',
				'message' => substr( $message, 0, 200 ),
			);
		}
		$row = array(
			'time'   => time(),
			'ok'     => (bool) $ok,
			'errors' => $ok ? array() : $clean,
			'notes'  => $ok ? $clean : array(), // Accepted, but a field was left out (not on the HubSpot form).
		);
		if ( $ok ) {
			$row['last_ok'] = time();
		} else {
			$row['last_ok'] = $last['last_ok'] ?? 0;
		}
		$reports[ $id ] = $row;
		update_option( self::REPORTS, $reports, false );
		return true;
	}

	public function report( $id ) {
		$reports = get_option( self::REPORTS, array() );
		return is_array( $reports ) ? ( $reports[ $id ] ?? null ) : null;
	}

	/** True when the site can show Atomic forms (they need Elementor Pro). */
	public static function atomic_forms_available() {
		if ( ! class_exists( '\\Elementor\\Utils' ) || ! \Elementor\Utils::has_pro() ) {
			return false;
		}
		$experiments = \Elementor\Plugin::$instance->experiments ?? null;
		return ! $experiments || ! method_exists( $experiments, 'is_feature_active' ) || $experiments->is_feature_active( 'e_pro_atomic_form' );
	}
}
