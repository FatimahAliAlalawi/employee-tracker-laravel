<?php

/*
 * منقول من بلجن ووردبريس employee-tracker 2.9.1 — منطق العمل كما هو،
 * يعمل فوق Laravel عبر app/Support/helpers.php (لا اعتماد على ووردبريس).
 */

class ET_LeaveBalances {

	public static function table() {
		return ET_Database::table( 'leave_balances' );
	}

	/** يعيد صف رصيد الموظف، وينشئه تلقائيًا عند أول استخدام (تاريخ استحقاق = تاريخ إضافة الموظف) */
	public static function get_or_create( $employee_id ) {
		global $wpdb;
		$table = self::table();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE employee_id = %d", $employee_id ) );
		if ( $row ) return $row;

		$employee = ET_Employees::get( $employee_id );
		$start    = $employee && ! empty( $employee->created_at ) ? date( 'Y-m-d', strtotime( $employee->created_at ) ) : current_time( 'Y-m-d' );

		$wpdb->insert( $table, array(
			'employee_id'            => (int) $employee_id,
			'accrual_start_date'     => $start,
			'manual_adjustment_days' => 0,
			'created_at'             => current_time( 'mysql' ),
			'updated_at'             => current_time( 'mysql' ),
		) );

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE employee_id = %d", $employee_id ) );
	}

	/** تعديل تاريخ بداية الاستحقاق و/أو تعديل يدوي (+/-) على الرصيد من الإدارة */
	public static function set( $employee_id, $accrual_start_date = null, $manual_adjustment_days = null ) {
		global $wpdb;
		self::get_or_create( $employee_id );
		$table  = self::table();
		$fields = array( 'updated_at' => current_time( 'mysql' ) );
		if ( $accrual_start_date !== null && $accrual_start_date !== '' ) {
			$fields['accrual_start_date'] = date( 'Y-m-d', strtotime( $accrual_start_date ) );
		}
		if ( $manual_adjustment_days !== null && $manual_adjustment_days !== '' ) {
			$fields['manual_adjustment_days'] = (float) $manual_adjustment_days;
		}
		$wpdb->update( $table, $fields, array( 'employee_id' => (int) $employee_id ) );
		return self::get_or_create( $employee_id );
	}

	/** مجموع أيام الإجازة السنوية "الموافق عليها" فقط (قبل الموافقة لا يُخصم شيء) */
	public static function used_days( $employee_id ) {
		global $wpdb;
		$table = ET_Database::table( 'leave_requests' );
		$sum = $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(days_count),0) FROM {$table} WHERE employee_id = %d AND status = 'approved'", $employee_id
		) );
		return (float) $sum;
	}

	/**
	 * يحسب رصيد الموظف الكامل بناءً على تاريخ استحقاقه ومعدل التراكم الشهري (من الإعدادات،
	 * غير ثابت داخل الكود) — القيم الافتراضية 21 يومًا أساسيًا / 2.5 يوم شهريًا قابلة للتغيير
	 * من الإدارة مستقبلًا دون أي تعديل برمجي.
	 */
	public static function compute( $employee_id ) {
		$balance_row = self::get_or_create( $employee_id );
		$settings    = ET_Settings::get();
		$annual_base = (float) ( $settings['leave_annual_base_days'] ?? 21 );
		$monthly     = (float) ( $settings['leave_monthly_accrual'] ?? 2.5 );

		$start_ts = strtotime( $balance_row->accrual_start_date );
		$now_dt   = new DateTime( ET_Time::date( 'Y-m-d' ) );

		$months_elapsed = 0;
		if ( $start_ts && $now_dt > new DateTime( date( 'Y-m-d', $start_ts ) ) ) {
			$start_dt = new DateTime( date( 'Y-m-d', $start_ts ) );
			$diff     = $start_dt->diff( $now_dt );
			$months_elapsed = ( $diff->y * 12 ) + $diff->m;
			// يُحسب الشهر الجاري كمكتمل بمجرد بدء يوم الاستحقاق فيه (نفس يوم/بعد يوم الشهر التالي)
			if ( $diff->d > 0 ) $months_elapsed++;
		}

		// الرصيد الأساسي (21 يومًا افتراضيًا) متاح للموظف بالكامل منذ اليوم الأول — لا يحتاج لإكمال سنة
		// كاملة حتى يصل له تدريجيًا. التراكم الشهري (2.5 يوم افتراضيًا) يُضاف فوق هذا الأساس مع كل شهر
		// يمر بعد تاريخ الاستحقاق، فيستمر الرصيد بالزيادة بلا سقف كلما طالت مدة خدمة الموظف.
		$earned  = round( $annual_base + ( $months_elapsed * $monthly ), 2 );
		$manual  = (float) $balance_row->manual_adjustment_days;
		$used    = self::used_days( $employee_id );
		$total_entitled = round( $earned + $manual, 2 );
		$remaining      = round( $total_entitled - $used, 2 );

		return array(
			'employee_id'          => (int) $employee_id,
			'annual_base_days'     => $annual_base,
			'monthly_accrual'      => $monthly,
			'accrual_start_date'   => $balance_row->accrual_start_date,
			'months_elapsed'       => $months_elapsed,
			'earned_to_date'       => $earned,
			'manual_adjustment'    => $manual,
			'total_entitled'       => $total_entitled,
			'used_days'            => $used,
			'remaining_days'       => $remaining,
		);
	}
}
