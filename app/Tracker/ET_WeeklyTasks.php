<?php

/*
 * منقول من بلجن ووردبريس employee-tracker 2.9.1 — منطق العمل كما هو،
 * يعمل فوق Laravel عبر app/Support/helpers.php (لا اعتماد على ووردبريس).
 */

class ET_WeeklyTasks {

	/** أيام أسبوع العمل المعتمدة (الأحد إلى الخميس فقط) */
	const DAYS = array( 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday' );

	/** مستويات الأهمية المتاحة للمهمة */
	const PRIORITIES = array( 'low', 'medium', 'high', 'critical' );

	/** بداية أسبوع العمل الحالي (الأحد) بصيغة Y-m-d حسب توقيت الموقع.
	 *  ملاحظة: لا نستخدم strtotime('sunday this week') لأنها بمنطق PHP (الأسبوع يبدأ اثنين)
	 *  تُرجع أحيانًا أحد الأسبوع القادم بدل أحد الأسبوع الحالي. الحساب هنا يطرح رقم يوم
	 *  الأسبوع (0=الأحد..6=السبت) من تاريخ اليوم فيعطي نفس الأحد لكل أيام الأسبوع بثبات. */
	public static function current_week_start() {
		return self::week_start_for( ET_Time::date( 'Y-m-d' ) );
	}

	/** أحد الأسبوع الذي يقع فيه التاريخ المُعطى (Y-m-d) */
	public static function week_start_for( $ymd ) {
		$d   = new DateTimeImmutable( (string) $ymd );
		$dow = (int) $d->format( 'w' );
		return $d->modify( "-{$dow} days" )->format( 'Y-m-d' );
	}

	/** يحسم أي تاريخ مُدخل (من الإدارة، عبر متصفح قد يكون بتوقيت مختلف عن توقيت الموقع)
	 *  إلى "أحد" الأسبوع الصحيح حسب توقيت الموقع. هذا يمنع مشكلة حرجة: لو المتصفح حسب
	 *  بداية الأسبوع بتوقيت محلي مختلف عن توقيت السيرفر، تُحفظ المهمة بتاريخ لا يطابق
	 *  ما يبحث عنه استعلام "مهام الأسبوع الحالي" عند الموظف، فتظهر له المهمة بالبريد فقط
	 *  ولا تظهر أبدًا داخل المنصة رغم أنها محفوظة فعليًا في قاعدة البيانات. */
	public static function normalize_week_start( $date_str ) {
		$ts = strtotime( (string) $date_str );
		if ( ! $ts ) { return self::current_week_start(); }
		$dow = (int) date( 'w', $ts );
		return date( 'Y-m-d', strtotime( "-{$dow} days", $ts ) );
	}

	/** اسم اليوم بالعربي لأحد أيام أسبوع العمل (الأحد..الخميس)، أو "غير محدد" إن لم يُحدَّد */
	public static function day_label( $day ) {
		$map = array(
			'sunday'    => 'الأحد',
			'monday'    => 'الاثنين',
			'tuesday'   => 'الثلاثاء',
			'wednesday' => 'الأربعاء',
			'thursday'  => 'الخميس',
		);
		return $map[ $day ] ?? 'غير محدد';
	}

	/** مدى أهمية المهمة بالعربي */
	public static function priority_label( $priority ) {
		$map = array( 'low' => 'عادية', 'medium' => 'متوسطة', 'high' => 'عالية', 'critical' => 'عاجلة' );
		return $map[ $priority ] ?? $priority;
	}

	/** شارة تأخير محسوبة تلقائيًا من تاريخ الاستحقاق: "متأخرة X يوم" / "مستحقة اليوم" / "خلال X يوم"، لا شيء
	 *  لو المهمة بدون تاريخ استحقاق أو مكتملة أصلًا. تُستخدم بواجهتي الموظف والإدارة كتنبيه بصري فوري. */
	public static function urgency_badge( $due_date, $status ) {
		if ( empty( $due_date ) || in_array( $status, array( 'done', 'review' ), true ) ) return null;

		$today = current_time( 'Y-m-d' );
		$diff  = (int) floor( ( strtotime( $due_date ) - strtotime( $today ) ) / DAY_IN_SECONDS );

		if ( $diff < 0 )  return array( 'label' => 'متأخرة ' . abs( $diff ) . ' يوم', 'level' => 'overdue' );
		if ( $diff === 0 ) return array( 'label' => 'مستحقة اليوم', 'level' => 'today' );
		if ( $diff <= 2 )  return array( 'label' => 'خلال ' . $diff . ' يوم', 'level' => 'soon' );
		return array( 'label' => 'خلال ' . $diff . ' يوم', 'level' => 'normal' );
	}

	public static function create( $data ) {
		global $wpdb;
		$table    = ET_Database::table( 'weekly_tasks' );
		$day      = in_array( $data['task_day'] ?? '', self::DAYS, true ) ? $data['task_day'] : null;
		$priority = in_array( $data['priority'] ?? '', self::PRIORITIES, true ) ? $data['priority'] : 'medium';
		$wpdb->insert( $table, array(
			'employee_id'     => (int) $data['employee_id'],
			'department_id'   => ! empty( $data['department_id'] ) ? (int) $data['department_id'] : null,
			'week_start_date' => $data['week_start_date'],
			'task_day'        => $day,
			'title'           => $data['title'],
			'description'     => $data['description'] ?? '',
			'priority'        => $priority,
			'due_date'        => ! empty( $data['due_date'] ) ? $data['due_date'] : null,
			'status'          => 'pending',
			'created_at'      => current_time( 'mysql' ),
		) );
		$task_id = (int) $wpdb->insert_id;

		// كل مهمة تبدأ بعضو واحد (صاحبها الأصلي) — دوره الافتراضي "المسؤول الرئيسي"، إلا إذا مرّر المُنشئ
		// نصًا مخصصًا لـ"المطلوب منه" (role_label)، كما بنموذج إنشاء المهام المشتركة بتبويب "🗺️ المشاريع".
		// الإدارة تقدر أيضًا تضيف أعضاء آخرين لاحقًا من تبويب "متابعة المهام" لتصبح مهمة جماعية — هذا ما يجعل
		// عمود employee_id يبقى صالحًا للتوافق القديم بينما جدول task_members هو مصدر الحقيقة الفعلي لعضوية
		// المهمة من الآن فصاعدًا.
		$primary_role = trim( (string) ( $data['role_label'] ?? '' ) ) !== '' ? trim( (string) $data['role_label'] ) : 'المسؤول الرئيسي';
		ET_TaskMembers::add_member( $task_id, (int) $data['employee_id'], $primary_role );

		return $task_id;
	}

	public static function get( $id ) {
		global $wpdb;
		$table = ET_Database::table( 'weekly_tasks' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
	}

	public static function delete( $id ) {
		global $wpdb;
		$table = ET_Database::table( 'weekly_tasks' );
		return $wpdb->delete( $table, array( 'id' => (int) $id ) );
	}

	/** يبدّل نسبة إنجاز الموظف نفسه بالمهمة (0 أو 100) — يتحقق أولًا أنه عضو فيها. حالة المهمة الإجمالية
	 *  تُكمَل تلقائيًا فقط عند وصول كل الأعضاء 100% (تُحسب داخل ET_TaskMembers::set_progress) */
	public static function set_completed( $id, $employee_id, $completed ) {
		if ( ! ET_TaskMembers::is_member( $id, $employee_id ) ) {
			return new WP_Error( 'et_not_found', __( 'المهمة غير موجودة.'), array( 'status' => 404 ) );
		}
		$just_completed = ET_TaskMembers::set_progress( $id, $employee_id, $completed ? 100 : 0 );
		if ( $completed && $just_completed ) {
			ET_TaskMembers::notify_member_completed( $id, $employee_id );
		}
		return true;
	}

	/** اعتماد الإدارة النهائي: ينقل مهمة من حالة "مراجعة" (كل الأعضاء أنجزوا 100%) إلى "مكتملة" فعليًا.
	 *  هذه الخطوة الوسيطة تمنع اعتبار المهمة منتهية فقط لأن الأرقام وصلت 100%، وتعطي الإدارة فرصة تدقيق أخيرة. */
	public static function approve( $id ) {
		global $wpdb;
		$task = self::get( $id );
		if ( ! $task ) {
			return new WP_Error( 'et_not_found', __( 'المهمة غير موجودة.'), array( 'status' => 404 ) );
		}
		if ( $task->status !== 'review' ) {
			return new WP_Error( 'et_not_review', __( 'هذه المهمة ليست بانتظار الاعتماد.'), array( 'status' => 400 ) );
		}
		$table = ET_Database::table( 'weekly_tasks' );
		$wpdb->update( $table, array( 'status' => 'done', 'completed_at' => current_time( 'mysql' ) ), array( 'id' => (int) $id ) );
		ET_AuditLog::log( 'الإدارة اعتمدت إنجاز مهمة "' . $task->title . '" بشكل نهائي.' );
		return true;
	}

	/** الإدارة تفرض حالة موحّدة على كل أعضاء المهمة يدويًا من تبويب "متابعة المهام" — بما أن الإدارة نفسها من تنفّذ
	 *  هذا الإجراء، يُعتبر اعتمادًا ضمنيًا فوريًا (تُكمَل المهمة مباشرة بدون المرور بمرحلة "مراجعة" الوسيطة). */
	public static function set_status_admin( $id, $completed ) {
		global $wpdb;
		$task = self::get( $id );
		if ( ! $task ) {
			return new WP_Error( 'et_not_found', __( 'المهمة غير موجودة.'), array( 'status' => 404 ) );
		}
		ET_TaskMembers::set_progress_all( $id, $completed ? 100 : 0 );
		if ( $completed ) {
			$table = ET_Database::table( 'weekly_tasks' );
			$wpdb->update( $table, array( 'status' => 'done', 'completed_at' => current_time( 'mysql' ) ), array( 'id' => (int) $id ) );
		}
		return true;
	}

	/** مهام أسبوع محدد (أو الأسبوع الحالي إن لم يُحدَّد) لكل الموظفين — لعرض تبويب "الإدارة" */
	/** بحث سريع عن مهام بالعنوان (أي أسبوع) — تُستخدم بمربع "ربط مهمة" في تبويب "المشاريع" حتى تُربط مهمة من أي أسبوع */
	public static function search_by_title( $query, $limit = 10 ) {
		global $wpdb;
		$table = ET_Database::table( 'weekly_tasks' );
		$emp   = ET_Database::table( 'employees' );
		$like  = '%' . $wpdb->esc_like( $query ) . '%';
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT t.*, e.name AS employee_name FROM {$table} t LEFT JOIN {$emp} e ON e.id = t.employee_id
			 WHERE t.title LIKE %s ORDER BY t.week_start_date DESC, t.id DESC LIMIT %d",
			$like, (int) $limit
		) );
	}

