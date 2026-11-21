<?php

/*
 * منقول من بلجن ووردبريس employee-tracker 2.9.1 — منطق العمل كما هو،
 * يعمل فوق Laravel عبر app/Support/helpers.php (لا اعتماد على ووردبريس).
 */

class ET_LeaveRequests {

	public static function table() {
		return ET_Database::table( 'leave_requests' );
	}

	public static function get( $id ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
	}

	/** إنشاء طلب إجازة سنوية جديد من الموظف — لا خصم من الرصيد قبل الموافقة */
	public static function create( $employee_id, $start_date, $end_date, $notes = '' ) {
		$days = ET_LeaveCalc::working_days( $start_date, $end_date );
		if ( is_wp_error( $days ) ) return $days;
		if ( $days < 1 ) {
			return new WP_Error( 'et_leave_zero_days', __( 'الفترة المختارة لا تحتوي على أي يوم عمل فعلي (الجمعة والسبت لا تُحتسب).'), array( 'status' => 400 ) );
		}

		$overlap = self::overlap_error( $employee_id, $start_date, $end_date );
		if ( $overlap ) return $overlap;

		// الطلبات قيد المراجعة تُحجز من الرصيد، حتى لا تتجاوز عدة طلبات معلّقة معًا الرصيد المتاح
		$balance   = ET_LeaveBalances::compute( $employee_id );
		$available = round( $balance['remaining_days'] - self::pending_days( $employee_id ), 2 );
		if ( $days > $available ) {
			/* translators: 1: الرصيد المتبقي 2: عدد الأيام المطلوبة */
			return new WP_Error( 'et_leave_insufficient', sprintf( __( 'رصيدك المتاح (%1$s يوم، بعد خصم الطلبات قيد المراجعة) لا يكفي لعدد أيام هذا الطلب (%2$s يوم).'), $available, $days ), array( 'status' => 400 ) );
		}

		return self::insert( $employee_id, $start_date, $end_date, $days, $notes, 'pending', 0, $balance['remaining_days'] );
	}

	/** تسجيل إجازة سابقة من الإدارة (قبل استخدام النظام) — تُحسب "موافق عليها" فورًا وتُخصم من الرصيد */
	public static function add_past( $employee_id, $start_date, $end_date, $notes = '' ) {
		$days = ET_LeaveCalc::working_days( $start_date, $end_date );
		if ( is_wp_error( $days ) ) return $days;
		if ( $days < 1 ) {
			return new WP_Error( 'et_leave_zero_days', __( 'الفترة المختارة لا تحتوي على أي يوم عمل فعلي (الجمعة والسبت لا تُحتسب).'), array( 'status' => 400 ) );
		}
		$overlap = self::overlap_error( $employee_id, $start_date, $end_date );
		if ( $overlap ) return $overlap;
		$balance = ET_LeaveBalances::compute( $employee_id );
		$id = self::insert( $employee_id, $start_date, $end_date, $days, $notes, 'approved', 1, $balance['remaining_days'] );
		if ( is_wp_error( $id ) ) return $id;
		global $wpdb;
		$wpdb->update( self::table(), array( 'decided_at' => current_time( 'mysql' ), 'admin_notes' => 'إجازة مسجلة من الإدارة' ), array( 'id' => $id ) );
		return $id;
	}

	/** خطأ إن كان للموظف طلب إجازة سنوية قيد المراجعة أو معتمد يتداخل مع الفترة، وإلا null */
	private static function overlap_error( $employee_id, $start_date, $end_date ) {
		global $wpdb;
		$table = self::table();
		$hit = $wpdb->get_row( $wpdb->prepare(
			"SELECT start_date, end_date FROM {$table} WHERE employee_id = %d AND status IN ('pending','approved') AND start_date <= %s AND end_date >= %s LIMIT 1",
			(int) $employee_id, date( 'Y-m-d', strtotime( $end_date ) ), date( 'Y-m-d', strtotime( $start_date ) )
		) );
		if ( ! $hit ) return null;
		/* translators: 1: بداية الطلب الموجود 2: نهايته */
		return new WP_Error( 'et_leave_overlap', sprintf( __( 'يوجد طلب إجازة آخر (قيد المراجعة أو معتمد) يتداخل مع هذه الفترة: من %1$s إلى %2$s.'), $hit->start_date, $hit->end_date ), array( 'status' => 400 ) );
	}

