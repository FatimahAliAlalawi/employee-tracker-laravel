<?php

/*
 * منقول من بلجن ووردبريس employee-tracker 2.9.1 — منطق العمل كما هو،
 * يعمل فوق Laravel عبر app/Support/helpers.php (لا اعتماد على ووردبريس).
 */

class ET_Admin {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function hooks() {
		// القائمة والمسارات في routes/web.php؛ التصدير يُستدعى من AdminController.
	}

	/** يلتقط طلب تصدير Excel/CSV قبل أي إخراج HTML وينهي التنفيذ مباشرة */
	public function handle_export() {
		if ( empty( $_GET['page'] ) || $_GET['page'] !== 'et-reports' ) return;
		if ( empty( $_GET['et_export'] ) || ! in_array( $_GET['et_export'], array( 'updates', 'problems', 'weekly_task_report' ), true ) ) return;
		if ( ! current_user_can( 'manage_options' ) ) return;

		check_admin_referer( 'et_export_' . $_GET['et_export'] );

		if ( $_GET['et_export'] === 'weekly_task_report' ) {
			$date_from = sanitize_text_field( $_GET['report_date_from'] ?? '' );
			$date_to   = sanitize_text_field( $_GET['report_date_to'] ?? '' );
			if ( empty( $date_from ) ) $date_from = ET_WeeklyTasks::current_week_start();
			if ( empty( $date_to ) )   $date_to   = $date_from;
			$employee_id = isset( $_GET['report_employee_id'] ) ? (int) $_GET['report_employee_id'] : 0;
			ET_Export::weekly_task_report_csv( $date_from, $date_to, $employee_id );
			return;
		}

		$args = array(
			'employee_id'   => isset( $_GET['employee_id'] ) ? (int) $_GET['employee_id'] : 0,
			'department_id' => isset( $_GET['department_id'] ) ? (int) $_GET['department_id'] : 0,
			'date_from'     => isset( $_GET['date_from'] ) ? sanitize_text_field( $_GET['date_from'] ) : '',
			'date_to'       => isset( $_GET['date_to'] ) ? sanitize_text_field( $_GET['date_to'] ) : '',
		);

		if ( $_GET['et_export'] === 'updates' ) {
			ET_Export::updates_csv( $args );
		} else {
			ET_Export::problems_csv( $args );
		}
	}


	private function guard() {
		if ( ! ET_Security::current_user_can_manage() ) {
			wp_die( __( 'ليس لديك صلاحية للوصول إلى هذه الصفحة.') );
		}
	}


	/* ---------------------------------------------------------------
	   أدوات مساعدة للعرض
	--------------------------------------------------------------- */
	private function badge_priority( $priority ) {
		$colors = array( 'low' => '#6B7684', 'medium' => '#4E42B8', 'high' => '#E2A93B', 'critical' => '#D6483F' );
		$color = $colors[ $priority ] ?? '#6B7684';
		return '<span class="et-pill" style="background:' . esc_attr( $color ) . '1A;color:' . esc_attr( $color ) . ';border-color:' . esc_attr( $color ) . '40;">' . esc_html( ET_Problems::priority_label( $priority ) ) . '</span>';
	}

	private function badge_task_priority( $priority ) {
		$colors = array( 'low' => '#6B7684', 'medium' => '#4E42B8', 'high' => '#E2A93B', 'critical' => '#D6483F' );
		$color  = $colors[ $priority ] ?? '#6B7684';
		return '<span class="et-pill" style="background:' . esc_attr( $color ) . '1A;color:' . esc_attr( $color ) . ';border-color:' . esc_attr( $color ) . '40;">' . esc_html( ET_WeeklyTasks::priority_label( $priority ) ) . '</span>';
	}

	private function badge_status( $status ) {
		$colors = array( 'new' => '#4E42B8', 'in_progress' => '#6488C9', 'waiting_action' => '#E2A93B', 'resolved' => '#1F9D7C', 'closed' => '#6B7684' );
		$color = $colors[ $status ] ?? '#6B7684';
		return '<span class="et-pill" style="background:' . esc_attr( $color ) . '1A;color:' . esc_attr( $color ) . ';border-color:' . esc_attr( $color ) . '40;">' . esc_html( ET_Problems::status_label( $status ) ) . '</span>';
	}

	private function pagination( $current_page, $total_pages, $base_url ) {
		if ( $total_pages <= 1 ) return;
		echo '<div class="et-pagination">';
		for ( $i = 1; $i <= $total_pages; $i++ ) {
			$url = add_query_arg( 'paged', $i, $base_url );
			$class = ( $i === $current_page ) ? 'et-page-link et-page-link--active' : 'et-page-link';
			echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '">' . intval( $i ) . '</a>';
		}
		echo '</div>';
	}

	private function cards( $items ) {
		echo '<div class="et-cards">';
		foreach ( $items as $item ) {
			echo '<div class="et-card-stat">';
			echo '<div class="et-card-stat__value" style="color:' . esc_attr( $item['color'] ?? '#3C3191' ) . '">' . esc_html( $item['value'] ) . '</div>';
			echo '<div class="et-card-stat__label">' . esc_html( $item['label'] ) . '</div>';
			echo '</div>';
		}
		echo '</div>';
	}

