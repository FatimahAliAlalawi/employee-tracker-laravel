<?php

/*
 * منقول من بلجن ووردبريس employee-tracker 2.9.1 — منطق العمل كما هو،
 * يعمل فوق Laravel عبر app/Support/helpers.php (لا اعتماد على ووردبريس).
 */

class ET_Checklist {

	public static function add( $task_id, $text ) {
		global $wpdb;
		$table = ET_Database::table( 'task_checklist' );
		$wpdb->insert( $table, array(
			'task_id' => (int) $task_id, 'text' => $text, 'done' => 0, 'created_at' => current_time( 'mysql' ),
		) );
		return (int) $wpdb->insert_id;
	}

	public static function toggle( $item_id, $done ) {
		global $wpdb;
		$table = ET_Database::table( 'task_checklist' );
		$wpdb->update( $table, array( 'done' => $done ? 1 : 0 ), array( 'id' => (int) $item_id ) );
	}

	public static function delete( $item_id ) {
		global $wpdb;
		$table = ET_Database::table( 'task_checklist' );
		$wpdb->delete( $table, array( 'id' => (int) $item_id ) );
	}

	public static function for_task( $task_id ) {
		global $wpdb;
		$table = ET_Database::table( 'task_checklist' );
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE task_id = %d ORDER BY id ASC", (int) $task_id ) );
	}

	public static function for_tasks( $task_ids ) {
		global $wpdb;
		$task_ids = array_filter( array_map( 'intval', (array) $task_ids ) );
		if ( empty( $task_ids ) ) return array();
		$table        = ET_Database::table( 'task_checklist' );
		$placeholders = implode( ',', array_fill( 0, count( $task_ids ), '%d' ) );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE task_id IN ({$placeholders}) ORDER BY id ASC", $task_ids ) );
		$out = array();
		foreach ( $rows as $r ) { $out[ (int) $r->task_id ][] = $r; }
		return $out;
	}

	public static function item_belongs_to_task( $item_id, $task_id ) {
		global $wpdb;
		$table = ET_Database::table( 'task_checklist' );
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE id = %d AND task_id = %d", (int) $item_id, (int) $task_id ) );
	}
}