	/** مجموع أيام طلبات الإجازة السنوية قيد المراجعة للموظف */
	private static function pending_days( $employee_id ) {
		global $wpdb;
		$table = self::table();
		return (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(days_count),0) FROM {$table} WHERE employee_id = %d AND status = 'pending'", (int) $employee_id ) );
	}

	private static function insert( $employee_id, $start_date, $end_date, $days, $notes, $status, $added_by_admin, $balance_before ) {
		global $wpdb;
		$employee = ET_Employees::get( $employee_id );
		if ( ! $employee ) {
			return new WP_Error( 'et_not_found', __( 'الموظف غير موجود.'), array( 'status' => 404 ) );
		}
		$wpdb->insert( self::table(), array(
			'employee_id'    => (int) $employee_id,
			'department_id'  => $employee->department_id ? (int) $employee->department_id : null,
			'start_date'     => date( 'Y-m-d', strtotime( $start_date ) ),
			'end_date'       => date( 'Y-m-d', strtotime( $end_date ) ),
			'days_count'     => $days,
			'notes'          => ET_Security::sanitize_textarea( $notes ),
			'status'         => $status,
			'balance_before' => $balance_before,
			'added_by_admin' => $added_by_admin ? 1 : 0,
			'created_at'     => current_time( 'mysql' ),
			'updated_at'     => current_time( 'mysql' ),
		) );
		return $wpdb->insert_id;
	}

	public static function list_for_employee( $employee_id ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE employee_id = %d ORDER BY created_at DESC", $employee_id ) );
	}

	public static function list_all( $status = '', $employee_id = 0 ) {
		global $wpdb;
		$table = self::table();
		$emp   = ET_Database::table( 'employees' );
		$where  = array( '1=1' );
		$params = array();
		if ( $status !== '' )      { $where[] = 'r.status = %s';      $params[] = $status; }
		if ( $employee_id > 0 )    { $where[] = 'r.employee_id = %d'; $params[] = $employee_id; }
		$sql = "SELECT r.*, e.name AS employee_name FROM {$table} r LEFT JOIN {$emp} e ON e.id = r.employee_id WHERE " . implode( ' AND ', $where ) . ' ORDER BY r.created_at DESC LIMIT 300';
		return empty( $params ) ? $wpdb->get_results( $sql ) : $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	/** موافقة/رفض من الإدارة — الخصم من الرصيد يتم فقط هنا (عبر status='approved' المحسوب لحظيًا ضمن ET_LeaveBalances) */
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

		if ( $status === 'approved' ) {
			$balance = ET_LeaveBalances::compute( $req->employee_id );
			if ( (float) $req->days_count > $balance['remaining_days'] ) {
				return new WP_Error( 'et_leave_insufficient', __( 'رصيد الموظف الحالي لا يكفي للموافقة على هذا الطلب.'), array( 'status' => 400 ) );
			}
		}

		$updated = $wpdb->update( self::table(), array(
			'status'            => $status,
			'admin_notes'       => ET_Security::sanitize_textarea( $admin_notes ),
			'rejection_reason'  => $status === 'rejected' ? ET_Security::sanitize_textarea( $rejection_reason ) : '',
			'decided_at'        => current_time( 'mysql' ),
			'updated_at'        => current_time( 'mysql' ),
		), array( 'id' => (int) $id, 'status' => 'pending' ) );
		if ( ! $updated ) {
			return new WP_Error( 'et_leave_already_decided', __( 'تم البت في هذا الطلب مسبقًا.'), array( 'status' => 400 ) );
		}

		return true;
	}

	/** إلغاء طلب من الموظف نفسه — بشرط أنه ما زال قيد المراجعة وأنه صاحب الطلب */
	public static function cancel( $id, $employee_id ) {
		global $wpdb;
		$req = self::get( $id );
		if ( ! $req || (int) $req->employee_id !== (int) $employee_id ) {
			return new WP_Error( 'et_not_found', __( 'الطلب غير موجود.'), array( 'status' => 404 ) );
		}
		if ( $req->status !== 'pending' ) {
			return new WP_Error( 'et_leave_cannot_cancel', __( 'لا يمكن إلغاء طلب تم البت فيه مسبقًا.'), array( 'status' => 400 ) );
		}
		$wpdb->update( self::table(), array( 'status' => 'cancelled', 'updated_at' => current_time( 'mysql' ) ), array( 'id' => (int) $id ) );
		return true;
	}
}
