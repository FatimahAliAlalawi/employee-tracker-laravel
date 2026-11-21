<?php

/*
 * منقول من بلجن ووردبريس employee-tracker 2.9.1 — منطق العمل كما هو،
 * يعمل فوق Laravel عبر app/Support/helpers.php (لا اعتماد على ووردبريس).
 */

class ET_Departments {

	public static function all( $status = null ) {
		global $wpdb;
		$table = ET_Database::table( 'departments' );
		if ( $status ) {
			return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE status = %s ORDER BY name ASC", $status ) );
		}
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY name ASC" );
	}

	public static function get( $id ) {
		global $wpdb;
		$table = ET_Database::table( 'departments' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
	}

	public static function create( $data ) {
		global $wpdb;
		$table = ET_Database::table( 'departments' );
		$wpdb->insert( $table, array(
			'name'         => sanitize_text_field( $data['name'] ),
			'manager_name' => sanitize_text_field( $data['manager_name'] ?? '' ),
			'status'       => in_array( $data['status'] ?? 'active', array( 'active', 'inactive' ), true ) ? $data['status'] : 'active',
			'created_at'   => current_time( 'mysql' ),
		) );
		return $wpdb->insert_id;
	}

	public static function update( $id, $data ) {
		global $wpdb;
		$table = ET_Database::table( 'departments' );
		return $wpdb->update( $table, array(
			'name'         => sanitize_text_field( $data['name'] ),
			'manager_name' => sanitize_text_field( $data['manager_name'] ?? '' ),
			'status'       => in_array( $data['status'] ?? 'active', array( 'active', 'inactive' ), true ) ? $data['status'] : 'active',
		), array( 'id' => (int) $id ) );
	}

	public static function delete_or_deactivate( $id ) {
		global $wpdb;
		$employees_table = ET_Database::table( 'employees' );
		$linked = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$employees_table} WHERE department_id = %d", $id ) );
		$table = ET_Database::table( 'departments' );
		if ( (int) $linked > 0 ) {
			return $wpdb->update( $table, array( 'status' => 'inactive' ), array( 'id' => (int) $id ) );
		}
		return $wpdb->delete( $table, array( 'id' => (int) $id ) );
	}

	public static function count_active() {
		global $wpdb;
		$table = ET_Database::table( 'departments' );
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'active'" );
	}
}
