<?php

/*
 * منقول من بلجن ووردبريس employee-tracker 2.9.1 — منطق العمل كما هو،
 * يعمل فوق Laravel عبر app/Support/helpers.php (لا اعتماد على ووردبريس).
 */

class ET_HelpRequests {

	const TYPES = array( 'review' => 'مراجعة', 'technical' => 'مشكلة تقنية', 'data' => 'بيانات ناقصة', 'other' => 'أخرى', 'handoff' => 'تسليم/تحويل الدور' );

	/** ينشئ طلب مساعدة (أو تسليم/تحويل دور، عبر $help_type = 'handoff') موجّهًا لزميل محدد بمهمة مشتركة —
	 *  يُنبّه الزميل المستهدف بقناتين معًا: إشعار داخلي فوري (🔔 بالتطبيق) وبريد إلكتروني إن كان له بريد مسجّل،
	 *  حتى يصله التنبيه حتى لو لم يفتح التطبيق فورًا. */
	/** @param array|null $attachment اختياري: array('url' => ..., 'name' => ...) — ملف عمل فعلي يرفعه المُرسِل
	 *  ويصل مباشرة مع الطلب/التحويل للزميل المستهدف (رفع حقيقي من الجهاز، وليس رابطًا يُكتب يدويًا). */
	public static function create( $task_id, $from_employee_id, $to_employee_id, $help_type, $message, $attachment = null ) {
		global $wpdb;
		$table = ET_Database::table( 'help_requests' );
		$help_type = in_array( $help_type, array_keys( self::TYPES ), true ) ? $help_type : 'other';
		$wpdb->insert( $table, array(
			'task_id'          => (int) $task_id,
			'from_employee_id' => (int) $from_employee_id,
			'to_employee_id'   => (int) $to_employee_id,
			'help_type'        => $help_type,
			'message'          => $message,
			'attachment_url'   => ! empty( $attachment['url'] ) ? $attachment['url'] : null,
			'attachment_name'  => ! empty( $attachment['name'] ) ? $attachment['name'] : null,
			'status'           => 'pending',
			'created_at'       => current_time( 'mysql' ),
		) );
		$id = (int) $wpdb->insert_id;

		$from_name   = ET_Employees::get( $from_employee_id );
		$to_employee = ET_Employees::get( $to_employee_id );
		$task        = ET_WeeklyTasks::get( $task_id );
		$is_handoff  = ( $help_type === 'handoff' );
		$has_file    = ! empty( $attachment['url'] ) && ! empty( $attachment['name'] );

		$notif_text = $is_handoff
			? ( ( $from_name ? $from_name->name : 'زميل' ) . ' أنجز جزءه وحوّل لك الدور بمهمة "' . ( $task ? $task->title : '' ) . '"' . ( $message ? ( ': ' . $message ) : '' ) )
			: ( ( $from_name ? $from_name->name : 'زميل' ) . ' طلب مساعدتك بمهمة "' . ( $task ? $task->title : '' ) . '" (' . self::TYPES[ $help_type ] . ')' . ( $message ? ( ': ' . $message ) : '' ) );
		if ( $has_file ) {
			$notif_text .= "\n📎 " . $attachment['name'] . ': ' . $attachment['url'];
		}

		ET_Notifications::create_for_employee( $to_employee_id, $notif_text );

		if ( $to_employee && ! empty( $to_employee->email ) && is_email( $to_employee->email ) ) {
			$subject = $is_handoff ? 'تحويل دور بمهمة مشتركة' : 'طلب مساعدة بمهمة';
			ET_Background::queue( 'et_bg_email', array( 'to' => $to_employee->email, 'subject' => $subject, 'message' => $notif_text ) );
		}

		// المرفق يُسجَّل أيضًا كمرفق عادي على مستوى المهمة، حتى يظهر لكل من يفتح بطاقة المهمة (وليس فقط
		// الزميل المستهدف بالطلب)، تمامًا كأي مرفق آخر يُضاف يدويًا من "📎 المرفقات".
		if ( $has_file ) {
			ET_TaskAttachments::add( $task_id, $attachment['name'], $attachment['url'] );
		}

		ET_AuditLog::log( ( $from_name ? $from_name->name : 'موظف' ) . ( $is_handoff ? ' حوّل جزءه بمهمة "' : ' طلب مساعدة بمهمة "' ) . ( $task ? $task->title : '' ) . '"' );

		return $id;
	}

	public static function resolve( $id ) {
		global $wpdb;
		$table = ET_Database::table( 'help_requests' );
		$wpdb->update( $table, array( 'status' => 'resolved', 'resolved_at' => current_time( 'mysql' ) ), array( 'id' => (int) $id ) );
	}

	public static function get( $id ) {
		global $wpdb;
		$table = ET_Database::table( 'help_requests' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ) );
	}

	public static function pending_for_task( $task_id ) {
		global $wpdb;
		$table = ET_Database::table( 'help_requests' );
		$emp   = ET_Database::table( 'employees' );
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT h.*, ef.name AS from_name, et.name AS to_name FROM {$table} h
			 LEFT JOIN {$emp} ef ON ef.id = h.from_employee_id LEFT JOIN {$emp} et ON et.id = h.to_employee_id
			 WHERE h.task_id = %d AND h.status = 'pending' ORDER BY h.id DESC",
			(int) $task_id
		) );
	}

	public static function pending_for_tasks( $task_ids ) {
		global $wpdb;
		$task_ids = array_filter( array_map( 'intval', (array) $task_ids ) );
		if ( empty( $task_ids ) ) return array();
		$table        = ET_Database::table( 'help_requests' );
		$emp          = ET_Database::table( 'employees' );
		$placeholders = implode( ',', array_fill( 0, count( $task_ids ), '%d' ) );
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT h.*, ef.name AS from_name, et.name AS to_name FROM {$table} h
			 LEFT JOIN {$emp} ef ON ef.id = h.from_employee_id LEFT JOIN {$emp} et ON et.id = h.to_employee_id
			 WHERE h.task_id IN ({$placeholders}) AND h.status = 'pending' ORDER BY h.id DESC",
			$task_ids
		) );
		$out = array();
		foreach ( $rows as $r ) { $out[ (int) $r->task_id ][] = $r; }
		return $out;
	}

	/** كل طلبات المساعدة الموجّهة لموظف معيّن وما زالت معلّقة — تُستخدم لتنبيهه بواجهته */
	public static function pending_for_recipient( $employee_id ) {
		global $wpdb;
		$table = ET_Database::table( 'help_requests' );
		$emp   = ET_Database::table( 'employees' );
		$tasks = ET_Database::table( 'weekly_tasks' );
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT h.*, ef.name AS from_name, t.title AS task_title FROM {$table} h
			 LEFT JOIN {$emp} ef ON ef.id = h.from_employee_id LEFT JOIN {$tasks} t ON t.id = h.task_id
			 WHERE h.to_employee_id = %d AND h.status = 'pending' ORDER BY h.id DESC",
			(int) $employee_id
		) );
	}
}
