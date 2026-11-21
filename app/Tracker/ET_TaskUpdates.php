<?php

/*
 * منقول من بلجن ووردبريس employee-tracker 2.9.1 — منطق العمل كما هو،
 * يعمل فوق Laravel عبر app/Support/helpers.php (لا اعتماد على ووردبريس).
 */

class ET_TaskUpdates {

	const WORK_STATUSES = array( 'started', 'in_progress', 'partial', 'need_help', 'problem', 'waiting_person', 'waiting_admin', 'done_part' );

	/** "حالة العمل الداخلية" — منفصلة عن حالة المهمة نفسها (pending/done)، توضح تحديدًا ماذا يفعل الموظف الآن */
	public static function work_status_label( $key ) {
		$labels = array(
			'started'        => 'بدأ العمل',
			'in_progress'    => 'يعمل عليها',
			'partial'        => 'أنجز جزءًا',
			'need_help'      => 'يحتاج مساعدة',
			'problem'        => 'ظهرت مشكلة',
			'waiting_person' => 'بانتظار شخص آخر',
			'waiting_admin'  => 'بانتظار الإدارة',
			'done_part'      => 'أكمل جزءه',
		);
		return $labels[ $key ] ?? $labels['in_progress'];
	}

	/** يضيف تحديثًا جديدًا لمهمة، يحدّث نسبة إنجاز العضو صاحب التحديث تحديدًا، ثم يُعاد حساب نسبة المهمة
	 *  الإجمالية كمتوسط كل الأعضاء (بدل الكتابة المباشرة فوق نسبة المهمة) — ضروري لدعم المهام الجماعية. */
	public static function add( $task_id, $employee_id, $data ) {
		global $wpdb;
		$table = ET_Database::table( 'task_updates' );

		$progress    = max( 0, min( 100, (int) ( $data['progress_percent'] ?? 0 ) ) );
		$work_status = in_array( $data['work_status'] ?? '', self::WORK_STATUSES, true ) ? $data['work_status'] : 'in_progress';

		$wpdb->insert( $table, array(
			'task_id'              => (int) $task_id,
			'employee_id'          => (int) $employee_id,
			'progress_percent'     => $progress,
			'work_status'          => $work_status,
			'achievements'         => $data['achievements'] ?? '',
			'current_work'         => $data['current_work'] ?? '',
			'has_problem'          => ! empty( $data['has_problem'] ) ? 1 : 0,
			'problem_description'  => $data['problem_description'] ?? '',
			'needs_help'           => ! empty( $data['needs_help'] ) ? 1 : 0,
			'help_description'     => $data['help_description'] ?? '',
			'created_at'           => current_time( 'mysql' ),
		) );

		$just_completed = ET_TaskMembers::set_progress( $task_id, $employee_id, $progress );
		if ( $just_completed ) {
			ET_TaskMembers::notify_member_completed( $task_id, $employee_id );
		}

		return (int) $wpdb->insert_id;
	}

	/** كل تحديثات مهمة واحدة مرتبة زمنيًا (الأقدم أولًا) — تُستخدم لبناء الـ Timeline */
	public static function for_task( $task_id ) {
		global $wpdb;
		$table = ET_Database::table( 'task_updates' );
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE task_id = %d ORDER BY id ASC", (int) $task_id
		) );
	}

	/** كل تحديثات اليوم عبر كل المهام والموظفين، مرتبة زمنيًا — تبني "Timeline موحّد" ليوم كامل بلوحة القيادة */
	public static function for_today() {
		global $wpdb;
		$table       = ET_Database::table( 'task_updates' );
		$tasks_table = ET_Database::table( 'weekly_tasks' );
		$emp         = ET_Database::table( 'employees' );
		$today       = current_time( 'Y-m-d' );

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT u.*, t.title AS task_title, e.name AS employee_name
			 FROM {$table} u
			 INNER JOIN {$tasks_table} t ON t.id = u.task_id
			 LEFT JOIN {$emp} e ON e.id = u.employee_id
			 WHERE DATE(u.created_at) = %s
			 ORDER BY u.created_at ASC",
			$today
		) );
	}

	/** آخر تحديث لكل مهمة من مجموعة مهام — تُستخدم بقوائم المهام (بدون تحميل السجل كاملًا) */
	public static function latest_for_tasks( $task_ids ) {
		global $wpdb;
		$task_ids = array_filter( array_map( 'intval', (array) $task_ids ) );
		if ( empty( $task_ids ) ) return array();
		$table        = ET_Database::table( 'task_updates' );
		$placeholders = implode( ',', array_fill( 0, count( $task_ids ), '%d' ) );
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT u1.* FROM {$table} u1
			 INNER JOIN ( SELECT task_id, MAX(id) AS max_id FROM {$table} WHERE task_id IN ({$placeholders}) GROUP BY task_id ) u2
			 ON u1.task_id = u2.task_id AND u1.id = u2.max_id",
			$task_ids
		) );
		$out = array();
		foreach ( $rows as $r ) { $out[ (int) $r->task_id ] = $r; }
		return $out;
	}
}
