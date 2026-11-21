<?php

/*
 * منقول من بلجن ووردبريس employee-tracker 2.9.1 — منطق العمل كما هو،
 * يعمل فوق Laravel عبر app/Support/helpers.php (لا اعتماد على ووردبريس).
 */

class ET_UpdateComments {

	public static function add( $update_id, $message, $author = 'الإدارة' ) {
		global $wpdb;
		$table = ET_Database::table( 'update_comments' );
		$wpdb->insert( $table, array(
			'update_id'  => (int) $update_id,
			'author'     => $author ?: 'الإدارة',
			'message'    => $message,
			'created_at' => current_time( 'mysql' ),
		) );
		return (int) $wpdb->insert_id;
	}

	public static function for_update( $update_id ) {
		global $wpdb;
		$table = ET_Database::table( 'update_comments' );
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE update_id = %d ORDER BY id ASC", (int) $update_id
		) );
	}

	/** يجلب تعليقات عدة تحديثات دفعة واحدة، مُصنَّفة حسب update_id، لتفادي استعلام منفصل لكل تحديث */
	public static function for_updates( $update_ids ) {
		global $wpdb;
		$update_ids = array_filter( array_map( 'intval', (array) $update_ids ) );
		if ( empty( $update_ids ) ) return array();
		$table        = ET_Database::table( 'update_comments' );
		$placeholders = implode( ',', array_fill( 0, count( $update_ids ), '%d' ) );
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE update_id IN ({$placeholders}) ORDER BY id ASC", $update_ids
		) );
		$out = array();
		foreach ( $rows as $r ) { $out[ (int) $r->update_id ][] = $r; }
		return $out;
	}

	/** عدد التحديثات (المهام المرسلة) التي أرسل عليها الموظف الحالي تعليقًا واحدًا على الأقل — تُستخدم لشارة العدّاد بواجهة الموظف */
	public static function count_for_employee( $employee_id ) {
		global $wpdb;
		$table   = ET_Database::table( 'update_comments' );
		$updates = ET_Database::table( 'daily_updates' );
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(DISTINCT c.update_id) FROM {$table} c INNER JOIN {$updates} u ON u.id = c.update_id WHERE u.employee_id = %d",
			(int) $employee_id
		) );
	}
}
