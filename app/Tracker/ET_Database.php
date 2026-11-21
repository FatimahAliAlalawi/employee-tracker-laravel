<?php

/*
 * ET_Database — أسماء الجداول + القيم الافتراضية.
 * إنشاء الجداول صار عبر migrations في database/migrations (php artisan migrate) بدل dbDelta.
 */
class ET_Database {

	public static function table( $name ) {
		return config( 'tracker.table_prefix', '' ) . 'et_' . $name;
	}

	/** يُستدعى من DatabaseSeeder / أمر tracker:install — يعادل تفعيل البلجن */
	public static function activate() {
		self::seed_defaults();
		update_option( 'et_db_version', ET_DB_VERSION );
		if ( ! get_option( 'et_activated_at' ) ) {
			update_option( 'et_activated_at', current_time( 'mysql' ) );
		}
		if ( ! get_option( 'et_token_secret' ) ) {
			update_option( 'et_token_secret', wp_generate_password( 64, true, true ) );
		}
		ET_Files::ensure_private_dir();
	}

	/** يعمل مرة واحدة: إعادة حساب دقائق التأخير لسجلات الحضور (تصحيح خلل فرق التوقيت من 2.9.1) */
	public static function maybe_upgrade() {
		if ( ! get_option( 'et_att_tz_repair_done' ) ) {
			update_option( 'et_att_tz_repair_done', current_time( 'mysql' ) );
			$fixed = ET_Attendance::repair_stored_minutes();
			if ( $fixed > 0 ) {
				ET_AuditLog::log( sprintf( 'تصحيح تلقائي: أُعيد حساب التأخير/ساعات العمل لـ %d سجل حضور تأثرت بخلل فرق التوقيت.', $fixed ) );
			}
		}
	}

	/** الإعدادات الافتراضية فقط — لا أقسام ولا موظفين جاهزين؛ تضيفها الإدارة من لوحة التحكم */
	public static function seed_defaults() {
		$current = get_option( 'et_settings', array() );
		if ( empty( $current ) ) {
			update_option( 'et_settings', ET_Settings::defaults() );
		}
	}
}
