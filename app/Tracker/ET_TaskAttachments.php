<?php

/*
 * منقول من بلجن ووردبريس employee-tracker 2.9.1 — منطق العمل كما هو،
 * يعمل فوق Laravel عبر app/Support/helpers.php (لا اعتماد على ووردبريس).
 */

class ET_TaskAttachments {

	public static function add( $task_id, $name, $url ) {
		global $wpdb;
		$table = ET_Database::table( 'task_attachments' );
		$wpdb->insert( $table, array(
			'task_id' => (int) $task_id, 'name' => $name, 'url' => $url, 'created_at' => current_time( 'mysql' ),
		) );
		return (int) $wpdb->insert_id;
	}

	public static function delete( $id ) {
		global $wpdb;
		$table = ET_Database::table( 'task_attachments' );
		$wpdb->delete( $table, array( 'id' => (int) $id ) );
	}

	public static function for_task( $task_id ) {
		global $wpdb;
		$table = ET_Database::table( 'task_attachments' );
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE task_id = %d ORDER BY id ASC", (int) $task_id ) );
	}

	public static function for_tasks( $task_ids ) {
		global $wpdb;
		$task_ids = array_filter( array_map( 'intval', (array) $task_ids ) );
		if ( empty( $task_ids ) ) return array();
		$table        = ET_Database::table( 'task_attachments' );
		$placeholders = implode( ',', array_fill( 0, count( $task_ids ), '%d' ) );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE task_id IN ({$placeholders}) ORDER BY id ASC", $task_ids ) );
		$out = array();
		foreach ( $rows as $r ) { $out[ (int) $r->task_id ][] = $r; }
		return $out;
	}
}
