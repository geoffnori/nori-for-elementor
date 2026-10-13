<?php
/**
 * Talks to Elementor's own REST endpoints from inside WordPress (no HTTP round trip).
 *
 * Going through Elementor's endpoints instead of writing its database records
 * directly means Elementor validates every class, regenerates CSS, and fires
 * its own hooks, exactly as when you edit a class in the editor.
 *
 * @package Nori_For_Elementor
 */

namespace Nori\Elementor;

use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Elementor_Api {

	const NS = '/elementor/v1';

	/**
	 * @return array|WP_Error Decoded response data.
	 */
	private function request( $method, $route, array $params = array() ) {
		$request = new WP_REST_Request( $method, self::NS . $route );
		if ( 'GET' === $method ) {
			$request->set_query_params( $params );
		} else {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( wp_json_encode( $params ) );
		}

		$response = rest_do_request( $request );
		$status   = $response->get_status();
		$data     = $response->get_data();

		if ( $response->is_error() || $status >= 400 ) {
			$message = is_array( $data ) && isset( $data['message'] ) ? $data['message'] : __( 'Elementor rejected the request.', 'nori-for-elementor' );
			return new WP_Error( 'nori_fe_elementor_api', $message, array( 'status' => $status, 'route' => $route, 'response' => $data ) );
		}

		return is_array( $data ) ? $data : array();
	}

	/**
	 * @return array|WP_Error List of [ 'id' => string, 'label' => string ] in Elementor's order.
	 */
	public function list_classes() {
		$data = $this->request( 'GET', '/global-classes', array( 'context' => 'frontend' ) );
		return is_wp_error( $data ) ? $data : (array) ( $data['data'] ?? array() );
	}

	/**
	 * @param string[] $ids Class ids.
	 * @return array|WP_Error Map of id => class item (or null when missing).
	 */
	public function get_classes( array $ids ) {
		if ( empty( $ids ) ) {
			return array();
		}
		$data = $this->request(
			'GET',
			'/global-classes/styles',
			array(
				'context' => 'frontend',
				'ids'     => implode( ',', $ids ),
			)
		);
		return is_wp_error( $data ) ? $data : (array) ( $data['data'] ?? array() );
	}

	/**
	 * Create and/or overwrite classes in one call.
	 *
	 * @param array    $items    Map of id => [ id, label, type, variants ].
	 * @param string[] $added    Ids that are new.
	 * @param string[] $modified Ids that already exist.
	 * @param string[] $order    Current order of all class ids (new ids are appended by Elementor).
	 * @return true|WP_Error
	 */
	public function put_classes( array $items, array $added, array $modified, array $order ) {
		$data = $this->request(
			'PUT',
			'/global-classes',
			array(
				'context' => 'frontend',
				'changes' => array(
					'added'    => array_values( $added ),
					'deleted'  => array(),
					'modified' => array_values( $modified ),
					'order'    => ! empty( $added ),
				),
				'items'   => $items,
				'order'   => array_values( $order ),
			)
		);
		return is_wp_error( $data ) ? $data : true;
	}

	/**
	 * Set the order of all classes (higher in the list wins when two classes set the same property).
	 *
	 * @param string[] $order Every class id, in the new order.
	 * @return true|WP_Error
	 */
	public function put_order( array $order ) {
		$data = $this->request(
			'PUT',
			'/global-classes',
			array(
				'context' => 'frontend',
				'changes' => array(
					'added'    => array(),
					'deleted'  => array(),
					'modified' => array(),
					'order'    => true,
				),
				'items'   => new \stdClass(),
				'order'   => array_values( $order ),
			)
		);
		return is_wp_error( $data ) ? $data : true;
	}

	/**
	 * Delete classes. Elementor then removes them from every element that uses them
	 * (its own "global classes cleanup" runs on the pages that reference them).
	 *
	 * @param string[] $ids   Class ids to delete.
	 * @param string[] $order Current order of all class ids.
	 * @return true|WP_Error
	 */
	public function delete_classes( array $ids, array $order ) {
		$data = $this->request(
			'PUT',
			'/global-classes',
			array(
				'context' => 'frontend',
				'changes' => array(
					'added'    => array(),
					'deleted'  => array_values( $ids ),
					'modified' => array(),
					'order'    => true,
				),
				'items'   => new \stdClass(),
				'order'   => array_values( array_diff( $order, $ids ) ),
			)
		);
		return is_wp_error( $data ) ? $data : true;
	}

	/**
	 * @return array|WP_Error Map of variable id => [ type, label, value, ... ] (deleted ones included).
	 */
	public function list_variables() {
		$data = $this->request( 'GET', '/variables/list' );
		return is_wp_error( $data ) ? $data : (array) ( $data['data']['variables'] ?? array() );
	}

	/**
	 * @return array|WP_Error The created variable including its new 'id'.
	 */
	public function create_variable( $type, $label, $value ) {
		$data = $this->request(
			'POST',
			'/variables/create',
			array(
				'type'  => $type,
				'label' => $label,
				'value' => $value,
			)
		);
		return is_wp_error( $data ) ? $data : (array) ( $data['data']['variable'] ?? array() );
	}

	/**
	 * @return array|WP_Error
	 */
	public function update_variable( $id, $label, $value ) {
		$data = $this->request(
			'PUT',
			'/variables/update',
			array(
				'id'    => $id,
				'label' => $label,
				'value' => $value,
			)
		);
		return is_wp_error( $data ) ? $data : (array) ( $data['data']['variable'] ?? array() );
	}

	/**
	 * Delete a variable. Elementor keeps it as "deleted", so it can be restored later.
	 *
	 * @return array|WP_Error
	 */
	public function delete_variable( $id ) {
		$data = $this->request( 'POST', '/variables/delete', array( 'id' => $id ) );
		return is_wp_error( $data ) ? $data : (array) ( $data['data']['variable'] ?? array() );
	}

	/**
	 * @return array|WP_Error
	 */
	public function restore_variable( $id ) {
		$data = $this->request( 'POST', '/variables/restore', array( 'id' => $id ) );
		return is_wp_error( $data ) ? $data : (array) ( $data['data']['variable'] ?? array() );
	}
}
