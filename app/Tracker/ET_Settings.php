<?php

/*
 * منقول من بلجن ووردبريس employee-tracker 2.9.1 — منطق العمل كما هو،
 * يعمل فوق Laravel عبر app/Support/helpers.php (لا اعتماد على ووردبريس).
 */

class ET_Settings {

	public static function defaults() {
		return array(
			'system_name'            => 'متابعة',
			'end_of_day_time'        => '15:00',
			'reminder_time'          => '13:30',
			'evening_reminder_time'  => '',
			'edit_window_minutes'    => 120,
			'allow_multiple_updates' => 0,
			'employee_session_hours' => 12,
			'show_gamification'      => 1,
			'max_upload_size_mb'     => 10,
			'delete_data_on_uninstall' => 0,
			'whatsapp_notify_enabled'   => 0,
			'whatsapp_admin_phone'      => '',
			'whatsapp_phone_number_id'  => '',
			'whatsapp_access_token'     => '',
			'whatsapp_template_name'    => '',
			'whatsapp_template_lang'    => '',
			'email_notify_enabled'    => 0,
			'email_notify_address'    => '',
			'email_notify_subject'    => 'إشعار تحديث موظف جديد',
			'employee_reminder_enabled' => 0,
			'employee_reminder_subject' => 'تذكير: لم يصلنا تحديثك اليومي',
			'task_reminder_enabled'  => 0,
			'task_reminder_time'     => '08:00',
			'task_reminder_subject'  => 'تذكير: لديك مهام لم تُنجز من يوم أمس',
			// نظام "الإجازات والاستئذانات": الرصيد السنوي الأساسي (معلوماتي/سقف مرجعي) والتراكم الشهري —
			// قابلان للتعديل من الإعدادات وليسا ثابتين (Hard-coded) داخل الواجهة أو منطق الحساب.
			'leave_annual_base_days'  => 21,
			'leave_monthly_accrual'   => 2.5,
			'leave_notify_email_enabled' => 1,
			// نظام "الحضور والانصراف": فترتا دوام منفصلتان (صباحية/مسائية) — كل القيم قابلة للتعديل من
			// الإعدادات، ولا شيء منها Hard-coded. حقل "الفترة" الموجود أصلاً بجدول الموظفين (shift:
			// morning/evening/both) هو ما يحدد أي فترة تُطبَّق على كل موظف (راجع ET_Attendance::schedule_for_employee).
			'attendance_morning_start'   => '08:00',
			'attendance_morning_end'     => '12:00',
			'attendance_evening_start'   => '16:00',
			'attendance_evening_end'     => '20:00',
			'attendance_grace_minutes'   => 0,
			'attendance_work_hours'      => 8,
			'attendance_location_lat'    => '',
			'attendance_location_lng'    => '',
			'attendance_location_radius' => 100,
			'attendance_working_days'    => 'sunday,monday,tuesday,wednesday,thursday',
			// طبقة تحقق إضافية اختيارية للحضور: إحداثيات GPS يرسلها جهاز الموظف ويمكن تزويرها، فإن حُدّدت
			// هنا عناوين IP (شبكة المعهد) لا يُقبل الحضور/الانصراف إلا من داخلها. فارغ = معطّل.
			'attendance_allowed_ips'     => '',
		);
	}

	public static function get() {
		$saved = get_option( 'et_settings', array() );
		return wp_parse_args( $saved, self::defaults() );
	}

	public static function update( $new_values ) {
		$current = self::get();
		$merged  = wp_parse_args( $new_values, $current );
		update_option( 'et_settings', $merged );
		return $merged;
	}
}
