<?php

/*
 * منقول من بلجن ووردبريس employee-tracker 2.9.1 — منطق العمل كما هو،
 * يعمل فوق Laravel عبر app/Support/helpers.php (لا اعتماد على ووردبريس).
 */

class ET_REST_API {

	private static $instance = null;
	const NS = 'employee-tracker/v1';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function hooks() {
		// المسارات تُسجَّل في routes/api.php عبر register_routes() — لا خطافات ووردبريس هنا.
	}


	public function register_routes() {
		register_rest_route( self::NS, '/employees', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_employees' ),
			'permission_callback' => '__return_true',
		) );

		// دخول الموظف بالبريد + كلمة المرور (بدل اختيار الاسم من قائمة عامة)
		register_rest_route( self::NS, '/login', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'select_employee' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( self::NS, '/me/password', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'change_my_password' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( self::NS, '/submit', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'submit_update' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( self::NS, '/me', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_me' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( self::NS, '/weekly-tasks', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_weekly_tasks' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( self::NS, '/weekly-tasks/complete', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'complete_weekly_task' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( self::NS, '/weekly-tasks/update', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'add_task_update' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( self::NS, '/weekly-tasks/timeline', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_task_timeline' ),
			'permission_callback' => '__return_true',
		) );

		// ------- تبويب "الإدارة" في نفس صفحة الموظفين: دخول برمز سري مشترك من الإعدادات -------

		register_rest_route( self::NS, '/logout', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'logout' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( self::NS, '/admin-login', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'admin_login' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( self::NS, '/admin/weekly-tasks', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'admin_get_weekly_tasks' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( self::NS, '/admin/weekly-tasks/create', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'admin_create_weekly_tasks' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( self::NS, '/admin/weekly-tasks/status', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'admin_set_task_status' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( self::NS, '/admin/weekly-tasks/comment', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'admin_add_task_comment' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( self::NS, '/admin/weekly-tasks/timeline', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'admin_get_task_timeline' ),
			'permission_callback' => '__return_true',
		) );

		// ------- "المهام الجماعية": إضافة/إزالة أعضاء على مهمة موجودة -------

		register_rest_route( self::NS, '/admin/weekly-tasks/add-member', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'admin_add_task_member' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( self::NS, '/admin/weekly-tasks/remove-member', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'admin_remove_task_member' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( self::NS, '/admin/weekly-tasks/transfer-member', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'admin_transfer_task_member' ),
			'permission_callback' => '__return_true',
		) );

		// ------- لوحة القيادة الموحّدة: عدادات + "ماذا يحدث الآن" + المشاكل + Timeline موحّد لليوم -------

		register_rest_route( self::NS, '/admin/dashboard', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'admin_get_dashboard' ),
			'permission_callback' => '__return_true',
		) );

		// ------- "خريطة المشاريع المترابطة": مشاريع تجمع عدة مهام بترتيب متسلسل -------

		register_rest_route( self::NS, '/admin/projects', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'admin_get_projects' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( self::NS, '/admin/projects/create', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'admin_create_project' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( self::NS, '/admin/projects/chain', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'admin_get_project_chain' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( self::NS, '/admin/projects/link-task', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'admin_link_task_to_project' ),
			'permission_callback' => '__return_true',
		) );

		// إنشاء مهمة جديدة مشتركة بين عدة موظفين مباشرة من تبويب "المشاريع"، بدل الاضطرار لإنشائها فرديًا
		// من "رفع مهام جديدة" ثم إضافة أعضاء لاحقًا واحدًا تلو الآخر.
		register_rest_route( self::NS, '/admin/projects/create-task', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'admin_create_project_task' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( self::NS, '/admin/projects/unlink-task', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'admin_unlink_task_from_project' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( self::NS, '/my-project-chain', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_my_project_chain' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( self::NS, '/admin/weekly-tasks/search', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'admin_search_weekly_tasks' ),
			'permission_callback' => '__return_true',
		) );

		// ------- تبويب "المهام المرسلة" داخل قسم الإدارة: كل التحديثات اليومية التي أرسلها الموظفون + تعليقات الإدارة عليها -------

		register_rest_route( self::NS, '/admin/updates', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'admin_get_updates' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( self::NS, '/admin/updates/comment', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'admin_add_update_comment' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( self::NS, '/my-updates', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_my_updates' ),
			'permission_callback' => '__return_true',
		) );

		// ------- مستوى الأداء الشهري -------

		register_rest_route( self::NS, '/admin/performance', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'admin_get_performance' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( self::NS, '/my-performance', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_my_performance' ),
			'permission_callback' => '__return_true',
		) );

		// ------- Checklist فرعي داخل كل مهمة -------
		register_rest_route( self::NS, '/weekly-tasks/checklist/add', array(
			'methods' => 'POST', 'callback' => array( $this, 'add_checklist_item' ), 'permission_callback' => '__return_true',
		) );
		register_rest_route( self::NS, '/weekly-tasks/checklist/toggle', array(
			'methods' => 'POST', 'callback' => array( $this, 'toggle_checklist_item' ), 'permission_callback' => '__return_true',
		) );

		// ------- مرفقات مستقلة على مستوى المهمة -------
		register_rest_route( self::NS, '/weekly-tasks/attachments/add', array(
			'methods' => 'POST', 'callback' => array( $this, 'add_task_attachment' ), 'permission_callback' => '__return_true',
		) );

		// ------- طلب مساعدة موجّه لزميل محدد -------
		register_rest_route( self::NS, '/weekly-tasks/help/request', array(
			'methods' => 'POST', 'callback' => array( $this, 'request_help' ), 'permission_callback' => '__return_true',
		) );
		register_rest_route( self::NS, '/weekly-tasks/help/resolve', array(
			'methods' => 'POST', 'callback' => array( $this, 'resolve_help' ), 'permission_callback' => '__return_true',
		) );
		// ------- تسليم/تحويل الدور: الموظف يُنهي جزءه من مهمة مشتركة ويُحوّل الدور لزميل آخر بنفس المهمة -------
		register_rest_route( self::NS, '/weekly-tasks/help/handoff', array(
			'methods' => 'POST', 'callback' => array( $this, 'handoff_task' ), 'permission_callback' => '__return_true',
		) );

		// ------- مرحلة "مراجعة واعتماد" -------
		register_rest_route( self::NS, '/admin/weekly-tasks/approve', array(
			'methods' => 'POST', 'callback' => array( $this, 'admin_approve_task' ), 'permission_callback' => '__return_true',
		) );

		// ------- مركز الإشعارات الداخلي -------
		register_rest_route( self::NS, '/notifications', array(
			'methods' => 'GET', 'callback' => array( $this, 'get_notifications' ), 'permission_callback' => '__return_true',
		) );
		register_rest_route( self::NS, '/notifications/mark-read', array(
			'methods' => 'POST', 'callback' => array( $this, 'mark_notifications_read' ), 'permission_callback' => '__return_true',
		) );
		register_rest_route( self::NS, '/admin/notifications', array(
			'methods' => 'GET', 'callback' => array( $this, 'admin_get_notifications' ), 'permission_callback' => '__return_true',
		) );
		register_rest_route( self::NS, '/admin/notifications/mark-read', array(
			'methods' => 'POST', 'callback' => array( $this, 'admin_mark_notifications_read' ), 'permission_callback' => '__return_true',
		) );

		// ------- سجل التدقيق العام -------
		register_rest_route( self::NS, '/admin/audit-log', array(
			'methods' => 'GET', 'callback' => array( $this, 'admin_get_audit_log' ), 'permission_callback' => '__return_true',
		) );

		// ------- صفحة "الفريق" -------
		register_rest_route( self::NS, '/admin/team', array(
			'methods' => 'GET', 'callback' => array( $this, 'admin_get_team' ), 'permission_callback' => '__return_true',
		) );

		// ------- تقرير أداء المهام الأسبوعي -------
		register_rest_route( self::NS, '/admin/weekly-task-report', array(
			'methods' => 'GET', 'callback' => array( $this, 'admin_get_weekly_task_report' ), 'permission_callback' => '__return_true',
		) );

		// ------- نقطة تشغيل خارجية لفحص التذكير التلقائي (بديل عن الاعتماد على WP-Cron الداخلي) -------
		register_rest_route( self::NS, '/cron/tick', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'cron_tick' ),
			'permission_callback' => '__return_true',
		) );

		// ================= نظام "الإجازات والاستئذانات" =================

		// -- الموظف: الإجازة السنوية --
		register_rest_route( self::NS, '/leave/summary', array(
			'methods' => 'GET', 'callback' => array( $this, 'leave_get_summary' ), 'permission_callback' => '__return_true',
		) );
		register_rest_route( self::NS, '/leave/calc', array(
			'methods' => 'GET', 'callback' => array( $this, 'leave_calc_days' ), 'permission_callback' => '__return_true',
		) );
		register_rest_route( self::NS, '/leave/requests', array(
			'methods' => 'GET', 'callback' => array( $this, 'leave_get_requests' ), 'permission_callback' => '__return_true',
		) );
		register_rest_route( self::NS, '/leave/requests/create', array(
			'methods' => 'POST', 'callback' => array( $this, 'leave_create_request' ), 'permission_callback' => '__return_true',
		) );
		register_rest_route( self::NS, '/leave/requests/cancel', array(
			'methods' => 'POST', 'callback' => array( $this, 'leave_cancel_request' ), 'permission_callback' => '__return_true',
		) );

		// -- الموظف: الإجازة المرضية / العذر الطبي --
		register_rest_route( self::NS, '/sick-leave/requests', array(
			'methods' => 'GET', 'callback' => array( $this, 'sick_get_requests' ), 'permission_callback' => '__return_true',
		) );
		register_rest_route( self::NS, '/sick-leave/requests/create', array(
			'methods' => 'POST', 'callback' => array( $this, 'sick_create_request' ), 'permission_callback' => '__return_true',
		) );

		// -- الموظف: الاستئذانات --
		register_rest_route( self::NS, '/permission/requests', array(
			'methods' => 'GET', 'callback' => array( $this, 'permission_get_requests' ), 'permission_callback' => '__return_true',
		) );
		register_rest_route( self::NS, '/permission/requests/create', array(
			'methods' => 'POST', 'callback' => array( $this, 'permission_create_request' ), 'permission_callback' => '__return_true',
		) );

		// -- الإدارة: مراجعة/موافقة/رفض الثلاثة أنواع + إضافة إجازة سابقة + إدارة أرصدة الموظفين --
		register_rest_route( self::NS, '/admin/leave/requests', array(
			'methods' => 'GET', 'callback' => array( $this, 'admin_leave_get_requests' ), 'permission_callback' => '__return_true',
		) );
		register_rest_route( self::NS, '/admin/leave/requests/decide', array(
			'methods' => 'POST', 'callback' => array( $this, 'admin_leave_decide' ), 'permission_callback' => '__return_true',
		) );
		register_rest_route( self::NS, '/admin/leave/requests/add-past', array(
			'methods' => 'POST', 'callback' => array( $this, 'admin_leave_add_past' ), 'permission_callback' => '__return_true',
		) );
		register_rest_route( self::NS, '/admin/sick-leave/requests', array(
			'methods' => 'GET', 'callback' => array( $this, 'admin_sick_get_requests' ), 'permission_callback' => '__return_true',
		) );
		register_rest_route( self::NS, '/admin/sick-leave/requests/decide', array(
			'methods' => 'POST', 'callback' => array( $this, 'admin_sick_decide' ), 'permission_callback' => '__return_true',
		) );
		register_rest_route( self::NS, '/admin/permission/requests', array(
			'methods' => 'GET', 'callback' => array( $this, 'admin_permission_get_requests' ), 'permission_callback' => '__return_true',
		) );
		register_rest_route( self::NS, '/admin/permission/requests/decide', array(
			'methods' => 'POST', 'callback' => array( $this, 'admin_permission_decide' ), 'permission_callback' => '__return_true',
		) );
		register_rest_route( self::NS, '/admin/leave/balances', array(
			'methods' => 'GET', 'callback' => array( $this, 'admin_leave_get_balances' ), 'permission_callback' => '__return_true',
		) );
		register_rest_route( self::NS, '/admin/leave/balances/adjust', array(
			'methods' => 'POST', 'callback' => array( $this, 'admin_leave_adjust_balance' ), 'permission_callback' => '__return_true',
		) );
		register_rest_route( self::NS, '/admin/leave/settings', array(
			'methods' => 'POST', 'callback' => array( $this, 'admin_leave_update_settings' ), 'permission_callback' => '__return_true',
		) );

		// ================= نظام "الحضور والانصراف والتحضير" =================

		// -- الموظف --
		register_rest_route( self::NS, '/attendance/settings', array(
			'methods' => 'GET', 'callback' => array( $this, 'attendance_get_settings' ), 'permission_callback' => '__return_true',
		) );
		register_rest_route( self::NS, '/attendance/today', array(
			'methods' => 'GET', 'callback' => array( $this, 'attendance_get_today' ), 'permission_callback' => '__return_true',
		) );
		register_rest_route( self::NS, '/attendance/check-in', array(
			'methods' => 'POST', 'callback' => array( $this, 'attendance_check_in' ), 'permission_callback' => '__return_true',
		) );
		register_rest_route( self::NS, '/attendance/check-out', array(
			'methods' => 'POST', 'callback' => array( $this, 'attendance_check_out' ), 'permission_callback' => '__return_true',
		) );
		register_rest_route( self::NS, '/attendance/history', array(
			'methods' => 'GET', 'callback' => array( $this, 'attendance_get_history' ), 'permission_callback' => '__return_true',
		) );

		// -- الإدارة --
		register_rest_route( self::NS, '/admin/attendance/day', array(
			'methods' => 'GET', 'callback' => array( $this, 'admin_attendance_get_day' ), 'permission_callback' => '__return_true',
		) );
		register_rest_route( self::NS, '/admin/attendance/settings', array(
			'methods' => 'GET', 'callback' => array( $this, 'admin_attendance_get_settings' ), 'permission_callback' => '__return_true',
		) );
		register_rest_route( self::NS, '/admin/attendance/settings/update', array(
			'methods' => 'POST', 'callback' => array( $this, 'admin_attendance_update_settings' ), 'permission_callback' => '__return_true',
		) );
		register_rest_route( self::NS, '/admin/attendance/employee-history', array(
			'methods' => 'GET', 'callback' => array( $this, 'admin_attendance_employee_history' ), 'permission_callback' => '__return_true',
		) );
		register_rest_route( self::NS, '/admin/attendance/manual-edit', array(
			'methods' => 'POST', 'callback' => array( $this, 'admin_attendance_manual_edit' ), 'permission_callback' => '__return_true',
		) );
		register_rest_route( self::NS, '/admin/attendance/audit-logs', array(
			'methods' => 'GET', 'callback' => array( $this, 'admin_attendance_get_audit' ), 'permission_callback' => '__return_true',
		) );
		register_rest_route( self::NS, '/admin/attendance/report', array(
			'methods' => 'GET', 'callback' => array( $this, 'admin_attendance_get_report' ), 'permission_callback' => '__return_true',
		) );
		register_rest_route( self::NS, '/admin/attendance/report/export', array(
			'methods' => 'GET', 'callback' => array( $this, 'admin_attendance_export_report' ), 'permission_callback' => '__return_true',
		) );

	}

	private function client_ip() {
		return ET_Security::client_ip();
	}

	/** POST /logout — يمسح كوكيز الجلسة (المستخدمة لفتح المرفقات المحمية) عند خروج الموظف/الإدارة */
	public function logout( WP_REST_Request $request ) {
		if ( $request->get_param( 'admin' ) ) {
			ET_Security::clear_cookie( ET_Security::ADMIN_COOKIE );
		} else {
			ET_Security::clear_cookie( ET_Security::EMPLOYEE_COOKIE );
		}
		return rest_ensure_response( array( 'success' => true ) );
	}

	/** GET /employees — قائمة الموظفين النشطين (لقوائم الزملاء والإدارة). لا تُعرض إلا لموظف أو مدير مسجّل دخوله. */
	public function get_employees( WP_REST_Request $request ) {
		$token = ET_Security::request_token( $request );
		if ( is_wp_error( ET_Security::verify_employee_token( $token ) ) && is_wp_error( ET_Security::verify_admin_session_token( $token ) ) ) {
			return new WP_Error( 'et_login_required', __( 'سجّل الدخول أولًا.'), array( 'status' => 401 ) );
		}
		$employees = ET_Employees::active_for_picker();
		$out = array();
		foreach ( $employees as $e ) {
			$out[] = array( 'id' => (int) $e->id, 'name' => $e->name, 'department' => $e->department_name ?: '' );
		}
		return rest_ensure_response( array( 'employees' => $out ) );
	}

	/** POST /login — دخول الموظف بالبريد الإلكتروني + كلمة المرور: إصدار Token + حالة تحديث اليوم */
	public function select_employee( WP_REST_Request $request ) {
		if ( ! ET_Security::rate_limit_ok( 'login_' . $this->client_ip(), 10, 60 ) ) {
			return new WP_Error( 'et_rate_limited', __( 'محاولات كثيرة جدًا، حاول لاحقًا.'), array( 'status' => 429 ) );
		}

		$email    = ET_Employees::normalize_email( (string) $request->get_param( 'email' ) );
		$password = (string) $request->get_param( 'password' );
		if ( $email === '' || $password === '' ) {
			return new WP_Error( 'et_login_missing', __( 'أدخل البريد الإلكتروني وكلمة المرور.'), array( 'status' => 400 ) );
		}

		// قفل مؤقت لهذا البريد بعد محاولات خاطئة متتالية (بغض النظر عن عنوان IP المهاجم)
		$lock_key = 'emp_' . md5( $email );
		if ( ET_Security::is_locked( $lock_key, ET_Security::EMPLOYEE_MAX_FAILS ) ) {
			return new WP_Error( 'et_code_locked', __( 'تم إيقاف الدخول لهذا الحساب مؤقتًا بسبب محاولات خاطئة متكررة. حاول بعد 15 دقيقة أو تواصل مع الإدارة.'), array( 'status' => 429 ) );
		}

		// رسالة واحدة لكل حالات الفشل حتى لا يُعرف إن كان البريد مسجّلًا أصلًا
		$employee = ET_Employees::find_active_by_email( $email );
		if ( ! $employee || trim( (string) $employee->access_code ) === '' || ! ET_Security::code_matches( $password, (string) $employee->access_code ) ) {
			ET_Security::register_failure( $lock_key );
			return new WP_Error( 'et_login_invalid', __( 'البريد الإلكتروني أو كلمة المرور غير صحيحة.'), array( 'status' => 403 ) );
		}
		ET_Security::clear_failures( $lock_key );

		$token   = ET_Security::issue_employee_token( $employee->id );
		// كوكي HttpOnly بنفس التوكن — فقط حتى تُفتح المرفقات المحمية من روابطها مباشرة
		ET_Security::set_cookie( ET_Security::EMPLOYEE_COOKIE, $token, time() + ET_Security::employee_session_hours() * HOUR_IN_SECONDS );
		// 'manual' فقط: تحديث أُنشئ تلقائيًا لمجرد تعليم مهمة "منجزة" (source='task_auto') لا يجب أن
		// يُظهر للموظف بنموذج التحديث اليدوي نفسه شارة "تم الإرسال اليوم" أو يمنعه من الكتابة يدويًا —
		// فالخياران (يدوي / تعليم مهمة) مستقلان تمامًا من ناحية نموذج التحديث اليدوي، حتى لو احتُسب
		// كلاهما معًا كـ"تحديث لليوم" لأغراض التذكيرات والتقارير والسلسلة (راجع ET_Updates::create).
		$today   = ET_Updates::get_today_update( $employee->id, null, 'manual' );
		$settings = ET_Settings::get();

		return rest_ensure_response( array(
			'token'              => $token,
			'employee'           => array( 'id' => (int) $employee->id, 'name' => $employee->name ),
			'already_submitted'  => (bool) $today && empty( $settings['allow_multiple_updates'] ),
			'can_edit'           => $today ? ET_Updates::can_edit( $today ) : false,
			'gamification'       => ! empty( $settings['show_gamification'] ) ? array(
				'streak'          => ET_Updates::current_streak( $employee->id ),
				'month_days'      => ET_Updates::committed_days_this_month( $employee->id ),
				'month_updates'   => ET_Updates::achievements_count_this_month( $employee->id ),
			) : null,
		) );
	}

	/** POST /me/password — الموظف يغيّر كلمة مروره بنفسه (يتطلب كلمة المرور الحالية) */
	public function change_my_password( WP_REST_Request $request ) {
		$employee_id = ET_Security::verify_employee_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $employee_id ) ) return $employee_id;

		$employee = ET_Employees::get( $employee_id );
		$current  = (string) $request->get_param( 'current_password' );
		$new      = ET_Employees::sanitize_code( $request->get_param( 'new_password' ) );

		$lock_key = 'pwchange_' . (int) $employee_id;
		if ( ET_Security::is_locked( $lock_key, ET_Security::EMPLOYEE_MAX_FAILS ) ) {
			return new WP_Error( 'et_code_locked', __( 'محاولات خاطئة كثيرة، حاول بعد 15 دقيقة.'), array( 'status' => 429 ) );
		}
		if ( ! $employee || ! ET_Security::code_matches( $current, (string) $employee->access_code ) ) {
			ET_Security::register_failure( $lock_key );
			return new WP_Error( 'et_password_wrong', __( 'كلمة المرور الحالية غير صحيحة.'), array( 'status' => 403 ) );
		}
		if ( mb_strlen( $new ) < ET_Employees::MIN_CODE_LENGTH ) {
			return new WP_Error( 'et_password_short', sprintf( __( 'كلمة المرور الجديدة قصيرة — %d أحرف على الأقل.'), ET_Employees::MIN_CODE_LENGTH ), array( 'status' => 400 ) );
		}
		ET_Security::clear_failures( $lock_key );
		ET_Employees::set_password( $employee_id, $new );

		// الجلسة الحالية تُلغى مع تغيير كلمة المرور — نصدر توكن جديد حتى يكمل الموظف بدون إعادة دخول
		$token = ET_Security::issue_employee_token( $employee_id );
		ET_Security::set_cookie( ET_Security::EMPLOYEE_COOKIE, $token, time() + ET_Security::employee_session_hours() * HOUR_IN_SECONDS );
		return rest_ensure_response( array( 'success' => true, 'token' => $token ) );
	}

	/** GET /me — بطاقات لوحة الموظف البسيطة (يتطلب Token) */
	public function get_me( WP_REST_Request $request ) {
		$token       = ET_Security::request_token( $request );
		$employee_id = ET_Security::verify_employee_token( $token );
		if ( is_wp_error( $employee_id ) ) return $employee_id;

		global $wpdb;
		$problems_table = ET_Database::table( 'problems' );

		$open = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$problems_table} WHERE employee_id = %d AND status NOT IN ('resolved','closed')", $employee_id
		) );
		$resolved = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$problems_table} WHERE employee_id = %d AND status IN ('resolved','closed')", $employee_id
		) );

