<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?php echo e( $nonce ); ?>">
<title><?php echo e( $system_name ); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@600;700;800&family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?php echo e( asset( 'tracker/tracker.css' ) ); ?>?v=<?php echo e( ET_VERSION ); ?>">
<style>html,body{margin:0;background:#F5F6F8;}</style>
</head>
<body>

		<div id="et-app" class="et-app" dir="rtl" data-rest="<?php echo e( $rest_url ); ?>" data-nonce="<?php echo e( $nonce ); ?>">
			<div class="et-admin-header-tools" id="et-global-tools">
				<input type="text" id="et-header-search" class="et-header-search" placeholder="🔍 بحث عام…" autocomplete="off" hidden>
				<div style="position:relative;">
					<button type="button" class="et-notif-bell" id="et-notif-bell" title="الإشعارات">🔔<span class="et-notif-badge" id="et-notif-badge" hidden>0</span></button>
					<div class="et-notif-panel" id="et-notif-panel" hidden>
						<div class="et-notif-panel-head"><b>الإشعارات</b><button type="button" id="et-notif-mark-read">تحديد الكل كمقروء</button></div>
						<div id="et-notif-list"></div>
						<div id="et-notif-empty" class="et-empty" hidden>لا توجد إشعارات.</div>
					</div>
				</div>
				<button type="button" class="et-dark-toggle" id="et-dark-toggle" title="الوضع الداكن">🌙</button>
			</div>

			<div class="et-card et-card--picker et-login" id="et-view-picker">
				<div class="et-badge-top"><?php echo e( $system_name ); ?></div>
				<h1 class="et-title">تسجيل الدخول</h1>
				<p class="et-subtitle">ادخل ببريدك الإلكتروني وكلمة المرور التي استلمتها من الإدارة</p>
				<div class="et-login-switch" role="tablist" aria-label="نوع الحساب">
					<button type="button" role="tab" class="et-login-switch-btn et-login-switch-btn--active" id="et-login-as-employee" aria-selected="true">موظف</button>
					<button type="button" role="tab" class="et-login-switch-btn" id="et-goto-admin" aria-selected="false">إدارة</button>
				</div>
				<form id="et-login-form" class="et-login-form" novalidate>
					<label class="et-label" for="et-login-email">البريد الإلكتروني</label>
					<input type="email" id="et-login-email" class="et-search-input et-login-input" dir="ltr" autocomplete="username" inputmode="email" required>
					<label class="et-label" for="et-login-password">كلمة المرور</label>
					<div class="et-pass-wrap">
						<input type="password" id="et-login-password" class="et-search-input et-login-input" dir="ltr" autocomplete="current-password" required>
						<button type="button" class="et-pass-toggle" data-target="et-login-password" aria-label="إظهار كلمة المرور" aria-pressed="false">إظهار</button>
					</div>
					<div id="et-picker-msg" class="et-inline-msg et-inline-msg--error" role="alert" hidden></div>
					<button type="submit" class="et-submit-btn" id="et-login-submit">دخول</button>
				</form>
				<p class="et-login-help">نسيت كلمة المرور؟ تواصل مع الإدارة لإعادة تعيينها.</p>
			</div>

			<div class="et-card" id="et-view-form" hidden>
				<!-- نفس هيكل لوحة الإدارة تمامًا (شريط جانبي + محتوى رئيسي)، بنفس الأصناف/الألوان،
				     بدل بطاقة "تبويبات أفقية" المخصصة أصلًا لهاتف بعرض ضيق — بطلب أن تتطابق واجهة
				     الموظف مع واجهة الإدارة شكلاً وتنظيمًا. -->
				<div id="et-employee-shell" class="et-admin-shell">
					<aside class="et-admin-sidebar">
						<div class="et-admin-sidebar-brand">
							<span id="et-greeting">👋 مرحبًا</span>
							<div class="et-streak-badge" id="et-streak-badge" hidden>🔥 <span id="et-streak-count">0</span> أيام متتالية</div>
						</div>
						<nav class="et-admin-nav">
							<button type="button" class="et-tab" id="et-tab-attendance">📍 <span>الحضور والانصراف</span></button>
							<button type="button" class="et-tab et-tab--active" id="et-tab-update">📝 <span>التحديث اليومي</span></button>
							<button type="button" class="et-tab" id="et-tab-tasks">📋 <span>مهامي الأسبوعية</span><span class="et-tab-count" id="et-tasks-count" hidden>0</span></button>
							<button type="button" class="et-tab" id="et-tab-help">🆘 <span>طلب مساعدة</span></button>
							<button type="button" class="et-tab" id="et-tab-replies">📨 <span>ردود الإدارة</span><span class="et-tab-count" id="et-replies-count" hidden>0</span></button>
							<button type="button" class="et-tab" id="et-tab-performance">📊 <span>مستوى أدائي</span></button>
							<button type="button" class="et-tab" id="et-tab-leave">🏖️ <span>الإجازات</span></button>
							<button type="button" class="et-tab" id="et-tab-permission">🕐 <span>الاستئذانات</span></button>
							<button type="button" class="et-tab" id="et-tab-password">🔑 <span>كلمة المرور</span></button>
						</nav>
						<button type="button" class="et-admin-sidebar-logout" id="et-change-name">‹ تسجيل الخروج</button>
					</aside>

					<div class="et-admin-main">
						<div id="et-panel-attendance" hidden>
							<div class="et-att-card">
								<div class="et-att-card-head">
									<span class="et-att-card-label">حالة اليوم</span>
									<span id="et-att-today-date" class="et-sub"></span>
								</div>
								<div id="et-att-status-badge" class="et-att-status et-att-status--gray">⚪ لم يسجل بعد</div>
								<div id="et-att-times" class="et-att-times" hidden>
									<div class="et-att-time-box"><span class="et-att-time-label">الحضور</span><span id="et-att-time-in" class="et-att-time-value">—</span></div>
									<div class="et-att-time-box"><span class="et-att-time-label">الانصراف</span><span id="et-att-time-out" class="et-att-time-value">—</span></div>
									<div class="et-att-time-box"><span class="et-att-time-label">ساعات العمل</span><span id="et-att-time-worked" class="et-att-time-value">—</span></div>
								</div>
								<div id="et-att-extra-info" class="et-sub" hidden style="margin-top:8px;"></div>
								<div id="et-att-msg" class="et-inline-msg" hidden style="margin-top:12px;"></div>
								<button type="button" class="et-submit-btn" id="et-att-checkin-btn" hidden>📍 تسجيل الحضور</button>
								<button type="button" class="et-submit-btn" id="et-att-checkout-btn" hidden style="background:var(--et-gold);">📍 تسجيل الانصراف</button>
							</div>

							<div class="et-tasks-heading" style="margin-top:20px;">سجل حضوري (آخر 30 يومًا)</div>
							<div id="et-att-history-loading" class="et-empty">جارِ التحميل…</div>
							<div id="et-att-history-empty" class="et-empty" hidden>لا يوجد سجل حضور بعد.</div>
							<div id="et-att-history-list"></div>
						</div>

						<div id="et-panel-update">
							<div id="et-already-msg" class="et-inline-msg et-inline-msg--info" hidden></div>

							<form id="et-update-form" class="et-form">
								<label class="et-label" style="margin-top:0;">ماذا أنجزت اليوم؟ <span class="et-req">*</span></label>
								<textarea name="achievements" class="et-textarea" rows="3" required placeholder="اكتب باختصار أهم الأعمال التي أنجزتها اليوم…"></textarea>

								<label class="et-label">ما الأعمال التي تعمل عليها حاليًا؟</label>
								<textarea name="current_tasks" class="et-textarea" rows="2" placeholder="اختياري"></textarea>

								<label class="et-label">هل واجهت مشكلة اليوم؟</label>
								<div class="et-radio-row">
									<label class="et-radio"><input type="radio" name="has_problem" value="0" checked> لا</label>
									<label class="et-radio"><input type="radio" name="has_problem" value="1"> نعم</label>
								</div>

								<div id="et-problem-fields" class="et-subfields" hidden>
									<label class="et-label">ما المشكلة التي واجهتها؟</label>
									<textarea name="problem_description" class="et-textarea" rows="2" placeholder="صف المشكلة بإيجاز"></textarea>

									<div class="et-grid-2">
										<div>
											<label class="et-label">مستوى المشكلة</label>
											<select name="problem_priority" class="et-select">
												<option value="low">منخفض</option>
												<option value="medium" selected>متوسط</option>
												<option value="high">عالي</option>
												<option value="critical">حرج</option>
											</select>
										</div>
										<div>
											<label class="et-label">تأثير المشكلة</label>
											<select name="problem_impact" class="et-select">
												<option value="لا يوجد تأثير كبير">لا يوجد تأثير كبير</option>
												<option value="تأخير بسيط">تأخير بسيط</option>
												<option value="تأخير في العمل">تأخير في العمل</option>
												<option value="توقف العمل">توقف العمل</option>
											</select>
										</div>
									</div>

									<label class="et-label">هل تحتاج إلى مساعدة؟</label>
									<div class="et-radio-row">
										<label class="et-radio"><input type="radio" name="assistance_required" value="0" checked> لا</label>
										<label class="et-radio"><input type="radio" name="assistance_required" value="1"> نعم</label>
									</div>
									<div id="et-assistance-field" hidden>
										<label class="et-label">ما المساعدة التي تحتاجها؟</label>
										<textarea name="assistance_description" class="et-textarea" rows="2"></textarea>
									</div>
								</div>

								<label class="et-label">ماذا ستعمل غدًا؟</label>
								<textarea name="tomorrow_plan" class="et-textarea" rows="2" placeholder="اختياري"></textarea>

								<label class="et-label">📎 إرفاق ملفات (اختياري)</label>
								<p class="et-file-hint">تقدر تضيف أكثر من ملف: اختر عدة ملفات دفعة واحدة (مع الضغط باستمرار على Ctrl أو Cmd)، أو اضغط "اختيار ملفات" أكثر من مرة وكل مرة تُضاف لقائمتك بدل ما تستبدلها.</p>
								<!-- id="et-file-input" بدون name عمدًا: لا نعتمد على FileList الأصلية للحقل عند
								     الإرسال (كل ضغطة على "اختيار ملفات" تستبدلها بالكامل بمعيار المتصفح، فيختفي أي
								     ملف مختار سابقًا). بدلها نبني قائمتنا الخاصة بجافاسكربت (state.selectedFiles)
								     تتراكم مع كل اختيار، ونُرفقها يدويًا بأسماء attachments[] عند الإرسال. -->
								<input type="file" id="et-file-input" class="et-file" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx" />
								<div id="et-file-list" class="et-file-list"></div>

								<div id="et-form-error" class="et-inline-msg et-inline-msg--error" hidden></div>

								<button type="submit" class="et-submit-btn" id="et-submit-btn">إرسال التحديث</button>
							</form>
						</div>

						<div id="et-panel-tasks" hidden>
							<div class="et-view-toggle">
								<button type="button" class="et-view-toggle-btn et-view-toggle-btn--active" id="et-my-view-toggle-list">📋 قائمة</button>
								<button type="button" class="et-view-toggle-btn" id="et-my-view-toggle-board">🗂 لوحتي</button>
							</div>
							<div id="et-tasks-loading" class="et-empty">جارِ التحميل…</div>
							<div id="et-tasks-empty" class="et-empty" hidden>لا توجد مهام أسبوعية حاليًا 👍</div>
							<div id="et-tasks-wrap" hidden>
								<div id="et-tasks-prev-section" hidden>
									<div class="et-tasks-heading">مهام سابقة غير منجزة</div>
									<ul class="et-tasks-list" id="et-tasks-prev-list"></ul>
								</div>
								<div id="et-tasks-current-section" hidden>
									<div class="et-tasks-heading">مهام هذا الأسبوع</div>
									<div id="et-tasks-current-list"></div>
								</div>
							</div>
							<div id="et-my-board" class="et-my-board" hidden></div>
						</div>

						<div id="et-panel-help" hidden>
							<p class="et-subtitle" style="margin-bottom:12px;">اختر مهمة مشتركة تعمل عليها مع زميل، ثم اختر الزميل: يمكنك طلب مساعدته بها، أو — إذا أنجزت جزءك منها — تحويل الدور له مباشرة ليكمل باقي العمل على نفس المهمة.</p>

							<div id="et-help-no-tasks" class="et-empty" hidden>لا توجد لديك حاليًا أي مهام لطلب المساعدة بشأنها.</div>

							<div id="et-help-form-wrap" hidden>
								<label class="et-label" style="margin-top:0;">المهمة المشتركة</label>
								<select id="et-help-task-select" class="et-select"></select>

								<label class="et-label">الزميل</label>
								<select id="et-help-peer-select" class="et-select"></select>

								<div id="et-help-pending-box"></div>

								<div style="margin-top:16px;padding-top:14px;border-top:1px solid var(--et-line);">
									<label class="et-label" style="margin-top:0;">🆘 طلب مساعدة (تبقيان تعملان على المهمة معًا)</label>
									<label class="et-label">نوع المساعدة</label>
									<select id="et-help-type-select" class="et-select"></select>
									<label class="et-label">رسالة</label>
									<textarea id="et-help-message-input" class="et-textarea" rows="2" placeholder="وضّح المطلوب…"></textarea>
									<label class="et-label">📎 إرفاق ملف العمل (اختياري)</label>
									<input type="file" id="et-help-file-input" class="et-file" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx">
									<div id="et-help-file-name" class="et-sub" hidden></div>
									<div id="et-help-msg" class="et-inline-msg" hidden></div>
									<button type="button" class="et-submit-btn" id="et-help-submit-btn" style="margin-top:8px;">إرسال طلب المساعدة</button>
								</div>

								<div style="margin-top:16px;padding-top:14px;border-top:1px solid var(--et-line);">
									<label class="et-label" style="margin-top:0;">✅ أنجزت جزئي — تحويل الدور للزميل</label>
									<p class="et-sub" style="margin-top:2px;">يُسجَّل جزءك من هذه المهمة كمُنجَز (100%) تلقائيًا، ويصل الزميل المختار بإشعار وبريد أنّ دوره جاء ليكمل باقي العمل.</p>
									<textarea id="et-handoff-message-input" class="et-textarea" rows="2" placeholder="رسالة اختيارية توضّح ما أنجزته وما تبقّى…"></textarea>
									<label class="et-label">📎 إرفاق ملف العمل الذي اشتغلت عليه (اختياري)</label>
									<input type="file" id="et-handoff-file-input" class="et-file" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx">
									<div id="et-handoff-file-name" class="et-sub" hidden></div>
									<div id="et-handoff-msg" class="et-inline-msg" hidden></div>
									<button type="button" class="et-submit-btn" id="et-handoff-submit-btn" style="margin-top:8px;">أنجزت جزئي وتحويل الدور</button>
								</div>
							</div>
						</div>

						<div id="et-panel-replies" hidden>
							<div id="et-replies-loading" class="et-empty">جارِ التحميل…</div>
							<div id="et-replies-empty" class="et-empty" hidden>لا توجد تعليقات من الإدارة على تحديثاتك بعد.</div>
							<div id="et-replies-list"></div>
						</div>

						<div id="et-panel-performance" hidden>
							<input type="month" id="et-perf-month" class="et-perf-month">
							<div id="et-perf-loading" class="et-empty">جارِ التحميل…</div>
							<div id="et-perf-content"></div>
						</div>

						<div id="et-panel-leave" hidden>
							<div class="et-view-toggle">
								<button type="button" class="et-view-toggle-btn et-view-toggle-btn--active" id="et-leave-subtab-annual">🏖️ الإجازة السنوية</button>
								<button type="button" class="et-view-toggle-btn" id="et-leave-subtab-sick">🩺 الأعذار الطبية / المرضية</button>
							</div>

							<div id="et-leave-annual-section">
								<div id="et-leave-balance-loading" class="et-empty">جارِ التحميل…</div>
								<div id="et-leave-balance-cards" class="et-leave-balance-cards" hidden>
									<div class="et-leave-balance-card"><div class="et-leave-balance-num" id="et-lb-remaining">0</div><div class="et-leave-balance-label">الرصيد المتبقي</div></div>
									<div class="et-leave-balance-card"><div class="et-leave-balance-num" id="et-lb-used">0</div><div class="et-leave-balance-label">الرصيد المستخدم</div></div>
									<div class="et-leave-balance-card"><div class="et-leave-balance-num" id="et-lb-entitled">0</div><div class="et-leave-balance-label">الرصيد المستحق</div></div>
								</div>
								<div class="et-task-progress-wrap" id="et-lb-progress-wrap" hidden>
									<div class="et-task-progress-bar"><div class="et-task-progress-fill" id="et-lb-progress-fill" style="width:0%"></div></div>
									<div class="et-task-progress-label" id="et-lb-progress-label">0%</div>
								</div>

								<button type="button" class="et-submit-btn" id="et-leave-new-btn" style="margin-top:14px;">+ طلب إجازة سنوية جديدة</button>

								<div id="et-leave-form-wrap" class="et-form" hidden style="margin-top:12px;">
									<div class="et-grid-2">
										<div>
											<label class="et-label" style="margin-top:0;">تاريخ البداية <span class="et-req">*</span></label>
											<input type="date" id="et-leave-start" class="et-select">
										</div>
										<div>
											<label class="et-label" style="margin-top:0;">تاريخ النهاية <span class="et-req">*</span></label>
											<input type="date" id="et-leave-end" class="et-select">
										</div>
									</div>
									<div id="et-leave-days-preview" class="et-sub" style="margin-top:6px;"></div>
									<label class="et-label">ملاحظات (اختياري)</label>
									<textarea id="et-leave-notes" class="et-textarea" rows="2"></textarea>
									<div id="et-leave-msg" class="et-inline-msg" hidden></div>
									<button type="button" class="et-submit-btn" id="et-leave-submit-btn" style="margin-top:8px;">إرسال الطلب</button>
								</div>

								<div class="et-tasks-heading" style="margin-top:18px;">طلباتي السابقة</div>
								<div id="et-leave-list-loading" class="et-empty">جارِ التحميل…</div>
								<div id="et-leave-list-empty" class="et-empty" hidden>لا توجد طلبات إجازة سنوية بعد.</div>
								<div id="et-leave-list"></div>
							</div>

							<div id="et-leave-sick-section" hidden>
								<button type="button" class="et-submit-btn" id="et-sick-new-btn">+ تقديم إجازة مرضية / عذر طبي</button>

								<div id="et-sick-form-wrap" class="et-form" hidden style="margin-top:12px;">
									<div class="et-grid-2">
										<div>
											<label class="et-label" style="margin-top:0;">تاريخ البداية <span class="et-req">*</span></label>
											<input type="date" id="et-sick-start" class="et-select">
										</div>
										<div>
											<label class="et-label" style="margin-top:0;">تاريخ النهاية <span class="et-req">*</span></label>
											<input type="date" id="et-sick-end" class="et-select">
										</div>
									</div>
									<label class="et-label">📎 إرفاق العذر / التقرير الطبي</label>
									<input type="file" id="et-sick-file-input" class="et-file" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx">
									<div id="et-sick-file-name" class="et-sub" hidden></div>
									<label class="et-label">ملاحظات (اختياري)</label>
									<textarea id="et-sick-notes" class="et-textarea" rows="2"></textarea>
									<div id="et-sick-msg" class="et-inline-msg" hidden></div>
									<button type="button" class="et-submit-btn" id="et-sick-submit-btn" style="margin-top:8px;">إرسال</button>
								</div>

								<div class="et-tasks-heading" style="margin-top:18px;">سجل الإجازات المرضية</div>
								<div id="et-sick-list-loading" class="et-empty">جارِ التحميل…</div>
								<div id="et-sick-list-empty" class="et-empty" hidden>لا توجد إجازات مرضية سابقة.</div>
								<div id="et-sick-list"></div>
							</div>
						</div>

						<div id="et-panel-permission" hidden>
							<button type="button" class="et-submit-btn" id="et-permission-new-btn">+ طلب استئذان جديد</button>

							<div id="et-permission-form-wrap" class="et-form" hidden style="margin-top:12px;">
								<div class="et-grid-2">
									<div>
										<label class="et-label" style="margin-top:0;">التاريخ <span class="et-req">*</span></label>
										<input type="date" id="et-permission-date" class="et-select">
									</div>
									<div>
										<label class="et-label" style="margin-top:0;">سبب الاستئذان</label>
										<input type="text" id="et-permission-reason" class="et-select" placeholder="مثال: مراجعة طبية">
									</div>
								</div>
								<div class="et-grid-2">
									<div>
										<label class="et-label">وقت الخروج <span class="et-req">*</span></label>
										<input type="time" id="et-permission-time-out" class="et-select">
									</div>
									<div>
										<label class="et-label">الوقت المتوقع للعودة (اختياري)</label>
										<input type="time" id="et-permission-time-return" class="et-select">
									</div>
								</div>
								<label class="et-label">ملاحظات (اختياري)</label>
								<textarea id="et-permission-notes" class="et-textarea" rows="2"></textarea>
								<div id="et-permission-msg" class="et-inline-msg" hidden></div>
								<button type="button" class="et-submit-btn" id="et-permission-submit-btn" style="margin-top:8px;">إرسال الطلب</button>
							</div>

							<div class="et-tasks-heading" style="margin-top:18px;">طلباتي السابقة</div>
							<div id="et-permission-list-loading" class="et-empty">جارِ التحميل…</div>
							<div id="et-permission-list-empty" class="et-empty" hidden>لا توجد طلبات استئذان بعد.</div>
							<div id="et-permission-list"></div>
						</div>

						<div id="et-panel-password" hidden>
							<form id="et-password-form" class="et-form et-password-form" novalidate>
								<p class="et-subtitle" style="text-align:right;margin:0 0 6px;">غيّر كلمة المرور التي استلمتها من الإدارة إلى كلمة تعرفها أنت فقط.</p>
								<input type="text" id="et-password-username" autocomplete="username" hidden>
								<label class="et-label" for="et-password-current">كلمة المرور الحالية</label>
								<input type="password" id="et-password-current" class="et-select" dir="ltr" autocomplete="current-password" required>
								<label class="et-label" for="et-password-new">كلمة المرور الجديدة <span class="et-hint">(8 أحرف على الأقل)</span></label>
								<input type="password" id="et-password-new" class="et-select" dir="ltr" autocomplete="new-password" minlength="8" required>
								<label class="et-label" for="et-password-confirm">تأكيد كلمة المرور الجديدة</label>
								<input type="password" id="et-password-confirm" class="et-select" dir="ltr" autocomplete="new-password" minlength="8" required>
								<div id="et-password-msg" class="et-inline-msg" role="alert" hidden></div>
								<button type="submit" class="et-submit-btn" id="et-password-submit" style="margin-top:10px;">حفظ كلمة المرور</button>
							</form>
						</div>
					</div>
				</div>
			</div>

			<div class="et-card et-card--success" id="et-view-success" hidden>
				<div class="et-success-icon">✅</div>
				<h1 class="et-title">تم تسجيل تحديثك بنجاح</h1>
				<p class="et-subtitle">شكرًا لك، استمر في الإنجاز 👏</p>
				<button type="button" class="et-link-back" id="et-success-back">‹ الرجوع للصفحة الرئيسية</button>
			</div>

			<div class="et-card" id="et-view-admin" hidden>
				<button type="button" class="et-link-back" id="et-admin-back" hidden>‹ رجوع لصفحة الموظفين</button>

				<div id="et-admin-login-box" class="et-login">
					<div class="et-badge-top"><?php echo e( $system_name ); ?></div>
					<h1 class="et-title">تسجيل الدخول</h1>
					<p class="et-subtitle">دخول الإدارة بحسابك الشخصي — نفس بريدك وكلمة مرورك في لوحة الإدارة</p>
					<div class="et-login-switch" role="tablist" aria-label="نوع الحساب">
					<button type="button" role="tab" class="et-login-switch-btn" id="et-admin-switch-employee" aria-selected="false">موظف</button>
					<button type="button" role="tab" class="et-login-switch-btn et-login-switch-btn--active" id="et-admin-switch-admin" aria-selected="true">إدارة</button>
				</div>
					<form id="et-admin-login-form" class="et-login-form" novalidate>
						<label class="et-label" for="et-admin-email-input">البريد الإلكتروني</label>
						<input type="email" id="et-admin-email-input" class="et-search-input et-login-input" dir="ltr" autocomplete="username" inputmode="email" required>
						<label class="et-label" for="et-admin-code-input">كلمة المرور</label>
						<div class="et-pass-wrap">
							<input type="password" id="et-admin-code-input" class="et-search-input et-login-input" dir="ltr" autocomplete="current-password" required>
							<button type="button" class="et-pass-toggle" data-target="et-admin-code-input" aria-label="إظهار كلمة المرور" aria-pressed="false">إظهار</button>
						</div>
						<div id="et-admin-login-msg" class="et-inline-msg et-inline-msg--error" role="alert" hidden></div>
						<button type="submit" class="et-submit-btn" id="et-admin-login-btn">دخول</button>
					</form>
				</div>

				<div id="et-admin-panel" hidden class="et-admin-shell">
					<aside class="et-admin-sidebar">
						<div class="et-admin-sidebar-brand">🔐 لوحة الإدارة</div>
						<nav class="et-admin-nav">
							<button type="button" class="et-tab et-tab--active" id="et-admin-tab-dashboard">🏠 <span>لوحة القيادة</span></button>
							<button type="button" class="et-tab" id="et-admin-tab-attendance">📍 <span>الحضور والانصراف</span></button>
							<button type="button" class="et-tab" id="et-admin-tab-view">📋 <span>متابعة المهام</span></button>
							<button type="button" class="et-tab" id="et-admin-tab-add">➕ <span>رفع مهام جديدة</span></button>
							<button type="button" class="et-tab" id="et-admin-tab-sent">📨 <span>المهام المرسلة</span></button>
							<button type="button" class="et-tab" id="et-admin-tab-performance">📊 <span>مستوى الأداء</span></button>
							<button type="button" class="et-tab" id="et-admin-tab-projects">🗺️ <span>المشاريع</span></button>
							<button type="button" class="et-tab" id="et-admin-tab-team">👥 <span>الفريق</span></button>
							<button type="button" class="et-tab" id="et-admin-tab-report">📈 <span>تقرير المهام</span></button>
							<button type="button" class="et-tab" id="et-admin-tab-audit">🗂️ <span>سجل التدقيق</span></button>
							<button type="button" class="et-tab" id="et-admin-tab-leave">🏖️ <span>الإجازات السنوية</span><span class="et-tab-count" id="et-admin-leave-count" hidden>0</span></button>
							<button type="button" class="et-tab" id="et-admin-tab-sick">🩺 <span>الإجازات المرضية</span><span class="et-tab-count" id="et-admin-sick-count" hidden>0</span></button>
							<button type="button" class="et-tab" id="et-admin-tab-permission">🕐 <span>الاستئذانات</span><span class="et-tab-count" id="et-admin-permission-count" hidden>0</span></button>
						</nav>
						<button type="button" class="et-admin-sidebar-logout" id="et-admin-logout">خروج ‹</button>
					</aside>

					<div class="et-admin-main">
						<div id="et-admin-panel-attendance" hidden>
							<div class="et-view-toggle">
								<button type="button" class="et-view-toggle-btn et-view-toggle-btn--active" id="et-att-subtab-day">📅 تحضير اليوم</button>
								<button type="button" class="et-view-toggle-btn" id="et-att-subtab-reports">📈 التقارير</button>
								<button type="button" class="et-view-toggle-btn" id="et-att-subtab-audit">🗂️ سجل التعديلات</button>
								<button type="button" class="et-view-toggle-btn" id="et-att-subtab-settings">⚙️ إعدادات الدوام</button>
							</div>

							<div id="et-att-day-section">
								<div class="et-grid-2" style="margin-bottom:12px;">
									<div>
										<label class="et-label" style="margin-top:0;">التاريخ</label>
										<input type="date" id="et-att-day-date" class="et-select">
									</div>
									<div>
										<label class="et-label" style="margin-top:0;">الحالة</label>
										<select id="et-att-day-status-filter" class="et-select">
											<option value="">الكل</option>
											<option value="present">🟢 حاضر</option>
											<option value="late">🟡 متأخر</option>
											<option value="absent">🔴 غائب</option>
											<option value="leave">🔵 إجازة</option>
											<option value="sick">🟣 إجازة مرضية</option>
											<option value="permission">🟠 استئذان</option>
											<option value="not_yet">⚪ لم يسجل بعد</option>
										</select>
									</div>
								</div>
								<div class="et-grid-2" style="margin-bottom:14px;">
									<div>
										<label class="et-label" style="margin-top:0;">بحث (الاسم / القسم)</label>
										<input type="text" id="et-att-day-search" class="et-select" placeholder="اكتب اسم الموظف أو القسم...">
									</div>
								</div>
								<div id="et-att-day-stats" class="et-dash-counts"></div>
								<div id="et-att-day-loading" class="et-empty">جارِ التحميل…</div>
								<div id="et-att-day-empty" class="et-empty" hidden>لا يوجد موظفون نشطون.</div>
								<div class="et-table-wrap" id="et-att-day-table-wrap" hidden>
									<table class="et-table">
										<thead><tr><th>الموظف</th><th>القسم</th><th>الحالة</th><th>الحضور</th><th>الانصراف</th><th>التأخير</th><th>ساعات العمل</th><th></th></tr></thead>
										<tbody id="et-att-day-tbody"></tbody>
									</table>
								</div>
							</div>

							<div id="et-att-reports-section" hidden>
								<div class="et-grid-2" style="margin-bottom:6px;">
									<div>
										<label class="et-label" style="margin-top:0;">الموظف</label>
										<div class="et-search-wrap">
											<input type="text" id="et-att-report-employee-input" class="et-select et-search-input" placeholder="كل الموظفين (اكتب للبحث)" autocomplete="off" style="padding:10px 12px;">
											<input type="hidden" id="et-att-report-employee-id" value="">
											<ul id="et-att-report-employee-results" class="et-search-results et-search-results--overlay"></ul>
										</div>
									</div>
									<div></div>
								</div>
								<div class="et-grid-2" style="margin-bottom:12px;">
									<div><label class="et-label">من تاريخ</label><input type="date" id="et-att-report-from" class="et-select"></div>
									<div><label class="et-label">إلى تاريخ</label><input type="date" id="et-att-report-to" class="et-select"></div>
								</div>
								<button type="button" class="et-submit-btn" id="et-att-report-export-btn" style="width:auto;margin-top:0;padding:10px 18px;">⬇️ Export Excel</button>
								<div id="et-att-report-loading" class="et-empty" style="margin-top:14px;">جارِ التحميل…</div>
								<div id="et-att-report-empty" class="et-empty" hidden>لا توجد بيانات ضمن هذا النطاق.</div>
								<div class="et-table-wrap" id="et-att-report-table-wrap" hidden style="margin-top:14px;">
									<table class="et-table">
										<thead><tr><th>الموظف</th><th>القسم</th><th>حاضر</th><th>متأخر</th><th>غائب</th><th>إجازة</th><th>مرضية</th><th>استئذان</th><th>دقائق التأخير</th><th>ساعات العمل</th></tr></thead>
										<tbody id="et-att-report-tbody"></tbody>
									</table>
								</div>
							</div>

							<div id="et-att-audit-section" hidden>
								<div class="et-grid-2" style="margin-bottom:12px;">
									<div>
										<label class="et-label" style="margin-top:0;">الموظف</label>
										<div class="et-search-wrap">
											<input type="text" id="et-att-audit-employee-input" class="et-select et-search-input" placeholder="كل الموظفين (اكتب للبحث)" autocomplete="off" style="padding:10px 12px;">
											<input type="hidden" id="et-att-audit-employee-id" value="">
											<ul id="et-att-audit-employee-results" class="et-search-results et-search-results--overlay"></ul>
										</div>
									</div>
									<div></div>
								</div>
								<div id="et-att-audit-loading" class="et-empty">جارِ التحميل…</div>
								<div id="et-att-audit-empty" class="et-empty" hidden>لا توجد تعديلات مسجّلة بعد.</div>
								<div id="et-att-audit-list"></div>
							</div>

							<div id="et-att-settings-section" hidden>
								<div class="et-form">
									<p class="et-subtitle" style="margin-bottom:6px;">لكل موظف "فترة" مُحدَّدة أصلاً من صفحة الموظفين (صباحية / مسائية / كلاهما) — تُطبَّق أوقات الفترة المطابقة تلقائيًا عند حساب تأخيره وغيابه. موظف "كلا الفترتين" يُحتسب حضوره/انصرافه على دوام متواصل من بداية الفترة الصباحية حتى نهاية الفترة المسائية.</p>
									<div class="et-tasks-heading" style="margin-top:10px;">☀️ الفترة الصباحية</div>
									<div class="et-grid-2">
										<div><label class="et-label" style="margin-top:0;">بداية الفترة الصباحية</label><input type="time" id="et-att-set-morning-start" class="et-select"></div>
										<div><label class="et-label" style="margin-top:0;">نهاية الفترة الصباحية</label><input type="time" id="et-att-set-morning-end" class="et-select"></div>
									</div>
									<div class="et-tasks-heading">🌙 الفترة المسائية</div>
									<div class="et-grid-2">
										<div><label class="et-label" style="margin-top:0;">بداية الفترة المسائية</label><input type="time" id="et-att-set-evening-start" class="et-select"></div>
										<div><label class="et-label" style="margin-top:0;">نهاية الفترة المسائية</label><input type="time" id="et-att-set-evening-end" class="et-select"></div>
									</div>
									<div class="et-grid-2">
										<div><label class="et-label">فترة السماح (دقيقة)</label><input type="number" min="0" id="et-att-set-grace" class="et-select"></div>
										<div><label class="et-label">عدد ساعات الدوام</label><input type="number" min="0" step="0.5" id="et-att-set-hours" class="et-select"></div>
									</div>
									<label class="et-label">أيام العمل</label>
									<div id="et-att-set-days" class="et-radio-row" style="flex-wrap:wrap;"></div>
									<div class="et-grid-2">
										<div><label class="et-label">Latitude (موقع المعهد)</label><input type="text" id="et-att-set-lat" class="et-select" placeholder="مثال: 24.7136"></div>
										<div><label class="et-label">Longitude (موقع المعهد)</label><input type="text" id="et-att-set-lng" class="et-select" placeholder="مثال: 46.6753"></div>
									</div>
									<label class="et-label">نطاق GPS المسموح (متر)</label>
									<input type="number" min="1" id="et-att-set-radius" class="et-select">
									<label class="et-label">شبكة المعهد المسموح منها بالتحضير (اختياري)</label>
									<input type="text" id="et-att-set-ips" class="et-select" dir="ltr" placeholder="مثال: 212.0.10.25 أو 212.0.10.0/24">
									<p class="et-subtitle" style="margin:4px 0 0;font-size:12px;">موقع GPS يرسله جوال الموظف ويمكن تزويره ببرامج خاصة. إن كتبت هنا عنوان IP الخارجي لإنترنت المعهد، لن يُقبل الحضور/الانصراف إلا من شبكة المعهد. افصل بين أكثر من عنوان بفاصلة. اتركه فارغًا لتعطيل هذا الشرط. (إن كان الموقع خلف Cloudflare يلزم إعداد ET_TRUSTED_PROXY_HEADER أولًا.)</p>
									<div id="et-att-set-msg" class="et-inline-msg" hidden style="margin-top:10px;"></div>
									<button type="button" class="et-submit-btn" id="et-att-set-save" style="margin-top:14px;">حفظ إعدادات الدوام</button>
								</div>
							</div>
						</div>

						<div id="et-admin-panel-team" hidden>
							<div id="et-team-loading" class="et-empty">جارِ التحميل…</div>
							<div id="et-team-list" class="et-team-grid"></div>
						</div>

						<div id="et-admin-panel-report" hidden>
							<p class="et-subtitle" style="margin-bottom:12px;">تقرير أداء المهام لكل موظف: عدد المهام المسندة، المنجزة، المتأخرة (حسب تاريخ الاستحقاق، وحسب يوم العمل المحدد)، ونسبة الإنجاز — اختر نطاق تاريخ (قد يجمع عدة أسابيع معًا) وموظفًا معينًا اختياريًا.</p>
							<div style="margin-bottom:6px;">
								<label class="et-label" style="margin-top:0;">الموظف</label>
								<div class="et-search-wrap">
									<input type="text" id="et-admin-report-filter-employee-input" class="et-select et-search-input" placeholder="كل الموظفين (اكتب للبحث)" autocomplete="off" style="padding:10px 12px;">
									<input type="hidden" id="et-admin-report-filter-employee" value="">
									<ul id="et-admin-report-filter-employee-results" class="et-search-results"></ul>
								</div>
							</div>
							<div class="et-grid-2" style="margin-bottom:12px;">
								<div>
									<label class="et-label" style="margin-top:0;">من تاريخ</label>
									<input type="date" id="et-admin-report-date-from" class="et-select">
								</div>
								<div>
									<label class="et-label" style="margin-top:0;">إلى تاريخ</label>
									<input type="date" id="et-admin-report-date-to" class="et-select">
								</div>
							</div>
							<div id="et-admin-report-loading" class="et-empty">جارِ التحميل…</div>
							<div id="et-admin-report-empty" class="et-empty" hidden>لا توجد مهام مسندة لأي موظف بهذا النطاق.</div>
							<div id="et-admin-report-list"></div>
						</div>

						<div id="et-admin-panel-audit" hidden>
							<div id="et-audit-loading" class="et-empty">جارِ التحميل…</div>
							<div id="et-audit-empty" class="et-empty" hidden>لا توجد أحداث مسجّلة بعد.</div>
							<div id="et-audit-list"></div>
						</div>


						<div id="et-admin-panel-leave" hidden>
							<div class="et-view-toggle">
								<button type="button" class="et-view-toggle-btn et-view-toggle-btn--active" id="et-admin-leave-subtab-requests">الطلبات</button>
								<button type="button" class="et-view-toggle-btn" id="et-admin-leave-subtab-balances">أرصدة الموظفين</button>
								<button type="button" class="et-view-toggle-btn" id="et-admin-leave-subtab-addpast">+ إضافة إجازة سابقة</button>
							</div>

							<div id="et-admin-leave-requests-section">
								<div class="et-grid-2" style="margin-bottom:12px;">
									<div>
										<label class="et-label" style="margin-top:0;">الموظف</label>
										<div class="et-search-wrap">
											<input type="text" id="et-admin-leave-filter-employee-input" class="et-select et-search-input" placeholder="كل الموظفين (اكتب للبحث)" autocomplete="off" style="padding:10px 12px;">
											<input type="hidden" id="et-admin-leave-filter-employee" value="">
											<ul id="et-admin-leave-filter-employee-results" class="et-search-results et-search-results--overlay"></ul>
										</div>
									</div>
									<div>
										<label class="et-label" style="margin-top:0;">الحالة</label>
										<select id="et-admin-leave-filter-status" class="et-select">
											<option value="">الكل</option>
											<option value="pending">قيد المراجعة</option>
											<option value="approved">موافق عليه</option>
											<option value="rejected">مرفوض</option>
											<option value="cancelled">ملغي</option>
										</select>
									</div>
								</div>
								<div id="et-admin-leave-loading" class="et-empty">جارِ التحميل…</div>
								<div id="et-admin-leave-empty" class="et-empty" hidden>لا توجد طلبات إجازة سنوية.</div>
								<div id="et-admin-leave-list"></div>
							</div>

							<div id="et-admin-leave-balances-section" hidden>
								<div id="et-admin-balances-loading" class="et-empty">جارِ التحميل…</div>
								<div id="et-admin-balances-list"></div>
							</div>

							<div id="et-admin-leave-addpast-section" hidden>
								<div class="et-form">
									<label class="et-label" style="margin-top:0;">الموظف <span class="et-req">*</span></label>
									<div class="et-search-wrap">
										<input type="text" id="et-admin-addpast-employee-input" class="et-select et-search-input" placeholder="اكتب اسم الموظف" autocomplete="off" style="padding:10px 12px;">
										<input type="hidden" id="et-admin-addpast-employee-id" value="">
										<ul id="et-admin-addpast-employee-results" class="et-search-results et-search-results--overlay"></ul>
									</div>
									<div class="et-grid-2">
										<div><label class="et-label">من تاريخ</label><input type="date" id="et-admin-addpast-start" class="et-select"></div>
										<div><label class="et-label">إلى تاريخ</label><input type="date" id="et-admin-addpast-end" class="et-select"></div>
									</div>
									<div id="et-admin-addpast-days-preview" class="et-sub"></div>
									<label class="et-label">ملاحظات</label>
									<textarea id="et-admin-addpast-notes" class="et-textarea" rows="2"></textarea>
									<div id="et-admin-addpast-msg" class="et-inline-msg" hidden></div>
									<button type="button" class="et-submit-btn" id="et-admin-addpast-submit" style="margin-top:8px;">تسجيل الإجازة السابقة</button>
								</div>
							</div>
						</div>

						<div id="et-admin-panel-sick" hidden>
								<div class="et-grid-2" style="margin-bottom:12px;">
									<div>
										<label class="et-label" style="margin-top:0;">الموظف</label>
										<div class="et-search-wrap">
											<input type="text" id="et-admin-sick-filter-employee-input" class="et-select et-search-input" placeholder="كل الموظفين (اكتب للبحث)" autocomplete="off" style="padding:10px 12px;">
											<input type="hidden" id="et-admin-sick-filter-employee" value="">
											<ul id="et-admin-sick-filter-employee-results" class="et-search-results et-search-results--overlay"></ul>
										</div>
									</div>
									<div>
										<label class="et-label" style="margin-top:0;">الحالة</label>
										<select id="et-admin-sick-filter-status" class="et-select">
											<option value="">الكل</option>
											<option value="pending">قيد المراجعة</option>
											<option value="approved">موافق عليه</option>
											<option value="rejected">مرفوض</option>
										</select>
									</div>
								</div>
							<div id="et-admin-sick-loading" class="et-empty">جارِ التحميل…</div>
							<div id="et-admin-sick-empty" class="et-empty" hidden>لا توجد طلبات إجازة مرضية.</div>
							<div id="et-admin-sick-list"></div>
						</div>

						<div id="et-admin-panel-permission" hidden>
								<div class="et-grid-2" style="margin-bottom:12px;">
									<div>
										<label class="et-label" style="margin-top:0;">الموظف</label>
										<div class="et-search-wrap">
											<input type="text" id="et-admin-permission-filter-employee-input" class="et-select et-search-input" placeholder="كل الموظفين (اكتب للبحث)" autocomplete="off" style="padding:10px 12px;">
											<input type="hidden" id="et-admin-permission-filter-employee" value="">
											<ul id="et-admin-permission-filter-employee-results" class="et-search-results et-search-results--overlay"></ul>
										</div>
									</div>
									<div>
										<label class="et-label" style="margin-top:0;">الحالة</label>
										<select id="et-admin-permission-filter-status" class="et-select">
											<option value="">الكل</option>
											<option value="pending">قيد المراجعة</option>
											<option value="approved">موافق عليه</option>
											<option value="rejected">مرفوض</option>
										</select>
									</div>
								</div>
							<div id="et-admin-permission-loading" class="et-empty">جارِ التحميل…</div>
							<div id="et-admin-permission-empty" class="et-empty" hidden>لا توجد طلبات استئذان.</div>
							<div id="et-admin-permission-list"></div>
						</div>
					<div id="et-admin-panel-dashboard">
						<div id="et-dash-counts" class="et-dash-counts"></div>

						<div class="et-dash-grid">
							<div class="et-dash-section">
								<div class="et-dash-section-title">👥 ماذا يحدث الآن؟</div>
								<div id="et-dash-now-doing" class="et-dash-now-doing"></div>
								<div id="et-dash-now-doing-empty" class="et-empty" hidden>لا يوجد عمل جارٍ حاليًا.</div>
							</div>

							<div class="et-dash-section">
								<div class="et-dash-section-title">⚠️ المشاكل الحالية</div>
								<div id="et-dash-problems" class="et-dash-problems"></div>
								<div id="et-dash-problems-empty" class="et-empty" hidden>لا توجد مشاكل حاليًا. 🎉</div>
							</div>

							<div class="et-dash-section">
								<div class="et-dash-section-title">🗺️ سير المشاريع</div>
								<div id="et-dash-projects" class="et-dash-projects"></div>
								<div id="et-dash-projects-empty" class="et-empty" hidden>لا توجد مشاريع بعد.</div>
							</div>

							<div class="et-dash-section">
								<div class="et-dash-section-title">🕒 أحداث اليوم</div>
								<div id="et-dash-timeline" class="et-dash-timeline"></div>
								<div id="et-dash-timeline-empty" class="et-empty" hidden>لا توجد أحداث اليوم بعد.</div>
							</div>
						</div>

						<div id="et-dash-loading" class="et-empty">جارِ التحميل…</div>
					</div>

					<div id="et-admin-panel-view" hidden>
						<div class="et-grid-2" style="margin-bottom:6px;">
							<div>
								<label class="et-label" style="margin-top:0;">الموظف</label>
								<div class="et-search-wrap">
									<input type="text" id="et-admin-filter-employee-input" class="et-select et-search-input" placeholder="كل الموظفين (اكتب للبحث)" autocomplete="off" style="padding:10px 12px;">
									<input type="hidden" id="et-admin-filter-employee" value="">
									<ul id="et-admin-filter-employee-results" class="et-search-results"></ul>
								</div>
							</div>
							<div>
								<label class="et-label" style="margin-top:0;">الأسبوع</label>
								<input type="date" id="et-admin-filter-week" class="et-select">
							</div>
						</div>
						<div class="et-view-toggle">
							<button type="button" class="et-view-toggle-btn et-view-toggle-btn--active" id="et-view-toggle-list">📋 قائمة</button>
							<button type="button" class="et-view-toggle-btn" id="et-view-toggle-kanban">🗂 Kanban</button>
						</div>
						<div id="et-admin-tasks-loading" class="et-empty">جارِ التحميل…</div>
						<div id="et-admin-tasks-empty" class="et-empty" hidden>لا توجد مهام لهذا الأسبوع.</div>
						<div id="et-admin-tasks-list"></div>
						<div id="et-admin-kanban-board" class="et-kanban-board" hidden></div>
					</div>

					<div id="et-admin-panel-add" hidden>
						<label class="et-label" style="margin-top:0;">الموظف *</label>
						<div class="et-search-wrap">
							<input type="text" id="et-admin-add-employee-input" class="et-select et-search-input" placeholder="اكتب اسم الموظف…" autocomplete="off" style="padding:10px 12px;">
							<input type="hidden" id="et-admin-add-employee" value="">
							<ul id="et-admin-add-employee-results" class="et-search-results"></ul>
						</div>

						<label class="et-label">بداية الأسبوع *</label>
						<input type="date" id="et-admin-add-week" class="et-select">

						<label class="et-label">المهام</label>
						<div id="et-admin-add-rows"></div>
						<button type="button" class="et-link-back" id="et-admin-add-row-btn" style="margin-top:2px;">+ إضافة مهمة أخرى</button>

						<label class="et-radio" style="margin-top:16px;"><input type="checkbox" id="et-admin-add-notify" checked> إرسال بريد إلكتروني للموظف بالمهام الجديدة</label>

						<div id="et-admin-add-msg" class="et-inline-msg" hidden></div>
						<button type="button" class="et-submit-btn" id="et-admin-add-submit">إضافة المهام</button>
					</div>

					<div id="et-admin-panel-sent" hidden>
						<p class="et-subtitle" style="margin-bottom:12px;">كل المهام والتحديثات اليومية التي أرسلها الموظفون، مع مرفقاتها — يمكنك التعليق عليها وسيصل تعليقك للموظف فورًا.</p>
						<div class="et-grid-2" style="margin-bottom:6px;">
							<div>
								<label class="et-label" style="margin-top:0;">الموظف</label>
								<div class="et-search-wrap">
									<input type="text" id="et-admin-sent-filter-employee-input" class="et-select et-search-input" placeholder="كل الموظفين (اكتب للبحث)" autocomplete="off" style="padding:10px 12px;">
									<input type="hidden" id="et-admin-sent-filter-employee" value="">
									<ul id="et-admin-sent-filter-employee-results" class="et-search-results"></ul>
								</div>
							</div>
						</div>
						<div id="et-admin-sent-loading" class="et-empty">جارِ التحميل…</div>
						<div id="et-admin-sent-empty" class="et-empty" hidden>لا توجد مهام مرسلة بعد.</div>
						<div id="et-admin-sent-list"></div>
					</div>

					<div id="et-admin-panel-performance" hidden>
						<p class="et-subtitle" style="margin-bottom:12px;">مستوى أداء كل موظف هذا الشهر، محسوب تلقائيًا من: إنجاز المهام في وقتها، الانتظام في إرسال التحديث اليومي، وقلة المشاكل/طلبات المساعدة المتكررة.</p>
						<input type="month" id="et-admin-perf-month" class="et-perf-month">
						<div id="et-admin-perf-loading" class="et-empty">جارِ التحميل…</div>
						<div id="et-admin-perf-empty" class="et-empty" hidden>لا يوجد موظفون نشطون.</div>
						<div id="et-admin-perf-list"></div>
					</div>

					<div id="et-admin-panel-projects" hidden>
						<div id="et-admin-projects-list-view">
							<p class="et-subtitle" style="margin-bottom:12px;">اجمع عدة مهام مترابطة في مشروع واحد بترتيب متسلسل، لترى فورًا وين توقف العمل ولماذا — بدل جدول مهام منفصل.</p>
							<div class="et-project-new-form">
								<input type="text" id="et-project-new-title" placeholder="اسم المشروع الجديد">
								<button type="button" id="et-project-new-submit">إنشاء مشروع</button>
							</div>
							<div id="et-projects-loading" class="et-empty">جارِ التحميل…</div>
							<div id="et-projects-empty" class="et-empty" hidden>لا توجد مشاريع بعد. أنشئ أول مشروع من الأعلى.</div>
							<div id="et-projects-list"></div>
						</div>
						<div id="et-admin-project-detail-view" hidden>
							<button type="button" class="et-project-back" id="et-project-back-btn">→ رجوع لكل المشاريع</button>
							<div class="et-project-detail-title" id="et-project-detail-title"></div>
							<div class="et-project-detail-desc" id="et-project-detail-desc"></div>
							<div id="et-project-chain"></div>

							<div class="et-project-link-form" id="et-project-new-task-form" style="flex-direction:column;align-items:stretch;">
								<div style="width:100%;">
									<label style="margin-top:0;">➕ إنشاء مهمة جديدة مشتركة لهذا المشروع</label>
									<input type="text" id="et-project-task-title" class="et-select" placeholder="عنوان المهمة" style="margin-bottom:8px;">
									<div class="et-grid-2" style="margin-bottom:8px;">
										<select id="et-project-task-day" class="et-select">
											<option value="">بدون تحديد يوم</option>
											<option value="sunday">الأحد</option>
											<option value="monday">الاثنين</option>
											<option value="tuesday">الثلاثاء</option>
											<option value="wednesday">الأربعاء</option>
											<option value="thursday">الخميس</option>
										</select>
										<select id="et-project-task-priority" class="et-select">
											<option value="medium" selected>أولوية متوسطة</option>
											<option value="low">أولوية عادية</option>
											<option value="high">أولوية عالية</option>
											<option value="critical">أولوية حرجة</option>
										</select>
									</div>
									<input type="date" id="et-project-task-due" class="et-select" style="margin-bottom:10px;" title="تاريخ الاستحقاق (اختياري)">

									<div id="et-project-task-members"></div>
									<button type="button" id="et-project-task-add-member" class="et-view-toggle-btn" style="margin:6px 0 12px;">+ إضافة موظف مشارك</button>

									<div id="et-project-task-msg" class="et-inline-msg" hidden></div>
									<button type="button" id="et-project-task-submit" class="et-submit-btn">➕ إنشاء المهمة</button>
								</div>
							</div>

							<div class="et-project-link-form">
								<div>
									<label>ربط مهمة موجودة</label>
									<div style="position:relative;">
										<input type="text" id="et-project-link-task-input" class="et-project-link-task" placeholder="اكتب عنوان المهمة" autocomplete="off">
										<input type="hidden" id="et-project-link-task-id">
										<ul id="et-project-link-task-results" class="et-search-results"></ul>
									</div>
								</div>
								<div>
									<label>الترتيب (اختياري)</label>
									<input type="number" id="et-project-link-order" class="et-project-link-order" min="1">
								</div>
								<button type="button" id="et-project-link-submit">🗺️ ربط بالمشروع</button>
							</div>
						</div>
					</div>
					</div>
				</div>
			</div>

			<div class="et-att-modal-overlay" id="et-att-history-modal" hidden>
				<div class="et-att-modal" style="max-width:560px;">
					<div class="et-att-modal-head">
						<b id="et-att-history-modal-title">📄 سجل حضور الموظف</b>
						<button type="button" id="et-att-history-modal-close">✕</button>
					</div>
					<div class="et-att-modal-body">
						<div id="et-att-history-modal-loading" class="et-empty">جارِ التحميل…</div>
						<div id="et-att-history-modal-empty" class="et-empty" hidden>لا يوجد سجل حضور بعد.</div>
						<div id="et-att-history-modal-list"></div>
					</div>
				</div>
			</div>

			<div class="et-att-modal-overlay" id="et-att-edit-modal" hidden>
				<div class="et-att-modal">
					<div class="et-att-modal-head">
						<b>✏️ تعديل الحضور</b>
						<button type="button" id="et-att-modal-close">✕</button>
					</div>
					<div class="et-att-modal-body">
						<div id="et-att-modal-employee" class="et-sub" style="font-weight:700;color:var(--et-navy);"></div>
						<div id="et-att-modal-date" class="et-sub" style="margin-bottom:8px;"></div>
						<div id="et-att-modal-current" class="et-sub" style="margin-bottom:10px;"></div>

						<label class="et-label" style="margin-top:6px;">الحالة الجديدة <span class="et-req">*</span></label>
						<select id="et-att-modal-status" class="et-select">
							<option value="present">🟢 حاضر</option>
							<option value="late">🟡 متأخر</option>
							<option value="absent">🔴 غائب</option>
						</select>

						<div id="et-att-modal-times" class="et-grid-2">
							<div><label class="et-label">وقت الحضور</label><input type="time" id="et-att-modal-checkin" class="et-select"></div>
							<div><label class="et-label">وقت الانصراف</label><input type="time" id="et-att-modal-checkout" class="et-select"></div>
						</div>

						<label class="et-label">سبب التعديل <span class="et-req">*</span></label>
						<textarea id="et-att-modal-reason" class="et-textarea" rows="2" placeholder="إجباري — مثال: تعذّر على الموظف تسجيل الحضور بسبب مشكلة تقنية في النظام."></textarea>

						<label class="et-label">ملاحظات (اختياري)</label>
						<textarea id="et-att-modal-notes" class="et-textarea" rows="2"></textarea>

						<div id="et-att-modal-msg" class="et-inline-msg" hidden style="margin-top:8px;"></div>
						<button type="button" class="et-submit-btn" id="et-att-modal-save" style="margin-top:12px;">حفظ التعديل</button>
					</div>
				</div>
			</div>
		</div>

<script src="<?php echo e( asset( 'tracker/tracker.js' ) ); ?>?v=<?php echo e( ET_VERSION ); ?>"></script>
</body>
</html>