	/* ---------------------------------------------------------------
	   لوحة المتابعة (Dashboard)
	--------------------------------------------------------------- */
	public function page_dashboard() {
		$this->guard();

		$active_employees = ET_Employees::count_active();
		$submitted_today  = ET_Updates::count_today();
		$updates_today    = ET_Updates::count_updates_today();
		$not_submitted    = max( 0, $active_employees - $submitted_today );
		$open_problems    = ET_Problems::count_open();
		$critical_open    = ET_Problems::count_critical_open();
		$compliance       = $active_employees > 0 ? round( ( $submitted_today / $active_employees ) * 100 ) : 0;

		echo '<div class="wrap et-wrap">';
		echo '<h1 class="et-h1">لوحة متابعة العمل</h1>';
		echo '<p class="et-sub">نظرة سريعة على أداء اليوم داخل المؤسسة</p>';

		$this->cards( array(
			array( 'label' => 'الموظفون النشطون', 'value' => $active_employees ),
			array( 'label' => 'أرسلوا اليوم', 'value' => $submitted_today, 'color' => '#1F9D7C' ),
			array( 'label' => 'لم يرسلوا', 'value' => $not_submitted, 'color' => '#D6483F' ),
			array( 'label' => 'تحديثات اليوم', 'value' => $updates_today ),
			array( 'label' => 'المشاكل المفتوحة', 'value' => $open_problems, 'color' => '#6488C9' ),
			array( 'label' => 'المشاكل الحرجة', 'value' => $critical_open, 'color' => '#D6483F' ),
		) );

		echo '<div class="et-panel">';
		echo '<h2>نسبة إرسال التحديثات اليوم</h2>';
		echo '<div class="et-progress"><div class="et-progress__bar" style="width:' . intval( $compliance ) . '%"></div></div>';
		echo '<div style="font-family:Cairo,sans-serif;font-weight:700;color:#3C3191;">' . intval( $compliance ) . '%</div>';
		echo '</div>';

		$not_list = ET_Updates::not_submitted_today();
		echo '<div class="et-panel">';
		echo '<h2>لم يرسلوا تحديث اليوم (' . count( $not_list ) . ')</h2>';
		if ( empty( $not_list ) ) {
			echo '<div class="et-empty">🎉 جميع الموظفين النشطين أرسلوا تحديثهم اليوم.</div>';
		} else {
			echo '<table class="et-table"><thead><tr><th>الموظف</th><th>القسم</th><th>آخر تحديث</th></tr></thead><tbody>';
			foreach ( $not_list as $row ) {
				$last = $row->last_update_date ? esc_html( $row->last_update_date ) : '—';
				echo '<tr><td>' . esc_html( $row->name ) . '</td><td>' . esc_html( $row->department_name ?: '—' ) . '</td><td>' . $last . '</td></tr>';
			}
			echo '</tbody></table>';
			echo '<p class="et-warn" style="margin-top:12px;">الإشعارات التلقائية عبر Email وWhatsApp ستتوفر ضمن المرحلة القادمة من النظام.</p>';
		}
		echo '</div>';

		$recent = ET_Updates::query( array( 'per_page' => 8, 'page' => 1 ) );
		echo '<div class="et-panel">';
		echo '<h2>آخر التحديثات</h2>';
		if ( empty( $recent['items'] ) ) {
			echo '<div class="et-empty">لا توجد تحديثات بعد.</div>';
		} else {
			echo '<table class="et-table"><thead><tr><th>الموظف</th><th>القسم</th><th>الإنجاز</th><th>المشكلة</th><th>التاريخ</th></tr></thead><tbody>';
			foreach ( $recent['items'] as $u ) {
				echo '<tr>';
				echo '<td>' . esc_html( $u->employee_name ) . '</td>';
				echo '<td>' . esc_html( $u->department_name ?: '—' ) . '</td>';
				echo '<td>' . esc_html( mb_substr( $u->achievements, 0, 60 ) ) . '</td>';
				echo '<td>' . ( $u->has_problem ? $this->badge_priority( $u->problem_priority ) : '—' ) . '</td>';
				echo '<td>' . esc_html( $u->update_date ) . '</td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
			echo '<p style="margin-top:12px;"><a href="' . esc_url( admin_url( 'admin.php?page=et-updates' ) ) . '">عرض كل التحديثات ‹</a></p>';
		}
		echo '</div>';

		$recurring = ET_Problems::most_recurring( 5 );
		if ( ! empty( $recurring ) ) {
			echo '<div class="et-panel">';
			echo '<h2>أكثر المشاكل تكرارًا</h2>';
			echo '<ol style="margin:0;padding-inline-start:20px;font-size:13px;">';
			foreach ( $recurring as $r ) {
				echo '<li style="margin-bottom:6px;">' . esc_html( $r->title ) . ' — <strong>' . intval( $r->occurrences ) . '</strong> مرة</li>';
			}
			echo '</ol></div>';
		}

		echo '</div>';
	}

	/* ---------------------------------------------------------------
	   الموظفون
	--------------------------------------------------------------- */
	public function page_employees() {
		$this->guard();
		$notice = '';

		if ( ! empty( $_POST['et_action'] ) && check_admin_referer( 'et_admin_action', 'et_nonce' ) ) {
			$action = sanitize_text_field( $_POST['et_action'] );
			if ( $action === 'save_employee' ) {
				$data = array(
					'name'          => sanitize_text_field( $_POST['name'] ?? '' ),
					'department_id' => intval( $_POST['department_id'] ?? 0 ),
					'manager_name'  => sanitize_text_field( $_POST['manager_name'] ?? '' ),
					'phone'         => sanitize_text_field( $_POST['phone'] ?? '' ),
					'email'         => sanitize_email( $_POST['email'] ?? '' ),
					'shift'         => sanitize_text_field( $_POST['shift'] ?? 'both' ),
					'access_code'   => sanitize_text_field( $_POST['access_code'] ?? '' ),
					'status'        => sanitize_text_field( $_POST['status'] ?? 'active' ),
					'att_morning_start_override' => sanitize_text_field( $_POST['att_morning_start_override'] ?? '' ),
					'att_morning_end_override'   => sanitize_text_field( $_POST['att_morning_end_override'] ?? '' ),
					'att_evening_start_override' => sanitize_text_field( $_POST['att_evening_start_override'] ?? '' ),
					'att_evening_end_override'   => sanitize_text_field( $_POST['att_evening_end_override'] ?? '' ),
				);
				$clean_code = ET_Employees::sanitize_code( $data['access_code'] );
				$id         = intval( $_POST['employee_id'] ?? 0 );
				if ( ! empty( $_POST['generate_code'] ) ) {
					$clean_code = ET_Employees::generate_code();
					$data['access_code'] = $clean_code;
				}
				$existing_emp = $id > 0 ? ET_Employees::get( $id ) : null;
				if ( $data['name'] === '' ) {
					$notice = '<div class="notice notice-error"><p>اسم الموظف مطلوب.</p></div>';
				} elseif ( $data['email'] === '' ) {
					$notice = '<div class="notice notice-error"><p>البريد الإلكتروني مطلوب — الموظف يدخل النظام ببريده وكلمة مروره.</p></div>';
				} elseif ( ET_Employees::email_taken( $data['email'], $id ) ) {
					$notice = '<div class="notice notice-error"><p>البريد <b>' . esc_html( $data['email'] ) . '</b> مستخدم لموظف آخر — لكل موظف بريد خاص به.</p></div>';
				} elseif ( $clean_code === '' && ! ( $existing_emp && $existing_emp->access_code ) ) {
					$notice = '<div class="notice notice-error"><p>كلمة المرور مطلوبة — اكتب كلمة مرور للموظف أو اختر "توليد كلمة مرور عشوائية".</p></div>';
				} elseif ( $clean_code !== '' && mb_strlen( $clean_code ) < ET_Employees::MIN_CODE_LENGTH ) {
					$notice = '<div class="notice notice-error"><p>كلمة المرور قصيرة — ' . (int) ET_Employees::MIN_CODE_LENGTH . ' أحرف على الأقل.</p></div>';
				} else {
					if ( $id > 0 ) {
						ET_Employees::update( $id, $data );
						$notice = '<div class="notice notice-success"><p>تم تحديث بيانات الموظف.</p></div>';
					} else {
						ET_Employees::create( $data );
						$notice = '<div class="notice notice-success"><p>تم إضافة الموظف بنجاح.</p></div>';
					}
					if ( $clean_code !== '' ) {
						// كلمة المرور تُحفظ مشفّرة ولا يمكن عرضها لاحقًا — نعرضها هنا مرة واحدة فقط
						$notice .= '<div class="notice notice-warning"><p>🔑 بيانات دخول <b>' . esc_html( $data['name'] ) . '</b>: البريد <code dir="ltr">' . esc_html( $data['email'] ) . '</code> — كلمة المرور <code dir="ltr" style="font-size:15px;letter-spacing:1px;">' . esc_html( $clean_code ) . '</code><br>انسخها وسلّمها للموظف الآن؛ لن تظهر مرة أخرى (تُحفظ مشفّرة).</p></div>';
						if ( ! empty( $_POST['send_credentials'] ) ) {
							$s_name = ET_Settings::get()['system_name'] ?: 'متابعة';
							$mail   = ET_EmailNotify::send(
								$data['email'],
								'بيانات دخولك إلى ' . $s_name,
								'مرحبًا ' . $data['name'] . "،\n\nهذه بيانات دخولك إلى نظام " . $s_name . ":\n\n"
									. 'الرابط: ' . home_url( '/' ) . "\n"
									. 'البريد الإلكتروني: ' . $data['email'] . "\n"
									. 'كلمة المرور: ' . $clean_code . "\n\n"
									. 'ننصحك بتغيير كلمة المرور بعد أول دخول من قائمة "🔑 كلمة المرور".',
								true
							);
							$notice .= ! empty( $mail['ok'] )
								? '<div class="notice notice-success"><p>✉️ أُرسلت بيانات الدخول إلى ' . esc_html( $data['email'] ) . '.</p></div>'
								: '<div class="notice notice-error"><p>تعذّر إرسال بيانات الدخول بالبريد: ' . esc_html( $mail['error'] ?? '' ) . '</p></div>';
						}
					}
				}
			} elseif ( $action === 'deactivate_employee' ) {
				ET_Employees::deactivate( intval( $_POST['employee_id'] ?? 0 ) );
				$notice = '<div class="notice notice-success"><p>تم تعطيل الموظف.</p></div>';
			}
		}

		$args = array(
			'search'        => sanitize_text_field( $_GET['s'] ?? '' ),
			'department_id' => intval( $_GET['department_id'] ?? 0 ),
			'status'        => sanitize_text_field( $_GET['status'] ?? '' ),
			'page'          => max( 1, intval( $_GET['paged'] ?? 1 ) ),
			'per_page'      => 20,
		);
		$result = ET_Employees::query( $args );
		$departments = ET_Departments::all();
		$base_url = remove_query_arg( 'paged' );

		$edit_employee = null;
		if ( ! empty( $_GET['edit'] ) ) {
			$edit_employee = ET_Employees::get( intval( $_GET['edit'] ) );
		}

		echo '<div class="wrap et-wrap">';
		echo $notice;
		$weak_count = count( (array) get_option( 'et_weak_code_employees', array() ) );
		if ( $weak_count > 0 ) {
			echo '<div class="notice notice-warning"><p>⚠️ يوجد <b>' . (int) $weak_count . '</b> موظف بكلمة مرور قصيرة (رمز قديم من نسخة ووردبريس) يسهل تخمينها. افتح "تعديل" لكل واحد منهم وحدد كلمة مرور جديدة أو ولّد واحدة عشوائية.</p></div>';
		}
		$no_email = ET_Employees::count_active_without_email();
		if ( $no_email > 0 ) {
			echo '<div class="notice notice-error"><p>📧 يوجد <b>' . (int) $no_email . '</b> موظف نشط بدون بريد إلكتروني — لا يستطيع الدخول حتى تضيف بريده من "تعديل".</p></div>';
		}
		echo '<h1 class="et-h1">الموظفون</h1>';
		echo '<p class="et-sub">كل موظف يدخل النظام ببريده الإلكتروني وكلمة المرور التي تحددها له هنا.</p>';

		// نموذج الإضافة/التعديل
		echo '<div class="et-panel">';
		echo '<h2>' . ( $edit_employee ? 'تعديل موظف' : 'إضافة موظف جديد' ) . '</h2>';
		echo '<form method="post">';
		wp_nonce_field( 'et_admin_action', 'et_nonce' );
		echo '<input type="hidden" name="et_action" value="save_employee" />';
		if ( $edit_employee ) echo '<input type="hidden" name="employee_id" value="' . intval( $edit_employee->id ) . '" />';
		echo '<div class="et-form-grid">';
		echo '<div><label>الاسم *</label><input type="text" name="name" required value="' . esc_attr( $edit_employee->name ?? '' ) . '"></div>';
		echo '<div><label>القسم</label><select name="department_id"><option value="">— بدون قسم —</option>';
		foreach ( $departments as $d ) {
			$sel = ( $edit_employee && (int) $edit_employee->department_id === (int) $d->id ) ? 'selected' : '';
			echo '<option value="' . intval( $d->id ) . '" ' . $sel . '>' . esc_html( $d->name ) . '</option>';
		}
		echo '</select></div>';
		echo '<div><label>المدير المباشر</label><input type="text" name="manager_name" value="' . esc_attr( $edit_employee->manager_name ?? '' ) . '"></div>';
		echo '<div><label>رقم الجوال</label><input type="text" name="phone" value="' . esc_attr( $edit_employee->phone ?? '' ) . '"></div>';
		echo '<div><label>البريد الإلكتروني * (اسم المستخدم للدخول)</label><input type="email" name="email" required dir="ltr" autocomplete="off" value="' . esc_attr( $edit_employee->email ?? '' ) . '"></div>';
		echo '<div><label>الفترة</label><select name="shift">';
		$cur_shift = $edit_employee->shift ?? 'both';
		echo '<option value="both" ' . selected( $cur_shift, 'both', false ) . '>كلا الفترتين</option>';
		echo '<option value="morning" ' . selected( $cur_shift, 'morning', false ) . '>صباحية</option>';
		echo '<option value="evening" ' . selected( $cur_shift, 'evening', false ) . '>مسائية</option>';
		echo '</select></div>';
		$code_required = $edit_employee ? '' : ' required';
		$code_hint     = $edit_employee
			? 'اتركها فارغة للإبقاء على كلمة المرور الحالية. كلمات المرور محفوظة مشفّرة ولا يمكن عرضها — اكتب كلمة جديدة (' . (int) ET_Employees::MIN_CODE_LENGTH . ' أحرف على الأقل) لتغييرها، وستُلغى جلسات الموظف المفتوحة تلقائيًا.'
			: (int) ET_Employees::MIN_CODE_LENGTH . ' أحرف على الأقل (حروف وأرقام). ستظهر لك مرة واحدة بعد الحفظ.';
		echo '<div><label>كلمة المرور' . ( $edit_employee ? '' : ' *' ) . '</label><input type="text" name="access_code" dir="ltr"' . $code_required . ' value="" placeholder="' . ( $edit_employee ? '•••••••• (بدون تغيير)' : 'كلمة مرور الموظف' ) . '" minlength="' . (int) ET_Employees::MIN_CODE_LENGTH . '" maxlength="' . (int) ET_Employees::MAX_CODE_LENGTH . '" autocomplete="new-password">'
			. '<label style="display:block;margin-top:6px;font-weight:normal;"><input type="checkbox" name="generate_code" value="1" onchange="var i=this.form.access_code;i.required=false;i.disabled=this.checked;"> توليد كلمة مرور عشوائية</label>'
			. '<label style="display:block;margin-top:4px;font-weight:normal;"><input type="checkbox" name="send_credentials" value="1"> إرسال بيانات الدخول للموظف على بريده</label>'
			. '<p class="description" style="margin:4px 0 0;font-size:12px;color:#6B7684;">' . esc_html( $code_hint ) . '</p></div>';
		echo '<div><label>الحالة</label><select name="status">';
		echo '<option value="active" ' . selected( ( $edit_employee->status ?? 'active' ), 'active', false ) . '>نشط</option>';
		echo '<option value="inactive" ' . selected( ( $edit_employee->status ?? '' ), 'inactive', false ) . '>غير نشط</option>';
		echo '</select></div>';
		echo '</div>';

		echo '<div style="margin-top:16px;padding-top:14px;border-top:1px solid #E7E9EE;">';
		echo '<h3 style="margin:0 0 6px;font-size:14px;">أوقات دوام مخصّصة لهذا الموظف (اختياري)</h3>';
		echo '<p class="description" style="margin:0 0 10px;font-size:12px;color:#6B7684;">اترك أي حقل فارغًا ليُستخدم إعداد "الدوام" العام. تُستخدم فقط لتجاوز وقت بداية/نهاية فترة هذا الموظف بعينه، مثال: موظف يبدأ دوامه الصباحي الساعة 9 بدلًا من الإعداد العام 8.</p>';
		echo '<div class="et-form-grid">';
		echo '<div><label>بداية الفترة الصباحية (خاص بالموظف)</label><input type="time" name="att_morning_start_override" value="' . esc_attr( $edit_employee->att_morning_start_override ?? '' ) . '"></div>';
		echo '<div><label>نهاية الفترة الصباحية (خاص بالموظف)</label><input type="time" name="att_morning_end_override" value="' . esc_attr( $edit_employee->att_morning_end_override ?? '' ) . '"></div>';
		echo '<div><label>بداية الفترة المسائية (خاص بالموظف)</label><input type="time" name="att_evening_start_override" value="' . esc_attr( $edit_employee->att_evening_start_override ?? '' ) . '"></div>';
		echo '<div><label>نهاية الفترة المسائية (خاص بالموظف)</label><input type="time" name="att_evening_end_override" value="' . esc_attr( $edit_employee->att_evening_end_override ?? '' ) . '"></div>';
		echo '</div>';
		echo '</div>';
		echo '<p style="margin-top:14px;"><button type="submit" class="button button-primary">' . ( $edit_employee ? 'حفظ التعديلات' : 'إضافة الموظف' ) . '</button>';
		if ( $edit_employee ) echo ' <a class="button" href="' . esc_url( admin_url( 'admin.php?page=et-employees' ) ) . '">إلغاء</a>';
		echo '</p>';
		echo '</form></div>';

		// الفلاتر
		echo '<form method="get" class="et-filters">';
		echo '<input type="hidden" name="page" value="et-employees" />';
		echo '<div><label>بحث</label><input type="text" name="s" value="' . esc_attr( $args['search'] ) . '" placeholder="الاسم / البريد / الجوال"></div>';
		echo '<div><label>القسم</label><select name="department_id"><option value="">الكل</option>';
		foreach ( $departments as $d ) {
			echo '<option value="' . intval( $d->id ) . '" ' . selected( $args['department_id'], $d->id, false ) . '>' . esc_html( $d->name ) . '</option>';
		}
		echo '</select></div>';
		echo '<div><label>الحالة</label><select name="status"><option value="">الكل</option><option value="active" ' . selected( $args['status'], 'active', false ) . '>نشط</option><option value="inactive" ' . selected( $args['status'], 'inactive', false ) . '>غير نشط</option></select></div>';
		echo '<div><button type="submit" class="button">تصفية</button></div>';
		echo '</form>';

		echo '<div class="et-panel">';
		if ( empty( $result['items'] ) ) {
			echo '<div class="et-empty">لا يوجد موظفون مطابقون.</div>';
		} else {
			echo '<table class="et-table"><thead><tr><th>الاسم</th><th>القسم</th><th>المدير</th><th>الجوال</th><th>البريد (الدخول)</th><th>كلمة المرور</th><th>الحالة</th><th>تاريخ الإضافة</th><th>إجراءات</th></tr></thead><tbody>';
			foreach ( $result['items'] as $e ) {
				echo '<tr>';
				echo '<td>' . esc_html( $e->name ) . '</td>';
				echo '<td>' . esc_html( $e->department_name ?: '—' ) . '</td>';
				echo '<td>' . esc_html( $e->manager_name ?: '—' ) . '</td>';
				echo '<td>' . esc_html( $e->phone ?: '—' ) . '</td>';
				echo '<td>' . esc_html( $e->email ?: '—' ) . '</td>';
				if ( empty( $e->access_code ) ) {
					echo '<td><span style="color:#D6483F;">غير محدد</span></td>';
				} elseif ( ET_Employees::has_weak_code( $e->id ) ) {
					echo '<td><span style="color:#B7791F;" title="كلمة المرور قصيرة — غيّرها من تعديل">⚠️ قصيرة — غيّرها</span></td>';
				} else {
					echo '<td>🔒 محفوظ</td>';
				}
				echo '<td>' . ( $e->status === 'active' ? '<span class="et-pill" style="background:#1F9D7C1A;color:#1F9D7C;border-color:#1F9D7C40;">نشط</span>' : '<span class="et-pill" style="background:#6B76841A;color:#6B7684;border-color:#6B768440;">غير نشط</span>' ) . '</td>';
				echo '<td>' . esc_html( mysql2date( 'Y-m-d', $e->created_at ) ) . '</td>';
				echo '<td>';
				echo '<a href="' . esc_url( admin_url( 'admin.php?page=et-employees&edit=' . $e->id ) ) . '">تعديل</a>';
				if ( $e->status === 'active' ) {
					echo ' | <form method="post" style="display:inline" onsubmit="return confirm(\'هل تريد تعطيل هذا الموظف؟\');">';
					wp_nonce_field( 'et_admin_action', 'et_nonce' );
					echo '<input type="hidden" name="et_action" value="deactivate_employee" /><input type="hidden" name="employee_id" value="' . intval( $e->id ) . '" />';
					echo '<button type="submit" class="button-link" style="color:#D6483F;">تعطيل</button></form>';
				}
				echo '</td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
			$this->pagination( $args['page'], $result['pages'], $base_url );
		}
		echo '</div></div>';
	}

	/* ---------------------------------------------------------------
	   الأقسام
	--------------------------------------------------------------- */
	public function page_departments() {
		$this->guard();
		$notice = '';

		if ( ! empty( $_POST['et_action'] ) && check_admin_referer( 'et_admin_action', 'et_nonce' ) ) {
			$action = sanitize_text_field( $_POST['et_action'] );
			if ( $action === 'save_department' ) {
				$data = array(
					'name'         => sanitize_text_field( $_POST['name'] ?? '' ),
					'manager_name' => sanitize_text_field( $_POST['manager_name'] ?? '' ),
					'status'       => sanitize_text_field( $_POST['status'] ?? 'active' ),
				);
				if ( $data['name'] === '' ) {
					$notice = '<div class="notice notice-error"><p>اسم القسم مطلوب.</p></div>';
				} else {
					$id = intval( $_POST['department_id'] ?? 0 );
					if ( $id > 0 ) {
						ET_Departments::update( $id, $data );
						$notice = '<div class="notice notice-success"><p>تم تحديث القسم.</p></div>';
					} else {
						ET_Departments::create( $data );
						$notice = '<div class="notice notice-success"><p>تم إضافة القسم.</p></div>';
					}
				}
			} elseif ( $action === 'remove_department' ) {
				ET_Departments::delete_or_deactivate( intval( $_POST['department_id'] ?? 0 ) );
				$notice = '<div class="notice notice-success"><p>تم تحديث حالة القسم.</p></div>';
			}
		}

		$edit_department = ! empty( $_GET['edit'] ) ? ET_Departments::get( intval( $_GET['edit'] ) ) : null;
		$departments = ET_Departments::all();

		echo '<div class="wrap et-wrap">';
		echo $notice;
		echo '<h1 class="et-h1">الأقسام</h1>';
		echo '<p class="et-sub">تُستخدم الأقسام لتصنيف الموظفين والتحديثات والمشاكل، وتظهر في التقارير حسب القسم.</p>';

		echo '<div class="et-panel">';
		echo '<h2>' . ( $edit_department ? 'تعديل قسم' : 'إضافة قسم جديد' ) . '</h2>';
		echo '<form method="post">';
		wp_nonce_field( 'et_admin_action', 'et_nonce' );
		echo '<input type="hidden" name="et_action" value="save_department" />';
		if ( $edit_department ) echo '<input type="hidden" name="department_id" value="' . intval( $edit_department->id ) . '" />';
		echo '<div class="et-form-grid">';
		echo '<div><label>اسم القسم *</label><input type="text" name="name" required value="' . esc_attr( $edit_department->name ?? '' ) . '"></div>';
		echo '<div><label>مدير القسم</label><input type="text" name="manager_name" value="' . esc_attr( $edit_department->manager_name ?? '' ) . '"></div>';
		echo '<div><label>الحالة</label><select name="status">';
		echo '<option value="active" ' . selected( ( $edit_department->status ?? 'active' ), 'active', false ) . '>نشط</option>';
		echo '<option value="inactive" ' . selected( ( $edit_department->status ?? '' ), 'inactive', false ) . '>غير نشط</option>';
		echo '</select></div>';
		echo '</div>';
		echo '<p style="margin-top:14px;"><button type="submit" class="button button-primary">' . ( $edit_department ? 'حفظ التعديلات' : 'إضافة القسم' ) . '</button>';
		if ( $edit_department ) echo ' <a class="button" href="' . esc_url( admin_url( 'admin.php?page=et-departments' ) ) . '">إلغاء</a>';
		echo '</p></form></div>';

		echo '<div class="et-panel">';
		if ( empty( $departments ) ) {
			echo '<div class="et-empty">لا توجد أقسام بعد.</div>';
		} else {
			echo '<table class="et-table"><thead><tr><th>القسم</th><th>المدير</th><th>الحالة</th><th>تاريخ الإضافة</th><th>إجراءات</th></tr></thead><tbody>';
			foreach ( $departments as $d ) {
				echo '<tr>';
				echo '<td>' . esc_html( $d->name ) . '</td>';
				echo '<td>' . esc_html( $d->manager_name ?: '—' ) . '</td>';
				echo '<td>' . ( $d->status === 'active' ? '<span class="et-pill" style="background:#1F9D7C1A;color:#1F9D7C;border-color:#1F9D7C40;">نشط</span>' : '<span class="et-pill" style="background:#6B76841A;color:#6B7684;border-color:#6B768440;">غير نشط</span>' ) . '</td>';
				echo '<td>' . esc_html( mysql2date( 'Y-m-d', $d->created_at ) ) . '</td>';
				echo '<td>';
				echo '<a href="' . esc_url( admin_url( 'admin.php?page=et-departments&edit=' . $d->id ) ) . '">تعديل</a>';
				echo ' | <form method="post" style="display:inline" onsubmit="return confirm(\'سيتم تعطيل القسم أو حذفه إن لم يكن مرتبطًا بموظفين. متابعة؟\');">';
				wp_nonce_field( 'et_admin_action', 'et_nonce' );
				echo '<input type="hidden" name="et_action" value="remove_department" /><input type="hidden" name="department_id" value="' . intval( $d->id ) . '" />';
				echo '<button type="submit" class="button-link" style="color:#D6483F;">حذف/تعطيل</button></form>';
				echo '</td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '</div></div>';
	}

	/* ---------------------------------------------------------------
	   المهام الأسبوعية — الإدارة تضيف مهام لكل موظف، والموظف يعلّمها منجزة من واجهته
	--------------------------------------------------------------- */
	public function page_weekly_tasks() {
		$this->guard();
		$notice = '';

		if ( ! empty( $_POST['et_action'] ) && check_admin_referer( 'et_admin_action', 'et_nonce' ) ) {
			$action = sanitize_text_field( $_POST['et_action'] );

			if ( $action === 'add_weekly_tasks' ) {
				$employee_id     = intval( $_POST['employee_id'] ?? 0 );
				$week_start      = sanitize_text_field( $_POST['week_start_date'] ?? '' );
				$titles_raw      = isset( $_POST['tasks_title'] ) ? (array) $_POST['tasks_title'] : array();
				$days_raw        = isset( $_POST['tasks_day'] ) ? (array) $_POST['tasks_day'] : array();
				$priorities_raw  = isset( $_POST['tasks_priority'] ) ? (array) $_POST['tasks_priority'] : array();
				$notify_employee = ! empty( $_POST['notify_employee'] );
				$employee        = $employee_id ? ET_Employees::get( $employee_id ) : null;

				if ( ! $employee ) {
					$notice = '<div class="notice notice-error"><p>الرجاء اختيار موظف صحيح.</p></div>';
				} elseif ( empty( $week_start ) ) {
					$notice = '<div class="notice notice-error"><p>الرجاء تحديد بداية الأسبوع.</p></div>';
				} else {
					// نحسم التاريخ إلى "أحد" الأسبوع الصحيح حسب توقيت الموقع (نفس منطق تبويب الإدارة بالمنصة).
					$week_start = ET_WeeklyTasks::normalize_week_start( $week_start );
					$created = array();
					foreach ( $titles_raw as $i => $raw_title ) {
						$title = sanitize_text_field( trim( (string) $raw_title ) );
						if ( $title === '' ) continue;

						$day      = sanitize_text_field( $days_raw[ $i ] ?? '' );
						$priority = sanitize_text_field( $priorities_raw[ $i ] ?? 'medium' );

						ET_WeeklyTasks::create( array(
							'employee_id'     => $employee->id,
							'department_id'   => $employee->department_id,
							'week_start_date' => $week_start,
							'task_day'        => $day,
							'title'           => $title,
							'priority'        => $priority,
						) );

						$created[] = array( 'title' => $title, 'task_day' => $day, 'priority' => $priority );
					}

					if ( empty( $created ) ) {
						$notice = '<div class="notice notice-error"><p>الرجاء كتابة مهمة واحدة على الأقل.</p></div>';
					} else {
						$notice = '<div class="notice notice-success"><p>تمت إضافة ' . count( $created ) . ' مهمة لـ' . esc_html( $employee->name ) . '.</p></div>';

						if ( $notify_employee ) {
							$diag = et_send_weekly_tasks_notification( $employee, $created );
							if ( ! empty( $diag['ok'] ) ) {
								$notice .= '<div class="notice notice-success"><p>✅ تم إرسال بريد إشعار للموظف على ' . esc_html( $diag['to'] ) . '.</p></div>';
							} else {
								$notice .= '<div class="notice notice-warning"><p>⚠️ تمت إضافة المهام، لكن تعذّر إرسال بريد الإشعار: ' . esc_html( $diag['error'] ?? 'خطأ غير معروف' ) . '</p></div>';
							}
						}
					}
				}
			} elseif ( $action === 'delete_weekly_task' ) {
				ET_WeeklyTasks::delete( intval( $_POST['task_id'] ?? 0 ) );
				$notice = '<div class="notice notice-success"><p>تم حذف المهمة.</p></div>';
			}
		}

		$args = array(
			'employee_id'     => intval( $_GET['employee_id'] ?? 0 ),
			'department_id'   => intval( $_GET['department_id'] ?? 0 ),
			'week_start_date' => sanitize_text_field( $_GET['week_start_date'] ?? '' ),
			'task_day'        => sanitize_text_field( $_GET['task_day'] ?? '' ),
			'status'          => sanitize_text_field( $_GET['status'] ?? '' ),
			'page'            => max( 1, intval( $_GET['paged'] ?? 1 ) ),
			'per_page'        => 20,
		);
		$result      = ET_WeeklyTasks::query( $args );
		$departments = ET_Departments::all();
		$employees   = ET_Employees::all();
		$base_url    = remove_query_arg( 'paged' );
		$this_week   = ET_WeeklyTasks::current_week_start();

		$day_options = '<option value="">غير محدد</option>';
		foreach ( ET_WeeklyTasks::DAYS as $d ) {
			$day_options .= '<option value="' . esc_attr( $d ) . '">' . esc_html( ET_WeeklyTasks::day_label( $d ) ) . '</option>';
		}
		$priority_options = '';
		foreach ( ET_WeeklyTasks::PRIORITIES as $p ) {
			$priority_options .= '<option value="' . esc_attr( $p ) . '" ' . selected( $p, 'medium', false ) . '>' . esc_html( ET_WeeklyTasks::priority_label( $p ) ) . '</option>';
		}

		echo '<div class="wrap et-wrap">';
		echo $notice;
		echo '<h1 class="et-h1">المهام الأسبوعية</h1>';
		echo '<p class="et-sub">أضف مهام الأسبوع لكل موظف — لكل مهمة حدد اليوم المستهدف (الأحد إلى الخميس) ومدى أهميتها، ويصل للموظف بريد إشعار فور الإضافة.</p>';

		echo '<div class="et-panel">';
		echo '<h2 style="margin-top:0;">إضافة مهام</h2>';
		echo '<form method="post" id="et-add-tasks-form">';
		wp_nonce_field( 'et_admin_action', 'et_nonce' );
		echo '<input type="hidden" name="et_action" value="add_weekly_tasks" />';
		echo '<div class="et-form-grid">';
		echo '<div><label>الموظف *</label><select name="employee_id" required><option value="">— اختر —</option>';
		foreach ( $employees as $e ) {
			echo '<option value="' . esc_attr( $e->id ) . '">' . esc_html( $e->name ) . ( $e->department_name ? ' — ' . esc_html( $e->department_name ) : '' ) . '</option>';
		}
		echo '</select></div>';
		echo '<div><label>بداية الأسبوع *</label><input type="date" name="week_start_date" required value="' . esc_attr( $this_week ) . '"></div>';
		echo '</div>';

		echo '<label style="display:block;font-size:12px;font-weight:700;color:#3C3191;margin:16px 0 6px;font-family:\'Cairo\',sans-serif;">المهام</label>';
		echo '<div id="et-task-rows">';
		echo '<div class="et-task-row">';
		echo '<input type="text" name="tasks_title[]" placeholder="نص المهمة" required>';
		echo '<select name="tasks_day[]" class="et-day-select">' . $day_options . '</select>';
		echo '<select name="tasks_priority[]" class="et-priority-select">' . $priority_options . '</select>';
		echo '<button type="button" class="button-link et-remove-row" style="color:#D6483F;">حذف</button>';
		echo '</div>';
		echo '</div>';
		echo '<p style="margin-top:6px;"><button type="button" class="button" id="et-add-row-btn">+ إضافة مهمة أخرى</button></p>';

		echo '<label style="display:flex;align-items:center;gap:6px;font-size:13px;margin-top:10px;"><input type="checkbox" name="notify_employee" value="1" checked> إرسال بريد إلكتروني للموظف بإشعاره أن لديه مهامًا جديدة</label>';

		echo '<p style="margin-top:16px;"><button type="submit" class="button button-primary">إضافة المهام</button></p>';
		echo '</form>';
		echo '</div>';

		echo "<script>
		(function(){
			var wrap = document.getElementById('et-task-rows');
			function bindRemove(row){
				var btn = row.querySelector('.et-remove-row');
				btn.addEventListener('click', function(){
					if(wrap.querySelectorAll('.et-task-row').length > 1){ row.remove(); }
				});
			}
			var addBtn = document.getElementById('et-add-row-btn');
			if(addBtn){
				addBtn.addEventListener('click', function(){
					var rows = wrap.querySelectorAll('.et-task-row');
					var clone = rows[0].cloneNode(true);
					clone.querySelector('input[type=text]').value = '';
					clone.querySelector('.et-day-select').selectedIndex = 0;
					clone.querySelector('.et-priority-select').value = 'medium';
					wrap.appendChild(clone);
					bindRemove(clone);
				});
			}
			wrap.querySelectorAll('.et-task-row').forEach(bindRemove);
		})();
		</script>";

		echo '<form method="get" class="et-filters">';
		echo '<input type="hidden" name="page" value="et-weekly-tasks" />';
		echo '<div><label>الموظف</label><select name="employee_id"><option value="">الكل</option>';
		foreach ( $employees as $e ) echo '<option value="' . intval( $e->id ) . '" ' . selected( $args['employee_id'], $e->id, false ) . '>' . esc_html( $e->name ) . '</option>';
		echo '</select></div>';
		echo '<div><label>القسم</label><select name="department_id"><option value="">الكل</option>';
		foreach ( $departments as $d ) echo '<option value="' . intval( $d->id ) . '" ' . selected( $args['department_id'], $d->id, false ) . '>' . esc_html( $d->name ) . '</option>';
		echo '</select></div>';
		echo '<div><label>الأسبوع</label><input type="date" name="week_start_date" value="' . esc_attr( $args['week_start_date'] ) . '"></div>';
		echo '<div><label>اليوم</label><select name="task_day"><option value="">الكل</option>';
		foreach ( ET_WeeklyTasks::DAYS as $d ) echo '<option value="' . esc_attr( $d ) . '" ' . selected( $args['task_day'], $d, false ) . '>' . esc_html( ET_WeeklyTasks::day_label( $d ) ) . '</option>';
		echo '</select></div>';
		echo '<div><label>الحالة</label><select name="status"><option value="">الكل</option><option value="pending" ' . selected( $args['status'], 'pending', false ) . '>لم تنجز</option><option value="done" ' . selected( $args['status'], 'done', false ) . '>منجزة</option></select></div>';
		echo '<div><button type="submit" class="button">تصفية</button></div>';
		echo '</form>';

		echo '<div class="et-panel">';
		if ( empty( $result['items'] ) ) {
			echo '<div class="et-empty">لا توجد مهام مطابقة.</div>';
		} else {
			echo '<table class="et-table"><thead><tr><th>الموظف</th><th>القسم</th><th>الأسبوع</th><th>اليوم</th><th>المهمة</th><th>الأهمية</th><th>الحالة</th><th>تاريخ الإضافة</th><th></th></tr></thead><tbody>';
			foreach ( $result['items'] as $t ) {
				echo '<tr>';
				echo '<td>' . esc_html( $t->employee_name ?: '—' ) . '</td>';
				echo '<td>' . esc_html( $t->department_name ?: '—' ) . '</td>';
				echo '<td>' . esc_html( $t->week_start_date ) . '</td>';
				echo '<td>' . esc_html( ET_WeeklyTasks::day_label( $t->task_day ) ) . '</td>';
				echo '<td>' . esc_html( $t->title ) . '</td>';
				echo '<td>' . $this->badge_task_priority( $t->priority ) . '</td>';
				echo '<td>' . ( $t->status === 'done'
					? '<span class="et-pill" style="background:#1F9D7C1A;color:#1F9D7C;border-color:#1F9D7C40;">منجزة</span>'
					: '<span class="et-pill" style="background:#6B76841A;color:#6B7684;border-color:#6B768440;">لم تنجز</span>' ) . '</td>';
				echo '<td>' . esc_html( mysql2date( 'Y-m-d', $t->created_at ) ) . '</td>';
				echo '<td><form method="post" style="display:inline" onsubmit="return confirm(\'حذف هذه المهمة؟\');">';
				wp_nonce_field( 'et_admin_action', 'et_nonce' );
				echo '<input type="hidden" name="et_action" value="delete_weekly_task" /><input type="hidden" name="task_id" value="' . intval( $t->id ) . '" />';
				echo '<button type="submit" class="button-link" style="color:#D6483F;">حذف</button></form></td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
			$this->pagination( $args['page'], $result['pages'], $base_url );
		}
		echo '</div></div>';
	}

	/* ---------------------------------------------------------------
	   التقارير والتصدير (Excel/CSV)
	--------------------------------------------------------------- */
	public function page_reports() {
		$this->guard();

		$departments = ET_Departments::all();
		$employees   = ET_Employees::all();

		echo '<div class="wrap et-wrap">';
		echo '<h1 class="et-h1">التقارير والتصدير</h1>';
		echo '<p class="et-sub">صدّر التحديثات اليومية أو المشاكل إلى ملف Excel (CSV) — حدد نطاق التاريخ والقسم/الموظف اختياريًا، واترك الحقل فارغًا لتضمين الكل.</p>';

		echo '<div class="et-panel">';
		echo '<h2 style="margin-top:0;">تصدير التحديثات اليومية</h2>';
		$this->render_export_form( 'updates', $departments, $employees );
		echo '</div>';

		echo '<div class="et-panel" style="margin-top:20px;">';
		echo '<h2 style="margin-top:0;">تصدير المشاكل</h2>';
		$this->render_export_form( 'problems', $departments, $employees );
		echo '</div>';

		echo '<div class="et-panel" style="margin-top:20px;">';
		echo '<h2 style="margin-top:0;">تقرير أداء المهام لكل موظف</h2>';
		echo '<p class="et-sub">اختر نطاق تاريخ (يمكن أن يجمع عدة أسابيع معًا) وموظفًا معينًا اختياريًا. لكل موظف: عدد المهام المسندة له بهذا النطاق، المنجزة منها، المتأخرة (بنوعين: حسب تاريخ الاستحقاق المحدد لها إن وُجد، وحسب يوم العمل الذي أُسندت له)، ونسبة الإنجاز.</p>';

		$report_date_from = sanitize_text_field( $_GET['report_date_from'] ?? '' );
		if ( empty( $report_date_from ) ) $report_date_from = ET_WeeklyTasks::current_week_start();
		$report_date_to = sanitize_text_field( $_GET['report_date_to'] ?? '' );
		if ( empty( $report_date_to ) ) $report_date_to = $report_date_from;
		$report_employee_id = isset( $_GET['report_employee_id'] ) ? (int) $_GET['report_employee_id'] : 0;

		echo '<form method="get" class="et-filters" style="margin-bottom:16px;">';
		echo '<input type="hidden" name="page" value="et-reports" />';
		echo '<div><label>من تاريخ</label><input type="date" name="report_date_from" value="' . esc_attr( $report_date_from ) . '"></div>';
		echo '<div><label>إلى تاريخ</label><input type="date" name="report_date_to" value="' . esc_attr( $report_date_to ) . '"></div>';
		echo '<div><label>الموظف</label><select name="report_employee_id"><option value="0">— كل الموظفين —</option>';
		foreach ( $employees as $e ) {
			echo '<option value="' . esc_attr( $e->id ) . '" ' . selected( $report_employee_id, $e->id, false ) . '>' . esc_html( $e->name ) . '</option>';
		}
		echo '</select></div>';
		echo '<div><button type="submit" class="button button-primary">عرض</button></div>';
		echo '</form>';

		$report_rows = ET_WeeklyTasks::employee_stats_for_range( $report_date_from, $report_date_to, $report_employee_id );

		if ( empty( $report_rows ) ) {
			echo '<div class="et-empty">لا توجد أي مهام مسندة لأي موظف بهذا النطاق.</div>';
		} else {
			echo '<p class="et-sub" style="margin:0 0 8px;">اضغط أي رقم بالجدول (مسندة/منجزة/متأخرة) لعرض قائمة المهام نفسها.</p>';
			echo '<table class="et-table"><thead><tr><th>الموظف</th><th>المهام المسندة</th><th>المنجزة</th><th>متأخرة (تاريخ الاستحقاق)</th><th>متأخرة (يوم العمل المحدد)</th><th>نسبة الإنجاز</th></tr></thead><tbody>';

			$categories = array(
				'assigned'    => 'المهام المسندة',
				'completed'   => 'المهام المنجزة',
				'delayed_due' => 'المهام المتأخرة (حسب تاريخ الاستحقاق)',
				'delayed_day' => 'المهام المتأخرة (حسب يوم العمل المحدد)',
			);

			foreach ( $report_rows as $row ) {
				$eid = (int) $row['employee_id'];
				echo '<tr>';
				echo '<td>' . esc_html( $row['name'] ) . '</td>';
				echo '<td><span class="et-report-count" data-emp="' . $eid . '" data-cat="assigned">' . (int) $row['assigned'] . '</span></td>';
				echo '<td><span class="et-report-count" data-emp="' . $eid . '" data-cat="completed">' . (int) $row['completed'] . '</span></td>';
				echo '<td><span class="et-report-count" data-emp="' . $eid . '" data-cat="delayed_due"' . ( $row['delayed_due'] > 0 ? ' style="color:#D6483F;font-weight:700;"' : '' ) . '>' . (int) $row['delayed_due'] . '</span></td>';
				echo '<td><span class="et-report-count" data-emp="' . $eid . '" data-cat="delayed_day"' . ( $row['delayed_day'] > 0 ? ' style="color:#D6483F;font-weight:700;"' : '' ) . '>' . (int) $row['delayed_day'] . '</span></td>';
				echo '<td>' . (int) $row['completion_percent'] . '٪</td>';
				echo '</tr>';

				echo '<tr class="et-report-detail-row" id="et-report-detail-' . $eid . '" style="display:none;"><td colspan="6" style="background:#FAFBFC;">';
				foreach ( $categories as $cat_key => $cat_label ) {
					$tasks = $row[ $cat_key . '_tasks' ];
					echo '<div class="et-report-detail-cat" data-cat="' . esc_attr( $cat_key ) . '" style="display:none;">';
					echo '<strong>' . esc_html( $cat_label ) . ':</strong>';
					if ( empty( $tasks ) ) {
						echo '<div class="et-empty" style="padding:8px 0;">لا توجد مهام بهذا التصنيف.</div>';
					} else {
						echo '<ul style="margin:8px 0 0; padding-right:18px;">';
						foreach ( $tasks as $t ) {
							echo '<li>' . esc_html( $t['title'] ) . ' <span style="color:#6B7684;font-size:11px;">(' . esc_html( $t['day_label'] ) . ' — أسبوع ' . esc_html( $t['week_start_date'] ) . ')</span></li>';
						}
						echo '</ul>';
					}
					echo '</div>';
				}
				echo '</td></tr>';
			}
			echo '</tbody></table>';

			echo "<script>
			(function(){
				document.querySelectorAll('.et-report-count').forEach(function(el){
					el.style.cursor = 'pointer';
					el.style.textDecoration = 'underline dotted';
					el.addEventListener('click', function(){
						var emp = el.getAttribute('data-emp');
						var cat = el.getAttribute('data-cat');
						var row = document.getElementById('et-report-detail-' + emp);
						if(!row) return;
						var target = row.querySelector('.et-report-detail-cat[data-cat=\"' + cat + '\"]');
						var wasOpenForThis = row.style.display !== 'none' && target && target.style.display !== 'none';
						row.querySelectorAll('.et-report-detail-cat').forEach(function(c){ c.style.display = 'none'; });
						if(wasOpenForThis){
							row.style.display = 'none';
						} else {
							row.style.display = '';
							if(target) target.style.display = '';
						}
					});
				});
			})();
			</script>";

			echo '<form method="get" style="margin-top:12px;">';
			echo '<input type="hidden" name="page" value="et-reports" />';
			echo '<input type="hidden" name="et_export" value="weekly_task_report" />';
			echo '<input type="hidden" name="report_date_from" value="' . esc_attr( $report_date_from ) . '" />';
			echo '<input type="hidden" name="report_date_to" value="' . esc_attr( $report_date_to ) . '" />';
			echo '<input type="hidden" name="report_employee_id" value="' . esc_attr( $report_employee_id ) . '" />';
			wp_nonce_field( 'et_export_weekly_task_report' );
			echo '<button type="submit" class="button button-primary">⬇️ تنزيل هذا التقرير Excel (CSV)</button>';
			echo '</form>';
		}
		echo '</div>';

		echo '</div>';
	}

	/** يطبع نموذج فلاتر تصدير واحد (تحديثات أو مشاكل) — يرسل GET يفعّل handle_export() ويبدأ تنزيل CSV مباشرة */
	private function render_export_form( $type, $departments, $employees ) {
		echo '<form method="get">';
		echo '<input type="hidden" name="page" value="et-reports" />';
		echo '<input type="hidden" name="et_export" value="' . esc_attr( $type ) . '" />';
		wp_nonce_field( 'et_export_' . $type );

		echo '<div class="et-form-grid">';
		echo '<div><label>من تاريخ</label><input type="date" name="date_from"></div>';
		echo '<div><label>إلى تاريخ</label><input type="date" name="date_to"></div>';

		echo '<div><label>القسم</label><select name="department_id"><option value="">— الكل —</option>';
		foreach ( $departments as $d ) {
			echo '<option value="' . esc_attr( $d->id ) . '">' . esc_html( $d->name ) . '</option>';
		}
		echo '</select></div>';

		echo '<div><label>الموظف</label><select name="employee_id"><option value="">— الكل —</option>';
		foreach ( $employees as $e ) {
			echo '<option value="' . esc_attr( $e->id ) . '">' . esc_html( $e->name ) . '</option>';
		}
		echo '</select></div>';
		echo '</div>';

		echo '<p style="margin-top:16px;"><button type="submit" class="button button-primary">⬇️ تنزيل ملف Excel (CSV)</button></p>';
		echo '</form>';
	}

	/* ---------------------------------------------------------------
	   التحديثات اليومية
	--------------------------------------------------------------- */
	public function page_updates() {
		$this->guard();

		if ( ! empty( $_GET['view'] ) ) {
			$this->render_update_detail( intval( $_GET['view'] ) );
			return;
		}

		$args = array(
			'search'        => sanitize_text_field( $_GET['s'] ?? '' ),
			'employee_id'   => intval( $_GET['employee_id'] ?? 0 ),
			'department_id' => intval( $_GET['department_id'] ?? 0 ),
			'has_problem'   => isset( $_GET['has_problem'] ) && $_GET['has_problem'] !== '' ? intval( $_GET['has_problem'] ) : '',
			'date_from'     => sanitize_text_field( $_GET['date_from'] ?? '' ),
			'date_to'       => sanitize_text_field( $_GET['date_to'] ?? '' ),
			'page'          => max( 1, intval( $_GET['paged'] ?? 1 ) ),
			'per_page'      => 20,
		);
		$result = ET_Updates::query( $args );
		$departments = ET_Departments::all();
		$base_url = remove_query_arg( 'paged' );

		echo '<div class="wrap et-wrap">';
		echo '<h1 class="et-h1">التحديثات اليومية</h1>';
		echo '<p class="et-sub">جميع التحديثات المُرسلة من الموظفين، مع إمكانية البحث والتصفية.</p>';

		echo '<form method="get" class="et-filters">';
		echo '<input type="hidden" name="page" value="et-updates" />';
		echo '<div><label>بحث</label><input type="text" name="s" value="' . esc_attr( $args['search'] ) . '" placeholder="كلمة داخل التحديث"></div>';
		echo '<div><label>القسم</label><select name="department_id"><option value="">الكل</option>';
		foreach ( $departments as $d ) echo '<option value="' . intval( $d->id ) . '" ' . selected( $args['department_id'], $d->id, false ) . '>' . esc_html( $d->name ) . '</option>';
		echo '</select></div>';
		echo '<div><label>يحتوي مشكلة؟</label><select name="has_problem"><option value="">الكل</option><option value="1" ' . selected( $args['has_problem'], 1, false ) . '>نعم</option><option value="0" ' . selected( $args['has_problem'], 0, false ) . '>لا</option></select></div>';
		echo '<div><label>من تاريخ</label><input type="date" name="date_from" value="' . esc_attr( $args['date_from'] ) . '"></div>';
		echo '<div><label>إلى تاريخ</label><input type="date" name="date_to" value="' . esc_attr( $args['date_to'] ) . '"></div>';
		echo '<div><button type="submit" class="button">تصفية</button></div>';
		echo '</form>';

		echo '<div class="et-panel">';
		if ( empty( $result['items'] ) ) {
			echo '<div class="et-empty">لا توجد تحديثات مطابقة.</div>';
		} else {
			echo '<table class="et-table"><thead><tr><th>الموظف</th><th>القسم</th><th>الإنجاز</th><th>المشكلة</th><th>الأولوية</th><th>التاريخ</th><th></th></tr></thead><tbody>';
			foreach ( $result['items'] as $u ) {
				$view_url = admin_url( 'admin.php?page=et-updates&view=' . $u->id );
				echo '<tr>';
				echo '<td>' . esc_html( $u->employee_name ) . '</td>';
				echo '<td>' . esc_html( $u->department_name ?: '—' ) . '</td>';
				echo '<td>' . esc_html( mb_substr( $u->achievements, 0, 50 ) ) . '</td>';
				echo '<td>' . ( $u->has_problem ? esc_html( mb_substr( $u->problem_description, 0, 40 ) ) : '—' ) . '</td>';
				echo '<td>' . ( $u->has_problem ? $this->badge_priority( $u->problem_priority ) : '—' ) . '</td>';
				echo '<td>' . esc_html( $u->update_date ) . '</td>';
				echo '<td><a href="' . esc_url( $view_url ) . '">عرض ‹</a></td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
			$this->pagination( $args['page'], $result['pages'], $base_url );
		}
		echo '</div></div>';
	}

	private function render_update_detail( $id ) {
		$u = ET_Updates::get( $id );
		echo '<div class="wrap et-wrap">';
		echo '<a class="et-back-link" href="' . esc_url( admin_url( 'admin.php?page=et-updates' ) ) . '">‹ رجوع لكل التحديثات</a>';
		if ( ! $u ) { echo '<div class="et-empty">التحديث غير موجود.</div></div>'; return; }

		$employee = ET_Employees::get( $u->employee_id );
		$dept = $u->department_id ? ET_Departments::get( $u->department_id ) : null;

		echo '<h1 class="et-h1">تفاصيل التحديث #' . intval( $u->id ) . '</h1>';
		echo '<div class="et-panel">';
		echo '<div class="et-detail-grid">';
		echo '<div class="et-detail-item"><label>الموظف</label><div class="val">' . esc_html( $employee->name ?? '—' ) . '</div></div>';
		echo '<div class="et-detail-item"><label>القسم</label><div class="val">' . esc_html( $dept->name ?? '—' ) . '</div></div>';
		echo '<div class="et-detail-item"><label>التاريخ</label><div class="val">' . esc_html( $u->update_date ) . '</div></div>';
		echo '<div class="et-detail-item"><label>وقت الإرسال</label><div class="val">' . esc_html( mysql2date( 'Y-m-d H:i', $u->created_at ) ) . '</div></div>';
		echo '</div>';

		echo '<div class="et-detail-item" style="margin-bottom:14px;"><label>الإنجاز</label><div class="val">' . esc_html( $u->achievements ?: '—' ) . '</div></div>';
		echo '<div class="et-detail-item" style="margin-bottom:14px;"><label>الأعمال الحالية</label><div class="val">' . esc_html( $u->current_tasks ?: '—' ) . '</div></div>';

		if ( $u->has_problem ) {
			echo '<div class="et-detail-grid">';
			echo '<div class="et-detail-item"><label>المشكلة</label><div class="val">' . esc_html( $u->problem_description ?: '—' ) . '</div></div>';
			echo '<div class="et-detail-item"><label>الأولوية / التأثير</label><div class="val">' . $this->badge_priority( $u->problem_priority ) . ' — ' . esc_html( $u->problem_impact ?: '' ) . '</div></div>';
			echo '</div>';
		}
		if ( $u->assistance_required ) {
			echo '<div class="et-detail-item" style="margin-bottom:14px;"><label>المساعدة المطلوبة</label><div class="val">' . esc_html( $u->assistance_description ?: '—' ) . '</div></div>';
		}
		echo '<div class="et-detail-item" style="margin-bottom:14px;"><label>خطة الغد</label><div class="val">' . esc_html( $u->tomorrow_plan ?: '—' ) . '</div></div>';

		$attachments = ! empty( $u->attachments ) ? json_decode( $u->attachments, true ) : array();
		echo '<div class="et-detail-item"><label>المرفقات</label><div class="val">';
		if ( empty( $attachments ) ) {
			echo '—';
		} else {
			foreach ( $attachments as $att ) {
				echo '<a href="' . esc_url( $att['url'] ) . '" target="_blank" rel="noopener">📎 ' . esc_html( $att['name'] ) . '</a><br/>';
			}
		}
		echo '</div></div>';

		if ( $u->has_problem ) {
			$problem = null;
			global $wpdb;
			$ptable = ET_Database::table( 'problems' );
			$problem = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$ptable} WHERE update_id = %d", $u->id ) );
			if ( $problem ) {
				echo '<p style="margin-top:16px;"><a class="button" href="' . esc_url( admin_url( 'admin.php?page=et-problems&view=' . $problem->id ) ) . '">فتح المشكلة المرتبطة ‹</a></p>';
			}
		}

		echo '</div></div>';
	}

	/* ---------------------------------------------------------------
	   المشاكل
	--------------------------------------------------------------- */
	public function page_problems() {
		$this->guard();

		if ( ! empty( $_POST['et_action'] ) && $_POST['et_action'] === 'update_problem' && check_admin_referer( 'et_admin_action', 'et_nonce' ) ) {
			$pid = intval( $_POST['problem_id'] ?? 0 );
			ET_Problems::update_admin_fields( $pid, array(
				'status'      => sanitize_text_field( $_POST['status'] ?? '' ),
				'priority'    => sanitize_text_field( $_POST['priority'] ?? '' ),
				'assigned_to' => sanitize_text_field( $_POST['assigned_to'] ?? '' ),
				'solution'    => $_POST['solution'] ?? '',
			) );
			echo '<script>window.location.href=' . wp_json_encode( admin_url( 'admin.php?page=et-problems&view=' . $pid . '&saved=1' ) ) . ';</script>';
			return;
		}

		if ( ! empty( $_GET['view'] ) ) {
			$this->render_problem_detail( intval( $_GET['view'] ) );
			return;
		}

		$args = array(
			'search'        => sanitize_text_field( $_GET['s'] ?? '' ),
			'employee_id'   => intval( $_GET['employee_id'] ?? 0 ),
			'department_id' => intval( $_GET['department_id'] ?? 0 ),
			'priority'      => sanitize_text_field( $_GET['priority'] ?? '' ),
			'status'        => sanitize_text_field( $_GET['status'] ?? '' ),
			'date_from'     => sanitize_text_field( $_GET['date_from'] ?? '' ),
			'date_to'       => sanitize_text_field( $_GET['date_to'] ?? '' ),
			'page'          => max( 1, intval( $_GET['paged'] ?? 1 ) ),
			'per_page'      => 20,
		);
		$result = ET_Problems::query( $args );
		$departments = ET_Departments::all();
		$base_url = remove_query_arg( 'paged' );

		echo '<div class="wrap et-wrap">';
		echo '<h1 class="et-h1">المشاكل</h1>';
		echo '<p class="et-sub">متابعة المشاكل المُبلَّغ عنها، حالتها، والمسؤول عن حلها.</p>';

		echo '<form method="get" class="et-filters">';
		echo '<input type="hidden" name="page" value="et-problems" />';
		echo '<div><label>بحث</label><input type="text" name="s" value="' . esc_attr( $args['search'] ) . '" placeholder="عنوان أو وصف المشكلة"></div>';
		echo '<div><label>القسم</label><select name="department_id"><option value="">الكل</option>';
		foreach ( $departments as $d ) echo '<option value="' . intval( $d->id ) . '" ' . selected( $args['department_id'], $d->id, false ) . '>' . esc_html( $d->name ) . '</option>';
		echo '</select></div>';
		echo '<div><label>الأولوية</label><select name="priority"><option value="">الكل</option>';
		foreach ( ET_Problems::PRIORITIES as $p ) echo '<option value="' . esc_attr( $p ) . '" ' . selected( $args['priority'], $p, false ) . '>' . esc_html( ET_Problems::priority_label( $p ) ) . '</option>';
		echo '</select></div>';
		echo '<div><label>الحالة</label><select name="status"><option value="">الكل</option>';
		foreach ( ET_Problems::STATUSES as $s ) echo '<option value="' . esc_attr( $s ) . '" ' . selected( $args['status'], $s, false ) . '>' . esc_html( ET_Problems::status_label( $s ) ) . '</option>';
		echo '</select></div>';
		echo '<div><label>من تاريخ</label><input type="date" name="date_from" value="' . esc_attr( $args['date_from'] ) . '"></div>';
		echo '<div><label>إلى تاريخ</label><input type="date" name="date_to" value="' . esc_attr( $args['date_to'] ) . '"></div>';
		echo '<div><button type="submit" class="button">تصفية</button></div>';
		echo '</form>';

		echo '<div class="et-panel">';
		if ( empty( $result['items'] ) ) {
			echo '<div class="et-empty">لا توجد مشاكل مطابقة.</div>';
		} else {
			echo '<table class="et-table"><thead><tr><th>#</th><th>الموظف</th><th>القسم</th><th>العنوان</th><th>الأولوية</th><th>الحالة</th><th>التاريخ</th><th></th></tr></thead><tbody>';
			foreach ( $result['items'] as $p ) {
				$view_url = admin_url( 'admin.php?page=et-problems&view=' . $p->id );
				echo '<tr>';
				echo '<td>#' . intval( $p->id ) . '</td>';
				echo '<td>' . esc_html( $p->employee_name ) . '</td>';
				echo '<td>' . esc_html( $p->department_name ?: '—' ) . '</td>';
				echo '<td>' . esc_html( mb_substr( $p->title, 0, 45 ) ) . '</td>';
				echo '<td>' . $this->badge_priority( $p->priority ) . '</td>';
				echo '<td>' . $this->badge_status( $p->status ) . '</td>';
				echo '<td>' . esc_html( mysql2date( 'Y-m-d', $p->created_at ) ) . '</td>';
				echo '<td><a href="' . esc_url( $view_url ) . '">عرض ‹</a></td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
			$this->pagination( $args['page'], $result['pages'], $base_url );
		}
		echo '</div></div>';
	}

	private function render_problem_detail( $id ) {
		$p = ET_Problems::get( $id );
		echo '<div class="wrap et-wrap">';
		echo '<a class="et-back-link" href="' . esc_url( admin_url( 'admin.php?page=et-problems' ) ) . '">‹ رجوع لكل المشاكل</a>';
		if ( ! $p ) { echo '<div class="et-empty">المشكلة غير موجودة.</div></div>'; return; }

		if ( ! empty( $_GET['saved'] ) ) {
			echo '<div class="notice notice-success"><p>تم حفظ التغييرات.</p></div>';
		}

		$employee = ET_Employees::get( $p->employee_id );
		$dept = $p->department_id ? ET_Departments::get( $p->department_id ) : null;

		echo '<h1 class="et-h1">المشكلة #' . intval( $p->id ) . '</h1>';

		echo '<div class="et-panel">';
		echo '<div class="et-detail-grid">';
		echo '<div class="et-detail-item"><label>الموظف</label><div class="val">' . esc_html( $employee->name ?? '—' ) . '</div></div>';
		echo '<div class="et-detail-item"><label>القسم</label><div class="val">' . esc_html( $dept->name ?? '—' ) . '</div></div>';
		echo '<div class="et-detail-item"><label>التاريخ</label><div class="val">' . esc_html( mysql2date( 'Y-m-d', $p->created_at ) ) . '</div></div>';
		echo '<div class="et-detail-item"><label>الحالة الحالية</label><div class="val">' . $this->badge_status( $p->status ) . ' &nbsp; ' . $this->badge_priority( $p->priority ) . '</div></div>';
		echo '</div>';
		echo '<div class="et-detail-item" style="margin-bottom:6px;"><label>وصف المشكلة</label><div class="val">' . esc_html( $p->description ?: '—' ) . '</div></div>';
		echo '<div class="et-detail-item" style="margin-bottom:6px;"><label>التأثير</label><div class="val">' . esc_html( $p->impact ?: '—' ) . '</div></div>';
		echo '</div>';

		echo '<div class="et-panel">';
		echo '<h2>تحديث المشكلة</h2>';
		echo '<form method="post">';
		wp_nonce_field( 'et_admin_action', 'et_nonce' );
		echo '<input type="hidden" name="et_action" value="update_problem" /><input type="hidden" name="problem_id" value="' . intval( $p->id ) . '" />';
		echo '<div class="et-form-grid">';
		echo '<div><label>الحالة</label><select name="status">';
		foreach ( ET_Problems::STATUSES as $s ) echo '<option value="' . esc_attr( $s ) . '" ' . selected( $p->status, $s, false ) . '>' . esc_html( ET_Problems::status_label( $s ) ) . '</option>';
		echo '</select></div>';
		echo '<div><label>الأولوية</label><select name="priority">';
		foreach ( ET_Problems::PRIORITIES as $pr ) echo '<option value="' . esc_attr( $pr ) . '" ' . selected( $p->priority, $pr, false ) . '>' . esc_html( ET_Problems::priority_label( $pr ) ) . '</option>';
		echo '</select></div>';
		echo '<div><label>المسؤول</label><input type="text" name="assigned_to" value="' . esc_attr( $p->assigned_to ) . '" placeholder="اسم المسؤول عن الحل"></div>';
		echo '<div class="full"><label>الحل / ملاحظات</label><textarea name="solution" rows="3">' . esc_textarea( $p->solution ) . '</textarea></div>';
		echo '</div>';
		echo '<p style="margin-top:14px;"><button type="submit" class="button button-primary">حفظ التغييرات</button></p>';
		echo '</form>';
		echo '</div>';

		$history = ET_Problems::history( $p->id );
		echo '<div class="et-panel">';
		echo '<h2>سجل المشكلة (Timeline)</h2>';
		echo '<div class="et-timeline">';
		foreach ( $history as $h ) {
			echo '<div class="et-timeline-item">';
			echo '<div class="meta">' . esc_html( mysql2date( 'Y-m-d H:i', $h->created_at ) ) . '</div>';
			echo '<div class="notes">' . esc_html( $h->notes ) . '</div>';
			echo '</div>';
		}
		echo '</div></div>';

		echo '</div>';
	}

	/* ---------------------------------------------------------------
	   الإعدادات
	--------------------------------------------------------------- */
	public function page_settings() {
		$this->guard();
		$notice = '';

		if ( ! empty( $_POST['et_action'] ) && $_POST['et_action'] === 'save_settings' && check_admin_referer( 'et_admin_action', 'et_nonce' ) ) {
			ET_Settings::update( array(
				'system_name'             => sanitize_text_field( $_POST['system_name'] ?? 'متابعة' ),
				'end_of_day_time'         => sanitize_text_field( $_POST['end_of_day_time'] ?? '15:00' ),
				'reminder_time'           => et_normalize_hhmm( $_POST['reminder_time'] ?? '' ) ?: '13:30',
				'evening_reminder_time'   => et_normalize_hhmm( $_POST['evening_reminder_time'] ?? '' ),
				'edit_window_minutes'     => max( 0, intval( $_POST['edit_window_minutes'] ?? 120 ) ),
				'allow_multiple_updates'  => isset( $_POST['allow_multiple_updates'] ) ? 1 : 0,
				'employee_session_hours'  => max( 1, min( ET_Security::MAX_EMPLOYEE_SESSION_HOURS, intval( $_POST['employee_session_hours'] ?? 12 ) ) ),
				'show_gamification'       => isset( $_POST['show_gamification'] ) ? 1 : 0,
				'max_upload_size_mb'      => max( 1, intval( $_POST['max_upload_size_mb'] ?? 10 ) ),
				'delete_data_on_uninstall'=> isset( $_POST['delete_data_on_uninstall'] ) ? 1 : 0,
				'whatsapp_notify_enabled'  => isset( $_POST['whatsapp_notify_enabled'] ) ? 1 : 0,
				'whatsapp_admin_phone'     => sanitize_text_field( $_POST['whatsapp_admin_phone'] ?? '' ),
				'whatsapp_phone_number_id' => sanitize_text_field( $_POST['whatsapp_phone_number_id'] ?? '' ),
				'whatsapp_access_token'    => sanitize_text_field( $_POST['whatsapp_access_token'] ?? '' ),
				'whatsapp_template_name'   => sanitize_text_field( $_POST['whatsapp_template_name'] ?? '' ),
				'whatsapp_template_lang'   => sanitize_text_field( $_POST['whatsapp_template_lang'] ?? '' ),
				'email_notify_enabled'    => isset( $_POST['email_notify_enabled'] ) ? 1 : 0,
				'email_notify_address'    => sanitize_email( $_POST['email_notify_address'] ?? '' ),
				'email_notify_subject'    => sanitize_text_field( $_POST['email_notify_subject'] ?? '' ),
				'employee_reminder_enabled' => isset( $_POST['employee_reminder_enabled'] ) ? 1 : 0,
				'employee_reminder_subject' => sanitize_text_field( $_POST['employee_reminder_subject'] ?? '' ),
				'task_reminder_enabled'   => isset( $_POST['task_reminder_enabled'] ) ? 1 : 0,
				'task_reminder_time'      => et_normalize_hhmm( $_POST['task_reminder_time'] ?? '' ) ?: '08:00',
				'task_reminder_subject'   => sanitize_text_field( $_POST['task_reminder_subject'] ?? '' ),
				'leave_annual_base_days'  => max( 0, (float) ( $_POST['leave_annual_base_days'] ?? 21 ) ),
				'leave_monthly_accrual'   => max( 0, (float) ( $_POST['leave_monthly_accrual'] ?? 2.5 ) ),
			) );
			$notice .= '<div class="notice notice-success"><p>تم حفظ الإعدادات.</p></div>';
		}

		if ( ! empty( $_POST['et_action'] ) && $_POST['et_action'] === 'test_whatsapp' && check_admin_referer( 'et_admin_action', 'et_nonce' ) ) {
			$diag = ET_WhatsApp::notify_admin( "🔔 رسالة تجريبية من نظام متابعة\nإذا وصلتك هذه الرسالة فالربط يعمل بنجاح.", true );

			if ( ! empty( $diag['ok'] ) ) {
				$notice = '<div class="notice notice-success"><p>تم الإرسال بنجاح عبر واتساب بزنس (HTTP ' . intval( $diag['code'] ) . '). تحقق من واتساب خلال لحظات.</p></div>';
			} else {
				$reason = ! empty( $diag['error'] )
					? esc_html( $diag['error'] )
					: 'رد ميتا (HTTP ' . intval( $diag['code'] ?? 0 ) . '): ' . esc_html( wp_strip_all_tags( (string) ( $diag['body'] ?? '' ) ) );
				$notice = '<div class="notice notice-error"><p><strong>فشل الإرسال.</strong><br>' . $reason . '</p></div>';
			}
		}

		if ( ! empty( $_POST['et_action'] ) && $_POST['et_action'] === 'test_email' && check_admin_referer( 'et_admin_action', 'et_nonce' ) ) {
			$diag = ET_EmailNotify::notify_admin( "🔔 رسالة تجريبية من نظام متابعة\nإذا وصلتك هذه الرسالة فالربط يعمل بنجاح.", true );

			if ( ! empty( $diag['ok'] ) ) {
				$notice = '<div class="notice notice-success"><p>تم إرسال البريد بنجاح إلى ' . esc_html( $diag['to'] ) . '. تحقق من صندوق الوارد (وملف السبام).</p></div>';
			} else {
				$notice = '<div class="notice notice-error"><p><strong>فشل إرسال البريد.</strong><br>' . esc_html( $diag['error'] ?? 'خطأ غير معروف.' ) . '</p></div>';
			}
		}

		if ( ! empty( $_POST['et_action'] ) && $_POST['et_action'] === 'test_employee_reminder' && check_admin_referer( 'et_admin_action', 'et_nonce' ) ) {
			$emp_id = intval( $_POST['test_employee_id'] ?? 0 );
			$emp    = $emp_id ? ET_Employees::get( $emp_id ) : null;

			if ( ! $emp ) {
				$notice = '<div class="notice notice-error"><p>الرجاء اختيار موظف من القائمة.</p></div>';
			} elseif ( empty( $emp->email ) || ! is_email( $emp->email ) ) {
				$notice = '<div class="notice notice-error"><p><strong>فشل الإرسال.</strong><br>الموظف "' . esc_html( $emp->name ) . '" لا يملك بريدًا إلكترونيًا صحيحًا مسجّلاً في ملفه. عدّل ملفه من صفحة الموظفين وأضف بريده أولاً.</p></div>';
			} else {
				$reminder_settings = ET_Settings::get();
				$subject = trim( $reminder_settings['employee_reminder_subject'] ?? '' );
				if ( empty( $subject ) ) $subject = 'تذكير: لم يصلنا تحديثك اليومي';

				$diag = ET_EmailNotify::send(
					$emp->email,
					$subject,
					'مرحبًا ' . $emp->name . "،\n\nهذه رسالة تذكير تجريبية للتأكد من وصول التذكيرات إلى بريدك.\n\nشكرًا لتعاونك.",
					true
				);

				if ( ! empty( $diag['ok'] ) ) {
					$notice = '<div class="notice notice-success"><p>تم إرسال بريد تجريبي بنجاح إلى ' . esc_html( $emp->email ) . ' (بريد ' . esc_html( $emp->name ) . '). تحقق من صندوق الوارد وملف السبام عنده.</p></div>';
				} else {
					$notice = '<div class="notice notice-error"><p><strong>فشل الإرسال إلى ' . esc_html( $emp->email ) . '.</strong><br>' . esc_html( $diag['error'] ?? 'خطأ غير معروف.' ) . '</p></div>';
				}
			}
		}

		if ( ! empty( $_POST['et_action'] ) && $_POST['et_action'] === 'run_reminder_now' && check_admin_referer( 'et_admin_action', 'et_nonce' ) ) {
			$reminder_settings = ET_Settings::get();

			if ( empty( $reminder_settings['employee_reminder_enabled'] ) ) {
				$notice = '<div class="notice notice-error"><p><strong>لم يتم التشغيل.</strong><br>خيار "تفعيل تذكير الموظفين" غير مفعّل حاليًا. فعّله واحفظ أولاً.</p></div>';
			} else {
				$not_submitted = ET_Updates::not_submitted_today(); // بدون فلترة فترة، لعرض تشخيص شامل
				if ( empty( $not_submitted ) ) {
					$notice = '<div class="notice notice-info"><p>لا يوجد حاليًا أي موظف نشط لم يرسل تحديثه اليوم — لذلك لا يوجد من يُرسل له تذكير. (هذا سلوك متوقع لو الجميع رفع تحديثه، وليس خللاً)</p></div>';
				} else {
					$results = et_send_employee_reminders( $not_submitted, $reminder_settings['employee_reminder_subject'] ?? '' );

					$rows = '';
					$fail_count = 0;
					foreach ( $results as $r ) {
						if ( $r['ok'] ) {
							$rows .= '<li style="color:#0a7d2c;">✅ ' . esc_html( $r['name'] ) . ' (' . esc_html( $r['email'] ?: '—' ) . ')</li>';
						} else {
							$fail_count++;
							$rows .= '<li style="color:#a00;">❌ ' . esc_html( $r['name'] ) . ( $r['email'] ? ' (' . esc_html( $r['email'] ) . ')' : '' ) . ' — ' . esc_html( $r['error'] ?? 'خطأ غير معروف' ) . '</li>';
						}
					}

					$class = $fail_count > 0 ? 'notice-warning' : 'notice-success';
					$notice = '<div class="notice ' . $class . '"><p><strong>تم تشغيل فحص التذكير الآن.</strong> عدد الموظفين الذين لم يرسلوا تحديثهم: ' . count( $results ) . '، منهم ' . $fail_count . ' فشل إرسال التذكير لهم.</p><ul style="margin-right:16px;">' . $rows . '</ul></div>';
				}
			}
		}

		if ( ! empty( $_POST['et_action'] ) && $_POST['et_action'] === 'run_task_reminder_now' && check_admin_referer( 'et_admin_action', 'et_nonce' ) ) {
			$task_reminder_settings = ET_Settings::get();

			if ( empty( $task_reminder_settings['task_reminder_enabled'] ) ) {
				$notice = '<div class="notice notice-error"><p><strong>لم يتم التشغيل.</strong><br>خيار "تفعيل تذكير المهام المتأخرة" غير مفعّل حاليًا. فعّله واحفظ أولاً.</p></div>';
			} else {
				$yesterday = ET_Time::date( 'Y-m-d', '-1 day' );
				$grouped   = ET_WeeklyTasks::incomplete_by_employee_as_of( $yesterday );
				if ( empty( $grouped ) ) {
					$notice = '<div class="notice notice-info"><p>لا يوجد حاليًا أي مهمة مستحقة (أمس أو قبله) لم تُنجز بعد — لذلك لا يوجد من يُرسل له تذكير. (سلوك متوقع لو الجميع أنجز مهامه، وليس خللاً)</p></div>';
				} else {
					$results = et_send_task_reminders( $grouped, $task_reminder_settings['task_reminder_subject'] ?? '' );

					$rows = '';
					$fail_count = 0;
					foreach ( $results as $r ) {
						if ( $r['ok'] ) {
							$rows .= '<li style="color:#0a7d2c;">✅ ' . esc_html( $r['name'] ) . ' (' . esc_html( $r['email'] ?: '—' ) . ') — ' . (int) $r['tasks_count'] . ' مهمة</li>';
						} else {
							$fail_count++;
							$rows .= '<li style="color:#a00;">❌ ' . esc_html( $r['name'] ) . ( $r['email'] ? ' (' . esc_html( $r['email'] ) . ')' : '' ) . ' — ' . esc_html( $r['error'] ?? 'خطأ غير معروف' ) . '</li>';
						}
					}

					$class = $fail_count > 0 ? 'notice-warning' : 'notice-success';
					$notice = '<div class="notice ' . $class . '"><p><strong>تم تشغيل فحص مهام الأمس الآن.</strong> عدد الموظفين الذين لديهم مهام متأخرة من أمس: ' . count( $results ) . '، منهم ' . $fail_count . ' فشل إرسال التذكير لهم.</p><ul style="margin-right:16px;">' . $rows . '</ul></div>';
				}
			}
		}

		if ( ! empty( $_POST['et_action'] ) && $_POST['et_action'] === 'test_task_reminder' && check_admin_referer( 'et_admin_action', 'et_nonce' ) ) {
			$emp_id = intval( $_POST['test_task_employee_id'] ?? 0 );
			$emp    = $emp_id ? ET_Employees::get( $emp_id ) : null;

			if ( ! $emp ) {
				$notice = '<div class="notice notice-error"><p>الرجاء اختيار موظف من القائمة.</p></div>';
			} elseif ( empty( $emp->email ) || ! is_email( $emp->email ) ) {
				$notice = '<div class="notice notice-error"><p><strong>فشل الإرسال.</strong><br>الموظف "' . esc_html( $emp->name ) . '" لا يملك بريدًا إلكترونيًا صحيحًا مسجّلاً في ملفه. عدّل ملفه من صفحة الموظفين وأضف بريده أولاً.</p></div>';
			} else {
				$task_reminder_settings = ET_Settings::get();
				$subject = trim( $task_reminder_settings['task_reminder_subject'] ?? '' );
				if ( empty( $subject ) ) $subject = 'تذكير: لديك مهام لم تُنجز من يوم أمس';

				$diag = ET_EmailNotify::send(
					$emp->email,
					$subject,
					'مرحبًا ' . $emp->name . "،\n\nهذه رسالة تذكير تجريبية للتأكد من وصول تذكير مهام الأمس إلى بريدك (بدون قائمة مهام فعلية — اختبار توصيل فقط).\n\nشكرًا لتعاونك.",
					true
				);

				if ( ! empty( $diag['ok'] ) ) {
					$notice = '<div class="notice notice-success"><p>تم إرسال بريد تجريبي بنجاح إلى ' . esc_html( $emp->email ) . ' (بريد ' . esc_html( $emp->name ) . '). تحقق من صندوق الوارد وملف السبام عنده.</p></div>';
				} else {
					$notice = '<div class="notice notice-error"><p><strong>فشل الإرسال إلى ' . esc_html( $emp->email ) . '.</strong><br>' . esc_html( $diag['error'] ?? 'خطأ غير معروف' ) . '</p></div>';
				}
			}
		}

		if ( ! empty( $_POST['et_action'] ) && $_POST['et_action'] === 'rotate_cron_key' && check_admin_referer( 'et_admin_action', 'et_nonce' ) ) {
			ET_Security::rotate_cron_secret();
			$notice = '<div class="notice notice-success"><p>تم توليد رابط كرون خارجي جديد. حدّث الرابط في خدمة الكرون الخارجية (أو الاستضافة) بالرابط الجديد بالأسفل — الرابط القديم لن يعمل بعد الآن.</p></div>';
		}

		$s = ET_Settings::get();

		echo '<div class="wrap et-wrap">';
		echo $notice;
		echo '<h1 class="et-h1">إعدادات النظام</h1>';
		echo '<p class="et-sub">إعدادات المرحلة الأولى (Phase 1). ستُضاف إعدادات Email والذكاء الاصطناعي مع المراحل القادمة.</p>';

		echo '<div class="et-panel">';
		echo '<form method="post">';
		wp_nonce_field( 'et_admin_action', 'et_nonce' );
		echo '<input type="hidden" name="et_action" value="save_settings" />';
		echo '<div class="et-form-grid">';

		foreach ( array( 'whatsapp_admin_phone', 'whatsapp_phone_number_id', 'whatsapp_access_token', 'whatsapp_template_name', 'whatsapp_template_lang','email_notify_address', 'email_notify_subject', 'employee_reminder_subject', 'task_reminder_time', 'task_reminder_subject' ) as $field ) {
			echo '<input type="hidden" name="' . esc_attr( $field ) . '" value="' . esc_attr( $s[ $field ] ) . '" />';
		}
		if ( $s['whatsapp_notify_enabled'] ) echo '<input type="hidden" name="whatsapp_notify_enabled" value="1" />';
		if ( $s['email_notify_enabled'] ) echo '<input type="hidden" name="email_notify_enabled" value="1" />';
		if ( $s['employee_reminder_enabled'] ) echo '<input type="hidden" name="employee_reminder_enabled" value="1" />';
		if ( $s['task_reminder_enabled'] ) echo '<input type="hidden" name="task_reminder_enabled" value="1" />';

		echo '<div><label>اسم النظام</label><input type="text" name="system_name" value="' . esc_attr( $s['system_name'] ) . '"></div>';
		echo '<div><label>وقت نهاية الدوام</label><input type="text" name="end_of_day_time" value="' . esc_attr( $s['end_of_day_time'] ) . '" placeholder="15:00"></div>';
		echo '<div><label>وقت تذكير الفترة الصباحية</label><input type="time" name="reminder_time" value="' . esc_attr( $s['reminder_time'] ) . '"></div>';
		echo '<div><label>وقت تذكير الفترة المسائية (اتركه فارغًا لتعطيل الفترة المسائية)</label><input type="time" name="evening_reminder_time" value="' . esc_attr( $s['evening_reminder_time'] ) . '"></div>';
		echo '<div><label>مدة تعديل التحديث (دقيقة)</label><input type="number" name="edit_window_minutes" value="' . esc_attr( $s['edit_window_minutes'] ) . '" min="0"></div>';
		echo '<div><label>مدة جلسة الموظف (ساعة)</label><input type="number" name="employee_session_hours" value="' . esc_attr( $s['employee_session_hours'] ) . '" min="1" max="' . (int) ET_Security::MAX_EMPLOYEE_SESSION_HOURS . '"></div>';
		echo '<div><label>أقصى حجم مرفق (ميجابايت)</label><input type="number" name="max_upload_size_mb" value="' . esc_attr( $s['max_upload_size_mb'] ) . '" min="1"></div>';
		echo '<div><label>الرصيد السنوي الأساسي للإجازة (يوم)</label><input type="number" step="0.5" name="leave_annual_base_days" value="' . esc_attr( $s['leave_annual_base_days'] ) . '" min="0"></div>';
		echo '<div><label>الرصيد المُضاف شهريًا للإجازة (يوم)</label><input type="number" step="0.1" name="leave_monthly_accrual" value="' . esc_attr( $s['leave_monthly_accrual'] ) . '" min="0"></div>';

		echo '<div class="full"><label><input type="checkbox" name="allow_multiple_updates" ' . checked( $s['allow_multiple_updates'], 1, false ) . '> السماح بأكثر من تحديث يوميًا لكل موظف</label></div>';
		echo '<div class="full"><label><input type="checkbox" name="show_gamification" ' . checked( $s['show_gamification'], 1, false ) . '> إظهار عناصر التحفيز البسيطة (أيام متتالية 🔥) للموظف</label></div>';
		if ( ! empty( $s['delete_data_on_uninstall'] ) ) echo '<input type="hidden" name="delete_data_on_uninstall" value="1" />';

		echo '</div>';
		echo '<p style="margin-top:16px;"><button type="submit" class="button button-primary">حفظ الإعدادات</button></p>';
		echo '</form></div>';

		echo '<div class="et-panel" style="margin-top:20px;">';
		echo '<h2 style="margin-top:0;">إشعار واتساب فوري للإدارة (واتساب بزنس الرسمي — Meta Cloud API)</h2>';
		echo '<p class="et-sub">عند تفعيل هذا الخيار، تصل رسالة واتساب فورية لرقم الإدارة بمجرد أن يرسل أي موظف تحديثه اليومي، عبر واجهة واتساب بزنس الرسمية من ميتا (لا CallMeBot ولا أي وسيط خارجي آخر).</p>';
		echo '<ol style="margin:0 0 16px 0; padding-inline-start:20px; line-height:1.9;">';
		echo '<li>من <a href="https://business.facebook.com/wa/manage/phone-numbers/" target="_blank" rel="noopener">WhatsApp Manager</a> بحساب Meta Business الخاص بكم: انسخوا <strong>Phone Number ID</strong> للرقم المُرسِل، وضعوه بالحقل بالأسفل.</li>';
		echo '<li>من إعدادات النظام (Business Settings) ← "System Users": أنشئوا مستخدم نظام بصلاحية على أصل واتساب، وولّدوا منه <strong>Access Token دائم</strong> (لا يُستخدم التوكن المؤقت لأنه ينتهي خلال ساعات) — الصقوه بالحقل المخصص.</li>';
		echo '<li>من نفس WhatsApp Manager ← "Message Templates": أنشئوا قالبًا جديدًا (تصنيف Utility) بجسم رسالة يحتوي متغيرًا واحدًا فقط، مثلاً: <code dir="ltr">{{1}}</code>، وانتظروا اعتماده من ميتا (عادة دقائق إلى ساعات). أدخلوا اسم القالب ولغته بالضبط كما اعتُمد بالأسفل (مثال اللغة: <code dir="ltr">ar</code> أو <code dir="ltr">ar_SA</code>).</li>';
		echo '<li>أدخلوا رقم واتساب الإدارة (المستلم) بصيغة دولية بدون علامة + أو أصفار في البداية (مثال: 9665xxxxxxxx).</li>';
		echo '</ol>';
		echo '<p class="et-warn" style="margin-top:0;">ملاحظة فنية مهمة: القالب المعتمد ضروري لأن واتساب بزنس الرسمي يرفض أي رسالة نصية عادية تُرسل للإدارة إن لم تكن قد راسلت الرقم خلال آخر 24 ساعة — بينما القالب المعتمد يصل في أي وقت، وهذا هو المطلوب لإشعار يومي متكرر تلقائيًا.</p>';

		echo '<form method="post">';
		wp_nonce_field( 'et_admin_action', 'et_nonce' );
		echo '<input type="hidden" name="et_action" value="save_settings" />';

		// إبقاء بقية الإعدادات كما هي عند حفظ هذا النموذج المنفصل
		foreach ( array( 'system_name', 'end_of_day_time', 'reminder_time', 'evening_reminder_time', 'edit_window_minutes', 'employee_session_hours', 'max_upload_size_mb', 'email_notify_address', 'email_notify_subject', 'employee_reminder_subject', 'task_reminder_time', 'task_reminder_subject' ) as $field ) {
			echo '<input type="hidden" name="' . esc_attr( $field ) . '" value="' . esc_attr( $s[ $field ] ) . '" />';
		}
		if ( $s['allow_multiple_updates'] ) echo '<input type="hidden" name="allow_multiple_updates" value="1" />';
		if ( $s['show_gamification'] ) echo '<input type="hidden" name="show_gamification" value="1" />';
		if ( $s['delete_data_on_uninstall'] ) echo '<input type="hidden" name="delete_data_on_uninstall" value="1" />';
		if ( $s['email_notify_enabled'] ) echo '<input type="hidden" name="email_notify_enabled" value="1" />';
		if ( $s['employee_reminder_enabled'] ) echo '<input type="hidden" name="employee_reminder_enabled" value="1" />';
		if ( $s['task_reminder_enabled'] ) echo '<input type="hidden" name="task_reminder_enabled" value="1" />';

		echo '<div class="et-form-grid">';
		echo '<div class="full"><label><input type="checkbox" name="whatsapp_notify_enabled" ' . checked( $s['whatsapp_notify_enabled'], 1, false ) . '> تفعيل إشعار واتساب الفوري للإدارة عند رفع أي موظف تحديثه</label></div>';
		echo '<div><label>رقم واتساب الإدارة المستلم (دولي، بدون +)</label><input type="text" name="whatsapp_admin_phone" value="' . esc_attr( $s['whatsapp_admin_phone'] ) . '" placeholder="9665xxxxxxxx"></div>';
		echo '<div><label>Phone Number ID (رقم واتساب البزنس المُرسِل)</label><input type="text" name="whatsapp_phone_number_id" value="' . esc_attr( $s['whatsapp_phone_number_id'] ) . '" placeholder="1029384756xxxxx" autocomplete="off"></div>';
		echo '<div><label>Access Token الدائم</label><input type="text" name="whatsapp_access_token" value="' . esc_attr( $s['whatsapp_access_token'] ) . '" placeholder="EAAG..." autocomplete="off"></div>';
		echo '<div><label>اسم القالب المعتمد (Template Name)</label><input type="text" name="whatsapp_template_name" value="' . esc_attr( $s['whatsapp_template_name'] ) . '" placeholder="admin_notify"></div>';
		echo '<div><label>لغة القالب المعتمد (Language Code)</label><input type="text" name="whatsapp_template_lang" value="' . esc_attr( $s['whatsapp_template_lang'] ) . '" placeholder="ar_SA"></div>';
		echo '</div>';

		echo '<p style="margin-top:16px;"><button type="submit" class="button button-primary">حفظ إعدادات واتساب</button></p>';
		echo '</form>';

		echo '<form method="post" style="margin-top:8px;">';
		wp_nonce_field( 'et_admin_action', 'et_nonce' );
		echo '<input type="hidden" name="et_action" value="test_whatsapp" />';
		echo '<button type="submit" class="button">إرسال رسالة تجريبية الآن</button>';
		echo '</form>';

		echo '</div>';

		echo '<div class="et-panel" style="margin-top:20px;">';
		echo '<h2 style="margin-top:0;">إشعار بريد إلكتروني فوري للإدارة</h2>';
		echo '<p class="et-sub">عند تفعيل هذا الخيار، يصل بريد إلكتروني فوري للعنوان المحدد بمجرد أن يرسل أي موظف تحديثه اليومي. يعتمد الإرسال على إعدادات البريد في الموقع (wp_mail) — إذا كان استضافتك تمنع الإرسال الافتراضي عبر PHP، يُنصح بتثبيت بلجن SMTP (مثل WP Mail SMTP) وربطه بحساب بريد فعلي.</p>';

		echo '<form method="post">';
		wp_nonce_field( 'et_admin_action', 'et_nonce' );
		echo '<input type="hidden" name="et_action" value="save_settings" />';

		foreach ( array( 'system_name', 'end_of_day_time', 'reminder_time', 'evening_reminder_time', 'edit_window_minutes', 'employee_session_hours', 'max_upload_size_mb', 'whatsapp_admin_phone', 'whatsapp_phone_number_id', 'whatsapp_access_token', 'whatsapp_template_name', 'whatsapp_template_lang','employee_reminder_subject', 'task_reminder_time', 'task_reminder_subject' ) as $field ) {
			echo '<input type="hidden" name="' . esc_attr( $field ) . '" value="' . esc_attr( $s[ $field ] ) . '" />';
		}
		if ( $s['allow_multiple_updates'] ) echo '<input type="hidden" name="allow_multiple_updates" value="1" />';
		if ( $s['show_gamification'] ) echo '<input type="hidden" name="show_gamification" value="1" />';
		if ( $s['delete_data_on_uninstall'] ) echo '<input type="hidden" name="delete_data_on_uninstall" value="1" />';
		if ( $s['whatsapp_notify_enabled'] ) echo '<input type="hidden" name="whatsapp_notify_enabled" value="1" />';
		if ( $s['employee_reminder_enabled'] ) echo '<input type="hidden" name="employee_reminder_enabled" value="1" />';
		if ( $s['task_reminder_enabled'] ) echo '<input type="hidden" name="task_reminder_enabled" value="1" />';

		echo '<div class="et-form-grid">';
		echo '<div class="full"><label><input type="checkbox" name="email_notify_enabled" ' . checked( $s['email_notify_enabled'], 1, false ) . '> تفعيل إشعار البريد الإلكتروني الفوري للإدارة عند رفع أي موظف تحديثه</label></div>';
		echo '<div><label>البريد الإلكتروني للإدارة</label><input type="email" name="email_notify_address" value="' . esc_attr( $s['email_notify_address'] ) . '" placeholder="admin@example.com"></div>';
		echo '<div><label>عنوان الرسالة (Subject)</label><input type="text" name="email_notify_subject" value="' . esc_attr( $s['email_notify_subject'] ) . '"></div>';
		echo '</div>';

		echo '<p style="margin-top:16px;"><button type="submit" class="button button-primary">حفظ إعدادات البريد</button></p>';
		echo '</form>';

		echo '<form method="post" style="margin-top:8px;">';
		wp_nonce_field( 'et_admin_action', 'et_nonce' );
		echo '<input type="hidden" name="et_action" value="test_email" />';
		echo '<button type="submit" class="button">إرسال بريد تجريبي الآن</button>';
		echo '</form>';

		echo '</div>';

		echo '<div class="et-panel" style="margin-top:20px;">';
		echo '<h2 style="margin-top:0;">تذكير الموظفين الذين لم يرسلوا تحديثهم</h2>';
		echo '<p class="et-sub">عند تفعيل هذا الخيار، يُرسل بريد تذكير تلقائي إلى كل موظف لم يرسل تحديثه اليومي بعد، وذلك عند وصول "وقت التذكير اليومي" المحدد أعلاه في إعدادات النظام العامة (الحقل: وقت التذكير اليومي). يعتمد التذكير على البريد الإلكتروني المسجّل لكل موظف في ملفه الشخصي — تأكد أن كل موظف لديه بريد إلكتروني صحيح.</p>';

		echo '<form method="post">';
		wp_nonce_field( 'et_admin_action', 'et_nonce' );
		echo '<input type="hidden" name="et_action" value="save_settings" />';

		foreach ( array( 'system_name', 'end_of_day_time', 'reminder_time', 'evening_reminder_time', 'edit_window_minutes', 'employee_session_hours', 'max_upload_size_mb', 'whatsapp_admin_phone', 'whatsapp_phone_number_id', 'whatsapp_access_token', 'whatsapp_template_name', 'whatsapp_template_lang','email_notify_address', 'email_notify_subject', 'task_reminder_time', 'task_reminder_subject' ) as $field ) {
			echo '<input type="hidden" name="' . esc_attr( $field ) . '" value="' . esc_attr( $s[ $field ] ) . '" />';
		}
		if ( $s['allow_multiple_updates'] ) echo '<input type="hidden" name="allow_multiple_updates" value="1" />';
		if ( $s['show_gamification'] ) echo '<input type="hidden" name="show_gamification" value="1" />';
		if ( $s['delete_data_on_uninstall'] ) echo '<input type="hidden" name="delete_data_on_uninstall" value="1" />';
		if ( $s['whatsapp_notify_enabled'] ) echo '<input type="hidden" name="whatsapp_notify_enabled" value="1" />';
		if ( $s['email_notify_enabled'] ) echo '<input type="hidden" name="email_notify_enabled" value="1" />';
		if ( $s['task_reminder_enabled'] ) echo '<input type="hidden" name="task_reminder_enabled" value="1" />';

		echo '<div class="et-form-grid">';
		echo '<div class="full"><label><input type="checkbox" name="employee_reminder_enabled" ' . checked( $s['employee_reminder_enabled'], 1, false ) . '> تفعيل تذكير الموظفين عبر البريد الإلكتروني عند عدم إرسال التحديث اليومي</label></div>';
		echo '<div><label>عنوان رسالة التذكير (Subject)</label><input type="text" name="employee_reminder_subject" value="' . esc_attr( $s['employee_reminder_subject'] ) . '"></div>';
		echo '</div>';

		echo '<p style="margin-top:16px;"><button type="submit" class="button button-primary">حفظ إعدادات التذكير</button></p>';
		echo '</form>';

		echo '<form method="post" style="margin-top:8px; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">';
		wp_nonce_field( 'et_admin_action', 'et_nonce' );
		echo '<input type="hidden" name="et_action" value="test_employee_reminder" />';
		echo '<label style="margin:0;">اختبر التذكير على موظف: </label>';
		echo '<select name="test_employee_id">';
		echo '<option value="">— اختر موظفًا —</option>';
		foreach ( ET_Employees::all() as $emp_row ) {
			$label = $emp_row->name . ( ! empty( $emp_row->email ) ? ' (' . $emp_row->email . ')' : ' — بلا بريد إلكتروني!' );
			echo '<option value="' . esc_attr( $emp_row->id ) . '">' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<button type="submit" class="button">إرسال تذكير تجريبي الآن</button>';
		echo '</form>';
		echo '<p class="et-sub" style="margin-top:6px;">هذا الاختبار يرسل فورًا بغض النظر عن الوقت أو الفترة المحددة، ويعرض سبب الفشل الحقيقي إن وجد — استخدمه للتأكد من صحة بريد الموظف قبل انتظار وقت التذكير الفعلي.</p>';

		echo '<form method="post" style="margin-top:12px;">';
		wp_nonce_field( 'et_admin_action', 'et_nonce' );
		echo '<input type="hidden" name="et_action" value="run_reminder_now" />';
		echo '<button type="submit" class="button button-secondary">تشغيل فحص التذكير الآن لكل الموظفين المتأخرين</button>';
		echo '</form>';
		echo '<p class="et-sub" style="margin-top:6px;">هذا الزر يشغّل بالضبط نفس المنطق الذي يشغّله الكرون التلقائي عند وصول الوقت — يفيدك للتأكد إن التذكير التلقائي شغّال فعليًا (بدل انتظار الوقت المحدد)، ويوريك قائمة كل موظف لم يرسل تحديثه اليوم مع حالة إرسال التذكير له.</p>';

		echo '<hr style="margin:20px 0; border:none; border-top:1px solid #ddd;">';
		echo '<h3 style="margin:0 0 8px;">تشخيص: هل التذكير التلقائي (بدون ضغط أي زر) شغّال فعلاً على هذا الموقع؟</h3>';

		$last_tick = (int) get_option( 'et_last_cron_tick', 0 );
		if ( ! $last_tick ) {
			echo '<div class="notice notice-error" style="margin:0 0 10px;"><p><strong>لم يُنفَّذ الفحص التلقائي ولا مرة واحدة بعد منذ تفعيل الإضافة.</strong> تأكد من إضافة سطر كرون Laravel على السيرفر: <code dir="ltr">* * * * * cd /path/to/project &amp;&amp; php artisan schedule:run</code> — أو استخدم الرابط الخارجي بالأسفل.</p></div>';
		} else {
			$ago = time() - $last_tick;
			if ( $ago <= 20 * MINUTE_IN_SECONDS ) {
				echo '<div class="notice notice-success" style="margin:0 0 10px;"><p>✅ يعمل بشكل طبيعي — آخر فحص تلقائي كان منذ ' . esc_html( human_time_diff( $last_tick, time() ) ) . '.</p></div>';
			} elseif ( $ago <= 2 * HOUR_IN_SECONDS ) {
				echo '<div class="notice notice-warning" style="margin:0 0 10px;"><p>⚠️ آخر فحص تلقائي كان منذ ' . esc_html( human_time_diff( $last_tick, time() ) ) . ' — من المفترض أن يعمل كل 15 دقيقة. تحقق من أن أمر <code dir="ltr">php artisan schedule:run</code> مضاف لكرون السيرفر، أو استخدم الرابط الخارجي بالأسفل.</p></div>';
			} else {
				echo '<div class="notice notice-error" style="margin:0 0 10px;"><p><strong>❌ متوقف على الأغلب.</strong> آخر فحص تلقائي كان منذ ' . esc_html( human_time_diff( $last_tick, time() ) ) . ' فقط — أطول من المتوقع بكثير. الأرجح أن كرون السيرفر (php artisan schedule:run) غير مضاف أو متوقف. استخدم الرابط الخارجي بالأسفل لضمان وصول التذكير 100%.</p></div>';
			}
		}


		$cron_url = add_query_arg( 'key', ET_Security::get_cron_secret(), rest_url( 'employee-tracker/v1/cron/tick' ) );
		echo '<div class="et-panel" style="background:#f6f7f7; margin:0 0 10px;">';
		echo '<p style="margin-top:0;"><strong>الحل المضمون 100%:</strong> أضف هذا الرابط لخدمة كرون خارجية مجانية مثل <a href="https://cron-job.org" target="_blank" rel="noopener">cron-job.org</a> أو EasyCron (أو كرون حقيقي في لوحة الاستضافة إن توفر)، واضبطها على استدعائه كل 15 دقيقة. هذا يضمن وصول التذكير حتى لو لم يزر أحد الموقع في وقت التذكير، كبديل عن كرون السيرفر إن لم يتوفر.</p>';
		echo '<input type="text" readonly onclick="this.select();" value="' . esc_attr( $cron_url ) . '" style="width:100%; direction:ltr; text-align:left; font-family:monospace; padding:8px; box-sizing:border-box;">';
		echo '<p class="et-sub" style="margin:6px 0 0;">هذا الرابط يحتوي رمزًا سريًا خاصًا بموقعك — لا تشاركه إلا مع خدمة الكرون التي تثق بها. لو تسرّب أو أردت تغييره لأي سبب، ولّد رابطًا جديدًا:</p>';
		echo '</div>';

		echo '<form method="post">';
		wp_nonce_field( 'et_admin_action', 'et_nonce' );
		echo '<input type="hidden" name="et_action" value="rotate_cron_key" />';
		echo '<button type="submit" class="button" onclick="return confirm(\'سيتوقف الرابط القديم عن العمل فورًا. متأكد؟\');">توليد رابط جديد (إبطال القديم)</button>';
		echo '</form>';

		echo '</div>';

		echo '<div class="et-panel" style="margin-top:20px;">';
		echo '<h2 style="margin-top:0;">تذكير المهام الأسبوعية المتأخرة من يوم أمس</h2>';
		echo '<p class="et-sub">عند تفعيل هذا الخيار، يُرسل بريد تذكير تلقائي إلى كل موظف كان لديه مهمة أسبوعية مُسندة له أمس ولم يُنجزها (أي لم تصل لحالة "قيد المراجعة" أو "مكتملة")، وذلك عند وصول الوقت المحدد أدناه. يُرسل التذكير مرة واحدة فقط يوميًا، ولا يُرسل إطلاقًا لو كان أمس يوم جمعة أو سبت (خارج أيام العمل).</p>';

		echo '<form method="post">';
		wp_nonce_field( 'et_admin_action', 'et_nonce' );
		echo '<input type="hidden" name="et_action" value="save_settings" />';

		foreach ( array( 'system_name', 'end_of_day_time', 'reminder_time', 'evening_reminder_time', 'edit_window_minutes', 'employee_session_hours', 'max_upload_size_mb', 'whatsapp_admin_phone', 'whatsapp_phone_number_id', 'whatsapp_access_token', 'whatsapp_template_name', 'whatsapp_template_lang','email_notify_address', 'email_notify_subject', 'employee_reminder_subject' ) as $field ) {
			echo '<input type="hidden" name="' . esc_attr( $field ) . '" value="' . esc_attr( $s[ $field ] ) . '" />';
		}
		if ( $s['allow_multiple_updates'] ) echo '<input type="hidden" name="allow_multiple_updates" value="1" />';
		if ( $s['show_gamification'] ) echo '<input type="hidden" name="show_gamification" value="1" />';
		if ( $s['delete_data_on_uninstall'] ) echo '<input type="hidden" name="delete_data_on_uninstall" value="1" />';
		if ( $s['whatsapp_notify_enabled'] ) echo '<input type="hidden" name="whatsapp_notify_enabled" value="1" />';
		if ( $s['email_notify_enabled'] ) echo '<input type="hidden" name="email_notify_enabled" value="1" />';
		if ( $s['employee_reminder_enabled'] ) echo '<input type="hidden" name="employee_reminder_enabled" value="1" />';

		echo '<div class="et-form-grid">';
		echo '<div class="full"><label><input type="checkbox" name="task_reminder_enabled" ' . checked( $s['task_reminder_enabled'], 1, false ) . '> تفعيل تذكير المهام المتأخرة عبر البريد الإلكتروني</label></div>';
		echo '<div><label>وقت إرسال التذكير</label><input type="time" name="task_reminder_time" value="' . esc_attr( $s['task_reminder_time'] ) . '"></div>';
		echo '<div><label>عنوان رسالة التذكير (Subject)</label><input type="text" name="task_reminder_subject" value="' . esc_attr( $s['task_reminder_subject'] ) . '"></div>';
		echo '</div>';

		echo '<p style="margin-top:16px;"><button type="submit" class="button button-primary">حفظ إعدادات تذكير المهام</button></p>';
		echo '</form>';

		echo '<form method="post" style="margin-top:8px; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">';
		wp_nonce_field( 'et_admin_action', 'et_nonce' );
		echo '<input type="hidden" name="et_action" value="test_task_reminder" />';
		echo '<label style="margin:0;">اختبر التذكير على موظف: </label>';
		echo '<select name="test_task_employee_id">';
		echo '<option value="">— اختر موظفًا —</option>';
		foreach ( ET_Employees::all() as $emp_row ) {
			$label = $emp_row->name . ( ! empty( $emp_row->email ) ? ' (' . $emp_row->email . ')' : ' — بلا بريد إلكتروني!' );
			echo '<option value="' . esc_attr( $emp_row->id ) . '">' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<button type="submit" class="button">إرسال تذكير تجريبي الآن</button>';
		echo '</form>';
		echo '<p class="et-sub" style="margin-top:6px;">هذا الاختبار يرسل بريدًا تجريبيًا فوريًا (بدون قائمة مهام فعلية) للتأكد من صحة بريد الموظف فقط — لا يعتمد على وجود مهام متأخرة فعلية له.</p>';

		echo '<form method="post" style="margin-top:12px;">';
		wp_nonce_field( 'et_admin_action', 'et_nonce' );
		echo '<input type="hidden" name="et_action" value="run_task_reminder_now" />';
		echo '<button type="submit" class="button button-secondary">تشغيل فحص مهام الأمس المتأخرة الآن لكل الموظفين</button>';
		echo '</form>';
		echo '<p class="et-sub" style="margin-top:6px;">هذا الزر يشغّل بالضبط نفس المنطق الذي يشغّله الكرون التلقائي عند وصول الوقت — يفيدك للتأكد إن تذكير المهام شغّال فعليًا، ويوريك قائمة كل موظف لديه مهام من أمس لم تُنجز مع حالة إرسال التذكير له. (يستخدم نفس تشخيص WP-Cron الظاهر في اللوحة أعلاه، فليس هناك حاجة لتكراره هنا.)</p>';

		echo '</div>';

		echo '<div class="et-panel" style="margin-top:20px;">';
		echo '<h2 style="margin-top:0;">حسابات الإدارة</h2>';
		echo '<p class="et-sub">كل مدير يدخل ببريده وكلمة مروره الخاصة — في هذه اللوحة وفي تبويب "🔐 دخول الإدارة" بصفحة الموظفين. لا يوجد رمز مشترك.</p>';
		echo '<p><a class="button button-primary" href="' . esc_url( url( '/admin/et-admins' ) ) . '">إدارة حسابات المديرين</a></p>';
		echo '</div></div>';
	}

}
