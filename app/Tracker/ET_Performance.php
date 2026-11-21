<?php

/*
 * منقول من بلجن ووردبريس employee-tracker 2.9.1 — منطق العمل كما هو،
 * يعمل فوق Laravel عبر app/Support/helpers.php (لا اعتماد على ووردبريس).
 */

class ET_Performance {

	const WEIGHT_TASKS       = 0.5;
	const WEIGHT_CONSISTENCY = 0.3;
	const WEIGHT_QUALITY     = 0.2;

	/** يحسب مستوى أداء موظف واحد لشهر مُحدد ويرجع تفصيلًا كاملًا قابل للعرض */
	public static function calculate( $employee_id, $year, $month ) {
		global $wpdb;

		$year  = (int) $year;
		$month = max( 1, min( 12, (int) $month ) );

		$month_start   = sprintf( '%04d-%02d-01', $year, $month );
		$days_in_month = (int) date( 't', strtotime( $month_start ) );
		$month_end     = sprintf( '%04d-%02d-%02d', $year, $month, $days_in_month );
		$today         = current_time( 'Y-m-d' );
		$range_end     = ( $month_end < $today ) ? $month_end : $today;

		$result = array(
			'employee_id'   => (int) $employee_id,
			'year'          => $year,
			'month'         => $month,
			'overall_score' => null,
			'level'         => 'لا توجد بيانات كافية بعد',
			'level_color'   => 'gray',
			'breakdown'     => array(
				'tasks'       => array( 'score' => null, 'on_time' => 0, 'late' => 0, 'total' => 0 ),
				'consistency' => array( 'score' => null, 'submitted_days' => 0, 'expected_days' => 0 ),
				'quality'     => array( 'score' => null, 'issue_updates' => 0, 'total_updates' => 0 ),
			),
		);

		if ( $range_end < $month_start ) {
			return $result; // شهر مستقبلي بالكامل: لا بيانات بعد
		}

		$tasks_table   = ET_Database::table( 'weekly_tasks' );
		$updates_table = ET_Database::table( 'daily_updates' );

		// ------- 1) إنجاز المهام الأسبوعية في وقتها -------
		$day_offset = array( 'sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4 );
		$tasks = $wpdb->get_results( $wpdb->prepare(
			"SELECT status, completed_at, week_start_date, task_day FROM {$tasks_table} WHERE employee_id = %d AND week_start_date BETWEEN %s AND %s",
			$employee_id,
			date( 'Y-m-d', strtotime( $month_start . ' -7 days' ) ),
			$month_end
		) );

		$on_time = 0; $late = 0;
		foreach ( $tasks as $t ) {
			$offset = isset( $day_offset[ $t->task_day ] ) ? $day_offset[ $t->task_day ] : 4; // بدون يوم محدد = تُحسب على نهاية الأسبوع (الخميس)
			$due    = date( 'Y-m-d', strtotime( $t->week_start_date . " +{$offset} days" ) );
			if ( $due < $month_start || $due > $month_end || $due > $today ) continue; // خارج الشهر أو لم يحن موعدها بعد
			$completed_on_time = ( $t->status === 'completed' && $t->completed_at && substr( $t->completed_at, 0, 10 ) <= $due );
			if ( $completed_on_time ) $on_time++; else $late++;
		}
		$total_due  = $on_time + $late;
		$task_score = $total_due > 0 ? round( ( $on_time / $total_due ) * 100 ) : null;

		// ------- 2) الانتظام في إرسال التحديث اليومي (أيام العمل الأحد-الخميس) -------
		$expected_days = 0;
		$cursor = $month_start;
		while ( $cursor <= $range_end ) {
			$dow = (int) date( 'w', strtotime( $cursor ) ); // 0=أحد ... 6=سبت
			if ( $dow !== 5 && $dow !== 6 ) $expected_days++;
			$cursor = date( 'Y-m-d', strtotime( $cursor . ' +1 day' ) );
		}
		$submitted_days = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(DISTINCT update_date) FROM {$updates_table} WHERE employee_id = %d AND update_date BETWEEN %s AND %s",
			$employee_id, $month_start, $range_end
		) );
		$consistency_score = $expected_days > 0 ? min( 100, round( ( $submitted_days / $expected_days ) * 100 ) ) : null;

		// ------- 3) قلة المشاكل/طلبات المساعدة المتكررة -------
		$total_updates = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$updates_table} WHERE employee_id = %d AND update_date BETWEEN %s AND %s",
			$employee_id, $month_start, $range_end
		) );
		$issue_updates = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$updates_table} WHERE employee_id = %d AND update_date BETWEEN %s AND %s AND (has_problem = 1 OR assistance_required = 1)",
			$employee_id, $month_start, $range_end
		) );
		$quality_score = $total_updates > 0 ? round( 100 * ( 1 - min( 1, $issue_updates / $total_updates ) ) ) : null;

		// ------- الدمج المرجّح: أي معيار بلا بيانات كافية يُستبعد وتُعاد توزيع وزنه على البقية بدل معاقبة الموظف على نقص بيانات -------
		$components = array(
			array( $task_score, self::WEIGHT_TASKS ),
			array( $consistency_score, self::WEIGHT_CONSISTENCY ),
			array( $quality_score, self::WEIGHT_QUALITY ),
		);
		$weight_sum = 0; $score_sum = 0;
		foreach ( $components as $c ) {
			list( $val, $w ) = $c;
			if ( $val !== null ) { $weight_sum += $w; $score_sum += $val * $w; }
		}
		$overall = $weight_sum > 0 ? (int) round( $score_sum / $weight_sum ) : null;
		$level   = self::level_info( $overall );

		$result['overall_score'] = $overall;
		$result['level']         = $level['label'];
		$result['level_color']   = $level['color'];
		$result['breakdown']     = array(
			'tasks'       => array( 'score' => $task_score, 'on_time' => $on_time, 'late' => $late, 'total' => $total_due ),
			'consistency' => array( 'score' => $consistency_score, 'submitted_days' => $submitted_days, 'expected_days' => $expected_days ),
			'quality'     => array( 'score' => $quality_score, 'issue_updates' => $issue_updates, 'total_updates' => $total_updates ),
		);
		return $result;
	}

	/** يحسب مستوى أداء كل الموظفين النشطين لشهر مُحدد، مرتبة من الأعلى للأدنى (تُستخدم في تبويب الإدارة) */
	public static function calculate_all( $year, $month ) {
		global $wpdb;
		$employees_table = ET_Database::table( 'employees' );
		$rows = $wpdb->get_results( "SELECT id, name FROM {$employees_table} WHERE status = 'active' ORDER BY name ASC" );

		$out = array();
		foreach ( $rows as $r ) {
			$perf = self::calculate( $r->id, $year, $month );
			$perf['employee_name'] = $r->name;
			$out[] = $perf;
		}

		usort( $out, function ( $a, $b ) {
			if ( $a['overall_score'] === null && $b['overall_score'] === null ) return 0;
			if ( $a['overall_score'] === null ) return 1;
			if ( $b['overall_score'] === null ) return -1;
			return $b['overall_score'] <=> $a['overall_score'];
		} );

		return $out;
	}

	private static function level_info( $score ) {
		if ( $score === null ) return array( 'label' => 'لا توجد بيانات كافية بعد', 'color' => 'gray' );
		if ( $score >= 85 )   return array( 'label' => 'مستوى عالٍ', 'color' => 'green' );
		if ( $score >= 60 )   return array( 'label' => 'مستوى متوسط', 'color' => 'orange' );
		return array( 'label' => 'مستوى منخفض', 'color' => 'red' );
	}
}
