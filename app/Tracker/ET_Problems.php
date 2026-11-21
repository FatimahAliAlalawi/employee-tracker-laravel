<?php

/*
 * منقول من بلجن ووردبريس employee-tracker 2.9.1 — منطق العمل كما هو،
 * يعمل فوق Laravel عبر app/Support/helpers.php (لا اعتماد على ووردبريس).
 */

class ET_Problems {

	const STATUSES   = array( 'new', 'in_progress', 'waiting_action', 'resolved', 'closed' );
	const PRIORITIES = array( 'low', 'medium', 'high', 'critical' );

	public static function status_label( $status ) {
		$map = array( 'new' => 'جديدة', 'in_progress' => 'قيد المتابعة', 'waiting_action' => 'بانتظار إجراء', 'resolved' => 'تم الحل', 'closed' => 'مغلقة' );
		return $map[ $status ] ?? $status;
	}

	public static function priority_label( $priority ) {
		$map = array( 'low' => 'منخفض', 'medium' => 'متوسط', 'high' => 'عالي', 'critical' => 'حرج' );
		return $map[ $priority ] ?? $priority;
	}

	/** عند تعديل تحديث خلال مهلة التعديل: يحدّث مشكلته الموجودة بدل إنشاء مشكلة مكررة */
	public static function upsert_from_update( $update_id, $employee, $data ) {
		global $wpdb;
		$table    = ET_Database::table( 'problems' );
		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE update_id = %d ORDER BY id ASC LIMIT 1", (int) $update_id ) );
		if ( ! $existing ) return self::create_from_update( $update_id, $employee, $data );

		$description = ET_Security::sanitize_textarea( $data['problem_description'] ?? '' );
		$title = mb_substr( trim( $description ), 0, 80 );
		$wpdb->update( $table, array(
			'title'       => $title !== '' ? $title : 'مشكلة بدون عنوان',
			'description' => $description,
			'priority'    => in_array( $data['problem_priority'] ?? 'medium', self::PRIORITIES, true ) ? $data['problem_priority'] : 'medium',
			'impact'      => sanitize_text_field( $data['problem_impact'] ?? '' ),
			'updated_at'  => current_time( 'mysql' ),
		), array( 'id' => (int) $existing ) );
		return (int) $existing;
	}

	public static function create_from_update( $update_id, $employee, $data ) {
		global $wpdb;
		$table = ET_Database::table( 'problems' );

		$description = ET_Security::sanitize_textarea( $data['problem_description'] ?? '' );
		$title = mb_substr( trim( $description ), 0, 80 );
		if ( $title === '' ) $title = 'مشكلة بدون عنوان';

		$wpdb->insert( $table, array(
			'update_id'     => $update_id,
			'employee_id'   => $employee->id,
			'department_id' => $employee->department_id,
			'title'         => $title,
			'description'   => $description,
			'priority'      => in_array( $data['problem_priority'] ?? 'medium', self::PRIORITIES, true ) ? $data['problem_priority'] : 'medium',
			'impact'        => sanitize_text_field( $data['problem_impact'] ?? '' ),
			'status'        => 'new',
			'created_at'    => current_time( 'mysql' ),
			'updated_at'    => current_time( 'mysql' ),
		) );

		$problem_id = $wpdb->insert_id;
		self::log( $problem_id, 'created', '', '', sprintf( 'أنشأ %s المشكلة تلقائيًا من التحديث اليومي.', $employee->name ) );
		return $problem_id;
	}

