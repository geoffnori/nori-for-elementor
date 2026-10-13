<?php
/**
 * Form Spam Protection admin: what was blocked and why (for the app), and clearing the log.
 *
 * @package Nori_For_Elementor
 */

namespace Nori\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Spam {

	const NONCE = 'nori_fe_spam';

	public function register_hooks() {
		add_action( 'wp_ajax_nori_fe_spam_clear', array( $this, 'ajax_clear' ) );
	}

	public function ajax_clear() {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! current_user_can( Admin::CAP ) ) {
			wp_send_json_error( null, 403 );
		}
		Form_Guard::clear_log();
		wp_send_json_success();
	}

	private static function kind_label( $kind ) {
		$labels = array(
			'classic' => __( 'Elementor form, on the server', 'nori-for-elementor' ),
			'atomic'  => __( 'Atomic form, on the server', 'nori-for-elementor' ),
			'browser' => __( 'In the browser', 'nori-for-elementor' ),
		);
		return $labels[ $kind ] ?? $labels['classic'];
	}

	/** The log for the admin app: totals, reasons and the latest entries, in words. */
	public static function app_state() {
		$t       = Form_Guard::totals( 7 );
		$log     = Form_Guard::read_log();
		$reasons = Form_Guard::reasons();
		$by      = array();
		foreach ( $t['by_reason'] as $why => $n ) {
			$by[] = array( $reasons[ $why ] ?? $why, (int) $n );
		}
		$recent = array();
		foreach ( array_slice( $log['recent'], 0, 20 ) as $row ) {
			$recent[] = array(
				/* translators: %s: time ago. */
				'when'   => sprintf( __( '%s ago', 'nori-for-elementor' ), human_time_diff( (int) $row['time'] ) ),
				'why'    => $reasons[ $row['why'] ] ?? $row['why'],
				'test'   => ! empty( $row['test'] ),
				'page'   => (string) $row['page'],
				'caught' => self::kind_label( $row['kind'] ),
			);
		}
		return array(
			'nonce'   => wp_create_nonce( self::NONCE ),
			'ajax'    => admin_url( 'admin-ajax.php' ),
			'blocked' => (int) $t['blocked'],
			'flagged' => (int) $t['flagged'],
			'reasons' => $by,
			'recent'  => $recent,
		);
	}
}