		return rest_ensure_response( array(
			'achievements_month' => ET_Updates::achievements_count_this_month( $employee_id ),
			'open_problems'      => $open,
			'resolved_problems'  => $resolved,
			'committed_days'     => ET_Updates::committed_days_this_month( $employee_id ),
			'streak'             => ET_Updates::current_streak( $employee_id ),
		) );
	}

	/** GET /weekly-tasks — مهام الموظف الأسبوعية (الأسبوع الحالي + مهام سابقة غير منجزة)، يتطلب Token */
	public function get_weekly_tasks( WP_REST_Request $request ) {
		$token       = ET_Security::request_token( $request );
		$employee_id = ET_Security::verify_employee_token( $token );
		if ( is_wp_error( $employee_id ) ) return $employee_id;

		$data = ET_WeeklyTasks::get_for_employee( $employee_id );

		$all_ids       = array_merge( wp_list_pluck( $data['current'], 'id' ), wp_list_pluck( $data['previous_pending'], 'id' ) );
		$comments      = ET_TaskComments::for_tasks( $all_ids );
		$latest_update = ET_TaskUpdates::latest_for_tasks( $all_ids );
		$members_map   = ET_TaskMembers::for_tasks( $all_ids );
		$checklist_map = ET_Checklist::for_tasks( $all_ids );
		$attach_map    = ET_TaskAttachments::for_tasks( $all_ids );
		$help_map      = ET_HelpRequests::pending_for_tasks( $all_ids );
		$project_titles = ET_Projects::titles_map( array_merge( wp_list_pluck( $data['current'], 'project_id' ), wp_list_pluck( $data['previous_pending'], 'project_id' ) ) );

		$map = function ( $t ) use ( $comments, $latest_update, $members_map, $employee_id, $project_titles, $checklist_map, $attach_map, $help_map ) {
			$latest  = $latest_update[ (int) $t->id ] ?? null;
			$members = $members_map[ (int) $t->id ] ?? array();
			return array(
				'id'                => (int) $t->id,
				'title'             => $t->title,
				'description'       => $t->description,
				'status'            => $t->status,
				'week_start'        => $t->week_start_date,
				'task_day'          => $t->task_day ?: '',
				'day_label'         => ET_WeeklyTasks::day_label( $t->task_day ),
				'priority'          => $t->priority ?: 'medium',
				'priority_label'    => ET_WeeklyTasks::priority_label( $t->priority ?: 'medium' ),
				'progress_percent'  => (int) $t->progress_percent,
				'is_team_task'      => count( $members ) > 1,
				'due_date'          => $t->due_date ?: null,
				'urgency'           => ET_WeeklyTasks::urgency_badge( $t->due_date, $t->status ),
				'project'           => ! empty( $t->project_id ) ? array( 'id' => (int) $t->project_id, 'title' => $project_titles[ (int) $t->project_id ] ?? '' ) : null,
				'members'           => array_map( function ( $m ) use ( $employee_id ) {
					return array(
						'employee_id'      => (int) $m->employee_id,
						'name'             => $m->employee_name ?: '—',
						'role_label'       => $m->role_label,
						'is_primary'       => $m->role_label === 'المسؤول الرئيسي',
						'progress_percent' => (int) $m->progress_percent,
						'is_you'           => (int) $m->employee_id === (int) $employee_id,
					);
				}, $members ),
				'checklist'         => array_map( function ( $c ) {
					return array( 'id' => (int) $c->id, 'text' => $c->text, 'done' => (bool) $c->done );
				}, $checklist_map[ (int) $t->id ] ?? array() ),
				'attachments'       => array_map( function ( $a ) {
					return array( 'id' => (int) $a->id, 'name' => $a->name, 'url' => $a->url );
				}, $attach_map[ (int) $t->id ] ?? array() ),
				'pending_help'      => array_map( function ( $h ) {
					return array( 'id' => (int) $h->id, 'from_name' => $h->from_name, 'to_name' => $h->to_name, 'help_type' => $h->help_type, 'message' => $h->message );
				}, $help_map[ (int) $t->id ] ?? array() ),
				'latest_update'     => $latest ? array(
					'current_work'        => $latest->current_work,
					'work_status'         => $latest->work_status,
					'work_status_label'   => ET_TaskUpdates::work_status_label( $latest->work_status ),
					'has_problem'         => (bool) $latest->has_problem,
					'problem_description' => $latest->problem_description,
					'needs_help'          => (bool) $latest->needs_help,
					'help_description'    => $latest->help_description,
					'date'                => mysql2date( 'Y-m-d H:i', $latest->created_at ),
				) : null,
				'comments'          => array_map( function ( $c ) {
					return array( 'author' => $c->author, 'message' => $c->message, 'date' => mysql2date( 'Y-m-d H:i', $c->created_at ) );
				}, $comments[ (int) $t->id ] ?? array() ),
			);
		};

		return rest_ensure_response( array(
			'week_start'        => $data['week_start'],
			'current'           => array_map( $map, $data['current'] ),
			'previous_pending'  => array_map( $map, $data['previous_pending'] ),
		) );
	}

	/** POST /weekly-tasks/complete — الموظف يعلّم مهمته منجزة/غير منجزة، يتطلب Token */
	public function complete_weekly_task( WP_REST_Request $request ) {
		if ( ! ET_Security::rate_limit_ok( 'wtask_' . $this->client_ip(), 60, 60 ) ) {
			return new WP_Error( 'et_rate_limited', __( 'محاولات كثيرة جدًا، حاول لاحقًا.'), array( 'status' => 429 ) );
		}

		$token       = ET_Security::request_token( $request );
		$employee_id = ET_Security::verify_employee_token( $token );
		if ( is_wp_error( $employee_id ) ) return $employee_id;

		$task_id   = (int) $request->get_param( 'task_id' );
		$completed = $request->get_param( 'completed' );
		$completed = ( $completed === '1' || $completed === 1 || $completed === true );

		$result = ET_WeeklyTasks::set_completed( $task_id, $employee_id, $completed );
		if ( is_wp_error( $result ) ) return $result;

		return rest_ensure_response( array( 'success' => true ) );
	}

	/** يجهّز صف تحديث واحد للعرض في Timeline (تُستخدم من جهة الموظف والإدارة معًا) */
	private function format_task_update_for_display( $u ) {
		return array(
			'progress_percent'     => (int) $u->progress_percent,
			'work_status'          => $u->work_status,
			'work_status_label'    => ET_TaskUpdates::work_status_label( $u->work_status ),
			'achievements'         => $u->achievements,
			'current_work'         => $u->current_work,
			'has_problem'          => (bool) $u->has_problem,
			'problem_description'  => $u->problem_description,
			'needs_help'           => (bool) $u->needs_help,
			'help_description'     => $u->help_description,
			'date'                 => mysql2date( 'Y-m-d H:i', $u->created_at ),
		);
	}

	/** POST /weekly-tasks/update — الموظف يرفع تحديثًا على مهمة مُحدَّدة (نسبة إنجاز + حالة عمل داخلية + منجز
	 *  + عمل حالي + مشكلة/مساعدة)، بدل تغيير الحالة فقط. عند وصول النسبة لـ100% تُكمَل المهمة تلقائيًا.
	 *  يتطلب Token وملكية المهمة. */
	public function add_task_update( WP_REST_Request $request ) {
		if ( ! ET_Security::rate_limit_ok( 'ttaskupd_' . $this->client_ip(), 60, 60 ) ) {
			return new WP_Error( 'et_rate_limited', __( 'محاولات كثيرة جدًا، حاول لاحقًا.'), array( 'status' => 429 ) );
		}

		$token       = ET_Security::request_token( $request );
		$employee_id = ET_Security::verify_employee_token( $token );
		if ( is_wp_error( $employee_id ) ) return $employee_id;

		$task_id = (int) $request->get_param( 'task_id' );
		$task    = ET_WeeklyTasks::get( $task_id );
		if ( ! $task || ! ET_TaskMembers::is_member( $task_id, $employee_id ) ) {
			return new WP_Error( 'et_not_found', __( 'المهمة غير موجودة.'), array( 'status' => 404 ) );
		}

		$progress = $request->get_param( 'progress_percent' );
		if ( $progress === null || $progress === '' ) {
			return new WP_Error( 'et_invalid_progress', __( 'حدّد نسبة الإنجاز أولاً.'), array( 'status' => 400 ) );
		}

		$data = array(
			'progress_percent'    => (int) $progress,
			'work_status'         => sanitize_text_field( (string) $request->get_param( 'work_status' ) ),
			'achievements'        => ET_Security::sanitize_textarea( $request->get_param( 'achievements' ) ),
			'current_work'        => ET_Security::sanitize_textarea( $request->get_param( 'current_work' ) ),
			'has_problem'         => (bool) $request->get_param( 'has_problem' ),
			'problem_description' => ET_Security::sanitize_textarea( $request->get_param( 'problem_description' ) ),
			'needs_help'          => (bool) $request->get_param( 'needs_help' ),
			'help_description'    => ET_Security::sanitize_textarea( $request->get_param( 'help_description' ) ),
		);

		ET_TaskUpdates::add( $task_id, $employee_id, $data );

		// إشعار داخلي فوري للإدارة — نفس السبب المذكور في submit_update(): تحديث المهمة قد يحمل
		// "⚠️ مشكلة" أو "🆘 يحتاج مساعدة" ولم يكن أي شيء يُنبّه الإدارة بمركز الإشعارات الداخلي بهذا إطلاقًا.
		if ( $data['has_problem'] || $data['needs_help'] ) {
			$emp = ET_Employees::get( $employee_id );
			$emp_name = $emp ? $emp->name : 'موظف';
			$notif = $data['has_problem'] && $data['needs_help']
				? ( '⚠️🆘 ' . $emp_name . ' سجّل مشكلة وطلب مساعدة بمهمة "' . $task->title . '"' )
				: ( $data['has_problem']
					? ( '⚠️ ' . $emp_name . ' سجّل مشكلة بمهمة "' . $task->title . '"' )
					: ( '🆘 ' . $emp_name . ' يحتاج مساعدة بمهمة "' . $task->title . '"' ) );
			ET_Notifications::create_for_admin( $notif );
		}

		$refreshed = ET_WeeklyTasks::get( $task_id );

		return rest_ensure_response( array(
			'success'          => true,
			'status'           => $refreshed->status,
			'progress_percent' => (int) $refreshed->progress_percent,
		) );
	}

	/** GET /weekly-tasks/timeline — سجل كل تحديثات مهمة يشارك فيها الموظف نفسه (كعضو بالفريق)، يتطلب Token وعضوية */
	public function get_task_timeline( WP_REST_Request $request ) {
		$token       = ET_Security::request_token( $request );
		$employee_id = ET_Security::verify_employee_token( $token );
		if ( is_wp_error( $employee_id ) ) return $employee_id;

		$task_id = (int) $request->get_param( 'task_id' );
		$task    = ET_WeeklyTasks::get( $task_id );
		if ( ! $task || ! ET_TaskMembers::is_member( $task_id, $employee_id ) ) {
			return new WP_Error( 'et_not_found', __( 'المهمة غير موجودة.'), array( 'status' => 404 ) );
		}

		$updates = ET_TaskUpdates::for_task( $task_id );
		return rest_ensure_response( array( 'updates' => array_map( array( $this, 'format_task_update_for_display' ), $updates ) ) );
	}

	/* ---------------------------------------------------------------------
	   تبويب "الإدارة" — كل مدير يدخل ببريده وكلمة مروره (نفس حسابه في لوحة /admin).
	   جلسة موقّعة (HMAC) صالحة 12 ساعة.
	   --------------------------------------------------------------------- */

	/** POST /admin-login — التحقق من بريد وكلمة مرور المدير وإصدار جلسة إدارة عند النجاح */
	public function admin_login( WP_REST_Request $request ) {
		if ( ! ET_Security::rate_limit_ok( 'admlogin_' . $this->client_ip(), 10, 60 ) ) {
			return new WP_Error( 'et_rate_limited', __( 'محاولات كثيرة جدًا، حاول لاحقًا.'), array( 'status' => 429 ) );
		}

		$email    = strtolower( trim( (string) $request->get_param( 'email' ) ) );
		$password = (string) $request->get_param( 'password' );
		if ( $email === '' || $password === '' ) {
			return new WP_Error( 'et_login_missing', __( 'أدخل البريد الإلكتروني وكلمة المرور.'), array( 'status' => 400 ) );
		}

		// قفل مؤقت: لكل حساب بعد 10 محاولات خاطئة، ولكل IP، وعام بعد 50 محاولة خاطئة خلال 15 دقيقة
		$acct_key = 'adm_' . md5( $email );
		$ip_key   = 'admip_' . md5( $this->client_ip() );
		if ( ET_Security::is_locked( $acct_key, ET_Security::ADMIN_MAX_FAILS ) || ET_Security::is_locked( $ip_key, ET_Security::ADMIN_MAX_FAILS ) || ET_Security::is_locked( 'adm_global', ET_Security::ADMIN_MAX_FAILS * 5 ) ) {
			return new WP_Error( 'et_admin_locked', __( 'تم إيقاف دخول الإدارة مؤقتًا بسبب محاولات خاطئة متكررة. حاول بعد 15 دقيقة.'), array( 'status' => 429 ) );
		}
		$user = ET_Security::find_admin_by_credentials( $email, $password );
		if ( ! $user ) {
			ET_Security::register_failure( $acct_key );
			ET_Security::register_failure( $ip_key );
			ET_Security::register_failure( 'adm_global' );
			return new WP_Error( 'et_admin_login_invalid', __( 'البريد الإلكتروني أو كلمة المرور غير صحيحة.'), array( 'status' => 403 ) );
		}
		ET_Security::clear_failures( $acct_key );
		ET_Security::clear_failures( $ip_key );

		$token = ET_Security::issue_admin_session_token( $user->id );
		// كوكي HttpOnly بنفس التوكن — يسمح بفتح المرفقات المحمية من الروابط مباشرة
		ET_Security::set_cookie( ET_Security::ADMIN_COOKIE, $token, time() + 12 * HOUR_IN_SECONDS );

		return rest_ensure_response( array( 'token' => $token, 'admin' => array( 'name' => $user->name, 'email' => $user->email ) ) );
	}

	/** GET /admin/weekly-tasks — كل مهام الأسبوع المطلوب (الافتراضي: الحالي) لكل الموظفين، مع تعليقات المتابعة */
	public function admin_get_weekly_tasks( WP_REST_Request $request ) {
		$auth = ET_Security::verify_admin_session_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $auth ) ) return $auth;

		$week  = sanitize_text_field( (string) $request->get_param( 'week_start' ) );
		$tasks = ET_WeeklyTasks::for_week( $week );

		$ids           = wp_list_pluck( $tasks, 'id' );
		$comments      = ET_TaskComments::for_tasks( $ids );
		$latest_update = ET_TaskUpdates::latest_for_tasks( $ids );
		$members_map   = ET_TaskMembers::for_tasks( $ids );
		$checklist_map = ET_Checklist::for_tasks( $ids );
		$attach_map    = ET_TaskAttachments::for_tasks( $ids );
		$help_map      = ET_HelpRequests::pending_for_tasks( $ids );
		$project_titles = ET_Projects::titles_map( wp_list_pluck( $tasks, 'project_id' ) );

		$out = array_map( function ( $t ) use ( $comments, $latest_update, $members_map, $project_titles, $checklist_map, $attach_map, $help_map ) {
			$latest  = $latest_update[ (int) $t->id ] ?? null;
			$members = $members_map[ (int) $t->id ] ?? array();
			return array(
				'id'                => (int) $t->id,
				'employee_id'       => (int) $t->employee_id,
				'employee_name'     => $t->employee_name ?: '—',
				'title'             => $t->title,
				'description'       => $t->description,
				'status'            => $t->status,
				'week_start'        => $t->week_start_date,
				'task_day'          => $t->task_day ?: '',
				'day_label'         => ET_WeeklyTasks::day_label( $t->task_day ),
				'priority'          => $t->priority ?: 'medium',
				'priority_label'    => ET_WeeklyTasks::priority_label( $t->priority ?: 'medium' ),
				'progress_percent'  => (int) $t->progress_percent,
				'is_team_task'      => count( $members ) > 1,
				'due_date'          => $t->due_date ?: null,
				'urgency'           => ET_WeeklyTasks::urgency_badge( $t->due_date, $t->status ),
				'project'           => ! empty( $t->project_id ) ? array( 'id' => (int) $t->project_id, 'title' => $project_titles[ (int) $t->project_id ] ?? '' ) : null,
				'members'           => array_map( function ( $m ) {
					return array(
						'employee_id'      => (int) $m->employee_id,
						'name'             => $m->employee_name ?: '—',
						'role_label'       => $m->role_label,
						'is_primary'       => $m->role_label === 'المسؤول الرئيسي',
						'progress_percent' => (int) $m->progress_percent,
					);
				}, $members ),
				'checklist'         => array_map( function ( $c ) {
					return array( 'id' => (int) $c->id, 'text' => $c->text, 'done' => (bool) $c->done );
				}, $checklist_map[ (int) $t->id ] ?? array() ),
				'attachments'       => array_map( function ( $a ) {
					return array( 'id' => (int) $a->id, 'name' => $a->name, 'url' => $a->url );
				}, $attach_map[ (int) $t->id ] ?? array() ),
				'pending_help'      => array_map( function ( $h ) {
					return array( 'id' => (int) $h->id, 'from_name' => $h->from_name, 'to_name' => $h->to_name, 'help_type' => $h->help_type, 'message' => $h->message );
				}, $help_map[ (int) $t->id ] ?? array() ),
				'latest_update'     => $latest ? array(
					'current_work'        => $latest->current_work,
					'work_status'         => $latest->work_status,
					'work_status_label'   => ET_TaskUpdates::work_status_label( $latest->work_status ),
					'has_problem'         => (bool) $latest->has_problem,
					'problem_description' => $latest->problem_description,
					'needs_help'          => (bool) $latest->needs_help,
					'help_description'    => $latest->help_description,
					'date'                => mysql2date( 'Y-m-d H:i', $latest->created_at ),
				) : null,
				'comments'          => array_map( function ( $c ) {
					return array( 'author' => $c->author, 'message' => $c->message, 'date' => mysql2date( 'Y-m-d H:i', $c->created_at ) );
				}, $comments[ (int) $t->id ] ?? array() ),
			);
		}, $tasks );

		return rest_ensure_response( array(
			'week_start' => $week ?: ET_WeeklyTasks::current_week_start(),
			'tasks'      => $out,
		) );
	}

	/** POST /admin/weekly-tasks/create — الإدارة ترفع مهامًا جديدة لموظف من تبويب الإدارة، مع إشعار بريدي اختياري */
	public function admin_create_weekly_tasks( WP_REST_Request $request ) {
		$auth = ET_Security::verify_admin_session_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $auth ) ) return $auth;

		if ( ! ET_Security::rate_limit_ok( 'admcreate_' . $this->client_ip(), 30, 60 ) ) {
			return new WP_Error( 'et_rate_limited', __( 'محاولات كثيرة جدًا، حاول لاحقًا.'), array( 'status' => 429 ) );
		}

		$employee_id = (int) $request->get_param( 'employee_id' );
		$week_start  = sanitize_text_field( (string) $request->get_param( 'week_start_date' ) );
		$items       = $request->get_param( 'tasks' ); // مصفوفة { title, task_day, priority }
		$notify      = (string) $request->get_param( 'notify_employee' ) === '1';

		$employee = $employee_id ? ET_Employees::get( $employee_id ) : null;
		if ( ! $employee ) {
			return new WP_Error( 'et_invalid_employee', __( 'الرجاء اختيار موظف صحيح.'), array( 'status' => 400 ) );
		}
		if ( empty( $week_start ) ) {
			return new WP_Error( 'et_invalid_week', __( 'الرجاء تحديد بداية الأسبوع.'), array( 'status' => 400 ) );
		}
		// نحسم التاريخ إلى "أحد" الأسبوع الصحيح حسب توقيت الموقع، بدل الاعتماد الكامل على حساب
		// المتصفح، حتى لا تُحفظ المهمة بتاريخ لا يطابق استعلام "مهامي الأسبوعية" عند الموظف.
		$week_start = ET_WeeklyTasks::normalize_week_start( $week_start );
		if ( empty( $items ) || ! is_array( $items ) ) {
			return new WP_Error( 'et_invalid_tasks', __( 'الرجاء كتابة مهمة واحدة على الأقل.'), array( 'status' => 400 ) );
		}

		$created = array();
		foreach ( $items as $item ) {
			$title = sanitize_text_field( trim( (string) ( $item['title'] ?? '' ) ) );
			if ( $title === '' ) continue;

			$day      = sanitize_text_field( (string) ( $item['task_day'] ?? '' ) );
			$priority = sanitize_text_field( (string) ( $item['priority'] ?? 'medium' ) );
			$due_date = sanitize_text_field( (string) ( $item['due_date'] ?? '' ) );

			ET_WeeklyTasks::create( array(
				'employee_id'     => $employee->id,
				'department_id'   => $employee->department_id,
				'week_start_date' => $week_start,
				'task_day'        => $day,
				'title'           => $title,
				'priority'        => $priority,
				'due_date'        => $due_date,
			) );

			$created[] = array( 'title' => $title, 'task_day' => $day, 'priority' => $priority );
		}

		if ( empty( $created ) ) {
			return new WP_Error( 'et_invalid_tasks', __( 'الرجاء كتابة مهمة واحدة على الأقل.'), array( 'status' => 400 ) );
		}

		ET_AuditLog::log( 'الإدارة أضافت ' . count( $created ) . ' مهمة جديدة لـ' . $employee->name . ' (أسبوع ' . $week_start . ')' );

		$email_diag = array( 'ok' => false );
		if ( $notify ) {
			$email_diag = et_send_weekly_tasks_notification( $employee, $created );
		}

		return rest_ensure_response( array(
			'success'     => true,
			'created'     => count( $created ),
			'email_sent'  => ! empty( $email_diag['ok'] ),
			'email_error' => $email_diag['error'] ?? null,
		) );
	}

	/** POST /admin/weekly-tasks/status — الإدارة تبدّل حالة أي مهمة (متابعة تنفيذ المهمة مع الموظف) */
	public function admin_set_task_status( WP_REST_Request $request ) {
		$auth = ET_Security::verify_admin_session_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $auth ) ) return $auth;

		$task_id   = (int) $request->get_param( 'task_id' );
		$completed = $request->get_param( 'completed' );
		$completed = ( $completed === '1' || $completed === 1 || $completed === true );

		$result = ET_WeeklyTasks::set_status_admin( $task_id, $completed );
		if ( is_wp_error( $result ) ) return $result;

		return rest_ensure_response( array( 'success' => true ) );
	}

	/** POST /admin/weekly-tasks/comment — الإدارة تضيف ملاحظة متابعة على مهمة، تظهر للموظف في واجهته */
	public function admin_add_task_comment( WP_REST_Request $request ) {
		$auth = ET_Security::verify_admin_session_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $auth ) ) return $auth;

		if ( ! ET_Security::rate_limit_ok( 'admcomment_' . $this->client_ip(), 60, 60 ) ) {
			return new WP_Error( 'et_rate_limited', __( 'محاولات كثيرة جدًا، حاول لاحقًا.'), array( 'status' => 429 ) );
		}

		$task_id = (int) $request->get_param( 'task_id' );
		$message = ET_Security::sanitize_textarea( $request->get_param( 'message' ) );

		if ( empty( $task_id ) || ! ET_WeeklyTasks::get( $task_id ) ) {
			return new WP_Error( 'et_not_found', __( 'المهمة غير موجودة.'), array( 'status' => 404 ) );
		}
		if ( trim( $message ) === '' ) {
			return new WP_Error( 'et_empty_comment', __( 'اكتب نص الملاحظة أولاً.'), array( 'status' => 400 ) );
		}

		ET_TaskComments::add( $task_id, $message );

		return rest_ensure_response( array( 'success' => true ) );
	}

	/** GET /admin/weekly-tasks/timeline — سجل كل تحديثات مهمة معيّنة (أي مهمة، بلا قيد ملكية)، يتطلب جلسة إدارة */
	public function admin_get_task_timeline( WP_REST_Request $request ) {
		$auth = ET_Security::verify_admin_session_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $auth ) ) return $auth;

		$task_id = (int) $request->get_param( 'task_id' );
		if ( empty( $task_id ) || ! ET_WeeklyTasks::get( $task_id ) ) {
			return new WP_Error( 'et_not_found', __( 'المهمة غير موجودة.'), array( 'status' => 404 ) );
		}

		$updates = ET_TaskUpdates::for_task( $task_id );
		return rest_ensure_response( array( 'updates' => array_map( array( $this, 'format_task_update_for_display' ), $updates ) ) );
	}

	/** يجهّز مصفوفة عضو واحد للعرض المشترك بين استجابات إضافة/إزالة عضو المهمة */
	private function format_task_members_response( $task_id ) {
		$task    = ET_WeeklyTasks::get( $task_id );
		$members = ET_TaskMembers::for_task( $task_id );
		return array(
			'success'          => true,
			'progress_percent' => (int) $task->progress_percent,
			'status'           => $task->status,
			'members'          => array_map( function ( $m ) {
				return array(
					'employee_id'      => (int) $m->employee_id,
					'name'             => $m->employee_name ?: '—',
					'role_label'       => $m->role_label,
					'progress_percent' => (int) $m->progress_percent,
				);
			}, $members ),
		);
	}

	/** POST /admin/weekly-tasks/add-member — تضيف الإدارة موظفًا إضافيًا لمهمة موجودة فتصبح "مهمة جماعية"،
	 *  مثل "سارة انضمت للمهمة" — مع دور اختياري يوضح جزء الموظف من المهمة */
	public function admin_add_task_member( WP_REST_Request $request ) {
		$auth = ET_Security::verify_admin_session_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $auth ) ) return $auth;

		$task_id     = (int) $request->get_param( 'task_id' );
		$employee_id = (int) $request->get_param( 'employee_id' );
		$role_label  = sanitize_text_field( (string) $request->get_param( 'role_label' ) );

		if ( ! ET_WeeklyTasks::get( $task_id ) ) {
			return new WP_Error( 'et_not_found', __( 'المهمة غير موجودة.'), array( 'status' => 404 ) );
		}
		if ( ! ET_Employees::get( $employee_id ) ) {
			return new WP_Error( 'et_not_found', __( 'الموظف غير موجود.'), array( 'status' => 404 ) );
		}

		ET_TaskMembers::add_member( $task_id, $employee_id, $role_label );

		return rest_ensure_response( $this->format_task_members_response( $task_id ) );
	}

	/** POST /admin/weekly-tasks/remove-member — تزيل الإدارة عضوًا من مهمة جماعية (يبقى عضو واحد على الأقل دومًا) */
	public function admin_remove_task_member( WP_REST_Request $request ) {
		$auth = ET_Security::verify_admin_session_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $auth ) ) return $auth;

		$task_id     = (int) $request->get_param( 'task_id' );
		$employee_id = (int) $request->get_param( 'employee_id' );

		if ( ! ET_WeeklyTasks::get( $task_id ) ) {
			return new WP_Error( 'et_not_found', __( 'المهمة غير موجودة.'), array( 'status' => 404 ) );
		}

		$result = ET_TaskMembers::remove_member( $task_id, $employee_id );
		if ( is_wp_error( $result ) ) return $result;

		return rest_ensure_response( $this->format_task_members_response( $task_id ) );
	}

	/** POST /admin/weekly-tasks/transfer-member — "نقل المسؤولية": تنقل الإدارة مسؤولية مهمة (بنسبة تقدّمها
	 *  الحالية) من موظف لآخر، مثل "أحمد حوّل جزءه لسارة" أثناء التنفيذ */
	public function admin_transfer_task_member( WP_REST_Request $request ) {
		$auth = ET_Security::verify_admin_session_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $auth ) ) return $auth;

		$task_id          = (int) $request->get_param( 'task_id' );
		$from_employee_id = (int) $request->get_param( 'from_employee_id' );
		$to_employee_id   = (int) $request->get_param( 'to_employee_id' );
		$role_label       = sanitize_text_field( (string) $request->get_param( 'role_label' ) );

		if ( ! ET_WeeklyTasks::get( $task_id ) ) {
			return new WP_Error( 'et_not_found', __( 'المهمة غير موجودة.'), array( 'status' => 404 ) );
		}
		if ( ! ET_Employees::get( $to_employee_id ) ) {
			return new WP_Error( 'et_not_found', __( 'الموظف المستلم غير موجود.'), array( 'status' => 404 ) );
		}

		$result = ET_TaskMembers::transfer_member( $task_id, $from_employee_id, $to_employee_id, $role_label );
		if ( is_wp_error( $result ) ) return $result;

		return rest_ensure_response( $this->format_task_members_response( $task_id ) );
	}

	/** GET /admin/dashboard — لوحة القيادة الموحّدة: عدادات شاملة + "ماذا يحدث الآن" + المهام المتوقفة +
	 *  ملخص المشاريع + Timeline موحّد لأحداث اليوم عبر كل الموظفين والمهام معًا، بصفحة واحدة بدل تبويبات متفرقة */
	public function admin_get_dashboard( WP_REST_Request $request ) {
		$auth = ET_Security::verify_admin_session_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $auth ) ) return $auth;

		$week  = ET_WeeklyTasks::current_week_start();
		$tasks = ET_WeeklyTasks::for_week( $week );

		$ids           = wp_list_pluck( $tasks, 'id' );
		$latest_update = ET_TaskUpdates::latest_for_tasks( $ids );
		$members_map   = ET_TaskMembers::for_tasks( $ids );

		$counts = array( 'total' => count( $tasks ), 'in_progress' => 0, 'review' => 0, 'stuck' => 0, 'waiting_admin' => 0, 'done' => 0, 'not_started' => 0 );
		$now_doing = array();
		$problems  = array();

		foreach ( $tasks as $t ) {
			$latest  = $latest_update[ (int) $t->id ] ?? null;
			$members = $members_map[ (int) $t->id ] ?? array();
			$is_done = $t->status === 'done';

			if ( $is_done ) {
				$counts['done']++;
			} elseif ( $t->status === 'review' ) {
				$counts['review']++;
			} elseif ( $latest && $latest->has_problem ) {
				$counts['stuck']++;
			} elseif ( $latest && $latest->work_status === 'waiting_admin' ) {
				$counts['waiting_admin']++;
			} elseif ( (int) $t->progress_percent > 0 ) {
				$counts['in_progress']++;
			} else {
				$counts['not_started']++;
			}

			if ( ! $is_done && (int) $t->progress_percent > 0 ) {
				foreach ( ( $members ?: array() ) as $m ) {
					if ( (int) $m->progress_percent >= 100 ) continue;
					$now_doing[] = array(
						'employee_name'    => $m->employee_name ?: '—',
						'task_title'       => $t->title,
						'progress_percent' => (int) $m->progress_percent,
						'work_status_label'=> $latest ? ET_TaskUpdates::work_status_label( $latest->work_status ) : '',
					);
				}
			}

			if ( ! $is_done && $latest && $latest->has_problem && $latest->problem_description ) {
				$problems[] = array(
					'task_id'    => (int) $t->id,
					'task_title' => $t->title,
					'employee_name' => $t->employee_name ?: '—',
					'description'   => $latest->problem_description,
				);
			}
		}

		$projects = array_map( function ( $p ) {
			return array(
				'id' => (int) $p->id, 'title' => $p->title,
				'tasks_count' => (int) $p->tasks_count, 'done_count' => (int) $p->done_count,
				'progress_percent' => (int) $p->progress_percent,
			);
		}, ET_Projects::all_with_summary() );

		$timeline = array_map( function ( $u ) {
			return array(
				'time'         => mysql2date( 'H:i', $u->created_at ),
				'employee_name'=> $u->employee_name ?: '—',
				'task_title'   => $u->task_title,
				'work_status_label' => ET_TaskUpdates::work_status_label( $u->work_status ),
				'progress_percent'  => (int) $u->progress_percent,
			);
		}, ET_TaskUpdates::for_today() );

		return rest_ensure_response( array(
			'counts'    => $counts,
			'now_doing' => $now_doing,
			'problems'  => $problems,
			'projects'  => $projects,
			'timeline'  => $timeline,
			'unread_notifications' => ET_Notifications::unread_count_for_admin(),
		) );
	}

	/** GET /admin/projects — كل المشاريع مع نسبة إنجاز إجمالية (متوسط مهامها)، يتطلب جلسة إدارة */
	public function admin_get_projects( WP_REST_Request $request ) {
		$auth = ET_Security::verify_admin_session_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $auth ) ) return $auth;

		$projects = ET_Projects::all_with_summary();
		$out = array_map( function ( $p ) {
			return array(
				'id'               => (int) $p->id,
				'title'            => $p->title,
				'description'      => $p->description,
				'tasks_count'      => (int) $p->tasks_count,
				'done_count'       => (int) $p->done_count,
				'progress_percent' => (int) $p->progress_percent,
			);
		}, $projects );

		return rest_ensure_response( array( 'projects' => $out ) );
	}

	/** POST /admin/projects/create — تنشئ مشروعًا جديدًا فارغًا؛ المهام تُربط به لاحقًا من "🗺️ ربط مهمة" */
	public function admin_create_project( WP_REST_Request $request ) {
		$auth = ET_Security::verify_admin_session_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $auth ) ) return $auth;

		$title = sanitize_text_field( (string) $request->get_param( 'title' ) );
		if ( trim( $title ) === '' ) {
			return new WP_Error( 'et_invalid_title', __( 'اكتب اسم المشروع أولاً.'), array( 'status' => 400 ) );
		}
		$description = ET_Security::sanitize_textarea( $request->get_param( 'description' ) );

		$id = ET_Projects::create( $title, $description );
		return rest_ensure_response( array( 'success' => true, 'id' => $id ) );
	}

	/** GET /admin/projects/chain — سلسلة مهام مشروع بالترتيب مع حالة كل مهمة على الخريطة (مكتملة/متوقفة/معلّقة...) */
	public function admin_get_project_chain( WP_REST_Request $request ) {
		$auth = ET_Security::verify_admin_session_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $auth ) ) return $auth;

		$project_id = (int) $request->get_param( 'project_id' );
		$project    = ET_Projects::get( $project_id );
		if ( ! $project ) {
			return new WP_Error( 'et_not_found', __( 'المشروع غير موجود.'), array( 'status' => 404 ) );
		}

		return rest_ensure_response( array(
			'project' => array( 'id' => (int) $project->id, 'title' => $project->title, 'description' => $project->description ),
			'chain'   => ET_Projects::chain_for_project( $project_id ),
		) );
	}

	/** POST /admin/projects/link-task — تربط مهمة موجودة بمشروع في ترتيب مُحدَّد ضمن السلسلة */
	public function admin_link_task_to_project( WP_REST_Request $request ) {
		$auth = ET_Security::verify_admin_session_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $auth ) ) return $auth;

		$project_id = (int) $request->get_param( 'project_id' );
		$task_id    = (int) $request->get_param( 'task_id' );
		$order      = $request->get_param( 'sequence_order' );
		$order      = ( $order === null || $order === '' ) ? null : (int) $order;

		if ( ! ET_Projects::get( $project_id ) || ! ET_WeeklyTasks::get( $task_id ) ) {
			return new WP_Error( 'et_not_found', __( 'المشروع أو المهمة غير موجودة.'), array( 'status' => 404 ) );
		}

		ET_Projects::link_task( $project_id, $task_id, $order );

		return rest_ensure_response( array( 'chain' => ET_Projects::chain_for_project( $project_id ) ) );
	}

	/** POST /admin/projects/create-task — تنشئ مهمة جديدة مشتركة بين موظف أو أكثر دفعة واحدة، كل واحد بنص
	 *  "المطلوب منه" الخاص به (role_label)، وتربطها مباشرة بمشروع — بدل الاضطرار لإنشاء مهمة فردية من
	 *  "رفع مهام جديدة" ثم إضافة كل عضو إضافي لاحقًا واحدًا تلو الآخر من "متابعة المهام". */
	public function admin_create_project_task( WP_REST_Request $request ) {
		$auth = ET_Security::verify_admin_session_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $auth ) ) return $auth;

		if ( ! ET_Security::rate_limit_ok( 'projtaskcreate_' . $this->client_ip(), 30, 60 ) ) {
			return new WP_Error( 'et_rate_limited', __( 'محاولات كثيرة جدًا، حاول لاحقًا.'), array( 'status' => 429 ) );
		}

		$project_id = (int) $request->get_param( 'project_id' );
		$project    = $project_id ? ET_Projects::get( $project_id ) : null;
		if ( ! $project ) {
			return new WP_Error( 'et_invalid_project', __( 'المشروع غير موجود.'), array( 'status' => 404 ) );
		}

		$title = sanitize_text_field( trim( (string) $request->get_param( 'title' ) ) );
		if ( $title === '' ) {
			return new WP_Error( 'et_invalid_title', __( 'الرجاء كتابة عنوان المهمة.'), array( 'status' => 400 ) );
		}

		$week_start = sanitize_text_field( (string) $request->get_param( 'week_start_date' ) );
		if ( empty( $week_start ) ) $week_start = ET_WeeklyTasks::current_week_start();
		$week_start = ET_WeeklyTasks::normalize_week_start( $week_start );

		$day      = sanitize_text_field( (string) $request->get_param( 'task_day' ) );
		$priority = sanitize_text_field( (string) $request->get_param( 'priority' ) );
		$due_date = sanitize_text_field( (string) $request->get_param( 'due_date' ) );

		$members = $request->get_param( 'members' ); // مصفوفة { employee_id, role_label } — عنصر واحد على الأقل
		if ( empty( $members ) || ! is_array( $members ) ) {
			return new WP_Error( 'et_invalid_members', __( 'اختر موظفًا واحدًا على الأقل.'), array( 'status' => 400 ) );
		}

		$clean_members  = array();
		$seen_employees = array();
		foreach ( $members as $m ) {
			$emp_id = (int) ( is_array( $m ) ? ( $m['employee_id'] ?? 0 ) : 0 );
			if ( ! $emp_id || in_array( $emp_id, $seen_employees, true ) ) continue; // تجاهل التكرار
			$employee = ET_Employees::get( $emp_id );
			if ( ! $employee ) continue;
			$seen_employees[] = $emp_id;
			$clean_members[]  = array(
				'employee'   => $employee,
				'role_label' => sanitize_text_field( trim( (string) ( $m['role_label'] ?? '' ) ) ),
			);
		}
		if ( empty( $clean_members ) ) {
			return new WP_Error( 'et_invalid_members', __( 'اختر موظفًا واحدًا صحيحًا على الأقل من القائمة.'), array( 'status' => 400 ) );
		}

		$primary = $clean_members[0];
		$task_id = ET_WeeklyTasks::create( array(
			'employee_id'     => $primary['employee']->id,
			'department_id'   => $primary['employee']->department_id,
			'week_start_date' => $week_start,
			'task_day'        => $day,
			'title'           => $title,
			'priority'        => $priority,
			'due_date'        => $due_date,
			'role_label'      => $primary['role_label'],
		) );

		for ( $i = 1, $n = count( $clean_members ); $i < $n; $i++ ) {
			ET_TaskMembers::add_member( $task_id, $clean_members[ $i ]['employee']->id, $clean_members[ $i ]['role_label'] );
		}

		ET_Projects::link_task( $project_id, $task_id );

		$names = implode( '، ', array_map( function ( $m ) { return $m['employee']->name; }, $clean_members ) );
		ET_AuditLog::log( 'الإدارة أنشأت مهمة مشتركة "' . $title . '" بمشروع "' . $project->title . '" لـ' . $names );

		return rest_ensure_response( array(
			'success' => true,
			'task_id' => $task_id,
			'chain'   => ET_Projects::chain_for_project( $project_id ),
		) );
	}

	/** POST /admin/projects/unlink-task — تفصل مهمة عن مشروعها دون حذف المهمة نفسها */
	public function admin_unlink_task_from_project( WP_REST_Request $request ) {
		$auth = ET_Security::verify_admin_session_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $auth ) ) return $auth;

		$task_id = (int) $request->get_param( 'task_id' );
		$task    = ET_WeeklyTasks::get( $task_id );
		if ( ! $task ) {
			return new WP_Error( 'et_not_found', __( 'المهمة غير موجودة.'), array( 'status' => 404 ) );
		}
		$project_id = (int) $task->project_id;

		ET_Projects::unlink_task( $task_id );

		return rest_ensure_response( array( 'chain' => $project_id ? ET_Projects::chain_for_project( $project_id ) : array() ) );
	}

	/** GET /my-project-chain — الموظف يشوف خريطة سير عمل مشروع تخص إحدى مهامه (يتحقق أنه عضو بمهمة من هذا المشروع) */
	public function get_my_project_chain( WP_REST_Request $request ) {
		$token       = ET_Security::request_token( $request );
		$employee_id = ET_Security::verify_employee_token( $token );
		if ( is_wp_error( $employee_id ) ) return $employee_id;

		$project_id = (int) $request->get_param( 'project_id' );
		$project    = ET_Projects::get( $project_id );
		if ( ! $project ) {
			return new WP_Error( 'et_not_found', __( 'المشروع غير موجود.'), array( 'status' => 404 ) );
		}

		$chain = ET_Projects::chain_for_project( $project_id );
		$member_task_ids = array();
		foreach ( $chain as $node ) {
			if ( ET_TaskMembers::is_member( $node['id'], $employee_id ) ) $member_task_ids[] = $node['id'];
		}
		if ( empty( $member_task_ids ) ) {
			return new WP_Error( 'et_forbidden', __( 'هذا المشروع لا يخص أيًا من مهامك.'), array( 'status' => 403 ) );
		}

		return rest_ensure_response( array(
			'project' => array( 'id' => (int) $project->id, 'title' => $project->title ),
			'chain'   => $chain,
		) );
	}

	/** GET /admin/weekly-tasks/search — بحث سريع بعنوان المهمة (أي أسبوع)، يُستخدم بمربع "ربط مهمة" داخل تبويب المشاريع */
	public function admin_search_weekly_tasks( WP_REST_Request $request ) {
		$auth = ET_Security::verify_admin_session_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $auth ) ) return $auth;

		$q = sanitize_text_field( (string) $request->get_param( 'q' ) );
		if ( trim( $q ) === '' ) {
			return rest_ensure_response( array( 'tasks' => array() ) );
		}

		$tasks = ET_WeeklyTasks::search_by_title( $q, 10 );
		$out = array_map( function ( $t ) {
			return array(
				'id'            => (int) $t->id,
				'title'         => $t->title,
				'employee_name' => $t->employee_name ?: '—',
				'week_start'    => $t->week_start_date,
				'in_project'    => (int) $t->project_id,
			);
		}, $tasks );

		return rest_ensure_response( array( 'tasks' => $out ) );
	}

	/* ---------------------------------------------------------------------
	   Checklist فرعي داخل كل مهمة
	   ------------------------------------------------------------------- */

	/** POST /weekly-tasks/checklist/add — يضيف بندًا لقائمة إنجاز فرعية بمهمة (عضو بالمهمة أو جلسة إدارة) */
	public function add_checklist_item( WP_REST_Request $request ) {
		$task_id = (int) $request->get_param( 'task_id' );
		$text    = ET_Security::sanitize_textarea( $request->get_param( 'text' ) );
		if ( trim( $text ) === '' ) {
			return new WP_Error( 'et_empty', __( 'اكتب نص البند أولاً.'), array( 'status' => 400 ) );
		}

		$is_admin = ! is_wp_error( ET_Security::verify_admin_session_token( ET_Security::request_token( $request ) ) );
		if ( ! $is_admin ) {
			$employee_id = ET_Security::verify_employee_token( ET_Security::request_token( $request ) );
			if ( is_wp_error( $employee_id ) || ! ET_TaskMembers::is_member( $task_id, $employee_id ) ) {
				return new WP_Error( 'et_not_found', __( 'المهمة غير موجودة.'), array( 'status' => 404 ) );
			}
		}
		if ( ! ET_WeeklyTasks::get( $task_id ) ) {
			return new WP_Error( 'et_not_found', __( 'المهمة غير موجودة.'), array( 'status' => 404 ) );
		}

		ET_Checklist::add( $task_id, $text );
		return rest_ensure_response( array( 'items' => $this->format_checklist_for_task( $task_id ) ) );
	}

	/** POST /weekly-tasks/checklist/toggle — يبدّل حالة بند صح/خطأ */
	public function toggle_checklist_item( WP_REST_Request $request ) {
		$item_id = (int) $request->get_param( 'item_id' );
		$task_id = (int) $request->get_param( 'task_id' );
		$done    = (bool) $request->get_param( 'done' );

		$is_admin = ! is_wp_error( ET_Security::verify_admin_session_token( ET_Security::request_token( $request ) ) );
		if ( ! $is_admin ) {
			$employee_id = ET_Security::verify_employee_token( ET_Security::request_token( $request ) );
			if ( is_wp_error( $employee_id ) || ! ET_TaskMembers::is_member( $task_id, $employee_id ) ) {
				return new WP_Error( 'et_not_found', __( 'المهمة غير موجودة.'), array( 'status' => 404 ) );
			}
		}
		if ( ! ET_Checklist::item_belongs_to_task( $item_id, $task_id ) ) {
			return new WP_Error( 'et_not_found', __( 'البند غير موجود.'), array( 'status' => 404 ) );
		}

		ET_Checklist::toggle( $item_id, $done );
		return rest_ensure_response( array( 'items' => $this->format_checklist_for_task( $task_id ) ) );
	}

	private function format_checklist_for_task( $task_id ) {
		return array_map( function ( $c ) {
			return array( 'id' => (int) $c->id, 'text' => $c->text, 'done' => (bool) $c->done );
		}, ET_Checklist::for_task( $task_id ) );
	}

	/* ---------------------------------------------------------------------
	   مرفقات مستقلة على مستوى المهمة
	   ------------------------------------------------------------------- */

	/** POST /weekly-tasks/attachments/add — يضيف رابط مرفق باسم مخصص على المهمة نفسها */
	public function add_task_attachment( WP_REST_Request $request ) {
		$task_id = (int) $request->get_param( 'task_id' );
		$name    = sanitize_text_field( (string) $request->get_param( 'name' ) );
		$url     = esc_url_raw( (string) $request->get_param( 'url' ) );

		if ( trim( $name ) === '' || trim( $url ) === '' || ! wp_http_validate_url( $url ) ) {
			return new WP_Error( 'et_invalid', __( 'أدخل اسمًا ورابطًا صحيحًا.'), array( 'status' => 400 ) );
		}

		$is_admin = ! is_wp_error( ET_Security::verify_admin_session_token( ET_Security::request_token( $request ) ) );
		if ( ! $is_admin ) {
			$employee_id = ET_Security::verify_employee_token( ET_Security::request_token( $request ) );
			if ( is_wp_error( $employee_id ) || ! ET_TaskMembers::is_member( $task_id, $employee_id ) ) {
				return new WP_Error( 'et_not_found', __( 'المهمة غير موجودة.'), array( 'status' => 404 ) );
			}
		}
		if ( ! ET_WeeklyTasks::get( $task_id ) ) {
			return new WP_Error( 'et_not_found', __( 'المهمة غير موجودة.'), array( 'status' => 404 ) );
		}

		ET_TaskAttachments::add( $task_id, $name, $url );
		$items = array_map( function ( $a ) {
			return array( 'id' => (int) $a->id, 'name' => $a->name, 'url' => $a->url );
		}, ET_TaskAttachments::for_task( $task_id ) );

		return rest_ensure_response( array( 'attachments' => $items ) );
	}

	/* ---------------------------------------------------------------------
	   طلب مساعدة موجّه لزميل محدد
	   ------------------------------------------------------------------- */

	/** POST /weekly-tasks/help/request — الموظف يطلب مساعدة زميل محدد بأي مهمة من مهامه (لا يُشترط أن يكون
	 *  الزميل عضوًا بالمهمة أصلاً — طلب المساعدة لا يُضيفه كعضو، فقط يُبلغه ويربط الطلب بالمهمة) */
	public function request_help( WP_REST_Request $request ) {
		if ( ! ET_Security::rate_limit_ok( 'helpreq_' . $this->client_ip(), 30, 60 ) ) {
			return new WP_Error( 'et_rate_limited', __( 'محاولات كثيرة جدًا، حاول لاحقًا.'), array( 'status' => 429 ) );
		}

		$employee_id = ET_Security::verify_employee_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $employee_id ) ) return $employee_id;

		$task_id        = (int) $request->get_param( 'task_id' );
		$to_employee_id = (int) $request->get_param( 'to_employee_id' );
		$help_type      = sanitize_text_field( (string) $request->get_param( 'help_type' ) );
		$message        = ET_Security::sanitize_textarea( $request->get_param( 'message' ) );

		if ( ! ET_TaskMembers::is_member( $task_id, $employee_id ) ) {
			return new WP_Error( 'et_not_found', __( 'المهمة غير موجودة.'), array( 'status' => 404 ) );
		}
		if ( $to_employee_id === (int) $employee_id ) {
			return new WP_Error( 'et_invalid_peer', __( 'اختر زميلاً آخر غير نفسك.'), array( 'status' => 400 ) );
		}
		$to_emp = ET_Employees::get( $to_employee_id );
		if ( ! $to_emp || $to_emp->status !== 'active' ) {
			return new WP_Error( 'et_not_found', __( 'الزميل المطلوب مساعدته غير موجود.'), array( 'status' => 404 ) );
		}

		$attachment = $this->handle_help_attachment_upload( $request );
		if ( is_wp_error( $attachment ) ) return $attachment;

		ET_HelpRequests::create( $task_id, $employee_id, $to_employee_id, $help_type, $message, $attachment );
		return rest_ensure_response( array( 'success' => true ) );
	}

	/** يعالج مرفقًا اختياريًا (ملف عمل حقيقي) مُرفَقًا مع طلب مساعدة أو تحويل دور — نفس آلية رفع الملفات
	 *  المستخدمة بالتحديث اليومي بالضبط (نفس مجلد الرفع الخاص بالبلجن، ونفس فحوصات النوع/الحجم). تُعيد
	 *  array('url'=>, 'name'=>) عند وجود ملف صالح، null إن لم يُرفق أي ملف (اختياري دائمًا)، أو WP_Error
	 *  عند رفض الملف (نوع غير مسموح أو حجم كبير). */
	private function handle_help_attachment_upload( WP_REST_Request $request ) {
		$files = $request->get_file_params();
		if ( empty( $files['attachment'] ) || empty( $files['attachment']['name'] ) ) {
			return null;
		}

		// يُحفظ بالمجلد الخاص المحمي باسم عشوائي (راجع ET_Files)
		return ET_Files::store_upload( $files['attachment'] );
	}

	/** POST /weekly-tasks/help/handoff — الموظف يُنهي جزءه من مهمة (يُصحّح على مهمته تلقائيًا) ويُحوّل الدور
	 *  لزميل آخر ليكمل هو باقي العمل — بدل مجرد طلب مساعدة عام يبقى فيه الاثنان يعملان بالتوازي. يعمل على
	 *  أي مهمة من مهام الموظف، سواء كانت مشتركة أصلاً أو فردية: إن لم يكن الزميل المستهدف عضوًا بالمهمة بعد،
	 *  يُضاف تلقائيًا كعضو جديد (ET_TaskMembers::add_member) ليكمل هو باقي العمل عليها. */
	public function handoff_task( WP_REST_Request $request ) {
		if ( ! ET_Security::rate_limit_ok( 'handoff_' . $this->client_ip(), 30, 60 ) ) {
			return new WP_Error( 'et_rate_limited', __( 'محاولات كثيرة جدًا، حاول لاحقًا.'), array( 'status' => 429 ) );
		}

		$employee_id = ET_Security::verify_employee_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $employee_id ) ) return $employee_id;

		$task_id        = (int) $request->get_param( 'task_id' );
		$to_employee_id = (int) $request->get_param( 'to_employee_id' );
		$message        = ET_Security::sanitize_textarea( $request->get_param( 'message' ) );

		if ( ! ET_TaskMembers::is_member( $task_id, $employee_id ) ) {
			return new WP_Error( 'et_not_found', __( 'المهمة غير موجودة.'), array( 'status' => 404 ) );
		}
		if ( $to_employee_id === (int) $employee_id ) {
			return new WP_Error( 'et_invalid_peer', __( 'اختر زميلاً آخر غير نفسك.'), array( 'status' => 400 ) );
		}
		$to_emp = ET_Employees::get( $to_employee_id );
		if ( ! $to_emp || $to_emp->status !== 'active' ) {
			return new WP_Error( 'et_not_found', __( 'الزميل المختار غير موجود.'), array( 'status' => 404 ) );
		}

		// نرفع المرفق أولًا: لو رُفض الملف لا نكون قد أضفنا الزميل للمهمة بلا داعٍ
		$attachment = $this->handle_help_attachment_upload( $request );
		if ( is_wp_error( $attachment ) ) return $attachment;

		if ( ! ET_TaskMembers::is_member( $task_id, $to_employee_id ) ) {
			// الزميل ليس عضوًا بالمهمة بعد (مهمة فردية أو مشتركة مع آخرين غيره) — نضيفه تلقائيًا كعضو جديد
			// بدل رفض الطلب، حتى يتمكن من إكمال باقي العمل عليها فور التحويل. وتُبلَّغ الإدارة بذلك لأن
			// الموظف أضاف عضوًا لمهمة بدون مرورها عليها.
			ET_TaskMembers::add_member( $task_id, $to_employee_id, '' );
			$from_emp = ET_Employees::get( $employee_id );
			$task_obj = ET_WeeklyTasks::get( $task_id );
			ET_Notifications::create_for_admin( '👥 ' . ( $from_emp ? $from_emp->name : 'موظف' ) . ' أضاف ' . $to_emp->name . ' لمهمة "' . ( $task_obj ? $task_obj->title : '' ) . '" عبر تحويل الدور' );
		}

		ET_WeeklyTasks::set_completed( $task_id, $employee_id, true );

		$final_message = trim( (string) $message ) !== '' ? $message : 'انتهيت من جزئي — الدور عليك الآن لإكمال باقي المهمة.';
		ET_HelpRequests::create( $task_id, $employee_id, $to_employee_id, 'handoff', $final_message, $attachment );

		return rest_ensure_response( array( 'success' => true ) );
	}

	/** POST /weekly-tasks/help/resolve — إغلاق طلب مساعدة (من صاحب الطلب أو من استُدعي للمساعدة أو الإدارة) */
	public function resolve_help( WP_REST_Request $request ) {
		$request_id = (int) $request->get_param( 'request_id' );
		$help       = ET_HelpRequests::get( $request_id );
		if ( ! $help ) {
			return new WP_Error( 'et_not_found', __( 'طلب المساعدة غير موجود.'), array( 'status' => 404 ) );
		}

		$is_admin = ! is_wp_error( ET_Security::verify_admin_session_token( ET_Security::request_token( $request ) ) );
		if ( ! $is_admin ) {
			$employee_id = ET_Security::verify_employee_token( ET_Security::request_token( $request ) );
			if ( is_wp_error( $employee_id ) || ! in_array( (int) $employee_id, array( (int) $help->from_employee_id, (int) $help->to_employee_id ), true ) ) {
				return new WP_Error( 'et_forbidden', __( 'لا يمكنك إغلاق هذا الطلب.'), array( 'status' => 403 ) );
			}
		}

		ET_HelpRequests::resolve( $request_id );
		return rest_ensure_response( array( 'success' => true ) );
	}

	/* ---------------------------------------------------------------------
	   مرحلة "مراجعة واعتماد"
	   ------------------------------------------------------------------- */

	/** POST /admin/weekly-tasks/approve — اعتماد الإدارة النهائي لمهمة وصلت 100% ("مراجعة") فتصبح "مكتملة" فعليًا */
	public function admin_approve_task( WP_REST_Request $request ) {
		$auth = ET_Security::verify_admin_session_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $auth ) ) return $auth;

		$task_id = (int) $request->get_param( 'task_id' );
		$result  = ET_WeeklyTasks::approve( $task_id );
		if ( is_wp_error( $result ) ) return $result;

		return rest_ensure_response( array( 'success' => true ) );
	}

	/* ---------------------------------------------------------------------
	   مركز الإشعارات الداخلي
	   ------------------------------------------------------------------- */

	/** GET /notifications — إشعارات الموظف الأخيرة + عدد غير المقروء */
	public function get_notifications( WP_REST_Request $request ) {
		$employee_id = ET_Security::verify_employee_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $employee_id ) ) return $employee_id;

		$items = array_map( array( $this, 'format_notification' ), ET_Notifications::for_employee( $employee_id ) );
		return rest_ensure_response( array( 'items' => $items, 'unread' => ET_Notifications::unread_count_for_employee( $employee_id ) ) );
	}

	/** POST /notifications/mark-read — يعلّم كل إشعارات الموظف كمقروءة */
	public function mark_notifications_read( WP_REST_Request $request ) {
		$employee_id = ET_Security::verify_employee_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $employee_id ) ) return $employee_id;

		ET_Notifications::mark_all_read_for_employee( $employee_id );
		return rest_ensure_response( array( 'success' => true ) );
	}

	/** GET /admin/notifications — إشعارات الإدارة الأخيرة + عدد غير المقروء */
	public function admin_get_notifications( WP_REST_Request $request ) {
		$auth = ET_Security::verify_admin_session_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $auth ) ) return $auth;

		$items = array_map( array( $this, 'format_notification' ), ET_Notifications::for_admin() );
		return rest_ensure_response( array( 'items' => $items, 'unread' => ET_Notifications::unread_count_for_admin() ) );
	}

	/** POST /admin/notifications/mark-read — يعلّم كل إشعارات الإدارة كمقروءة */
	public function admin_mark_notifications_read( WP_REST_Request $request ) {
		$auth = ET_Security::verify_admin_session_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $auth ) ) return $auth;

		ET_Notifications::mark_all_read_for_admin();
		return rest_ensure_response( array( 'success' => true ) );
	}

	private function format_notification( $n ) {
		return array(
			'id'      => (int) $n->id,
			'message' => $n->message,
			'is_read' => (bool) $n->is_read,
			'date'    => mysql2date( 'Y-m-d H:i', $n->created_at ),
		);
	}

	/* ---------------------------------------------------------------------
	   سجل التدقيق العام
	   ------------------------------------------------------------------- */

	/** GET /admin/audit-log — أحدث 100 حدث موثّق عبر النظام كله (مو مهمة واحدة) */
	public function admin_get_audit_log( WP_REST_Request $request ) {
		$auth = ET_Security::verify_admin_session_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $auth ) ) return $auth;

		$items = array_map( function ( $l ) {
			return array( 'message' => $l->message, 'date' => mysql2date( 'Y-m-d H:i', $l->created_at ) );
		}, ET_AuditLog::recent( 100 ) );

		return rest_ensure_response( array( 'items' => $items ) );
	}

	/* ---------------------------------------------------------------------
	   صفحة "الفريق"
	   ------------------------------------------------------------------- */

	/** GET /admin/team — بطاقة لكل موظف نشط: عدد مهامه الحالية النشطة، المكتملة هذا الأسبوع، ومستوى أدائه الشهري */
	public function admin_get_team( WP_REST_Request $request ) {
		$auth = ET_Security::verify_admin_session_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $auth ) ) return $auth;

		global $wpdb;
		$employees_table = ET_Database::table( 'employees' );
		$members_table   = ET_Database::table( 'task_members' );
		$tasks_table     = ET_Database::table( 'weekly_tasks' );
		$week            = ET_WeeklyTasks::current_week_start();

		$rows = $wpdb->get_results( "SELECT id, name FROM {$employees_table} WHERE status = 'active' ORDER BY name ASC" );

		$out = array();
		foreach ( $rows as $r ) {
			$active = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$members_table} m INNER JOIN {$tasks_table} t ON t.id = m.task_id
				 WHERE m.employee_id = %d AND t.week_start_date = %s AND t.status != 'done'",
				$r->id, $week
			) );
			$done = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$members_table} m INNER JOIN {$tasks_table} t ON t.id = m.task_id
				 WHERE m.employee_id = %d AND t.week_start_date = %s AND t.status = 'done'",
				$r->id, $week
			) );
			$perf = ET_Performance::calculate( $r->id, (int) current_time( 'Y' ), (int) current_time( 'n' ) );
			$out[] = array(
				'employee_id'      => (int) $r->id,
				'name'             => $r->name,
				'active_tasks'     => $active,
				'done_this_week'   => $done,
				'performance_score'=> $perf['overall_score'],
				'performance_level'=> $perf['level'],
				'performance_color'=> $perf['level_color'],
			);
		}

		return rest_ensure_response( array( 'team' => $out ) );
	}

	/** GET /admin/weekly-task-report — تقرير أداء المهام لكل موظف (مسندة/منجزة/متأخرة بنوعيها/نسبة
	 *  الإنجاز) ضمن نطاق تاريخ تختاره الإدارة (قد يجمع عدة أسابيع معًا)، مع إمكانية تضييقه لموظف واحد فقط. */
	public function admin_get_weekly_task_report( WP_REST_Request $request ) {
		$auth = ET_Security::verify_admin_session_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $auth ) ) return $auth;

		$date_from = sanitize_text_field( (string) $request->get_param( 'date_from' ) );
		$date_to   = sanitize_text_field( (string) $request->get_param( 'date_to' ) );
		if ( empty( $date_from ) ) $date_from = ET_WeeklyTasks::current_week_start();
		if ( empty( $date_to ) )   $date_to   = $date_from;

		$employee_id = (int) $request->get_param( 'employee_id' );

		$items = ET_WeeklyTasks::employee_stats_for_range( $date_from, $date_to, $employee_id );

		return rest_ensure_response( array(
			'date_from' => $date_from,
			'date_to'   => $date_to,
			'items'     => $items,
		) );
	}

	/**
	 * نقطة اتصال خارجية (لا تعتمد على زيارات الموقع أو WP-Cron الداخلي) لتشغيل فحص
	 * تذكير الموظفين بالضبط بنفس منطق الجدولة التلقائية. الهدف: بعض الاستضافات تعطّل
	 * أو تُبطئ WP-Cron (لأنه أصلاً "زائف" في ووردبريس ويعتمد على زوّار الموقع)، فيمكن
	 * لأي خدمة كرون خارجية مجانية (مثل cron-job.org) أو كرون حقيقي بالاستضافة استدعاء
	 * هذا الرابط كل 15 دقيقة لضمان وصول التذكير 100% بغض النظر عن حركة الزوار.
	 * محمي برمز سرّي طويل (?key=...) وليس بصلاحيات ووردبريس، لأنه يُستدعى من خارج الموقع.
	 */
	public function cron_tick( WP_REST_Request $request ) {
		if ( ! ET_Security::rate_limit_ok( 'cron_tick_' . $this->client_ip(), 20, 60 ) ) {
			return new WP_Error( 'et_rate_limited', __( 'محاولات كثيرة جدًا، حاول لاحقًا.'), array( 'status' => 429 ) );
		}

		$key  = (string) $request->get_param( 'key' );
		$real = ET_Security::get_cron_secret();
		if ( empty( $key ) || ! hash_equals( $real, $key ) ) {
			return new WP_Error( 'et_forbidden', __( 'رمز غير صحيح.'), array( 'status' => 403 ) );
		}

		do_action( 'et_daily_reminder_cron' );

		return rest_ensure_response( array( 'ok' => true, 'ran_at' => current_time( 'mysql' ) ) );
	}

	/** يجهّز مصفوفة تحديث واحد للعرض في "المهام المرسلة" (الإدارة) أو "ردود الإدارة" (الموظف)، مع تعليقاتها */
	private function format_update_for_display( $u, $comments_map ) {
		$attachments = ! empty( $u->attachments ) ? json_decode( $u->attachments, true ) : array();
		if ( ! is_array( $attachments ) ) $attachments = array();

		return array(
			'id'             => (int) $u->id,
			'employee_id'    => (int) $u->employee_id,
			'employee_name'  => $u->employee_name ?? '',
			'department_name'=> $u->department_name ?? '',
			'update_date'    => $u->update_date,
			'created_at'     => mysql2date( 'Y-m-d H:i', $u->created_at ),
			'achievements'   => $u->achievements,
			'current_tasks'  => $u->current_tasks,
			'has_problem'    => (bool) $u->has_problem,
			'problem_description' => $u->problem_description,
			'assistance_required' => (bool) $u->assistance_required,
			'assistance_description' => $u->assistance_description,
			'tomorrow_plan'  => $u->tomorrow_plan,
			'attachments'    => array_map( function ( $a ) {
				return array( 'name' => $a['name'] ?? 'ملف', 'url' => $a['url'] ?? '' );
			}, $attachments ),
			'comments'       => array_map( function ( $c ) {
				return array( 'author' => $c->author, 'message' => $c->message, 'date' => mysql2date( 'Y-m-d H:i', $c->created_at ) );
			}, $comments_map[ (int) $u->id ] ?? array() ),
		);
	}

	/** GET /admin/updates — كل المهام/التحديثات اليومية التي أرسلها الموظفون ("المهام المرسلة")، مع تعليقات الإدارة، يتطلب جلسة إدارة */
	public function admin_get_updates( WP_REST_Request $request ) {
		$auth = ET_Security::verify_admin_session_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $auth ) ) return $auth;

		$employee_id = (int) $request->get_param( 'employee_id' );
		$page        = max( 1, (int) $request->get_param( 'page' ) );

		$result = ET_Updates::query( array(
			'employee_id' => $employee_id,
			'per_page'    => 30,
			'page'        => $page,
		) );

		$ids      = wp_list_pluck( $result['items'], 'id' );
		$comments = ET_UpdateComments::for_updates( $ids );

		$out = array_map( function ( $u ) use ( $comments ) {
			return $this->format_update_for_display( $u, $comments );
		}, $result['items'] );

		return rest_ensure_response( array(
			'updates' => $out,
			'total'   => $result['total'],
			'pages'   => $result['pages'],
			'page'    => $page,
		) );
	}

	/** POST /admin/updates/comment — الإدارة تضيف تعليقًا على مهمة/تحديث مُرسل، يصل للموظف في واجهته */
	public function admin_add_update_comment( WP_REST_Request $request ) {
		$auth = ET_Security::verify_admin_session_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $auth ) ) return $auth;

		if ( ! ET_Security::rate_limit_ok( 'admupdcomment_' . $this->client_ip(), 60, 60 ) ) {
			return new WP_Error( 'et_rate_limited', __( 'محاولات كثيرة جدًا، حاول لاحقًا.'), array( 'status' => 429 ) );
		}

		$update_id = (int) $request->get_param( 'update_id' );
		$message   = ET_Security::sanitize_textarea( $request->get_param( 'message' ) );

		if ( empty( $update_id ) || ! ET_Updates::get( $update_id ) ) {
			return new WP_Error( 'et_not_found', __( 'المهمة المرسلة غير موجودة.'), array( 'status' => 404 ) );
		}
		if ( trim( $message ) === '' ) {
			return new WP_Error( 'et_empty_comment', __( 'اكتب نص التعليق أولاً.'), array( 'status' => 400 ) );
		}

		ET_UpdateComments::add( $update_id, $message );

		return rest_ensure_response( array( 'success' => true ) );
	}

	/** GET /my-updates — تحديثات الموظف الأخيرة مع تعليقات الإدارة عليها (تظهر في تبويب "ردود الإدارة")، يتطلب Token */
	public function get_my_updates( WP_REST_Request $request ) {
		$token       = ET_Security::request_token( $request );
		$employee_id = ET_Security::verify_employee_token( $token );
		if ( is_wp_error( $employee_id ) ) return $employee_id;

		$result = ET_Updates::query( array( 'employee_id' => $employee_id, 'per_page' => 20, 'page' => 1 ) );

		$ids      = wp_list_pluck( $result['items'], 'id' );
		$comments = ET_UpdateComments::for_updates( $ids );

		$out = array_map( function ( $u ) use ( $comments ) {
			return $this->format_update_for_display( $u, $comments );
		}, $result['items'] );

		return rest_ensure_response( array( 'updates' => $out ) );
	}

	/** تتحقق أن (year, month) المرسلة من الطلب صالحة وترجعهما، وإلا تُرجع الشهر الحالي كافتراضي */
	private function resolved_period( WP_REST_Request $request ) {
		$year  = (int) $request->get_param( 'year' );
		$month = (int) $request->get_param( 'month' );
		if ( $year < 2000 ) $year = (int) current_time( 'Y' );
		if ( $month < 1 || $month > 12 ) $month = (int) current_time( 'n' );
		return array( $year, $month );
	}

	/** GET /admin/performance — مستوى الأداء الشهري لكل الموظفين (المهام في وقتها + الانتظام اليومي + قلة المشاكل)، يتطلب جلسة إدارة */
	public function admin_get_performance( WP_REST_Request $request ) {
		$auth = ET_Security::verify_admin_session_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $auth ) ) return $auth;

		list( $year, $month ) = $this->resolved_period( $request );
		$items = ET_Performance::calculate_all( $year, $month );

		return rest_ensure_response( array( 'items' => $items, 'year' => $year, 'month' => $month ) );
	}

	/** GET /my-performance — مستوى أداء الموظف نفسه لشهر مُحدد، بنفس التفاصيل التي تراها الإدارة، ليتمكن من تطوير نفسه */
	public function get_my_performance( WP_REST_Request $request ) {
		$token       = ET_Security::request_token( $request );
		$employee_id = ET_Security::verify_employee_token( $token );
		if ( is_wp_error( $employee_id ) ) return $employee_id;

		list( $year, $month ) = $this->resolved_period( $request );
		$perf = ET_Performance::calculate( $employee_id, $year, $month );

		return rest_ensure_response( $perf );
	}

	/** POST /submit — إرسال التحديث اليومي (multipart/form-data يدعم المرفقات) */
	public function submit_update( WP_REST_Request $request ) {
		if ( ! ET_Security::rate_limit_ok( 'submit_' . $this->client_ip(), 15, 60 ) ) {
			return new WP_Error( 'et_rate_limited', __( 'محاولات كثيرة جدًا، حاول لاحقًا.'), array( 'status' => 429 ) );
		}

		$token = ET_Security::request_token( $request );
		// الهوية تُستخرج فقط من التوكن الموقّع — لا يُعتد بأي employee_id يُرسل يدويًا
		$employee_id = ET_Security::verify_employee_token( $token );
		if ( is_wp_error( $employee_id ) ) return $employee_id;

		$data = array(
			'achievements'            => $request->get_param( 'achievements' ),
			'current_tasks'           => $request->get_param( 'current_tasks' ),
			'has_problem'             => $request->get_param( 'has_problem' ) === '1',
			'problem_description'     => $request->get_param( 'problem_description' ),
			'problem_priority'        => $request->get_param( 'problem_priority' ),
			'problem_impact'          => $request->get_param( 'problem_impact' ),
			'assistance_required'     => $request->get_param( 'assistance_required' ) === '1',
			'assistance_description'  => $request->get_param( 'assistance_description' ),
			'tomorrow_plan'           => $request->get_param( 'tomorrow_plan' ),
		);

		// نرفض قبل حفظ أي مرفق، حتى لا تبقى ملفات يتيمة على السيرفر لتحديث سيُرفض أصلًا
		$blocked = ET_Updates::manual_submission_blocked( $employee_id );
		if ( $blocked ) return $blocked;

		// معالجة المرفقات (اختياري)
		$attachments = array();
		$files = $request->get_file_params();
		if ( ! empty( $files['attachments'] ) ) {
			$file_list = array_filter( $this->normalize_files_array( $files['attachments'] ), function ( $f ) { return ! empty( $f['name'] ); } );
			// نتحقق من كل الملفات أولًا قبل حفظ أي منها
			foreach ( $file_list as $file ) {
				$check = ET_Security::validate_upload( $file );
				if ( is_wp_error( $check ) ) return $check;
			}
			foreach ( $file_list as $file ) {
				$stored = ET_Files::store_upload( $file ); // مجلد خاص محمي باسم عشوائي
				if ( ! is_wp_error( $stored ) ) {
					$attachments[] = $stored;
				}
			}
		}
		if ( ! empty( $attachments ) ) $data['attachments'] = $attachments;

		$result = ET_Updates::create( $employee_id, $data );
		if ( is_wp_error( $result ) ) return $result;

		// إشعار فوري للإدارة (واتساب + بريد) يحتوي ملخص ما رفعه الموظف
		$employee = ET_Employees::get( $employee_id );
		if ( $employee ) {
			$department  = $employee->department_id ? ET_Departments::get( $employee->department_id ) : null;
			$dept_name   = $department ? $department->name : '—';
			$has_problem = ! empty( $data['has_problem'] );
			$needs_help  = ! empty( $data['assistance_required'] );

			// رسالة مفصّلة (نفس دالة البناء المشتركة المستخدمة أيضًا عند تعليم مهمة "منجزة" مباشرة بدل
			// كتابة تحديث يدوي — راجع ET_TaskMembers::notify_member_completed — حتى يصل البريد بنفس
			// الصيغة تمامًا في الحالتين): كاملة للبريد، ومختصرة لواتساب.
			ET_Background::queue( 'et_bg_whatsapp_admin', array( 'message' => ET_Updates::build_summary_message( $employee, $dept_name, $data, false ) ) );
			ET_Background::queue( 'et_bg_email_admin', array( 'message' => ET_Updates::build_summary_message( $employee, $dept_name, $data, true ) ) );

			// إشعار داخلي فوري (🔔 بلوحة الإدارة) عند مشكلة أو طلب مساعدة — كانت قنوات واتساب/البريد
			// وحدها من "تصل" فعليًا (وقد تفشل أو تكون غير مفعّلة أصلاً)، بينما مركز الإشعارات الداخلي
			// نفسه لم يكن يُغذَّى بأي شيء إطلاقًا مهما حصل، فيبقى فارغًا دومًا مهما طلب أحد مساعدة.
			if ( $has_problem || $needs_help ) {
				$notif = $has_problem && $needs_help
					? ( '⚠️🆘 ' . $employee->name . ' سجّل مشكلة وطلب مساعدة بتحديثه اليومي' )
					: ( $has_problem
						? ( '⚠️ ' . $employee->name . ' سجّل مشكلة بتحديثه اليومي' )
						: ( '🆘 ' . $employee->name . ' يحتاج مساعدة بتحديثه اليومي' ) );
				ET_Notifications::create_for_admin( $notif );
			}
		}

		return rest_ensure_response( array( 'success' => true, 'update_id' => $result ) );
	}

	private function normalize_files_array( $files ) {
		if ( ! is_array( $files['name'] ) ) return array( $files );
		$normalized = array();
		foreach ( $files['name'] as $i => $name ) {
			$normalized[] = array(
				'name'     => $files['name'][ $i ],
				'type'     => $files['type'][ $i ],
				'tmp_name' => $files['tmp_name'][ $i ],
				'error'    => $files['error'][ $i ],
				'size'     => $files['size'][ $i ],
			);
		}
		return $normalized;
	}

	/* ===================================================================
	   نظام "الإجازات والاستئذانات" — Handlers
	   =================================================================== */

	private function fmt_leave_request( $r ) {
		return array(
			'id'                => (int) $r->id,
			'employee_id'       => (int) $r->employee_id,
			'employee_name'     => $r->employee_name ?? '',
			'start_date'        => $r->start_date,
			'end_date'          => $r->end_date,
			'days_count'        => (float) $r->days_count,
			'notes'             => $r->notes,
			'status'            => $r->status,
			'status_label'      => $this->leave_status_label( $r->status ),
			'balance_before'    => (float) $r->balance_before,
			'admin_notes'       => $r->admin_notes,
			'rejection_reason'  => $r->rejection_reason,
			'added_by_admin'    => ! empty( $r->added_by_admin ),
			'decided_at'        => $r->decided_at,
			'created_at'        => $r->created_at,
		);
	}

	private function fmt_sick_request( $r ) {
		return array(
			'id'                => (int) $r->id,
			'employee_id'       => (int) $r->employee_id,
			'employee_name'     => $r->employee_name ?? '',
			'start_date'        => $r->start_date,
			'end_date'          => $r->end_date,
			'days_count'        => (float) $r->days_count,
			'notes'             => $r->notes,
			'attachment_url'    => $r->attachment_url,
			'attachment_name'   => $r->attachment_name,
			'status'            => $r->status,
			'status_label'      => $this->leave_status_label( $r->status ),
			'admin_notes'       => $r->admin_notes,
			'rejection_reason'  => $r->rejection_reason,
			'decided_at'        => $r->decided_at,
			'created_at'        => $r->created_at,
		);
	}

	private function fmt_permission_request( $r ) {
		return array(
			'id'                => (int) $r->id,
			'employee_id'       => (int) $r->employee_id,
			'employee_name'     => $r->employee_name ?? '',
			'perm_date'         => $r->perm_date,
			'time_out'          => $r->time_out,
			'time_return'       => $r->time_return,
			'reason'            => $r->reason,
			'notes'             => $r->notes,
			'status'            => $r->status,
			'status_label'      => $this->leave_status_label( $r->status ),
			'admin_notes'       => $r->admin_notes,
			'rejection_reason'  => $r->rejection_reason,
			'decided_at'        => $r->decided_at,
			'created_at'        => $r->created_at,
		);
	}

	private function leave_status_label( $status ) {
		$labels = array(
			'pending'   => 'قيد المراجعة',
			'approved'  => 'موافق عليه',
			'rejected'  => 'مرفوض',
			'cancelled' => 'ملغي',
		);
		return $labels[ $status ] ?? $status;
	}

	/** GET /leave/summary — رصيد الإجازة السنوية للموظف الحالي (Token) */
	public function leave_get_summary( WP_REST_Request $request ) {
		$employee_id = ET_Security::verify_employee_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $employee_id ) ) return $employee_id;
		return rest_ensure_response( ET_LeaveBalances::compute( $employee_id ) );
	}

	/** GET /leave/calc — حساب أيام العمل الفعلية تلقائيًا (استثناء الجمعة/السبت) لفترة مُختارة، بدون إدخال يدوي */
	public function leave_calc_days( WP_REST_Request $request ) {
		$employee_id = ET_Security::verify_employee_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $employee_id ) ) return $employee_id;
		$days = ET_LeaveCalc::working_days( $request->get_param( 'start_date' ), $request->get_param( 'end_date' ) );
		if ( is_wp_error( $days ) ) return $days;
		return rest_ensure_response( array( 'days' => $days ) );
	}

	/** GET /leave/requests — طلبات الإجازة السنوية السابقة للموظف الحالي */
	public function leave_get_requests( WP_REST_Request $request ) {
		$employee_id = ET_Security::verify_employee_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $employee_id ) ) return $employee_id;
		$items = array_map( array( $this, 'fmt_leave_request' ), ET_LeaveRequests::list_for_employee( $employee_id ) );
		return rest_ensure_response( array( 'requests' => $items ) );
	}

	/** POST /leave/requests/create — تقديم طلب إجازة سنوية جديد */
	public function leave_create_request( WP_REST_Request $request ) {
		if ( ! ET_Security::rate_limit_ok( 'leavecreate_' . $this->client_ip(), 15, 60 ) ) {
			return new WP_Error( 'et_rate_limited', __( 'محاولات كثيرة جدًا، حاول لاحقًا.'), array( 'status' => 429 ) );
		}
		$employee_id = ET_Security::verify_employee_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $employee_id ) ) return $employee_id;

		$id = ET_LeaveRequests::create(
			$employee_id,
			$request->get_param( 'start_date' ),
			$request->get_param( 'end_date' ),
			$request->get_param( 'notes' )
		);
		if ( is_wp_error( $id ) ) return $id;

		$this->leave_notify_new_request( $employee_id, 'annual', ET_LeaveRequests::get( $id ) );

		return rest_ensure_response( array( 'success' => true, 'id' => $id ) );
	}

	/** POST /leave/requests/cancel — إلغاء طلب إجازة سنوية قيد المراجعة من الموظف نفسه */
	public function leave_cancel_request( WP_REST_Request $request ) {
		$employee_id = ET_Security::verify_employee_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $employee_id ) ) return $employee_id;
		$result = ET_LeaveRequests::cancel( (int) $request->get_param( 'id' ), $employee_id );
		if ( is_wp_error( $result ) ) return $result;
		return rest_ensure_response( array( 'success' => true ) );
	}

	/** GET /sick-leave/requests — سجل الإجازات المرضية للموظف الحالي */
	public function sick_get_requests( WP_REST_Request $request ) {
		$employee_id = ET_Security::verify_employee_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $employee_id ) ) return $employee_id;
		$items = array_map( array( $this, 'fmt_sick_request' ), ET_SickLeaveRequests::list_for_employee( $employee_id ) );
		return rest_ensure_response( array( 'requests' => $items ) );
	}

	/** POST /sick-leave/requests/create — تقديم إجازة مرضية (multipart لدعم إرفاق التقرير الطبي) */
	public function sick_create_request( WP_REST_Request $request ) {
		if ( ! ET_Security::rate_limit_ok( 'sickcreate_' . $this->client_ip(), 15, 60 ) ) {
			return new WP_Error( 'et_rate_limited', __( 'محاولات كثيرة جدًا، حاول لاحقًا.'), array( 'status' => 429 ) );
		}
		$employee_id = ET_Security::verify_employee_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $employee_id ) ) return $employee_id;

		$attachment_url  = '';
		$attachment_name = '';
		$files = $request->get_file_params();
		if ( ! empty( $files['attachment'] ) && ! empty( $files['attachment']['name'] ) ) {
			// تقرير طبي = بيانات صحية حساسة: يُحفظ بالمجلد الخاص المحمي ولا يُفتح إلا للموظف نفسه والإدارة
			$stored = ET_Files::store_upload( $files['attachment'] );
			if ( is_wp_error( $stored ) ) return $stored;
			$attachment_url  = $stored['url'];
			$attachment_name = $stored['name'];
		}

		$id = ET_SickLeaveRequests::create(
			$employee_id,
			$request->get_param( 'start_date' ),
			$request->get_param( 'end_date' ),
			$request->get_param( 'notes' ),
			$attachment_url,
			$attachment_name
		);
		if ( is_wp_error( $id ) ) return $id;

		$this->leave_notify_new_request( $employee_id, 'sick', ET_SickLeaveRequests::get( $id ) );

		return rest_ensure_response( array( 'success' => true, 'id' => $id ) );
	}

	/** GET /permission/requests — سجل طلبات الاستئذان للموظف الحالي */
	public function permission_get_requests( WP_REST_Request $request ) {
		$employee_id = ET_Security::verify_employee_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $employee_id ) ) return $employee_id;
		$items = array_map( array( $this, 'fmt_permission_request' ), ET_PermissionRequests::list_for_employee( $employee_id ) );
		return rest_ensure_response( array( 'requests' => $items ) );
	}

	/** POST /permission/requests/create — تقديم طلب استئذان جديد */
	public function permission_create_request( WP_REST_Request $request ) {
		if ( ! ET_Security::rate_limit_ok( 'permcreate_' . $this->client_ip(), 15, 60 ) ) {
			return new WP_Error( 'et_rate_limited', __( 'محاولات كثيرة جدًا، حاول لاحقًا.'), array( 'status' => 429 ) );
		}
		$employee_id = ET_Security::verify_employee_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $employee_id ) ) return $employee_id;

		$id = ET_PermissionRequests::create(
			$employee_id,
			$request->get_param( 'perm_date' ),
			$request->get_param( 'time_out' ),
			$request->get_param( 'time_return' ),
			$request->get_param( 'reason' ),
			$request->get_param( 'notes' )
		);
		if ( is_wp_error( $id ) ) return $id;

		$this->leave_notify_new_request( $employee_id, 'permission', ET_PermissionRequests::get( $id ) );

		return rest_ensure_response( array( 'success' => true, 'id' => $id ) );
	}

	/** إشعار داخلي + بريد فوري للإدارة عند تقديم أي طلب (إجازة سنوية/مرضية/استئذان) — نفس آلية submit_update تمامًا */
	private function leave_notify_new_request( $employee_id, $type, $row ) {
		$employee = ET_Employees::get( $employee_id );
		if ( ! $employee || ! $row ) return;

		$labels = array( 'annual' => 'طلب إجازة سنوية', 'sick' => 'طلب إجازة مرضية', 'permission' => 'طلب استئذان' );
		$label  = $labels[ $type ] ?? 'طلب';

		if ( $type === 'permission' ) {
			$period = 'بتاريخ ' . $row->perm_date . ' من ' . $row->time_out . ( ! empty( $row->time_return ) ? ( ' إلى ' . $row->time_return ) : '' );
		} else {
			$period = 'من ' . $row->start_date . ' إلى ' . $row->end_date . ' (' . $row->days_count . ' يوم)';
		}

		$message = "📥 {$label} جديد\nالموظف: {$employee->name}\n{$period}";
		if ( ! empty( $row->notes ) ) $message .= "\nملاحظات: " . $row->notes;

		ET_Notifications::create_for_admin( "📥 {$employee->name} قدّم {$label}" );
		ET_Background::queue( 'et_bg_email_admin', array( 'message' => $message, 'subject' => "إشعار: {$label} من {$employee->name}" ) );

		ET_AuditLog::log( "{$employee->name} قدّم {$label}" );
	}

	/** إشعار الموظف داخل النظام + بريده الشخصي (إن وُجد) عند تغيّر حالة أي طلب من طلباته */
	private function leave_notify_decision( $employee_id, $type, $status, $rejection_reason = '' ) {
		$employee = ET_Employees::get( $employee_id );
		$labels = array( 'annual' => 'طلب الإجازة السنوية', 'sick' => 'طلب الإجازة المرضية', 'permission' => 'طلب الاستئذان' );
		$label  = $labels[ $type ] ?? 'الطلب';

		if ( $status === 'approved' ) {
			$msg = "✅ تمت الموافقة على {$label}.";
		} else {
			$msg = "❌ تم رفض {$label}." . ( $rejection_reason ? ' السبب: ' . $rejection_reason : '' );
		}
		ET_Notifications::create_for_employee( $employee_id, $msg );

		if ( $employee && ! empty( $employee->email ) ) {
			ET_Background::queue( 'et_bg_email', array( 'to' => $employee->email, 'subject' => $label . ' — ' . $this->leave_status_label( $status ), 'message' => $msg ) );
		}
	}

	/** GET /admin/leave/requests — كل طلبات الإجازة السنوية (فلترة اختيارية بالحالة/الموظف)، يتطلب جلسة إدارة */
	public function admin_leave_get_requests( WP_REST_Request $request ) {
		$auth = ET_Security::verify_admin_session_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $auth ) ) return $auth;
		$items = array_map( array( $this, 'fmt_leave_request' ), ET_LeaveRequests::list_all(
			sanitize_text_field( (string) $request->get_param( 'status' ) ),
			(int) $request->get_param( 'employee_id' )
		) );
		return rest_ensure_response( array( 'requests' => $items ) );
	}

	/** POST /admin/leave/requests/decide — موافقة/رفض طلب إجازة سنوية */
	public function admin_leave_decide( WP_REST_Request $request ) {
		$auth = ET_Security::verify_admin_session_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $auth ) ) return $auth;

		$id     = (int) $request->get_param( 'id' );
		$status = sanitize_text_field( (string) $request->get_param( 'status' ) );
		$req    = ET_LeaveRequests::get( $id );

		$result = ET_LeaveRequests::decide( $id, $status, $request->get_param( 'admin_notes' ), $request->get_param( 'rejection_reason' ) );
		if ( is_wp_error( $result ) ) return $result;

		if ( $req ) {
			$this->leave_notify_decision( $req->employee_id, 'annual', $status, $request->get_param( 'rejection_reason' ) );
			$employee = ET_Employees::get( $req->employee_id );
			ET_AuditLog::log( ( $employee ? $employee->name : '' ) . ' — طلب إجازة سنوية: ' . $this->leave_status_label( $status ) );
		}

		return rest_ensure_response( array( 'success' => true ) );
	}

	/** POST /admin/leave/requests/add-past — تسجيل إجازة سابقة (قبل استخدام النظام) من الإدارة مباشرة */
	public function admin_leave_add_past( WP_REST_Request $request ) {
		$auth = ET_Security::verify_admin_session_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $auth ) ) return $auth;

		$employee_id = (int) $request->get_param( 'employee_id' );
		$id = ET_LeaveRequests::add_past(
			$employee_id,
			$request->get_param( 'start_date' ),
			$request->get_param( 'end_date' ),
			$request->get_param( 'notes' )
		);
		if ( is_wp_error( $id ) ) return $id;

		$employee = ET_Employees::get( $employee_id );
		ET_AuditLog::log( ( $employee ? $employee->name : '' ) . ' — تسجيل إجازة سابقة من الإدارة' );

		return rest_ensure_response( array( 'success' => true, 'id' => $id ) );
	}

	/** GET /admin/sick-leave/requests */
	public function admin_sick_get_requests( WP_REST_Request $request ) {
		$auth = ET_Security::verify_admin_session_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $auth ) ) return $auth;
		$items = array_map( array( $this, 'fmt_sick_request' ), ET_SickLeaveRequests::list_all(
			sanitize_text_field( (string) $request->get_param( 'status' ) ),
			(int) $request->get_param( 'employee_id' )
		) );
		return rest_ensure_response( array( 'requests' => $items ) );
	}

	/** POST /admin/sick-leave/requests/decide */
	public function admin_sick_decide( WP_REST_Request $request ) {
		$auth = ET_Security::verify_admin_session_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $auth ) ) return $auth;

		$id     = (int) $request->get_param( 'id' );
		$status = sanitize_text_field( (string) $request->get_param( 'status' ) );
		$req    = ET_SickLeaveRequests::get( $id );

		$result = ET_SickLeaveRequests::decide( $id, $status, $request->get_param( 'admin_notes' ), $request->get_param( 'rejection_reason' ) );
		if ( is_wp_error( $result ) ) return $result;

		if ( $req ) {
			$this->leave_notify_decision( $req->employee_id, 'sick', $status, $request->get_param( 'rejection_reason' ) );
			$employee = ET_Employees::get( $req->employee_id );
			ET_AuditLog::log( ( $employee ? $employee->name : '' ) . ' — طلب إجازة مرضية: ' . $this->leave_status_label( $status ) );
		}

		return rest_ensure_response( array( 'success' => true ) );
	}

	/** GET /admin/permission/requests */
	public function admin_permission_get_requests( WP_REST_Request $request ) {
		$auth = ET_Security::verify_admin_session_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $auth ) ) return $auth;
		$items = array_map( array( $this, 'fmt_permission_request' ), ET_PermissionRequests::list_all(
			sanitize_text_field( (string) $request->get_param( 'status' ) ),
			(int) $request->get_param( 'employee_id' )
		) );
		return rest_ensure_response( array( 'requests' => $items ) );
	}

	/** POST /admin/permission/requests/decide */
	public function admin_permission_decide( WP_REST_Request $request ) {
		$auth = ET_Security::verify_admin_session_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $auth ) ) return $auth;

		$id     = (int) $request->get_param( 'id' );
		$status = sanitize_text_field( (string) $request->get_param( 'status' ) );
		$req    = ET_PermissionRequests::get( $id );

		$result = ET_PermissionRequests::decide( $id, $status, $request->get_param( 'admin_notes' ), $request->get_param( 'rejection_reason' ) );
		if ( is_wp_error( $result ) ) return $result;

		if ( $req ) {
			$this->leave_notify_decision( $req->employee_id, 'permission', $status, $request->get_param( 'rejection_reason' ) );
			$employee = ET_Employees::get( $req->employee_id );
			ET_AuditLog::log( ( $employee ? $employee->name : '' ) . ' — طلب استئذان: ' . $this->leave_status_label( $status ) );
		}

		return rest_ensure_response( array( 'success' => true ) );
	}

	/** GET /admin/leave/balances — أرصدة كل الموظفين النشطين، للوحة إدارة الأرصدة */
	public function admin_leave_get_balances( WP_REST_Request $request ) {
		$auth = ET_Security::verify_admin_session_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $auth ) ) return $auth;

		$employees = ET_Employees::active_for_picker();
		$items = array_map( function ( $e ) {
			$b = ET_LeaveBalances::compute( $e->id );
			$b['employee_name']     = $e->name;
			$b['department_name']   = $e->department_name;
			return $b;
		}, $employees );

		return rest_ensure_response( array(
			'balances' => $items,
			'settings' => array(
				'annual_base_days' => (float) ET_Settings::get()['leave_annual_base_days'],
				'monthly_accrual'  => (float) ET_Settings::get()['leave_monthly_accrual'],
			),
		) );
	}

	/** POST /admin/leave/balances/adjust — تعديل تاريخ استحقاق موظف و/أو رصيد يدوي إضافي */
	public function admin_leave_adjust_balance( WP_REST_Request $request ) {
		$auth = ET_Security::verify_admin_session_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $auth ) ) return $auth;

		$employee_id = (int) $request->get_param( 'employee_id' );
		if ( ! ET_Employees::get( $employee_id ) ) {
			return new WP_Error( 'et_not_found', __( 'الموظف غير موجود.'), array( 'status' => 404 ) );
		}

		ET_LeaveBalances::set(
			$employee_id,
			$request->get_param( 'accrual_start_date' ),
			$request->get_param( 'manual_adjustment_days' )
		);

		return rest_ensure_response( array( 'success' => true, 'balance' => ET_LeaveBalances::compute( $employee_id ) ) );
	}

	/** POST /admin/leave/settings — تعديل الرصيد السنوي الأساسي والتراكم الشهري من الإدارة (بدون Hard-coding) */
	public function admin_leave_update_settings( WP_REST_Request $request ) {
		$auth = ET_Security::verify_admin_session_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $auth ) ) return $auth;

		$new = array();
		$base = $request->get_param( 'annual_base_days' );
		$rate = $request->get_param( 'monthly_accrual' );
		if ( $base !== null && $base !== '' ) $new['leave_annual_base_days'] = max( 0, (float) $base );
		if ( $rate !== null && $rate !== '' ) $new['leave_monthly_accrual'] = max( 0, (float) $rate );

		ET_Settings::update( $new );

		return rest_ensure_response( array( 'success' => true, 'settings' => ET_Settings::get() ) );
	}

	/* ===================================================================
	   نظام "الحضور والانصراف والتحضير" — Handlers
	   =================================================================== */

	/** يحدد المدير الحالي لتسجيله في سجل التدقيق: صاحب جلسة الإدارة (حسابه الشخصي)، وإلا تسمية عامة */
	private function attendance_admin_identity( WP_REST_Request $request = null ) {
		if ( $request ) {
			$token = ET_Security::request_token( $request );
			$name  = ET_Security::admin_name_from_token( $token );
			if ( $name !== '' ) {
				list( $payload_b64 ) = explode( '.', $token, 2 );
				$payload = json_decode( base64_decode( strtr( $payload_b64, '-_', '+/' ) ), true );
				return array( $name, (int) ( $payload['uid'] ?? 0 ) );
			}
		}
		$uid = get_current_user_id();
		if ( $uid > 0 ) {
			$user = get_userdata( $uid );
			return array( $user ? $user->display_name : 'مدير النظام', $uid );
		}
		return array( 'مدير النظام', 0 );
	}

	/** GET /attendance/settings — فترة دوام الموظف الحالي الفعلية (حسب حقل "الفترة": صباحي/مسائي/كلاهما) */
	public function attendance_get_settings( WP_REST_Request $request ) {
		$employee_id = ET_Security::verify_employee_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $employee_id ) ) return $employee_id;

		$s = ET_Attendance::settings();
		$schedule = ET_Attendance::schedule_for_employee( ET_Employees::get( $employee_id ) );
		return rest_ensure_response( array(
			'work_start'    => $schedule['start'],
			'work_end'      => $schedule['end'],
			'grace_minutes' => $s['grace_minutes'],
			'work_hours'    => $s['work_hours'],
			'radius_meters' => $s['radius_meters'],
			'working_days'  => $s['working_days'],
			'location_set'  => $s['location_lat'] !== null && $s['location_lng'] !== null,
		) );
	}

	/** GET /attendance/today — حالة تحضير اليوم للموظف الحالي */
	public function attendance_get_today( WP_REST_Request $request ) {
		$employee_id = ET_Security::verify_employee_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $employee_id ) ) return $employee_id;
		return rest_ensure_response( ET_Attendance::day_status( $employee_id, current_time( 'Y-m-d' ) ) );
	}

	/** POST /attendance/check-in */
	public function attendance_check_in( WP_REST_Request $request ) {
		if ( ! ET_Security::rate_limit_ok( 'attin_' . $this->client_ip(), 20, 60 ) ) {
			return new WP_Error( 'et_rate_limited', __( 'محاولات كثيرة جدًا، حاول لاحقًا.'), array( 'status' => 429 ) );
		}
		$employee_id = ET_Security::verify_employee_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $employee_id ) ) return $employee_id;

		$result = ET_Attendance::check_in( $employee_id, $request->get_param( 'lat' ), $request->get_param( 'lng' ), $request->get_param( 'accuracy' ) );
		if ( is_wp_error( $result ) ) return $result;

		return rest_ensure_response( array( 'success' => true, 'status' => ET_Attendance::day_status( $employee_id, current_time( 'Y-m-d' ) ) ) );
	}

	/** POST /attendance/check-out */
	public function attendance_check_out( WP_REST_Request $request ) {
		if ( ! ET_Security::rate_limit_ok( 'attout_' . $this->client_ip(), 20, 60 ) ) {
			return new WP_Error( 'et_rate_limited', __( 'محاولات كثيرة جدًا، حاول لاحقًا.'), array( 'status' => 429 ) );
		}
		$employee_id = ET_Security::verify_employee_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $employee_id ) ) return $employee_id;

		$result = ET_Attendance::check_out( $employee_id, $request->get_param( 'lat' ), $request->get_param( 'lng' ), $request->get_param( 'accuracy' ) );
		if ( is_wp_error( $result ) ) return $result;

		return rest_ensure_response( array( 'success' => true, 'status' => ET_Attendance::day_status( $employee_id, current_time( 'Y-m-d' ) ) ) );
	}

	/** GET /attendance/history — سجل حضور الموظف الحالي نفسه فقط */
	public function attendance_get_history( WP_REST_Request $request ) {
		$employee_id = ET_Security::verify_employee_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $employee_id ) ) return $employee_id;
		$to   = $request->get_param( 'date_to' ) ?: current_time( 'Y-m-d' );
		$from = $request->get_param( 'date_from' ) ?: date( 'Y-m-d', strtotime( $to . ' -29 days' ) );
		$rows = ET_Attendance::employee_history( $employee_id, $from, $to );
		if ( is_wp_error( $rows ) ) return $rows;
		return rest_ensure_response( array( 'items' => $rows ) );
	}

	/** GET /admin/attendance/day — تحضير كل الموظفين ليوم مُحدد (اليوم افتراضيًا) + إحصائيات */
	public function admin_attendance_get_day( WP_REST_Request $request ) {
		$auth = ET_Security::verify_admin_session_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $auth ) ) return $auth;
		$date = $request->get_param( 'date' ) ?: current_time( 'Y-m-d' );
		return rest_ensure_response( ET_Attendance::day_overview( $date ) );
	}

	/** GET /admin/attendance/settings */
	public function admin_attendance_get_settings( WP_REST_Request $request ) {
		$auth = ET_Security::verify_admin_session_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $auth ) ) return $auth;
		return rest_ensure_response( ET_Attendance::settings() );
	}

	/** POST /admin/attendance/settings/update */
	public function admin_attendance_update_settings( WP_REST_Request $request ) {
		$auth = ET_Security::verify_admin_session_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $auth ) ) return $auth;

		$working_days = $request->get_param( 'working_days' );
		if ( is_string( $working_days ) ) {
			$decoded = json_decode( $working_days, true );
			$working_days = is_array( $decoded ) ? $decoded : array_filter( array_map( 'trim', explode( ',', $working_days ) ) );
		}

		$settings = ET_Attendance::update_settings( array(
			'morning_start' => $request->get_param( 'morning_start' ),
			'morning_end'   => $request->get_param( 'morning_end' ),
			'evening_start' => $request->get_param( 'evening_start' ),
			'evening_end'   => $request->get_param( 'evening_end' ),
			'grace_minutes' => $request->get_param( 'grace_minutes' ),
			'work_hours'    => $request->get_param( 'work_hours' ),
			'location_lat'  => $request->get_param( 'location_lat' ),
			'location_lng'  => $request->get_param( 'location_lng' ),
			'radius_meters' => $request->get_param( 'radius_meters' ),
			'working_days'  => $working_days,
			'allowed_ips'   => $request->get_param( 'allowed_ips' ),
		) );
		if ( is_wp_error( $settings ) ) return $settings;

		ET_AuditLog::log( 'تم تحديث إعدادات الدوام من الإدارة.' );

		return rest_ensure_response( array( 'success' => true, 'settings' => $settings ) );
	}

	/** GET /admin/attendance/employee-history — سجل حضور موظف مُحدد ضمن نطاق تاريخ */
	public function admin_attendance_employee_history( WP_REST_Request $request ) {
		$auth = ET_Security::verify_admin_session_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $auth ) ) return $auth;

		$employee_id = (int) $request->get_param( 'employee_id' );
		if ( ! $employee_id || ! ET_Employees::get( $employee_id ) ) {
			return new WP_Error( 'et_not_found', __( 'الموظف غير موجود.'), array( 'status' => 404 ) );
		}
		$to   = $request->get_param( 'date_to' ) ?: current_time( 'Y-m-d' );
		$from = $request->get_param( 'date_from' ) ?: date( 'Y-m-d', strtotime( $to . ' -29 days' ) );
		$rows = ET_Attendance::employee_history( $employee_id, $from, $to );
		if ( is_wp_error( $rows ) ) return $rows;
		return rest_ensure_response( array( 'items' => $rows ) );
	}

	/** POST /admin/attendance/manual-edit — تعديل يدوي، السبب إجباري، ويُسجَّل دائمًا في Audit Log منفصل */
	public function admin_attendance_manual_edit( WP_REST_Request $request ) {
		$auth = ET_Security::verify_admin_session_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $auth ) ) return $auth;

		list( $admin_label, $admin_user_id ) = $this->attendance_admin_identity( $request );

		$result = ET_Attendance::manual_edit(
			(int) $request->get_param( 'employee_id' ),
			sanitize_text_field( (string) $request->get_param( 'date' ) ),
			sanitize_text_field( (string) $request->get_param( 'status' ) ),
			sanitize_text_field( (string) $request->get_param( 'check_in' ) ),
			sanitize_text_field( (string) $request->get_param( 'check_out' ) ),
			$request->get_param( 'reason' ),
			$request->get_param( 'notes' ),
			$admin_label,
			$admin_user_id
		);
		if ( is_wp_error( $result ) ) return $result;

		$employee = ET_Employees::get( (int) $request->get_param( 'employee_id' ) );
		if ( $employee ) {
			ET_Notifications::create_for_employee( $employee->id, sprintf( '✏️ تم تعديل سجل حضورك بتاريخ %s يدويًا من الإدارة.', $request->get_param( 'date' ) ) );
		}

		return rest_ensure_response( array( 'success' => true, 'status' => $result ) );
	}

	/** GET /admin/attendance/audit-logs */
	public function admin_attendance_get_audit( WP_REST_Request $request ) {
		$auth = ET_Security::verify_admin_session_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $auth ) ) return $auth;
		$rows = ET_Attendance::audit_logs(
			(int) $request->get_param( 'employee_id' ),
			sanitize_text_field( (string) $request->get_param( 'date_from' ) ),
			sanitize_text_field( (string) $request->get_param( 'date_to' ) )
		);
		return rest_ensure_response( array( 'items' => $rows ) );
	}

	/** GET /admin/attendance/report — تقرير مُجمّع (حضور/تأخير/غياب/ساعات عمل) ضمن نطاق تاريخ */
	public function admin_attendance_get_report( WP_REST_Request $request ) {
		$auth = ET_Security::verify_admin_session_token( ET_Security::request_token( $request ) );
		if ( is_wp_error( $auth ) ) return $auth;
		$to   = $request->get_param( 'date_to' ) ?: current_time( 'Y-m-d' );
		$from = $request->get_param( 'date_from' ) ?: date( 'Y-m-d', strtotime( $to . ' -29 days' ) );
		$rows = ET_Attendance::report_rows( $from, $to, (int) $request->get_param( 'employee_id' ) );
		return rest_ensure_response( array( 'items' => $rows, 'date_from' => $from, 'date_to' => $to ) );
	}

	/** GET /admin/attendance/report/export — نفس بيانات التقرير أعلاه، لكن كملف CSV يفتح مباشرة في Excel */
	public function admin_attendance_export_report( WP_REST_Request $request ) {
		// تنزيل ملف عبر رابط عادي (لا يمكن إرسال هيدر): نقبل كوكي جلسة الإدارة. طلب قراءة فقط (GET)،
		// فلا خطر CSRF هنا — موقع خارجي لا يستطيع قراءة الملف الناتج.
		$token = ET_Security::request_token( $request );
		if ( $token === '' && ! empty( $_COOKIE[ ET_Security::ADMIN_COOKIE ] ) ) {
			$token = (string) wp_unslash( $_COOKIE[ ET_Security::ADMIN_COOKIE ] );
		}
		$auth = ET_Security::verify_admin_session_token( $token );
		if ( is_wp_error( $auth ) ) return $auth;
		$to   = $request->get_param( 'date_to' ) ?: current_time( 'Y-m-d' );
		$from = $request->get_param( 'date_from' ) ?: date( 'Y-m-d', strtotime( $to . ' -29 days' ) );
		$rows = ET_Attendance::report_rows( $from, $to, (int) $request->get_param( 'employee_id' ) );
		ET_Export::attendance_csv( $rows, $from, $to ); // ينهي التنفيذ (exit) بعد إرسال ملف CSV
	}







}
