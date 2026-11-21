<?php

/*
 * ET_Background — تشغيل المهام البطيئة (بريد، واتساب) بعد إرسال الرد للمستخدم.
 * في Laravel: app()->terminating() يُنفَّذ بعد إرسال الرد للمتصفح (fastcgi_finish_request)،
 * فلا ينتظر الموظف/المدير الاتصالات الخارجية. المعالجات مسجّلة في AppServiceProvider.
 */
class ET_Background {

	private static $queue      = array();
	private static $registered = false;

	public static function queue( $hook, $args = array() ) {
		self::$queue[] = array( $hook, $args );
		if ( self::$registered ) return;
		self::$registered = true;

		if ( app()->runningInConsole() ) {
			// من الأوامر/المجدول: لا يوجد متصفح ينتظر — نشغّلها في نهاية التنفيذ
			register_shutdown_function( array( __CLASS__, 'run_after_response' ) );
		} else {
			app()->terminating( array( __CLASS__, 'run_after_response' ) );
		}
	}

	public static function run_after_response() {
		if ( empty( self::$queue ) ) return;
		ignore_user_abort( true );
		$jobs             = self::$queue;
		self::$queue      = array();
		self::$registered = false;
		foreach ( $jobs as $job ) {
			try {
				do_action( $job[0], $job[1] );
			} catch ( \Throwable $e ) {
				report( $e );
			}
		}
	}
}
