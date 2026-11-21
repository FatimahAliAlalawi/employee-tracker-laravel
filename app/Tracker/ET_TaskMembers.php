<?php

/*
 * منقول من بلجن ووردبريس employee-tracker 2.9.1 — منطق العمل كما هو،
 * يعمل فوق Laravel عبر app/Support/helpers.php (لا اعتماد على ووردبريس).
 */

class ET_TaskMembers {

	/** يضيف موظفًا كعضو بمهمة (أو يحدّث دوره إن كان عضوًا أصلًا)، تُستدعى عند إنشاء المهمة وعند "➕ إضافة عضو" */
	public static function add_member( $task_id, $employee_id, $role_label = '' ) {
		global $wpdb;
		$table    = ET_Database::table( 'task_members' );
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$table} WHERE task_id = %d AND employee_id = %d", (int) $task_id, (int) $employee_id ) );

		if ( $existing ) {
			if ( $role_label !== '' ) {
				$wpdb->update( $table, array( 'role_label' => $role_label ), array( 'id' => $existing->id ) );
			}
			return (int) $existing->id;
		}

		$wpdb->insert( $table, array(
			'task_id'          => (int) $task_id,
			'employee_id'      => (int) $employee_id,
			'role_label'       => $role_label,
			'progress_percent' => 0,
			'created_at'       => current_time( 'mysql' ),
		) );
		self::recalc_task_progress( $task_id );
		return (int) $wpdb->insert_id;
	}

	/** يزيل عضوًا من مهمة — يرفض إزالة آخر عضو، لأن كل مهمة يجب أن يبقى لها مسؤول واحد على الأقل */
	public static function remove_member( $task_id, $employee_id ) {
		global $wpdb;
		if ( self::count_for_task( $task_id ) <= 1 ) {
			return new WP_Error( 'et_last_member', __( 'لا يمكن إزالة آخر عضو من المهمة.'), array( 'status' => 400 ) );
		}
		$table = ET_Database::table( 'task_members' );
		$wpdb->delete( $table, array( 'task_id' => (int) $task_id, 'employee_id' => (int) $employee_id ) );
		self::recalc_task_progress( $task_id );
		return true;
	}

	/** "نقل المسؤولية": يحوّل مسؤولية جزء (أو كل) المهمة من موظف لآخر — يضيف الموظف الجديد بنفس نسبة تقدّم
	 *  الموظف القديم (بدل ما يبدأ من صفر)، ثم يزيل الموظف القديم من المهمة. تُسجَّل كتحديث في Timeline المهمة
	 *  حتى يبقى أثر واضح لمن استلم من مين ومتى — يطابق فكرة "سارة انضمت للمهمة" بالوثيقة الأصلية. */
	public static function transfer_member( $task_id, $from_employee_id, $to_employee_id, $role_label = '' ) {
		global $wpdb;
		$table = ET_Database::table( 'task_members' );

		$from = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE task_id = %d AND employee_id = %d", (int) $task_id, (int) $from_employee_id ) );
		if ( ! $from ) {
			return new WP_Error( 'et_not_found', __( 'هذا الموظف ليس عضوًا في المهمة أصلًا.'), array( 'status' => 404 ) );
		}

		self::add_member( $task_id, $to_employee_id, $role_label ?: $from->role_label );
		self::set_progress( $task_id, $to_employee_id, (int) $from->progress_percent );

		if ( self::count_for_task( $task_id ) > 1 ) {
			self::remove_member( $task_id, $from_employee_id );
		}

		return true;
	}

	public static function count_for_task( $task_id ) {
		global $wpdb;
		$table = ET_Database::table( 'task_members' );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE task_id = %d", (int) $task_id ) );
	}

	public static function is_member( $task_id, $employee_id ) {
		global $wpdb;
		$table = ET_Database::table( 'task_members' );
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE task_id = %d AND employee_id = %d", (int) $task_id, (int) $employee_id ) );
	}

	/** كل أعضاء مهمة واحدة مع أسمائهم، مرتبين حسب وقت الانضمام (الأقدم أولًا) */
	public static function for_task( $task_id ) {
		global $wpdb;
		$table = ET_Database::table( 'task_members' );
		$emp   = ET_Database::table( 'employees' );
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT m.*, e.name AS employee_name FROM {$table} m LEFT JOIN {$emp} e ON e.id = m.employee_id WHERE m.task_id = %d ORDER BY m.id ASC",
			(int) $task_id
		) );
	}

	/** أعضاء عدة مهام دفعة واحدة، مصنّفين حسب task_id — لتفادي استعلام منفصل لكل مهمة بقوائم المهام */
	public static function for_tasks( $task_ids ) {
		global $wpdb;
		$task_ids = array_filter( array_map( 'intval', (array) $task_ids ) );
		if ( empty( $task_ids ) ) return array();
		$table        = ET_Database::table( 'task_members' );
		$emp          = ET_Database::table( 'employees' );
		$placeholders = implode( ',', array_fill( 0, count( $task_ids ), '%d' ) );
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT m.*, e.name AS employee_name FROM {$table} m LEFT JOIN {$emp} e ON e.id = m.employee_id WHERE m.task_id IN ({$placeholders}) ORDER BY m.id ASC",
			$task_ids
		) );
		$out = array();
		foreach ( $rows as $r ) { $out[ (int) $r->task_id ][] = $r; }
		return $out;
	}

	/** يحدّث نسبة إنجاز عضو معيّن بمهمة (يُنشئ عضويته تلقائيًا إن لم تكن موجودة)، ثم يعيد حساب نسبة المهمة الإجمالية */
	/** يحدّث نسبة إنجاز عضو معيّن، ثم يُعيد true فقط إذا كان هذا هو "أول" وصول فعلي لـ100% (وليس تكرارًا لحفظ
	 *  سابق كان أصلاً 100%) — تعتمد عليها notify_member_completed() لتفادي تكرار بريد/إشعار "تم الإنجاز". */
	public static function set_progress( $task_id, $employee_id, $progress ) {
		global $wpdb;
		$table    = ET_Database::table( 'task_members' );
		$progress = max( 0, min( 100, (int) $progress ) );
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id, progress_percent FROM {$table} WHERE task_id = %d AND employee_id = %d", (int) $task_id, (int) $employee_id ) );
		$was_complete = $existing && (int) $existing->progress_percent >= 100;

		if ( $existing ) {
			$wpdb->update( $table, array( 'progress_percent' => $progress ), array( 'id' => $existing->id ) );
		} else {
			$wpdb->insert( $table, array(
				'task_id' => (int) $task_id, 'employee_id' => (int) $employee_id,
				'role_label' => '', 'progress_percent' => $progress, 'created_at' => current_time( 'mysql' ),
			) );
		}
		self::recalc_task_progress( $task_id );

		return ( $progress >= 100 && ! $was_complete );
	}

	/** يُرسل إشعارًا داخليًا (🔔) وبريدًا للإدارة (نفس إعداد "بريد الإشعارات" المستخدم لملخص التحديث اليومي)
	 *  عندما ينجز موظف مهمته الفردية بالكامل، أو جزءه من مهمة مشتركة — يُستدعى فقط عند أول وصول فعلي لـ100%. */
	public static function notify_member_completed( $task_id, $employee_id ) {
		$task = ET_WeeklyTasks::get( $task_id );
		$emp  = ET_Employees::get( $employee_id );
		if ( ! $task || ! $emp ) return;

		// مرة واحدة فقط لكل عضو بكل مهمة: إلغاء علامة "منجزة" ثم إعادتها لا يُكرر الإشعار والبريد والتحديث التلقائي
		global $wpdb;
		$members_tbl = ET_Database::table( 'task_members' );
		$claimed = $wpdb->query( $wpdb->prepare(
			"UPDATE {$members_tbl} SET completion_notified_at = %s WHERE task_id = %d AND employee_id = %d AND completion_notified_at IS NULL",
			current_time( 'mysql' ), (int) $task_id, (int) $employee_id
		) );
		if ( ! $claimed ) return;

		$is_shared = self::count_for_task( $task_id ) > 1;
		$message = $is_shared
			? ( '✅ ' . $emp->name . ' أنجز جزءه من المهمة المشتركة "' . $task->title . '"' )
			: ( '✅ ' . $emp->name . ' أنجز مهمة "' . $task->title . '"' );

		// كل خطوة من الخطوات الثلاث التالية (الإشعار الداخلي، إنشاء صف "تحديث يومي" تلقائي، إرسال البريد)
		// مستقلة تمامًا ومُغلَّفة بـ try/catch خاص بها — فلو فشلت واحدة منها لأي سبب غير متوقع (خطأ بقاعدة
		// بيانات حقيقية تختلف عن بيئة الاختبار، إضافة أخرى بالموقع تتعارض معها، إلخ) لا تمنع الخطوتين
		// الأخريين من التنفيذ. قبل هذا التعديل: أي استثناء بمنتصف الدالة كان يوقف تنفيذها بالكامل بصمت (لا
		// رسالة خطأ تصل للموظف ولا للإدارة) فيبدو الأمر وكأن "شيئًا لم يحدث إطلاقًا" رغم نجاح تسجيل إنجاز
		// الموظف فعليًا بقاعدة البيانات — وتحديدًا كان بإمكان هذا أن يمنع وصول بريد الإدارة تحديدًا لو فشلت
		// خطوة إنشاء صف "التحديث التلقائي" التي تسبقه بالكود.
		try {
			// إشعار داخلي فوري (🔔 بلوحة الإدارة) — يبقى نصًا مختصرًا كما كان.
			ET_Notifications::create_for_admin( $message );
		} catch ( \Throwable $e ) {
			error_log( '[ET_TaskMembers::notify_member_completed] فشل إنشاء الإشعار الداخلي: ' . $e->getMessage() );
		}

		// بدل إرسال نص الإشعار المختصر وحده بالبريد، نُعامل تعليم المهمة "منجزة" كخيار ثانٍ كامل مقابل
		// كتابة تحديث يدوي: نُنشئ "تحديثًا يوميًا" فعليًا (source='task_auto') يُحتسب بنفس احتساب "هل
		// أرسل تحديثه اليوم؟" (يخرجه من قائمة "لم يرسلوا تحديث اليوم"، يظهر بتقرير التحديثات اليومية عند
		// الإدارة، ويُحسب بسلسلة الالتزام) تمامًا كالتحديث اليدوي — ثم نرسل البريد بنفس صيغة "✅ تحديث
		// يومي جديد" الكاملة، لا مجرد جملة إنجاز مختصرة، فيصل للإدارة كأن الموظف سجّل تحديثه اليدوي فعليًا.
		// كل مهمة تُعلَّم منجزة تُنشئ تحديثها المستقل الخاص بها (لا تُدمَج مع تحديثات أخرى بنفس اليوم).
		$achievements = '';
		try {
			global $wpdb;
			$members_table = ET_Database::table( 'task_members' );
			$role_label = $wpdb->get_var( $wpdb->prepare(
				"SELECT role_label FROM {$members_table} WHERE task_id = %d AND employee_id = %d", (int) $task_id, (int) $employee_id
			) );

			$achievements = $is_shared
				? ( 'أنجز جزءه من المهمة المشتركة "' . $task->title . '"' . ( $role_label ? ( ' — المطلوب منه: ' . $role_label ) : '' ) )
				: ( 'أنجز مهمة "' . $task->title . '"' );

			ET_Updates::create( $employee_id, array( 'achievements' => $achievements, 'current_tasks' => '', 'source' => 'task_auto' ) );
		} catch ( \Throwable $e ) {
			error_log( '[ET_TaskMembers::notify_member_completed] فشل إنشاء صف التحديث التلقائي: ' . $e->getMessage() );
			if ( $achievements === '' ) {
				$achievements = $is_shared
					? ( 'أنجز جزءه من المهمة المشتركة "' . $task->title . '"' )
					: ( 'أنجز مهمة "' . $task->title . '"' );
			}
		}

		try {
			$department = $emp->department_id ? ET_Departments::get( $emp->department_id ) : null;
			$dept_name  = $department ? $department->name : '—';
			$auto_data  = array( 'achievements' => $achievements, 'current_tasks' => '', 'source' => 'task_auto' );
			// نستدعي بالوضع التشخيصي (نفس وضع زر "إرسال بريد تجريبي") بدل الوضع الصامت المعتاد، لنعرف
			// فعليًا هل نجح الإرسال أم لا، ونُسجّل النتيجة الحقيقية بسجل التدقيق المرئي للإدارة (🗂️ سجل
			// التدقيق بلوحة الإدارة) — بدل الاكتفاء بسجل أخطاء الخادم (error_log) الذي لا يصل إليه المستخدم
			// عادة. هذا يحوّل سؤال "ليش ما وصل بريد إنجاز المهمة؟" من تخمين إلى دليل واضح يراه بنفسه فورًا
			// بعد أي محاولة تعليم مهمة "منجزة" — بلا أي تغيير على وقت أو طريقة الإرسال الفعلي نفسه.
			// عنوان البريد نفسه يحمل اسم المهمة صراحة (بدل العنوان الثابت الموحّد بالإعدادات) — حتى تتعرف
			// الإدارة على أي مهمة يخص البريد من عنوانه مباشرة بصندوق الوارد، دون الحاجة لفتح الرسالة.
			$mail_subject = 'مهمة "' . $task->title . '" — بانتظار اعتماد الإدارة ✅';
			$mail_diag = ET_EmailNotify::notify_admin( ET_Updates::build_summary_message( $emp, $dept_name, $auto_data, true ), true, $mail_subject );
			// نتحقق من تفعيل إشعار البريد صراحة (لا من شكل نتيجة $mail_diag) قبل الكتابة بسجل التدقيق:
			// عند التعطيل المقصود، notify_admin() بالوضع التشخيصي يرجع أيضًا مصفوفة (ok=false) لا null —
			// فلو اعتمدنا على شكل النتيجة فقط لسجّلنا "فشل" زائف في كل مرة رغم أن هذا وضع مقصود ومعروف
			// مسبقًا للإدارة، وليس عطلاً يستحق إغراق السجل به.
			if ( ! empty( ET_Settings::get()['email_notify_enabled'] ) ) {
				if ( ! empty( $mail_diag['ok'] ) ) {
					ET_AuditLog::log( '📧 نجح إرسال بريد إنجاز مهمة "' . $task->title . '" (' . $emp->name . ') إلى ' . $mail_diag['to'] . '.' );
				} else {
					ET_AuditLog::log( '⚠️ فشل إرسال بريد إنجاز مهمة "' . $task->title . '" (' . $emp->name . '): ' . ( $mail_diag['error'] ?? 'سبب غير معروف.' ) );
				}
			}
		} catch ( \Throwable $e ) {
			error_log( '[ET_TaskMembers::notify_member_completed] فشل إرسال بريد الإدارة: ' . $e->getMessage() );
			ET_AuditLog::log( '⚠️ خطأ غير متوقع أثناء محاولة إرسال بريد إنجاز مهمة "' . $task->title . '": ' . $e->getMessage() );
		}
	}

	/** يفرض نسبة موحّدة (0 أو 100) على كل أعضاء مهمة دفعة واحدة — تُستخدم عند تبديل الإدارة لحالة المهمة يدويًا */
	public static function set_progress_all( $task_id, $progress ) {
		global $wpdb;
		$table = ET_Database::table( 'task_members' );
		$wpdb->update( $table, array( 'progress_percent' => max( 0, min( 100, (int) $progress ) ) ), array( 'task_id' => (int) $task_id ) );
		self::recalc_task_progress( $task_id );
	}

	/** يعيد حساب نسبة إنجاز المهمة الإجمالية = متوسط تقدّم كل الأعضاء، وتُكمَل المهمة تلقائيًا فقط عندما يصل الجميع 100% */
	public static function recalc_task_progress( $task_id ) {
		global $wpdb;
		$table       = ET_Database::table( 'task_members' );
		$tasks_table = ET_Database::table( 'weekly_tasks' );

		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT progress_percent FROM {$table} WHERE task_id = %d", (int) $task_id ) );
		if ( empty( $rows ) ) return;

		$sum = 0; $all_done = true;
		foreach ( $rows as $r ) {
			$sum += (int) $r->progress_percent;
			if ( (int) $r->progress_percent < 100 ) $all_done = false;
		}
		$avg = (int) round( $sum / count( $rows ) );

		$update_fields = array( 'progress_percent' => $avg );
		if ( $all_done ) {
			// وصول الجميع لـ100% لا يُكمل المهمة تلقائيًا بعد الآن — تنتقل إلى "مراجعة" بانتظار اعتماد نهائي
			// من الإدارة (ET_WeeklyTasks::approve)، تمامًا مثل خطوة "مراجعة واعتماد" في نموذج سير العمل.
			$current = $wpdb->get_row( $wpdb->prepare( "SELECT status FROM {$tasks_table} WHERE id = %d", (int) $task_id ) );
			if ( ! $current || $current->status !== 'done' ) {
				$update_fields['status'] = 'review';
			}
		} else {
			// لو كانت المهمة قيد المراجعة أو مكتملة سابقًا وتراجع أحد الأعضاء (عدّل نسبته لأقل من 100)،
			// نعيدها "قيد التنفيذ" لتعكس الواقع
			$current = $wpdb->get_row( $wpdb->prepare( "SELECT status FROM {$tasks_table} WHERE id = %d", (int) $task_id ) );
			if ( $current && in_array( $current->status, array( 'done', 'review' ), true ) ) {
				$update_fields['status']       = 'pending';
				$update_fields['completed_at'] = null;
			}
		}
		$wpdb->update( $tasks_table, $update_fields, array( 'id' => (int) $task_id ) );
	}
}
