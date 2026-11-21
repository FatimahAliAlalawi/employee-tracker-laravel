<?php

/*
 * منقول من بلجن ووردبريس employee-tracker 2.9.1 — منطق العمل كما هو،
 * يعمل فوق Laravel عبر app/Support/helpers.php (لا اعتماد على ووردبريس).
 */

class ET_PermissionRequests {

	public static function table() {
		return ET_Database::table( 'permission_requests' );
	}

	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . self::table() . " WHERE id = %d", $id ) );
	}

	public static function create( $employee_id, $perm_date, $time_out, $time_return, $reason, $notes = '' ) {
		global $wpdb;
		$employee = ET_Employees::get( $employee_id );
		if ( ! $employee ) {
			return new WP_Error( 'et_not_found', __( 'الموظف غير موجود.'), array( 'status' => 404 ) );
		}
		// وقت العودة المتوقع اختياري (قد لا يعرفه الموظف مسبقًا) — التاريخ ووقت الخروج فقط إلزاميان
		if ( empty( $perm_date ) || empty( $time_out ) ) {
			return new WP_Error( 'et_permission_missing', __( 'الرجاء تعبئة التاريخ ووقت الخروج.'), array( 'status' => 400 ) );
		}
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $perm_date ) || ! strtotime( $perm_date ) ) {
			return new WP_Error( 'et_permission_bad_date', __( 'تاريخ الاستئذان غير صحيح.'), array( 'status' => 400 ) );
		}
		$raw_return  = (string) $time_return;
		$time_out    = et_normalize_hhmm( $time_out );
		$time_return = $raw_return === '' ? '' : et_normalize_hhmm( $raw_return );
		if ( $time_out === '' || ( $raw_return !== '' && $time_return === '' ) ) {
			return new WP_Error( 'et_permission_bad_time', __( 'وقت الخروج أو العودة غير صحيح (الصيغة المطلوبة 10:30).'), array( 'status' => 400 ) );
		}
		$wpdb->insert( self::table(), array(
			'employee_id'    => (int) $employee_id,
			'department_id'  => $employee->department_id ? (int) $employee->department_id : null,
			'perm_date'      => date( 'Y-m-d', strtotime( $perm_date ) ),
			'time_out'       => sanitize_text_field( $time_out ),
			'time_return'    => sanitize_text_field( (string) $time_return ),
			'reason'         => sanitize_text_field( $reason ),
			'notes'          => ET_Security::sanitize_textarea( $notes ),
			'status'         => 'pending',
			'created_at'     => current_time( 'mysql' ),
			'updated_at'     => current_time( 'mysql' ),
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

	/** الاستئذان لا يخصم من رصيد الإجازة السنوية إطلاقًا */
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
