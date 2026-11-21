<?php

/*
 * منقول من بلجن ووردبريس employee-tracker 2.9.1 — منطق العمل كما هو،
 * يعمل فوق Laravel عبر app/Support/helpers.php (لا اعتماد على ووردبريس).
 */

class ET_Attendance {

	const DAY_MAP = array( 'sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6 );

	public static function table() { return ET_Database::table( 'attendance_records' ); }
	public static function audit_table() { return ET_Database::table( 'attendance_audit_logs' ); }

	/** إعدادات الدوام الحالية — كلها قابلة للتعديل من الإدارة، لا شيء ثابت (Hard-coded) */
	public static function settings() {
		$s = ET_Settings::get();
		return array(
			'morning_start' => $s['attendance_morning_start'],
			'morning_end'   => $s['attendance_morning_end'],
			'evening_start' => $s['attendance_evening_start'],
			'evening_end'   => $s['attendance_evening_end'],
			'grace_minutes' => (int) $s['attendance_grace_minutes'],
			'work_hours'    => (float) $s['attendance_work_hours'],
			'location_lat'  => $s['attendance_location_lat'] !== '' ? (float) $s['attendance_location_lat'] : null,
			'location_lng'  => $s['attendance_location_lng'] !== '' ? (float) $s['attendance_location_lng'] : null,
			'radius_meters' => (int) $s['attendance_location_radius'],
			'working_days'  => array_values( array_filter( array_map( 'trim', explode( ',', (string) $s['attendance_working_days'] ) ) ) ),
			'allowed_ips'   => (string) ( $s['attendance_allowed_ips'] ?? '' ),
		);
	}

	/**
	 * فترة الدوام الفعلية المُطبَّقة على موظف مُحدد، حسب حقل "الفترة" الموجود أصلاً بجدول الموظفين
	 * (shift: morning/evening/both) — دون أي حقل جديد. موظف "كلا الفترتين" يُعامَل كدوام متواصل
	 * يبدأ من بداية الفترة الصباحية وينتهي بنهاية الفترة المسائية (حضور/انصراف واحد يغطي اليوم كاملاً).
	 *
	 * كما تُطبَّق أوقات دوام مخصصة اختيارية لكل موظف (att_*_override) إن وُجدت — لأن بعض الموظفين
	 * يبدؤون فترتهم الساعة 8 وآخرون الساعة 9 مثلًا. إن تُرك الحقل فارغًا يُستخدم الإعداد العام كما هو.
	 */
	public static function schedule_for_employee( $employee ) {
		$settings = self::settings();
		$shift = ( $employee && ! empty( $employee->shift ) ) ? $employee->shift : 'both';

		$morning_start = ( $employee && ! empty( $employee->att_morning_start_override ) ) ? $employee->att_morning_start_override : $settings['morning_start'];
		$morning_end   = ( $employee && ! empty( $employee->att_morning_end_override ) )   ? $employee->att_morning_end_override   : $settings['morning_end'];
		$evening_start = ( $employee && ! empty( $employee->att_evening_start_override ) ) ? $employee->att_evening_start_override : $settings['evening_start'];
		$evening_end   = ( $employee && ! empty( $employee->att_evening_end_override ) )   ? $employee->att_evening_end_override   : $settings['evening_end'];

		if ( $shift === 'morning' ) {
			return array( 'start' => $morning_start, 'end' => $morning_end );
		}
		if ( $shift === 'evening' ) {
			return array( 'start' => $evening_start, 'end' => $evening_end );
		}
		return array( 'start' => $morning_start, 'end' => $evening_end );
	}

	public static function update_settings( $data ) {
		$new = array();
		// أوقات الدوام تُحفظ دائمًا بصيغة HH:MM؛ أي قيمة غير مفهومة تُرفض بدل أن تُفسَّر لاحقًا كمنتصف الليل
		foreach ( array( 'morning_start', 'morning_end', 'evening_start', 'evening_end' ) as $key ) {
			if ( ! isset( $data[ $key ] ) || $data[ $key ] === '' ) continue;
			$time = et_normalize_hhmm( $data[ $key ] );
			if ( $time === '' ) {
				/* translators: %s: القيمة المُدخلة */
				return new WP_Error( 'et_att_bad_time', sprintf( __( 'وقت الدوام "%s" غير صحيح، استخدم الصيغة 08:00.'), sanitize_text_field( $data[ $key ] ) ), array( 'status' => 400 ) );
			}
			$new[ 'attendance_' . $key ] = $time;
		}
		if ( isset( $data['grace_minutes'] ) && $data['grace_minutes'] !== '' ) $new['attendance_grace_minutes'] = max( 0, (int) $data['grace_minutes'] );
		if ( isset( $data['work_hours'] ) && $data['work_hours'] !== '' )   $new['attendance_work_hours'] = max( 0, (float) $data['work_hours'] );
		if ( isset( $data['location_lat'] ) && $data['location_lat'] !== '' ) $new['attendance_location_lat'] = (string) (float) $data['location_lat'];
		if ( isset( $data['location_lng'] ) && $data['location_lng'] !== '' ) $new['attendance_location_lng'] = (string) (float) $data['location_lng'];
		if ( isset( $data['radius_meters'] ) && $data['radius_meters'] !== '' ) $new['attendance_location_radius'] = max( 1, (int) $data['radius_meters'] );
		if ( isset( $data['working_days'] ) && is_array( $data['working_days'] ) ) {
			$valid = array_keys( self::DAY_MAP );
			$new['attendance_working_days'] = implode( ',', array_values( array_intersect( $valid, $data['working_days'] ) ) );
		}
		if ( isset( $data['allowed_ips'] ) && $data['allowed_ips'] !== null ) {
			$clean = array();
			foreach ( preg_split( '/[\s,]+/', (string) $data['allowed_ips'] ) as $entry ) {
				$entry = trim( $entry );
				if ( $entry === '' ) continue;
				$ip_part = strpos( $entry, '/' ) !== false ? explode( '/', $entry, 2 )[0] : $entry;
				if ( filter_var( $ip_part, FILTER_VALIDATE_IP ) ) $clean[] = $entry;
			}
			$new['attendance_allowed_ips'] = implode( ', ', array_unique( $clean ) );
		}
		ET_Settings::update( $new );
		return self::settings();
	}

	public static function is_working_day( $date_str ) {
		$settings = self::settings();
		$dow  = (int) date( 'w', strtotime( $date_str ) );
		$name = array_search( $dow, self::DAY_MAP, true );
		return $name && in_array( $name, $settings['working_days'], true );
	}

	/** مسافة هافرساين بالأمتار بين نقطتين جغرافيتين */
	public static function distance_meters( $lat1, $lng1, $lat2, $lng2 ) {
		$earth = 6371000;
		$d_lat = deg2rad( $lat2 - $lat1 );
		$d_lng = deg2rad( $lng2 - $lng1 );
		$a = sin( $d_lat / 2 ) * sin( $d_lat / 2 ) + cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) * sin( $d_lng / 2 ) * sin( $d_lng / 2 );
		$c = 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );
		return $earth * $c;
	}

	public static function get_record( $employee_id, $date ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE employee_id=%d AND attendance_date=%s', $employee_id, $date ) );
	}

	private static function client_ip() {
		return ET_Security::client_ip();
	}

	/** قائمة عناوين/شبكات IP المسموح منها بتسجيل الحضور (فارغة = بلا قيد) */
	public static function allowed_ips_list() {
		$s = ET_Settings::get();
		return array_values( array_filter( array_map( 'trim', preg_split( '/[\s,]+/', (string) ( $s['attendance_allowed_ips'] ?? '' ) ) ) ) );
	}

	/** يقبل عنوانًا مفردًا (1.2.3.4) أو شبكة CIDR (1.2.3.0/24 أو IPv6/64) */
	private static function ip_in_range( $ip, $range ) {
		if ( strpos( $range, '/' ) === false ) {
			return $ip === $range;
		}
		list( $subnet, $bits ) = explode( '/', $range, 2 );
		$ip_bin  = @inet_pton( $ip );
		$net_bin = @inet_pton( $subnet );
		if ( $ip_bin === false || $net_bin === false || strlen( $ip_bin ) !== strlen( $net_bin ) ) return false;
		$bits = (int) $bits;
		$max  = strlen( $ip_bin ) * 8;
		if ( $bits < 0 || $bits > $max ) return false;
		$full = intdiv( $bits, 8 );
		if ( substr( $ip_bin, 0, $full ) !== substr( $net_bin, 0, $full ) ) return false;
		$rem = $bits % 8;
		if ( $rem === 0 ) return true;
		$mask = chr( ( 0xFF << ( 8 - $rem ) ) & 0xFF );
		return ( $ip_bin[ $full ] & $mask ) === ( $net_bin[ $full ] & $mask );
	}

	/** طبقة تحقق إضافية: GPS يرسله جهاز الموظف ويمكن تزويره، أما عنوان IP فيحدده السيرفر */
	private static function validate_network() {
		$allowed = self::allowed_ips_list();
		if ( empty( $allowed ) ) return true;
		$ip = self::client_ip();
		foreach ( $allowed as $range ) {
			if ( self::ip_in_range( $ip, $range ) ) return true;
		}
		return new WP_Error( 'et_att_bad_network', __( 'لا يمكن تسجيل الحضور/الانصراف إلا من شبكة المعهد (Wi-Fi المعهد). اتصل بشبكة المعهد ثم حاول مجددًا.'), array( 'status' => 403 ) );
	}

	/** يتحقق Server-side من صحة الموقع المُرسل ويعيد المسافة أو WP_Error */
	private static function validate_location( $lat, $lng, $accuracy ) {
		$network = self::validate_network();
		if ( is_wp_error( $network ) ) return $network;

		if ( $lat === null || $lng === null || $lat === '' || $lng === '' ) {
			return new WP_Error( 'et_att_no_location', __( 'تعذّر الحصول على موقعك. تأكد من تفعيل خدمة الموقع (GPS) والسماح للمتصفح بالوصول إليه.'), array( 'status' => 400 ) );
		}
		$lat = (float) $lat; $lng = (float) $lng;
		if ( $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 ) {
			return new WP_Error( 'et_att_bad_location', __( 'إحداثيات الموقع غير صحيحة.'), array( 'status' => 400 ) );
		}
		$settings = self::settings();
		if ( $settings['location_lat'] === null || $settings['location_lng'] === null ) {
			return new WP_Error( 'et_att_no_settings', __( 'لم تُحدَّد الإدارة موقع المعهد بعد، يرجى مراجعة إعدادات الدوام.'), array( 'status' => 400 ) );
		}
		$accuracy = ( $accuracy !== null && $accuracy !== '' ) ? (float) $accuracy : null;
		if ( $accuracy !== null && $accuracy > 300 ) {
			return new WP_Error( 'et_att_low_accuracy', __( 'دقة تحديد موقعك منخفضة جدًا حاليًا. حاول مجددًا في مكان مفتوح، أو انتظر قليلًا حتى يتحسن استقبال GPS.'), array( 'status' => 400 ) );
		}
		$distance = self::distance_meters( $settings['location_lat'], $settings['location_lng'], $lat, $lng );
		if ( $distance > $settings['radius_meters'] ) {
			return new WP_Error(
				'et_att_out_of_range',
				sprintf(
					/* translators: 1: المسافة الفعلية بالمتر 2: الحد المسموح بالمتر */
					__( 'لا يمكن تسجيل الحضور لأنك خارج نطاق المعهد. موقعك يبعد %1$s مترًا عن المعهد، والحد المسموح %2$s مترًا.'),
					(int) round( $distance ), (int) $settings['radius_meters']
				),
				array( 'status' => 403, 'distance' => (int) round( $distance ), 'radius' => $settings['radius_meters'] )
			);
		}
		return $distance;
	}

	/** تسجيل حضور — كل التحقق يتم في السيرفر (النطاق، التكرار، الوقت) ولا يمكن تجاوزه من الواجهة */
	public static function check_in( $employee_id, $lat, $lng, $accuracy ) {
		global $wpdb;
		$employee = ET_Employees::get( $employee_id );
		if ( ! $employee ) return new WP_Error( 'et_not_found', __( 'الموظف غير موجود.'), array( 'status' => 404 ) );

		$today    = current_time( 'Y-m-d' );
		$existing = self::get_record( $employee_id, $today );
		if ( $existing && ! empty( $existing->check_in_time ) ) {
			return new WP_Error( 'et_att_already_checked_in', __( 'لقد سجّلت حضورك بالفعل اليوم.'), array( 'status' => 400 ) );
		}

		$distance = self::validate_location( $lat, $lng, $accuracy );
		if ( is_wp_error( $distance ) ) return $distance;

		$now_ts = time();
		list( $status, $late_minutes ) = self::lateness( $employee, $today, $now_ts );

		$fields = array(
			'employee_id'       => (int) $employee_id,
			'attendance_date'   => $today,
			'check_in_time'     => current_time( 'mysql' ),
			'check_in_lat'      => (float) $lat,
			'check_in_lng'      => (float) $lng,
			'check_in_accuracy' => ( $accuracy !== null && $accuracy !== '' ) ? (float) $accuracy : null,
			'check_in_distance' => round( $distance, 2 ),
			'check_in_ip'       => self::client_ip(),
			'status'            => $status,
			'late_minutes'      => $late_minutes,
			'source'            => 'gps',
			'is_manual'         => 0,
			'updated_at'        => current_time( 'mysql' ),
		);

		if ( $existing ) {
			$wpdb->update( self::table(), $fields, array( 'id' => $existing->id ) );
		} else {
			$fields['created_at'] = current_time( 'mysql' );
			$wpdb->insert( self::table(), $fields );
		}

		ET_AuditLog::log( sprintf( '%s سجّل حضوره الساعة %s (%s)', $employee->name, wp_date( 'H:i', $now_ts ), self::status_label( $status ) ) );

		return self::get_record( $employee_id, $today );
	}

	/** حالة الحضور (present/late) ودقائق التأخير لوقت حضور فعلي (طابع Unix) بتاريخ محدد، حسب دوام الموظف ومهلة السماح */
	public static function lateness( $employee, $date, $check_in_ts ) {
		if ( ! self::is_working_day( $date ) ) return array( 'present', 0 ); // حضور تطوعي يوم عطلة
		$settings = self::settings();
		$schedule = self::schedule_for_employee( $employee );
		$start_ts = ET_Time::ts( $date . ' ' . $schedule['start'] );
		if ( $start_ts === false ) return array( 'present', 0 );
		if ( $check_in_ts > $start_ts + ( $settings['grace_minutes'] * MINUTE_IN_SECONDS ) ) {
			return array( 'late', max( 0, (int) round( ( $check_in_ts - $start_ts ) / 60 ) ) );
		}
		return array( 'present', 0 );
	}

	/**
	 * إصلاح لمرة واحدة: يعيد حساب دقائق التأخير وساعات العمل (والحالة حاضر/متأخر) لسجلات GPS المحفوظة
	 * من أوقات الحضور/الانصراف المخزّنة نفسها — تلك الأوقات كانت دائمًا صحيحة، أما الأرقام المحسوبة منها
	 * فكانت مُزاحة بفرق منطقة PHP قبل إصلاح ET_Time. السجلات المعدّلة يدويًا من الإدارة لا تُمس.
	 * يعتمد على أوقات الدوام الحالية لكل موظف. ترجع عدد السجلات التي تغيّرت.
	 */
	public static function repair_stored_minutes() {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT * FROM ' . self::table() . " WHERE is_manual = 0 AND check_in_time IS NOT NULL AND status IN ('present','late')" );
		$employees = array();
		$changed   = 0;
		foreach ( (array) $rows as $row ) {
			$eid = (int) $row->employee_id;
			if ( ! array_key_exists( $eid, $employees ) ) $employees[ $eid ] = ET_Employees::get( $eid );
			if ( ! $employees[ $eid ] ) continue;

			$in_ts = ET_Time::ts( $row->check_in_time );
			if ( $in_ts === false ) continue;
			list( $status, $late ) = self::lateness( $employees[ $eid ], $row->attendance_date, $in_ts );

			$worked = null;
			if ( ! empty( $row->check_out_time ) ) {
				$out_ts = ET_Time::ts( $row->check_out_time );
				if ( $out_ts !== false ) $worked = max( 0, (int) round( ( $out_ts - $in_ts ) / 60 ) );
			}

			$old_worked = $row->worked_minutes === null ? null : (int) $row->worked_minutes;
			if ( $status === $row->status && $late === (int) $row->late_minutes && $worked === $old_worked ) continue;

			$wpdb->update( self::table(), array( 'status' => $status, 'late_minutes' => $late, 'worked_minutes' => $worked ), array( 'id' => (int) $row->id ) );
			$changed++;
		}
		return $changed;
	}

	public static function check_out( $employee_id, $lat, $lng, $accuracy ) {
		global $wpdb;
		$employee = ET_Employees::get( $employee_id );
		if ( ! $employee ) return new WP_Error( 'et_not_found', __( 'الموظف غير موجود.'), array( 'status' => 404 ) );

		$today    = current_time( 'Y-m-d' );
		$existing = self::get_record( $employee_id, $today );
		if ( ! $existing || empty( $existing->check_in_time ) ) {
			return new WP_Error( 'et_att_no_checkin', __( 'يجب تسجيل الحضور أولًا قبل تسجيل الانصراف.'), array( 'status' => 400 ) );
		}
		if ( ! empty( $existing->check_out_time ) ) {
			return new WP_Error( 'et_att_already_checked_out', __( 'لقد سجّلت انصرافك بالفعل اليوم.'), array( 'status' => 400 ) );
		}

		$distance = self::validate_location( $lat, $lng, $accuracy );
		if ( is_wp_error( $distance ) ) return $distance;

		$now_ts         = time();
		$in_ts          = ET_Time::ts( $existing->check_in_time );
		$worked_minutes = max( 0, (int) round( ( $now_ts - $in_ts ) / 60 ) );

		$wpdb->update( self::table(), array(
			'check_out_time'     => current_time( 'mysql' ),
			'check_out_lat'      => (float) $lat,
			'check_out_lng'      => (float) $lng,
			'check_out_accuracy' => ( $accuracy !== null && $accuracy !== '' ) ? (float) $accuracy : null,
			'check_out_distance' => round( $distance, 2 ),
			'check_out_ip'       => self::client_ip(),
			'worked_minutes'     => $worked_minutes,
			'updated_at'         => current_time( 'mysql' ),
		), array( 'id' => $existing->id ) );

		ET_AuditLog::log( sprintf( '%s سجّل انصرافه الساعة %s', $employee->name, wp_date( 'H:i', $now_ts ) ) );

		return self::get_record( $employee_id, $today );
	}

	/** الإجازة السنوية/المرضية/الاستئذان المعتمدة لهذا التاريخ — قراءة فقط من النظام الموجود مسبقًا */
	public static function leave_covering( $employee_id, $date ) {
		global $wpdb;
		$table = ET_Database::table( 'leave_requests' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE employee_id=%d AND status='approved' AND start_date<=%s AND end_date>=%s LIMIT 1", $employee_id, $date, $date ) );
	}
	public static function sick_covering( $employee_id, $date ) {
		global $wpdb;
		$table = ET_Database::table( 'sick_leave_requests' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE employee_id=%d AND status='approved' AND start_date<=%s AND end_date>=%s LIMIT 1", $employee_id, $date, $date ) );
	}
	public static function permission_covering( $employee_id, $date ) {
		global $wpdb;
		$table = ET_Database::table( 'permission_requests' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE employee_id=%d AND status='approved' AND perm_date=%s ORDER BY id DESC LIMIT 1", $employee_id, $date ) );
	}

	/**
	 * الحالة الكاملة للتحضير ليوم واحد لموظف واحد: تدمج سجل الحضور الفعلي مع حالات الإجازة/
	 * الاستئذان الموجودة مسبقًا (قراءة فقط) — بلا أي تعديل على رصيد الإجازات مطلقًا.
	 * الأولوية عند التعارض: مرضية ثم سنوية ثم استئذان ثم الحضور الفعلي.
	 */
	public static function day_status( $employee_id, $date ) {
		$record = self::get_record( $employee_id, $date );
		$sick   = self::sick_covering( $employee_id, $date );
		$leave  = self::leave_covering( $employee_id, $date );
		$perm   = self::permission_covering( $employee_id, $date );

		if ( $sick ) {
			$status = 'sick';
		} elseif ( $leave ) {
			$status = 'leave';
		} elseif ( $perm ) {
			$status = 'permission';
		} elseif ( $record && ! empty( $record->check_in_time ) ) {
			$status = $record->status;
		} elseif ( ! self::is_working_day( $date ) ) {
			$status = 'off';
		} else {
			$today = current_time( 'Y-m-d' );
			if ( $date < $today ) {
				$status = 'absent';
			} elseif ( $date === $today ) {
				$settings = self::settings();
				$schedule = self::schedule_for_employee( ET_Employees::get( $employee_id ) );
				$cutoff   = ET_Time::ts( $date . ' ' . $schedule['end'] ) + ( $settings['grace_minutes'] * MINUTE_IN_SECONDS );
				$status   = ( time() > $cutoff ) ? 'absent' : 'not_yet';
			} else {
				$status = 'not_yet';
			}
		}

		return array(
			'employee_id'    => (int) $employee_id,
			'date'           => $date,
			'status'         => $status,
			'status_label'   => self::status_label( $status ),
			'status_color'   => self::status_color( $status ),
			'check_in'       => $record ? $record->check_in_time : null,
			'check_out'      => $record ? $record->check_out_time : null,
			'late_minutes'   => $record ? (int) $record->late_minutes : 0,
			'worked_minutes' => ( $record && $record->worked_minutes !== null ) ? (int) $record->worked_minutes : null,
			'worked_label'   => ( $record && $record->worked_minutes !== null ) ? self::format_worked( (int) $record->worked_minutes ) : '',
			'is_manual'      => $record ? (bool) $record->is_manual : false,
			'permission'     => $perm  ? array( 'time_out' => $perm->time_out, 'time_return' => $perm->time_return, 'reason' => $perm->reason ) : null,
			'leave'          => $leave ? array( 'start_date' => $leave->start_date, 'end_date' => $leave->end_date ) : null,
			'sick'           => $sick  ? array( 'start_date' => $sick->start_date, 'end_date' => $sick->end_date ) : null,
		);
	}

	public static function status_label( $status ) {
		$labels = array(
			'present'    => 'حاضر', 'late' => 'متأخر', 'absent' => 'غائب',
			'leave'      => 'إجازة', 'sick' => 'إجازة مرضية', 'permission' => 'استئذان',
			'not_yet'    => 'لم يسجل بعد', 'off' => 'عطلة',
		);
		return $labels[ $status ] ?? $status;
	}

	public static function status_color( $status ) {
		$colors = array(
			'present' => 'green', 'late' => 'orange', 'absent' => 'red',
			'leave'   => 'blue', 'sick' => 'purple', 'permission' => 'gold',
			'not_yet' => 'gray', 'off' => 'gray',
		);
		return $colors[ $status ] ?? 'gray';
	}

	public static function format_worked( $minutes ) {
		if ( $minutes === null ) return '';
		$h = intdiv( (int) $minutes, 60 ); $m = ( (int) $minutes ) % 60;
		if ( $h > 0 && $m > 0 ) return sprintf( '%d ساعة و%d دقيقة', $h, $m );
		if ( $h > 0 ) return sprintf( '%d ساعة', $h );
		return sprintf( '%d دقيقة', $m );
	}

	/** تحضير كل الموظفين النشطين ليوم مُحدد + إحصائيات اليوم — للوحة الإدارة */
	public static function day_overview( $date ) {
		$employees = ET_Employees::active_for_picker();
		$rows  = array();
		$stats = array( 'present' => 0, 'late' => 0, 'absent' => 0, 'leave' => 0, 'sick' => 0, 'permission' => 0, 'not_yet' => 0, 'off' => 0 );
		foreach ( $employees as $e ) {
			$s = self::day_status( $e->id, $date );
			$s['employee_name']   = $e->name;
			$s['department_name'] = $e->department_name;
			if ( isset( $stats[ $s['status'] ] ) ) $stats[ $s['status'] ]++;
			$rows[] = $s;
		}
		return array( 'date' => $date, 'items' => $rows, 'stats' => $stats );
	}

	/** سجل حضور موظف واحد عبر نطاق تاريخ (للإدارة أو للموظف نفسه) */
	public static function employee_history( $employee_id, $date_from, $date_to ) {
		$start = strtotime( $date_from ); $end = strtotime( $date_to );
		if ( ! $start || ! $end || $end < $start ) {
			return new WP_Error( 'et_att_bad_range', __( 'نطاق التواريخ غير صحيح.'), array( 'status' => 400 ) );
		}
		$rows = array(); $cursor = $start; $guard = 0;
		while ( $cursor <= $end && $guard < 400 ) {
			$rows[] = self::day_status( $employee_id, date( 'Y-m-d', $cursor ) );
			$cursor = strtotime( '+1 day', $cursor );
			$guard++;
		}
		return array_reverse( $rows );
	}

	/** تعديل يدوي من الإدارة — يُسجَّل دائمًا في سجل تدقيق مستقل، والسجل الأصلي لا يُحذف، ولا يمسّ رصيد الإجازات إطلاقًا */
	public static function manual_edit( $employee_id, $date, $new_status, $check_in, $check_out, $reason, $notes, $admin_label, $admin_user_id ) {
		global $wpdb;
		$employee = ET_Employees::get( $employee_id );
		if ( ! $employee ) return new WP_Error( 'et_not_found', __( 'الموظف غير موجود.'), array( 'status' => 404 ) );

		$reason = trim( (string) $reason );
		if ( $reason === '' ) {
			return new WP_Error( 'et_att_reason_required', __( 'سبب التعديل إجباري — لا يمكن حفظ التعديل بدونه.'), array( 'status' => 400 ) );
		}
		if ( ! in_array( $new_status, array( 'present', 'late', 'absent' ), true ) ) {
			return new WP_Error( 'et_att_bad_status', __( 'حالة غير صحيحة — التعديل اليدوي يقتصر على حاضر/متأخر/غائب (الإجازات والاستئذانات تُدار من نظامها الخاص فقط).'), array( 'status' => 400 ) );
		}

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $date ) || ! strtotime( $date ) ) {
			return new WP_Error( 'et_att_bad_date', __( 'التاريخ غير صحيح.'), array( 'status' => 400 ) );
		}
		if ( $new_status !== 'absent' ) {
			$check_in  = $check_in ? et_normalize_hhmm( $check_in ) : '';
			$check_out = $check_out ? et_normalize_hhmm( $check_out ) : '';
			if ( $check_in && $check_out && $check_out <= $check_in ) {
				return new WP_Error( 'et_att_bad_range', __( 'وقت الانصراف يجب أن يكون بعد وقت الحضور.'), array( 'status' => 400 ) );
			}
		}

		$before   = self::day_status( $employee_id, $date );
		$existing = self::get_record( $employee_id, $date );

		$check_in_dt  = ( $new_status !== 'absent' && $check_in )  ? date( 'Y-m-d H:i:s', strtotime( $date . ' ' . $check_in ) )  : null;
		$check_out_dt = ( $new_status !== 'absent' && $check_out ) ? date( 'Y-m-d H:i:s', strtotime( $date . ' ' . $check_out ) ) : null;

		$late_minutes = 0;
		if ( $new_status === 'late' && $check_in_dt ) {
			$schedule = self::schedule_for_employee( $employee );
			$start_ts = strtotime( $date . ' ' . $schedule['start'] );
			$late_minutes = max( 0, (int) round( ( strtotime( $check_in_dt ) - $start_ts ) / 60 ) );
		}
		$worked_minutes = null;
		if ( $check_in_dt && $check_out_dt ) {
			$worked_minutes = max( 0, (int) round( ( strtotime( $check_out_dt ) - strtotime( $check_in_dt ) ) / 60 ) );
		}

		$fields = array(
			'employee_id'     => (int) $employee_id,
			'attendance_date' => $date,
			'check_in_time'   => $check_in_dt,
			'check_out_time'  => $check_out_dt,
			'status'          => $new_status,
			'late_minutes'    => $late_minutes,
			'worked_minutes'  => $worked_minutes,
			'source'          => 'manual',
			'is_manual'       => 1,
			'updated_at'      => current_time( 'mysql' ),
		);

		if ( $existing ) {
			$wpdb->update( self::table(), $fields, array( 'id' => $existing->id ) );
		} else {
			$fields['created_at'] = current_time( 'mysql' );
			$wpdb->insert( self::table(), $fields );
		}

		// السجل الأصلي لا يُحذف أبدًا — يبقى محفوظًا كاملاً ضمن سجل التدقيق (الحالة/الأوقات السابقة إلى جانب الجديدة).
		$wpdb->insert( self::audit_table(), array(
			'employee_id'     => (int) $employee_id,
			'attendance_date' => $date,
			'old_status'      => $before['status'],
			'new_status'      => $new_status,
			'old_check_in'    => $before['check_in'],
			'new_check_in'    => $check_in_dt,
			'old_check_out'   => $before['check_out'],
			'new_check_out'   => $check_out_dt,
			'reason'          => ET_Security::sanitize_textarea( $reason ),
			'notes'           => ET_Security::sanitize_textarea( $notes ),
			'admin_label'     => sanitize_text_field( $admin_label ),
			'admin_user_id'   => (int) $admin_user_id,
			'created_at'      => current_time( 'mysql' ),
		) );

		ET_AuditLog::log( sprintf(
			'تم تعديل الحضور يدويًا بواسطة %s — الموظف: %s، التاريخ: %s، %s ← %s',
			$admin_label, $employee->name, $date, self::status_label( $before['status'] ), self::status_label( $new_status )
		) );

		return self::day_status( $employee_id, $date );
	}

	public static function audit_logs( $employee_id = 0, $date_from = '', $date_to = '' ) {
		global $wpdb;
		$table = self::audit_table();
		$emp   = ET_Database::table( 'employees' );
		$where = array( '1=1' ); $params = array();
		if ( $employee_id > 0 ) { $where[] = 'a.employee_id=%d';       $params[] = $employee_id; }
		if ( $date_from !== '' ) { $where[] = 'a.attendance_date >= %s'; $params[] = $date_from; }
		if ( $date_to !== '' )   { $where[] = 'a.attendance_date <= %s'; $params[] = $date_to; }
		$sql = "SELECT a.*, e.name AS employee_name FROM {$table} a LEFT JOIN {$emp} e ON e.id=a.employee_id WHERE " . implode( ' AND ', $where ) . ' ORDER BY a.id DESC LIMIT 300';
		return empty( $params ) ? $wpdb->get_results( $sql ) : $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	/** تقرير مُجمّع لكل موظف ضمن نطاق تاريخ — قراءة فقط، لا يعدّل أي بيانات */
	public static function report_rows( $date_from, $date_to, $employee_id = 0 ) {
		$employees = $employee_id > 0 ? array_filter( array( ET_Employees::get( $employee_id ) ) ) : ET_Employees::active_for_picker();

		$rows = array();
		foreach ( $employees as $e ) {
			$history = self::employee_history( $e->id, $date_from, $date_to );
			if ( is_wp_error( $history ) ) continue;
			$agg = array( 'present' => 0, 'late' => 0, 'absent' => 0, 'leave' => 0, 'sick' => 0, 'permission' => 0, 'worked_minutes' => 0, 'late_minutes' => 0 );
			foreach ( $history as $d ) {
				if ( isset( $agg[ $d['status'] ] ) ) $agg[ $d['status'] ]++;
				// يوم غطّته إجازة/مرضية/استئذان لاحقًا لا تُحتسب فيه دقائق تأخير
				if ( in_array( $d['status'], array( 'present', 'late' ), true ) ) {
					$agg['worked_minutes'] += (int) ( $d['worked_minutes'] ?? 0 );
					$agg['late_minutes']   += (int) ( $d['late_minutes'] ?? 0 );
				}
			}
			$rows[] = array_merge( array(
				'employee_id'     => $e->id,
				'employee_name'   => $e->name,
				'department_name' => $e->department_name,
			), $agg );
		}
		return $rows;
	}
}
