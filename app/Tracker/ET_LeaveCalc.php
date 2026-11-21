<?php

/*
 * منقول من بلجن ووردبريس employee-tracker 2.9.1 — منطق العمل كما هو،
 * يعمل فوق Laravel عبر app/Support/helpers.php (لا اعتماد على ووردبريس).
 */

class ET_LeaveCalc {

	/** هل هذا التاريخ (Y-m-d) يقع ضمن عطلة نهاية الأسبوع (الجمعة/السبت)؟ */
	/** يوم عطلة = ليس من أيام الدوام المحددة بإعدادات الحضور (الجمعة والسبت افتراضيًا إن لم تُحدَّد) */
	public static function is_weekend( $date_str ) {
		$working = ET_Attendance::settings()['working_days'];
		if ( empty( $working ) ) {
			$dow = (int) date( 'N', strtotime( $date_str ) ); // الاثنين=1 ... الأحد=7
			return $dow === 5 || $dow === 6;
		}
		return ! ET_Attendance::is_working_day( $date_str );
	}

	/**
	 * عدد أيام العمل الفعلية بين تاريخين (شاملين للطرفين) مع استثناء كل جمعة وسبت تقع بينهما.
	 * مثال: الخميس → الأحد = 2 (الخميس + الأحد، بعد استثناء الجمعة والسبت).
	 *
	 * @return int|WP_Error
	 */
	public static function working_days( $start_date, $end_date ) {
		$start_ts = strtotime( (string) $start_date );
		$end_ts   = strtotime( (string) $end_date );
		if ( ! $start_ts || ! $end_ts ) {
			return new WP_Error( 'et_leave_bad_dates', __( 'التواريخ المُدخلة غير صحيحة.'), array( 'status' => 400 ) );
		}
		if ( $end_ts < $start_ts ) {
			return new WP_Error( 'et_leave_bad_range', __( 'تاريخ النهاية يجب أن يكون بعد تاريخ البداية.'), array( 'status' => 400 ) );
		}

		$count   = 0;
		$cursor  = $start_ts;
		$guard   = 0; // شبكة أمان ضد حلقة لا نهائية بمدخل خاطئ
		while ( $cursor <= $end_ts && $guard < 3660 ) {
			if ( ! self::is_weekend( date( 'Y-m-d', $cursor ) ) ) {
				$count++;
			}
			$cursor = strtotime( '+1 day', $cursor );
			$guard++;
		}
		return $count;
	}

	/** عدد الأيام التقويمية (بدون استثناء) بين تاريخين شاملين للطرفين — تُستخدم للإجازة المرضية */
	public static function calendar_days( $start_date, $end_date ) {
		$start_ts = strtotime( (string) $start_date );
		$end_ts   = strtotime( (string) $end_date );
		if ( ! $start_ts || ! $end_ts ) {
			return new WP_Error( 'et_leave_bad_dates', __( 'التواريخ المُدخلة غير صحيحة.'), array( 'status' => 400 ) );
		}
		if ( $end_ts < $start_ts ) {
			return new WP_Error( 'et_leave_bad_range', __( 'تاريخ النهاية يجب أن يكون بعد تاريخ البداية.'), array( 'status' => 400 ) );
		}
		return (int) round( ( $end_ts - $start_ts ) / DAY_IN_SECONDS ) + 1;
	}
}