	public static function for_week( $week_start = '' ) {
		global $wpdb;
		$table = ET_Database::table( 'weekly_tasks' );
		$emp   = ET_Database::table( 'employees' );
		$week  = $week_start ?: self::current_week_start();
		$day_order = "FIELD(t.task_day,'sunday','monday','tuesday','wednesday','thursday')";

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT t.*, e.name AS employee_name FROM {$table} t
			 LEFT JOIN {$emp} e ON e.id = t.employee_id
			 WHERE t.week_start_date = %s
			 ORDER BY e.name ASC, (t.task_day IS NULL OR t.task_day = '') ASC, {$day_order} ASC, t.id ASC",
			$week
		) );
	}
	/** مهام موظف معيّن — الآن تُحسب عبر عضويته في task_members (وليس فقط عمود employee_id)، حتى تظهر له
	 *  المهام الجماعية التي أُضيف لها لاحقًا كعضو، لا فقط المهام التي أنشأتها الإدارة له مباشرة. */
	public static function get_for_employee( $employee_id ) {
		global $wpdb;
		$table   = ET_Database::table( 'weekly_tasks' );
		$members = ET_Database::table( 'task_members' );
		$week    = self::current_week_start();

		// ترتيب: المهام المحددة الأيام أولاً بترتيب الأحد←الخميس، ثم غير المحددة أخيرًا
		$day_order = "FIELD(t.task_day,'sunday','monday','tuesday','wednesday','thursday')";

		// المهام المعتمدة نهائيًا (status = 'done') تُستبعد من هنا عمدًا فتختفي فورًا من صفحة الموظف
		// (قائمة "مهامي" ولوحة "لوحتي" معًا، لأن كلتيهما تُبنيان من نفس هذه النتيجة) بمجرد اعتماد
		// الإدارة لها نهائيًا — لا تبقى عالقة تُشوّش على مهامه الفعلية المتبقية. هذا لا يمس إطلاقًا
		// ما تراه الإدارة نفسها (صفحات ووردبريس + تبويب "الإدارة")، فهي تُستعلم من دالة for_week()
		// المنفصلة كليًا والتي تعرض كل المهام دائمًا بلا أي استثناء لأي حالة.
		$current = $wpdb->get_results( $wpdb->prepare(
			"SELECT t.* FROM {$table} t INNER JOIN {$members} m ON m.task_id = t.id
			 WHERE m.employee_id = %d AND t.week_start_date = %s AND t.status != 'done'
			 ORDER BY (t.task_day IS NULL OR t.task_day = '') ASC, {$day_order} ASC, t.id ASC",
			$employee_id, $week
		) );

		// ملاحظة إصلاح: كانت هذه الجملة تشترط status = 'pending' حرفيًا فقط، فأي مهمة مشتركة من أسبوع
		// سابق أنجز كل أعضائها 100% (تتحول حالتها تلقائيًا إلى 'review' بانتظار اعتماد الإدارة النهائي عبر
		// ET_TaskMembers::recalc_task_progress) كانت تسقط من هذا الاستعلام تمامًا — ولأن أسبوعها لم يعد
		// "الأسبوع الحالي" فهي تسقط من استعلام current أيضًا، فتختفي المهمة بالكامل من واجهة الموظف (قائمة
		// "مهامي"، لوحة "لوحتي"، وتبويب "طلب مساعدة" الذي يعتمد على نفس هذه القائمة) رغم أنها لم تُعتمد
		// نهائيًا بعد. الإصلاح: نعتبر أي مهمة لم تُعتمد نهائيًا ('pending' أو 'review') "معلّقة من أسبوع
		// سابق" ما دامت لم تصل status='done'.
		$previous_pending = $wpdb->get_results( $wpdb->prepare(
			"SELECT t.* FROM {$table} t INNER JOIN {$members} m ON m.task_id = t.id
			 WHERE m.employee_id = %d AND t.week_start_date < %s AND t.status IN ('pending','review')
			 ORDER BY t.week_start_date DESC, (t.task_day IS NULL OR t.task_day = '') ASC, {$day_order} ASC, t.id ASC",
			$employee_id, $week
		) );

		return array( 'current' => $current, 'previous_pending' => $previous_pending, 'week_start' => $week );
	}

	/**
	 * مهام أُسندت ليوم عمل محدد (تاريخ فعلي، وليس فقط اسم يوم) ولم تُنجز بعد نهائيًا — تشمل
	 * "قيد التنفيذ"/"متوقفة"/"لم تبدأ" لكن تستثني عمدًا "مراجعة" (الموظف أنجز وينتظر اعتماد
	 * الإدارة، فلا داعي لتذكيره) و"مكتملة". تُستخدم لتذكير الموظف صباح اليوم التالي بمهام
	 * الأمس الفائتة — مُجمَّعة حسب الموظف لدعم المهام الجماعية (أكثر من عضو بنفس المهمة).
	 *
	 * @param string $date تاريخ فعلي (Y-m-d)، عادة "أمس". يُحسم أسبوعه ويومه تلقائيًا حتى
	 *                     يعمل بشكل صحيح حتى لو "أمس" كان بأسبوع مختلف عن أسبوع اليوم
	 *                     (مثال: اليوم أحد ← أمس خميس الأسبوع الماضي).
	 * @return array مصفوفة مفهرسة بـ employee_id: { employee: {id,name,email}, tasks: [{id,title}] }
	 */
	public static function incomplete_by_employee_for_date( $date ) {
		global $wpdb;

		$ts = strtotime( (string) $date );
		if ( ! $ts ) return array();

		$day_name = strtolower( date( 'l', $ts ) );
		// نهاية الأسبوع (جمعة/سبت) — لا توجد مهام تُسند لهذين اليومين أصلاً، فلا داعي لأي استعلام
		if ( ! in_array( $day_name, array( 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday' ), true ) ) {
			return array();
		}

		$week_start = self::normalize_week_start( $date );
		$table      = ET_Database::table( 'weekly_tasks' );
		$members    = ET_Database::table( 'task_members' );
		$emp        = ET_Database::table( 'employees' );

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT t.id, t.title, m.employee_id, e.name, e.email
			 FROM {$table} t
			 INNER JOIN {$members} m ON m.task_id = t.id
			 INNER JOIN {$emp} e ON e.id = m.employee_id
			 WHERE t.week_start_date = %s AND t.task_day = %s
			   AND t.status NOT IN ('done', 'review') AND e.status = 'active'
			 ORDER BY e.name ASC, t.id ASC",
			$week_start, $day_name
		) );

		$out = array();
		foreach ( $rows as $r ) {
			$eid = (int) $r->employee_id;
			if ( ! isset( $out[ $eid ] ) ) {
				$out[ $eid ] = array( 'employee' => array( 'id' => $eid, 'name' => $r->name, 'email' => $r->email ), 'tasks' => array() );
			}
			$out[ $eid ]['tasks'][] = array( 'id' => (int) $r->id, 'title' => $r->title );
		}
		return $out;
	}

	/**
	 * تُعيد كل المهام (من أي أسبوع) التي ما زالت غير منجزة حتى تاريخ معيّن — وليس فقط
	 * مهام يوم واحد بعينه. تُستخدم للتذكير اليومي المتكرر: أي مهمة استُحقت بالأمس أو
	 * قبله ولم تُنجز بعد تظهر هنا كل يوم إلى أن يُنجزها الموظف أو تُعلَّم كـ"قيد المراجعة".
	 *
	 * @param string $as_of التاريخ المرجعي (YYYY-MM-DD) — تُحسب كل مهمة استحقاقها في هذا التاريخ أو قبله.
	 * @return array مصفوفة مجمّعة بنفس شكل incomplete_by_employee_for_date(): { employee_id => { employee, tasks } }
	 */
	public static function incomplete_by_employee_as_of( $as_of ) {
		global $wpdb;

		$as_of_ts = strtotime( (string) $as_of );
		if ( ! $as_of_ts ) return array();

		$table   = ET_Database::table( 'weekly_tasks' );
		$members = ET_Database::table( 'task_members' );
		$emp     = ET_Database::table( 'employees' );

		$rows = $wpdb->get_results(
			"SELECT t.id, t.title, t.week_start_date, t.task_day, t.due_date, m.employee_id, e.name, e.email
			 FROM {$table} t
			 INNER JOIN {$members} m ON m.task_id = t.id
			 INNER JOIN {$emp} e ON e.id = m.employee_id
			 WHERE t.status NOT IN ('done', 'review') AND e.status = 'active'
			 ORDER BY e.name ASC, t.id ASC"
		);

		$day_offset = array( 'sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4 );

		$out = array();
		foreach ( $rows as $r ) {
			if ( ! empty( $r->due_date ) ) {
				$task_date = $r->due_date;
			} else {
				$offset    = isset( $day_offset[ $r->task_day ] ) ? $day_offset[ $r->task_day ] : 4;
				$task_date = date( 'Y-m-d', strtotime( '+' . $offset . ' days', strtotime( $r->week_start_date ) ) );
			}

			if ( strtotime( $task_date ) > $as_of_ts ) continue; // لم يحن استحقاقها بعد

			$eid = (int) $r->employee_id;
			if ( ! isset( $out[ $eid ] ) ) {
				$out[ $eid ] = array( 'employee' => array( 'id' => $eid, 'name' => $r->name, 'email' => $r->email ), 'tasks' => array() );
			}
			$out[ $eid ]['tasks'][] = array( 'id' => (int) $r->id, 'title' => $r->title );
		}
		return $out;
	}

	/**
	 * تقرير أداء المهام: لكل موظف (أو موظف واحد محدد فقط) — عدد المهام المسندة له ضمن نطاق
	 * تاريخ يجمع أسبوعًا واحدًا أو عدة أسابيع معًا (عبر عضويته الفعلية بـ task_members، فتُحتسب
	 * له المهام الجماعية أيضًا)، المنجزة منها، ونوعان من "المتأخرة": حسب تاريخ الاستحقاق المحدد
	 * لها (due_date) إن وُجد، وحسب يوم العمل الفعلي الذي أُسندت له (task_day) بغض النظر عن وجود
	 * تاريخ استحقاق أصلاً. "قيد المراجعة" لا تُحتسب منجزة ولا متأخرة — الموظف أنجزها فعليًا
	 * وينتظر فقط اعتماد الإدارة النهائي، فلا يستحق أن تُحسب عليه كمهمة متأخرة.
	 *
	 * التاريخان يُحسمان تلقائيًا (نفس منطق normalize_week_start) لبداية أسبوع كل منهما، حتى
	 * يشمل النطاق كامل كل أسبوع "يلمسه" التاريخان — تمامًا كما لو اخترت "من" أي يوم بأسبوع
	 * و"إلى" أي يوم بأسبوع آخر لاحق، فتحصل على تقرير مجمّع لكل الأسابيع بينهما بحدودها كاملة.
	 *
	 * كل تصنيف (مسندة/منجزة/متأخرة بنوعيها) يحمل أيضًا قائمة المهام الفعلية التي تكوّنه
	 * (id، العنوان، اسم اليوم بالعربي، أسبوعها، حالتها) — حتى تقدر الإدارة تضغط على أي رقم
	 * بالتقرير وتشوف تفصيله فورًا بدل رقم مجرّد بلا تفاصيل.
	 *
	 * @param string $date_from   تاريخ بداية النطاق (Y-m-d).
	 * @param string $date_to     تاريخ نهاية النطاق (Y-m-d).
	 * @param int    $employee_id لو أكبر من صفر، يقتصر التقرير على هذا الموظف فقط.
	 * @return array مصفوفة عناصر: { employee_id, name, assigned, completed, delayed_due, delayed_day,
	 *               completion_percent, assigned_tasks, completed_tasks, delayed_due_tasks, delayed_day_tasks }
	 *               كل عنصر بقوائم المهام: { id, title, day_label, week_start_date, status }
	 */
	public static function employee_stats_for_range( $date_from, $date_to, $employee_id = 0 ) {
		global $wpdb;
		$table   = ET_Database::table( 'weekly_tasks' );
		$members = ET_Database::table( 'task_members' );
		$emp     = ET_Database::table( 'employees' );

		$week_from = self::normalize_week_start( $date_from );
		$week_to   = self::normalize_week_start( $date_to );
		if ( $week_from > $week_to ) {
			$tmp = $week_from; $week_from = $week_to; $week_to = $tmp;
		}

		$today      = current_time( 'Y-m-d' );
		$day_offset = array( 'sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4 );

		$where  = "t.week_start_date BETWEEN %s AND %s AND e.status = 'active'";
		$params = array( $week_from, $week_to );
		if ( (int) $employee_id > 0 ) {
			$where   .= ' AND m.employee_id = %d';
			$params[] = (int) $employee_id;
		}

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT t.id AS task_id, t.title, t.week_start_date, t.status, t.due_date, t.task_day, m.employee_id, e.name
			 FROM {$table} t
			 INNER JOIN {$members} m ON m.task_id = t.id
			 INNER JOIN {$emp} e ON e.id = m.employee_id
			 WHERE {$where}
			 ORDER BY e.name ASC, t.week_start_date ASC",
			$params
		) );

		$out = array();
		foreach ( $rows as $r ) {
			$eid = (int) $r->employee_id;
			if ( ! isset( $out[ $eid ] ) ) {
				$out[ $eid ] = array(
					'employee_id'       => $eid,
					'name'              => $r->name,
					'assigned'          => 0,
					'completed'         => 0,
					'delayed_due'       => 0,
					'delayed_day'       => 0,
					'assigned_tasks'    => array(),
					'completed_tasks'   => array(),
					'delayed_due_tasks' => array(),
					'delayed_day_tasks' => array(),
				);
			}
			$task_info = array(
				'id'              => (int) $r->task_id,
				'title'           => $r->title,
				'day_label'       => self::day_label( $r->task_day ),
				'week_start_date' => $r->week_start_date,
				'status'          => $r->status,
			);

			$out[ $eid ]['assigned']++;
			$out[ $eid ]['assigned_tasks'][] = $task_info;

			if ( $r->status === 'done' ) {
				$out[ $eid ]['completed']++;
				$out[ $eid ]['completed_tasks'][] = $task_info;
				continue;
			}
			if ( $r->status === 'review' ) {
				continue; // أنجز فعليًا وبانتظار اعتماد فقط — لا يُحتسب متأخرًا
			}

			if ( ! empty( $r->due_date ) && $r->due_date < $today ) {
				$out[ $eid ]['delayed_due']++;
				$out[ $eid ]['delayed_due_tasks'][] = $task_info;
			}
			if ( isset( $day_offset[ $r->task_day ] ) ) {
				$task_date = date( 'Y-m-d', strtotime( '+' . $day_offset[ $r->task_day ] . ' days', strtotime( (string) $r->week_start_date ) ) );
				if ( $task_date < $today ) {
					$out[ $eid ]['delayed_day']++;
					$out[ $eid ]['delayed_day_tasks'][] = $task_info;
				}
			}
		}

		foreach ( $out as &$row ) {
			$row['completion_percent'] = $row['assigned'] > 0 ? (int) round( ( $row['completed'] / $row['assigned'] ) * 100 ) : 0;
		}
		unset( $row );

		return array_values( $out );
	}

	/** قائمة المهام لصفحة الإدارة مع فلاتر وترقيم صفحات */
	public static function query( $args = array() ) {
		global $wpdb;
		$table = ET_Database::table( 'weekly_tasks' );
		$emp   = ET_Database::table( 'employees' );
		$dept  = ET_Database::table( 'departments' );

		$args = wp_parse_args( $args, array(
			'employee_id' => 0, 'department_id' => 0, 'week_start_date' => '', 'task_day' => '', 'status' => '',
			'page' => 1, 'per_page' => 20,
		) );

		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['employee_id'] ) )     { $where[] = 't.employee_id = %d'; $params[] = (int) $args['employee_id']; }
		if ( ! empty( $args['department_id'] ) )   { $where[] = 't.department_id = %d'; $params[] = (int) $args['department_id']; }
		if ( ! empty( $args['week_start_date'] ) ) { $where[] = 't.week_start_date = %s'; $params[] = $args['week_start_date']; }
		if ( ! empty( $args['task_day'] ) )        { $where[] = 't.task_day = %s'; $params[] = $args['task_day']; }
		if ( ! empty( $args['status'] ) )          { $where[] = 't.status = %s'; $params[] = $args['status']; }

		$where_sql = implode( ' AND ', $where );
		$per_page  = max( 1, (int) $args['per_page'] );
		$offset    = ( max( 1, (int) $args['page'] ) - 1 ) * $per_page;

		$count_sql = "SELECT COUNT(*) FROM {$table} t WHERE {$where_sql}";
		$total = (int) ( empty( $params ) ? $wpdb->get_var( $count_sql ) : $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) );

		$sql = "SELECT t.*, e.name AS employee_name, d.name AS department_name
				FROM {$table} t
				LEFT JOIN {$emp} e ON e.id = t.employee_id
				LEFT JOIN {$dept} d ON d.id = t.department_id
				WHERE {$where_sql}
				ORDER BY t.week_start_date DESC, (t.task_day IS NULL OR t.task_day = '') ASC,
				         FIELD(t.task_day,'sunday','monday','tuesday','wednesday','thursday') ASC, t.id DESC
				LIMIT %d OFFSET %d";
		$items_params = array_merge( $params, array( $per_page, $offset ) );
		$items = $wpdb->get_results( $wpdb->prepare( $sql, $items_params ) );

		return array( 'items' => $items, 'total' => $total, 'pages' => (int) ceil( $total / $per_page ) );
	}
}
