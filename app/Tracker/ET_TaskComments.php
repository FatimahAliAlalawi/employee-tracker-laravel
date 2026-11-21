<?php

/*
 * منقول من بلجن ووردبريس employee-tracker 2.9.1 — منطق العمل كما هو،
 * يعمل فوق Laravel عبر app/Support/helpers.php (لا اعتماد على ووردبريس).
 */

class ET_TaskComments {

	public static function add( $task_id, $message, $author = 'الإدارة' ) {
		global $wpdb;
		$table = ET_Database::table( 'task_comments' );
		$wpdb->insert( $table, array(
			'task_id'    => (int) $task_id,
			'author'     => $author ?: 'الإدارة',
			'message'    => $message,
			'created_at' => current_time( 'mysql' ),
		) );
		return (int) $wpdb->insert_id;
	}

	public static function for_task( $task_id ) {
		global $wpdb;
		$table = ET_Database::table( 'task_comments' );
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE task_id = %d ORDER BY id ASC", (int) $task_id
		) );
	}

	/** يجلب تعليقات عدة مهام دفعة واحدة، مُصنَّفة حسب task_id، لتفادي استعلام منفصل لكل مهمة */
	public static function for_tasks( $task_ids ) {
		global $wpdb;
		$task_ids = array_filter( array_map( 'intval', (array) $task_ids ) );
		if ( empty( $task_ids ) ) return array();
		$table        = ET_Database::table( 'task_comments' );
		$placeholders = implode( ',', array_fill( 0, count( $task_ids ), '%d' ) );
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE task_id IN ({$placeholders}) ORDER BY id ASC", $task_ids
		) );
		$out = array();
		foreach ( $rows as $r ) { $out[ (int) $r->task_id ][] = $r; }
		return $out;
	}
}