	public static function get( $id ) {
		global $wpdb;
		$table = ET_Database::table( 'problems' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
	}

	public static function query( $args = array() ) {
		global $wpdb;
		$table = ET_Database::table( 'problems' );
		$emp   = ET_Database::table( 'employees' );
		$dept  = ET_Database::table( 'departments' );

		$args = wp_parse_args( $args, array(
			'search' => '', 'employee_id' => 0, 'department_id' => 0, 'priority' => '', 'status' => '',
			'date_from' => '', 'date_to' => '', 'per_page' => 20, 'page' => 1,
		) );

		$where  = array( '1=1' );
		$params = array();

		if ( $args['search'] !== '' ) {
			$like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[] = '(p.title LIKE %s OR p.description LIKE %s OR e.name LIKE %s)';
			array_push( $params, $like, $like, $like );
		}
		foreach ( array( 'employee_id' => 'p.employee_id', 'department_id' => 'p.department_id' ) as $key => $col ) {
			if ( ! empty( $args[ $key ] ) ) { $where[] = "{$col} = %d"; $params[] = (int) $args[ $key ]; }
		}
		if ( ! empty( $args['priority'] ) ) { $where[] = 'p.priority = %s'; $params[] = $args['priority']; }
		if ( ! empty( $args['status'] ) )   { $where[] = 'p.status = %s'; $params[] = $args['status']; }
		if ( ! empty( $args['date_from'] ) ) { $where[] = 'DATE(p.created_at) >= %s'; $params[] = $args['date_from']; }
		if ( ! empty( $args['date_to'] ) )   { $where[] = 'DATE(p.created_at) <= %s'; $params[] = $args['date_to']; }

		$where_sql = implode( ' AND ', $where );
		$per_page  = max( 1, (int) $args['per_page'] );
		$page      = max( 1, (int) $args['page'] );
		$offset    = ( $page - 1 ) * $per_page;

		$sql = "SELECT p.*, e.name AS employee_name, d.name AS department_name
				FROM {$table} p
				LEFT JOIN {$emp} e ON e.id = p.employee_id
				LEFT JOIN {$dept} d ON d.id = p.department_id
				WHERE {$where_sql} ORDER BY p.created_at DESC LIMIT %d OFFSET %d";
		$count_sql = "SELECT COUNT(*) FROM {$table} p LEFT JOIN {$emp} e ON e.id = p.employee_id WHERE {$where_sql}";

		$params_with_limit = array_merge( $params, array( $per_page, $offset ) );
		$items = $wpdb->get_results( $wpdb->prepare( $sql, $params_with_limit ) );
		$total = (int) ( empty( $params ) ? $wpdb->get_var( $count_sql ) : $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) );

		return array( 'items' => $items, 'total' => $total, 'pages' => (int) ceil( $total / $per_page ) );
	}

