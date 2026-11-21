<?php

/*
 * منقول من بلجن ووردبريس employee-tracker 2.9.1 — منطق العمل كما هو،
 * يعمل فوق Laravel عبر app/Support/helpers.php (لا اعتماد على ووردبريس).
 */

class ET_AuditLog {

	public static function log( $message ) {
		global $wpdb;
		$table = ET_Database::table( 'audit_log' );
		$wpdb->insert( $table, array( 'message' => $message, 'created_at' => current_time( 'mysql' ) ) );
	}

	public static function recent( $limit = 100 ) {
		global $wpdb;
		$table = ET_Database::table( 'audit_log' );
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", (int) $limit ) );
	}
}
