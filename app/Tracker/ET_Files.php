<?php

/*
 * ET_Files — مرفقات محمية: تُحفظ داخل storage/app/private (خارج public، لا وصول مباشر إطلاقًا)
 * باسم عشوائي، ولا تُفتح إلا عبر رابط ?et_file=... يتحقق من الصلاحية قبل إرسال الملف:
 * الإدارة (حساب Laravel أو جلسة رمز الإدارة)، أو الموظف صاحب الملف / عضو المهمة المرتبطة به.
 * صيغة الرابط نفسها مثل نسخة ووردبريس، فتبقى روابط المرفقات المستوردة صالحة على نفس النطاق.
 */
class ET_Files {

	const QUERY_VAR     = 'et_file';
	const LEGACY_PREFIX = 'legacy/';

	public static function base_dir() {
		return rtrim( (string) config( 'tracker.private_storage' ), '/\\' );
	}

	/** مرفقات ووردبريس القديمة (قبل 2.7.0) إن نُقلت إلى هذا المجلد */
	public static function legacy_dir() {
		$dir = rtrim( (string) config( 'tracker.legacy_storage' ), '/\\' );
		return is_dir( $dir ) ? $dir : '';
	}

	public static function ensure_private_dir() {
		$dir = self::base_dir();
		if ( ! is_dir( $dir ) ) wp_mkdir_p( $dir );
	}

	/** رابط الملف المحمي (يمر دائمًا عبر فحص الصلاحية) */
	public static function url_for( $relative ) {
		$segments = array_map( 'rawurlencode', explode( '/', ltrim( (string) $relative, '/' ) ) );
		return home_url( '/' ) . '?' . self::QUERY_VAR . '=' . implode( '/', $segments );
	}

	/**
	 * يرفع ملفًا واحدًا (مصفوفة بصيغة $_FILES) للمجلد الخاص باسم عشوائي.
	 * @return array|WP_Error array( 'url' => رابط محمي, 'name' => الاسم الأصلي المنظّف )
	 */
	public static function store_upload( $file ) {
		$check = ET_Security::validate_upload( $file );
		if ( is_wp_error( $check ) ) return $check;

		if ( ! empty( $file['error'] ) || empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error( 'et_upload_failed', __( 'تعذر رفع الملف، حاول مجددًا.' ), array( 'status' => 400 ) );
		}

		$type   = wp_check_filetype( $file['name'] );
		$subdir = ET_Time::date( 'Y' ) . '/' . ET_Time::date( 'm' );
		$dir    = self::base_dir() . '/' . $subdir;
		if ( ! is_dir( $dir ) ) wp_mkdir_p( $dir );

		$stored = strtolower( wp_generate_password( 32, false, false ) ) . '.' . $type['ext'];
		if ( ! @move_uploaded_file( $file['tmp_name'], $dir . '/' . $stored ) ) {
			return new WP_Error( 'et_upload_failed', __( 'تعذر رفع الملف، حاول مجددًا.' ), array( 'status' => 400 ) );
		}

		return array( 'url' => self::url_for( $subdir . '/' . $stored ), 'name' => sanitize_file_name( $file['name'] ) );
	}

