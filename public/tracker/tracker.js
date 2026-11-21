(function(){
	var root = document.getElementById('et-app');
	if(!root) return;
	var REST = root.dataset.rest;
	var NONCE = root.dataset.nonce;
	var state = { employees: [], token: null, employeeId: null, employeeName: '', pendingId: null, pendingName: '', selectedFiles: [] };

	// "حالة العمل الداخلية": منفصلة عن حالة المهمة نفسها (pending/done)، توضح تحديدًا ماذا يفعل الموظف الآن
	var WORK_STATUS_OPTS = [
		['in_progress', 'يعمل عليها'], ['started', 'بدأ العمل'], ['partial', 'أنجز جزءًا'],
		['done_part', 'أكمل جزءه'], ['need_help', 'يحتاج مساعدة'], ['problem', 'ظهرت مشكلة'],
		['waiting_person', 'بانتظار شخص آخر'], ['waiting_admin', 'بانتظار الإدارة']
	];
	var WORK_STATUS_LABELS = {};
	WORK_STATUS_OPTS.forEach(function(o){ WORK_STATUS_LABELS[o[0]] = o[1]; });

	var viewPicker = document.getElementById('et-view-picker');
	var viewForm = document.getElementById('et-view-form');
	var viewSuccess = document.getElementById('et-view-success');
	var viewAdmin = document.getElementById('et-view-admin');
	var pickerMsg = document.getElementById('et-picker-msg');
	var loginForm = document.getElementById('et-login-form');
	var loginEmail = document.getElementById('et-login-email');
	var loginPassword = document.getElementById('et-login-password');
	var loginSubmit = document.getElementById('et-login-submit');

	/* ---------- الوضع الداكن ---------- */
	var darkToggle = document.getElementById('et-dark-toggle');
	function applyDarkMode(on){
		root.classList.toggle('et-dark', on);
		darkToggle.textContent = on ? '☀️' : '🌙';
		try { localStorage.setItem('et-dark-mode', on ? '1' : '0'); } catch(e){}
	}
	darkToggle.addEventListener('click', function(){ applyDarkMode(!root.classList.contains('et-dark')); });
	try { if(localStorage.getItem('et-dark-mode') === '1'){ applyDarkMode(true); } } catch(e){}

	/* ---------- مركز الإشعارات (يعمل لكل من الموظف والإدارة، حسب مين مسجّل دخول حاليًا) ---------- */
	var notifBell = document.getElementById('et-notif-bell');
	var notifPanel = document.getElementById('et-notif-panel');
	var notifBadge = document.getElementById('et-notif-badge');
	var notifList = document.getElementById('et-notif-list');
	var notifEmpty = document.getElementById('et-notif-empty');
	var notifMarkReadBtn = document.getElementById('et-notif-mark-read');

	/** يحدّد صاحب جرس الإشعارات حسب الشاشة **الظاهرة فعليًا** الآن، لا فقط أي رمز دخول محفوظ بالذاكرة.
	 *  سبب الحماية هذه: adminState.token يبقى محفوظًا (بالذاكرة و sessionStorage) حتى بعد الضغط على "‹
	 *  رجوع لصفحة الموظفين" لأن هذا يسمح للإدارة بالعودة السريعة للوحتها دون إعادة كتابة الرمز السري —
	 *  فلو اعتمدنا وجود الرمز فقط (بدل الشاشة الظاهرة)، أي موظف يسجّل دخوله لاحقًا بنفس المتصفح والتبويب
	 *  (جهاز مشترك) كان سيرى شارة/قائمة إشعارات **الإدارة** بدل إشعاراته الخاصة — وهذا بالضبط ما حدث. */
	function currentNotifContext(){
		var onAdminView = !viewAdmin.hidden;
		if(onAdminView){
			return adminState.token ? { path: '/admin/notifications', markPath: '/admin/notifications/mark-read', token: adminState.token } : null;
		}
		return state.token ? { path: '/notifications', markPath: '/notifications/mark-read', token: state.token } : null;
	}
	function refreshNotifBadge(){
		var ctx = currentNotifContext();
		if(!ctx){ notifBell.hidden = true; return; }
		notifBell.hidden = false;
		apiFetch(ctx.path + '?token=' + encodeURIComponent(ctx.token)).then(function(data){
			if(data.unread > 0){ notifBadge.hidden = false; notifBadge.textContent = data.unread > 9 ? '9+' : data.unread; }
			else { notifBadge.hidden = true; }
		}).catch(function(){});
	}
	function loadNotifPanel(){
		var ctx = currentNotifContext();
		if(!ctx) return;
		notifList.innerHTML = 'جارِ التحميل…';
		apiFetch(ctx.path + '?token=' + encodeURIComponent(ctx.token)).then(function(data){
			notifList.innerHTML = '';
			var items = data.items || [];
			notifEmpty.hidden = items.length > 0;
			items.forEach(function(n){
				var el = document.createElement('div');
				el.className = 'et-notif-item' + (!n.is_read ? ' et-notif-item--unread' : '');
				el.textContent = n.message;
				var d = document.createElement('div');
				d.className = 'et-notif-item-date'; d.textContent = n.date;
				el.appendChild(d);
				notifList.appendChild(el);
			});
		}).catch(function(){ notifList.innerHTML = ''; });
	}
	notifBell.addEventListener('click', function(){
		notifPanel.hidden = !notifPanel.hidden;
		if(!notifPanel.hidden){ loadNotifPanel(); }
	});
	notifMarkReadBtn.addEventListener('click', function(){
		var ctx = currentNotifContext();
		if(!ctx) return;
		apiFetch(ctx.markPath, {
			method: 'POST', headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ token: ctx.token })
		}).then(function(){ refreshNotifBadge(); loadNotifPanel(); }).catch(function(){});
	});
	document.addEventListener('click', function(e){
		if(!notifPanel.hidden && !notifPanel.contains(e.target) && e.target !== notifBell){ notifPanel.hidden = true; }
	});
	notifBell.hidden = true;
	setInterval(refreshNotifBadge, 60000);

	/* ---------- بحث عام بالهيدر (يظهر فقط داخل تبويب الإدارة، ويفلتر المهام المعروضة حاليًا) ---------- */
	var headerSearch = document.getElementById('et-header-search');
	headerSearch.addEventListener('input', function(){
		var q = headerSearch.value.trim().toLowerCase();
		document.querySelectorAll('.et-admin-task, .et-perf-card, .et-team-card').forEach(function(card){
			var text = card.textContent.toLowerCase();
			card.style.display = (!q || text.indexOf(q) !== -1) ? '' : 'none';
		});
	});

	var form = document.getElementById('et-update-form');
	var submitBtn = document.getElementById('et-submit-btn');
	var formError = document.getElementById('et-form-error');
	var alreadyMsg = document.getElementById('et-already-msg');


	var tabAttendance = document.getElementById('et-tab-attendance');
	var panelAttendance = document.getElementById('et-panel-attendance');
	var tabUpdate = document.getElementById('et-tab-update');
	var tabTasks = document.getElementById('et-tab-tasks');
	var tabHelp = document.getElementById('et-tab-help');
	var tabReplies = document.getElementById('et-tab-replies');
	var tabPerformance = document.getElementById('et-tab-performance');
	var tabLeave = document.getElementById('et-tab-leave');
	var tabPermission = document.getElementById('et-tab-permission');
	var tabPassword = document.getElementById('et-tab-password');
	var panelPassword = document.getElementById('et-panel-password');
	var panelLeave = document.getElementById('et-panel-leave');
	var panelPermissionMain = document.getElementById('et-panel-permission');
	var tasksCountBadge = document.getElementById('et-tasks-count');
	var repliesCountBadge = document.getElementById('et-replies-count');
	var panelUpdate = document.getElementById('et-panel-update');
	var panelTasks = document.getElementById('et-panel-tasks');
	var panelHelp = document.getElementById('et-panel-help');
	var panelReplies = document.getElementById('et-panel-replies');
	var panelPerformance = document.getElementById('et-panel-performance');
	var helpNoTasksEl = document.getElementById('et-help-no-tasks');
	var helpFormWrap = document.getElementById('et-help-form-wrap');
	var helpTaskSelect = document.getElementById('et-help-task-select');
	var helpPeerSelect = document.getElementById('et-help-peer-select');
	var helpPendingBox = document.getElementById('et-help-pending-box');
	var helpTypeSelect = document.getElementById('et-help-type-select');
	var helpMessageInput = document.getElementById('et-help-message-input');
	var helpFileInput = document.getElementById('et-help-file-input');
	var helpFileNameEl = document.getElementById('et-help-file-name');
	var helpMsgEl = document.getElementById('et-help-msg');
	var helpSubmitBtn = document.getElementById('et-help-submit-btn');
	var handoffMessageInput = document.getElementById('et-handoff-message-input');
	var handoffFileInput = document.getElementById('et-handoff-file-input');
	var handoffFileNameEl = document.getElementById('et-handoff-file-name');
	var handoffMsgEl = document.getElementById('et-handoff-msg');
	var handoffSubmitBtn = document.getElementById('et-handoff-submit-btn');
	var helpTasksData = [];

	function showChosenFileName(fileInput, labelEl){
		if(fileInput.files && fileInput.files.length){
			labelEl.hidden = false;
			labelEl.textContent = '📎 ' + fileInput.files[0].name;
		} else {
			labelEl.hidden = true;
		}
	}
	helpFileInput.addEventListener('change', function(){ showChosenFileName(helpFileInput, helpFileNameEl); });
	handoffFileInput.addEventListener('change', function(){ showChosenFileName(handoffFileInput, handoffFileNameEl); });
	var perfMonthInput = document.getElementById('et-perf-month');
	var perfLoading = document.getElementById('et-perf-loading');
	var perfContent = document.getElementById('et-perf-content');
	var tasksLoading = document.getElementById('et-tasks-loading');
	var tasksEmpty = document.getElementById('et-tasks-empty');
	var tasksWrap = document.getElementById('et-tasks-wrap');
	var tasksPrevSection = document.getElementById('et-tasks-prev-section');
	var tasksPrevList = document.getElementById('et-tasks-prev-list');
	var tasksCurrentSection = document.getElementById('et-tasks-current-section');
	var tasksCurrentList = document.getElementById('et-tasks-current-list');
	var tasksLoaded = false;
	var myViewToggleList = document.getElementById('et-my-view-toggle-list');
	var myViewToggleBoard = document.getElementById('et-my-view-toggle-board');
	var myBoard = document.getElementById('et-my-board');
	var myTasksCache = [];
	var myViewMode = 'list';
	var repliesLoading = document.getElementById('et-replies-loading');
	var repliesEmpty = document.getElementById('et-replies-empty');
	var repliesList = document.getElementById('et-replies-list');

	function showView(name){
		viewPicker.hidden = name !== 'picker';
		viewForm.hidden = name !== 'form';
		viewSuccess.hidden = name !== 'success';
		viewAdmin.hidden = name !== 'admin';
		// لوحة الإدارة ولوحة الموظف صارتا بنفس هيكل "شريط جانبي + محتوى" فتحتاجان عرضًا أوسع
		// من عرض بطاقة "اختيار الموظف" المخصص أصلًا لهاتف بعرض ضيق
		root.classList.toggle('et-wide', name === 'admin' || name === 'form');
		// نُحدّث جرس الإشعارات فورًا عند أي تغيّر شاشة (بدل الانتظار لدورة الفحص كل 60 ثانية) حتى لا تبقى
		// شارة/محتوى الطرف السابق (مثلاً الإدارة) ظاهرة ولو للحظات بعد الانتقال لشاشة موظف على نفس الجهاز.
		notifPanel.hidden = true;
		refreshNotifBadge();
	}

	function showTab(name){
		tabAttendance.className = 'et-tab' + (name === 'attendance' ? ' et-tab--active' : '');
		tabUpdate.className = 'et-tab' + (name === 'update' ? ' et-tab--active' : '');
		tabTasks.className = 'et-tab' + (name === 'tasks' ? ' et-tab--active' : '');
		tabHelp.className = 'et-tab' + (name === 'help' ? ' et-tab--active' : '');
		tabReplies.className = 'et-tab' + (name === 'replies' ? ' et-tab--active' : '');
		tabPerformance.className = 'et-tab' + (name === 'performance' ? ' et-tab--active' : '');
		tabLeave.className = 'et-tab' + (name === 'leave' ? ' et-tab--active' : '');
		tabPermission.className = 'et-tab' + (name === 'permission' ? ' et-tab--active' : '');
		tabPassword.className = 'et-tab' + (name === 'password' ? ' et-tab--active' : '');
		panelAttendance.hidden = name !== 'attendance';
		panelUpdate.hidden = name !== 'update';
		panelTasks.hidden = name !== 'tasks';
		panelHelp.hidden = name !== 'help';
		panelReplies.hidden = name !== 'replies';
		panelPerformance.hidden = name !== 'performance';
		panelLeave.hidden = name !== 'leave';
		panelPermissionMain.hidden = name !== 'permission';
		panelPassword.hidden = name !== 'password';
		// نعيد تحميل المهام/الردود/الأداء كل مرة يفتح فيها التبويب (وليس مرة واحدة فقط) حتى تظهر أي مهام أو
		// تعليقات أضافتها الإدارة بعد أول تحميل، بدل الاعتماد على تخزين قديم في المتصفح.
		if(name === 'tasks'){ loadWeeklyTasks(); }
		if(name === 'help'){ loadHelpPanel(); }
		if(name === 'replies'){ loadMyUpdateReplies(); }
		if(name === 'performance'){ loadMyPerformance(); }
		if(name === 'leave'){ loadLeaveBalance(); loadLeaveRequests(); loadSickRequests(); }
		if(name === 'permission'){ loadPermissionRequests(); }
		if(name === 'attendance'){ loadAttendanceToday(); loadAttendanceHistory(); }
	}
	tabAttendance.addEventListener('click', function(){ showTab('attendance'); });
	tabUpdate.addEventListener('click', function(){ showTab('update'); });
	tabTasks.addEventListener('click', function(){ showTab('tasks'); });
	tabHelp.addEventListener('click', function(){ showTab('help'); });
	tabReplies.addEventListener('click', function(){ showTab('replies'); });
	tabLeave.addEventListener('click', function(){ showTab('leave'); });
	tabPermission.addEventListener('click', function(){ showTab('permission'); });
	tabPerformance.addEventListener('click', function(){ showTab('performance'); });
	tabPassword.addEventListener('click', function(){ showTab('password'); });

	/* ================= نظام "الحضور والانصراف" — واجهة الموظف ================= */

	var attTodayDateEl = document.getElementById('et-att-today-date');
	var attStatusBadge = document.getElementById('et-att-status-badge');
	var attTimesEl = document.getElementById('et-att-times');
	var attTimeIn = document.getElementById('et-att-time-in');
	var attTimeOut = document.getElementById('et-att-time-out');
	var attTimeWorked = document.getElementById('et-att-time-worked');
	var attExtraInfo = document.getElementById('et-att-extra-info');
	var attMsg = document.getElementById('et-att-msg');
	var attCheckinBtn = document.getElementById('et-att-checkin-btn');
	var attCheckoutBtn = document.getElementById('et-att-checkout-btn');
	var attHistoryLoading = document.getElementById('et-att-history-loading');
	var attHistoryEmpty = document.getElementById('et-att-history-empty');
	var attHistoryList = document.getElementById('et-att-history-list');

	var ATT_ICONS = { present:'🟢', late:'🟡', absent:'🔴', leave:'🔵', sick:'🟣', permission:'🟠', not_yet:'⚪', off:'⚪' };

	function fmtTime(dtStr){
		if(!dtStr) return '—';
		var d = new Date(dtStr.replace(' ', 'T'));
		if(isNaN(d.getTime())) return '—';
		var h = d.getHours(), m = d.getMinutes();
		var ampm = h >= 12 ? 'م' : 'ص';
		var h12 = h % 12; if(h12 === 0) h12 = 12;
		return h12 + ':' + (m < 10 ? '0' : '') + m + ' ' + ampm;
	}

	/** رسائل مفهومة للمستخدم بدل أخطاء GPS التقنية — يغطي: رفض الصلاحية، عدم توفر GPS، البطء، عدم الدعم */
	function getLocation(){
		return new Promise(function(resolve, reject){
			if(!('geolocation' in navigator)){
				reject('هذا الجهاز أو المتصفح لا يدعم تحديد الموقع الجغرافي.');
				return;
			}
			navigator.geolocation.getCurrentPosition(
				function(pos){ resolve(pos.coords); },
				function(err){
					if(err.code === err.PERMISSION_DENIED){
						reject('تم رفض صلاحية الوصول للموقع. الرجاء السماح بالوصول لموقعك من إعدادات المتصفح ثم إعادة المحاولة.');
					} else if(err.code === err.POSITION_UNAVAILABLE){
						reject('تعذّر تحديد موقعك الحالي (GPS غير متاح). تأكد من تفعيل خدمة الموقع بجهازك وحاول مجددًا.');
					} else if(err.code === err.TIMEOUT){
						reject('استغرق تحديد موقعك وقتًا طويلًا. حاول مجددًا في مكان مفتوح بعيدًا عن المباني.');
					} else {
						reject('تعذّر تحديد موقعك الحالي. حاول مجددًا.');
					}
				},
				{ enableHighAccuracy: true, timeout: 20000, maximumAge: 0 }
			);
		});
	}

	function showAttMsg(text, type){
		attMsg.hidden = false;
		attMsg.className = 'et-inline-msg et-inline-msg--' + (type || 'error');
		attMsg.textContent = text;
	}

	function renderAttendanceStatus(s){
		attMsg.hidden = true;
		var color = s.status_color || 'gray';
		attStatusBadge.className = 'et-att-status et-att-status--' + color;
		attStatusBadge.textContent = (ATT_ICONS[s.status] || '⚪') + ' ' + s.status_label;
		attTodayDateEl.textContent = s.date;

		var hasTimes = !!(s.check_in || s.check_out);
		attTimesEl.hidden = !hasTimes;
		attTimeIn.textContent = s.check_in ? fmtTime(s.check_in) : '—';
		attTimeOut.textContent = s.check_out ? fmtTime(s.check_out) : '—';
		attTimeWorked.textContent = s.worked_label || '—';

		var extra = '';
		if(s.status === 'late' && s.late_minutes > 0){ extra = 'متأخر ' + s.late_minutes + ' دقيقة عن بداية الدوام.'; }
		if(s.permission){ extra = '🕐 لديك استئذان معتمد اليوم من ' + s.permission.time_out + (s.permission.time_return ? (' إلى ' + s.permission.time_return) : '') + (s.permission.reason ? (' — ' + s.permission.reason) : ''); }
		if(s.leave){ extra = '🏖️ لديك إجازة سنوية معتمدة اليوم (' + s.leave.start_date + ' إلى ' + s.leave.end_date + ').'; }
		if(s.sick){ extra = '🩺 لديك إجازة مرضية معتمدة اليوم (' + s.sick.start_date + ' إلى ' + s.sick.end_date + ').'; }
		if(s.is_manual){ extra += (extra ? ' ' : '') + '✏️ عُدِّل هذا السجل يدويًا من الإدارة.'; }
		attExtraInfo.hidden = !extra;
		attExtraInfo.textContent = extra;

		var blockedByLeave = !!(s.leave || s.sick);
		attCheckinBtn.hidden = !( !s.check_in && !blockedByLeave && s.status !== 'off' );
		attCheckoutBtn.hidden = !( s.check_in && !s.check_out );
	}

	function loadAttendanceToday(){
		apiFetch('/attendance/today?token=' + encodeURIComponent(state.token)).then(function(data){
			renderAttendanceStatus(data);
		}).catch(function(err){
			showAttMsg(typeof err === 'string' ? err : 'تعذّر تحميل حالة الحضور.', 'error');
		});
	}

	attCheckinBtn.addEventListener('click', function(){
		if (typeof navigator !== 'undefined' && 'onLine' in navigator && !navigator.onLine) {
			showAttMsg('📡 لا يوجد اتصال بالإنترنت. الرجاء الاتصال بالإنترنت لإتمام تسجيل الحضور.', 'error');
			return;
		}
		attCheckinBtn.disabled = true;
		attMsg.hidden = true;
		getLocation().then(function(coords){
			return apiFetch('/attendance/check-in', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ token: state.token, lat: coords.latitude, lng: coords.longitude, accuracy: coords.accuracy })
			});
		}).then(function(data){
			renderAttendanceStatus(data.status);
			showAttMsg('✅ تم تسجيل حضورك بنجاح.', 'success');
			loadAttendanceHistory();
		}).catch(function(err){
			showAttMsg(typeof err === 'string' ? err : 'تعذّر تسجيل الحضور، حاول مجددًا.', 'error');
		}).then(function(){ attCheckinBtn.disabled = false; });
	});

	attCheckoutBtn.addEventListener('click', function(){
		if (typeof navigator !== 'undefined' && 'onLine' in navigator && !navigator.onLine) {
			showAttMsg('📡 لا يوجد اتصال بالإنترنت. الرجاء الاتصال بالإنترنت لإتمام تسجيل الانصراف.', 'error');
			return;
		}
		attCheckoutBtn.disabled = true;
		attMsg.hidden = true;
		getLocation().then(function(coords){
			return apiFetch('/attendance/check-out', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ token: state.token, lat: coords.latitude, lng: coords.longitude, accuracy: coords.accuracy })
			});
		}).then(function(data){
			renderAttendanceStatus(data.status);
			showAttMsg('✅ تم تسجيل انصرافك بنجاح.', 'success');
			loadAttendanceHistory();
		}).catch(function(err){
			showAttMsg(typeof err === 'string' ? err : 'تعذّر تسجيل الانصراف، حاول مجددًا.', 'error');
		}).then(function(){ attCheckoutBtn.disabled = false; });
	});

	function loadAttendanceHistory(){
		attHistoryLoading.hidden = false; attHistoryEmpty.hidden = true; attHistoryList.innerHTML = '';
		apiFetch('/attendance/history?token=' + encodeURIComponent(state.token)).then(function(data){
			attHistoryLoading.hidden = true;
			var items = data.items || [];
			if(!items.length){ attHistoryEmpty.hidden = false; return; }
			items.forEach(function(s){
				var row = document.createElement('div');
				row.className = 'et-task-item';
				row.style.flexDirection = 'column';
				row.style.alignItems = 'stretch';
				var top = document.createElement('div');
				top.style.cssText = 'display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:6px;';
				var badge = document.createElement('span');
				badge.className = 'et-att-badge et-att-badge--' + (s.status_color || 'gray');
				badge.textContent = (ATT_ICONS[s.status] || '⚪') + ' ' + s.status_label;
				var dateEl = document.createElement('b'); dateEl.textContent = s.date;
				top.appendChild(dateEl); top.appendChild(badge);
				row.appendChild(top);
				if(s.check_in || s.check_out){
					var times = document.createElement('div');
					times.className = 'et-sub'; times.style.marginTop = '4px';
					times.textContent = 'الحضور: ' + (s.check_in ? fmtTime(s.check_in) : '—') + '   |   الانصراف: ' + (s.check_out ? fmtTime(s.check_out) : '—') + (s.worked_label ? ('   |   ساعات العمل: ' + s.worked_label) : '');
					row.appendChild(times);
				}
				attHistoryList.appendChild(row);
			});
		}).catch(function(){
			attHistoryLoading.hidden = true;
			attHistoryEmpty.hidden = false;
		});
	}

	/* ================= نظام "الإجازات والاستئذانات" — واجهة الموظف ================= */

	function leaveStatusColor(status){
		if(status === 'approved') return 'green';
		if(status === 'rejected') return 'red';
		if(status === 'cancelled') return 'gray';
		return 'orange';
	}

	var leaveSubtabAnnual = document.getElementById('et-leave-subtab-annual');
	var leaveSubtabSick = document.getElementById('et-leave-subtab-sick');
	var leaveAnnualSection = document.getElementById('et-leave-annual-section');
	var leaveSickSection = document.getElementById('et-leave-sick-section');
	leaveSubtabAnnual.addEventListener('click', function(){
		leaveSubtabAnnual.className = 'et-view-toggle-btn et-view-toggle-btn--active';
		leaveSubtabSick.className = 'et-view-toggle-btn';
		leaveAnnualSection.hidden = false; leaveSickSection.hidden = true;
	});
	leaveSubtabSick.addEventListener('click', function(){
		leaveSubtabSick.className = 'et-view-toggle-btn et-view-toggle-btn--active';
		leaveSubtabAnnual.className = 'et-view-toggle-btn';
		leaveSickSection.hidden = false; leaveAnnualSection.hidden = true;
	});

	// ---- الرصيد ----
	var lbLoading = document.getElementById('et-leave-balance-loading');
	var lbCards = document.getElementById('et-leave-balance-cards');
	var lbRemaining = document.getElementById('et-lb-remaining');
	var lbUsed = document.getElementById('et-lb-used');
	var lbEntitled = document.getElementById('et-lb-entitled');
	var lbProgressWrap = document.getElementById('et-lb-progress-wrap');
	var lbProgressFill = document.getElementById('et-lb-progress-fill');
	var lbProgressLabel = document.getElementById('et-lb-progress-label');

	function loadLeaveBalance(){
		lbLoading.hidden = false; lbCards.hidden = true; lbProgressWrap.hidden = true;
		apiFetch('/leave/summary?token=' + encodeURIComponent(state.token)).then(function(data){
			lbLoading.hidden = true; lbCards.hidden = false; lbProgressWrap.hidden = false;
			lbRemaining.textContent = data.remaining_days;
			lbUsed.textContent = data.used_days;
			lbEntitled.textContent = data.total_entitled;
			var pct = data.total_entitled > 0 ? Math.max(0, Math.min(100, Math.round((data.used_days / data.total_entitled) * 100))) : 0;
			lbProgressFill.style.width = pct + '%';
			lbProgressLabel.textContent = pct + '%';
		}).catch(function(){
			lbLoading.hidden = false;
			lbLoading.textContent = 'تعذر تحميل الرصيد.';
		});
	}

	// ---- طلب إجازة سنوية جديد ----
	var leaveNewBtn = document.getElementById('et-leave-new-btn');
	var leaveFormWrap = document.getElementById('et-leave-form-wrap');
	var leaveStart = document.getElementById('et-leave-start');
	var leaveEnd = document.getElementById('et-leave-end');
	var leaveDaysPreview = document.getElementById('et-leave-days-preview');
	var leaveNotes = document.getElementById('et-leave-notes');
	var leaveMsg = document.getElementById('et-leave-msg');
	var leaveSubmitBtn = document.getElementById('et-leave-submit-btn');

	leaveNewBtn.addEventListener('click', function(){ leaveFormWrap.hidden = !leaveFormWrap.hidden; });

	function previewLeaveDays(){
		leaveDaysPreview.textContent = '';
		if(!leaveStart.value || !leaveEnd.value) return;
		apiFetch('/leave/calc?token=' + encodeURIComponent(state.token) + '&start_date=' + leaveStart.value + '&end_date=' + leaveEnd.value).then(function(data){
			leaveDaysPreview.textContent = '📆 عدد أيام العمل الفعلية (باستثناء الجمعة والسبت): ' + data.days + ' يوم';
		}).catch(function(err){
			leaveDaysPreview.textContent = typeof err === 'string' ? err : '';
		});
	}
	leaveStart.addEventListener('change', previewLeaveDays);
	leaveEnd.addEventListener('change', previewLeaveDays);

	leaveSubmitBtn.addEventListener('click', function(){
		leaveMsg.hidden = true;
		if(!leaveStart.value || !leaveEnd.value){
			leaveMsg.hidden = false; leaveMsg.className = 'et-inline-msg et-inline-msg--error';
			leaveMsg.textContent = 'الرجاء تحديد تاريخ البداية والنهاية.';
			return;
		}
		leaveSubmitBtn.disabled = true;
		apiFetch('/leave/requests/create', {
			method: 'POST', headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ token: state.token, start_date: leaveStart.value, end_date: leaveEnd.value, notes: leaveNotes.value })
		}).then(function(){
			toast('تم إرسال طلب الإجازة بنجاح.');
			leaveStart.value = ''; leaveEnd.value = ''; leaveNotes.value = ''; leaveDaysPreview.textContent = '';
			leaveFormWrap.hidden = true;
			loadLeaveRequests(); loadLeaveBalance();
		}).catch(function(err){
			leaveMsg.hidden = false; leaveMsg.className = 'et-inline-msg et-inline-msg--error';
			leaveMsg.textContent = typeof err === 'string' ? err : 'تعذر إرسال الطلب.';
		}).then(function(){ leaveSubmitBtn.disabled = false; });
	});

	var leaveListLoading = document.getElementById('et-leave-list-loading');
	var leaveListEmpty = document.getElementById('et-leave-list-empty');
	var leaveList = document.getElementById('et-leave-list');

	function loadLeaveRequests(){
		leaveListLoading.hidden = false; leaveListEmpty.hidden = true; leaveList.innerHTML = '';
		apiFetch('/leave/requests?token=' + encodeURIComponent(state.token)).then(function(data){
			leaveListLoading.hidden = true;
			var items = data.requests || [];
			if(!items.length){ leaveListEmpty.hidden = false; return; }
			items.forEach(function(r){ leaveList.appendChild(buildLeaveCard(r)); });
		}).catch(function(){
			leaveListLoading.hidden = true; leaveListEmpty.hidden = false;
			leaveListEmpty.textContent = 'تعذر تحميل الطلبات.';
		});
	}

	function buildLeaveCard(r){
		var box = document.createElement('div');
		box.className = 'et-admin-task';
		var title = document.createElement('div');
		title.className = 'et-admin-task-title';
		title.textContent = 'من ' + r.start_date + ' إلى ' + r.end_date + ' (' + r.days_count + ' يوم)';
		box.appendChild(title);
		var meta = document.createElement('div');
		meta.className = 'et-admin-task-meta';
		var badge = document.createElement('span');
		badge.className = perfBadgeClass(leaveStatusColor(r.status));
		badge.textContent = r.status_label;
		meta.appendChild(badge);
		if(r.added_by_admin){
			var adm = document.createElement('span'); adm.textContent = '📌 إجازة مسجلة من الإدارة'; meta.appendChild(adm);
		}
		box.appendChild(meta);
		if(r.notes){ var n = document.createElement('div'); n.className = 'et-task-desc'; n.textContent = '📝 ' + r.notes; box.appendChild(n); }
		if(r.status === 'rejected' && r.rejection_reason){ var rr = document.createElement('div'); rr.className = 'et-task-desc'; rr.textContent = '❌ سبب الرفض: ' + r.rejection_reason; box.appendChild(rr); }
		if(r.admin_notes){ var an = document.createElement('div'); an.className = 'et-task-desc'; an.textContent = '💬 ملاحظة الإدارة: ' + r.admin_notes; box.appendChild(an); }
		if(r.status === 'pending' && !r.added_by_admin){
			var cancelBtn = document.createElement('button');
			cancelBtn.type = 'button'; cancelBtn.className = 'et-task-update-toggle';
			cancelBtn.textContent = 'إلغاء الطلب';
			cancelBtn.addEventListener('click', function(){
				if(!confirm('هل تريد إلغاء طلب الإجازة هذا؟')) return;
				apiFetch('/leave/requests/cancel', {
					method: 'POST', headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({ token: state.token, id: r.id })
				}).then(function(){ toast('تم إلغاء الطلب.'); loadLeaveRequests(); loadLeaveBalance(); }).catch(function(err){
					alert(typeof err === 'string' ? err : 'تعذر إلغاء الطلب.');
				});
			});
			box.appendChild(cancelBtn);
		}
		return box;
	}

	// ---- الإجازة المرضية ----
	var sickNewBtn = document.getElementById('et-sick-new-btn');
	var sickFormWrap = document.getElementById('et-sick-form-wrap');
	var sickStart = document.getElementById('et-sick-start');
	var sickEnd = document.getElementById('et-sick-end');
	var sickFileInput = document.getElementById('et-sick-file-input');
	var sickFileNameEl = document.getElementById('et-sick-file-name');
	var sickNotes = document.getElementById('et-sick-notes');
	var sickMsg = document.getElementById('et-sick-msg');
	var sickSubmitBtn = document.getElementById('et-sick-submit-btn');
	sickNewBtn.addEventListener('click', function(){ sickFormWrap.hidden = !sickFormWrap.hidden; });
	sickFileInput.addEventListener('change', function(){ showChosenFileName(sickFileInput, sickFileNameEl); });

	sickSubmitBtn.addEventListener('click', function(){
		sickMsg.hidden = true;
		if(!sickStart.value || !sickEnd.value){
			sickMsg.hidden = false; sickMsg.className = 'et-inline-msg et-inline-msg--error';
			sickMsg.textContent = 'الرجاء تحديد تاريخ البداية والنهاية.';
			return;
		}
		sickSubmitBtn.disabled = true;
		var fd = new FormData();
		fd.append('token', state.token);
		fd.append('start_date', sickStart.value);
		fd.append('end_date', sickEnd.value);
		fd.append('notes', sickNotes.value);
		if(sickFileInput.files && sickFileInput.files.length){ fd.append('attachment', sickFileInput.files[0], sickFileInput.files[0].name); }
		apiFetch('/sick-leave/requests/create', { method: 'POST', body: fd }).then(function(){
			toast('تم إرسال طلب الإجازة المرضية بنجاح.');
			sickStart.value = ''; sickEnd.value = ''; sickNotes.value = ''; sickFileInput.value = ''; sickFileNameEl.hidden = true;
			sickFormWrap.hidden = true;
			loadSickRequests();
		}).catch(function(err){
			sickMsg.hidden = false; sickMsg.className = 'et-inline-msg et-inline-msg--error';
			sickMsg.textContent = typeof err === 'string' ? err : 'تعذر إرسال الطلب.';
		}).then(function(){ sickSubmitBtn.disabled = false; });
	});

	var sickListLoading = document.getElementById('et-sick-list-loading');
	var sickListEmpty = document.getElementById('et-sick-list-empty');
	var sickList = document.getElementById('et-sick-list');

	function loadSickRequests(){
		sickListLoading.hidden = false; sickListEmpty.hidden = true; sickList.innerHTML = '';
		apiFetch('/sick-leave/requests?token=' + encodeURIComponent(state.token)).then(function(data){
			sickListLoading.hidden = true;
			var items = data.requests || [];
			if(!items.length){ sickListEmpty.hidden = false; return; }
			items.forEach(function(r){ sickList.appendChild(buildSickCard(r)); });
		}).catch(function(){
			sickListLoading.hidden = true; sickListEmpty.hidden = false;
			sickListEmpty.textContent = 'تعذر تحميل السجل.';
		});
	}

	function buildSickCard(r){
		var box = document.createElement('div');
		box.className = 'et-admin-task';
		var title = document.createElement('div');
		title.className = 'et-admin-task-title';
		title.textContent = 'من ' + r.start_date + ' إلى ' + r.end_date + ' (' + r.days_count + ' يوم)';
		box.appendChild(title);
		var meta = document.createElement('div');
		meta.className = 'et-admin-task-meta';
		var badge = document.createElement('span');
		badge.className = perfBadgeClass(leaveStatusColor(r.status));
		badge.textContent = r.status_label;
		meta.appendChild(badge);
		box.appendChild(meta);
		if(r.notes){ var n = document.createElement('div'); n.className = 'et-task-desc'; n.textContent = '📝 ' + r.notes; box.appendChild(n); }
		if(r.attachment_url){
			var att = document.createElement('div'); att.className = 'et-task-desc';
			var link = document.createElement('a'); link.href = r.attachment_url; link.target = '_blank'; link.rel = 'noopener';
			link.textContent = '📎 ' + (r.attachment_name || 'عرض الملف المرفق');
			att.appendChild(link); box.appendChild(att);
		}
		if(r.status === 'rejected' && r.rejection_reason){ var rr = document.createElement('div'); rr.className = 'et-task-desc'; rr.textContent = '❌ سبب الرفض: ' + r.rejection_reason; box.appendChild(rr); }
		if(r.admin_notes){ var an = document.createElement('div'); an.className = 'et-task-desc'; an.textContent = '💬 ملاحظة الإدارة: ' + r.admin_notes; box.appendChild(an); }
		return box;
	}

	// ---- الاستئذانات ----
	var permissionNewBtn = document.getElementById('et-permission-new-btn');
	var permissionFormWrap = document.getElementById('et-permission-form-wrap');
	var permissionDate = document.getElementById('et-permission-date');
	var permissionReason = document.getElementById('et-permission-reason');
	var permissionTimeOut = document.getElementById('et-permission-time-out');
	var permissionTimeReturn = document.getElementById('et-permission-time-return');
	var permissionNotes = document.getElementById('et-permission-notes');
	var permissionMsg = document.getElementById('et-permission-msg');
	var permissionSubmitBtn = document.getElementById('et-permission-submit-btn');
	permissionNewBtn.addEventListener('click', function(){ permissionFormWrap.hidden = !permissionFormWrap.hidden; });

	permissionSubmitBtn.addEventListener('click', function(){
		permissionMsg.hidden = true;
		if(!permissionDate.value || !permissionTimeOut.value){
			permissionMsg.hidden = false; permissionMsg.className = 'et-inline-msg et-inline-msg--error';
			permissionMsg.textContent = 'الرجاء تعبئة التاريخ ووقت الخروج.';
			return;
		}
		permissionSubmitBtn.disabled = true;
		apiFetch('/permission/requests/create', {
			method: 'POST', headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ token: state.token, perm_date: permissionDate.value, time_out: permissionTimeOut.value, time_return: permissionTimeReturn.value, reason: permissionReason.value, notes: permissionNotes.value })
		}).then(function(){
			toast('تم إرسال طلب الاستئذان بنجاح.');
			permissionDate.value = ''; permissionReason.value = ''; permissionTimeOut.value = ''; permissionTimeReturn.value = ''; permissionNotes.value = '';
			permissionFormWrap.hidden = true;
			loadPermissionRequests();
		}).catch(function(err){
			permissionMsg.hidden = false; permissionMsg.className = 'et-inline-msg et-inline-msg--error';
			permissionMsg.textContent = typeof err === 'string' ? err : 'تعذر إرسال الطلب.';
		}).then(function(){ permissionSubmitBtn.disabled = false; });
	});

	var permissionListLoading = document.getElementById('et-permission-list-loading');
	var permissionListEmpty = document.getElementById('et-permission-list-empty');
	var permissionListEl = document.getElementById('et-permission-list');

	function loadPermissionRequests(){
		permissionListLoading.hidden = false; permissionListEmpty.hidden = true; permissionListEl.innerHTML = '';
		apiFetch('/permission/requests?token=' + encodeURIComponent(state.token)).then(function(data){
			permissionListLoading.hidden = true;
			var items = data.requests || [];
			if(!items.length){ permissionListEmpty.hidden = false; return; }
			items.forEach(function(r){ permissionListEl.appendChild(buildPermissionCard(r)); });
		}).catch(function(){
			permissionListLoading.hidden = true; permissionListEmpty.hidden = false;
			permissionListEmpty.textContent = 'تعذر تحميل الطلبات.';
		});
	}

	function buildPermissionCard(r){
		var box = document.createElement('div');
		box.className = 'et-admin-task';
		var title = document.createElement('div');
		title.className = 'et-admin-task-title';
		title.textContent = r.perm_date + ' — من ' + r.time_out + (r.time_return ? (' إلى ' + r.time_return) : ' (وقت العودة غير محدد)');
		box.appendChild(title);
		var meta = document.createElement('div');
		meta.className = 'et-admin-task-meta';
		var badge = document.createElement('span');
		badge.className = perfBadgeClass(leaveStatusColor(r.status));
		badge.textContent = r.status_label;
		meta.appendChild(badge);
		box.appendChild(meta);
		if(r.reason){ var rs = document.createElement('div'); rs.className = 'et-task-desc'; rs.textContent = '🗒️ السبب: ' + r.reason; box.appendChild(rs); }
		if(r.notes){ var n = document.createElement('div'); n.className = 'et-task-desc'; n.textContent = '📝 ' + r.notes; box.appendChild(n); }
		if(r.status === 'rejected' && r.rejection_reason){ var rr = document.createElement('div'); rr.className = 'et-task-desc'; rr.textContent = '❌ سبب الرفض: ' + r.rejection_reason; box.appendChild(rr); }
		if(r.admin_notes){ var an = document.createElement('div'); an.className = 'et-task-desc'; an.textContent = '💬 ملاحظة الإدارة: ' + r.admin_notes; box.appendChild(an); }
		return box;
	}

	/** يبني قائمة "طلب مساعدة" المستقلة: يجلب كل مهام الموظف (وليس فقط المهام المشتركة مع زملاء)، ثم يبني
	 *  اختيار الزميل ونوع المساعدة تبعًا للمهمة المختارة — بدل الاضطرار لفتح المهمة نفسها أولاً. اختيار
	 *  الزميل يشمل كل موظفي المؤسسة (state.employees من /employees) بغض النظر عن كونهم أعضاء بالمهمة أصلاً،
	 *  حتى يقدر الموظف يطلب مساعدة أو يُحوّل الدور لأي زميل، ولو كانت مهمته فردية. */
	function loadHelpPanel(){
		helpFormWrap.hidden = true;
		helpNoTasksEl.hidden = true;
		apiFetch('/weekly-tasks?token=' + encodeURIComponent(state.token)).then(function(data){
			var all = [].concat(data.previous_pending || [], data.current || []);
			helpTasksData = all;
			if(!all.length){
				helpNoTasksEl.hidden = false;
				return;
			}
			helpFormWrap.hidden = false;
			helpTaskSelect.innerHTML = '';
			all.forEach(function(t){
				var opt = document.createElement('option');
				opt.value = t.id;
				opt.textContent = t.title + ' (' + t.week_start_date + ')';
				helpTaskSelect.appendChild(opt);
			});
			renderHelpPeerOptions();
		}).catch(function(){
			helpNoTasksEl.hidden = false;
			helpNoTasksEl.textContent = 'تعذر تحميل المهام، حاول مجددًا.';
		});
	}

	function renderHelpPeerOptions(){
		var task = helpTasksData.filter(function(t){ return String(t.id) === String(helpTaskSelect.value); })[0];
		helpPeerSelect.innerHTML = '';
		helpPendingBox.innerHTML = '';
		if(!task) return;
		state.employees.filter(function(e){ return String(e.id) !== String(state.employeeId); }).forEach(function(e){
			var opt = document.createElement('option'); opt.value = e.id; opt.textContent = e.name;
			helpPeerSelect.appendChild(opt);
		});
		(task.pending_help || []).forEach(function(h){
			var row = document.createElement('div');
			row.className = 'et-help-pending-row';
			var span = document.createElement('span');
			span.textContent = '🆘 ' + h.from_name + ' ← ' + h.to_name + ': ' + (h.message || '');
			row.appendChild(span);
			var resolveBtn = document.createElement('button');
			resolveBtn.type = 'button'; resolveBtn.textContent = 'تم الحل';
			resolveBtn.addEventListener('click', function(){
				apiFetch('/weekly-tasks/help/resolve', {
					method: 'POST', headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({ token: state.token, request_id: h.id })
				}).then(function(){ row.remove(); }).catch(function(){});
			});
			row.appendChild(resolveBtn);
			helpPendingBox.appendChild(row);
		});
	}
	helpTaskSelect.addEventListener('change', renderHelpPeerOptions);

	[['review','مراجعة'],['technical','مشكلة تقنية'],['data','بيانات ناقصة'],['other','أخرى']].forEach(function(o){
		var opt = document.createElement('option'); opt.value = o[0]; opt.textContent = o[1];
		helpTypeSelect.appendChild(opt);
	});

	helpSubmitBtn.addEventListener('click', function(){
		helpMsgEl.hidden = true;
		if(!helpTaskSelect.value || !helpPeerSelect.value) return;
		helpSubmitBtn.disabled = true;
		var fd = new FormData();
		fd.append('token', state.token);
		fd.append('task_id', helpTaskSelect.value);
		fd.append('to_employee_id', helpPeerSelect.value);
		fd.append('help_type', helpTypeSelect.value);
		fd.append('message', helpMessageInput.value);
		if(helpFileInput.files && helpFileInput.files.length){ fd.append('attachment', helpFileInput.files[0], helpFileInput.files[0].name); }
		apiFetch('/weekly-tasks/help/request', { method: 'POST', body: fd }).then(function(){
			helpMessageInput.value = '';
			helpFileInput.value = ''; helpFileNameEl.hidden = true;
			toast('تم إرسال طلب المساعدة.');
			loadHelpPanel();
		}).catch(function(err){
			helpMsgEl.hidden = false;
			helpMsgEl.className = 'et-inline-msg et-inline-msg--error';
			helpMsgEl.textContent = typeof err === 'string' ? err : 'تعذر إرسال الطلب، حاول مجددًا.';
		}).then(function(){ helpSubmitBtn.disabled = false; });
	});

	handoffSubmitBtn.addEventListener('click', function(){
		handoffMsgEl.hidden = true;
		if(!helpTaskSelect.value || !helpPeerSelect.value) return;
		handoffSubmitBtn.disabled = true;
		var fd2 = new FormData();
		fd2.append('token', state.token);
		fd2.append('task_id', helpTaskSelect.value);
		fd2.append('to_employee_id', helpPeerSelect.value);
		fd2.append('message', handoffMessageInput.value);
		if(handoffFileInput.files && handoffFileInput.files.length){ fd2.append('attachment', handoffFileInput.files[0], handoffFileInput.files[0].name); }
		apiFetch('/weekly-tasks/help/handoff', { method: 'POST', body: fd2 }).then(function(){
			handoffMessageInput.value = '';
			handoffFileInput.value = ''; handoffFileNameEl.hidden = true;
			toast('تم تسجيل إنجاز جزئك وتحويل الدور للزميل.');
			loadHelpPanel();
			if(tasksLoaded){ loadWeeklyTasks(); }
		}).catch(function(err){
			handoffMsgEl.hidden = false;
			handoffMsgEl.className = 'et-inline-msg et-inline-msg--error';
			handoffMsgEl.textContent = typeof err === 'string' ? err : 'تعذر تنفيذ العملية، حاول مجددًا.';
		}).then(function(){ handoffSubmitBtn.disabled = false; });
	});

	/** يبني عنصر تعليق واحد (يُستخدم لتعليقات المهام الأسبوعية وتعليقات المهام/التحديثات المرسلة معًا) */
	function buildReplyCommentEl(author, message, date){
		var el = document.createElement('div');
		el.className = 'et-admin-comment';
		var b = document.createElement('b');
		b.textContent = author + ': ';
		el.appendChild(b);
		el.appendChild(document.createTextNode(message));
		var span = document.createElement('span');
		span.textContent = date;
		el.appendChild(span);
		return el;
	}

	function buildMyUpdateReplyCard(u){
		var box = document.createElement('div');
		box.className = 'et-admin-task';

		var title = document.createElement('div');
		title.className = 'et-admin-task-title';
		title.textContent = 'تحديث يوم ' + u.update_date;
		box.appendChild(title);

		var summary = document.createElement('div');
		summary.className = 'et-task-desc';
		summary.textContent = u.achievements || '—';
		box.appendChild(summary);

		var commentsBox = document.createElement('div');
		commentsBox.className = 'et-admin-comments';
		(u.comments || []).forEach(function(c){ commentsBox.appendChild(buildReplyCommentEl(c.author, c.message, c.date)); });
		box.appendChild(commentsBox);

		return box;
	}

	/** تحمّل تحديثات الموظف الأخيرة وتعرض فقط ما وصله عليه تعليق من الإدارة (📨 ردود الإدارة) */
	function loadMyUpdateReplies(){
		repliesLoading.hidden = false;
		repliesEmpty.hidden = true;
		repliesList.innerHTML = '';
		apiFetch('/my-updates?token=' + encodeURIComponent(state.token)).then(function(data){
			repliesLoading.hidden = true;
			var withComments = (data.updates || []).filter(function(u){ return u.comments && u.comments.length; });
			if(repliesCountBadge){
				if(withComments.length > 0){ repliesCountBadge.hidden = false; repliesCountBadge.textContent = withComments.length; }
				else { repliesCountBadge.hidden = true; }
			}
			if(!withComments.length){ repliesEmpty.hidden = false; return; }
			withComments.forEach(function(u){ repliesList.appendChild(buildMyUpdateReplyCard(u)); });
		}).catch(function(){
			repliesLoading.hidden = true;
			repliesEmpty.hidden = false;
			repliesEmpty.textContent = 'تعذر تحميل الردود، حاول مجددًا.';
		});
	}

	/* ---------- مستوى الأداء الشهري (مشترك بين واجهة الموظف وواجهة الإدارة) ---------- */

	function perfBadgeClass(color){ return 'et-perf-badge et-perf-badge--' + color; }
	function perfBarColor(color){
		if(color === 'green') return '#1a7a41';
		if(color === 'orange') return '#b06a00';
		if(color === 'red') return '#b3261e';
		return '#c7cbd1';
	}
	function perfTipText(perf){
		if(perf.overall_score === null) return 'لا توجد بيانات كافية بعد لحساب مستوى الأداء لهذا الشهر.';
		if(perf.level_color === 'green') return 'أداء ممتاز 👏 استمر على نفس الوتيرة في إنجاز المهام بوقتها وإرسال تحديثك اليومي بانتظام.';
		if(perf.level_color === 'orange') return 'أداء جيد بشكل عام، وفيه مجال للتحسين. حاول إنجاز المهام المتأخرة بأسرع وقت، والحرص على إرسال التحديث اليومي كل يوم عمل.';
		return 'المستوى هذا الشهر يحتاج تحسين ملحوظ. ركّز على إنجاز مهامك في موعدها، وأرسل تحديثك اليومي بانتظام، وحاول تقليل المشاكل المتكررة.';
	}

	function buildPerformanceCard(perf, showName){
		var card = document.createElement('div');
		card.className = 'et-perf-card';

		var head = document.createElement('div');
		head.className = 'et-perf-head';
		if(showName){
			var nameEl = document.createElement('div');
			nameEl.className = 'et-perf-name';
			nameEl.textContent = perf.employee_name;
			head.appendChild(nameEl);
		}
		var scoreWrap = document.createElement('div');
		scoreWrap.style.display = 'flex'; scoreWrap.style.alignItems = 'center'; scoreWrap.style.gap = '10px';
		var scoreEl = document.createElement('div');
		scoreEl.className = 'et-perf-score';
		scoreEl.textContent = perf.overall_score !== null ? (perf.overall_score + '٪') : '—';
		var badge = document.createElement('span');
		badge.className = perfBadgeClass(perf.level_color);
		badge.textContent = perf.level;
		scoreWrap.appendChild(scoreEl);
		scoreWrap.appendChild(badge);
		head.appendChild(scoreWrap);
		card.appendChild(head);

		var bar = document.createElement('div');
		bar.className = 'et-perf-bar';
		var fill = document.createElement('div');
		fill.className = 'et-perf-bar-fill';
		fill.style.width = (perf.overall_score !== null ? perf.overall_score : 0) + '%';
		fill.style.background = perfBarColor(perf.level_color);
		bar.appendChild(fill);
		card.appendChild(bar);

		var grid = document.createElement('div');
		grid.className = 'et-perf-breakdown';
		var b = perf.breakdown;
		function metric(label, value){
			var m = document.createElement('div'); m.className = 'et-perf-metric';
			var l = document.createElement('div'); l.className = 'et-perf-metric-label'; l.textContent = label;
			var v = document.createElement('div'); v.className = 'et-perf-metric-value'; v.textContent = value;
			m.appendChild(l); m.appendChild(v);
			return m;
		}
		grid.appendChild(metric('المهام في وقتها', b.tasks.total > 0 ? (b.tasks.on_time + ' من ' + b.tasks.total + ' (' + b.tasks.score + '٪)') : 'لا توجد مهام مستحقة'));
		grid.appendChild(metric('الانتظام اليومي', b.consistency.expected_days > 0 ? (b.consistency.submitted_days + ' من ' + b.consistency.expected_days + ' يوم') : '—'));
		grid.appendChild(metric('تحديثات بدون مشاكل', b.quality.total_updates > 0 ? ((b.quality.total_updates - b.quality.issue_updates) + ' من ' + b.quality.total_updates) : '—'));
		card.appendChild(grid);

		var tip = document.createElement('div');
		tip.className = 'et-perf-tip';
		tip.textContent = perfTipText(perf);
		card.appendChild(tip);

		return card;
	}

	function currentMonthValue(){
		var now = new Date();
		return now.getFullYear() + '-' + ('0' + (now.getMonth() + 1)).slice(-2);
	}

	perfMonthInput.value = currentMonthValue();
	perfMonthInput.max = currentMonthValue();

	function loadMyPerformance(){
		perfLoading.hidden = false;
		perfContent.innerHTML = '';
		var parts = (perfMonthInput.value || currentMonthValue()).split('-');
		var qs = 'token=' + encodeURIComponent(state.token) + '&year=' + parts[0] + '&month=' + parseInt(parts[1], 10);
		apiFetch('/my-performance?' + qs).then(function(perf){
			perfLoading.hidden = true;
			perfContent.innerHTML = '';
			perfContent.appendChild(buildPerformanceCard(perf, false));
		}).catch(function(){
			perfLoading.hidden = true;
			perfContent.innerHTML = '<div class="et-empty">تعذر تحميل بيانات الأداء.</div>';
		});
	}
	perfMonthInput.addEventListener('change', loadMyPerformance);

	function updateTasksBadge(){
		var pendingCount = tasksWrap.querySelectorAll('.et-task-item:not(.et-task-item--done)').length;
		if(pendingCount > 0){
			tasksCountBadge.hidden = false;
			tasksCountBadge.textContent = pendingCount;
		} else {
			tasksCountBadge.hidden = true;
		}
	}

	/** خريطة سير عمل مشروع، بشكل مبسّط وللقراءة فقط، تُعرض للموظف داخل بطاقة مهمته (بدون أزرار تعديل) */
	function renderEmployeeProjectChain(container, chain){
		container.innerHTML = '';
		if(!chain || !chain.length){ container.textContent = 'لا توجد مهام في هذا المشروع.'; return; }
		chain.forEach(function(node, idx){
			var el = document.createElement('div');
			el.className = 'et-chain-node et-chain-node--' + node.node_state;
			el.style.marginBottom = '6px';

			var top = document.createElement('div');
			top.className = 'et-chain-node-top';
			var titleEl = document.createElement('div');
			titleEl.className = 'et-chain-node-title';
			titleEl.textContent = (idx + 1) + '. ' + node.title;
			var badge = document.createElement('span');
			badge.className = 'et-chain-node-badge et-chain-node-badge--' + node.node_state;
			badge.textContent = node.node_state === 'done' ? '✓ مكتملة' : node.node_state === 'in_progress' ? 'قيد التنفيذ' : node.node_state === 'stuck' ? '⚠ متوقفة' : node.node_state === 'blocked' ? 'معلّقة' : 'لم تبدأ';
			top.appendChild(titleEl);
			top.appendChild(badge);
			el.appendChild(top);

			var metaEl = document.createElement('div');
			metaEl.className = 'et-chain-node-meta';
			var names = (node.members && node.members.length) ? node.members.map(function(m){ return m.name + ' ' + m.progress_percent + '٪'; }).join(' · ') : node.employee_name;
			metaEl.textContent = '👤 ' + names;
			el.appendChild(metaEl);

			if(node.node_reason){
				var reasonEl = document.createElement('div');
				reasonEl.className = 'et-chain-node-reason';
				reasonEl.textContent = node.node_state === 'stuck' ? ('⚠ ' + node.node_reason) : node.node_reason;
				el.appendChild(reasonEl);
			}
			container.appendChild(el);
			if(idx < chain.length - 1){
				var arrow = document.createElement('div');
				arrow.className = 'et-chain-arrow';
				arrow.textContent = '↓';
				container.appendChild(arrow);
			}
		});
	}

	function buildTaskItem(task){
		var li = document.createElement('li');
		// "منجزة من جهتي" لا تعني بالضرورة "منجزة نهائيًا" (task.status==='done' يعني اعتماد الإدارة النهائي
		// فقط) — بمهمة مشتركة، نصيبي قد يصل 100٪ بينما المهمة نفسها لا تزال 'review' (بانتظار الطرف الآخر
		// أو اعتماد الإدارة). لو ربطنا صندوق الاختيار بـ task.status==='done' فقط، يظهر للموظف "غير مؤشَّر"
		// فور تسجيل إنجازه رغم نجاح الطلب فعليًا — فيظن أن ضغطته لم تُسجَّل ويضغط مجددًا بلا أي أثر إضافي
		// (لأن الخادم يتجاهل عمدًا تكرار "الوصول لـ100٪" لنفس الموظف لتفادي تكرار البريد/الإشعار)، فيبدو له
		// وكأن شيئًا لا يعمل. الصحيح: نعرض الصندوق كمؤشَّر إن كان نصيبي أنا تحديدًا قد وصل 100٪ (سواء كانت
		// حالة المهمة الكلية 'review' بانتظار الاعتماد أو 'done' بعد الاعتماد)، بغض النظر عن نصيب الآخرين.
		var myMember = (task.members || []).filter(function(m){ return m.is_you; })[0];
		var myDone = myMember ? (myMember.progress_percent >= 100) : (task.status === 'done');
		li.className = 'et-task-item' + (myDone ? ' et-task-item--done' : '');
		li.dataset.id = task.id;

		var checkbox = document.createElement('input');
		checkbox.type = 'checkbox';
		checkbox.checked = myDone;
		checkbox.addEventListener('change', function(){
			var completed = checkbox.checked;
			li.classList.toggle('et-task-item--done', completed);
			apiFetch('/weekly-tasks/complete', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ token: state.token, task_id: task.id, completed: completed ? '1' : '0' })
			}).then(function(){
				updateTasksBadge();
				// تأكيد صريح للموظف أن إنجازه تسجّل (بدل الاكتفاء بخط فوق العنوان قد لا يلاحظه) —
				// ثم إعادة تحميل القائمة فورًا حتى تظهر شارة "بانتظار الاعتماد"، أو تختفي المهمة تمامًا
				// من القائمة لو كانت هذه آخر خطوة واعتمدتها الإدارة مسبقًا بشكل تلقائي.
				if(completed){ toast('تم تسجيل إنجازك — بانتظار اعتماد الإدارة النهائي.'); }
				loadWeeklyTasks();
			}).catch(function(){
				checkbox.checked = !completed;
				li.classList.toggle('et-task-item--done', !completed);
			});
		});

		var body = document.createElement('div');
		body.className = 'et-task-body';
		var title = document.createElement('div');
		title.className = 'et-task-title';
		title.textContent = task.title;
		body.appendChild(title);
		if(task.description){
			var desc = document.createElement('div');
			desc.className = 'et-task-desc';
			desc.textContent = task.description;
			body.appendChild(desc);
		}
		if(task.priority_label){
			var meta = document.createElement('div');
			meta.className = 'et-task-meta';
			var pill = document.createElement('span');
			pill.className = 'et-priority-pill et-priority-pill--' + (task.priority || 'medium');
			pill.textContent = task.priority_label;
			meta.appendChild(pill);
			if(task.is_team_task){
				var teamPill = document.createElement('span');
				teamPill.className = 'et-team-pill';
				teamPill.textContent = '👥 مهمة جماعية';
				meta.appendChild(teamPill);
			}
			body.appendChild(meta);
		}

		if(task.urgency){
			var urgBadge = document.createElement('span');
			urgBadge.className = 'et-urgency-badge et-urgency-badge--' + task.urgency.level;
			urgBadge.textContent = '⏰ ' + task.urgency.label;
			body.appendChild(urgBadge);
		}
		if(task.status === 'review'){
			var reviewBadge = document.createElement('div');
			reviewBadge.className = 'et-review-badge';
			reviewBadge.textContent = '🏷 بانتظار اعتماد الإدارة النهائي';
			body.appendChild(reviewBadge);
		}

		// شريط نسبة الإنجاز الفعلي للمهمة (بدل "الحالة" وحدها) — لمهمة جماعية هذا متوسط كل الأعضاء
		var progress = task.progress_percent || 0;
		var progWrap = document.createElement('div');
		progWrap.className = 'et-task-progress-wrap';
		var progBar = document.createElement('div');
		progBar.className = 'et-task-progress-bar';
		var progFill = document.createElement('div');
		progFill.className = 'et-task-progress-fill';
		progFill.style.width = progress + '%';
		progBar.appendChild(progFill);
		var progLabel = document.createElement('div');
		progLabel.className = 'et-task-progress-label';
		progLabel.textContent = progress + '٪';
		progWrap.appendChild(progBar);
		progWrap.appendChild(progLabel);
		body.appendChild(progWrap);

		// شارة المشروع: هذه المهمة خطوة ضمن سلسلة عمل متسلسلة — بالضغط عليها تظهر خريطة السلسلة كاملة للموظف
		if(task.project){
			var projectBadge = document.createElement('button');
			projectBadge.type = 'button';
			projectBadge.className = 'et-project-badge-inline';
			projectBadge.textContent = '🗺️ ضمن مشروع: ' + task.project.title;
			var projectChainBox = document.createElement('div');
			projectChainBox.className = 'et-task-timeline';
			projectChainBox.hidden = true;
			var projectChainLoaded = false;
			projectBadge.addEventListener('click', function(){
				projectChainBox.hidden = !projectChainBox.hidden;
				if(!projectChainBox.hidden && !projectChainLoaded){
					projectChainBox.innerHTML = 'جارِ التحميل…';
					apiFetch('/my-project-chain?token=' + encodeURIComponent(state.token) + '&project_id=' + task.project.id).then(function(data){
						projectChainLoaded = true;
						renderEmployeeProjectChain(projectChainBox, data.chain);
					}).catch(function(){ projectChainBox.textContent = 'تعذر تحميل خريطة المشروع.'; });
				}
			});
			body.appendChild(projectBadge);
			body.appendChild(projectChainBox);
		}

		// لمهمة جماعية: نعرض نسبة كل عضو من الفريق على حدة، ونميّز مساهمة الموظف نفسه
		if(task.is_team_task && task.members && task.members.length){
			var teamBox = document.createElement('div');
			teamBox.className = 'et-team-members';
			task.members.forEach(function(m){
				var row = document.createElement('div');
				row.className = 'et-team-member-row' + (m.is_you ? ' et-team-member-row--you' : '');
				var nameEl = document.createElement('span');
				nameEl.className = 'et-team-member-name';
				nameEl.textContent = (m.is_you ? 'أنت' : m.name) + (m.role_label ? ' · ' + m.role_label : '');
				var barWrap = document.createElement('div');
				barWrap.className = 'et-team-member-bar';
				var barFill = document.createElement('div');
				barFill.className = 'et-team-member-bar-fill';
				barFill.style.width = (m.progress_percent || 0) + '%';
				barWrap.appendChild(barFill);
				var pctEl = document.createElement('span');
				pctEl.className = 'et-team-member-pct';
				pctEl.textContent = (m.progress_percent || 0) + '٪';
				row.appendChild(nameEl);
				row.appendChild(barWrap);
				row.appendChild(pctEl);
				teamBox.appendChild(row);
			});
			body.appendChild(teamBox);
		}

		// صف مضغوط من أزرار الوصول السريع لباقي تفاصيل المهمة (Checklist/مرفقات) — بدل عرضها دائمًا
		// بشكل مفتوح ومزدحم، تُطوى افتراضيًا وتُفتح عند الحاجة أو تلقائيًا لو فيها محتوى فعلًا
		var extrasRow = document.createElement('div');
		extrasRow.className = 'et-extras-row';
		body.appendChild(extrasRow);

		// Checklist فرعي داخل المهمة — بنود صح/خطأ منفصلة عن نسبة الإنجاز العامة
		var checklistToggle = document.createElement('button');
		checklistToggle.type = 'button';
		checklistToggle.className = 'et-extras-toggle';
		extrasRow.appendChild(checklistToggle);
		var checklistBox = document.createElement('div');
		checklistBox.className = 'et-checklist';
		checklistBox.hidden = true;
		var checklistItemsWrap = document.createElement('div');
		checklistBox.appendChild(checklistItemsWrap);
		function updateChecklistToggleLabel(items){
			var done = items.filter(function(i){ return i.done; }).length;
			checklistToggle.textContent = '✅ قائمة الإنجاز (' + done + '/' + items.length + ')';
		}
		function renderChecklist(items){
			checklistItemsWrap.innerHTML = '';
			updateChecklistToggleLabel(items);
			items.forEach(function(it){
				var row = document.createElement('div');
				row.className = 'et-checklist-item' + (it.done ? ' et-checklist-item--done' : '');
				var cb = document.createElement('input');
				cb.type = 'checkbox'; cb.checked = it.done;
				cb.addEventListener('change', function(){
					apiFetch('/weekly-tasks/checklist/toggle', {
						method: 'POST', headers: { 'Content-Type': 'application/json' },
						body: JSON.stringify({ token: state.token, task_id: task.id, item_id: it.id, done: cb.checked ? '1' : '' })
					}).then(function(res){ task.checklist = res.items; renderChecklist(res.items); }).catch(function(){});
				});
				var span = document.createElement('span'); span.textContent = it.text;
				row.appendChild(cb); row.appendChild(span);
				checklistItemsWrap.appendChild(row);
			});
		}
		renderChecklist(task.checklist || []);
		var checklistAddRow = document.createElement('div');
		checklistAddRow.className = 'et-checklist-add-row';
		var checklistInput = document.createElement('input');
		checklistInput.type = 'text'; checklistInput.placeholder = 'أضف بندًا جديدًا…';
		var checklistAddBtn = document.createElement('button');
		checklistAddBtn.type = 'button'; checklistAddBtn.textContent = 'إضافة';
		checklistAddBtn.addEventListener('click', function(){
			var text = checklistInput.value.trim();
			if(!text) return;
			apiFetch('/weekly-tasks/checklist/add', {
				method: 'POST', headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ token: state.token, task_id: task.id, text: text })
			}).then(function(res){ task.checklist = res.items; renderChecklist(res.items); checklistInput.value = ''; }).catch(function(){});
		});
		checklistAddRow.appendChild(checklistInput);
		checklistAddRow.appendChild(checklistAddBtn);
		checklistBox.appendChild(checklistAddRow);
		checklistToggle.addEventListener('click', function(){ checklistBox.hidden = !checklistBox.hidden; });
		checklistBox.hidden = !(task.checklist && task.checklist.length); // تُفتح تلقائيًا لو فيها بنود موجودة أصلًا
		body.appendChild(checklistBox);

		// مرفقات مستقلة على مستوى المهمة (اسم + رابط)، منفصلة عن مرفقات التحديث اليومي
		var attachToggle = document.createElement('button');
		attachToggle.type = 'button';
		attachToggle.className = 'et-extras-toggle';
		extrasRow.appendChild(attachToggle);
		var attachBox = document.createElement('div');
		attachBox.className = 'et-task-attachments';
		attachBox.hidden = true;
		var attachRowsWrap = document.createElement('div');
		attachBox.appendChild(attachRowsWrap);
		function renderAttachments(items){
			attachRowsWrap.innerHTML = '';
			attachToggle.textContent = '📎 المرفقات (' + (items || []).length + ')';
			(items || []).forEach(function(a){
				var row = document.createElement('div');
				row.className = 'et-task-attachment-row';
				var link = document.createElement('a');
				link.href = a.url; link.target = '_blank'; link.rel = 'noopener'; link.textContent = a.name;
				row.appendChild(link);
				attachRowsWrap.appendChild(row);
			});
		}
		renderAttachments(task.attachments || []);
		var attachAddRow = document.createElement('div');
		attachAddRow.className = 'et-attachment-add-row';
		var attachNameInput = document.createElement('input');
		attachNameInput.type = 'text'; attachNameInput.placeholder = 'اسم المرفق';
		var attachUrlInput = document.createElement('input');
		attachUrlInput.type = 'text'; attachUrlInput.placeholder = 'رابط المرفق (https://...)';
		var attachAddBtn = document.createElement('button');
		attachAddBtn.type = 'button'; attachAddBtn.textContent = 'إضافة';
		attachAddBtn.addEventListener('click', function(){
			var name = attachNameInput.value.trim(), url = attachUrlInput.value.trim();
			if(!name || !url) return;
			apiFetch('/weekly-tasks/attachments/add', {
				method: 'POST', headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ token: state.token, task_id: task.id, name: name, url: url })
			}).then(function(res){ task.attachments = res.attachments; renderAttachments(res.attachments); attachNameInput.value=''; attachUrlInput.value=''; })
			.catch(function(err){ alert(typeof err === 'string' ? err : 'تعذر إضافة المرفق.'); });
		});
		attachAddRow.appendChild(attachNameInput);
		attachAddRow.appendChild(attachUrlInput);
		attachAddRow.appendChild(attachAddBtn);
		attachBox.appendChild(attachAddRow);
		attachToggle.addEventListener('click', function(){ attachBox.hidden = !attachBox.hidden; });
		attachBox.hidden = !(task.attachments && task.attachments.length);
		body.appendChild(attachBox);

		// طلبات المساعدة المعلّقة على هذه المهمة (موجّهة لزميل محدد) — تبقى ظاهرة دائمًا لأنها تحتاج إجراء
		if(task.pending_help && task.pending_help.length){
			var helpPendingBox = document.createElement('div');
			helpPendingBox.className = 'et-help-pending';
			task.pending_help.forEach(function(h){
				var row = document.createElement('div');
				row.className = 'et-help-pending-row';
				var span = document.createElement('span');
				span.textContent = '🆘 ' + h.from_name + ' ← ' + h.to_name + ': ' + (h.message || '');
				row.appendChild(span);
				var resolveBtn = document.createElement('button');
				resolveBtn.type = 'button'; resolveBtn.textContent = 'تم الحل';
				resolveBtn.addEventListener('click', function(){
					apiFetch('/weekly-tasks/help/resolve', {
						method: 'POST', headers: { 'Content-Type': 'application/json' },
						body: JSON.stringify({ token: state.token, request_id: h.id })
					}).then(function(){ row.remove(); }).catch(function(){});
				});
				row.appendChild(resolveBtn);
				helpPendingBox.appendChild(row);
			});
			body.appendChild(helpPendingBox);
		}

		// طلب مساعدة موجّه لزميل محدد بهذه المهمة (بدل Checkbox عام "أحتاج مساعدة") — ضمن نفس صف الأزرار المضغوط.
		// يعمل على أي مهمة من مهام الموظف، وليس فقط المهام المشتركة أصلاً — الزميل المختار قد لا يكون عضوًا
		// بالمهمة بعد، وهذا مقصود (طلب المساعدة لا يُضيفه كعضو، فقط يُبلغه).
		{
			var helpToggle = document.createElement('button');
			helpToggle.type = 'button';
			helpToggle.className = 'et-extras-toggle et-extras-toggle--warn';
			helpToggle.textContent = '🆘 طلب مساعدة';
			extrasRow.appendChild(helpToggle);
			var helpForm = document.createElement('div');
			helpForm.className = 'et-help-form';
			helpForm.hidden = true;

			var helpPeerLabel = document.createElement('label'); helpPeerLabel.textContent = 'اطلب المساعدة من';
			var helpPeerSelect = document.createElement('select');
			state.employees.filter(function(e){ return String(e.id) !== String(state.employeeId); }).forEach(function(e){
				var opt = document.createElement('option'); opt.value = e.id; opt.textContent = e.name;
				helpPeerSelect.appendChild(opt);
			});
			var helpTypeLabel = document.createElement('label'); helpTypeLabel.textContent = 'نوع المساعدة';
			var helpTypeSelect = document.createElement('select');
			[['review','مراجعة'],['technical','مشكلة تقنية'],['data','بيانات ناقصة'],['other','أخرى']].forEach(function(o){
				var opt = document.createElement('option'); opt.value = o[0]; opt.textContent = o[1];
				helpTypeSelect.appendChild(opt);
			});
			var helpMsgLabel = document.createElement('label'); helpMsgLabel.textContent = 'رسالة';
			var helpMsgInput = document.createElement('textarea'); helpMsgInput.placeholder = 'وضّح المطلوب…';
			var helpSubmitBtn = document.createElement('button');
			helpSubmitBtn.type = 'button'; helpSubmitBtn.textContent = 'إرسال الطلب';
			helpSubmitBtn.addEventListener('click', function(){
				helpSubmitBtn.disabled = true;
				apiFetch('/weekly-tasks/help/request', {
					method: 'POST', headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({ token: state.token, task_id: task.id, to_employee_id: helpPeerSelect.value, help_type: helpTypeSelect.value, message: helpMsgInput.value })
				}).then(function(){
					helpForm.hidden = true; helpMsgInput.value = '';
					toast('تم إرسال طلب المساعدة.');
				}).catch(function(){}).then(function(){ helpSubmitBtn.disabled = false; });
			});
			helpForm.appendChild(helpPeerLabel); helpForm.appendChild(helpPeerSelect);
			helpForm.appendChild(helpTypeLabel); helpForm.appendChild(helpTypeSelect);
			helpForm.appendChild(helpMsgLabel); helpForm.appendChild(helpMsgInput);
			helpForm.appendChild(helpSubmitBtn);
			helpToggle.addEventListener('click', function(){ helpForm.hidden = !helpForm.hidden; });
			body.appendChild(helpForm);
		}

		// آخر تحديث مرفوع على هذه المهمة تحديدًا (لا التحديث اليومي العام)
		var latestBox = document.createElement('div');
		latestBox.className = 'et-task-latest';
		latestBox.hidden = !task.latest_update;
		body.appendChild(latestBox);
		function renderLatest(u){
			latestBox.innerHTML = '';
			if(!u){ latestBox.hidden = true; return; }
			latestBox.hidden = false;
			if(u.work_status_label){
				var statusLine = document.createElement('div');
				var statusBadge = document.createElement('span');
				statusBadge.className = 'et-work-status-badge et-work-status-badge--' + (u.work_status || 'in_progress');
				statusBadge.textContent = u.work_status_label;
				statusLine.appendChild(statusBadge);
				latestBox.appendChild(statusLine);
			}
			var line1 = document.createElement('div');
			var b1 = document.createElement('b'); b1.textContent = 'العمل الحالي: ';
			line1.appendChild(b1);
			line1.appendChild(document.createTextNode(u.current_work || '—'));
			latestBox.appendChild(line1);
			if(u.has_problem && u.problem_description){
				var line2 = document.createElement('div');
				var flag = document.createElement('span'); flag.className='et-task-latest-flag'; flag.textContent = '⚠️ مشكلة: ';
				line2.appendChild(flag);
				line2.appendChild(document.createTextNode(u.problem_description));
				latestBox.appendChild(line2);
			}
			if(u.needs_help && u.help_description){
				var line3 = document.createElement('div');
				var flag2 = document.createElement('span'); flag2.className='et-task-latest-flag'; flag2.textContent = '🆘 تحتاج مساعدة: ';
				line3.appendChild(flag2);
				line3.appendChild(document.createTextNode(u.help_description));
				latestBox.appendChild(line3);
			}
			var dateLine = document.createElement('div');
			dateLine.style.color = 'var(--et-muted)'; dateLine.style.marginTop='3px'; dateLine.style.fontSize='10.5px';
			dateLine.textContent = u.date;
			latestBox.appendChild(dateLine);
		}
		renderLatest(task.latest_update);

		// زر "عرض السجل" يفتح Timeline كل تحديثات هذه المهمة تسلسليًا
		var timelineToggle = document.createElement('button');
		timelineToggle.type = 'button';
		timelineToggle.className = 'et-task-timeline-toggle';
		timelineToggle.textContent = 'عرض سجل التحديثات';
		var timelineBox = document.createElement('div');
		timelineBox.className = 'et-task-timeline';
		timelineBox.hidden = true;
		var timelineLoaded = false;
		timelineToggle.addEventListener('click', function(){
			timelineBox.hidden = !timelineBox.hidden;
			timelineToggle.textContent = timelineBox.hidden ? 'عرض سجل التحديثات' : 'إخفاء سجل التحديثات';
			if(!timelineBox.hidden && !timelineLoaded){
				timelineBox.innerHTML = 'جارِ التحميل…';
				apiFetch('/weekly-tasks/timeline?token=' + encodeURIComponent(state.token) + '&task_id=' + task.id).then(function(data){
					timelineLoaded = true;
					timelineBox.innerHTML = '';
					var updates = data.updates || [];
					if(!updates.length){ timelineBox.textContent = 'لا يوجد تحديثات بعد.'; return; }
					updates.forEach(function(u){
						var item = document.createElement('div');
						item.className = 'et-task-timeline-item';
						var b = document.createElement('b'); b.textContent = u.progress_percent + '٪ — ' + (u.work_status_label || '') + ' — ';
						item.appendChild(b);
						item.appendChild(document.createTextNode((u.current_work || u.achievements || '') + ' · ' + u.date));
						timelineBox.appendChild(item);
					});
				}).catch(function(){ timelineBox.textContent = 'تعذر تحميل السجل.'; });
			}
		});
		body.appendChild(timelineToggle);
		body.appendChild(timelineBox);

		// زر + نموذج "إضافة تحديث": بدل تغيير الحالة فقط، الموظف يرفع نسبة إنجاز + منجز + عمل حالي + مشكلة/مساعدة
		var updateToggle = document.createElement('button');
		updateToggle.type = 'button';
		updateToggle.className = 'et-task-update-toggle';
		updateToggle.textContent = '➕ إضافة تحديث';

		var form = document.createElement('div');
		form.className = 'et-task-update-form';
		form.hidden = true;

		var progInputLabel = document.createElement('label');
		progInputLabel.textContent = 'نسبة الإنجاز';
		var progInputRow = document.createElement('div');
		progInputRow.style.display = 'flex'; progInputRow.style.alignItems = 'center'; progInputRow.style.gap = '8px';
		var progInput = document.createElement('input');
		progInput.type = 'range'; progInput.min = '0'; progInput.max = '100'; progInput.step = '5';
		progInput.value = progress;
		var progInputVal = document.createElement('span');
		progInputVal.style.fontWeight = '700'; progInputVal.style.fontSize = '12.5px'; progInputVal.style.minWidth = '32px';
		progInputVal.textContent = progress + '٪';
		progInput.addEventListener('input', function(){ progInputVal.textContent = progInput.value + '٪'; });
		progInputRow.appendChild(progInput);
		progInputRow.appendChild(progInputVal);

		var statusLabel = document.createElement('label'); statusLabel.textContent = 'حالة العمل الآن';
		var statusSelect = document.createElement('select');
		statusSelect.className = 'et-work-status-select';
		WORK_STATUS_OPTS.forEach(function(opt){
			var o = document.createElement('option'); o.value = opt[0]; o.textContent = opt[1];
			statusSelect.appendChild(o);
		});
		statusSelect.value = 'in_progress';
		statusSelect.addEventListener('change', function(){
			if(statusSelect.value === 'problem'){ probCheck.checked = true; probInput.hidden = false; }
			if(statusSelect.value === 'need_help'){ helpCheck.checked = true; helpInput.hidden = false; }
		});

		var achLabel = document.createElement('label'); achLabel.textContent = 'ما الذي أنجزته؟';
		var achInput = document.createElement('textarea'); achInput.placeholder = 'مثال: جهزت الجزء الأول من الأسئلة';

		var curLabel = document.createElement('label'); curLabel.textContent = 'ما الذي تعمل عليه الآن؟';
		var curInput = document.createElement('textarea'); curInput.placeholder = 'مثال: أراجع الأسئلة النهائية';

		var probRow = document.createElement('div'); probRow.className = 'et-task-update-checkbox-row';
		var probCheck = document.createElement('input'); probCheck.type = 'checkbox'; probCheck.id = 'et-tu-prob-' + task.id;
		var probLabel = document.createElement('label'); probLabel.setAttribute('for', probCheck.id); probLabel.style.margin='0'; probLabel.textContent = 'هل ظهرت مشكلة؟';
		probRow.appendChild(probCheck); probRow.appendChild(probLabel);
		var probInput = document.createElement('textarea'); probInput.placeholder = 'صف المشكلة…'; probInput.hidden = true;
		probCheck.addEventListener('change', function(){ probInput.hidden = !probCheck.checked; });

		var helpRow = document.createElement('div'); helpRow.className = 'et-task-update-checkbox-row';
		var helpCheck = document.createElement('input'); helpCheck.type = 'checkbox'; helpCheck.id = 'et-tu-help-' + task.id;
		var helpLabel = document.createElement('label'); helpLabel.setAttribute('for', helpCheck.id); helpLabel.style.margin='0'; helpLabel.textContent = 'تحتاج شخصًا آخر يساعدك؟';
		helpRow.appendChild(helpCheck); helpRow.appendChild(helpLabel);
		var helpInput = document.createElement('textarea'); helpInput.placeholder = 'ما المساعدة المطلوبة ومن؟'; helpInput.hidden = true;
		helpCheck.addEventListener('change', function(){ helpInput.hidden = !helpCheck.checked; });

		var submitBtn = document.createElement('button');
		submitBtn.type = 'button';
		submitBtn.className = 'et-task-update-submit';
		submitBtn.textContent = 'إرسال التحديث';

		var formMsg = document.createElement('div');
		formMsg.className = 'et-task-update-msg';

		submitBtn.addEventListener('click', function(){
			submitBtn.disabled = true;
			formMsg.textContent = '';
			apiFetch('/weekly-tasks/update', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({
					token: state.token,
					task_id: task.id,
					progress_percent: progInput.value,
					work_status: statusSelect.value,
					achievements: achInput.value,
					current_work: curInput.value,
					has_problem: probCheck.checked ? '1' : '',
					problem_description: probInput.value,
					needs_help: helpCheck.checked ? '1' : '',
					help_description: helpInput.value
				})
			}).then(function(res){
				var newProgress = parseInt(res.progress_percent, 10) || 0;
				progFill.style.width = newProgress + '%';
				progLabel.textContent = newProgress + '٪';
				renderLatest({
					current_work: curInput.value,
					work_status: statusSelect.value,
					work_status_label: WORK_STATUS_LABELS[statusSelect.value] || '',
					has_problem: probCheck.checked,
					problem_description: probInput.value,
					needs_help: helpCheck.checked,
					help_description: helpInput.value,
					date: 'الآن'
				});
				timelineLoaded = false; // نجبر إعادة تحميل السجل عند فتحه لاحقًا حتى يظهر هذا التحديث
				if(res.status === 'done'){
					checkbox.checked = true;
					li.classList.add('et-task-item--done');
					updateTasksBadge();
				}
				form.hidden = true;
				updateToggle.textContent = '➕ إضافة تحديث';
				achInput.value = ''; curInput.value = ''; probInput.value = ''; helpInput.value = '';
				probCheck.checked = false; probInput.hidden = true;
				helpCheck.checked = false; helpInput.hidden = true;
				statusSelect.value = 'in_progress';
			}).catch(function(err){
				formMsg.style.color = '#b3261e';
				formMsg.textContent = typeof err === 'string' ? err : 'تعذر إرسال التحديث، حاول مجددًا.';
			}).then(function(){ submitBtn.disabled = false; });
		});

		form.appendChild(progInputLabel);
		form.appendChild(progInputRow);
		form.appendChild(statusLabel);
		form.appendChild(statusSelect);
		form.appendChild(achLabel);
		form.appendChild(achInput);
		form.appendChild(curLabel);
		form.appendChild(curInput);
		form.appendChild(probRow);
		form.appendChild(probInput);
		form.appendChild(helpRow);
		form.appendChild(helpInput);
		form.appendChild(submitBtn);
		form.appendChild(formMsg);

		updateToggle.addEventListener('click', function(){
			form.hidden = !form.hidden;
			updateToggle.textContent = form.hidden ? '➕ إضافة تحديث' : '➖ إخفاء نموذج التحديث';
		});

		body.appendChild(updateToggle);
		body.appendChild(form);

		li.appendChild(checkbox);
		li.appendChild(body);
		return li;
	}

	var DAY_ORDER = ['sunday','monday','tuesday','wednesday','thursday'];

	/** يجمع مهام الأسبوع الحالي في مجموعات حسب اليوم (الأحد←الخميس)، وأي مهمة بلا يوم محدد تظهر أخيرًا */
	function renderGroupedTasks(container, tasks){
		container.innerHTML = '';
		var buckets = {};
		DAY_ORDER.forEach(function(d){ buckets[d] = []; });
		buckets[''] = [];
		tasks.forEach(function(t){
			var key = DAY_ORDER.indexOf(t.task_day) !== -1 ? t.task_day : '';
			buckets[key].push(t);
		});
		DAY_ORDER.concat(['']).forEach(function(key){
			var list = buckets[key];
			if(!list.length) return;
			var heading = document.createElement('div');
			heading.className = 'et-tasks-day-heading';
			heading.textContent = key ? list[0].day_label : 'مهام أخرى';
			container.appendChild(heading);
			var ul = document.createElement('ul');
			ul.className = 'et-tasks-list';
			list.forEach(function(t){ ul.appendChild(buildTaskItem(t)); });
			container.appendChild(ul);
		});
	}

	function loadWeeklyTasks(){
		tasksLoading.hidden = false;
		tasksEmpty.hidden = true;
		tasksWrap.hidden = true;
		apiFetch('/weekly-tasks?token=' + encodeURIComponent(state.token)).then(function(data){
			tasksLoaded = true;
			tasksLoading.hidden = true;
			var hasAny = (data.current && data.current.length) || (data.previous_pending && data.previous_pending.length);
			myTasksCache = [].concat(data.previous_pending || [], data.current || []);
			if(!hasAny){
				tasksEmpty.hidden = false;
				tasksCountBadge.hidden = true;
				myBoard.innerHTML = '';
				return;
			}
			tasksWrap.hidden = myViewMode !== 'list';
			myBoard.hidden = myViewMode !== 'board';
			tasksPrevList.innerHTML = '';
			tasksCurrentList.innerHTML = '';
			if(data.previous_pending && data.previous_pending.length){
				tasksPrevSection.hidden = false;
				data.previous_pending.forEach(function(t){ tasksPrevList.appendChild(buildTaskItem(t)); });
			} else {
				tasksPrevSection.hidden = true;
			}
			if(data.current && data.current.length){
				tasksCurrentSection.hidden = false;
				renderGroupedTasks(tasksCurrentList, data.current);
			} else {
				tasksCurrentSection.hidden = true;
			}
			if(myViewMode === 'board'){ renderMyBoard(myTasksCache); }
			updateTasksBadge();
		}).catch(function(){
			tasksLoading.hidden = true;
			tasksEmpty.hidden = false;
			tasksEmpty.textContent = 'تعذر تحميل المهام، حاول مجددًا.';
		});
	}

	function setMyViewMode(mode){
		myViewMode = mode;
		myViewToggleList.className = 'et-view-toggle-btn' + (mode === 'list' ? ' et-view-toggle-btn--active' : '');
		myViewToggleBoard.className = 'et-view-toggle-btn' + (mode === 'board' ? ' et-view-toggle-btn--active' : '');
		tasksWrap.hidden = mode !== 'list';
		myBoard.hidden = mode !== 'board';
		if(mode === 'board'){ renderMyBoard(myTasksCache); }
	}
	myViewToggleList.addEventListener('click', function(){ setMyViewMode('list'); });
	myViewToggleBoard.addEventListener('click', function(){ setMyViewMode('board'); });

	/** لوحة "مهامي" المبسّطة للموظف — بدون سحب وإفلات (غير مدعوم باللمس على الجوال)، بل أزرار نقل مباشرة:
	 *  🆕 جديدة (اضغط "ابدأ التنفيذ" لتنتقل لـ⚙️) ← ⚙️ قيد التنفيذ (اضغط "تم التنفيذ" لترسل للمراجعة) ← 🏷 مراجعة ← ✅ مكتملة.
	 *  نفس فكرة Kanban الإدارة تمامًا، لكن بتفاعل مبني للمس بدل السحب. */
	// عمد "مكتملة" غير موجود هنا: المهام المعتمدة نهائيًا (status = 'done') لا تصل أصلاً لهذه
	// اللوحة — تُستبعد من السيرفر بمجرد اعتماد الإدارة لها (راجع ET_WeeklyTasks::get_for_employee)
	// حتى تختفي فورًا من صفحة الموظف بدل أن تبقى عالقة بعمود لن يفرغ منه شيء أبدًا.
	var MY_BOARD_COLUMNS = [
		{ key: 'not_started', label: '🆕 جديدة' },
		{ key: 'in_progress', label: '⚙️ قيد التنفيذ' },
		{ key: 'stuck', label: '⚠️ متوقفة' },
		{ key: 'review', label: '🏷 بانتظار الاعتماد' }
	];

	/** ينفّذ فعليًا نقل مهمة الموظف من عمود لعمود — نفس الإجراءين المستخدمين بالأزرار، لكن يُستدعى إما من
	 *  زر أو من إفلات سحب حقيقي، حتى لا يتكرر المنطق. الانتقالات المسموحة فقط: جديدة←قيد التنفيذ، قيد التنفيذ/متوقفة←مراجعة. */
	function startMyTask(t, onDone){
		return apiFetch('/weekly-tasks/update', {
			method: 'POST', headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ token: state.token, task_id: t.id, progress_percent: 10, work_status: 'started', achievements: '', current_work: 'بدأ العمل على المهمة' })
		}).then(function(){ loadWeeklyTasks(); if(onDone) onDone(); }).catch(function(){ if(onDone) onDone(); });
	}
	function finishMyTask(t, onDone){
		return apiFetch('/weekly-tasks/update', {
			method: 'POST', headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ token: state.token, task_id: t.id, progress_percent: 100, work_status: 'done_part', achievements: 'تم إنجاز المهمة', current_work: '' })
		}).then(function(){ loadWeeklyTasks(); toast('أُرسلت المهمة لاعتماد الإدارة.'); if(onDone) onDone(); }).catch(function(){ if(onDone) onDone(); });
	}
	function handleMyBoardMove(t, fromKey, toKey){
		if(fromKey === toKey) return;
		if(fromKey === 'not_started' && toKey === 'in_progress'){ startMyTask(t); return; }
		if((fromKey === 'in_progress' || fromKey === 'stuck') && toKey === 'review'){ finishMyTask(t); return; }
		toast('لا يمكن نقل المهمة مباشرة لهذه الحالة — انتقل خطوة بخطوة (جديدة ← قيد التنفيذ ← مراجعة).');
	}

	/** سحب حقيقي بالفأرة أو باللمس معًا عبر Pointer Events (بعكس HTML5 Drag & Drop الأصلي غير المدعوم باللمس) —
	 *  هذا ما يجعل السحب يشتغل على جوال الموظف فعليًا، وليس بالماوس فقط كما في لوحة الإدارة. */
	function makeMyCardDraggable(card, t){
		card.style.touchAction = 'none';
		card.addEventListener('pointerdown', function(e){
			if(e.target.closest('button')) return; // لا نبدأ سحبًا لو الضغطة على أحد الأزرار
			var startX = e.clientX, startY = e.clientY, dragging = false;
			var fromKey = kanbanColumnFor(t);
			var hoveredSection = null;
			try { card.setPointerCapture(e.pointerId); } catch(err){}

			function onMove(ev){
				var dx = ev.clientX - startX, dy = ev.clientY - startY;
				if(!dragging && (Math.abs(dx) > 8 || Math.abs(dy) > 8)){
					dragging = true;
					card.classList.add('et-my-board-card--dragging');
				}
				if(dragging){
					card.style.transform = 'translate(' + dx + 'px,' + dy + 'px)';
					card.style.visibility = 'hidden';
					var elUnder = document.elementFromPoint(ev.clientX, ev.clientY);
					card.style.visibility = '';
					var section = elUnder ? elUnder.closest('.et-my-board-section') : null;
					if(section !== hoveredSection){
						if(hoveredSection) hoveredSection.classList.remove('et-my-board-section--dragover');
						hoveredSection = section;
						if(hoveredSection) hoveredSection.classList.add('et-my-board-section--dragover');
					}
				}
			}
			function onUp(ev){
				card.removeEventListener('pointermove', onMove);
				card.removeEventListener('pointerup', onUp);
				card.removeEventListener('pointercancel', onUp);
				try { card.releasePointerCapture(e.pointerId); } catch(err){}
				if(hoveredSection) hoveredSection.classList.remove('et-my-board-section--dragover');
				if(dragging){
					card.classList.remove('et-my-board-card--dragging');
					card.style.transform = '';
					if(hoveredSection){ handleMyBoardMove(t, fromKey, hoveredSection.dataset.colKey); }
				}
			}
			card.addEventListener('pointermove', onMove);
			card.addEventListener('pointerup', onUp);
			card.addEventListener('pointercancel', onUp);
		});
	}

	function renderMyBoard(tasks){
		myBoard.innerHTML = '';
		var grouped = {};
		MY_BOARD_COLUMNS.forEach(function(c){ grouped[c.key] = []; });
		// حماية إضافية: لو وصلت مهمة "مكتملة" فعلاً بالخطأ (بيانات مخزّنة قديمة بالمتصفح مثلاً)، لا يوجد
		// عمود 'done' هنا أصلاً بعد الآن — نتجاهلها بدل أن يتعطل السكربت بمحاولة الدفع لمصفوفة غير موجودة.
		tasks.forEach(function(t){ var colKey = kanbanColumnFor(t); if(grouped[colKey]) grouped[colKey].push(t); });

		MY_BOARD_COLUMNS.forEach(function(col){
			var section = document.createElement('div');
			section.className = 'et-my-board-section';
			section.dataset.colKey = col.key;
			var head = document.createElement('div');
			head.className = 'et-my-board-head';
			head.innerHTML = '<span>' + escHtml(col.label) + '</span><span class="et-kanban-col-count">' + escHtml(grouped[col.key].length) + '</span>';
			section.appendChild(head);

			if(!grouped[col.key].length){
				var hint = document.createElement('div');
				hint.className = 'et-kanban-empty-hint';
				hint.textContent = 'اسحب مهمة هنا';
				section.appendChild(hint);
			}

			grouped[col.key].forEach(function(t){
				var card = document.createElement('div');
				card.className = 'et-my-board-card';
				var titleEl = document.createElement('div');
				titleEl.className = 'et-kanban-card-title';
				titleEl.textContent = t.title;
				card.appendChild(titleEl);
				var metaEl = document.createElement('div');
				metaEl.className = 'et-kanban-card-meta';
				metaEl.innerHTML = '<span>' + escHtml(t.day_label) + '</span><span>' + escHtml(t.progress_percent) + '٪</span>';
				card.appendChild(metaEl);
				if(t.urgency){
					var urg = document.createElement('div');
					urg.className = 'et-urgency-badge et-urgency-badge--' + t.urgency.level;
					urg.style.marginTop = '5px';
					urg.textContent = '⏰ ' + t.urgency.label;
					card.appendChild(urg);
				}

				if(col.key === 'not_started'){
					var startBtn = document.createElement('button');
					startBtn.type = 'button';
					startBtn.className = 'et-my-board-action';
					startBtn.textContent = '▶️ خذ المهمة وابدأ التنفيذ';
					startBtn.addEventListener('click', function(){ startBtn.disabled = true; startMyTask(t); });
					card.appendChild(startBtn);
				} else if(col.key === 'in_progress' || col.key === 'stuck'){
					var finishBtn = document.createElement('button');
					finishBtn.type = 'button';
					finishBtn.className = 'et-my-board-action';
					finishBtn.textContent = '✅ تم التنفيذ';
					finishBtn.addEventListener('click', function(){ finishBtn.disabled = true; finishMyTask(t); });
					card.appendChild(finishBtn);
					var detailBtn = document.createElement('button');
					detailBtn.type = 'button';
					detailBtn.className = 'et-my-board-action et-my-board-action--ghost';
					detailBtn.textContent = '📝 تحديث تفصيلي';
					detailBtn.addEventListener('click', function(){ setMyViewMode('list'); });
					card.appendChild(detailBtn);
				} else if(col.key === 'review'){
					var reviewNote = document.createElement('div');
					reviewNote.className = 'et-review-badge';
					reviewNote.style.marginTop = '6px';
					reviewNote.textContent = '🏷 بانتظار اعتماد الإدارة النهائي';
					card.appendChild(reviewNote);
				}

				makeMyCardDraggable(card, t);
				section.appendChild(card);
			});
			myBoard.appendChild(section);
		});
	}

	// تهريب أي نص قبل إدراجه داخل HTML (innerHTML) — يمنع XSS حتى لو وصل نص غير منظّف من السيرفر مستقبلاً
	function escHtml(value){
		return String(value === null || value === undefined ? '' : value)
			.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
	}

	// ينقل التوكن من الرابط (?token=...) إلى هيدر X-ET-Token حتى لا يظهر بسجلات السيرفر/البروكسي
	// أو سجل المتصفح. (بقية الكود يبني الروابط كما هو؛ النقل يتم هنا في نقطة واحدة.)
	function moveTokenToHeader(path, headers){
		var qIndex = path.indexOf('?');
		if (qIndex === -1) return path;
		var base = path.slice(0, qIndex);
		var kept = [];
		path.slice(qIndex + 1).split('&').forEach(function(pair){
			if (pair.indexOf('token=') === 0) {
				var val = decodeURIComponent(pair.slice(6));
				if (val && val !== 'null' && val !== 'undefined') headers['X-ET-Token'] = val;
			} else if (pair !== '') {
				kept.push(pair);
			}
		});
		return kept.length ? base + '?' + kept.join('&') : base;
	}

	function apiFetch(path, options){
		options = options || {};
		options.headers = options.headers || {};
		options.headers['X-CSRF-TOKEN'] = NONCE;
		options.headers['Accept'] = 'application/json';
		path = moveTokenToHeader(path, options.headers);
		return fetch(REST + path, options).then(function(res){
			return res.json().then(function(json){
				if(!res.ok){
					// جلسة إدارة منتهية/ملغاة (مثلًا بعد تغيير الرمز): نعيد عرض شاشة إدخال الرمز بدل أخطاء متتالية
					if (res.status === 401 && json && (json.code === 'et_invalid_admin_token' || json.code === 'et_admin_token_expired')) {
						try { sessionStorage.removeItem('et_admin_token'); } catch(e){}
						if (typeof adminState !== 'undefined' && adminState.token) {
							adminState.token = null;
							if (typeof adminLoginBox !== 'undefined' && adminLoginBox) adminLoginBox.hidden = false;
							if (typeof adminPanel !== 'undefined' && adminPanel) adminPanel.hidden = true;
						}
					}
					throw (json && json.message) ? json.message : 'حدث خطأ غير متوقع';
				}
				return json;
			});
		});
	}

	/** قائمة الموظفين (لقوائم الزملاء والإدارة) — لا تُحمَّل إلا بعد تسجيل الدخول */
	function loadEmployees(token){
		apiFetch('/employees?token=' + encodeURIComponent(token)).then(function(data){
			state.employees = data.employees || [];
		}).catch(function(){});
	}

	/* ---------- إظهار/إخفاء كلمة المرور ---------- */
	document.querySelectorAll('.et-pass-toggle').forEach(function(btn){
		btn.addEventListener('click', function(){
			var input = document.getElementById(btn.getAttribute('data-target'));
			var show = input.type === 'password';
			input.type = show ? 'text' : 'password';
			btn.textContent = show ? 'إخفاء' : 'إظهار';
			btn.setAttribute('aria-pressed', show ? 'true' : 'false');
			btn.setAttribute('aria-label', show ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور');
			input.focus();
		});
	});

	function showLoginError(el, text){
		el.hidden = false;
		el.textContent = text;
	}

	/* ---------- دخول الموظف: البريد الإلكتروني + كلمة المرور ---------- */
	loginForm.addEventListener('submit', function(ev){
		ev.preventDefault();
		var email = loginEmail.value.trim();
		var password = loginPassword.value;
		if(!email || !password){
			showLoginError(pickerMsg, 'أدخل البريد الإلكتروني وكلمة المرور.');
			(email ? loginPassword : loginEmail).focus();
			return;
		}
		pickerMsg.hidden = true;
		loginSubmit.disabled = true;
		loginSubmit.textContent = 'جارِ الدخول…';
		apiFetch('/login', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ email: email, password: password })
		}).then(function(data){
			state.token = data.token;
			state.employeeId = data.employee.id;
			state.employeeName = data.employee.name;
			loginPassword.value = '';
			document.getElementById('et-password-username').value = email;
			document.getElementById('et-greeting').textContent = '👋 مرحبًا ' + data.employee.name;
			loadEmployees(state.token);
			refreshNotifBadge();

			if(data.gamification && data.gamification.streak > 0){
				document.getElementById('et-streak-badge').hidden = false;
				document.getElementById('et-streak-count').textContent = data.gamification.streak;
			}

			if(data.already_submitted && !data.can_edit){
				alreadyMsg.hidden = false;
				alreadyMsg.textContent = 'لقد أرسلت تحديثك اليوم بالفعل ✅ يمكنك التواصل مع إدارتك المباشرة لأي تعديل إضافي.';
				form.hidden = true;
			} else {
				alreadyMsg.hidden = true;
				form.hidden = false;
				if(data.already_submitted && data.can_edit){
					alreadyMsg.hidden = false;
					alreadyMsg.textContent = 'لديك تحديث مُرسل اليوم، ولا يزال بإمكانك تعديله خلال المهلة المتاحة.';
				}
			}
			showView('form');
			showTab('update');
		}).catch(function(err){
			showLoginError(pickerMsg, typeof err === 'string' ? err : 'تعذر تسجيل الدخول، حاول مجددًا.');
			loginPassword.select();
		}).then(function(){
			loginSubmit.disabled = false;
			loginSubmit.textContent = 'دخول';
		});
	});

	/* ---------- الموظف يغيّر كلمة مروره ---------- */
	var passwordForm = document.getElementById('et-password-form');
	var passwordMsg = document.getElementById('et-password-msg');
	var passwordSubmit = document.getElementById('et-password-submit');
	passwordForm.addEventListener('submit', function(ev){
		ev.preventDefault();
		var current = document.getElementById('et-password-current').value;
		var next = document.getElementById('et-password-new').value;
		var confirmVal = document.getElementById('et-password-confirm').value;
		passwordMsg.className = 'et-inline-msg et-inline-msg--error';
		if(!current || !next){ showLoginError(passwordMsg, 'أدخل كلمة المرور الحالية والجديدة.'); return; }
		if(next.length < 8){ showLoginError(passwordMsg, 'كلمة المرور الجديدة قصيرة — 8 أحرف على الأقل.'); return; }
		if(next !== confirmVal){ showLoginError(passwordMsg, 'تأكيد كلمة المرور غير مطابق.'); return; }
		passwordMsg.hidden = true;
		passwordSubmit.disabled = true;
		apiFetch('/me/password', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'X-ET-Token': state.token },
			body: JSON.stringify({ current_password: current, new_password: next })
		}).then(function(data){
			if(data.token){ state.token = data.token; }
			passwordForm.reset();
			passwordMsg.className = 'et-inline-msg et-inline-msg--success';
			showLoginError(passwordMsg, 'تم حفظ كلمة المرور الجديدة. استخدمها في الدخول القادم.');
		}).catch(function(err){
			showLoginError(passwordMsg, typeof err === 'string' ? err : 'تعذر حفظ كلمة المرور، حاول مجددًا.');
		}).then(function(){ passwordSubmit.disabled = false; });
	});

	// يمسح كوكي الجلسة بالسيرفر (المستخدم لفتح المرفقات المحمية) عند الخروج
	function serverLogout(isAdmin){
		apiFetch('/logout', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ admin: isAdmin ? 1 : 0 })
		}).catch(function(){});
	}

	document.getElementById('et-change-name').addEventListener('click', function(){
		serverLogout(false);
		state.token = null;
		tasksLoaded = false;
		form.reset();
		state.selectedFiles = [];
		renderFileList();
		document.getElementById('et-problem-fields').hidden = true;
		document.getElementById('et-assistance-field').hidden = true;
		document.getElementById('et-streak-badge').hidden = true;
		resetToLogin();
	});
	document.getElementById('et-success-back').addEventListener('click', function(){
		serverLogout(false);
		state.token = null;
		tasksLoaded = false;
		form.reset();
		state.selectedFiles = [];
		renderFileList();
		resetToLogin();
	});

	function resetToLogin(){
		state.employees = [];
		passwordForm.reset();
		passwordMsg.hidden = true;
		pickerMsg.hidden = true;
		loginPassword.value = '';
		showView('picker');
		setTimeout(function(){ (loginEmail.value ? loginPassword : loginEmail).focus(); }, 50);
	}

	// ================= قسم "الإدارة" — دخول برمز سري مشترك تحدده الإدارة من الإعدادات =================
	var adminState = { token: null };

	var adminLoginBox = document.getElementById('et-admin-login-box');
	var adminPanel = document.getElementById('et-admin-panel');
	var adminCodeInput = document.getElementById('et-admin-code-input');
	var adminEmailInput = document.getElementById('et-admin-email-input');
	var adminLoginForm = document.getElementById('et-admin-login-form');
	var adminLoginMsg = document.getElementById('et-admin-login-msg');
	var adminLoginBtn = document.getElementById('et-admin-login-btn');
	var adminLogoutBtn = document.getElementById('et-admin-logout');

	var adminTabAttendance = document.getElementById('et-admin-tab-attendance');
	var adminPanelAttendance = document.getElementById('et-admin-panel-attendance');
	var adminTabDashboard = document.getElementById('et-admin-tab-dashboard');
	var adminTabView = document.getElementById('et-admin-tab-view');
	var adminTabAdd = document.getElementById('et-admin-tab-add');
	var adminTabSent = document.getElementById('et-admin-tab-sent');
	var adminTabPerformance = document.getElementById('et-admin-tab-performance');
	var adminTabProjects = document.getElementById('et-admin-tab-projects');
	var adminTabTeam = document.getElementById('et-admin-tab-team');
	var adminTabReport = document.getElementById('et-admin-tab-report');
	var adminTabAudit = document.getElementById('et-admin-tab-audit');
	var adminTabLeave = document.getElementById('et-admin-tab-leave');
	var adminTabSick = document.getElementById('et-admin-tab-sick');
	var adminTabPermission = document.getElementById('et-admin-tab-permission');
	var adminPanelLeave = document.getElementById('et-admin-panel-leave');
	var adminPanelSick = document.getElementById('et-admin-panel-sick');
	var adminPanelPermission = document.getElementById('et-admin-panel-permission');
	var adminPanelDashboard = document.getElementById('et-admin-panel-dashboard');
	var adminPanelView = document.getElementById('et-admin-panel-view');
	var adminPanelAdd = document.getElementById('et-admin-panel-add');
	var adminPanelSent = document.getElementById('et-admin-panel-sent');
	var adminPanelPerformance = document.getElementById('et-admin-panel-performance');
	var adminPanelProjects = document.getElementById('et-admin-panel-projects');
	var adminPanelTeam = document.getElementById('et-admin-panel-team');
	var adminPanelReport = document.getElementById('et-admin-panel-report');
	var adminPanelAudit = document.getElementById('et-admin-panel-audit');
	var teamLoading = document.getElementById('et-team-loading');
	var teamList = document.getElementById('et-team-list');
	var adminReportFilterEmployeeInput = document.getElementById('et-admin-report-filter-employee-input');
	var adminReportFilterEmployee = document.getElementById('et-admin-report-filter-employee');
	var adminReportFilterEmployeeResults = document.getElementById('et-admin-report-filter-employee-results');
	var adminReportDateFrom = document.getElementById('et-admin-report-date-from');
	var adminReportDateTo = document.getElementById('et-admin-report-date-to');
	var adminReportLoading = document.getElementById('et-admin-report-loading');
	var adminReportEmpty = document.getElementById('et-admin-report-empty');
	var adminReportList = document.getElementById('et-admin-report-list');
	var auditLoading = document.getElementById('et-audit-loading');
	var auditEmpty = document.getElementById('et-audit-empty');
	var auditList = document.getElementById('et-audit-list');

	var dashCounts = document.getElementById('et-dash-counts');
	var dashNowDoing = document.getElementById('et-dash-now-doing');
	var dashNowDoingEmpty = document.getElementById('et-dash-now-doing-empty');
	var dashProblems = document.getElementById('et-dash-problems');
	var dashProblemsEmpty = document.getElementById('et-dash-problems-empty');
	var dashProjects = document.getElementById('et-dash-projects');
	var dashProjectsEmpty = document.getElementById('et-dash-projects-empty');
	var dashTimeline = document.getElementById('et-dash-timeline');
	var dashTimelineEmpty = document.getElementById('et-dash-timeline-empty');
	var dashLoading = document.getElementById('et-dash-loading');

	var adminProjectsListView = document.getElementById('et-admin-projects-list-view');
	var adminProjectDetailView = document.getElementById('et-admin-project-detail-view');
	var adminProjectNewTitle = document.getElementById('et-project-new-title');
	var adminProjectNewSubmit = document.getElementById('et-project-new-submit');
	var adminProjectsLoading = document.getElementById('et-projects-loading');
	var adminProjectsEmpty = document.getElementById('et-projects-empty');
	var adminProjectsList = document.getElementById('et-projects-list');
	var adminProjectBackBtn = document.getElementById('et-project-back-btn');
	var adminProjectDetailTitle = document.getElementById('et-project-detail-title');
	var adminProjectDetailDesc = document.getElementById('et-project-detail-desc');
	var adminProjectChain = document.getElementById('et-project-chain');
	var adminProjectLinkTaskInput = document.getElementById('et-project-link-task-input');
	var adminProjectLinkTaskId = document.getElementById('et-project-link-task-id');
	var adminProjectLinkTaskResults = document.getElementById('et-project-link-task-results');
	var adminProjectLinkOrder = document.getElementById('et-project-link-order');
	var adminProjectLinkSubmit = document.getElementById('et-project-link-submit');
	var currentProjectId = null;
	var adminAllTasksCache = []; // كل مهام الأسبوع الحالي، تُستخدم للبحث عند ربط مهمة بمشروع

	var adminProjectTaskTitle = document.getElementById('et-project-task-title');
	var adminProjectTaskDay = document.getElementById('et-project-task-day');
	var adminProjectTaskPriority = document.getElementById('et-project-task-priority');
	var adminProjectTaskDue = document.getElementById('et-project-task-due');
	var adminProjectTaskMembers = document.getElementById('et-project-task-members');
	var adminProjectTaskAddMember = document.getElementById('et-project-task-add-member');
	var adminProjectTaskMsg = document.getElementById('et-project-task-msg');
	var adminProjectTaskSubmit = document.getElementById('et-project-task-submit');

	var adminPerfMonthInput = document.getElementById('et-admin-perf-month');
	var adminPerfLoading = document.getElementById('et-admin-perf-loading');
	var adminPerfEmpty = document.getElementById('et-admin-perf-empty');
	var adminPerfList = document.getElementById('et-admin-perf-list');

	var adminSentFilterEmployee = document.getElementById('et-admin-sent-filter-employee');
	var adminSentFilterEmployeeInput = document.getElementById('et-admin-sent-filter-employee-input');
	var adminSentFilterEmployeeResults = document.getElementById('et-admin-sent-filter-employee-results');
	var adminSentLoading = document.getElementById('et-admin-sent-loading');
	var adminSentEmpty = document.getElementById('et-admin-sent-empty');
	var adminSentList = document.getElementById('et-admin-sent-list');
	var adminSentLoaded = false;

	var adminFilterEmployee = document.getElementById('et-admin-filter-employee');
	var adminFilterEmployeeInput = document.getElementById('et-admin-filter-employee-input');
	var adminFilterEmployeeResults = document.getElementById('et-admin-filter-employee-results');
	var adminFilterWeek = document.getElementById('et-admin-filter-week');
	var adminTasksLoading = document.getElementById('et-admin-tasks-loading');
	var adminTasksEmpty = document.getElementById('et-admin-tasks-empty');
	var adminTasksList = document.getElementById('et-admin-tasks-list');
	var adminKanbanBoard = document.getElementById('et-admin-kanban-board');
	var viewToggleList = document.getElementById('et-view-toggle-list');
	var viewToggleKanban = document.getElementById('et-view-toggle-kanban');
	var adminViewMode = 'list';

	var adminAddEmployee = document.getElementById('et-admin-add-employee');
	var adminAddEmployeeInput = document.getElementById('et-admin-add-employee-input');
	var adminAddEmployeeResults = document.getElementById('et-admin-add-employee-results');
	var adminAddWeek = document.getElementById('et-admin-add-week');
	var adminAddRows = document.getElementById('et-admin-add-rows');
	var adminAddRowBtn = document.getElementById('et-admin-add-row-btn');
	var adminAddNotify = document.getElementById('et-admin-add-notify');
	var adminAddMsg = document.getElementById('et-admin-add-msg');
	var adminAddSubmit = document.getElementById('et-admin-add-submit');

	var ADMIN_DAY_OPTS = [['', 'غير محدد'], ['sunday', 'الأحد'], ['monday', 'الاثنين'], ['tuesday', 'الثلاثاء'], ['wednesday', 'الأربعاء'], ['thursday', 'الخميس']];
	var ADMIN_PRIORITY_OPTS = [['low', 'عادية'], ['medium', 'متوسطة'], ['high', 'عالية'], ['critical', 'عاجلة']];

	function todayISO(){
		var d = new Date();
		var m = ('0' + (d.getMonth() + 1)).slice(-2);
		var day = ('0' + d.getDate()).slice(-2);
		return d.getFullYear() + '-' + m + '-' + day;
	}

	/** بداية أسبوع العمل الحالي (الأحد) — لازم تطابق ET_WeeklyTasks::current_week_start() بالسيرفر
	 *  حتى تظهر المهام المضافة عند الموظف مباشرة (نفس منطق: طرح رقم يوم الأسبوع من تاريخ اليوم) */
	function currentWeekStartISO(){
		var d = new Date();
		d.setDate(d.getDate() - d.getDay());
		var m = ('0' + (d.getMonth() + 1)).slice(-2);
		var day = ('0' + d.getDate()).slice(-2);
		return d.getFullYear() + '-' + m + '-' + day;
	}

	document.getElementById('et-goto-admin').addEventListener('click', function(){
		showView('admin');
		var cached = sessionStorage.getItem('et_admin_token');
		if(cached){
			adminState.token = cached;
			enterAdminPanel();
		} else {
			adminLoginBox.hidden = false;
			adminPanel.hidden = true;
			document.getElementById('et-admin-back').hidden = true;
			setTimeout(function(){ adminEmailInput.focus(); }, 50);
		}
	});

	document.getElementById('et-admin-back').addEventListener('click', function(){
		showView('picker');
	});
	document.getElementById('et-admin-switch-employee').addEventListener('click', function(){
		adminLoginMsg.hidden = true;
		showView('picker');
		setTimeout(function(){ loginEmail.focus(); }, 50);
	});

	adminLoginForm.addEventListener('submit', function(ev){
		ev.preventDefault();
		var email = adminEmailInput.value.trim();
		var code = adminCodeInput.value;
		if(!email || !code){
			showLoginError(adminLoginMsg, 'أدخل البريد الإلكتروني وكلمة المرور.');
			(email ? adminCodeInput : adminEmailInput).focus();
			return;
		}
		adminLoginMsg.hidden = true;
		adminLoginBtn.disabled = true;
		adminLoginBtn.textContent = 'جارِ الدخول…';
		apiFetch('/admin-login', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ email: email, password: code })
		}).then(function(data){
			adminState.token = data.token;
			sessionStorage.setItem('et_admin_token', data.token);
			adminCodeInput.value = '';
			enterAdminPanel();
		}).catch(function(err){
			adminLoginMsg.hidden = false;
			adminLoginMsg.textContent = typeof err === 'string' ? err : 'تعذر تسجيل الدخول، حاول مجددًا.';
		}).then(function(){
			adminLoginBtn.disabled = false;
			adminLoginBtn.textContent = 'دخول';
		});
	});

	adminLogoutBtn.addEventListener('click', function(){
		serverLogout(true);
		adminState.token = null;
		sessionStorage.removeItem('et_admin_token');
		adminLoginBox.hidden = false;
		adminPanel.hidden = true;
		document.getElementById('et-admin-back').hidden = true;
		state.employees = [];
	});

	function enterAdminPanel(){
		adminLoginMsg.hidden = true;
		adminLoginBox.hidden = true;
		adminPanel.hidden = false;
		document.getElementById('et-admin-back').hidden = false;
		if(!adminAddWeek.value) adminAddWeek.value = currentWeekStartISO();
		if(adminAddRows.children.length === 0){ addAdminTaskRow(); }
		populateAdminEmployeeSelects();
		showAdminTab('dashboard');
		refreshNotifBadge();
		headerSearch.hidden = false;
	}

	function showAdminTab(name){
		adminTabAttendance.className = 'et-tab' + (name === 'attendance' ? ' et-tab--active' : '');
		adminTabDashboard.className = 'et-tab' + (name === 'dashboard' ? ' et-tab--active' : '');
		adminTabView.className = 'et-tab' + (name === 'view' ? ' et-tab--active' : '');
		adminTabAdd.className = 'et-tab' + (name === 'add' ? ' et-tab--active' : '');
		adminTabSent.className = 'et-tab' + (name === 'sent' ? ' et-tab--active' : '');
		adminTabPerformance.className = 'et-tab' + (name === 'performance' ? ' et-tab--active' : '');
		adminTabProjects.className = 'et-tab' + (name === 'projects' ? ' et-tab--active' : '');
		adminTabTeam.className = 'et-tab' + (name === 'team' ? ' et-tab--active' : '');
		adminTabReport.className = 'et-tab' + (name === 'report' ? ' et-tab--active' : '');
		adminTabAudit.className = 'et-tab' + (name === 'audit' ? ' et-tab--active' : '');
		adminTabLeave.className = 'et-tab' + (name === 'leave' ? ' et-tab--active' : '');
		adminTabSick.className = 'et-tab' + (name === 'sick' ? ' et-tab--active' : '');
		adminTabPermission.className = 'et-tab' + (name === 'permission' ? ' et-tab--active' : '');
		adminPanelAttendance.hidden = name !== 'attendance';
		adminPanelDashboard.hidden = name !== 'dashboard';
		adminPanelView.hidden = name !== 'view';
		adminPanelAdd.hidden = name !== 'add';
		adminPanelSent.hidden = name !== 'sent';
		adminPanelPerformance.hidden = name !== 'performance';
		adminPanelProjects.hidden = name !== 'projects';
		adminPanelTeam.hidden = name !== 'team';
		adminPanelReport.hidden = name !== 'report';
		adminPanelAudit.hidden = name !== 'audit';
		adminPanelLeave.hidden = name !== 'leave';
		adminPanelSick.hidden = name !== 'sick';
		adminPanelPermission.hidden = name !== 'permission';
		headerSearch.value = ''; headerSearch.dispatchEvent(new Event('input'));
		if(name === 'dashboard'){ loadAdminDashboard(); }
		if(name === 'view'){ loadAdminTasks(); }
		if(name === 'sent'){ loadAdminSentUpdates(); }
		if(name === 'performance'){ loadAdminPerformance(); }
		if(name === 'projects'){ showProjectsListView(); loadAdminProjects(); }
		if(name === 'team'){ loadAdminTeam(); }
		if(name === 'report'){ loadAdminWeeklyReport(); }
		if(name === 'audit'){ loadAdminAudit(); }
		if(name === 'leave'){ loadAdminLeaveRequests(); loadAdminBalances(); }
		if(name === 'sick'){ loadAdminSickRequests(); }
		if(name === 'permission'){ loadAdminPermissionRequests(); }
		if(name === 'attendance'){ initAdminAttendancePanel(); }
	}
	adminTabAttendance.addEventListener('click', function(){ showAdminTab('attendance'); });
	adminTabDashboard.addEventListener('click', function(){ showAdminTab('dashboard'); });
	adminTabView.addEventListener('click', function(){ showAdminTab('view'); });
	adminTabAdd.addEventListener('click', function(){ showAdminTab('add'); });
	adminTabSent.addEventListener('click', function(){ showAdminTab('sent'); });
	adminTabPerformance.addEventListener('click', function(){ showAdminTab('performance'); });
	adminTabProjects.addEventListener('click', function(){ showAdminTab('projects'); });
	adminTabTeam.addEventListener('click', function(){ showAdminTab('team'); });
	adminTabReport.addEventListener('click', function(){ showAdminTab('report'); });
	adminTabLeave.addEventListener('click', function(){ showAdminTab('leave'); });
	adminTabSick.addEventListener('click', function(){ showAdminTab('sick'); });
	adminTabPermission.addEventListener('click', function(){ showAdminTab('permission'); });
	adminTabAudit.addEventListener('click', function(){ showAdminTab('audit'); });

	/* ================= نظام "الحضور والانصراف" — لوحة الإدارة ================= */

	var ATT_ICONS_ADMIN = { present:'🟢', late:'🟡', absent:'🔴', leave:'🔵', sick:'🟣', permission:'🟠', not_yet:'⚪', off:'⚪' };
	var attSubtabDay = document.getElementById('et-att-subtab-day');
	var attSubtabReports = document.getElementById('et-att-subtab-reports');
	var attSubtabAudit = document.getElementById('et-att-subtab-audit');
	var attSubtabSettings = document.getElementById('et-att-subtab-settings');
	var attDaySection = document.getElementById('et-att-day-section');
	var attReportsSection = document.getElementById('et-att-reports-section');
	var attAuditSection = document.getElementById('et-att-audit-section');
	var attSettingsSection = document.getElementById('et-att-settings-section');

	var attDayDate = document.getElementById('et-att-day-date');
	var attDayStatusFilter = document.getElementById('et-att-day-status-filter');
	var attDaySearch = document.getElementById('et-att-day-search');
	var attDayStats = document.getElementById('et-att-day-stats');
	var attDayLoading = document.getElementById('et-att-day-loading');
	var attDayEmpty = document.getElementById('et-att-day-empty');
	var attDayTableWrap = document.getElementById('et-att-day-table-wrap');
	var attDayTbody = document.getElementById('et-att-day-tbody');
	var attDayCache = [];

	var attReportEmployeeInput = document.getElementById('et-att-report-employee-input');
	var attReportEmployeeId = document.getElementById('et-att-report-employee-id');
	var attReportEmployeeResults = document.getElementById('et-att-report-employee-results');
	var attReportFrom = document.getElementById('et-att-report-from');
	var attReportTo = document.getElementById('et-att-report-to');
	var attReportExportBtn = document.getElementById('et-att-report-export-btn');
	var attReportLoading = document.getElementById('et-att-report-loading');
	var attReportEmpty = document.getElementById('et-att-report-empty');
	var attReportTableWrap = document.getElementById('et-att-report-table-wrap');
	var attReportTbody = document.getElementById('et-att-report-tbody');

	var attAuditEmployeeInput = document.getElementById('et-att-audit-employee-input');
	var attAuditEmployeeId = document.getElementById('et-att-audit-employee-id');
	var attAuditEmployeeResults = document.getElementById('et-att-audit-employee-results');
	var attAuditLoading = document.getElementById('et-att-audit-loading');
	var attAuditEmpty = document.getElementById('et-att-audit-empty');
	var attAuditList = document.getElementById('et-att-audit-list');

	var attSetMorningStart = document.getElementById('et-att-set-morning-start');
	var attSetMorningEnd = document.getElementById('et-att-set-morning-end');
	var attSetEveningStart = document.getElementById('et-att-set-evening-start');
	var attSetEveningEnd = document.getElementById('et-att-set-evening-end');
	var attSetGrace = document.getElementById('et-att-set-grace');
	var attSetHours = document.getElementById('et-att-set-hours');
	var attSetDays = document.getElementById('et-att-set-days');
	var attSetLat = document.getElementById('et-att-set-lat');
	var attSetLng = document.getElementById('et-att-set-lng');
	var attSetRadius = document.getElementById('et-att-set-radius');
	var attSetIps = document.getElementById('et-att-set-ips');
	var attSetMsg = document.getElementById('et-att-set-msg');
	var attSetSave = document.getElementById('et-att-set-save');
	var ATT_DAY_OPTS = [['sunday','الأحد'],['monday','الاثنين'],['tuesday','الثلاثاء'],['wednesday','الأربعاء'],['thursday','الخميس'],['friday','الجمعة'],['saturday','السبت']];

	var attModal = document.getElementById('et-att-edit-modal');
	var attModalClose = document.getElementById('et-att-modal-close');
	var attModalEmployee = document.getElementById('et-att-modal-employee');
	var attModalDate = document.getElementById('et-att-modal-date');
	var attModalCurrent = document.getElementById('et-att-modal-current');
	var attModalStatus = document.getElementById('et-att-modal-status');
	var attModalCheckin = document.getElementById('et-att-modal-checkin');
	var attModalCheckout = document.getElementById('et-att-modal-checkout');
	var attModalReason = document.getElementById('et-att-modal-reason');
	var attModalNotes = document.getElementById('et-att-modal-notes');
	var attModalMsg = document.getElementById('et-att-modal-msg');
	var attModalSave = document.getElementById('et-att-modal-save');
	var attModalCurrentRow = null;

	var attInitDone = false;

	function initAdminAttendancePanel(){
		if(!attDayDate.value) attDayDate.value = todayISO();
		if(!attReportFrom.value){
			var d = new Date(); d.setDate(d.getDate() - 29);
			attReportFrom.value = d.toISOString().slice(0,10);
		}
		if(!attReportTo.value) attReportTo.value = todayISO();
		if(!attInitDone){
			attInitDone = true;
			setupEmployeeAutocomplete(attReportEmployeeInput, attReportEmployeeId, attReportEmployeeResults, loadAdminAttendanceReport);
			setupEmployeeAutocomplete(attAuditEmployeeInput, attAuditEmployeeId, attAuditEmployeeResults, loadAdminAttendanceAudit);
			ATT_DAY_OPTS.forEach(function(opt){
				var label = document.createElement('label');
				label.className = 'et-radio';
				label.innerHTML = '<input type="checkbox" value="' + escHtml(opt[0]) + '"> ' + escHtml(opt[1]);
				attSetDays.appendChild(label);
			});
		}
		showAttSubtab('day');
	}

	function showAttSubtab(name){
		attSubtabDay.className = 'et-view-toggle-btn' + (name === 'day' ? ' et-view-toggle-btn--active' : '');
		attSubtabReports.className = 'et-view-toggle-btn' + (name === 'reports' ? ' et-view-toggle-btn--active' : '');
		attSubtabAudit.className = 'et-view-toggle-btn' + (name === 'audit' ? ' et-view-toggle-btn--active' : '');
		attSubtabSettings.className = 'et-view-toggle-btn' + (name === 'settings' ? ' et-view-toggle-btn--active' : '');
		attDaySection.hidden = name !== 'day';
		attReportsSection.hidden = name !== 'reports';
		attAuditSection.hidden = name !== 'audit';
		attSettingsSection.hidden = name !== 'settings';
		if(name === 'day') loadAdminAttendanceDay();
		if(name === 'reports') loadAdminAttendanceReport();
		if(name === 'audit') loadAdminAttendanceAudit();
		if(name === 'settings') loadAdminAttendanceSettings();
	}
	attSubtabDay.addEventListener('click', function(){ showAttSubtab('day'); });
	attSubtabReports.addEventListener('click', function(){ showAttSubtab('reports'); });
	attSubtabAudit.addEventListener('click', function(){ showAttSubtab('audit'); });
	attSubtabSettings.addEventListener('click', function(){ showAttSubtab('settings'); });

	function attDashCard(num, label, color){
		var card = document.createElement('div');
		card.className = 'et-dash-count-card' + (color ? (' et-dash-count-card--' + color) : '');
		var n = document.createElement('div'); n.className = 'et-dash-count-num'; n.textContent = num;
		var l = document.createElement('div'); l.className = 'et-dash-count-label'; l.textContent = label;
		card.appendChild(n); card.appendChild(l);
		return card;
	}

	function loadAdminAttendanceDay(){
		attDayLoading.hidden = false; attDayEmpty.hidden = true; attDayTableWrap.hidden = true;
		apiFetch('/admin/attendance/day?token=' + encodeURIComponent(adminState.token) + '&date=' + encodeURIComponent(attDayDate.value || todayISO())).then(function(data){
			attDayLoading.hidden = true;
			attDayCache = data.items || [];
			attDayStats.innerHTML = '';
			var st = data.stats || {};
			attDayStats.appendChild(attDashCard(st.present||0, '🟢 حاضر', 'green'));
			attDayStats.appendChild(attDashCard(st.late||0, '🟡 متأخر', 'orange'));
			attDayStats.appendChild(attDashCard(st.absent||0, '🔴 غائب', 'red'));
			attDayStats.appendChild(attDashCard(st.leave||0, '🔵 إجازة', 'blue'));
			attDayStats.appendChild(attDashCard(st.sick||0, '🟣 مرضية', undefined));
			attDayStats.appendChild(attDashCard(st.permission||0, '🟠 استئذان', 'orange'));
			attDayStats.appendChild(attDashCard(st.not_yet||0, '⚪ لم يسجل', undefined));
			renderAdminAttendanceDayTable();
		}).catch(function(){
			attDayLoading.hidden = true; attDayEmpty.hidden = false;
		});
	}

	function renderAdminAttendanceDayTable(){
		var q = attDaySearch.value.trim().toLowerCase();
		var statusFilter = attDayStatusFilter.value;
		var rows = attDayCache.filter(function(r){
			if(statusFilter && r.status !== statusFilter) return false;
			if(q && (r.employee_name||'').toLowerCase().indexOf(q) === -1 && (r.department_name||'').toLowerCase().indexOf(q) === -1) return false;
			return true;
		});
		attDayTbody.innerHTML = '';
		attDayTableWrap.hidden = rows.length === 0;
		attDayEmpty.hidden = rows.length !== 0 || attDayCache.length !== 0;
		rows.forEach(function(r){
			var tr = document.createElement('tr');
			function td(text){ var el = document.createElement('td'); el.textContent = text; tr.appendChild(el); return el; }
			td(r.employee_name);
			td(r.department_name || '—');
			var statusTd = document.createElement('td');
			var badge = document.createElement('span');
			badge.className = 'et-att-badge et-att-badge--' + (r.status_color || 'gray');
			badge.textContent = (ATT_ICONS_ADMIN[r.status] || '⚪') + ' ' + r.status_label;
			statusTd.appendChild(badge);
			if(r.is_manual){ var m = document.createElement('span'); m.className = 'et-sub'; m.style.marginRight = '6px'; m.textContent = '✏️'; m.title = 'مُعدَّل يدويًا'; statusTd.appendChild(m); }
			tr.appendChild(statusTd);
			td(r.check_in ? fmtTime(r.check_in) : '—');
			td(r.check_out ? fmtTime(r.check_out) : '—');
			td(r.late_minutes > 0 ? (r.late_minutes + ' دقيقة') : '—');
			td(r.worked_label || '—');
			var actionTd = document.createElement('td');
			actionTd.style.cssText = 'display:flex;gap:6px;flex-wrap:wrap;';
			var btn = document.createElement('button');
			btn.type = 'button'; btn.className = 'et-att-edit-btn'; btn.textContent = '✏️ تعديل الحضور';
			btn.addEventListener('click', function(){ openAttEditModal(r); });
			var histBtn = document.createElement('button');
			histBtn.type = 'button'; histBtn.className = 'et-att-edit-btn'; histBtn.textContent = '📄 السجل';
			histBtn.addEventListener('click', function(){ openAttHistoryModal(r.employee_id, r.employee_name); });
			actionTd.appendChild(btn);
			actionTd.appendChild(histBtn);
			tr.appendChild(actionTd);
			attDayTbody.appendChild(tr);
		});
	}
	attDayDate.addEventListener('change', loadAdminAttendanceDay);
	attDayStatusFilter.addEventListener('change', renderAdminAttendanceDayTable);
	attDaySearch.addEventListener('input', renderAdminAttendanceDayTable);

	function openAttEditModal(r){
		attModalCurrentRow = r;
		attModalEmployee.textContent = r.employee_name;
		attModalDate.textContent = r.date;
		attModalCurrent.textContent = 'الحالة الحالية: ' + (ATT_ICONS_ADMIN[r.status] || '⚪') + ' ' + r.status_label;
		attModalStatus.value = (r.status === 'present' || r.status === 'late') ? r.status : 'absent';
		attModalCheckin.value = r.check_in ? r.check_in.substr(11,5) : '';
		attModalCheckout.value = r.check_out ? r.check_out.substr(11,5) : '';
		attModalReason.value = '';
		attModalNotes.value = '';
		attModalMsg.hidden = true;
		attModal.hidden = false;
	}
	attModalClose.addEventListener('click', function(){ attModal.hidden = true; });
	attModal.addEventListener('click', function(e){ if(e.target === attModal) attModal.hidden = true; });

	attModalSave.addEventListener('click', function(){
		if(!attModalCurrentRow) return;
		if(!attModalReason.value.trim()){
			attModalMsg.hidden = false; attModalMsg.className = 'et-inline-msg et-inline-msg--error';
			attModalMsg.textContent = 'سبب التعديل إجباري.';
			return;
		}
		attModalSave.disabled = true;
		apiFetch('/admin/attendance/manual-edit', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({
				token: adminState.token,
				employee_id: attModalCurrentRow.employee_id,
				date: attModalCurrentRow.date,
				status: attModalStatus.value,
				check_in: attModalCheckin.value,
				check_out: attModalCheckout.value,
				reason: attModalReason.value,
				notes: attModalNotes.value
			})
		}).then(function(){
			attModal.hidden = true;
			loadAdminAttendanceDay();
		}).catch(function(err){
			attModalMsg.hidden = false; attModalMsg.className = 'et-inline-msg et-inline-msg--error';
			attModalMsg.textContent = typeof err === 'string' ? err : 'تعذّر حفظ التعديل.';
		}).then(function(){ attModalSave.disabled = false; });
	});

	var attHistoryModal = document.getElementById('et-att-history-modal');
	var attHistoryModalClose = document.getElementById('et-att-history-modal-close');
	var attHistoryModalTitle = document.getElementById('et-att-history-modal-title');
	var attHistoryModalLoading = document.getElementById('et-att-history-modal-loading');
	var attHistoryModalEmpty = document.getElementById('et-att-history-modal-empty');
	var attHistoryModalList = document.getElementById('et-att-history-modal-list');
	attHistoryModalClose.addEventListener('click', function(){ attHistoryModal.hidden = true; });
	attHistoryModal.addEventListener('click', function(e){ if(e.target === attHistoryModal) attHistoryModal.hidden = true; });

	function openAttHistoryModal(employeeId, employeeName){
		attHistoryModalTitle.textContent = '📄 سجل حضور: ' + employeeName;
		attHistoryModal.hidden = false;
		attHistoryModalLoading.hidden = false; attHistoryModalEmpty.hidden = true; attHistoryModalList.innerHTML = '';
		apiFetch('/admin/attendance/employee-history?token=' + encodeURIComponent(adminState.token) + '&employee_id=' + encodeURIComponent(employeeId)).then(function(data){
			attHistoryModalLoading.hidden = true;
			var items = data.items || [];
			if(!items.length){ attHistoryModalEmpty.hidden = false; return; }
			items.forEach(function(s){
				var row = document.createElement('div');
				row.className = 'et-task-item';
				row.style.flexDirection = 'column'; row.style.alignItems = 'stretch';
				var top = document.createElement('div');
				top.style.cssText = 'display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:6px;';
				var badge = document.createElement('span');
				badge.className = 'et-att-badge et-att-badge--' + (s.status_color || 'gray');
				badge.textContent = (ATT_ICONS_ADMIN[s.status] || '⚪') + ' ' + s.status_label;
				var dateEl = document.createElement('b'); dateEl.textContent = s.date;
				top.appendChild(dateEl); top.appendChild(badge);
				row.appendChild(top);
				if(s.check_in || s.check_out){
					var times = document.createElement('div');
					times.className = 'et-sub'; times.style.marginTop = '4px';
					times.textContent = 'الحضور: ' + (s.check_in ? fmtTime(s.check_in) : '—') + '   |   الانصراف: ' + (s.check_out ? fmtTime(s.check_out) : '—') + (s.worked_label ? ('   |   ساعات العمل: ' + s.worked_label) : '') + (s.is_manual ? '   |   ✏️ مُعدَّل يدويًا' : '');
					row.appendChild(times);
				}
				attHistoryModalList.appendChild(row);
			});
		}).catch(function(){
			attHistoryModalLoading.hidden = true; attHistoryModalEmpty.hidden = false;
		});
	}

	function loadAdminAttendanceReport(){
		attReportLoading.hidden = false; attReportEmpty.hidden = true; attReportTableWrap.hidden = true;
		var qs = '?token=' + encodeURIComponent(adminState.token) + '&date_from=' + encodeURIComponent(attReportFrom.value) + '&date_to=' + encodeURIComponent(attReportTo.value);
		if(attReportEmployeeId.value) qs += '&employee_id=' + encodeURIComponent(attReportEmployeeId.value);
		apiFetch('/admin/attendance/report' + qs).then(function(data){
			attReportLoading.hidden = true;
			var items = data.items || [];
			attReportEmpty.hidden = items.length > 0;
			attReportTableWrap.hidden = items.length === 0;
			attReportTbody.innerHTML = '';
			items.forEach(function(row){
				var tr = document.createElement('tr');
				function td(text){ var el = document.createElement('td'); el.textContent = text; tr.appendChild(el); }
				td(row.employee_name); td(row.department_name || '—');
				td(row.present); td(row.late); td(row.absent); td(row.leave); td(row.sick); td(row.permission);
				td(row.late_minutes);
				td(row.worked_minutes ? (Math.floor(row.worked_minutes/60) + 'س ' + (row.worked_minutes%60) + 'د') : '—');
				attReportTbody.appendChild(tr);
			});
		}).catch(function(){
			attReportLoading.hidden = true; attReportEmpty.hidden = false;
		});
	}
	attReportFrom.addEventListener('change', loadAdminAttendanceReport);
	attReportTo.addEventListener('change', loadAdminAttendanceReport);
	attReportExportBtn.addEventListener('click', function(){
		// بدون توكن بالرابط: السيرفر يتحقق من كوكي جلسة الإدارة (HttpOnly) الذي ضُبط عند تسجيل الدخول
		var qs = '?date_from=' + encodeURIComponent(attReportFrom.value) + '&date_to=' + encodeURIComponent(attReportTo.value);
		if(attReportEmployeeId.value) qs += '&employee_id=' + encodeURIComponent(attReportEmployeeId.value);
		window.open(REST + '/admin/attendance/report/export' + qs, '_blank');
	});

	function loadAdminAttendanceAudit(){
		attAuditLoading.hidden = false; attAuditEmpty.hidden = true; attAuditList.innerHTML = '';
		var qs = '?token=' + encodeURIComponent(adminState.token);
		if(attAuditEmployeeId.value) qs += '&employee_id=' + encodeURIComponent(attAuditEmployeeId.value);
		apiFetch('/admin/attendance/audit-logs' + qs).then(function(data){
			attAuditLoading.hidden = true;
			var items = data.items || [];
			if(!items.length){ attAuditEmpty.hidden = false; return; }
			items.forEach(function(a){
				var row = document.createElement('div');
				row.className = 'et-att-audit-row';
				row.innerHTML =
					'<div><b>' + escHtml(a.employee_name) + '</b> — ' + escHtml(a.attendance_date) + '</div>' +
					'<div>' + (ATT_ICONS_ADMIN[a.old_status]||'') + ' ' + escHtml(a.old_status||'—') + ' ← ' + (ATT_ICONS_ADMIN[a.new_status]||'') + ' ' + escHtml(a.new_status||'—') + '</div>' +
					'<div>السبب: ' + escHtml(a.reason) + '</div>' +
					(a.notes ? ('<div>ملاحظات: ' + escHtml(a.notes) + '</div>') : '') +
					'<div class="et-sub">بواسطة ' + escHtml(a.admin_label) + ' — ' + escHtml(a.created_at) + '</div>';
				attAuditList.appendChild(row);
			});
		}).catch(function(){
			attAuditLoading.hidden = true; attAuditEmpty.hidden = false;
		});
	}

	function loadAdminAttendanceSettings(){
		apiFetch('/admin/attendance/settings?token=' + encodeURIComponent(adminState.token)).then(function(s){
			attSetMorningStart.value = s.morning_start; attSetMorningEnd.value = s.morning_end;
			attSetEveningStart.value = s.evening_start; attSetEveningEnd.value = s.evening_end;
			attSetGrace.value = s.grace_minutes; attSetHours.value = s.work_hours;
			attSetLat.value = s.location_lat !== null ? s.location_lat : '';
			attSetLng.value = s.location_lng !== null ? s.location_lng : '';
			attSetRadius.value = s.radius_meters;
			if (attSetIps) attSetIps.value = s.allowed_ips || '';
			Array.prototype.forEach.call(attSetDays.querySelectorAll('input[type=checkbox]'), function(cb){
				cb.checked = s.working_days.indexOf(cb.value) !== -1;
			});
		});
	}
	attSetSave.addEventListener('click', function(){
		var days = Array.prototype.filter.call(attSetDays.querySelectorAll('input[type=checkbox]'), function(cb){ return cb.checked; }).map(function(cb){ return cb.value; });
		attSetSave.disabled = true;
		apiFetch('/admin/attendance/settings/update', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({
				token: adminState.token,
				morning_start: attSetMorningStart.value, morning_end: attSetMorningEnd.value,
				evening_start: attSetEveningStart.value, evening_end: attSetEveningEnd.value,
				grace_minutes: attSetGrace.value, work_hours: attSetHours.value,
				location_lat: attSetLat.value, location_lng: attSetLng.value,
				radius_meters: attSetRadius.value, working_days: days,
				allowed_ips: attSetIps ? attSetIps.value : null
			})
		}).then(function(){
			attSetMsg.hidden = false; attSetMsg.className = 'et-inline-msg et-inline-msg--success';
			attSetMsg.textContent = '✅ تم حفظ إعدادات الدوام بنجاح.';
		}).catch(function(err){
			attSetMsg.hidden = false; attSetMsg.className = 'et-inline-msg et-inline-msg--error';
			attSetMsg.textContent = typeof err === 'string' ? err : 'تعذّر حفظ الإعدادات.';
		}).then(function(){ attSetSave.disabled = false; });
	});

	/* ================= نظام "الإجازات والاستئذانات" — لوحة الإدارة ================= */

	function adminLeaveStatusColor(status){
		if(status === 'approved') return 'green';
		if(status === 'rejected') return 'red';
		if(status === 'cancelled') return 'gray';
		return 'orange';
	}

	/** حساب أيام العمل الفعلية على المتصفح فورًا (معاينة فقط) — نفس منطق ET_LeaveCalc::working_days
	 *  بالسيرفر، الذي يبقى المصدر الرسمي والنهائي للحساب عند الإرسال الفعلي. */
	function calcWorkingDaysClient(startStr, endStr){
		if(!startStr || !endStr) return null;
		var start = new Date(startStr + 'T00:00:00');
		var end = new Date(endStr + 'T00:00:00');
		if(isNaN(start.getTime()) || isNaN(end.getTime()) || end < start) return null;
		var count = 0;
		var cur = new Date(start.getTime());
		var guard = 0;
		while(cur <= end && guard < 3660){
			var dow = cur.getDay(); // الأحد=0 ... السبت=6
			if(dow !== 5 && dow !== 6) count++;
			cur.setDate(cur.getDate() + 1);
			guard++;
		}
		return count;
	}

	function buildAdminDecideButtons(onApprove, onReject){
		var wrap = document.createElement('div');
		wrap.style.cssText = 'display:flex;gap:8px;margin-top:10px;flex-wrap:wrap;';
		var approveBtn = document.createElement('button');
		approveBtn.type = 'button'; approveBtn.className = 'et-submit-btn'; approveBtn.style.cssText = 'padding:7px 14px;font-size:12.5px;width:auto;';
		approveBtn.textContent = '✅ موافقة';
		approveBtn.addEventListener('click', function(){
			if(!confirm('تأكيد الموافقة على هذا الطلب؟')) return;
			onApprove();
		});
		var rejectBtn = document.createElement('button');
		rejectBtn.type = 'button'; rejectBtn.className = 'et-task-update-toggle';
		rejectBtn.textContent = '❌ رفض';
		rejectBtn.addEventListener('click', function(){
			var reason = prompt('سبب الرفض (اختياري):', '');
			if(reason === null) return;
			if(!confirm('تأكيد رفض هذا الطلب؟')) return;
			onReject(reason);
		});
		wrap.appendChild(approveBtn); wrap.appendChild(rejectBtn);
		return wrap;
	}

	// ---- تبويب "الإجازات السنوية": طلبات / أرصدة / إضافة سابقة ----
	var adminLeaveSubtabRequests = document.getElementById('et-admin-leave-subtab-requests');
	var adminLeaveSubtabBalances = document.getElementById('et-admin-leave-subtab-balances');
	var adminLeaveSubtabAddpast = document.getElementById('et-admin-leave-subtab-addpast');
	var adminLeaveRequestsSection = document.getElementById('et-admin-leave-requests-section');
	var adminLeaveBalancesSection = document.getElementById('et-admin-leave-balances-section');
	var adminLeaveAddpastSection = document.getElementById('et-admin-leave-addpast-section');

	function showAdminLeaveSubtab(name){
		adminLeaveSubtabRequests.className = 'et-view-toggle-btn' + (name === 'requests' ? ' et-view-toggle-btn--active' : '');
		adminLeaveSubtabBalances.className = 'et-view-toggle-btn' + (name === 'balances' ? ' et-view-toggle-btn--active' : '');
		adminLeaveSubtabAddpast.className = 'et-view-toggle-btn' + (name === 'addpast' ? ' et-view-toggle-btn--active' : '');
		adminLeaveRequestsSection.hidden = name !== 'requests';
		adminLeaveBalancesSection.hidden = name !== 'balances';
		adminLeaveAddpastSection.hidden = name !== 'addpast';
		if(name === 'balances'){ loadAdminBalances(); }
	}
	adminLeaveSubtabRequests.addEventListener('click', function(){ showAdminLeaveSubtab('requests'); });
	adminLeaveSubtabBalances.addEventListener('click', function(){ showAdminLeaveSubtab('balances'); });
	adminLeaveSubtabAddpast.addEventListener('click', function(){ showAdminLeaveSubtab('addpast'); });

	var adminLeaveFilterEmployeeInput = document.getElementById('et-admin-leave-filter-employee-input');
	var adminLeaveFilterEmployee = document.getElementById('et-admin-leave-filter-employee');
	var adminLeaveFilterEmployeeResults = document.getElementById('et-admin-leave-filter-employee-results');
	var adminLeaveFilterStatus = document.getElementById('et-admin-leave-filter-status');
	var adminLeaveLoading = document.getElementById('et-admin-leave-loading');
	var adminLeaveEmpty = document.getElementById('et-admin-leave-empty');
	var adminLeaveListEl = document.getElementById('et-admin-leave-list');

	function loadAdminLeaveRequests(){
		adminLeaveLoading.hidden = false; adminLeaveEmpty.hidden = true; adminLeaveListEl.innerHTML = '';
		var qs = 'token=' + encodeURIComponent(adminState.token);
		if(adminLeaveFilterEmployee.value) qs += '&employee_id=' + encodeURIComponent(adminLeaveFilterEmployee.value);
		if(adminLeaveFilterStatus.value) qs += '&status=' + encodeURIComponent(adminLeaveFilterStatus.value);
		apiFetch('/admin/leave/requests?' + qs).then(function(data){
			adminLeaveLoading.hidden = true;
			var items = data.requests || [];
			if(!items.length){ adminLeaveEmpty.hidden = false; return; }
			items.forEach(function(r){ adminLeaveListEl.appendChild(buildAdminLeaveCard(r)); });
		}).catch(function(){
			adminLeaveLoading.hidden = true; adminLeaveEmpty.hidden = false;
			adminLeaveEmpty.textContent = 'تعذر تحميل الطلبات.';
		});
	}

	function buildAdminLeaveCard(r){
		var box = document.createElement('div');
		box.className = 'et-admin-task';
		var title = document.createElement('div');
		title.className = 'et-admin-task-title';
		title.textContent = r.employee_name + ' — من ' + r.start_date + ' إلى ' + r.end_date + ' (' + r.days_count + ' يوم)';
		box.appendChild(title);
		var meta = document.createElement('div');
		meta.className = 'et-admin-task-meta';
		var badge = document.createElement('span');
		badge.className = perfBadgeClass(adminLeaveStatusColor(r.status));
		badge.textContent = r.status_label;
		meta.appendChild(badge);
		var beforeSpan = document.createElement('span'); beforeSpan.textContent = '🕒 الرصيد قبل الطلب: ' + r.balance_before + ' يوم';
		meta.appendChild(beforeSpan);
		if(r.added_by_admin){ var adm = document.createElement('span'); adm.textContent = '📌 مسجلة من الإدارة'; meta.appendChild(adm); }
		box.appendChild(meta);
		if(r.notes){ var n = document.createElement('div'); n.className = 'et-task-desc'; n.textContent = '📝 ' + r.notes; box.appendChild(n); }
		if(r.status === 'rejected' && r.rejection_reason){ var rr = document.createElement('div'); rr.className = 'et-task-desc'; rr.textContent = '❌ سبب الرفض: ' + r.rejection_reason; box.appendChild(rr); }
		if(r.status === 'pending'){
			box.appendChild(buildAdminDecideButtons(function(){
				apiFetch('/admin/leave/requests/decide', {
					method: 'POST', headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({ token: adminState.token, id: r.id, status: 'approved' })
				}).then(function(){ toast('تمت الموافقة على الطلب.'); loadAdminLeaveRequests(); }).catch(function(err){ alert(typeof err === 'string' ? err : 'تعذر تنفيذ العملية.'); });
			}, function(reason){
				apiFetch('/admin/leave/requests/decide', {
					method: 'POST', headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({ token: adminState.token, id: r.id, status: 'rejected', rejection_reason: reason })
				}).then(function(){ toast('تم رفض الطلب.'); loadAdminLeaveRequests(); }).catch(function(err){ alert(typeof err === 'string' ? err : 'تعذر تنفيذ العملية.'); });
			}));
		}
		return box;
	}

	// ---- أرصدة الموظفين ----
	var adminBalancesLoading = document.getElementById('et-admin-balances-loading');
	var adminBalancesList = document.getElementById('et-admin-balances-list');

	function loadAdminBalances(){
		adminBalancesLoading.hidden = false; adminBalancesList.innerHTML = '';
		apiFetch('/admin/leave/balances?token=' + encodeURIComponent(adminState.token)).then(function(data){
			adminBalancesLoading.hidden = true;
			(data.balances || []).forEach(function(b){ adminBalancesList.appendChild(buildAdminBalanceCard(b)); });
		}).catch(function(){
			adminBalancesLoading.hidden = false;
			adminBalancesLoading.textContent = 'تعذر تحميل الأرصدة.';
		});
	}

	function buildAdminBalanceCard(b){
		var box = document.createElement('div');
		box.className = 'et-admin-task';
		var title = document.createElement('div');
		title.className = 'et-admin-task-title';
		title.textContent = b.employee_name + (b.department_name ? ' — ' + b.department_name : '');
		box.appendChild(title);
		var meta = document.createElement('div');
		meta.className = 'et-admin-task-meta';
		[ 'المتبقي: ' + b.remaining_days, 'المستخدم: ' + b.used_days, 'المستحق: ' + b.total_entitled, 'تاريخ الاستحقاق: ' + b.accrual_start_date ].forEach(function(t){
			var s = document.createElement('span'); s.textContent = t; meta.appendChild(s);
		});
		box.appendChild(meta);
		var editBtn = document.createElement('button');
		editBtn.type = 'button'; editBtn.className = 'et-task-update-toggle';
		editBtn.textContent = '✏️ تعديل تاريخ الاستحقاق / رصيد إضافي';
		editBtn.addEventListener('click', function(){
			var newDate = prompt('تاريخ بداية استحقاق الإجازة (YYYY-MM-DD):', b.accrual_start_date);
			if(newDate === null) return;
			var newAdj = prompt('رصيد يدوي إضافي (أيام، موجب أو سالب):', b.manual_adjustment);
			if(newAdj === null) return;
			apiFetch('/admin/leave/balances/adjust', {
				method: 'POST', headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ token: adminState.token, employee_id: b.employee_id, accrual_start_date: newDate, manual_adjustment_days: newAdj })
			}).then(function(){ toast('تم تحديث رصيد الموظف.'); loadAdminBalances(); }).catch(function(err){ alert(typeof err === 'string' ? err : 'تعذر تنفيذ العملية.'); });
		});
		box.appendChild(editBtn);
		return box;
	}

	// ---- إضافة إجازة سابقة ----
	var adminAddpastEmployeeInput = document.getElementById('et-admin-addpast-employee-input');
	var adminAddpastEmployeeId = document.getElementById('et-admin-addpast-employee-id');
	var adminAddpastEmployeeResults = document.getElementById('et-admin-addpast-employee-results');
	var adminAddpastStart = document.getElementById('et-admin-addpast-start');
	var adminAddpastEnd = document.getElementById('et-admin-addpast-end');
	var adminAddpastDaysPreview = document.getElementById('et-admin-addpast-days-preview');
	var adminAddpastNotes = document.getElementById('et-admin-addpast-notes');
	var adminAddpastMsg = document.getElementById('et-admin-addpast-msg');
	var adminAddpastSubmit = document.getElementById('et-admin-addpast-submit');

	function refreshAddpastPreview(){
		var d = calcWorkingDaysClient(adminAddpastStart.value, adminAddpastEnd.value);
		adminAddpastDaysPreview.textContent = d !== null ? ('📆 عدد الأيام (باستثناء الجمعة والسبت): ' + d + ' يوم') : '';
	}
	adminAddpastStart.addEventListener('change', refreshAddpastPreview);
	adminAddpastEnd.addEventListener('change', refreshAddpastPreview);

	adminAddpastSubmit.addEventListener('click', function(){
		adminAddpastMsg.hidden = true;
		if(!adminAddpastEmployeeId.value || !adminAddpastStart.value || !adminAddpastEnd.value){
			adminAddpastMsg.hidden = false; adminAddpastMsg.className = 'et-inline-msg et-inline-msg--error';
			adminAddpastMsg.textContent = 'الرجاء اختيار الموظف وتحديد التواريخ.';
			return;
		}
		adminAddpastSubmit.disabled = true;
		apiFetch('/admin/leave/requests/add-past', {
			method: 'POST', headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ token: adminState.token, employee_id: adminAddpastEmployeeId.value, start_date: adminAddpastStart.value, end_date: adminAddpastEnd.value, notes: adminAddpastNotes.value })
		}).then(function(){
			toast('تم تسجيل الإجازة السابقة بنجاح.');
			adminAddpastEmployeeInput.value = ''; adminAddpastEmployeeId.value = '';
			adminAddpastStart.value = ''; adminAddpastEnd.value = ''; adminAddpastNotes.value = ''; adminAddpastDaysPreview.textContent = '';
			loadAdminBalances();
			showAdminLeaveSubtab('requests');
			loadAdminLeaveRequests();
		}).catch(function(err){
			adminAddpastMsg.hidden = false; adminAddpastMsg.className = 'et-inline-msg et-inline-msg--error';
			adminAddpastMsg.textContent = typeof err === 'string' ? err : 'تعذر تنفيذ العملية.';
		}).then(function(){ adminAddpastSubmit.disabled = false; });
	});

	// ---- تبويب "الإجازات المرضية" ----
	var adminSickFilterEmployeeInput = document.getElementById('et-admin-sick-filter-employee-input');
	var adminSickFilterEmployee = document.getElementById('et-admin-sick-filter-employee');
	var adminSickFilterEmployeeResults = document.getElementById('et-admin-sick-filter-employee-results');
	var adminSickFilterStatus = document.getElementById('et-admin-sick-filter-status');
	var adminSickLoading = document.getElementById('et-admin-sick-loading');
	var adminSickEmpty = document.getElementById('et-admin-sick-empty');
	var adminSickListEl = document.getElementById('et-admin-sick-list');

	function loadAdminSickRequests(){
		adminSickLoading.hidden = false; adminSickEmpty.hidden = true; adminSickListEl.innerHTML = '';
		var qs = 'token=' + encodeURIComponent(adminState.token);
		if(adminSickFilterEmployee.value) qs += '&employee_id=' + encodeURIComponent(adminSickFilterEmployee.value);
		if(adminSickFilterStatus.value) qs += '&status=' + encodeURIComponent(adminSickFilterStatus.value);
		apiFetch('/admin/sick-leave/requests?' + qs).then(function(data){
			adminSickLoading.hidden = true;
			var items = data.requests || [];
			if(!items.length){ adminSickEmpty.hidden = false; return; }
			items.forEach(function(r){ adminSickListEl.appendChild(buildAdminSickCard(r)); });
		}).catch(function(){
			adminSickLoading.hidden = true; adminSickEmpty.hidden = false;
			adminSickEmpty.textContent = 'تعذر تحميل الطلبات.';
		});
	}

	function buildAdminSickCard(r){
		var box = document.createElement('div');
		box.className = 'et-admin-task';
		var title = document.createElement('div');
		title.className = 'et-admin-task-title';
		title.textContent = r.employee_name + ' — من ' + r.start_date + ' إلى ' + r.end_date + ' (' + r.days_count + ' يوم)';
		box.appendChild(title);
		var meta = document.createElement('div');
		meta.className = 'et-admin-task-meta';
		var badge = document.createElement('span');
		badge.className = perfBadgeClass(adminLeaveStatusColor(r.status));
		badge.textContent = r.status_label;
		meta.appendChild(badge);
		box.appendChild(meta);
		if(r.notes){ var n = document.createElement('div'); n.className = 'et-task-desc'; n.textContent = '📝 ' + r.notes; box.appendChild(n); }
		if(r.attachment_url){
			var att = document.createElement('div'); att.className = 'et-task-desc';
			var link = document.createElement('a'); link.href = r.attachment_url; link.target = '_blank'; link.rel = 'noopener';
			link.textContent = '📎 عرض الملف المرفق' + (r.attachment_name ? (': ' + r.attachment_name) : '');
			att.appendChild(link); box.appendChild(att);
		}
		if(r.status === 'rejected' && r.rejection_reason){ var rr = document.createElement('div'); rr.className = 'et-task-desc'; rr.textContent = '❌ سبب الرفض: ' + r.rejection_reason; box.appendChild(rr); }
		if(r.status === 'pending'){
			box.appendChild(buildAdminDecideButtons(function(){
				apiFetch('/admin/sick-leave/requests/decide', {
					method: 'POST', headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({ token: adminState.token, id: r.id, status: 'approved' })
				}).then(function(){ toast('تمت الموافقة على الطلب.'); loadAdminSickRequests(); }).catch(function(err){ alert(typeof err === 'string' ? err : 'تعذر تنفيذ العملية.'); });
			}, function(reason){
				apiFetch('/admin/sick-leave/requests/decide', {
					method: 'POST', headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({ token: adminState.token, id: r.id, status: 'rejected', rejection_reason: reason })
				}).then(function(){ toast('تم رفض الطلب.'); loadAdminSickRequests(); }).catch(function(err){ alert(typeof err === 'string' ? err : 'تعذر تنفيذ العملية.'); });
			}));
		}
		return box;
	}

	// ---- تبويب "الاستئذانات" ----
	var adminPermissionFilterEmployeeInput = document.getElementById('et-admin-permission-filter-employee-input');
	var adminPermissionFilterEmployee = document.getElementById('et-admin-permission-filter-employee');
	var adminPermissionFilterEmployeeResults = document.getElementById('et-admin-permission-filter-employee-results');
	var adminPermissionFilterStatus = document.getElementById('et-admin-permission-filter-status');
	var adminPermissionLoading = document.getElementById('et-admin-permission-loading');
	var adminPermissionEmpty = document.getElementById('et-admin-permission-empty');
	var adminPermissionListEl = document.getElementById('et-admin-permission-list');

	function loadAdminPermissionRequests(){
		adminPermissionLoading.hidden = false; adminPermissionEmpty.hidden = true; adminPermissionListEl.innerHTML = '';
		var qs = 'token=' + encodeURIComponent(adminState.token);
		if(adminPermissionFilterEmployee.value) qs += '&employee_id=' + encodeURIComponent(adminPermissionFilterEmployee.value);
		if(adminPermissionFilterStatus.value) qs += '&status=' + encodeURIComponent(adminPermissionFilterStatus.value);
		apiFetch('/admin/permission/requests?' + qs).then(function(data){
			adminPermissionLoading.hidden = true;
			var items = data.requests || [];
			if(!items.length){ adminPermissionEmpty.hidden = false; return; }
			items.forEach(function(r){ adminPermissionListEl.appendChild(buildAdminPermissionCard(r)); });
		}).catch(function(){
			adminPermissionLoading.hidden = true; adminPermissionEmpty.hidden = false;
			adminPermissionEmpty.textContent = 'تعذر تحميل الطلبات.';
		});
	}

	function buildAdminPermissionCard(r){
		var box = document.createElement('div');
		box.className = 'et-admin-task';
		var title = document.createElement('div');
		title.className = 'et-admin-task-title';
		title.textContent = r.employee_name + ' — ' + r.perm_date + ' من ' + r.time_out + (r.time_return ? (' إلى ' + r.time_return) : ' (وقت العودة غير محدد)');
		box.appendChild(title);
		var meta = document.createElement('div');
		meta.className = 'et-admin-task-meta';
		var badge = document.createElement('span');
		badge.className = perfBadgeClass(adminLeaveStatusColor(r.status));
		badge.textContent = r.status_label;
		meta.appendChild(badge);
		box.appendChild(meta);
		if(r.reason){ var rs = document.createElement('div'); rs.className = 'et-task-desc'; rs.textContent = '🗒️ السبب: ' + r.reason; box.appendChild(rs); }
		if(r.notes){ var n = document.createElement('div'); n.className = 'et-task-desc'; n.textContent = '📝 ' + r.notes; box.appendChild(n); }
		if(r.status === 'rejected' && r.rejection_reason){ var rr = document.createElement('div'); rr.className = 'et-task-desc'; rr.textContent = '❌ سبب الرفض: ' + r.rejection_reason; box.appendChild(rr); }
		if(r.status === 'pending'){
			box.appendChild(buildAdminDecideButtons(function(){
				apiFetch('/admin/permission/requests/decide', {
					method: 'POST', headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({ token: adminState.token, id: r.id, status: 'approved' })
				}).then(function(){ toast('تمت الموافقة على الطلب.'); loadAdminPermissionRequests(); }).catch(function(err){ alert(typeof err === 'string' ? err : 'تعذر تنفيذ العملية.'); });
			}, function(reason){
				apiFetch('/admin/permission/requests/decide', {
					method: 'POST', headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({ token: adminState.token, id: r.id, status: 'rejected', rejection_reason: reason })
				}).then(function(){ toast('تم رفض الطلب.'); loadAdminPermissionRequests(); }).catch(function(err){ alert(typeof err === 'string' ? err : 'تعذر تنفيذ العملية.'); });
			}));
		}
		return box;
	}

	function loadAdminTeam(){
		teamLoading.hidden = false;
		teamList.innerHTML = '';
		apiFetch('/admin/team?token=' + encodeURIComponent(adminState.token)).then(function(data){
			teamLoading.hidden = true;
			(data.team || []).forEach(function(m){
				var card = document.createElement('div');
				card.className = 'et-team-card';
				var head = document.createElement('div');
				head.className = 'et-team-card-head';
				var nameEl = document.createElement('div');
				nameEl.className = 'et-team-card-name';
				nameEl.textContent = m.name;
				head.appendChild(nameEl);
				if(m.performance_score !== null){
					var badge = document.createElement('span');
					badge.className = 'et-perf-badge et-perf-badge--' + m.performance_color;
					badge.textContent = m.performance_score + '٪ ' + m.performance_level;
					head.appendChild(badge);
				}
				card.appendChild(head);
				var stats = document.createElement('div');
				stats.className = 'et-team-card-stats';
				stats.innerHTML = '<span>مهام نشطة: <b>' + escHtml(m.active_tasks) + '</b></span><span>مكتملة هذا الأسبوع: <b>' + escHtml(m.done_this_week) + '</b></span>';
				card.appendChild(stats);
				teamList.appendChild(card);
			});
		}).catch(function(){ teamLoading.hidden = true; });
	}

	/* ---------- تقرير أداء المهام الأسبوعي ("📈 تقرير المهام") ---------- */

	function buildWeeklyReportCard(row){
		var card = document.createElement('div');
		card.className = 'et-perf-card';

		var head = document.createElement('div');
		head.className = 'et-perf-head';
		var nameEl = document.createElement('div');
		nameEl.className = 'et-perf-name';
		nameEl.textContent = row.name;
		head.appendChild(nameEl);
		var scoreEl = document.createElement('div');
		scoreEl.className = 'et-perf-score';
		scoreEl.textContent = row.completion_percent + '٪';
		head.appendChild(scoreEl);
		card.appendChild(head);

		var bar = document.createElement('div');
		bar.className = 'et-perf-bar';
		var fill = document.createElement('div');
		fill.className = 'et-perf-bar-fill';
		fill.style.width = row.completion_percent + '%';
		fill.style.background = row.completion_percent >= 80 ? '#1F9D7C' : (row.completion_percent >= 50 ? '#E2A93B' : '#D6483F');
		bar.appendChild(fill);
		card.appendChild(bar);

		var grid = document.createElement('div');
		grid.className = 'et-perf-breakdown';

		var detailBox = document.createElement('div');
		detailBox.style.display = 'none';
		detailBox.style.marginTop = '10px';
		detailBox.style.background = 'var(--et-surface)';
		detailBox.style.border = '1px solid var(--et-line)';
		detailBox.style.borderRadius = '9px';
		detailBox.style.padding = '10px 12px';
		detailBox.style.fontSize = '12.5px';
		var openCat = null;

		function metric(label, value, tasksKey){
			var m = document.createElement('div'); m.className = 'et-perf-metric';
			m.style.cursor = 'pointer';
			var l = document.createElement('div'); l.className = 'et-perf-metric-label'; l.textContent = label;
			var v = document.createElement('div'); v.className = 'et-perf-metric-value'; v.style.textDecoration = 'underline dotted'; v.textContent = value;
			m.appendChild(l); m.appendChild(v);
			m.addEventListener('click', function(){
				if(openCat === tasksKey){
					detailBox.style.display = 'none';
					openCat = null;
					return;
				}
				var tasks = row[tasksKey] || [];
				detailBox.innerHTML = '';
				var title = document.createElement('div');
				title.style.fontWeight = '700'; title.style.marginBottom = '6px';
				title.textContent = label + ':';
				detailBox.appendChild(title);
				if(!tasks.length){
					var empty = document.createElement('div');
					empty.style.color = 'var(--et-muted)';
					empty.textContent = 'لا توجد مهام بهذا التصنيف.';
					detailBox.appendChild(empty);
				} else {
					var ul = document.createElement('ul');
					ul.style.margin = '0'; ul.style.paddingRight = '16px';
					tasks.forEach(function(t){
						var li = document.createElement('li');
						li.textContent = t.title + ' (' + t.day_label + ' — أسبوع ' + t.week_start_date + ')';
						ul.appendChild(li);
					});
					detailBox.appendChild(ul);
				}
				detailBox.style.display = '';
				openCat = tasksKey;
			});
			return m;
		}
		grid.appendChild(metric('المهام المسندة', row.assigned, 'assigned_tasks'));
		grid.appendChild(metric('المنجزة', row.completed, 'completed_tasks'));
		grid.appendChild(metric('متأخرة (تاريخ الاستحقاق)', row.delayed_due, 'delayed_due_tasks'));
		grid.appendChild(metric('متأخرة (يوم العمل المحدد)', row.delayed_day, 'delayed_day_tasks'));
		card.appendChild(grid);
		card.appendChild(detailBox);

		return card;
	}

	function loadAdminWeeklyReport(){
		if(!adminReportDateFrom.value){ adminReportDateFrom.value = currentWeekStartISO(); }
		if(!adminReportDateTo.value){ adminReportDateTo.value = adminReportDateFrom.value; }
		adminReportLoading.hidden = false;
		adminReportEmpty.hidden = true;
		adminReportList.innerHTML = '';
		var qs = 'token=' + encodeURIComponent(adminState.token)
			+ '&date_from=' + encodeURIComponent(adminReportDateFrom.value)
			+ '&date_to=' + encodeURIComponent(adminReportDateTo.value);
		if(adminReportFilterEmployee.value) qs += '&employee_id=' + encodeURIComponent(adminReportFilterEmployee.value);
		apiFetch('/admin/weekly-task-report?' + qs).then(function(data){
			adminReportLoading.hidden = true;
			var items = data.items || [];
			if(!items.length){ adminReportEmpty.hidden = false; return; }
			items.forEach(function(row){ adminReportList.appendChild(buildWeeklyReportCard(row)); });
		}).catch(function(){
			adminReportLoading.hidden = true;
			adminReportEmpty.hidden = false;
			adminReportEmpty.textContent = 'تعذر تحميل التقرير.';
		});
	}
	setupEmployeeAutocomplete(adminReportFilterEmployeeInput, adminReportFilterEmployee, adminReportFilterEmployeeResults, loadAdminWeeklyReport);
	adminReportDateFrom.addEventListener('change', loadAdminWeeklyReport);
	adminReportDateTo.addEventListener('change', loadAdminWeeklyReport);

	function loadAdminAudit(){
		auditLoading.hidden = false;
		auditEmpty.hidden = true;
		auditList.innerHTML = '';
		apiFetch('/admin/audit-log?token=' + encodeURIComponent(adminState.token)).then(function(data){
			auditLoading.hidden = true;
			var items = data.items || [];
			auditEmpty.hidden = items.length > 0;
			items.forEach(function(l){
				var row = document.createElement('div');
				row.className = 'et-audit-item';
				var timeEl = document.createElement('div');
				timeEl.className = 'et-audit-time'; timeEl.textContent = l.date;
				var textEl = document.createElement('div');
				textEl.textContent = l.message;
				row.appendChild(timeEl); row.appendChild(textEl);
				auditList.appendChild(row);
			});
		}).catch(function(){ auditLoading.hidden = true; });
	}

	/* ---------- لوحة القيادة الموحّدة ("🏠 لوحة القيادة") ---------- */

	var DASH_COUNT_META = [
		['total', 'إجمالي المهام', ''],
		['in_progress', 'قيد التنفيذ', 'blue'],
		['stuck', 'متوقفة', 'red'],
		['waiting_admin', 'بانتظار الإدارة', 'orange'],
		['done', 'مكتملة', 'green'],
		['not_started', 'لم تبدأ', 'gray']
	];

	function loadAdminDashboard(){
		dashLoading.hidden = false;
		apiFetch('/admin/dashboard?token=' + encodeURIComponent(adminState.token)).then(function(data){
			dashLoading.hidden = true;
			renderDashCounts(data.counts || {});
			renderDashNowDoing(data.now_doing || []);
			renderDashProblems(data.problems || []);
			renderDashProjects(data.projects || []);
			renderDashTimeline(data.timeline || []);
		}).catch(function(){
			dashLoading.hidden = true;
			dashLoading.hidden = false;
			dashLoading.textContent = 'تعذر تحميل لوحة القيادة.';
		});
	}

	function renderDashCounts(counts){
		dashCounts.innerHTML = '';
		DASH_COUNT_META.forEach(function(meta){
			var card = document.createElement('div');
			card.className = 'et-dash-count-card' + (meta[2] ? ' et-dash-count-card--' + meta[2] : '');
			var num = document.createElement('div');
			num.className = 'et-dash-count-num';
			num.textContent = counts[meta[0]] || 0;
			var label = document.createElement('div');
			label.className = 'et-dash-count-label';
			label.textContent = meta[1];
			card.appendChild(num);
			card.appendChild(label);
			dashCounts.appendChild(card);
		});
	}

	function renderDashNowDoing(items){
		dashNowDoing.innerHTML = '';
		dashNowDoingEmpty.hidden = items.length > 0;
		items.forEach(function(it){
			var row = document.createElement('div');
			row.className = 'et-dash-now-row';
			var nameEl = document.createElement('div');
			nameEl.className = 'et-dash-now-name';
			nameEl.textContent = it.employee_name;
			var taskEl = document.createElement('div');
			taskEl.className = 'et-dash-now-task';
			taskEl.textContent = it.task_title + (it.work_status_label ? ' · ' + it.work_status_label : '');
			var barWrap = document.createElement('div');
			barWrap.className = 'et-dash-now-bar';
			var barFill = document.createElement('div');
			barFill.className = 'et-dash-now-bar-fill';
			barFill.style.width = it.progress_percent + '%';
			barWrap.appendChild(barFill);
			var pctEl = document.createElement('div');
			pctEl.className = 'et-dash-now-pct';
			pctEl.textContent = it.progress_percent + '٪';
			row.appendChild(nameEl);
			row.appendChild(taskEl);
			row.appendChild(barWrap);
			row.appendChild(pctEl);
			dashNowDoing.appendChild(row);
		});
	}

	function renderDashProblems(items){
		dashProblems.innerHTML = '';
		dashProblemsEmpty.hidden = items.length > 0;
		items.forEach(function(it){
			var row = document.createElement('div');
			row.className = 'et-dash-problem-row';
			var titleEl = document.createElement('div');
			titleEl.className = 'et-dash-problem-title';
			titleEl.textContent = it.task_title + ' · ' + it.employee_name;
			var descEl = document.createElement('div');
			descEl.className = 'et-dash-problem-desc';
			descEl.textContent = it.description;
			row.appendChild(titleEl);
			row.appendChild(descEl);
			row.addEventListener('click', function(){ showAdminTab('view'); });
			dashProblems.appendChild(row);
		});
	}

	function renderDashProjects(items){
		dashProjects.innerHTML = '';
		dashProjectsEmpty.hidden = items.length > 0;
		items.forEach(function(p){
			var row = document.createElement('div');
			row.className = 'et-dash-project-row';
			var titleEl = document.createElement('div');
			titleEl.className = 'et-dash-project-title';
			titleEl.textContent = p.title;
			var barWrap = document.createElement('div');
			barWrap.className = 'et-dash-now-bar';
			var barFill = document.createElement('div');
			barFill.className = 'et-dash-now-bar-fill';
			barFill.style.width = p.progress_percent + '%';
			barWrap.appendChild(barFill);
			var pctEl = document.createElement('div');
			pctEl.className = 'et-dash-now-pct';
			pctEl.textContent = p.done_count + '/' + p.tasks_count;
			row.appendChild(titleEl);
			row.appendChild(barWrap);
			row.appendChild(pctEl);
			row.addEventListener('click', function(){ showAdminTab('projects'); openProjectDetail(p.id); });
			dashProjects.appendChild(row);
		});
	}

	function renderDashTimeline(items){
		dashTimeline.innerHTML = '';
		dashTimelineEmpty.hidden = items.length > 0;
		items.forEach(function(it){
			var row = document.createElement('div');
			row.className = 'et-dash-timeline-row';
			var timeEl = document.createElement('div');
			timeEl.className = 'et-dash-timeline-time';
			timeEl.textContent = it.time;
			var textEl = document.createElement('div');
			textEl.className = 'et-dash-timeline-text';
			var b = document.createElement('b'); b.textContent = it.employee_name + ' ';
			textEl.appendChild(b);
			textEl.appendChild(document.createTextNode(it.work_status_label + ' — ' + it.task_title + ' (' + it.progress_percent + '٪)'));
			row.appendChild(timeEl);
			row.appendChild(textEl);
			dashTimeline.appendChild(row);
		});
	}


	function populateAdminEmployeeSelects(){
		if(!state.employees.length && adminState.token){
			loadEmployees(adminState.token);
		}
	}

	function setupEmployeeAutocomplete(inputEl, hiddenEl, resultsEl, onChange){
		inputEl.addEventListener('input', function(){
			var q = inputEl.value.trim();
			hiddenEl.value = '';
			if(typeof onChange === 'function') onChange();
			resultsEl.innerHTML = '';
			if(!q){ return; }
			var matches = state.employees.filter(function(e){
				return e.name.indexOf(q) !== -1;
			}).slice(0, 8);
			matches.forEach(function(e){
				var li = document.createElement('li');
				li.innerHTML = '<span>' + escHtml(e.name) + '</span><span class="dept">' + escHtml(e.department||'') + '</span>';
				/** mousedown (لا click) + preventDefault: يمنع الحقل من فقدان التركيز (blur) قبل أن
				 *  يُنفَّذ الاختيار، وهو سبب شائع لظهور مشكلة "يختار الاسم لكن ما يثبت" في القوائم
				 *  المبنية بـ input+ul بدل <select> — إذ كان blur يمسح القائمة أحيانًا قبل اكتمال click. */
				li.addEventListener('mousedown', function(e2){
					e2.preventDefault();
					inputEl.value = e.name;
					hiddenEl.value = e.id;
					resultsEl.innerHTML = '';
					if(typeof onChange === 'function') onChange();
				});
				resultsEl.appendChild(li);
			});
		});
		inputEl.addEventListener('blur', function(){
			setTimeout(function(){ resultsEl.innerHTML = ''; }, 150);
		});
	}

	setupEmployeeAutocomplete(adminFilterEmployeeInput, adminFilterEmployee, adminFilterEmployeeResults, applyAdminFilter);
	setupEmployeeAutocomplete(adminSentFilterEmployeeInput, adminSentFilterEmployee, adminSentFilterEmployeeResults, loadAdminSentUpdates);
	setupEmployeeAutocomplete(adminAddEmployeeInput, adminAddEmployee, adminAddEmployeeResults);
	setupEmployeeAutocomplete(adminLeaveFilterEmployeeInput, adminLeaveFilterEmployee, adminLeaveFilterEmployeeResults, loadAdminLeaveRequests);
	setupEmployeeAutocomplete(adminAddpastEmployeeInput, adminAddpastEmployeeId, adminAddpastEmployeeResults);
	setupEmployeeAutocomplete(adminSickFilterEmployeeInput, adminSickFilterEmployee, adminSickFilterEmployeeResults, loadAdminSickRequests);
	setupEmployeeAutocomplete(adminPermissionFilterEmployeeInput, adminPermissionFilterEmployee, adminPermissionFilterEmployeeResults, loadAdminPermissionRequests);
	adminLeaveFilterStatus.addEventListener('change', loadAdminLeaveRequests);
	adminSickFilterStatus.addEventListener('change', loadAdminSickRequests);
	adminPermissionFilterStatus.addEventListener('change', loadAdminPermissionRequests);
	adminFilterWeek.addEventListener('change', loadAdminTasks);

	var adminTasksCache = [];

	function loadAdminTasks(){
		adminTasksLoading.hidden = false;
		adminTasksEmpty.hidden = true;
		adminTasksList.innerHTML = '';
		var qs = 'token=' + encodeURIComponent(adminState.token);
		if(adminFilterWeek.value) qs += '&week_start=' + encodeURIComponent(adminFilterWeek.value);
		apiFetch('/admin/weekly-tasks?' + qs).then(function(data){
			adminTasksLoading.hidden = true;
			adminTasksCache = data.tasks || [];
			applyAdminFilter();
		}).catch(function(err){
			adminTasksLoading.hidden = true;
			adminTasksEmpty.hidden = false;
			adminTasksEmpty.textContent = typeof err === 'string' ? err : 'تعذر تحميل المهام.';
		});
	}

	function applyAdminFilter(){
		var tasks = adminTasksCache;
		var empFilter = adminFilterEmployee.value;
		if(empFilter){ tasks = tasks.filter(function(t){ return String(t.employee_id) === String(empFilter); }); }
		if(!tasks.length){ adminTasksList.innerHTML = ''; adminKanbanBoard.innerHTML = ''; adminTasksEmpty.hidden = false; return; }
		adminTasksEmpty.hidden = true;
		if(adminViewMode === 'kanban'){ renderKanbanBoard(tasks); } else { renderAdminTasks(tasks); }
	}

	function setAdminViewMode(mode){
		adminViewMode = mode;
		viewToggleList.className = 'et-view-toggle-btn' + (mode === 'list' ? ' et-view-toggle-btn--active' : '');
		viewToggleKanban.className = 'et-view-toggle-btn' + (mode === 'kanban' ? ' et-view-toggle-btn--active' : '');
		adminTasksList.hidden = mode !== 'list';
		adminKanbanBoard.hidden = mode !== 'kanban';
		applyAdminFilter();
	}
	viewToggleList.addEventListener('click', function(){ setAdminViewMode('list'); });
	viewToggleKanban.addEventListener('click', function(){ setAdminViewMode('kanban'); });

	/** يحدّد عمود Kanban الذي تنتمي له المهمة، بنفس منطق تصنيف لوحة القيادة (لا حالة تخزين إضافية) */
	function kanbanColumnFor(t){
		if(t.status === 'done') return 'done';
		if(t.status === 'review') return 'review';
		var latest = t.latest_update;
		if(latest && latest.has_problem) return 'stuck';
		if((t.progress_percent || 0) > 0) return 'in_progress';
		return 'not_started';
	}

	var KANBAN_COLUMNS = [
		{ key: 'not_started', label: '🆕 لم تبدأ', droppable: true },
		{ key: 'in_progress', label: '⚙️ قيد التنفيذ', droppable: false },
		{ key: 'stuck', label: '⚠️ متوقفة', droppable: false },
		{ key: 'review', label: '🏷 مراجعة', droppable: false },
		{ key: 'done', label: '✅ مكتملة', droppable: true }
	];

	/** لوحة Kanban بسحب وإفلات حقيقي (HTML5 Drag & Drop الأصلي، بدون أي مكتبة خارجية). الأعمدة الوسطى
	 *  (قيد التنفيذ/متوقفة/مراجعة) محسوبة تلقائيًا من البيانات فلا تقبل إفلاتًا يدويًا؛ فقط "لم تبدأ" و"مكتملة"
	 *  قابلتان للسحب لأن لهما إجراء API مباشر وواضح (إعادة ضبط / اعتماد إنجاز). */
	function renderKanbanBoard(tasks){
		adminKanbanBoard.innerHTML = '';
		var grouped = {};
		KANBAN_COLUMNS.forEach(function(c){ grouped[c.key] = []; });
		tasks.forEach(function(t){ grouped[kanbanColumnFor(t)].push(t); });

		KANBAN_COLUMNS.forEach(function(col){
			var colEl = document.createElement('div');
			colEl.className = 'et-kanban-col';
			colEl.dataset.colKey = col.key;

			var head = document.createElement('div');
			head.className = 'et-kanban-col-head';
			var labelEl = document.createElement('span'); labelEl.textContent = col.label;
			var countEl = document.createElement('span'); countEl.className = 'et-kanban-col-count'; countEl.textContent = grouped[col.key].length;
			head.appendChild(labelEl); head.appendChild(countEl);
			colEl.appendChild(head);

			if(!grouped[col.key].length){
				var hint = document.createElement('div');
				hint.className = 'et-kanban-empty-hint';
				hint.textContent = 'لا توجد مهام';
				colEl.appendChild(hint);
			}

			grouped[col.key].forEach(function(t){
				var card = document.createElement('div');
				card.className = 'et-kanban-card';
				card.draggable = true;
				card.dataset.taskId = t.id;
				var titleEl = document.createElement('div');
				titleEl.className = 'et-kanban-card-title';
				titleEl.textContent = t.title;
				var metaEl = document.createElement('div');
				metaEl.className = 'et-kanban-card-meta';
				metaEl.innerHTML = '<span>' + escHtml(t.employee_name) + '</span><span>' + escHtml(t.progress_percent) + '٪</span>';
				card.appendChild(titleEl);
				card.appendChild(metaEl);
				if(t.urgency){
					var urg = document.createElement('div');
					urg.className = 'et-urgency-badge et-urgency-badge--' + t.urgency.level;
					urg.style.marginTop = '5px';
					urg.textContent = '⏰ ' + t.urgency.label;
					card.appendChild(urg);
				}
				card.addEventListener('dragstart', function(e){
					card.classList.add('et-kanban-card--dragging');
					e.dataTransfer.setData('text/plain', String(t.id));
					e.dataTransfer.effectAllowed = 'move';
				});
				card.addEventListener('dragend', function(){ card.classList.remove('et-kanban-card--dragging'); });
				colEl.appendChild(card);
			});

			if(col.droppable){
				colEl.addEventListener('dragover', function(e){ e.preventDefault(); colEl.classList.add('et-kanban-col--dragover'); });
				colEl.addEventListener('dragleave', function(){ colEl.classList.remove('et-kanban-col--dragover'); });
				colEl.addEventListener('drop', function(e){
					e.preventDefault();
					colEl.classList.remove('et-kanban-col--dragover');
					var taskId = e.dataTransfer.getData('text/plain');
					var task = tasks.find(function(t){ return String(t.id) === taskId; });
					if(!task) return;
					var fromCol = kanbanColumnFor(task);
					if(fromCol === col.key) return;
					handleKanbanDrop(task, col.key);
				});
			} else {
				colEl.addEventListener('dragover', function(e){ e.preventDefault(); });
				colEl.addEventListener('drop', function(e){
					e.preventDefault();
					toast('حالة "' + col.label + '" تُحسب تلقائيًا من التحديثات، ولا يمكن سحب مهمة إليها مباشرة.');
				});
			}

			adminKanbanBoard.appendChild(colEl);
		});
	}

	/** ينفّذ الإجراء الفعلي عند إفلات بطاقة على "لم تبدأ" (إعادة ضبط) أو "مكتملة" (اعتماد/إنهاء فوري) */
	function handleKanbanDrop(task, targetKey){
		if(targetKey === 'done'){
			var call = task.status === 'review'
				? apiFetch('/admin/weekly-tasks/approve', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ token: adminState.token, task_id: task.id }) })
				: apiFetch('/admin/weekly-tasks/status', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ token: adminState.token, task_id: task.id, completed: '1' }) });
			call.then(function(){ loadAdminTasks(); }).catch(function(){ toast('تعذر تحديث حالة المهمة.'); });
		} else if(targetKey === 'not_started'){
			apiFetch('/admin/weekly-tasks/status', {
				method: 'POST', headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ token: adminState.token, task_id: task.id, completed: '' })
			}).then(function(){ loadAdminTasks(); }).catch(function(){ toast('تعذر تحديث حالة المهمة.'); });
		}
	}

	/** إشعار عائم بسيط لأخطاء/ملاحظات لوحة Kanban، بدون الاعتماد على alert() المزعج */
	var toastTimer = null;
	function toast(message){
		var el = document.getElementById('et-toast');
		if(!el){
			el = document.createElement('div');
			el.id = 'et-toast';
			el.style.cssText = 'position:fixed;bottom:20px;left:50%;transform:translateX(-50%);background:#1c1f26;color:#fff;padding:10px 18px;border-radius:999px;font-size:12.5px;z-index:999;box-shadow:0 6px 20px rgba(0,0,0,.2);';
			document.body.appendChild(el);
		}
		el.textContent = message;
		el.style.display = 'block';
		clearTimeout(toastTimer);
		toastTimer = setTimeout(function(){ el.style.display = 'none'; }, 2500);
	}

	function renderAdminTasks(tasks){
		adminTasksList.innerHTML = '';
		var groups = {}, order = [];
		tasks.forEach(function(t){
			if(!groups[t.employee_name]){ groups[t.employee_name] = []; order.push(t.employee_name); }
			groups[t.employee_name].push(t);
		});
		order.forEach(function(name){
			var heading = document.createElement('div');
			heading.className = 'et-admin-emp-heading';
			heading.textContent = name;
			adminTasksList.appendChild(heading);
			groups[name].forEach(function(t){ adminTasksList.appendChild(buildAdminTaskCard(t)); });
		});
	}

	function buildAdminTaskCard(task){
		var box = document.createElement('div');
		box.className = 'et-admin-task';

		var top = document.createElement('div');
		top.className = 'et-admin-task-top';

		var title = document.createElement('div');
		title.className = 'et-admin-task-title' + (task.status === 'done' ? ' et-admin-task-title--done' : '');
		title.textContent = task.title;

		var checkbox = document.createElement('input');
		checkbox.type = 'checkbox';
		checkbox.checked = task.status === 'done';
		checkbox.addEventListener('change', function(){
			var completed = checkbox.checked;
			title.className = 'et-admin-task-title' + (completed ? ' et-admin-task-title--done' : '');
			apiFetch('/admin/weekly-tasks/status', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ token: adminState.token, task_id: task.id, completed: completed ? '1' : '0' })
			}).then(function(){
				var newProgress = completed ? 100 : 0;
				progFill.style.width = newProgress + '%';
				progLabel.textContent = newProgress + '٪';
				membersBox.querySelectorAll('.et-admin-member-bar-fill').forEach(function(el){ el.style.width = newProgress + '%'; });
				membersBox.querySelectorAll('.et-admin-member-pct').forEach(function(el){ el.textContent = newProgress + '٪'; });
			}).catch(function(){
				checkbox.checked = !completed;
				title.className = 'et-admin-task-title' + (checkbox.checked ? ' et-admin-task-title--done' : '');
			});
		});

		var body = document.createElement('div');
		body.style.flex = '1';
		body.appendChild(title);

		if(task.description){
			var desc = document.createElement('div');
			desc.className = 'et-task-desc';
			desc.textContent = task.description;
			body.appendChild(desc);
		}

		var meta = document.createElement('div');
		meta.className = 'et-admin-task-meta';
		var dayBadge = document.createElement('span');
		dayBadge.textContent = '📅 ' + task.day_label;
		meta.appendChild(dayBadge);
		var pill = document.createElement('span');
		pill.className = 'et-priority-pill et-priority-pill--' + task.priority;
		pill.textContent = task.priority_label;
		meta.appendChild(pill);
		body.appendChild(meta);

		if(task.project){
			var projBadge = document.createElement('span');
			projBadge.className = 'et-project-badge-inline';
			projBadge.textContent = '🗺️ ' + task.project.title;
			projBadge.addEventListener('click', function(){ showAdminTab('projects'); openProjectDetail(task.project.id); });
			body.appendChild(projBadge);
		}

		if(task.urgency){
			var urgBadge = document.createElement('span');
			urgBadge.className = 'et-urgency-badge et-urgency-badge--' + task.urgency.level;
			urgBadge.textContent = '⏰ ' + task.urgency.label;
			body.appendChild(urgBadge);
		}

		if(task.status === 'review'){
			var reviewBadge = document.createElement('div');
			reviewBadge.className = 'et-review-badge';
			reviewBadge.textContent = '🏷 بانتظار اعتماد الإدارة النهائي';
			body.appendChild(reviewBadge);
			var approveBtn = document.createElement('button');
			approveBtn.type = 'button';
			approveBtn.className = 'et-approve-btn';
			approveBtn.textContent = '🏷 اعتماد نهائي وإنهاء المهمة';
			approveBtn.addEventListener('click', function(){
				approveBtn.disabled = true;
				apiFetch('/admin/weekly-tasks/approve', {
					method: 'POST', headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({ token: adminState.token, task_id: task.id })
				}).then(function(){
					title.className = 'et-admin-task-title et-admin-task-title--done';
					checkbox.checked = true;
					reviewBadge.remove(); approveBtn.remove();
				}).catch(function(){ approveBtn.disabled = false; });
			});
			body.appendChild(approveBtn);
		}

		// شريط نسبة الإنجاز الفعلي + آخر تحديث رفعه الموظف على هذه المهمة تحديدًا
		var progress = task.progress_percent || 0;
		var progWrap = document.createElement('div');
		progWrap.className = 'et-task-progress-wrap';
		var progBar = document.createElement('div');
		progBar.className = 'et-task-progress-bar';
		var progFill = document.createElement('div');
		progFill.className = 'et-task-progress-fill';
		progFill.style.width = progress + '%';
		progBar.appendChild(progFill);
		var progLabel = document.createElement('div');
		progLabel.className = 'et-task-progress-label';
		progLabel.textContent = progress + '٪';
		progWrap.appendChild(progBar);
		progWrap.appendChild(progLabel);
		body.appendChild(progWrap);

		// أعضاء الفريق: كل عضو بنسبة إنجاز مستقلة + دوره بالمهمة، مع إمكانية إضافة/إزالة عضو لتتحول المهمة لجماعية
		var membersBox = document.createElement('div');
		membersBox.className = 'et-admin-members-box';

		function renderMembers(members){
			membersBox.innerHTML = '';
			(members || []).forEach(function(m){
				var row = document.createElement('div');
				row.className = 'et-admin-member-row';
				var nameWrap = document.createElement('div');
				nameWrap.className = 'et-admin-member-name';
				nameWrap.textContent = m.name;
				if(m.role_label){
					var roleEl = document.createElement('span');
					roleEl.className = 'et-admin-member-role';
					roleEl.textContent = m.role_label;
					nameWrap.appendChild(roleEl);
				}
				var barWrap = document.createElement('div');
				barWrap.className = 'et-admin-member-bar';
				var barFill = document.createElement('div');
				barFill.className = 'et-admin-member-bar-fill';
				barFill.style.width = (m.progress_percent || 0) + '%';
				barWrap.appendChild(barFill);
				var pctEl = document.createElement('span');
				pctEl.className = 'et-admin-member-pct';
				pctEl.textContent = (m.progress_percent || 0) + '٪';
				row.appendChild(nameWrap);
				row.appendChild(barWrap);
				row.appendChild(pctEl);

				var transferBtn = document.createElement('button');
				transferBtn.type = 'button';
				transferBtn.className = 'et-admin-member-remove';
				transferBtn.textContent = '🔄';
				transferBtn.title = 'نقل المسؤولية لموظف آخر';
				transferBtn.addEventListener('click', function(){
					var newName = prompt('انقل مسؤولية "' + m.name + '" في هذه المهمة إلى (اكتب اسم الموظف بالضبط):');
					if(!newName) return;
					var target = state.employees.find(function(e){ return e.name.trim() === newName.trim(); });
					if(!target){ alert('لم يتم العثور على موظف بهذا الاسم بالضبط.'); return; }
					apiFetch('/admin/weekly-tasks/transfer-member', {
						method: 'POST',
						headers: { 'Content-Type': 'application/json' },
						body: JSON.stringify({ token: adminState.token, task_id: task.id, from_employee_id: m.employee_id, to_employee_id: target.id })
					}).then(function(res){
						task.progress_percent = res.progress_percent;
						progFill.style.width = res.progress_percent + '%';
						progLabel.textContent = res.progress_percent + '٪';
						renderMembers(res.members);
					}).catch(function(){});
				});
				row.appendChild(transferBtn);

				if(members.length > 1){
					var removeBtn = document.createElement('button');
					removeBtn.type = 'button';
					removeBtn.className = 'et-admin-member-remove';
					removeBtn.textContent = '✕';
					removeBtn.title = 'إزالة من المهمة';
					removeBtn.addEventListener('click', function(){
						if(!confirm('إزالة ' + m.name + ' من هذه المهمة؟')) return;
						apiFetch('/admin/weekly-tasks/remove-member', {
							method: 'POST',
							headers: { 'Content-Type': 'application/json' },
							body: JSON.stringify({ token: adminState.token, task_id: task.id, employee_id: m.employee_id })
						}).then(function(res){
							task.progress_percent = res.progress_percent;
							progFill.style.width = res.progress_percent + '%';
							progLabel.textContent = res.progress_percent + '٪';
							renderMembers(res.members);
						}).catch(function(){});
					});
					row.appendChild(removeBtn);
				}
				membersBox.appendChild(row);
			});
		}
		renderMembers(task.members && task.members.length ? task.members : [{ name: task.employee_name, role_label: '', progress_percent: task.progress_percent }]);
		body.appendChild(membersBox);

		var addMemberToggle = document.createElement('button');
		addMemberToggle.type = 'button';
		addMemberToggle.className = 'et-admin-add-member-toggle';
		addMemberToggle.textContent = '➕ إضافة عضو';
		var addMemberForm = document.createElement('div');
		addMemberForm.className = 'et-admin-add-member-form';
		addMemberForm.hidden = true;

		var memberNameInput = document.createElement('input');
		memberNameInput.type = 'text'; memberNameInput.className = 'et-admin-add-member-name';
		memberNameInput.placeholder = 'اسم الموظف'; memberNameInput.autocomplete = 'off';
		var memberIdInput = document.createElement('input');
		memberIdInput.type = 'hidden';
		var memberResults = document.createElement('ul');
		memberResults.className = 'et-search-results et-search-results--overlay';

		var memberRoleInput = document.createElement('input');
		memberRoleInput.type = 'text'; memberRoleInput.className = 'et-admin-add-member-role';
		memberRoleInput.placeholder = 'الدور (اختياري): مراجعة، تصميم…';

		var memberAddBtn = document.createElement('button');
		memberAddBtn.type = 'button';
		memberAddBtn.textContent = 'إضافة';
		memberAddBtn.addEventListener('click', function(){
			if(!memberIdInput.value){ memberNameInput.focus(); return; }
			memberAddBtn.disabled = true;
			apiFetch('/admin/weekly-tasks/add-member', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ token: adminState.token, task_id: task.id, employee_id: memberIdInput.value, role_label: memberRoleInput.value })
			}).then(function(res){
				task.progress_percent = res.progress_percent;
				progFill.style.width = res.progress_percent + '%';
				progLabel.textContent = res.progress_percent + '٪';
				renderMembers(res.members);
				memberNameInput.value = ''; memberIdInput.value = ''; memberRoleInput.value = '';
				addMemberForm.hidden = true;
				addMemberToggle.textContent = '➕ إضافة عضو';
			}).catch(function(){}).then(function(){ memberAddBtn.disabled = false; });
		});

		var memberInputWrap = document.createElement('div');
		memberInputWrap.style.position = 'relative';
		memberInputWrap.appendChild(memberNameInput);
		memberInputWrap.appendChild(memberIdInput);
		memberInputWrap.appendChild(memberResults);
		setupEmployeeAutocomplete(memberNameInput, memberIdInput, memberResults);

		addMemberForm.appendChild(memberInputWrap);
		addMemberForm.appendChild(memberRoleInput);
		addMemberForm.appendChild(memberAddBtn);

		addMemberToggle.addEventListener('click', function(){
			addMemberForm.hidden = !addMemberForm.hidden;
			addMemberToggle.textContent = addMemberForm.hidden ? '➕ إضافة عضو' : '➖ إخفاء';
		});
		body.appendChild(addMemberToggle);
		body.appendChild(addMemberForm);

		// صف مضغوط لأزرار الوصول السريع (Checklist/مرفقات) بدل عرضها مفتوحة دائمًا
		var adminExtrasRow = document.createElement('div');
		adminExtrasRow.className = 'et-extras-row';
		body.appendChild(adminExtrasRow);

		// Checklist فرعي (الإدارة تقدر تضيف/تبدّل بنود أيضًا)
		var adminChecklistToggle = document.createElement('button');
		adminChecklistToggle.type = 'button';
		adminChecklistToggle.className = 'et-extras-toggle';
		adminExtrasRow.appendChild(adminChecklistToggle);
		var adminChecklistBox = document.createElement('div');
		adminChecklistBox.className = 'et-checklist';
		adminChecklistBox.hidden = true;
		var adminChecklistItemsWrap = document.createElement('div');
		adminChecklistBox.appendChild(adminChecklistItemsWrap);
		function renderAdminChecklist(items){
			adminChecklistItemsWrap.innerHTML = '';
			var done = items.filter(function(i){ return i.done; }).length;
			adminChecklistToggle.textContent = '✅ قائمة الإنجاز (' + done + '/' + items.length + ')';
			items.forEach(function(it){
				var row = document.createElement('div');
				row.className = 'et-checklist-item' + (it.done ? ' et-checklist-item--done' : '');
				var cb = document.createElement('input');
				cb.type = 'checkbox'; cb.checked = it.done;
				cb.addEventListener('change', function(){
					apiFetch('/weekly-tasks/checklist/toggle', {
						method: 'POST', headers: { 'Content-Type': 'application/json' },
						body: JSON.stringify({ token: adminState.token, task_id: task.id, item_id: it.id, done: cb.checked ? '1' : '' })
					}).then(function(res){ renderAdminChecklist(res.items); }).catch(function(){});
				});
				var span = document.createElement('span'); span.textContent = it.text;
				row.appendChild(cb); row.appendChild(span);
				adminChecklistItemsWrap.appendChild(row);
			});
		}
		renderAdminChecklist(task.checklist || []);
		var adminChecklistAddRow = document.createElement('div');
		adminChecklistAddRow.className = 'et-checklist-add-row';
		var adminChecklistInput = document.createElement('input');
		adminChecklistInput.type = 'text'; adminChecklistInput.placeholder = 'أضف بندًا جديدًا…';
		var adminChecklistAddBtn = document.createElement('button');
		adminChecklistAddBtn.type = 'button'; adminChecklistAddBtn.textContent = 'إضافة';
		adminChecklistAddBtn.addEventListener('click', function(){
			var text = adminChecklistInput.value.trim();
			if(!text) return;
			apiFetch('/weekly-tasks/checklist/add', {
				method: 'POST', headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ token: adminState.token, task_id: task.id, text: text })
			}).then(function(res){ renderAdminChecklist(res.items); adminChecklistInput.value = ''; }).catch(function(){});
		});
		adminChecklistAddRow.appendChild(adminChecklistInput);
		adminChecklistAddRow.appendChild(adminChecklistAddBtn);
		adminChecklistBox.appendChild(adminChecklistAddRow);
		adminChecklistToggle.addEventListener('click', function(){ adminChecklistBox.hidden = !adminChecklistBox.hidden; });
		adminChecklistBox.hidden = !(task.checklist && task.checklist.length);
		body.appendChild(adminChecklistBox);

		// مرفقات مستقلة على مستوى المهمة
		if(task.attachments && task.attachments.length){
			var adminAttachToggle = document.createElement('button');
			adminAttachToggle.type = 'button';
			adminAttachToggle.className = 'et-extras-toggle';
			adminAttachToggle.textContent = '📎 المرفقات (' + task.attachments.length + ')';
			adminExtrasRow.appendChild(adminAttachToggle);
			var adminAttachBox = document.createElement('div');
			adminAttachBox.className = 'et-task-attachments';
			adminAttachBox.hidden = true;
			task.attachments.forEach(function(a){
				var row = document.createElement('div');
				row.className = 'et-task-attachment-row';
				var link = document.createElement('a');
				link.href = a.url; link.target = '_blank'; link.rel = 'noopener'; link.textContent = a.name;
				row.appendChild(link);
				adminAttachBox.appendChild(row);
			});
			adminAttachToggle.addEventListener('click', function(){ adminAttachBox.hidden = !adminAttachBox.hidden; });
			body.appendChild(adminAttachBox);
		}

		// طلبات المساعدة المعلّقة على هذه المهمة — تبقى ظاهرة دائمًا لأنها تحتاج إجراء
		if(task.pending_help && task.pending_help.length){
			var adminHelpBox = document.createElement('div');
			adminHelpBox.className = 'et-help-pending';
			task.pending_help.forEach(function(h){
				var row = document.createElement('div');
				row.className = 'et-help-pending-row';
				var span = document.createElement('span');
				span.textContent = '🆘 ' + h.from_name + ' ← ' + h.to_name + ': ' + (h.message || '');
				row.appendChild(span);
				var resolveBtn = document.createElement('button');
				resolveBtn.type = 'button'; resolveBtn.textContent = 'تم الحل';
				resolveBtn.addEventListener('click', function(){
					apiFetch('/weekly-tasks/help/resolve', {
						method: 'POST', headers: { 'Content-Type': 'application/json' },
						body: JSON.stringify({ token: adminState.token, request_id: h.id })
					}).then(function(){ row.remove(); }).catch(function(){});
				});
				row.appendChild(resolveBtn);
				adminHelpBox.appendChild(row);
			});
			body.appendChild(adminHelpBox);
		}

		if(task.latest_update){
			var u = task.latest_update;
			var latestBox = document.createElement('div');
			latestBox.className = 'et-task-latest';
			if(u.work_status_label){
				var statusBadgeEl = document.createElement('span');
				statusBadgeEl.className = 'et-work-status-badge et-work-status-badge--' + (u.work_status || 'in_progress');
				statusBadgeEl.textContent = u.work_status_label;
				latestBox.appendChild(statusBadgeEl);
			}
			var line1 = document.createElement('div');
			var b1 = document.createElement('b'); b1.textContent = 'العمل الحالي: ';
			line1.appendChild(b1);
			line1.appendChild(document.createTextNode(u.current_work || '—'));
			latestBox.appendChild(line1);
			if(u.has_problem && u.problem_description){
				var line2 = document.createElement('div');
				var flag = document.createElement('span'); flag.className = 'et-task-latest-flag'; flag.textContent = '⚠️ مشكلة: ';
				line2.appendChild(flag);
				line2.appendChild(document.createTextNode(u.problem_description));
				latestBox.appendChild(line2);
			}
			if(u.needs_help && u.help_description){
				var line3 = document.createElement('div');
				var flag2 = document.createElement('span'); flag2.className = 'et-task-latest-flag'; flag2.textContent = '🆘 يحتاج مساعدة: ';
				line3.appendChild(flag2);
				line3.appendChild(document.createTextNode(u.help_description));
				latestBox.appendChild(line3);
			}
			var dateLine = document.createElement('div');
			dateLine.style.color = 'var(--et-muted)'; dateLine.style.marginTop = '3px'; dateLine.style.fontSize = '10.5px';
			dateLine.textContent = '🕒 ' + u.date;
			latestBox.appendChild(dateLine);
			body.appendChild(latestBox);
		}

		var timelineToggle = document.createElement('button');
		timelineToggle.type = 'button';
		timelineToggle.className = 'et-task-timeline-toggle';
		timelineToggle.textContent = 'عرض سجل التحديثات';
		var timelineBox = document.createElement('div');
		timelineBox.className = 'et-task-timeline';
		timelineBox.hidden = true;
		var timelineLoaded = false;
		timelineToggle.addEventListener('click', function(){
			timelineBox.hidden = !timelineBox.hidden;
			timelineToggle.textContent = timelineBox.hidden ? 'عرض سجل التحديثات' : 'إخفاء سجل التحديثات';
			if(!timelineBox.hidden && !timelineLoaded){
				timelineBox.innerHTML = 'جارِ التحميل…';
				apiFetch('/admin/weekly-tasks/timeline?token=' + encodeURIComponent(adminState.token) + '&task_id=' + task.id).then(function(data){
					timelineLoaded = true;
					timelineBox.innerHTML = '';
					var updates = data.updates || [];
					if(!updates.length){ timelineBox.textContent = 'لا يوجد تحديثات بعد.'; return; }
					updates.forEach(function(u2){
						var item = document.createElement('div');
						item.className = 'et-task-timeline-item';
						var b = document.createElement('b'); b.textContent = u2.progress_percent + '٪ — ' + (u2.work_status_label || '') + ' — ';
						item.appendChild(b);
						item.appendChild(document.createTextNode((u2.current_work || u2.achievements || '') + ' · ' + u2.date));
						timelineBox.appendChild(item);
					});
				}).catch(function(){ timelineBox.textContent = 'تعذر تحميل السجل.'; });
			}
		});
		body.appendChild(timelineToggle);
		body.appendChild(timelineBox);

		var commentsBox = document.createElement('div');
		commentsBox.className = 'et-admin-comments';
		(task.comments || []).forEach(function(c){ commentsBox.appendChild(buildAdminCommentEl(c.author, c.message, c.date)); });

		var cForm = document.createElement('div');
		cForm.className = 'et-admin-comment-form';
		var cInput = document.createElement('input');
		cInput.type = 'text';
		cInput.placeholder = 'أضف ملاحظة متابعة…';
		var cBtn = document.createElement('button');
		cBtn.type = 'button';
		cBtn.textContent = 'إرسال';
		cBtn.addEventListener('click', function(){
			var msg = cInput.value.trim();
			if(!msg) return;
			cBtn.disabled = true;
			apiFetch('/admin/weekly-tasks/comment', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ token: adminState.token, task_id: task.id, message: msg })
			}).then(function(){
				commentsBox.insertBefore(buildAdminCommentEl('الإدارة', msg, 'الآن'), cForm);
				cInput.value = '';
			}).catch(function(){}).then(function(){ cBtn.disabled = false; });
		});
		cForm.appendChild(cInput);
		cForm.appendChild(cBtn);
		commentsBox.appendChild(cForm);
		body.appendChild(commentsBox);

		top.appendChild(checkbox);
		top.appendChild(body);
		box.appendChild(top);
		return box;
	}

	function buildAdminCommentEl(author, message, date){
		var el = document.createElement('div');
		el.className = 'et-admin-comment';
		var b = document.createElement('b');
		b.textContent = author + ': ';
		el.appendChild(b);
		el.appendChild(document.createTextNode(message));
		var span = document.createElement('span');
		span.textContent = date;
		el.appendChild(span);
		return el;
	}

	/** يحمّل كل المهام/التحديثات اليومية المرسلة من الموظفين (مع فلترة اختيارية حسب موظف) لتبويب "المهام المرسلة" */
	/** يحمّل مستوى الأداء الشهري لكل الموظفين لتبويب "مستوى الأداء" بالإدارة */
	function loadAdminPerformance(){
		if(!adminPerfMonthInput.value){ adminPerfMonthInput.value = currentMonthValue(); adminPerfMonthInput.max = currentMonthValue(); }
		adminPerfLoading.hidden = false;
		adminPerfEmpty.hidden = true;
		adminPerfList.innerHTML = '';
		var parts = adminPerfMonthInput.value.split('-');
		var qs = 'token=' + encodeURIComponent(adminState.token) + '&year=' + parts[0] + '&month=' + parseInt(parts[1], 10);
		apiFetch('/admin/performance?' + qs).then(function(data){
			adminPerfLoading.hidden = true;
			var items = data.items || [];
			if(!items.length){ adminPerfEmpty.hidden = false; return; }
			items.forEach(function(perf){ adminPerfList.appendChild(buildPerformanceCard(perf, true)); });
		}).catch(function(){
			adminPerfLoading.hidden = true;
			adminPerfEmpty.hidden = false;
			adminPerfEmpty.textContent = 'تعذر تحميل بيانات الأداء.';
		});
	}
	adminPerfMonthInput.addEventListener('change', loadAdminPerformance);

	/* ---------- خريطة المشاريع المترابطة ("🗺️ المشاريع") ---------- */

	function showProjectsListView(){
		currentProjectId = null;
		adminProjectsListView.hidden = false;
		adminProjectDetailView.hidden = true;
	}

	function loadAdminProjects(){
		adminProjectsLoading.hidden = false;
		adminProjectsEmpty.hidden = true;
		adminProjectsList.innerHTML = '';
		apiFetch('/admin/projects?token=' + encodeURIComponent(adminState.token)).then(function(data){
			adminProjectsLoading.hidden = true;
			var projects = data.projects || [];
			if(!projects.length){ adminProjectsEmpty.hidden = false; return; }
			projects.forEach(function(p){ adminProjectsList.appendChild(buildProjectCard(p)); });
		}).catch(function(){
			adminProjectsLoading.hidden = true;
			adminProjectsEmpty.hidden = false;
			adminProjectsEmpty.textContent = 'تعذر تحميل المشاريع.';
		});
	}

	function buildProjectCard(p){
		var card = document.createElement('div');
		card.className = 'et-project-card';
		var title = document.createElement('div');
		title.className = 'et-project-card-title';
		title.textContent = p.title;
		card.appendChild(title);
		if(p.description){
			var desc = document.createElement('div');
			desc.className = 'et-project-card-desc';
			desc.textContent = p.description;
			card.appendChild(desc);
		}
		var meta = document.createElement('div');
		meta.className = 'et-project-card-meta';
		var stepsSpan = document.createElement('span');
		stepsSpan.textContent = '🔗 ' + p.tasks_count + ' مهمة (' + p.done_count + ' مكتملة)';
		meta.appendChild(stepsSpan);
		var pctSpan = document.createElement('span');
		pctSpan.textContent = p.progress_percent + '٪ إجمالي';
		meta.appendChild(pctSpan);
		card.appendChild(meta);
		card.addEventListener('click', function(){ openProjectDetail(p.id); });
		return card;
	}

	adminProjectNewSubmit.addEventListener('click', function(){
		var title = adminProjectNewTitle.value.trim();
		if(!title) return;
		adminProjectNewSubmit.disabled = true;
		apiFetch('/admin/projects/create', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ token: adminState.token, title: title })
		}).then(function(res){
			adminProjectNewTitle.value = '';
			loadAdminProjects();
			openProjectDetail(res.id);
		}).catch(function(){}).then(function(){ adminProjectNewSubmit.disabled = false; });
	});
	adminProjectNewTitle.addEventListener('keydown', function(e){ if(e.key === 'Enter'){ adminProjectNewSubmit.click(); } });

	function openProjectDetail(projectId){
		currentProjectId = projectId;
		adminProjectsListView.hidden = true;
		adminProjectDetailView.hidden = false;
		resetProjectTaskForm();
		adminProjectChain.innerHTML = 'جارِ التحميل…';
		apiFetch('/admin/projects/chain?token=' + encodeURIComponent(adminState.token) + '&project_id=' + projectId).then(function(data){
			adminProjectDetailTitle.textContent = data.project.title;
			adminProjectDetailDesc.textContent = data.project.description || '';
			adminProjectDetailDesc.hidden = !data.project.description;
			renderProjectChain(data.chain);
		}).catch(function(){
			adminProjectChain.textContent = 'تعذر تحميل المشروع.';
		});
		// نجهّز مهام الأسبوع الحالي لخانة "ربط مهمة موجودة" (بحث نصي بالعنوان)
		apiFetch('/admin/weekly-tasks?token=' + encodeURIComponent(adminState.token)).then(function(data){
			adminAllTasksCache = data.tasks || [];
		}).catch(function(){});
	}
	adminProjectBackBtn.addEventListener('click', showProjectsListView);

	function chainNodeBadgeLabel(state){
		if(state === 'done') return '✓ مكتملة';
		if(state === 'in_progress') return 'قيد التنفيذ';
		if(state === 'stuck') return '⚠ متوقفة';
		if(state === 'blocked') return 'معلّقة';
		return 'لم تبدأ';
	}

	function renderProjectChain(chain){
		adminProjectChain.innerHTML = '';
		if(!chain || !chain.length){
			adminProjectChain.innerHTML = '<div class="et-empty">لا توجد مهام مربوطة بهذا المشروع بعد. اربط مهمة من الأسفل.</div>';
			return;
		}
		chain.forEach(function(node, idx){
			var el = document.createElement('div');
			el.className = 'et-chain-node et-chain-node--' + node.node_state;

			var top = document.createElement('div');
			top.className = 'et-chain-node-top';
			var titleEl = document.createElement('div');
			titleEl.className = 'et-chain-node-title';
			titleEl.textContent = (idx + 1) + '. ' + node.title;
			var badge = document.createElement('span');
			badge.className = 'et-chain-node-badge et-chain-node-badge--' + node.node_state;
			badge.textContent = chainNodeBadgeLabel(node.node_state);
			top.appendChild(titleEl);
			top.appendChild(badge);
			el.appendChild(top);

			var metaEl = document.createElement('div');
			metaEl.className = 'et-chain-node-meta';
			var names = (node.members && node.members.length) ? node.members.map(function(m){ return m.name + ' ' + m.progress_percent + '٪'; }).join(' · ') : node.employee_name;
			metaEl.textContent = '👤 ' + names + ' · ' + node.progress_percent + '٪ إجمالي';
			el.appendChild(metaEl);

			if(node.node_reason){
				var reasonEl = document.createElement('div');
				reasonEl.className = 'et-chain-node-reason';
				reasonEl.textContent = node.node_state === 'stuck' ? ('⚠ ' + node.node_reason) : node.node_reason;
				el.appendChild(reasonEl);
			}

			var unlinkBtn = document.createElement('button');
			unlinkBtn.type = 'button';
			unlinkBtn.className = 'et-chain-unlink';
			unlinkBtn.textContent = 'فصل عن المشروع';
			unlinkBtn.addEventListener('click', function(){
				if(!confirm('فصل "' + node.title + '" عن هذا المشروع؟')) return;
				apiFetch('/admin/projects/unlink-task', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({ token: adminState.token, task_id: node.id })
				}).then(function(res){
					renderProjectChain(res.chain);
					loadAdminProjects();
				}).catch(function(){});
			});
			el.appendChild(unlinkBtn);

			adminProjectChain.appendChild(el);

			if(idx < chain.length - 1){
				var arrow = document.createElement('div');
				arrow.className = 'et-chain-arrow';
				arrow.textContent = '↓';
				adminProjectChain.appendChild(arrow);
			}
		});
	}

	function setupTaskAutocomplete(inputEl, hiddenEl, resultsEl){
		inputEl.addEventListener('input', function(){
			var q = inputEl.value.trim();
			hiddenEl.value = '';
			resultsEl.innerHTML = '';
			if(!q) return;
			var matches = adminAllTasksCache.filter(function(t){
				return t.title.indexOf(q) !== -1;
			}).slice(0, 8);
			matches.forEach(function(t){
				var li = document.createElement('li');
				li.innerHTML = '<span>' + escHtml(t.title) + '</span><span class="dept">' + escHtml(t.employee_name || '') + '</span>';
				li.addEventListener('mousedown', function(e2){
					e2.preventDefault();
					inputEl.value = t.title;
					hiddenEl.value = t.id;
					resultsEl.innerHTML = '';
				});
				resultsEl.appendChild(li);
			});
		});
		inputEl.addEventListener('blur', function(){
			setTimeout(function(){ resultsEl.innerHTML = ''; }, 150);
		});
	}
	setupTaskAutocomplete(adminProjectLinkTaskInput, adminProjectLinkTaskId, adminProjectLinkTaskResults);

	adminProjectLinkSubmit.addEventListener('click', function(){
		if(!currentProjectId || !adminProjectLinkTaskId.value){ adminProjectLinkTaskInput.focus(); return; }
		adminProjectLinkSubmit.disabled = true;
		apiFetch('/admin/projects/link-task', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({
				token: adminState.token,
				project_id: currentProjectId,
				task_id: adminProjectLinkTaskId.value,
				sequence_order: adminProjectLinkOrder.value || ''
			})
		}).then(function(res){
			renderProjectChain(res.chain);
			adminProjectLinkTaskInput.value = ''; adminProjectLinkTaskId.value = ''; adminProjectLinkOrder.value = '';
			loadAdminProjects();
		}).catch(function(){}).then(function(){ adminProjectLinkSubmit.disabled = false; });
	});

	/** "إنشاء مهمة مشتركة لهذا المشروع": صف واحد لكل موظف مشارك (بحث بالاسم + نص "المطلوب منه" الخاص
	 *  به)، الصف الأول (الموظف الأساسي) دائم ولا يُحذف، وأي صف إضافي يُضاف عبر زر "+ إضافة موظف مشارك"
	 *  ويحمل زر حذف خاصًا به. عند الإرسال تُنشأ مهمة واحدة بنفس العنوان تُشارك بين كل من اختير. */
	function buildProjectTaskMemberRow(label, removable){
		var row = document.createElement('div');
		row.className = 'et-project-task-member-row';
		row.style.cssText = 'margin-bottom:10px;padding:10px;border:1px solid var(--et-line);border-radius:8px;';

		var lbl = document.createElement('label');
		lbl.className = 'et-label';
		lbl.style.marginTop = '0';
		lbl.textContent = label;
		row.appendChild(lbl);

		var searchWrap = document.createElement('div');
		searchWrap.style.position = 'relative';
		var input = document.createElement('input');
		input.type = 'text'; input.className = 'et-select et-search-input'; input.placeholder = 'اكتب اسم الموظف…'; input.autocomplete = 'off';
		var hidden = document.createElement('input');
		hidden.type = 'hidden';
		var results = document.createElement('ul');
		results.className = 'et-search-results';
		searchWrap.appendChild(input); searchWrap.appendChild(hidden); searchWrap.appendChild(results);
		row.appendChild(searchWrap);

		var roleInput = document.createElement('input');
		roleInput.type = 'text'; roleInput.className = 'et-select'; roleInput.style.marginTop = '6px';
		roleInput.placeholder = 'المطلوب منه بهذه المهمة تحديدًا (اختياري)';
		row.appendChild(roleInput);

		if(removable){
			var removeBtn = document.createElement('button');
			removeBtn.type = 'button'; removeBtn.className = 'et-view-toggle-btn'; removeBtn.style.marginTop = '6px';
			removeBtn.textContent = '✕ إزالة هذا الموظف';
			removeBtn.addEventListener('click', function(){ row.remove(); });
			row.appendChild(removeBtn);
		}

		setupEmployeeAutocomplete(input, hidden, results);
		row._etEmployeeHidden = hidden;
		row._etRoleInput = roleInput;
		return row;
	}

	function resetProjectTaskForm(){
		adminProjectTaskTitle.value = '';
		adminProjectTaskDay.value = '';
		adminProjectTaskPriority.value = 'medium';
		adminProjectTaskDue.value = '';
		adminProjectTaskMembers.innerHTML = '';
		adminProjectTaskMembers.appendChild(buildProjectTaskMemberRow('الموظف الأول', false));
		adminProjectTaskMsg.hidden = true;
	}
	adminProjectTaskAddMember.addEventListener('click', function(){
		var count = adminProjectTaskMembers.children.length;
		adminProjectTaskMembers.appendChild(buildProjectTaskMemberRow('الموظف ' + (count + 1), true));
	});

	adminProjectTaskSubmit.addEventListener('click', function(){
		adminProjectTaskMsg.hidden = true;
		var title = adminProjectTaskTitle.value.trim();
		if(!title){ adminProjectTaskMsg.hidden = false; adminProjectTaskMsg.className = 'et-inline-msg et-inline-msg--error'; adminProjectTaskMsg.textContent = 'الرجاء كتابة عنوان المهمة.'; return; }
		if(!currentProjectId) return;

		var members = [];
		Array.prototype.forEach.call(adminProjectTaskMembers.children, function(row){
			var empId = row._etEmployeeHidden ? row._etEmployeeHidden.value : '';
			if(!empId) return;
			members.push({ employee_id: empId, role_label: row._etRoleInput ? row._etRoleInput.value : '' });
		});
		if(!members.length){ adminProjectTaskMsg.hidden = false; adminProjectTaskMsg.className = 'et-inline-msg et-inline-msg--error'; adminProjectTaskMsg.textContent = 'اختر موظفًا واحدًا على الأقل من القائمة.'; return; }

		adminProjectTaskSubmit.disabled = true;
		apiFetch('/admin/projects/create-task', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({
				token: adminState.token,
				project_id: currentProjectId,
				title: title,
				task_day: adminProjectTaskDay.value,
				priority: adminProjectTaskPriority.value,
				due_date: adminProjectTaskDue.value,
				members: members
			})
		}).then(function(res){
			renderProjectChain(res.chain);
			resetProjectTaskForm();
			loadAdminProjects();
			toast('تم إنشاء المهمة المشتركة وتوزيعها على الموظفين المختارين.');
		}).catch(function(err){
			adminProjectTaskMsg.hidden = false;
			adminProjectTaskMsg.className = 'et-inline-msg et-inline-msg--error';
			adminProjectTaskMsg.textContent = typeof err === 'string' ? err : 'تعذر إنشاء المهمة، حاول مجددًا.';
		}).then(function(){ adminProjectTaskSubmit.disabled = false; });
	});
	resetProjectTaskForm();

	function loadAdminSentUpdates(){
		adminSentLoading.hidden = false;
		adminSentEmpty.hidden = true;
		adminSentList.innerHTML = '';
		var qs = 'token=' + encodeURIComponent(adminState.token);
		if(adminSentFilterEmployee.value) qs += '&employee_id=' + encodeURIComponent(adminSentFilterEmployee.value);
		apiFetch('/admin/updates?' + qs).then(function(data){
			adminSentLoading.hidden = true;
			adminSentLoaded = true;
			var updates = data.updates || [];
			if(!updates.length){ adminSentEmpty.hidden = false; return; }
			renderAdminSentUpdates(updates);
		}).catch(function(err){
			adminSentLoading.hidden = true;
			adminSentEmpty.hidden = false;
			adminSentEmpty.textContent = typeof err === 'string' ? err : 'تعذر تحميل المهام المرسلة.';
		});
	}

	function renderAdminSentUpdates(updates){
		adminSentList.innerHTML = '';
		var groups = {}, order = [];
		updates.forEach(function(u){
			if(!groups[u.employee_name]){ groups[u.employee_name] = []; order.push(u.employee_name); }
			groups[u.employee_name].push(u);
		});
		order.forEach(function(name){
			var heading = document.createElement('div');
			heading.className = 'et-admin-emp-heading';
			heading.textContent = name;
			adminSentList.appendChild(heading);
			groups[name].forEach(function(u){ adminSentList.appendChild(buildAdminSentCard(u)); });
		});
	}

	function buildAdminSentCard(u){
		var box = document.createElement('div');
		box.className = 'et-admin-task';

		var title = document.createElement('div');
		title.className = 'et-admin-task-title';
		title.textContent = 'تحديث يوم ' + u.update_date + (u.has_problem ? ' ⚠️' : '');
		box.appendChild(title);

		var meta = document.createElement('div');
		meta.className = 'et-admin-task-meta';
		var dateSpan = document.createElement('span');
		dateSpan.textContent = '🕒 ' + u.created_at;
		meta.appendChild(dateSpan);
		box.appendChild(meta);

		if(u.achievements){
			var ach = document.createElement('div');
			ach.className = 'et-task-desc';
			ach.textContent = '📌 ' + u.achievements;
			box.appendChild(ach);
		}
		if(u.current_tasks){
			var cur = document.createElement('div');
			cur.className = 'et-task-desc';
			cur.textContent = '🗂️ ' + u.current_tasks;
			box.appendChild(cur);
		}
		if(u.has_problem && u.problem_description){
			var prob = document.createElement('div');
			prob.className = 'et-task-desc';
			prob.textContent = '⚠️ ' + u.problem_description;
			box.appendChild(prob);
		}
		if(u.assistance_required && u.assistance_description){
			var help = document.createElement('div');
			help.className = 'et-task-desc';
			help.textContent = '🆘 ' + u.assistance_description;
			box.appendChild(help);
		}

		// المرفقات: كانت لا تصل عبر بريد الإدارة، فهذا العرض هو المصدر الموثوق لرؤيتها والوصول إليها
		if(u.attachments && u.attachments.length){
			var attWrap = document.createElement('div');
			attWrap.className = 'et-task-desc';
			attWrap.textContent = '📎 المرفقات: ';
			u.attachments.forEach(function(a, i){
				var link = document.createElement('a');
				link.href = a.url; link.target = '_blank'; link.rel = 'noopener';
				link.textContent = a.name;
				link.style.marginLeft = '6px';
				attWrap.appendChild(link);
				if(i < u.attachments.length - 1) attWrap.appendChild(document.createTextNode('، '));
			});
			box.appendChild(attWrap);
		}

		var commentsBox = document.createElement('div');
		commentsBox.className = 'et-admin-comments';
		(u.comments || []).forEach(function(c){ commentsBox.appendChild(buildAdminCommentEl(c.author, c.message, c.date)); });

		var cForm = document.createElement('div');
		cForm.className = 'et-admin-comment-form';
		var cInput = document.createElement('input');
		cInput.type = 'text';
		cInput.placeholder = 'أضف تعليقًا يصل للموظف…';
		var cBtn = document.createElement('button');
		cBtn.type = 'button';
		cBtn.textContent = 'إرسال';
		cBtn.addEventListener('click', function(){
			var msg = cInput.value.trim();
			if(!msg) return;
			cBtn.disabled = true;
			apiFetch('/admin/updates/comment', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ token: adminState.token, update_id: u.id, message: msg })
			}).then(function(){
				commentsBox.insertBefore(buildAdminCommentEl('الإدارة', msg, 'الآن'), cForm);
				cInput.value = '';
			}).catch(function(){}).then(function(){ cBtn.disabled = false; });
		});
		cForm.appendChild(cInput);
		cForm.appendChild(cBtn);
		commentsBox.appendChild(cForm);
		box.appendChild(commentsBox);

		return box;
	}

	function addAdminTaskRow(){
		var row = document.createElement('div');
		row.className = 'et-admin-add-row';

		var input = document.createElement('input');
		input.type = 'text';
		input.placeholder = 'نص المهمة';

		var daySelect = document.createElement('select');
		ADMIN_DAY_OPTS.forEach(function(o){
			var opt = document.createElement('option');
			opt.value = o[0]; opt.textContent = o[1];
			daySelect.appendChild(opt);
		});

		var prioritySelect = document.createElement('select');
		ADMIN_PRIORITY_OPTS.forEach(function(o){
			var opt = document.createElement('option');
			opt.value = o[0]; opt.textContent = o[1];
			if(o[0] === 'medium') opt.selected = true;
			prioritySelect.appendChild(opt);
		});

		var dueDateInput = document.createElement('input');
		dueDateInput.type = 'date';
		dueDateInput.className = 'et-admin-add-due';
		dueDateInput.title = 'تاريخ الاستحقاق (اختياري)';

		var removeBtn = document.createElement('button');
		removeBtn.type = 'button';
		removeBtn.className = 'et-remove-row';
		removeBtn.textContent = '✕';
		removeBtn.addEventListener('click', function(){
			if(adminAddRows.children.length > 1){ row.remove(); }
		});

		row.appendChild(input);
		row.appendChild(daySelect);
		row.appendChild(prioritySelect);
		row.appendChild(dueDateInput);
		row.appendChild(removeBtn);
		adminAddRows.appendChild(row);
	}
	adminAddRowBtn.addEventListener('click', addAdminTaskRow);

	function showAdminAddMsg(type, text){
		adminAddMsg.hidden = false;
		adminAddMsg.className = 'et-inline-msg et-inline-msg--' + type;
		adminAddMsg.textContent = text;
	}

	adminAddSubmit.addEventListener('click', function(){
		var employeeId = adminAddEmployee.value;
		var week = adminAddWeek.value;
		if(!employeeId){ showAdminAddMsg('error', 'الرجاء اختيار موظف.'); return; }
		if(!week){ showAdminAddMsg('error', 'الرجاء تحديد بداية الأسبوع.'); return; }

		var items = [];
		adminAddRows.querySelectorAll('.et-admin-add-row').forEach(function(row){
			var title = row.querySelector('input[type=text]').value.trim();
			if(!title) return;
			var selects = row.querySelectorAll('select');
			var due = row.querySelector('.et-admin-add-due');
			items.push({ title: title, task_day: selects[0].value, priority: selects[1].value, due_date: due ? due.value : '' });
		});
		if(!items.length){ showAdminAddMsg('error', 'الرجاء كتابة مهمة واحدة على الأقل.'); return; }

		adminAddMsg.hidden = true;
		adminAddSubmit.disabled = true;
		adminAddSubmit.textContent = 'جارِ الإضافة…';

		apiFetch('/admin/weekly-tasks/create', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({
				token: adminState.token,
				employee_id: employeeId,
				week_start_date: week,
				tasks: items,
				notify_employee: adminAddNotify.checked ? '1' : '0'
			})
		}).then(function(data){
			var msg = 'تمت إضافة ' + data.created + ' مهمة بنجاح.';
			if(adminAddNotify.checked){ msg += data.email_sent ? ' وتم إرسال بريد الإشعار للموظف.' : ' لكن تعذر إرسال بريد الإشعار.'; }
			showAdminAddMsg('info', msg);
			adminAddRows.innerHTML = '';
			addAdminTaskRow();
			adminAddEmployeeInput.value = '';
			adminAddEmployee.value = '';
			loadAdminTasks();
		}).catch(function(err){
			showAdminAddMsg('error', typeof err === 'string' ? err : 'تعذر إضافة المهام، حاول مجددًا.');
		}).then(function(){
			adminAddSubmit.disabled = false;
			adminAddSubmit.textContent = 'إضافة المهام';
		});
	});

	form.querySelectorAll('input[name="has_problem"]').forEach(function(radio){
		radio.addEventListener('change', function(){
			document.getElementById('et-problem-fields').hidden = (this.value !== '1');
		});
	});
	form.querySelectorAll('input[name="assistance_required"]').forEach(function(radio){
		radio.addEventListener('change', function(){
			document.getElementById('et-assistance-field').hidden = (this.value !== '1');
		});
	});

	var fileInputEl = document.getElementById('et-file-input');
	var fileListEl = document.getElementById('et-file-list');

	/** مفتاح تفرّد بسيط للملف (اسم + حجم + تاريخ تعديل) لتفادي تكرار نفس الملف مرتين لو اختير بالخطأ مرة ثانية */
	function fileKey(f){ return f.name + '|' + f.size + '|' + (f.lastModified || 0); }

	function renderFileList(){
		fileListEl.innerHTML = '';
		state.selectedFiles.forEach(function(f, idx){
			var chip = document.createElement('div');
			chip.className = 'et-file-chip';
			var nameEl = document.createElement('span');
			nameEl.textContent = f.name;
			var removeBtn = document.createElement('button');
			removeBtn.type = 'button';
			removeBtn.title = 'إزالة هذا الملف';
			removeBtn.textContent = '✕';
			removeBtn.addEventListener('click', function(){
				state.selectedFiles.splice(idx, 1);
				renderFileList();
			});
			chip.appendChild(nameEl);
			chip.appendChild(removeBtn);
			fileListEl.appendChild(chip);
		});
	}

	/** كل اختيار جديد يُضاف لقائمتنا الخاصة (state.selectedFiles) بدل استبدالها — لأن حقل type=file نفسه
	 *  يستبدل ملفاته المختارة بالكامل مع كل ضغطة "اختيار ملفات"، وهذا بالضبط ما كان يجعل الموظف يقدر
	 *  يضيف ملفًا واحدًا فقط: كل ضغطة ثانية على الزر كانت تمحو اختيار الملف الأول تلقائيًا. */
	fileInputEl.addEventListener('change', function(){
		var existingKeys = state.selectedFiles.map(fileKey);
		Array.prototype.forEach.call(this.files, function(f){
			var key = fileKey(f);
			if(existingKeys.indexOf(key) === -1){ state.selectedFiles.push(f); existingKeys.push(key); }
		});
		fileInputEl.value = ''; // نُفرغ الحقل حتى تفتح ضغطة "اختيار ملفات" التالية حوارًا نظيفًا وتُضاف نتيجتها فوق القائمة
		renderFileList();
	});

	form.addEventListener('submit', function(e){
		e.preventDefault();
		formError.hidden = true;

		if(!state.token){
			formError.hidden = false;
			formError.textContent = 'الرجاء اختيار اسمك أولًا.';
			return;
		}

		var fd = new FormData(form);
		fd.append('token', state.token);
		state.selectedFiles.forEach(function(f){ fd.append('attachments[]', f, f.name); });

		submitBtn.disabled = true;
		submitBtn.textContent = 'جارِ الإرسال…';

		apiFetch('/submit', { method: 'POST', body: fd }).then(function(){
			state.selectedFiles = [];
			renderFileList();
			showView('success');
		}).catch(function(err){
			formError.hidden = false;
			formError.textContent = typeof err === 'string' ? err : 'تعذر إرسال التحديث، حاول مجددًا.';
		}).finally(function(){
			submitBtn.disabled = false;
			submitBtn.textContent = 'إرسال التحديث';
		});
	});
})();