	/** كل المشاكل المطابقة للفلاتر بدون Pagination — تُستخدم للتصدير */
	public static function query_all( $args = array() ) {
		global $wpdb;
		$table = ET_Database::table( 'problems' );
		$emp   = ET_Database::table( 'employees' );
		$dept  = ET_Database::table( 'departments' );

		$args = wp_parse_args( $args, array(
			'employee_id' => 0, 'department_id' => 0, 'date_from' => '', 'date_to' => '',
		) );

		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['employee_id'] ) )   { $where[] = 'p.employee_id = %d'; $params[] = (int) $args['employee_id']; }
		if ( ! empty( $args['department_id'] ) ) { $where[] = 'p.department_id = %d'; $params[] = (int) $args['department_id']; }
		if ( ! empty( $args['date_from'] ) )     { $where[] = 'DATE(p.created_at) >= %s'; $params[] = $args['date_from']; }
		if ( ! empty( $args['date_to'] ) )       { $where[] = 'DATE(p.created_at) <= %s'; $params[] = $args['date_to']; }

		$where_sql = implode( ' AND ', $where );

		$sql = "SELECT p.*, e.name AS employee_name, d.name AS department_name
				FROM {$table} p
				LEFT JOIN {$emp} e ON e.id = p.employee_id
				LEFT JOIN {$dept} d ON d.id = p.department_id
				WHERE {$where_sql} ORDER BY p.created_at DESC";

		if ( empty( $params ) ) return $wpdb->get_results( $sql );
		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	public static function update_admin_fields( $id, $data, $actor_name = '' ) {
		global $wpdb;
		$table   = ET_Database::table( 'problems' );
		$problem = self::get( $id );
		if ( ! $problem ) return new WP_Error( 'et_not_found', __( 'المشكلة غير موجودة.') );

		$update_fields = array( 'updated_at' => current_time( 'mysql' ) );
		$actor = $actor_name ?: wp_get_current_user()->display_name;

		if ( isset( $data['status'] ) && in_array( $data['status'], self::STATUSES, true ) && $data['status'] !== $problem->status ) {
			self::log( $id, 'status_change', self::status_label( $problem->status ), self::status_label( $data['status'] ), sprintf( 'قام %s بتغيير الحالة.', $actor ) );
			$update_fields['status'] = $data['status'];
			if ( in_array( $data['status'], array( 'resolved', 'closed' ), true ) && empty( $problem->resolved_at ) ) {
				$update_fields['resolved_at'] = current_time( 'mysql' );
			}
		}
		if ( isset( $data['priority'] ) && in_array( $data['priority'], self::PRIORITIES, true ) && $data['priority'] !== $problem->priority ) {
			self::log( $id, 'priority_change', self::priority_label( $problem->priority ), self::priority_label( $data['priority'] ), sprintf( 'قام %s بتغيير الأولوية.', $actor ) );
			$update_fields['priority'] = $data['priority'];
		}
		if ( isset( $data['assigned_to'] ) && sanitize_text_field( $data['assigned_to'] ) !== $problem->assigned_to ) {
			self::log( $id, 'assigned', $problem->assigned_to, sanitize_text_field( $data['assigned_to'] ), sprintf( 'قام %s بتعيين المسؤول.', $actor ) );
			$update_fields['assigned_to'] = sanitize_text_field( $data['assigned_to'] );
		}
		if ( isset( $data['solution'] ) && ET_Security::sanitize_textarea( $data['solution'] ) !== $problem->solution ) {
			self::log( $id, 'solution_updated', '', '', sprintf( 'أضاف %s الحل/الملاحظة.', $actor ) );
			$update_fields['solution'] = ET_Security::sanitize_textarea( $data['solution'] );
		}

		if ( count( $update_fields ) > 1 ) {
			$wpdb->update( $table, $update_fields, array( 'id' => (int) $id ) );
		}
		return true;
	}

	public static function log( $problem_id, $action, $old_value, $new_value, $notes = '' ) {
		global $wpdb;
		$table = ET_Database::table( 'problem_logs' );
		$wpdb->insert( $table, array(
			'problem_id' => (int) $problem_id,
			'action'     => sanitize_text_field( $action ),
			'old_value'  => sanitize_text_field( $old_value ),
			'new_value'  => sanitize_text_field( $new_value ),
			'notes'      => sanitize_text_field( $notes ),
			'created_at' => current_time( 'mysql' ),
		) );
	}

	public static function history( $problem_id ) {
		global $wpdb;
		$table = ET_Database::table( 'problem_logs' );
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE problem_id = %d ORDER BY created_at ASC", $problem_id ) );
	}

	public static function count_open() {
		global $wpdb;
		$table = ET_Database::table( 'problems' );
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status NOT IN ('resolved','closed')" );
	}

	public static function count_critical_open() {
		global $wpdb;
		$table = ET_Database::table( 'problems' );
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status NOT IN ('resolved','closed') AND priority = 'critical'" );
	}

	public static function count_today() {
		global $wpdb;
		$table = ET_Database::table( 'problems' );
		$today = current_time( 'Y-m-d' );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE DATE(created_at) = %s", $today ) );
	}

	public static function most_recurring( $limit = 5 ) {
		global $wpdb;
		$table = ET_Database::table( 'problems' );
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT title, COUNT(*) AS occurrences FROM {$table} GROUP BY title HAVING occurrences > 1 ORDER BY occurrences DESC LIMIT %d",
			$limit
		) );
	}
}
