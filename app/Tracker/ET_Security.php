<?php

/*
 * منقول من بلجن ووردبريس employee-tracker 2.9.1 — منطق العمل كما هو،
 * يعمل فوق Laravel عبر app/Support/helpers.php (لا اعتماد على ووردبريس).
 */

class ET_Security {

	/** أقصى مدة لجلسة الموظف بالساعات مهما كانت قيمة الإعداد */
	const MAX_EMPLOYEE_SESSION_HOURS = 72;
	/** أقل طول لكلمة مرور حساب المدير */
	const MIN_ADMIN_CODE_LENGTH = 8;
	/** محاولات الدخول الخاطئة المسموحة قبل القفل المؤقت */
	const EMPLOYEE_MAX_FAILS = 5;
	const ADMIN_MAX_FAILS    = 10;
	const LOCKOUT_SECONDS    = 900; // 15 دقيقة

	/** اسما الكوكيز (HttpOnly) — تُستخدم فقط لفتح المرفقات المحمية من روابط <a> العادية */
	const EMPLOYEE_COOKIE = 'et_emp_session';
	const ADMIN_COOKIE    = 'et_wp_admin_pin';

	/** مدة جلسة الموظف بالساعات (من الإعدادات، بحد أدنى 1 وحد أقصى MAX_EMPLOYEE_SESSION_HOURS) */
	public static function employee_session_hours() {
		$settings = ET_Settings::get();
		$hours    = isset( $settings['employee_session_hours'] ) ? (int) $settings['employee_session_hours'] : 12;
		return max( 1, min( self::MAX_EMPLOYEE_SESSION_HOURS, $hours ) );
	}

	/* ---------------------------------------------------------------
	   رموز الدخول: تُحفظ مشفّرة (wp_hash_password) ولا تُحفظ كنص صريح أبدًا
	--------------------------------------------------------------- */
	public static function hash_code( $code ) {
		return wp_hash_password( (string) $code );
	}

	public static function is_hashed( $value ) {
		$value = (string) $value;
		return strpos( $value, '$P$' ) === 0 || strpos( $value, '$wp$' ) === 0 || strpos( $value, '$2y$' ) === 0 || strpos( $value, '$argon2' ) === 0;
	}

	/** يقارن الرمز المُدخل بالمحفوظ (مشفّر، أو نص صريح قديم لم تتم ترقيته بعد) */
	public static function code_matches( $entered, $stored ) {
		$entered = (string) $entered;
		$stored  = (string) $stored;
		if ( $entered === '' || $stored === '' ) return false;
		if ( self::is_hashed( $stored ) ) {
			return wp_check_password( $entered, $stored );
		}
		return hash_equals( $stored, $entered );
	}

	/** بصمة قصيرة للرمز المحفوظ — تُضمَّن في التوكن حتى تُلغى كل الجلسات القديمة فور تغيير الرمز */
	private static function code_fingerprint( $stored ) {
		return substr( hash_hmac( 'sha256', (string) $stored, self::get_secret() ), 0, 16 );
	}

	/* ---------------------------------------------------------------
	   قفل مؤقت بعد محاولات دخول خاطئة (لكل موظف / لرمز الإدارة) — مستقل عن الـ IP،
	   فلا يفيد المهاجم توزيع المحاولات على عناوين IP كثيرة.
	--------------------------------------------------------------- */
	public static function is_locked( $key, $max ) {
		return self::counter_get( 'et_lock_' . $key ) >= $max;
	}

	public static function register_failure( $key ) {
		self::counter_incr( 'et_lock_' . $key, self::LOCKOUT_SECONDS );
	}

	public static function clear_failures( $key ) {
		self::counter_delete( 'et_lock_' . $key );
	}

	/** يقرأ التوكن من الهيدر X-ET-Token (المفضّل — لا يظهر بسجلات السيرفر)، ثم من جسم الطلب كاحتياط */
	public static function request_token( $request ) {
		$header = $request->get_header( 'x_et_token' );
		if ( is_string( $header ) && $header !== '' ) return $header;
		$param = $request->get_param( 'token' );
		return is_string( $param ) ? $param : '';
	}

	/** يضبط كوكي HttpOnly (لفتح المرفقات المحمية فقط — كل طلبات الـ API تبقى بالهيدر) */
	public static function set_cookie( $name, $value, $expires ) {
		if ( headers_sent() ) return;
		$path   = defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/';
		$domain = defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ? COOKIE_DOMAIN : '';
		setcookie( $name, $value, array(
			'expires'  => $expires,
			'path'     => $path,
			'domain'   => $domain,
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		) );
	}

