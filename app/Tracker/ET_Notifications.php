<?php

/*
 * منقول من بلجن ووردبريس employee-tracker 2.9.1 — منطق العمل كما هو،
 * يعمل فوق Laravel عبر app/Support/helpers.php (لا اعتماد على ووردبريس).
 */

class ET_Notifications {

	public static function create_for_employee( $employee_id, $message ) {
		global $wpdb;
		$table = ET_Database::table( 'notifications' );
		$wpdb->insert( $table, array(
			'target_employee_id' => (int) $employee_id, 'target_admin' => 0,
			'message' => $message, 'is_read' => 0, 'created_at' => current_time( 'mysql' ),
		) );
	}

	public static function create_for_admin( $message ) {
		global $wpdb;
		$table = ET_Database::table( 'notifications' );
		$wpdb->insert( $table, array(
			'target_employee_id' => null, 'target_admin' => 1,
			'message' => $message, 'is_read' => 0, 'created_at' => current_time( 'mysql' ),
		) );
	}

	public static function for_employee( $employee_id, $limit = 30 ) {
		global $wpdb;
		$table = ET_Database::table( 'notifications' );
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE target_employee_id = %d ORDER BY id DESC LIMIT %d", (int) $employee_id, (int) $limit
		) );
	}

	public static function for_admin( $limit = 30 ) {
		global $wpdb;
		$table = ET_Database::table( 'notifications' );
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE target_admin = 1 ORDER BY id DESC LIMIT %d", (int) $limit ) );
	}

	public static function unread_count_for_employee( $employee_id ) {
		global $wpdb;
		$table = ET_Database::table( 'notifications' );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE target_employee_id = %d AND is_read = 0", (int) $employee_id ) );
	}

	public static function unread_count_for_admin() {
		global $wpdb;
		$table = ET_Database::table( 'notifications' );
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE target_admin = 1 AND is_read = 0" );
	}

	public static function mark_all_read_for_employee( $employee_id ) {
		global $wpdb;
		$table = ET_Database::table( 'notifications' );
		$wpdb->update( $table, array( 'is_read' => 1 ), array( 'target_employee_id' => (int) $employee_id ) );
	}

	public static function mark_all_read_for_admin() {
		global $wpdb;
		$table = ET_Database::table( 'notifications' );
		$wpdb->update( $table, array( 'is_read' => 1 ), array( 'target_admin' => 1 ) );
	}
}
