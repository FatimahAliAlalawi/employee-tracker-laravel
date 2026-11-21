<?php

/*
 * منقول من بلجن ووردبريس employee-tracker 2.9.1 — منطق العمل كما هو،
 * يعمل فوق Laravel عبر app/Support/helpers.php (لا اعتماد على ووردبريس).
 */

class ET_Export {

	/** يبني ملف CSV للتحديثات اليومية حسب الفلاتر ويرسله للتنزيل (ينهي التنفيذ) */
	public static function updates_csv( $args = array() ) {
		$rows = ET_Updates::query_all( $args );

		self::stream(
			'التحديثات-اليومية-' . current_time( 'Y-m-d' ) . '.csv',
			array( 'التاريخ', 'الموظف', 'القسم', 'الإنجازات', 'المهام الحالية', 'يوجد مشكلة', 'وصف المشكلة', 'أولوية المشكلة', 'تأثير المشكلة', 'يحتاج مساعدة', 'وصف المساعدة', 'خطة الغد', 'وقت الإرسال' ),
			$rows,
			function ( $row ) {
				return array(
					$row->update_date,
					$row->employee_name,
					$row->department_name,
					$row->achievements,
					$row->current_tasks,
					! empty( $row->has_problem ) ? 'نعم' : 'لا',
					$row->problem_description,
					$row->problem_priority ? ET_Problems::priority_label( $row->problem_priority ) : '',
					$row->problem_impact,
					! empty( $row->assistance_required ) ? 'نعم' : 'لا',
					$row->assistance_description,
					$row->tomorrow_plan,
					$row->created_at,
				);
			}
		);
	}

	/** يبني ملف CSV للمشاكل حسب الفلاتر ويرسله للتنزيل (ينهي التنفيذ) */
	public static function problems_csv( $args = array() ) {
		$rows = ET_Problems::query_all( $args );

		self::stream(
			'المشاكل-' . current_time( 'Y-m-d' ) . '.csv',
			array( 'العنوان', 'الموظف', 'القسم', 'الوصف', 'الأولوية', 'التأثير', 'الحالة', 'المسؤول', 'الحل', 'تاريخ الإنشاء', 'تاريخ الحل' ),
			$rows,
			function ( $row ) {
				return array(
					$row->title,
					$row->employee_name,
					$row->department_name,
					$row->description,
					ET_Problems::priority_label( $row->priority ),
					$row->impact,
					ET_Problems::status_label( $row->status ),
					$row->assigned_to,
					$row->solution,
					$row->created_at,
					$row->resolved_at,
				);
			}
		);
	}

	/** يبني ملف CSV لتقرير أداء المهام ضمن نطاق تاريخ محدد (وموظف واحد اختياريًا) ويرسله للتنزيل (ينهي التنفيذ) */
	public static function weekly_task_report_csv( $date_from, $date_to, $employee_id = 0 ) {
		$rows = ET_WeeklyTasks::employee_stats_for_range( $date_from, $date_to, $employee_id );

		self::stream(
			'تقرير-المهام-' . $date_from . '_إلى_' . $date_to . '.csv',
			array( 'الموظف', 'المهام المسندة', 'المنجزة', 'متأخرة (تاريخ الاستحقاق)', 'متأخرة (يوم العمل المحدد)', 'نسبة الإنجاز' ),
			$rows,
			function ( $row ) {
				return array(
					$row['name'],
					$row['assigned'],
					$row['completed'],
					$row['delayed_due'],
					$row['delayed_day'],
					$row['completion_percent'] . '%',
				);
			}
		);
	}

	/** يبني ملف CSV لتقرير الحضور والانصراف المُجمّع ضمن نطاق تاريخ ويرسله للتنزيل (ينهي التنفيذ) */
	public static function attendance_csv( $rows, $date_from, $date_to ) {
		self::stream(
			'تقرير-الحضور-' . $date_from . '_إلى_' . $date_to . '.csv',
			array( 'الموظف', 'القسم', 'أيام الحضور', 'أيام التأخير', 'أيام الغياب', 'أيام الإجازة', 'أيام الإجازة المرضية', 'أيام الاستئذان', 'إجمالي دقائق التأخير', 'إجمالي ساعات العمل' ),
			$rows,
			function ( $row ) {
				return array(
					$row['employee_name'],
					$row['department_name'],
					$row['present'],
					$row['late'],
					$row['absent'],
					$row['leave'],
					$row['sick'],
					$row['permission'],
					$row['late_minutes'],
					ET_Attendance::format_worked( $row['worked_minutes'] ),
				);
			}
		);
	}

	/** يطبع رؤوس التنزيل ثم محتوى CSV مع BOM حتى يقرأ إكسل النص العربي بشكل صحيح، وينهي التنفيذ */
	private static function stream( $filename, $headers, $rows, $row_mapper ) {
		$out = fopen( 'php://temp', 'w+' );
		fwrite( $out, "\xEF\xBB\xBF" ); // BOM
		fputcsv( $out, $headers );
		foreach ( $rows as $row ) {
			fputcsv( $out, array_map( array( 'ET_Security', 'csv_safe' ), $row_mapper( $row ) ) );
		}
		rewind( $out );
		$csv = stream_get_contents( $out );
		fclose( $out );

		$safe_name = str_replace( array( '"', "\r", "\n" ), '', $filename );
		throw new \Illuminate\Http\Exceptions\HttpResponseException( response( $csv, 200, array(
			'Content-Type'        => 'text/csv; charset=UTF-8',
			'Content-Disposition' => "attachment; filename*=UTF-8''" . rawurlencode( $safe_name ),
			'Cache-Control'       => 'no-store, no-cache, must-revalidate, max-age=0',
			'Pragma'              => 'public',
		) ) );
	}
}