	public static function clear_cookie( $name ) {
		self::set_cookie( $name, '', time() - YEAR_IN_SECONDS );
	}

	/** عنوان IP للزائر. خلف Cloudflare/بروكسي: عرّف ET_TRUSTED_PROXY_HEADER في wp-config.php
	 *  (مثال: define( 'ET_TRUSTED_PROXY_HEADER', 'HTTP_CF_CONNECTING_IP' );) — لا نثق بأي هيدر افتراضيًا
	 *  لأنه قابل للتزوير إن لم يكن الموقع خلف ذلك البروكسي فعلًا. */
	public static function client_ip() {
		if ( defined( 'ET_TRUSTED_PROXY_HEADER' ) && ET_TRUSTED_PROXY_HEADER && ! empty( $_SERVER[ ET_TRUSTED_PROXY_HEADER ] ) ) {
			$ip = trim( explode( ',', (string) wp_unslash( $_SERVER[ ET_TRUSTED_PROXY_HEADER ] ) )[0] );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) return $ip;
		}
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
	}

	/** إصدار Token موقّع (HMAC) بدون حالة (Stateless) بعد اختيار الموظف اسمه */
	public static function issue_employee_token( $employee_id ) {
		$secret    = self::get_secret();
		$expires   = time() + ( self::employee_session_hours() * HOUR_IN_SECONDS );
		$employee  = ET_Employees::get( (int) $employee_id );
		$cv        = self::code_fingerprint( $employee ? $employee->access_code : '' );

		$payload      = wp_json_encode( array( 'eid' => (int) $employee_id, 'exp' => $expires, 'cv' => $cv ) );
		$payload_b64  = self::b64url_encode( $payload );
		$sig          = hash_hmac( 'sha256', $payload_b64, $secret );

		return $payload_b64 . '.' . $sig;
	}

	/** التحقق من Token ويعيد employee_id عند النجاح أو WP_Error عند الفشل */
	public static function verify_employee_token( $token ) {
		if ( empty( $token ) || strpos( $token, '.' ) === false ) {
			return new WP_Error( 'et_invalid_token', __( 'جلسة غير صالحة، الرجاء اختيار اسمك مجددًا.'), array( 'status' => 401 ) );
		}

		list( $payload_b64, $sig ) = explode( '.', $token, 2 );
		$secret       = self::get_secret();
		$expected_sig = hash_hmac( 'sha256', $payload_b64, $secret );

		if ( ! hash_equals( $expected_sig, $sig ) ) {
			return new WP_Error( 'et_invalid_token', __( 'جلسة غير صالحة، الرجاء اختيار اسمك مجددًا.'), array( 'status' => 401 ) );
		}

		$payload = json_decode( self::b64url_decode( $payload_b64 ), true );
		if ( empty( $payload['eid'] ) || empty( $payload['exp'] ) ) {
			return new WP_Error( 'et_invalid_token', __( 'جلسة غير صالحة.'), array( 'status' => 401 ) );
		}
		if ( time() > (int) $payload['exp'] ) {
			return new WP_Error( 'et_token_expired', __( 'انتهت صلاحية الجلسة، الرجاء اختيار اسمك مجددًا.'), array( 'status' => 401 ) );
		}

		$employee = ET_Employees::get( (int) $payload['eid'] );
		if ( ! $employee || $employee->status !== 'active' ) {
			return new WP_Error( 'et_employee_inactive', __( 'الحساب غير متاح حاليًا.'), array( 'status' => 403 ) );
		}

		// تغيّر رمز الموظف بعد إصدار هذا التوكن؟ → الجلسة ملغاة
		if ( empty( $payload['cv'] ) || ! hash_equals( self::code_fingerprint( $employee->access_code ), (string) $payload['cv'] ) ) {
			return new WP_Error( 'et_invalid_token', __( 'انتهت الجلسة بسبب تغيير رمز الدخول، الرجاء الدخول مجددًا.'), array( 'status' => 401 ) );
		}

		return (int) $payload['eid'];
	}

	private static function get_secret() {
		$secret = get_option( 'et_token_secret' );
		if ( ! $secret ) {
			$secret = wp_generate_password( 64, true, true );
			update_option( 'et_token_secret', $secret );
		}
		return $secret;
	}

	/**
	 * رمز سرّي مستقل (غير et_token_secret) يُستخدم فقط لحماية رابط تشغيل كرون التذكير
	 * الخارجي. لا يحتوي رموزًا خاصة حتى يبقى آمنًا للنسخ داخل رابط (URL) مباشرة.
	 */
	public static function get_cron_secret() {
		$secret = get_option( 'et_cron_secret' );
		if ( ! $secret ) {
			$secret = wp_generate_password( 40, false );
			update_option( 'et_cron_secret', $secret );
		}
		return $secret;
	}

	/** يولّد رمزًا سريًا جديدًا لرابط الكرون الخارجي (لو تسرّب الرابط القديم مثلاً) */
	public static function rotate_cron_secret() {
		$secret = wp_generate_password( 40, false );
		update_option( 'et_cron_secret', $secret );
		return $secret;
	}

	/* ---------------------------------------------------------------
	   حسابات الإدارة: كل مدير يدخل ببريده وكلمة مروره (جدول users)، في تبويب الإدارة
	   بصفحة الموظفين وفي لوحة /admin على حد سواء.
	--------------------------------------------------------------- */

	/** يتحقق من بريد وكلمة مرور مدير، ويعيد حسابه أو null */
	public static function find_admin_by_credentials( $email, $password ) {
		$email = strtolower( trim( (string) $email ) );
		if ( $email === '' || (string) $password === '' ) return null;
		$user = \App\Models\User::whereRaw( 'LOWER(email) = ?', array( $email ) )->first();
		if ( ! $user || ! \Illuminate\Support\Facades\Hash::check( (string) $password, $user->password ) ) return null;
		return $user;
	}

	/**
	 * جلسة تبويب "الإدارة" بصفحة الموظفين لمدير محدد. التوكن يحمل رقم حسابه وبصمة كلمة مروره،
	 * فتغيير كلمة المرور أو حذف الحساب يُنهي جلساته المفتوحة فورًا.
	 */
	public static function issue_admin_session_token( $user_id ) {
		$secret  = self::get_secret();
		$expires = time() + ( 12 * HOUR_IN_SECONDS );
		$user    = \App\Models\User::find( (int) $user_id );

		$payload     = wp_json_encode( array( 'adm' => 1, 'uid' => (int) $user_id, 'exp' => $expires, 'cv' => self::code_fingerprint( $user ? $user->password : '' ) ) );
		$payload_b64 = self::b64url_encode( $payload );
		$sig         = hash_hmac( 'sha256', $payload_b64, $secret );

		return $payload_b64 . '.' . $sig;
	}

	/** التحقق من توكن جلسة الإدارة، يعيد true عند النجاح أو WP_Error عند الفشل */
	public static function verify_admin_session_token( $token ) {
		if ( empty( $token ) || strpos( $token, '.' ) === false ) {
			return new WP_Error( 'et_invalid_admin_token', __( 'جلسة الإدارة غير صالحة، سجّل الدخول مجددًا.'), array( 'status' => 401 ) );
		}

		list( $payload_b64, $sig ) = explode( '.', $token, 2 );
		$secret       = self::get_secret();
		$expected_sig = hash_hmac( 'sha256', $payload_b64, $secret );

		if ( ! hash_equals( $expected_sig, $sig ) ) {
			return new WP_Error( 'et_invalid_admin_token', __( 'جلسة الإدارة غير صالحة، سجّل الدخول مجددًا.'), array( 'status' => 401 ) );
		}

		$payload = json_decode( self::b64url_decode( $payload_b64 ), true );
		if ( empty( $payload['adm'] ) || empty( $payload['exp'] ) ) {
			return new WP_Error( 'et_invalid_admin_token', __( 'جلسة الإدارة غير صالحة.'), array( 'status' => 401 ) );
		}
		if ( time() > (int) $payload['exp'] ) {
			return new WP_Error( 'et_admin_token_expired', __( 'انتهت صلاحية جلسة الإدارة، سجّل الدخول مجددًا.'), array( 'status' => 401 ) );
		}

		// الحساب حُذف أو تغيّرت كلمة مروره بعد إصدار هذا التوكن؟ → الجلسة ملغاة
		$user = empty( $payload['uid'] ) ? null : \App\Models\User::find( (int) $payload['uid'] );
		if ( ! $user || empty( $payload['cv'] ) || ! hash_equals( self::code_fingerprint( $user->password ), (string) $payload['cv'] ) ) {
			return new WP_Error( 'et_invalid_admin_token', __( 'انتهت جلسة الإدارة، سجّل الدخول مجددًا.'), array( 'status' => 401 ) );
		}

		return true;
	}

	/** اسم المدير صاحب توكن جلسة الإدارة (لسجل التدقيق)، أو '' */
	public static function admin_name_from_token( $token ) {
		if ( is_wp_error( self::verify_admin_session_token( $token ) ) ) return '';
		list( $payload_b64 ) = explode( '.', (string) $token, 2 );
		$payload = json_decode( self::b64url_decode( $payload_b64 ), true );
		$user    = \App\Models\User::find( (int) ( $payload['uid'] ?? 0 ) );
		return $user ? (string) $user->name : '';
	}

	private static function b64url_encode( $data ) { return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' ); }
	private static function b64url_decode( $data ) { return base64_decode( strtr( $data, '-_', '+/' ) ); }

	public static function rate_limit_ok( $key, $max_attempts = 20, $window_seconds = 60 ) {
		$counter_key = 'et_rl_' . md5( $key );
		if ( self::counter_get( $counter_key ) >= $max_attempts ) return false;
		self::counter_incr( $counter_key, $window_seconds );
		return true;
	}

	/* ---------------------------------------------------------------
	   عدّادات مؤقتة: تستخدم Object Cache (Redis/Memcached) إن وُجد — بلا أي كتابة بقاعدة البيانات —
	   وإلا Transients (تُحذف المنتهية منها تلقائيًا بكرون ووردبريس اليومي).
	--------------------------------------------------------------- */
	private static function counter_get( $key ) {
		if ( wp_using_ext_object_cache() ) {
			return (int) wp_cache_get( $key, 'employee_tracker' );
		}
		return (int) get_transient( $key );
	}

	private static function counter_incr( $key, $ttl ) {
		if ( wp_using_ext_object_cache() ) {
			if ( false === wp_cache_add( $key, 1, 'employee_tracker', $ttl ) ) {
				wp_cache_incr( $key, 1, 'employee_tracker' );
			}
			return;
		}
		set_transient( $key, (int) get_transient( $key ) + 1, $ttl );
	}

	private static function counter_delete( $key ) {
		if ( wp_using_ext_object_cache() ) {
			wp_cache_delete( $key, 'employee_tracker' );
			return;
		}
		delete_transient( $key );
	}

	/** يمنع حقن المعادلات بملفات CSV (Excel ينفّذ أي خلية تبدأ بـ = + - @ أو Tab/CR كمعادلة) */
	public static function csv_safe( $value ) {
		if ( ! is_string( $value ) || $value === '' ) return $value;
		if ( in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
			return "'" . $value;
		}
		return $value;
	}


	public static function current_user_can_manage() {
		return current_user_can( 'manage_options' );
	}

	public static function sanitize_textarea( $value ) {
		return sanitize_textarea_field( wp_unslash( (string) $value ) );
	}

	public static function validate_upload( $file ) {
		$allowed_mimes = array(
			'jpg|jpeg' => 'image/jpeg',
			'png'      => 'image/png',
			'gif'      => 'image/gif',
			'webp'     => 'image/webp',
			'pdf'      => 'application/pdf',
			'doc'      => 'application/msword',
			'docx'     => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'xls'      => 'application/vnd.ms-excel',
			'xlsx'     => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		);

		$filetype = wp_check_filetype( $file['name'], $allowed_mimes );
		if ( ! $filetype['ext'] || ! $filetype['type'] ) {
			return new WP_Error( 'et_invalid_file_type', __( 'نوع الملف غير مسموح به.'), array( 'status' => 400 ) );
		}

		$settings  = ET_Settings::get();
		$max_size  = isset( $settings['max_upload_size_mb'] ) ? (int) $settings['max_upload_size_mb'] : 10;
		$max_bytes = $max_size * MB_IN_BYTES;
		if ( $file['size'] > $max_bytes ) {
			/* translators: %d: أقصى حجم بالميغابايت */
			return new WP_Error( 'et_file_too_large', sprintf( __( 'حجم الملف يتجاوز الحد المسموح (%d ميجابايت).'), $max_size ), array( 'status' => 400 ) );
		}
		return true;
	}
}