	/**
	 * يخدم الملف المطلوب بعد التحقق من الصلاحية.
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public static function serve( $rel ) {
		$rel = ltrim( str_replace( '\\', '/', (string) $rel ), '/' );
		if ( $rel === '' || strpos( $rel, '..' ) !== false || strpos( $rel, "\0" ) !== false ) {
			return self::deny( 400 );
		}

		$is_legacy = strpos( $rel, self::LEGACY_PREFIX ) === 0;
		$base      = $is_legacy ? self::legacy_dir() : self::base_dir();
		$inner     = $is_legacy ? substr( $rel, strlen( self::LEGACY_PREFIX ) ) : $rel;
		if ( ! $base ) return self::deny( 404 );

		$base_real = realpath( $base );
		$path      = realpath( $base . '/' . $inner );
		if ( ! $base_real || ! $path || strpos( wp_normalize_path( $path ), trailingslashit( wp_normalize_path( $base_real ) ) ) !== 0 || ! is_file( $path ) ) {
			return self::deny( 404 );
		}

		$basename = basename( $path );
		$access   = self::check_access( $basename );
		if ( ! $access['allowed'] ) return self::deny( $access['logged_in'] ? 403 : 401 );

		$type   = wp_check_filetype( $basename );
		$mime   = $type['type'] ? $type['type'] : 'application/octet-stream';
		$inline = in_array( $mime, array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf' ), true );
		$name   = $access['name'] ? $access['name'] : $basename;

		return response()->file( $path, array(
			'Content-Type'           => $mime,
			'X-Content-Type-Options' => 'nosniff',
			'Cache-Control'          => 'private, no-store, max-age=0',
			'Content-Disposition'    => ( $inline ? 'inline' : 'attachment' ) . "; filename*=UTF-8''" . rawurlencode( $name ),
		) );
	}

	/**
	 * من يحق له فتح الملف؟
	 *  - مدير النظام (حساب Laravel) أو جلسة رمز الإدارة → كل الملفات
	 *  - الموظف (كوكي جلسته) → ملفاته هو، ومرفقات المهام التي هو عضو فيها، وطلبات المساعدة المرسلة له/منه
	 */
	private static function check_access( $basename ) {
		$result   = array( 'allowed' => false, 'logged_in' => false, 'name' => '' );
		$is_admin = current_user_can( 'manage_options' );
		if ( ! $is_admin && ! empty( $_COOKIE[ ET_Security::ADMIN_COOKIE ] ) ) {
			$is_admin = ! is_wp_error( ET_Security::verify_admin_session_token( (string) $_COOKIE[ ET_Security::ADMIN_COOKIE ] ) );
		}

		$employee_id = 0;
		if ( ! $is_admin && ! empty( $_COOKIE[ ET_Security::EMPLOYEE_COOKIE ] ) ) {
			$eid = ET_Security::verify_employee_token( (string) $_COOKIE[ ET_Security::EMPLOYEE_COOKIE ] );
			if ( ! is_wp_error( $eid ) ) $employee_id = (int) $eid;
		}
		if ( ! $is_admin && ! $employee_id ) return $result;
		$result['logged_in'] = true;

		global $wpdb;
		$needles = array_unique( array( $basename, rawurlencode( $basename ) ) );
		foreach ( $needles as $needle ) {
			$like = '%' . $wpdb->esc_like( $needle ) . '%';

			$t = ET_Database::table( 'sick_leave_requests' );
			foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT employee_id, attachment_name FROM {$t} WHERE attachment_url LIKE %s", $like ) ) as $r ) {
				if ( $is_admin || (int) $r->employee_id === $employee_id ) return self::grant( $result, $r->attachment_name );
			}

			$t = ET_Database::table( 'help_requests' );
			foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT task_id, from_employee_id, to_employee_id, attachment_name FROM {$t} WHERE attachment_url LIKE %s", $like ) ) as $r ) {
				if ( $is_admin || in_array( $employee_id, array( (int) $r->from_employee_id, (int) $r->to_employee_id ), true ) || ET_TaskMembers::is_member( (int) $r->task_id, $employee_id ) ) {
					return self::grant( $result, $r->attachment_name );
				}
			}

			$t = ET_Database::table( 'task_attachments' );
			foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT task_id, name FROM {$t} WHERE url LIKE %s", $like ) ) as $r ) {
				if ( $is_admin || ET_TaskMembers::is_member( (int) $r->task_id, $employee_id ) ) return self::grant( $result, $r->name );
			}

			$t = ET_Database::table( 'daily_updates' );
			foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT employee_id FROM {$t} WHERE attachments LIKE %s", $like ) ) as $r ) {
				if ( $is_admin || (int) $r->employee_id === $employee_id ) return self::grant( $result, '' );
			}
		}

		// ملف غير مرتبط بأي سجل معروف: للإدارة فقط
		if ( $is_admin ) return self::grant( $result, '' );
		return $result;
	}

	private static function grant( $result, $name ) {
		$result['allowed'] = true;
		$result['name']    = $name ? (string) $name : '';
		return $result;
	}

	private static function deny( $status ) {
		$messages = array(
			400 => 'رابط غير صالح.',
			401 => 'هذا الملف محمي. افتح نظام المتابعة وسجّل دخولك أولًا، ثم افتح الرابط مجددًا من نفس المتصفح.',
			403 => 'ليست لديك صلاحية لفتح هذا الملف.',
			404 => 'الملف غير موجود.',
		);
		$msg  = esc_html( $messages[ $status ] ?? '' );
		$html = '<!doctype html><html lang="ar" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . $msg . '</title>'
			. '<body style="font-family:system-ui,Tahoma,sans-serif;display:flex;align-items:center;justify-content:center;min-height:90vh;margin:0;background:#F5F6F8;">'
			. '<p style="background:#fff;border:1px solid #E4E7EC;border-radius:12px;padding:24px 28px;max-width:420px;text-align:center;line-height:1.8;">' . $msg . '</p></body></html>';
		return response( $html, $status, array( 'Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => 'no-store' ) );
	}
}
