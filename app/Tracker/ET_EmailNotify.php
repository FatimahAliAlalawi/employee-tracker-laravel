<?php

/*
 * منقول من بلجن ووردبريس employee-tracker 2.9.1 — منطق العمل كما هو،
 * يعمل فوق Laravel عبر app/Support/helpers.php (لا اعتماد على ووردبريس).
 */

class ET_EmailNotify {

	/**
	 * يرسل بريدًا إلى عنوان الإدارة المُعرَّف في الإعدادات عبر wp_mail.
	 *
	 * @param string      $message         نص الرسالة (سيُستخدم كجسم البريد).
	 * @param bool        $diagnostic      إذا true: يرجع مصفوفة تشخيصية (نجاح/فشل + سبب) بدل null.
	 *                                     يُستخدم فقط من زر "إرسال بريد تجريبي" في الإدارة.
	 * @param string|null $subject_override عنوان مخصّص للبريد يحل محل عنوان الإعدادات الثابت — يُستخدم
	 *                                     لجعل بريد "مهمة منجزة بانتظار الاعتماد" يحمل اسم المهمة نفسها
	 *                                     بعنوان البريد مباشرة، حتى يتميّز فورًا في صندوق وارد الإدارة عن
	 *                                     بريد "تحديث يومي" العادي (كلاهما كان يصل بنفس العنوان الثابت
	 *                                     تمامًا من قبل، فيصعب تمييز أيهما يحتاج اعتمادًا فعليًا بنظرة سريعة).
	 * @return array|null
	 */
	public static function notify_admin( $message, $diagnostic = false, $subject_override = null ) {
		$settings = ET_Settings::get();

		if ( empty( $settings['email_notify_enabled'] ) ) {
			return $diagnostic ? array( 'ok' => false, 'error' => 'إشعار البريد غير مفعّل في الإعدادات.' ) : null;
		}

		$to = trim( $settings['email_notify_address'] ?? '' );

		if ( empty( $to ) || ! is_email( $to ) ) {
			return $diagnostic ? array( 'ok' => false, 'error' => 'البريد الإلكتروني غير مُدخل أو غير صحيح.' ) : null;
		}

		if ( $subject_override !== null && trim( $subject_override ) !== '' ) {
			$subject = trim( $subject_override );
		} else {
			$subject = trim( $settings['email_notify_subject'] ?? '' );
			if ( empty( $subject ) ) $subject = 'إشعار تحديث موظف جديد';
		}

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		// نلتقط أخطاء wp_mail عبر hook مؤقت لأن wp_mail نفسها ترجع true/false فقط بدون تفاصيل السبب
		$last_error = null;
		$catch_error = function ( $wp_error ) use ( &$last_error ) {
			$last_error = $wp_error;
		};
		add_action( 'wp_mail_failed', $catch_error );

		$sent = wp_mail( $to, $subject, $message, $headers );

		remove_action( 'wp_mail_failed', $catch_error );

		if ( ! $diagnostic ) return null;

		if ( $sent ) {
			return array( 'ok' => true, 'to' => $to );
		}

		$reason = $last_error instanceof WP_Error
			? $last_error->get_error_message()
			: 'تعذّر الإرسال بدون تفاصيل — تحقق من إعدادات البريد (MAIL_*) في ملف .env.';

		error_log( '[ET_EmailNotify] فشل الإرسال إلى ' . $to . ': ' . $reason );

		return array( 'ok' => false, 'error' => $reason );
	}

	/**
	 * إرسال بريد عام لأي عنوان (يُستخدم لتذكير الموظفين، بعيدًا عن بريد الإدارة الثابت).
	 *
	 * @param bool $diagnostic إذا true: يرجع مصفوفة تشخيصية بدل true/false فقط.
	 * @return bool|array
	 */
	public static function send( $to, $subject, $message, $diagnostic = false ) {
		if ( empty( $to ) || ! is_email( $to ) ) {
			return $diagnostic ? array( 'ok' => false, 'error' => 'البريد الإلكتروني غير صحيح أو فارغ: "' . $to . '"' ) : false;
		}

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		$last_error = null;
		$catch_error = function ( $wp_error ) use ( &$last_error ) {
			$last_error = $wp_error;
		};
		add_action( 'wp_mail_failed', $catch_error );

		$sent = wp_mail( $to, $subject, $message, $headers );

		remove_action( 'wp_mail_failed', $catch_error );

		if ( ! $diagnostic ) return (bool) $sent;

		if ( $sent ) return array( 'ok' => true, 'to' => $to );

		$reason = $last_error instanceof WP_Error
			? $last_error->get_error_message()
			: 'تعذّر الإرسال بدون تفاصيل — تحقق من إعدادات البريد (MAIL_*) في ملف .env.';

		error_log( '[ET_EmailNotify] فشل تذكير الموظف على ' . $to . ': ' . $reason );

		return array( 'ok' => false, 'error' => $reason );
	}
}
