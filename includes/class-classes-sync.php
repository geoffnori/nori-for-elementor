<?php
/**
 * Imports library classes into Elementor's Class Manager, reports which ones were
 * edited in the editor, and resets edited ones to the library default.
 *
 * Import never overwrites a class that already exists, so edits made in the
 * Elementor editor always survive re-imports and plugin updates.
 *
 * @package Nori_For_Elementor
 */

namespace Nori\Elementor;

use Elementor\Modules\AtomicWidgets\Styles\Style_Schema;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Classes_Sync {

	const STATUS_MISSING = 'missing';
	const STATUS_DEFAULT = 'default';
	const STATUS_EDITED  = 'edited';

	/** @var Library */
	private $library;

	/** @var Elementor_Api */
	private $api;

	/** @var Variables_Sync */
	private $variables;

	/** @var Style_Converter|null Built once per operation (it needs the variable list). */
	private $converter = null;

	public function __construct( Library $library, Elementor_Api $api, Variables_Sync $variables ) {
		$this->library   = $library;
		$this->api       = $api;
		$this->variables = $variables;
	}

	/**
	 * Deterministic Elementor id for a library class, in Elementor's own "g-xxxxxxx" style.
	 * Stable across installs, so a reinstall finds the same classes again.
	 */
	public static function class_id( $key ) {
		return 'g-' . substr( md5( 'nori-fe:' . $key ), 0, 7 );
	}

	/**
	 * The library default for a class, as Elementor variants.
	 */
	public function default_variants( array $class ) {
		if ( null === $this->converter ) {
			$this->converter = new Style_Converter( Style_Schema::get(), $this->variables->reference_map() );
		}
		$converted = $this->converter->to_variants( (array) ( $class['styles'] ?? array() ) );
		return $converted['variants'];
	}

	/**
	 * @return array|WP_Error Map of key => [ status, id, label, module ].
	 */
	public function status() {
		$this->converter = null; // Variables may have changed since the last call.
		$list = $this->api->list_classes();
		if ( is_wp_error( $list ) ) {
			return $list;
		}

		$labels_by_id = array();
		foreach ( $list as $row ) {
			$labels_by_id[ $row['id'] ] = $row['label'];
		}
		$ids_by_label = array_flip( $labels_by_id );

		$library = $this->library->classes();
		$found   = array();
		foreach ( $library as $key => $class ) {
			$found[ $key ] = $this->locate( $key, $class, $labels_by_id, $ids_by_label );
		}

		$items = $this->api->get_classes( array_values( array_filter( $found ) ) );
		if ( is_wp_error( $items ) ) {
			return $items;
		}

		$out = array();
		foreach ( $library as $key => $class ) {
			$id     = $found[ $key ];
			$status = self::STATUS_MISSING;
			if ( $id && ! empty( $items[ $id ] ) ) {
				$status = $this->is_default( $key, $class, $items[ $id ] ) ? self::STATUS_DEFAULT : self::STATUS_EDITED;
			}
			$out[ $key ] = array(
				'status' => $status,
				'id'     => $id,
				'label'  => $id ? ( $labels_by_id[ $id ] ?? $class['label'] ) : $class['label'],
				'module' => $class['module'] ?? null,
			);
		}
		return $out;
	}

	private function locate( $key, array $class, array $labels_by_id, array $ids_by_label ) {
		$record = State::get( 'classes', $key );
		if ( $record && isset( $labels_by_id[ $record['id'] ] ) ) {
			return $record['id'];
		}
		$id = self::class_id( $key );
		if ( isset( $labels_by_id[ $id ] ) ) {
			return $id;
		}
		return $ids_by_label[ $class['label'] ] ?? null;
	}

	private function is_default( $key, array $class, array $item ) {
		$current = State::fingerprint( $item['variants'] ?? array() );
		$record  = State::get( 'classes', $key );
		if ( $record && ! empty( $record['default_fp'] ) ) {
			return $current === $record['default_fp'];
		}
		// Not imported by this install (e.g. after a reinstall): compare with a fresh default.
		return $current === State::fingerprint( $this->default_variants( $class ) );
	}

	/**
	 * Add library classes that aren't in Elementor yet. Classes that already exist are
	 * linked but never changed.
	 *
	 * @param string[] $keys Library keys (empty = all).
	 * @return array{done: string[], skipped: string[], errors: array}
	 */
	public function import( array $keys = array() ) {
		$result = array( 'done' => array(), 'skipped' => array(), 'errors' => array(), 'variables' => array() );
		$status = $this->status();
		if ( is_wp_error( $status ) ) {
			$result['errors']['all'] = $status->get_error_message();
			return $result;
		}

		$library = $this->library->classes();
		$keys    = $keys ? array_values( array_intersect( $keys, array_keys( $library ) ) ) : array_keys( $library );

		// Classes that point at a variable bring that variable along, so they stay connected.
		$needed = $this->variables_needed( array_filter( $keys, fn( $k ) => self::STATUS_MISSING === $status[ $k ]['status'] ) );
		if ( $needed ) {
			$v                   = $this->variables->import( $needed );
			$result['variables'] = $v['done'];
			$this->converter     = null; // Rebuild with the new variable ids.
		}
		$items   = array();
		$added   = array();

		foreach ( $keys as $key ) {
			$row = $status[ $key ];
			if ( self::STATUS_MISSING !== $row['status'] ) {
				if ( ! State::get( 'classes', $key ) ) {
					State::set( 'classes', $key, array( 'id' => $row['id'] ) );
				}
				$result['skipped'][] = $key;
				continue;
			}
			$id           = self::class_id( $key );
			$items[ $id ] = array(
				'id'       => $id,
				'label'    => $library[ $key ]['label'],
				'type'     => 'class',
				'variants' => $this->default_variants( $library[ $key ] ),
			);
			$added[ $key ] = $id;
		}

		if ( empty( $items ) ) {
			return $result;
		}

		$saved = $this->save( $items, array_values( $added ), array() );
		if ( is_wp_error( $saved ) ) {
			foreach ( array_keys( $added ) as $key ) {
				$result['errors'][ $key ] = $saved->get_error_message();
			}
			return $result;
		}

		$this->remember( $added, $items );
		$result['done'] = array_keys( $added );
		$this->apply_order();
		return $result;
	}

	/**
	 * Starter variables the given classes use (module variables come with their modules).
	 *
	 * @param string[] $keys Library class keys.
	 * @return string[] Variable keys.
	 */
	public function variables_needed( array $keys ) {
		$vars = $this->library->variables();
		$out  = array();
		foreach ( $keys as $key ) {
			foreach ( $this->library->class_variables( $key ) as $v ) {
				if ( empty( $vars[ $v ]['module'] ) ) {
					$out[ $v ] = true;
				}
			}
		}
		return array_keys( $out );
	}

	/**
	 * Keep Nori's classes in a sensible order in Elementor: modifiers and effects above the
	 * looks they change (higher in the list wins), all after the site's own classes.
	 *
	 * @return bool Whether the order changed.
	 */
	public function apply_order() {
		$list = $this->api->list_classes();
		if ( is_wp_error( $list ) ) {
			return false;
		}
		$current = wp_list_pluck( $list, 'id' );
		$library = $this->library->classes();
		$rank    = array();
		$i       = 0;
		foreach ( $library as $key => $class ) {
			$record = State::get( 'classes', $key );
			$id     = $record['id'] ?? self::class_id( $key );
			$rank[ $id ] = array( (int) ( $class['priority'] ?? 60 ), $i++ );
		}
		$theirs = array_values( array_filter( $current, fn( $id ) => ! isset( $rank[ $id ] ) ) );
		$ours   = array_values( array_filter( $current, fn( $id ) => isset( $rank[ $id ] ) ) );
		usort( $ours, fn( $a, $b ) => $rank[ $a ] <=> $rank[ $b ] );
		$order = array_merge( $theirs, $ours );
		if ( $order === $current ) {
			return false;
		}
		return true === $this->api->put_order( $order );
	}

	/**
	 * Classes Nori renamed (btn-arrow-np became svg-mover-np): relabel the class in Elementor.
	 * The id stays the same, so every element that uses it keeps it, and its styles are untouched.
	 * A label the user chose in Elementor is kept; only Nori's own old label is replaced.
	 *
	 * @return string[] New keys whose class was relabeled.
	 */
	public function apply_renames() {
		$renames = $this->library->renamed_keys();
		if ( ! $renames ) {
			return array();
		}
		$list = $this->api->list_classes();
		if ( is_wp_error( $list ) || $this->capability_error() ) {
			return array();
		}
		$labels_by_id = array();
		foreach ( $list as $row ) {
			$labels_by_id[ $row['id'] ] = $row['label'];
		}
		$library = $this->library->classes();
		$items   = array();
		$moved   = array();
		foreach ( $renames as $old => $new ) {
			if ( empty( $library[ $new ] ) ) {
				continue;
			}
			$record = State::get( 'classes', $old );
			$id     = $record['id'] ?? self::class_id( $old );
			if ( ! isset( $labels_by_id[ $id ] ) ) {
				$id = array_search( $old . '-np', $labels_by_id, true );
			}
			if ( ! $id || ! isset( $labels_by_id[ $id ] ) ) {
				State::forget( 'classes', $old );
				continue;
			}
			if ( in_array( $library[ $new ]['label'], $labels_by_id, true ) ) {
				continue; // The new class already exists; leave both alone.
			}
			if ( $old . '-np' === $labels_by_id[ $id ] ) {
				$items[ $id ] = array( 'id' => $id, 'label' => $library[ $new ]['label'] );
			}
			$moved[ $old ] = array( $new, $id, $record );
		}
		if ( $items ) {
			$stored = $this->api->get_classes( array_keys( $items ) );
			if ( is_wp_error( $stored ) ) {
				return array();
			}
			foreach ( $items as $id => $item ) {
				$items[ $id ] = array_merge( $stored[ $id ], $item );
			}
			$saved = $this->save( $items, array(), array_keys( $items ) );
			if ( is_wp_error( $saved ) ) {
				return array();
			}
		}
		$done = array();
		foreach ( $moved as $old => $row ) {
			list( $new, $id, $record ) = $row;
			State::set( 'classes', $new, $record ? array_merge( $record, array( 'id' => $id ) ) : array( 'id' => $id, 'default_fp' => null, 'library_fp' => null, 'version' => NORI_FE_VERSION ) );
			State::forget( 'classes', $old );
			$done[] = $new;
		}
		return $done;
	}

	/**
	 * Delete classes Nori used to ship but has removed from its library.
	 *
	 * @return string[] Keys that were deleted.
	 */
	public function remove_retired() {
		$retired = $this->library->retired_keys();
		$list    = $this->api->list_classes();
		if ( ! $retired || is_wp_error( $list ) || $this->capability_error() ) {
			return array();
		}
		$present = wp_list_pluck( $list, 'id' );
		$ids     = array();
		foreach ( $retired as $key ) {
			$record = State::get( 'classes', $key );
			$id     = $record['id'] ?? self::class_id( $key );
			if ( in_array( $id, $present, true ) ) {
				$ids[ $key ] = $id;
			}
			State::forget( 'classes', $key );
		}
		if ( ! $ids ) {
			return array();
		}
		$r = $this->api->delete_classes( array_values( $ids ), $present );
		return true === $r ? array_keys( $ids ) : array();
	}

	/**
	 * Classes that point at a variable which has been deleted keep its last value instead,
	 * as a plain number or color, so they show as edited rather than silently losing it.
	 *
	 * @return string[] Keys of classes that were changed.
	 */
	public function detach_deleted_variables() {
		$all = $this->api->list_variables();
		if ( is_wp_error( $all ) || $this->capability_error() ) {
			return array();
		}
		$deleted = array();
		foreach ( $all as $id => $v ) {
			if ( ! empty( $v['deleted'] ) || ! empty( $v['deleted_at'] ) ) {
				$deleted[ (string) $id ] = $v;
			}
		}
		if ( ! $deleted ) {
			return array();
		}
		$status = $this->status();
		if ( is_wp_error( $status ) ) {
			return array();
		}
		$in    = array_filter( $status, fn( $r ) => self::STATUS_MISSING !== $r['status'] );
		$items = $in ? $this->api->get_classes( wp_list_pluck( $in, 'id' ) ) : array();
		if ( is_wp_error( $items ) ) {
			return array();
		}
		$write = array();
		foreach ( $in as $key => $row ) {
			$variants = $items[ $row['id'] ]['variants'] ?? null;
			if ( ! $variants ) {
				continue;
			}
			$changed = false;
			$new     = $this->detach( $variants, $deleted, $changed );
			if ( $changed ) {
				$write[ $key ] = array( 'variants' => $new );
			}
		}
		if ( ! $write ) {
			return array();
		}
		$r = $this->write_variants( $write );
		return $r['done'];
	}

	private function detach( $value, array $deleted, &$changed ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		if ( isset( $value['$$type'], $value['value'] ) && is_string( $value['value'] ) && isset( $deleted[ $value['value'] ] ) ) {
			$var     = $deleted[ $value['value'] ];
			$literal = Variables_Sync::plain( $var['value'] ?? '' );
			if ( 'global-color-variable' === $value['$$type'] ) {
				$changed = true;
				return array( '$$type' => 'color', 'value' => $literal );
			}
			if ( in_array( $value['$$type'], array( 'global-size-variable', 'global-custom-size-variable' ), true ) ) {
				if ( null === $this->converter ) {
					$this->converter = new Style_Converter( Style_Schema::get(), $this->variables->reference_map() );
				}
				$size = $this->converter->parse_size( $literal );
				if ( $size ) {
					$changed = true;
					return array( '$$type' => 'size', 'value' => $size );
				}
			}
			return $value;
		}
		foreach ( $value as $k => $v ) {
			$value[ $k ] = $this->detach( $v, $deleted, $changed );
		}
		return $value;
	}

	/**
	 * Put edited classes back to the library default. The class keeps its id and
	 * current name, so every element that uses it just picks up the default look.
	 *
	 * @param string[] $keys Library keys.
	 */
	public function reset( array $keys ) {
		$result = array( 'done' => array(), 'skipped' => array(), 'errors' => array() );
		$status = $this->status();
		if ( is_wp_error( $status ) ) {
			$result['errors']['all'] = $status->get_error_message();
			return $result;
		}

		$library = $this->library->classes();
		$items   = array();
		$reset   = array();
		foreach ( array_intersect( $keys, array_keys( $library ) ) as $key ) {
			$row = $status[ $key ];
			if ( self::STATUS_EDITED !== $row['status'] ) {
				$result['skipped'][] = $key;
				continue;
			}
			$items[ $row['id'] ] = array(
				'id'       => $row['id'],
				'label'    => $row['label'],
				'type'     => 'class',
				'variants' => $this->default_variants( $library[ $key ] ),
			);
			$reset[ $key ] = $row['id'];
		}

		if ( empty( $items ) ) {
			return $result;
		}

		$saved = $this->save( $items, array(), array_values( $reset ) );
		if ( is_wp_error( $saved ) ) {
			foreach ( array_keys( $reset ) as $key ) {
				$result['errors'][ $key ] = $saved->get_error_message();
			}
			return $result;
		}

		$this->remember( $reset, $items );
		$result['done'] = array_keys( $reset );
		return $result;
	}

	/**
	 * Delete library classes from Elementor. Elementor strips them from every element
	 * that uses them, on every page. This can't be undone (import brings back the default).
	 *
	 * @param string[] $keys Library keys.
	 * @return array{done: string[], skipped: string[], errors: array}
	 */
	public function remove( array $keys ) {
		$result = array( 'done' => array(), 'skipped' => array(), 'errors' => array() );
		$status = $this->status();
		if ( is_wp_error( $status ) ) {
			$result['errors']['all'] = $status->get_error_message();
			return $result;
		}

		$ids = array();
		foreach ( array_intersect( $keys, array_keys( $status ) ) as $key ) {
			if ( self::STATUS_MISSING === $status[ $key ]['status'] ) {
				State::forget( 'classes', $key );
				$result['skipped'][] = $key;
				continue;
			}
			$ids[ $key ] = $status[ $key ]['id'];
		}
		if ( empty( $ids ) ) {
			return $result;
		}

		$saved = $this->capability_error();
		if ( ! $saved ) {
			$list  = $this->api->list_classes();
			$saved = is_wp_error( $list ) ? $list : $this->api->delete_classes( array_values( $ids ), wp_list_pluck( $list, 'id' ) );
		}
		if ( is_wp_error( $saved ) ) {
			foreach ( array_keys( $ids ) as $key ) {
				$result['errors'][ $key ] = $saved->get_error_message();
			}
			return $result;
		}

		foreach ( array_keys( $ids ) as $key ) {
			State::forget( 'classes', $key );
		}
		$result['done'] = array_keys( $ids );
		return $result;
	}

	/**
	 * How many posts, pages and templates use each class, from Elementor's own usage index.
	 *
	 * @param array<string, array> $status Output of status() (only imported classes are counted).
	 * @return array<string, int> Map of key => number of documents.
	 */
	public function usage( array $status ) {
		$out = array();
		if ( ! class_exists( '\\Elementor\\Modules\\GlobalClasses\\Global_Classes_Relations' ) ) {
			return $out;
		}
		$relations = new \Elementor\Modules\GlobalClasses\Global_Classes_Relations();
		foreach ( $status as $key => $row ) {
			if ( ! empty( $row['id'] ) && self::STATUS_MISSING !== $row['status'] ) {
				$posts       = $relations->get_posts_by_style( $row['id'] );
				$out[ $key ] = count( array_filter( (array) $posts, fn( $id ) => 'trash' !== get_post_status( $id ) && get_post_status( $id ) ) );
			}
		}
		return $out;
	}

	/** @return WP_Error|null An error when the current user can't write global classes. */
	private function capability_error() {
		if ( current_user_can( 'elementor_global_classes_update_class' ) ) {
			return null;
		}
		return new WP_Error(
			'nori_fe_no_class_cap',
			__( 'Your account can\'t edit Elementor global classes yet. Open any Elementor screen in WP Admin once (so Elementor finishes its update), then try again.', 'nori-for-elementor' )
		);
	}

	/**
	 * Overwrite classes that are in Elementor with the given variants (and label, if given).
	 * Used by Import to bring over edits made on another site. The stored default fingerprint
	 * is kept, so an imported edit shows as "edited".
	 *
	 * @param array<string, array> $by_key Map of key => [ variants, label? ].
	 * @return array{done: string[], skipped: string[], errors: array}
	 */
	public function write_variants( array $by_key ) {
		$result = array( 'done' => array(), 'skipped' => array(), 'errors' => array() );
		$status = $this->status();
		if ( is_wp_error( $status ) ) {
			$result['errors']['all'] = $status->get_error_message();
			return $result;
		}
		$items = array();
		$ids   = array();
		foreach ( $by_key as $key => $row ) {
			if ( ! isset( $status[ $key ] ) || self::STATUS_MISSING === $status[ $key ]['status'] || ! is_array( $row['variants'] ?? null ) ) {
				$result['skipped'][] = $key;
				continue;
			}
			$id           = $status[ $key ]['id'];
			$items[ $id ] = array(
				'id'       => $id,
				'label'    => ! empty( $row['label'] ) ? sanitize_text_field( $row['label'] ) : $status[ $key ]['label'],
				'type'     => 'class',
				'variants' => array_values( $row['variants'] ),
			);
			$ids[ $key ] = $id;
		}
		if ( ! $items ) {
			return $result;
		}
		$saved = $this->save( $items, array(), array_values( $ids ) );
		if ( is_wp_error( $saved ) ) {
			foreach ( array_keys( $ids ) as $key ) {
				$result['errors'][ $key ] = $saved->get_error_message();
			}
			return $result;
		}
		$result['done'] = array_keys( $ids );
		return $result;
	}

	private function save( array $items, array $added, array $modified ) {
		$error = $this->capability_error();
		if ( $error ) {
			return $error;
		}
		$list = $this->api->list_classes();
		if ( is_wp_error( $list ) ) {
			return $list;
		}
		return $this->api->put_classes( $items, $added, $modified, wp_list_pluck( $list, 'id' ) );
	}

	/**
	 * Store the fingerprint of what Elementor actually saved, so later edits are detectable,
	 * and of the library default that was sent, so plugin updates can tell when it changed.
	 *
	 * @param array<string, string> $ids_by_key
	 * @param array                 $items      The items that were sent, keyed by id.
	 */
	private function remember( array $ids_by_key, array $items = array() ) {
		$stored = $this->api->get_classes( array_values( $ids_by_key ) );
		foreach ( $ids_by_key as $key => $id ) {
			$variants = ( ! is_wp_error( $stored ) && ! empty( $stored[ $id ] ) ) ? ( $stored[ $id ]['variants'] ?? array() ) : null;
			State::set(
				'classes',
				$key,
				array(
					'id'         => $id,
					'default_fp' => null === $variants ? null : State::fingerprint( $variants ),
					'library_fp' => isset( $items[ $id ] ) ? State::fingerprint( $items[ $id ]['variants'] ) : null,
					'version'    => NORI_FE_VERSION,
				)
			);
		}
	}

	/** Variants that carry no styles at all count as nothing. */
	private function without_empty( array $variants ) {
		return array_values(
			array_filter(
				$variants,
				fn( $v ) => ! empty( $v['props'] ) || ! empty( $v['custom_css'] )
			)
		);
	}

	/**
	 * After a plugin update: bring classes the user never edited up to the new library default.
	 * Edited classes are left alone.
	 *
	 * @return array{done: string[], skipped: string[], errors: array}
	 */
	public function upgrade_untouched() {
		$result = array( 'done' => array(), 'skipped' => array(), 'errors' => array() );
		$status = $this->status();
		if ( is_wp_error( $status ) ) {
			$result['errors']['all'] = $status->get_error_message();
			return $result;
		}
		$library = $this->library->classes();
		$items   = array();
		$ids     = array();
		// Classes linked without a record of their default (made elsewhere, or before Nori kept one)
		// that are still completely empty: there is nothing of the user's to keep, so they update too.
		$unknown = array();
		foreach ( $status as $key => $row ) {
			$record = State::get( 'classes', $key );
			if ( self::STATUS_EDITED === $row['status'] && $row['id'] && empty( $record['default_fp'] ) ) {
				$unknown[ $key ] = $row['id'];
			}
		}
		$empty = array();
		if ( $unknown ) {
			$stored = $this->api->get_classes( array_values( $unknown ) );
			foreach ( $unknown as $key => $id ) {
				if ( ! is_wp_error( $stored ) && isset( $stored[ $id ] ) && State::fingerprint( array() ) === State::fingerprint( $this->without_empty( $stored[ $id ]['variants'] ?? array() ) ) ) {
					$empty[ $key ] = true;
				}
			}
		}
		foreach ( $status as $key => $row ) {
			$record = State::get( 'classes', $key );
			if ( isset( $empty[ $key ] ) ) {
				$record = array( 'default_fp' => 'empty', 'library_fp' => null );
			} elseif ( self::STATUS_DEFAULT !== $row['status'] || ! $record || empty( $record['default_fp'] ) ) {
				continue;
			}
			$variants = $this->default_variants( $library[ $key ] );
			if ( ! empty( $record['library_fp'] ) && State::fingerprint( $variants ) === $record['library_fp'] ) {
				continue; // Library default unchanged.
			}
			$items[ $row['id'] ] = array(
				'id'       => $row['id'],
				'label'    => $row['label'],
				'type'     => 'class',
				'variants' => $variants,
			);
			$ids[ $key ] = $row['id'];
		}
		if ( ! $items ) {
			return $result;
		}
		$saved = $this->save( $items, array(), array_values( $ids ) );
		if ( is_wp_error( $saved ) ) {
			$result['errors']['all'] = $saved->get_error_message();
			return $result;
		}
		$this->remember( $ids, $items );
		$result['done'] = array_keys( $ids );
		return $result;
	}
}
