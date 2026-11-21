<?php

/*
 * منقول من بلجن ووردبريس employee-tracker 2.9.1 — منطق العمل كما هو،
 * يعمل فوق Laravel عبر app/Support/helpers.php (لا اعتماد على ووردبريس).
 */

class ET_SickLeaveRequests {

	public static function table() {
		return ET_Database::table( 'sick_leave_requests' );
	}

	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . self::table() . " WHERE id = %d", $id ) );
	}

	public static function create( $employee_id, $start_date, $end_date, $notes = '', $attachment_url = '', $attachment_name = '' ) {
		$days = ET_LeaveCalc::calendar_days( $start_date, $end_date );
		if ( is_wp_error( $days ) ) return $days;

		global $wpdb;
		$employee = ET_Employees::get( $employee_id );
		if ( ! $employee ) {
			return new WP_Error( 'et_not_found', __( 'الموظف غير موجود.'), array( 'status' => 404 ) );
		}
		$wpdb->insert( self::table(), array(
			'employee_id'      => (int) $employee_id,
			'department_id'    => $employee->department_id ? (int) $employee->department_id : null,
			'start_date'       => date( 'Y-m-d', strtotime( $start_date ) ),
			'end_date'         => date( 'Y-m-d', strtotime( $end_date ) ),
			'days_count'       => $days,
			'notes'            => ET_Security::sanitize_textarea( $notes ),
			'attachment_url'   => $attachment_url ? esc_url_raw( $attachment_url ) : null,
			'attachment_name'  => $attachment_name ? sanitize_file_name( $attachment_name ) : null,
			'status'           => 'pending',
			'created_at'       => current_time( 'mysql' ),
			'updated_at'       => current_time( 'mysql' ),
		) );
		return $wpdb->insert_id;
	}

	public static function list_for_employee( $employee_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . self::table() . " WHERE employee_id = %d ORDER BY created_at DESC", $employee_id ) );
	}

	public static function list_all( $status = '', $employee_id = 0 ) {
		global $wpdb;
		$emp = ET_Database::table( 'employees' );
		$where  = array( '1=1' );
		$params = array();
		if ( $status !== '' )   { $where[] = 'r.status = %s';      $params[] = $status; }
		if ( $employee_id > 0 ) { $where[] = 'r.employee_id = %d'; $params[] = $employee_id; }
		$sql = "SELECT r.*, e.name AS employee_name FROM " . self::table() . " r LEFT JOIN {$emp} e ON e.id = r.employee_id WHERE " . implode( ' AND ', $where ) . ' ORDER BY r.created_at DESC LIMIT 300';
		return empty( $params ) ? $wpdb->get_results( $sql ) : $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	/** الإجازة المرضية لا تخصم من الرصيد السنوي إطلاقًا — الموافقة/الرفض هنا لا يمسّان ET_LeaveBalances مطلقًا */
	public static function decide( $id, $status, $admin_notes = '', $rejection_reason = '' ) {
		global $wpdb;
		$req = self::get( $id );
		if ( ! $req ) return new WP_Error( 'et_not_found', __( 'الطلب غير موجود.'), array( 'status' => 404 ) );
		if ( $req->status !== 'pending' ) {
			return new WP_Error( 'et_leave_already_decided', __( 'تم البت في هذا الطلب مسبقًا.'), array( 'status' => 400 ) );
		}
		if ( ! in_array( $status, array( 'approved', 'rejected' ), true ) ) {
			return new WP_Error( 'et_leave_bad_status', __( 'حالة غير صحيحة.'), array( 'status' => 400 ) );
		}
		$wpdb->update( self::table(), array(
			'status'           => $status,
			'admin_notes'      => ET_Security::sanitize_textarea( $admin_notes ),
			'rejection_reason' => $status === 'rejected' ? ET_Security::sanitize_textarea( $rejection_reason ) : '',
			'decided_at'       => current_time( 'mysql' ),
			'updated_at'       => current_time( 'mysql' ),
		), array( 'id' => (int) $id ) );
		return true;
	}
}
