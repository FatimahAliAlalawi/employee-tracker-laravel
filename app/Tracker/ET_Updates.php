<?php

/*
 * منقول من بلجن ووردبريس employee-tracker 2.9.1 — منطق العمل كما هو،
 * يعمل فوق Laravel عبر app/Support/helpers.php (لا اعتماد على ووردبريس).
 */

class ET_Updates {

	/** @param string|null $source مرّر 'manual' لتجاهل أي تحديث أُنشئ تلقائيًا بمجرد تعليم مهمة "منجزة"
	 *  (source='task_auto') عند فحص "هل أرسل الموظف تحديثه اليدوي اليوم؟" — حتى لا يُحسَب إنجاز مهمة وحده
	 *  كأنه التحديث اليدوي الكامل نفسه (يمنع الموظف من التعديل عليه، أو يُظهر له شارة "تم الإرسال" زورًا). */
	public static function get_today_update( $employee_id, $date = null, $source = null ) {
		global $wpdb;
		$table = ET_Database::table( 'daily_updates' );
		$date  = $date ?: current_time( 'Y-m-d' );
		if ( $source ) {
			return $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM {$table} WHERE employee_id = %d AND update_date = %s AND source = %s ORDER BY id DESC LIMIT 1",
				$employee_id, $date, $source
			) );
		}
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE employee_id = %d AND update_date = %s ORDER BY id DESC LIMIT 1",
			$employee_id, $date
		) );
	}

	public static function get( $id ) {
		global $wpdb;
		$table = ET_Database::table( 'daily_updates' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
	}

	public static function create( $employee_id, $data ) {
		global $wpdb;
		$settings = ET_Settings::get();
		$today    = current_time( 'Y-m-d' );

		$employee = ET_Employees::get( $employee_id );
		if ( ! $employee ) {
			return new WP_Error( 'et_employee_not_found', __( 'الموظف غير موجود.'), array( 'status' => 404 ) );
		}

		$has_problem = ! empty( $data['has_problem'] ) ? 1 : 0;
		$table       = ET_Database::table( 'daily_updates' );
		$raw_source  = $data['source'] ?? 'manual';
		$source      = in_array( $raw_source, array( 'manual', 'task_auto' ), true ) ? $raw_source : 'manual';

		$fields = array(
			'achievements'            => ET_Security::sanitize_textarea( $data['achievements'] ?? '' ),
			'current_tasks'           => ET_Security::sanitize_textarea( $data['current_tasks'] ?? '' ),
			'has_problem'             => $has_problem,
			'problem_description'     => $has_problem ? ET_Security::sanitize_textarea( $data['problem_description'] ?? '' ) : '',
			'problem_priority'        => $has_problem ? sanitize_text_field( $data['problem_priority'] ?? 'medium' ) : null,
			'problem_impact'          => $has_problem ? sanitize_text_field( $data['problem_impact'] ?? '' ) : null,
			'assistance_required'     => ! empty( $data['assistance_required'] ) ? 1 : 0,
			'assistance_description'  => ET_Security::sanitize_textarea( $data['assistance_description'] ?? '' ),
			'tomorrow_plan'           => ET_Security::sanitize_textarea( $data['tomorrow_plan'] ?? '' ),
			'source'                  => $source,
			'updated_at'              => current_time( 'mysql' ),
		);
		if ( ! empty( $data['attachments'] ) ) {
			$fields['attachments'] = wp_json_encode( $data['attachments'] );
		}

		// تحديث تلقائي (source='task_auto'): يُنشأ فقط لمجرد تعليم موظف مهمة "منجزة" (بدل كتابة تحديث
		// يدوي) — كل مهمة تُعلَّم منجزة تُنشئ سجلها المستقل الخاص بها دومًا (لا يُدمَج مع غيره ولا يخضع
		// لمنطق "نافذة التعديل"/"تحديث واحد باليوم" الخاص بالتحديث اليدوي)، لكنه لا يزال يُحتسب كـ"تحديث
		// لهذا اليوم" لأغراض قائمة "لم يرسلوا تحديثهم" وتقرير التحديثات وسلسلة الالتزام (كلها تُحصي بحسب
		// update_date فقط بلا تمييز للمصدر) — بالضبط كما طلب المستخدم: "يصير عنده خيارين، يدوي أو تعليم
		// المهمة فقط، وكلاهما يُحسب كتحديث فعلي لليوم".
		if ( $source === 'task_auto' ) {
			$fields['employee_id']   = $employee_id;
			$fields['department_id'] = $employee->department_id;
			$fields['update_date']   = $today;
			$fields['created_at']    = current_time( 'mysql' );
			if ( ! isset( $fields['attachments'] ) ) $fields['attachments'] = '';
			$wpdb->insert( $table, $fields );
			return $wpdb->insert_id;
		}

		// من هنا فصاعدًا: مسار التحديث اليدوي الأصلي بلا أي تغيير — نتجاهل أي تحديثات تلقائية سابقة
		// اليوم عند فحص "هل أرسل تحديثه اليوم؟" (source='manual' فقط) حتى لا يمنع تعليم مهمة "منجزة"
		// الموظف من إرسال تحديثه اليدوي الحقيقي لاحقًا بنفس اليوم، ولا يُظهر له خطأ "انتهت مهلة التعديل"
		// بسبب تحديث تلقائي هو لم يكتبه أصلاً.
		$existing = self::get_today_update( $employee_id, $today, 'manual' );

		// حالة وجود تحديث سابق اليوم: إما نافذة تعديل مسموحة أو منع تام
		if ( $existing && empty( $settings['allow_multiple_updates'] ) ) {
			$blocked = self::manual_submission_blocked( $employee_id );
			if ( $blocked ) return $blocked;
			// داخل نافذة التعديل: تحديث نفس السجل بدل إنشاء سجل جديد
			$wpdb->update( $table, $fields, array( 'id' => $existing->id ) );
			$update_id = $existing->id;

			if ( $has_problem ) {
				ET_Problems::upsert_from_update( $update_id, $employee, $data );
			}
			return $update_id;
		}

		// تحديث جديد (اليوم الأول أو النظام يسمح بأكثر من تحديث يوميًا)
		$fields['employee_id']   = $employee_id;
		$fields['department_id'] = $employee->department_id;
		$fields['update_date']   = $today;
		$fields['created_at']    = current_time( 'mysql' );
		if ( ! isset( $fields['attachments'] ) ) $fields['attachments'] = '';

		$wpdb->insert( $table, $fields );
		$update_id = $wpdb->insert_id;

		if ( $has_problem ) {
			ET_Problems::create_from_update( $update_id, $employee, $data );
		}

		return $update_id;
	}

	/** يبني نص إشعار "تحديث يومي" (نفس الصيغة المستخدمة بالضبط لتحديث يدوي حقيقي) — يُستخدم من مسارين معًا:
	 *  إرسال تحديث يدوي فعلي (submit_update)، وتعليم مهمة "منجزة" مباشرة بدل كتابة تحديث (notify_member_completed)
	 *  حتى يصل البريد للإدارة بنفس الشكل تمامًا في الحالتين، فتماشيًا مع طلب المستخدم: "يرسله للإدارة إيميل
	 *  كأنه سجّل تحديثه اليومي اليدوي" بغض النظر عن أي الطريقتين استخدمها الموظف فعليًا.
	 *
	 * @param object $employee    صف الموظف (يحتاج ->name).
	 * @param string $dept_name   اسم القسم الجاهز للعرض (أو '—').
	 * @param array  $data        نفس شكل $data المُمرَّر لـ create() (achievements/current_tasks/has_problem/...).
	 * @param bool   $full        true لنسخة كاملة (بريد)، false لنسخة مختصرة (واتساب).
	 */
	public static function build_summary_message( $employee, $dept_name, $data, $full = true ) {
		$has_problem = ! empty( $data['has_problem'] );
		$needs_help  = ! empty( $data['assistance_required'] );

		$clip = function ( $text, $limit = 200 ) {
			$text = trim( (string) $text );
			if ( $text === '' ) return '—';
			if ( function_exists( 'mb_strlen' ) && mb_strlen( $text ) > $limit ) {
				return mb_substr( $text, 0, $limit ) . '…';
			}
			return $text;
		};

		$m  = ( ( $data['source'] ?? '' ) === 'task_auto' ) ? "✅ مهمة منجزة — بانتظار اعتماد الإدارة\n" : "✅ تحديث يومي جديد\n";
		$m .= 'الموظف: ' . $employee->name . "\n";
		$m .= 'القسم: ' . $dept_name . "\n";
		$m .= 'الوقت: ' . current_time( 'Y-m-d H:i' ) . "\n";
		$m .= "\n";

		$m .= "📌 الإنجازات:\n" . ( $full ? ( trim( (string) ( $data['achievements'] ?? '' ) ) !== '' ? trim( $data['achievements'] ) : '—' ) : $clip( $data['achievements'] ?? '' ) ) . "\n\n";
		$m .= "🗂️ المهام الحالية:\n" . ( $full ? ( trim( (string) ( $data['current_tasks'] ?? '' ) ) !== '' ? trim( $data['current_tasks'] ) : '—' ) : $clip( $data['current_tasks'] ?? '' ) ) . "\n";

		if ( $has_problem ) {
			$m .= "\n⚠️ مشكلة مسجّلة:\n";
			$m .= ( $full ? ( trim( (string) ( $data['problem_description'] ?? '' ) ) !== '' ? trim( $data['problem_description'] ) : '—' ) : $clip( $data['problem_description'] ?? '', 150 ) ) . "\n";
			if ( ! empty( $data['problem_priority'] ) ) $m .= 'الأولوية: ' . $data['problem_priority'] . "\n";
			if ( ! empty( $data['problem_impact'] ) )   $m .= 'الأثر: ' . $data['problem_impact'] . "\n";
		}

		if ( $needs_help ) {
			$m .= "\n🆘 يحتاج مساعدة:\n" . ( $full ? ( trim( (string) ( $data['assistance_description'] ?? '' ) ) !== '' ? trim( $data['assistance_description'] ) : '—' ) : $clip( $data['assistance_description'] ?? '', 150 ) ) . "\n";
		}

		if ( ! empty( $data['tomorrow_plan'] ) ) {
			$m .= "\n📅 خطة الغد:\n" . ( $full ? trim( $data['tomorrow_plan'] ) : $clip( $data['tomorrow_plan'] ) ) . "\n";
		}

		if ( ! empty( $data['attachments'] ) && is_array( $data['attachments'] ) ) {
			$m .= "\n📎 المرفقات (" . count( $data['attachments'] ) . "):\n";
			foreach ( $data['attachments'] as $att ) {
				$name = ! empty( $att['name'] ) ? $att['name'] : 'ملف';
				$url  = ! empty( $att['url'] ) ? $att['url'] : '';
				$m .= '- ' . $name . ( $url ? ( ' : ' . $url ) : '' ) . "\n";
			}
		}

		return trim( $m );
	}

	/** خطأ "أرسلت تحديثك وانتهت مهلة التعديل" إن كان إرسال تحديث يدوي جديد مرفوضًا الآن، وإلا null */
	public static function manual_submission_blocked( $employee_id ) {
		$settings = ET_Settings::get();
		if ( ! empty( $settings['allow_multiple_updates'] ) ) return null;
		$existing = self::get_today_update( $employee_id, current_time( 'Y-m-d' ), 'manual' );
		if ( ! $existing || self::can_edit( $existing ) ) return null;
		return new WP_Error( 'et_already_submitted', __( 'لقد قمت بإرسال تحديثك اليوم بالفعل، وانتهت مهلة التعديل المسموحة.'), array( 'status' => 409, 'update_id' => $existing->id ) );
	}

	public static function can_edit( $update_row ) {
		$settings = ET_Settings::get();
		$window   = (int) $settings['edit_window_minutes'];
		if ( $window <= 0 ) return false;
		$created = ET_Time::ts( $update_row->created_at );
		return $created !== false && ( time() - $created ) <= ( $window * MINUTE_IN_SECONDS );
	}

	public static function query( $args = array() ) {
		global $wpdb;
		$table = ET_Database::table( 'daily_updates' );
		$emp   = ET_Database::table( 'employees' );
		$dept  = ET_Database::table( 'departments' );

		$args = wp_parse_args( $args, array(
			'search' => '', 'employee_id' => 0, 'department_id' => 0, 'has_problem' => '',
			'date_from' => '', 'date_to' => '', 'per_page' => 20, 'page' => 1,
		) );

		$where  = array( '1=1' );
		$params = array();

		if ( $args['search'] !== '' ) {
			$like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[] = '(u.achievements LIKE %s OR u.current_tasks LIKE %s OR u.problem_description LIKE %s OR e.name LIKE %s)';
			array_push( $params, $like, $like, $like, $like );
		}
		if ( ! empty( $args['employee_id'] ) )   { $where[] = 'u.employee_id = %d'; $params[] = (int) $args['employee_id']; }
		if ( ! empty( $args['department_id'] ) ) { $where[] = 'u.department_id = %d'; $params[] = (int) $args['department_id']; }
		if ( $args['has_problem'] !== '' )       { $where[] = 'u.has_problem = %d'; $params[] = (int) $args['has_problem']; }
		if ( ! empty( $args['date_from'] ) )     { $where[] = 'u.update_date >= %s'; $params[] = $args['date_from']; }
		if ( ! empty( $args['date_to'] ) )       { $where[] = 'u.update_date <= %s'; $params[] = $args['date_to']; }

		$where_sql = implode( ' AND ', $where );
		$per_page  = max( 1, (int) $args['per_page'] );
		$page      = max( 1, (int) $args['page'] );
		$offset    = ( $page - 1 ) * $per_page;

		$sql = "SELECT u.*, e.name AS employee_name, d.name AS department_name
				FROM {$table} u
				LEFT JOIN {$emp} e ON e.id = u.employee_id
				LEFT JOIN {$dept} d ON d.id = u.department_id
				WHERE {$where_sql} ORDER BY u.created_at DESC LIMIT %d OFFSET %d";
		$count_sql = "SELECT COUNT(*) FROM {$table} u LEFT JOIN {$emp} e ON e.id = u.employee_id WHERE {$where_sql}";

		$params_with_limit = array_merge( $params, array( $per_page, $offset ) );
		$items = $wpdb->get_results( $wpdb->prepare( $sql, $params_with_limit ) );
		$total = (int) ( empty( $params ) ? $wpdb->get_var( $count_sql ) : $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) );

		return array( 'items' => $items, 'total' => $total, 'pages' => (int) ceil( $total / $per_page ) );
	}

	/** كل التحديثات المطابقة للفلاتر بدون Pagination — تُستخدم للتصدير */
	public static function query_all( $args = array() ) {
		global $wpdb;
		$table = ET_Database::table( 'daily_updates' );
		$emp   = ET_Database::table( 'employees' );
		$dept  = ET_Database::table( 'departments' );

		$args = wp_parse_args( $args, array(
			'employee_id' => 0, 'department_id' => 0, 'date_from' => '', 'date_to' => '',
		) );

		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['employee_id'] ) )   { $where[] = 'u.employee_id = %d'; $params[] = (int) $args['employee_id']; }
		if ( ! empty( $args['department_id'] ) ) { $where[] = 'u.department_id = %d'; $params[] = (int) $args['department_id']; }
		if ( ! empty( $args['date_from'] ) )     { $where[] = 'u.update_date >= %s'; $params[] = $args['date_from']; }
		if ( ! empty( $args['date_to'] ) )       { $where[] = 'u.update_date <= %s'; $params[] = $args['date_to']; }

		$where_sql = implode( ' AND ', $where );

		$sql = "SELECT u.*, e.name AS employee_name, d.name AS department_name
				FROM {$table} u
				LEFT JOIN {$emp} e ON e.id = u.employee_id
				LEFT JOIN {$dept} d ON d.id = u.department_id
				WHERE {$where_sql} ORDER BY u.update_date DESC, u.created_at DESC";

		if ( empty( $params ) ) return $wpdb->get_results( $sql );
		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	public static function count_updates_today() {
		global $wpdb;
		$table = ET_Database::table( 'daily_updates' );
		$today = current_time( 'Y-m-d' );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE update_date = %s", $today ) );
	}

	/** عدد الموظفين (الأشخاص) الذين أرسلوا تحديثهم اليوم — تُستخدم بلوحة المتابعة لحساب نسبة الالتزام */
	public static function count_today() {
		global $wpdb;
		$table = ET_Database::table( 'daily_updates' );
		$today = current_time( 'Y-m-d' );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT employee_id) FROM {$table} WHERE update_date = %s", $today ) );
	}

	/**
	 * يرجع الموظفين النشطين الذين لم يرسلوا تحديث اليوم بعد.
	 *
	 * @param string|null $shift فلترة اختيارية حسب الفترة: 'morning' أو 'evening'.
	 *                           موظف بفترة 'both' يظهر دائمًا بغض النظر عن الفلتر.
	 *                           تمرير null يرجع الجميع بدون فلترة فترة.
	 */
	public static function not_submitted_today( $shift = null ) {
		global $wpdb;
		$emp   = ET_Database::table( 'employees' );
		$table = ET_Database::table( 'daily_updates' );
		$dept  = ET_Database::table( 'departments' );
		$today = current_time( 'Y-m-d' );

		$shift_sql = '';
		$params    = array( $today );
		if ( in_array( $shift, array( 'morning', 'evening' ), true ) ) {
			$shift_sql = ' AND e.shift IN (%s, %s) ';
			$params[]  = $shift;
			$params[]  = 'both';
		}

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT e.id, e.name, e.email, e.shift, d.name AS department_name,
				(SELECT MAX(update_date) FROM {$table} u2 WHERE u2.employee_id = e.id) AS last_update_date
			 FROM {$emp} e LEFT JOIN {$dept} d ON d.id = e.department_id
			 WHERE e.status = 'active' AND e.id NOT IN (SELECT employee_id FROM {$table} WHERE update_date = %s)
			 {$shift_sql}
			 ORDER BY e.name ASC",
			$params
		) );

		// حماية: لو فشل الاستعلام لأي سبب (مثل عمود مفقود)، نرجع مصفوفة فارغة بدل null
		// حتى لا تتعطل الصفحات التي تستخدم count()/foreach() على النتيجة مباشرة.
		return is_array( $results ) ? $results : array();
	}

	/** من يستحق تذكير "لم يصلنا تحديثك" اليوم: لا أحد في يوم العطلة، ولا من لديه إجازة/مرضية معتمدة اليوم */
	public static function reminder_recipients( $shift = null ) {
		$today = current_time( 'Y-m-d' );
		if ( ! ET_Attendance::is_working_day( $today ) ) return array();
		return array_values( array_filter( self::not_submitted_today( $shift ), function ( $emp ) use ( $today ) {
			return ! ET_Attendance::leave_covering( $emp->id, $today ) && ! ET_Attendance::sick_covering( $emp->id, $today );
		} ) );
	}

	public static function committed_days_this_month( $employee_id ) {
		global $wpdb;
		$table = ET_Database::table( 'daily_updates' );
		$month_start = current_time( 'Y-m-01' );
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(DISTINCT update_date) FROM {$table} WHERE employee_id = %d AND update_date >= %s",
			$employee_id, $month_start
		) );
	}

	public static function achievements_count_this_month( $employee_id ) {
		global $wpdb;
		$table = ET_Database::table( 'daily_updates' );
		$month_start = current_time( 'Y-m-01' );
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE employee_id = %d AND update_date >= %s AND achievements != ''",
			$employee_id, $month_start
		) );
	}

	public static function current_streak( $employee_id ) {
		global $wpdb;
		$table = ET_Database::table( 'daily_updates' );
		$dates = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT update_date FROM {$table} WHERE employee_id = %d ORDER BY update_date DESC LIMIT 90",
			$employee_id
		) );
		if ( empty( $dates ) ) return 0;

		// أيام العطلة بلا تحديث لا تقطع السلسلة، واليوم الحالي قبل إرسال تحديثه لا يُصفّرها
		$have   = array_flip( $dates );
		$streak = 0;
		$cursor = new DateTimeImmutable( current_time( 'Y-m-d' ) );
		$oldest = new DateTimeImmutable( end( $dates ) );
		$first  = true;
		while ( $cursor >= $oldest ) {
			$day = $cursor->format( 'Y-m-d' );
			if ( isset( $have[ $day ] ) ) {
				$streak++;
			} elseif ( ! $first && ET_Attendance::is_working_day( $day ) ) {
				break;
			}
			$first  = false;
			$cursor = $cursor->modify( '-1 day' );
		}
		return $streak;
	}
}
