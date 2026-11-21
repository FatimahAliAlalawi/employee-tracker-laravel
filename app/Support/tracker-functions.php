<?php

/* دوال عامة منقولة من البلجن (تطبيع الوقت + إرسال التذكيرات) */

function et_normalize_hhmm( $raw ) {
	$raw = trim( (string) $raw );
	if ( $raw === '' ) return '';
	if ( ! preg_match( '/^(\d{1,2}):(\d{1,2})/', $raw, $m ) ) return '';
	$h = max( 0, min( 23, (int) $m[1] ) );
	$i = max( 0, min( 59, (int) $m[2] ) );
	return sprintf( '%02d:%02d', $h, $i );
}

function et_send_employee_reminders( $not_submitted, $subject = '' ) {
	if ( empty( $subject ) ) $subject = 'تذكير: لم يصلنا تحديثك اليومي';
	$results = array();

	foreach ( $not_submitted as $emp ) {
		if ( empty( $emp->email ) || ! is_email( $emp->email ) ) {
			$results[] = array( 'name' => $emp->name, 'email' => '', 'ok' => false, 'error' => 'لا يوجد بريد إلكتروني مسجّل لهذا الموظف.' );
			error_log( '[ET_EmailNotify] تعذّر تذكير الموظف "' . $emp->name . '" — لا يوجد بريد إلكتروني مسجّل له.' );
			continue;
		}

		$message  = 'مرحبًا ' . $emp->name . "،\n\n";
		$message .= "لم يصلنا تحديثك اليومي حتى الآن. الرجاء تسجيل دخولك لنظام متابعة الموظفين وإرسال تحديث اليوم في أقرب وقت.\n\n";
		if ( ! empty( $emp->last_update_date ) ) {
			$message .= 'آخر تحديث مسجّل لك كان بتاريخ: ' . $emp->last_update_date . "\n\n";
		}
		$message .= "شكرًا لتعاونك.";

		$diag = ET_EmailNotify::send( $emp->email, $subject, $message, true );
		$results[] = array(
			'name'  => $emp->name,
			'email' => $emp->email,
			'ok'    => ! empty( $diag['ok'] ),
			'error' => $diag['error'] ?? null,
		);
	}

	return $results;
}

/**
 * يرسل لكل موظف تذكيرًا واحدًا يجمع كل مهامه غير المنجزة من يوم عمل محدد (عادة "أمس")، بدل
 * رسالة منفصلة لكل مهمة. تُستخدم من كل من: الكرون التلقائي (كل صباح)، وزر "تشغيل الآن" التشخيصي.
 *
 * @param array  $grouped مخرجات ET_WeeklyTasks::incomplete_by_employee_as_of() (أو incomplete_by_employee_for_date()) مباشرة.
 * @return array مصفوفة عناصر { name, email, ok, error?, tasks_count } لكل موظف تمت معالجته.
 */
function et_send_task_reminders( $grouped, $subject = '' ) {
	if ( empty( $subject ) ) $subject = 'تذكير: لديك مهام لم تُنجز بعد';
	$results = array();

	foreach ( $grouped as $row ) {
		$emp   = $row['employee'];
		$tasks = $row['tasks'];

		if ( empty( $emp['email'] ) || ! is_email( $emp['email'] ) ) {
			$results[] = array( 'name' => $emp['name'], 'email' => '', 'ok' => false, 'tasks_count' => count( $tasks ), 'error' => 'لا يوجد بريد إلكتروني مسجّل لهذا الموظف.' );
			error_log( '[ET_EmailNotify] تعذّر تذكير الموظف "' . $emp['name'] . '" بمهامه المتأخرة — لا يوجد بريد إلكتروني مسجّل له.' );
			continue;
		}

		$message  = 'مرحبًا ' . $emp['name'] . "،\n\n";
		$message .= count( $tasks ) === 1
			? "لديك مهمة سابقة لم تُنجز بعد:\n\n"
			: 'لديك ' . count( $tasks ) . " مهام سابقة لم تُنجز بعد:\n\n";
		foreach ( $tasks as $t ) {
			$message .= '• ' . $t['title'] . "\n";
		}
		$message .= "\nالرجاء تسجيل دخولك ومتابعتها في أقرب وقت.\n\nشكرًا لتعاونك.";

		$diag = ET_EmailNotify::send( $emp['email'], $subject, $message, true );
		$results[] = array(
			'name'        => $emp['name'],
			'email'       => $emp['email'],
			'ok'          => ! empty( $diag['ok'] ),
			'tasks_count' => count( $tasks ),
			'error'       => $diag['error'] ?? null,
		);
	}

	return $results;
}

/**
 * يرسل بريدًا للموظف بإشعاره أن الإدارة رفعت له مهامًا أسبوعية جديدة، مع تفصيل كل مهمة
 * (اليوم المستهدف من الأحد إلى الخميس + مدى الأهمية). تُستدعى من صفحة "المهام الأسبوعية"
 * في الإدارة مباشرة عند إضافة المهام، إذا كان خيار "إرسال بريد إلكتروني للموظف" مفعّلاً.
 *
 * @param object $employee صف الموظف (يجب أن يحتوي name/email).
 * @param array  $tasks    مصفوفة عناصر { title, task_day, priority } للمهام المُضافة للتو.
 * @return array مصفوفة تشخيصية { ok, to?, error? } مطابقة لمخرجات ET_EmailNotify::send().
 */
function et_send_weekly_tasks_notification( $employee, $tasks ) {
	if ( empty( $employee->email ) || ! is_email( $employee->email ) ) {
		return array( 'ok' => false, 'error' => 'لا يوجد بريد إلكتروني مسجّل لهذا الموظف — أضفه من ملفه الشخصي أولاً.' );
	}

	$settings    = ET_Settings::get();
	$system_name = ! empty( $settings['system_name'] ) ? $settings['system_name'] : 'متابعة';
	$subject     = 'لديك مهام جديدة — ' . $system_name;

	$message  = 'مرحبًا ' . $employee->name . "،\n\n";
	$message .= 'رفعت لك الإدارة ' . count( $tasks ) . ' مهمة جديدة عبر نظام "' . $system_name . '". الرجاء تسجيل الدخول ومراجعتها:' . "\n\n";

	foreach ( $tasks as $t ) {
		$day      = ET_WeeklyTasks::day_label( $t['task_day'] ?? '' );
		$priority = ET_WeeklyTasks::priority_label( $t['priority'] ?? 'medium' );
		$message .= '• [' . $day . ' — الأهمية: ' . $priority . '] ' . $t['title'] . "\n";
	}

	$message .= "\nشكرًا لتعاونك.";

	return ET_EmailNotify::send( $employee->email, $subject, $message, true );
}
