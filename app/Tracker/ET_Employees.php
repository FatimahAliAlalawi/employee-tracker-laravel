<?php

/*
 * منقول من بلجن ووردبريس employee-tracker 2.9.1 — منطق العمل كما هو،
 * يعمل فوق Laravel عبر app/Support/helpers.php (لا اعتماد على ووردبريس).
 */

class ET_Employees {

	/** يتحقق أن القيمة وقت بصيغة HH:MM صالحة، وإلا يعيد null (يعني: استخدم الإعداد العام) */
	public static function sanitize_time_override( $value ) {
		$value = trim( (string) $value );
		if ( $value === '' ) return null;
		if ( ! preg_match( '/^([01]\d|2[0-3]):([0-5]\d)$/', $value ) ) return null;
		return $value;
	}

	public static function get( $id ) {
		global $wpdb;
		$table = ET_Database::table( 'employees' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
	}

	/** قائمة الموظفين النشطين — لا تُعرض إلا بعد تسجيل الدخول (قوائم الزملاء والإدارة) */
	public static function active_for_picker() {
		global $wpdb;
		$table = ET_Database::table( 'employees' );
		$dept  = ET_Database::table( 'departments' );
		return $wpdb->get_results(
			"SELECT e.id, e.name, d.name AS department_name
			 FROM {$table} e LEFT JOIN {$dept} d ON d.id = e.department_id
			 WHERE e.status = 'active' ORDER BY e.name ASC"
		);
	}

	public static function query( $args = array() ) {
		global $wpdb;
		$table = ET_Database::table( 'employees' );
		$dept  = ET_Database::table( 'departments' );

		$args = wp_parse_args( $args, array(
			'search' => '', 'department_id' => 0, 'status' => '',
			'per_page' => 20, 'page' => 1, 'orderby' => 'name', 'order' => 'ASC',
		) );

		$where  = array( '1=1' );
		$params = array();

		if ( $args['search'] !== '' ) {
			$like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[] = '(e.name LIKE %s OR e.email LIKE %s OR e.phone LIKE %s)';
			array_push( $params, $like, $like, $like );
		}
		if ( ! empty( $args['department_id'] ) ) { $where[] = 'e.department_id = %d'; $params[] = (int) $args['department_id']; }
		if ( ! empty( $args['status'] ) )        { $where[] = 'e.status = %s'; $params[] = $args['status']; }

		$where_sql = implode( ' AND ', $where );
		$allowed_orderby = array( 'name', 'created_at', 'status' );
		$orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'name';
		$order   = strtoupper( $args['order'] ) === 'DESC' ? 'DESC' : 'ASC';

		$per_page = max( 1, (int) $args['per_page'] );
		$page     = max( 1, (int) $args['page'] );
		$offset   = ( $page - 1 ) * $per_page;

		$sql = "SELECT e.*, d.name AS department_name FROM {$table} e
				LEFT JOIN {$dept} d ON d.id = e.department_id
				WHERE {$where_sql} ORDER BY e.{$orderby} {$order} LIMIT %d OFFSET %d";
		$count_sql = "SELECT COUNT(*) FROM {$table} e WHERE {$where_sql}";

		$params_with_limit = array_merge( $params, array( $per_page, $offset ) );
		$items = $wpdb->get_results( $wpdb->prepare( $sql, $params_with_limit ) );
		$total = (int) ( empty( $params ) ? $wpdb->get_var( $count_sql ) : $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) );

		return array( 'items' => $items, 'total' => $total, 'pages' => (int) ceil( $total / $per_page ) );
	}

	/** كلمة مرور الموظف (تُحفظ مشفّرة في عمود access_code). الدخول بالبريد + كلمة المرور. */
	const MIN_CODE_LENGTH = 8;
	const MAX_CODE_LENGTH = 128;

	/** ينظّف كلمة المرور: أي أحرف مسموحة، بدون مسافات طرفية، بحد أقصى MAX_CODE_LENGTH */
	public static function sanitize_code( $code ) {
		return mb_substr( trim( (string) $code ), 0, self::MAX_CODE_LENGTH );
	}

	/** كلمات المرور مشفّرة ولا معنى لفرض عدم تكرارها — التمييز يتم بالبريد. للتوافق فقط. */
	public static function code_taken( $code, $exclude_id = 0 ) {
		return false;
	}

	/** يولّد كلمة مرور عشوائية سهلة القراءة (10 خانات، بدون أحرف متشابهة مثل 0/O و 1/l) — تُعرض للإدارة مرة واحدة */
	public static function generate_code() {
		$chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
		$code  = '';
		for ( $i = 0; $i < 10; $i++ ) {
			$code .= $chars[ wp_rand( 0, strlen( $chars ) - 1 ) ];
		}
		return $code;
	}

	/** بريد الموظف بصيغة موحّدة للمقارنة */
	public static function normalize_email( $email ) {
		return strtolower( trim( (string) $email ) );
	}

	/** الموظف النشط صاحب هذا البريد (للدخول) */
	public static function find_active_by_email( $email ) {
		$email = self::normalize_email( $email );
		if ( $email === '' ) return null;
		global $wpdb;
		$table = ET_Database::table( 'employees' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE LOWER(email) = %s AND status = 'active' ORDER BY id ASC LIMIT 1", $email ) );
	}

	/** هل البريد مستخدم لموظف آخر؟ (البريد هو اسم المستخدم، فلا يتكرر) */
	public static function email_taken( $email, $exclude_id = 0 ) {
		$email = self::normalize_email( $email );
		if ( $email === '' ) return false;
		global $wpdb;
		$table = ET_Database::table( 'employees' );
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE LOWER(email) = %s AND id <> %d LIMIT 1", $email, (int) $exclude_id ) );
	}

	/** عدد الموظفين النشطين بدون بريد (لا يستطيعون الدخول حتى يُضاف بريدهم) */
	public static function count_active_without_email() {
		global $wpdb;
		$table = ET_Database::table( 'employees' );
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'active' AND (email = '' OR email IS NULL)" );
	}

	/** يغيّر كلمة مرور الموظف (مشفّرة) — يُلغي تلقائيًا جلساته المفتوحة لأن بصمة التوكن تتغير */
	public static function set_password( $id, $password ) {
		global $wpdb;
		$table = ET_Database::table( 'employees' );
		self::clear_weak_flag( $id );
		return $wpdb->update( $table, array( 'access_code' => ET_Security::hash_code( $password ) ), array( 'id' => (int) $id ) );
	}

	/** هل كلمة مرور هذا الموظف مسجّلة كضعيفة (رمز قصير قديم من نسخة ووردبريس)؟ */
	public static function has_weak_code( $employee_id ) {
		return in_array( (int) $employee_id, array_map( 'intval', (array) get_option( 'et_weak_code_employees', array() ) ), true );
	}

	private static function clear_weak_flag( $employee_id ) {
		$weak = array_map( 'intval', (array) get_option( 'et_weak_code_employees', array() ) );
		$weak = array_values( array_diff( $weak, array( (int) $employee_id ) ) );
		update_option( 'et_weak_code_employees', $weak, false );
	}

	public static function create( $data ) {
		global $wpdb;
		$table = ET_Database::table( 'employees' );
		$code  = self::sanitize_code( $data['access_code'] ?? '' );
		if ( $code === '' ) { $code = self::generate_code(); }
		$code  = ET_Security::hash_code( $code );
		$wpdb->insert( $table, array(
			'name'          => sanitize_text_field( $data['name'] ),
			'department_id' => ! empty( $data['department_id'] ) ? (int) $data['department_id'] : null,
			'manager_name'  => sanitize_text_field( $data['manager_name'] ?? '' ),
			'phone'         => sanitize_text_field( $data['phone'] ?? '' ),
			'email'         => self::normalize_email( sanitize_email( $data['email'] ?? '' ) ),
			'shift'         => in_array( $data['shift'] ?? 'both', array( 'morning', 'evening', 'both' ), true ) ? $data['shift'] : 'both',
			'access_code'   => $code,
			'status'        => in_array( $data['status'] ?? 'active', array( 'active', 'inactive' ), true ) ? $data['status'] : 'active',
			'att_morning_start_override' => self::sanitize_time_override( $data['att_morning_start_override'] ?? '' ),
			'att_morning_end_override'   => self::sanitize_time_override( $data['att_morning_end_override'] ?? '' ),
			'att_evening_start_override' => self::sanitize_time_override( $data['att_evening_start_override'] ?? '' ),
			'att_evening_end_override'   => self::sanitize_time_override( $data['att_evening_end_override'] ?? '' ),
			'created_at'    => current_time( 'mysql' ),
		) );
		return $wpdb->insert_id;
	}

	public static function update( $id, $data ) {
		global $wpdb;
		$table = ET_Database::table( 'employees' );
		$code  = self::sanitize_code( $data['access_code'] ?? '' );
		if ( $code === '' ) {
			// لا نفرغ رمز موظف قائم بالخطأ — إذا تُرك الحقل فارغًا نُبقي رمزه الحالي (المشفّر) كما هو.
			$existing = self::get( $id );
			$code = ( $existing && $existing->access_code ) ? $existing->access_code : '';
		} else {
			$code = ET_Security::hash_code( $code );
			self::clear_weak_flag( $id );
		}
		return $wpdb->update( $table, array(
			'name'          => sanitize_text_field( $data['name'] ),
			'department_id' => ! empty( $data['department_id'] ) ? (int) $data['department_id'] : null,
			'manager_name'  => sanitize_text_field( $data['manager_name'] ?? '' ),
			'phone'         => sanitize_text_field( $data['phone'] ?? '' ),
			'email'         => self::normalize_email( sanitize_email( $data['email'] ?? '' ) ),
			'shift'         => in_array( $data['shift'] ?? 'both', array( 'morning', 'evening', 'both' ), true ) ? $data['shift'] : 'both',
			'access_code'   => $code,
			'status'        => in_array( $data['status'] ?? 'active', array( 'active', 'inactive' ), true ) ? $data['status'] : 'active',
			'att_morning_start_override' => self::sanitize_time_override( $data['att_morning_start_override'] ?? '' ),
			'att_morning_end_override'   => self::sanitize_time_override( $data['att_morning_end_override'] ?? '' ),
			'att_evening_start_override' => self::sanitize_time_override( $data['att_evening_start_override'] ?? '' ),
			'att_evening_end_override'   => self::sanitize_time_override( $data['att_evening_end_override'] ?? '' ),
		), array( 'id' => (int) $id ) );
	}

	public static function deactivate( $id ) {
		global $wpdb;
		$table = ET_Database::table( 'employees' );
		return $wpdb->update( $table, array( 'status' => 'inactive' ), array( 'id' => (int) $id ) );
	}

	public static function count_active() {
		global $wpdb;
		$table = ET_Database::table( 'employees' );
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'active'" );
	}

	/** كل الموظفين (لأي حالة) — تُستخدم في قوائم فلترة التقارير */
	public static function all() {
		global $wpdb;
		$table = ET_Database::table( 'employees' );
		$dept  = ET_Database::table( 'departments' );
		return $wpdb->get_results(
			"SELECT e.*, d.name AS department_name FROM {$table} e
			 LEFT JOIN {$dept} d ON d.id = e.department_id
			 ORDER BY e.name ASC"
		);
	}
}
