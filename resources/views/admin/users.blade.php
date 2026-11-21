<div class="wrap et-wrap">
  @if (session('status'))
    <div class="notice notice-success"><p>{{ session('status') }}</p></div>
  @endif
  @if ($errors->any())
    <div class="notice notice-error"><p>{{ $errors->first() }}</p></div>
  @endif

  <h1 class="et-h1">المديرون</h1>
  <p class="et-sub">كل مدير يدخل ببريده وكلمة مروره، في هذه اللوحة وفي تبويب "🔐 دخول الإدارة" بصفحة الموظفين.</p>

  <div class="et-panel">
    <h2>إضافة مدير</h2>
    <form method="post" action="{{ url('/admin/et-admins') }}">
      <input type="hidden" name="_token" value="{{ csrf_token() }}">
      <div class="et-form-grid">
        <div><label for="adm-name">الاسم *</label><input id="adm-name" type="text" name="name" value="{{ old('name') }}" required></div>
        <div><label for="adm-email">البريد الإلكتروني *</label><input id="adm-email" type="email" name="email" dir="ltr" value="{{ old('email') }}" required autocomplete="off"></div>
        <div><label for="adm-pass">كلمة المرور * (8 أحرف على الأقل)</label><input id="adm-pass" type="password" name="password" dir="ltr" minlength="8" required autocomplete="new-password"></div>
        <div><label for="adm-pass2">تأكيد كلمة المرور *</label><input id="adm-pass2" type="password" name="password_confirmation" dir="ltr" minlength="8" required autocomplete="new-password"></div>
      </div>
      <p style="margin-top:14px;"><button type="submit" class="button button-primary">إضافة المدير</button></p>
    </form>
  </div>

  <div class="et-panel">
    <h2>الحسابات الحالية</h2>
    <table class="et-table">
      <thead><tr><th>الاسم</th><th>البريد (الدخول)</th><th>أُضيف في</th><th>تغيير كلمة المرور</th><th></th></tr></thead>
      <tbody>
      @foreach ($users as $u)
        <tr>
          <td>{{ $u->name }} @if ($u->id === $me)<span class="et-pill" style="background:#3C31911A;color:#3C3191;border-color:#3C319140;">أنت</span>@endif</td>
          <td dir="ltr" style="text-align:right;">{{ $u->email }}</td>
          <td>{{ optional($u->created_at)->format('Y-m-d') }}</td>
          <td>
            <form method="post" action="{{ url('/admin/et-admins/'.$u->id.'/password') }}" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
              <input type="hidden" name="_token" value="{{ csrf_token() }}">
              <input type="password" name="password" dir="ltr" minlength="8" required placeholder="كلمة مرور جديدة" autocomplete="new-password" aria-label="كلمة مرور جديدة لـ {{ $u->name }}" style="padding:6px 9px;border:1px solid #E4E7EC;border-radius:8px;width:150px;">
              <input type="password" name="password_confirmation" dir="ltr" minlength="8" required placeholder="تأكيدها" autocomplete="new-password" aria-label="تأكيد كلمة المرور" style="padding:6px 9px;border:1px solid #E4E7EC;border-radius:8px;width:120px;">
              <button type="submit" class="button">حفظ</button>
            </form>
          </td>
          <td>
            @if ($u->id !== $me)
              <form method="post" action="{{ url('/admin/et-admins/'.$u->id.'/delete') }}" onsubmit="return confirm(@js('حذف حساب '.$u->name.'؟ لن يستطيع الدخول بعدها.'));">
                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                <button type="submit" class="button-link">حذف</button>
              </form>
            @endif
          </td>
        </tr>
      @endforeach
      </tbody>
    </table>
  </div>
</div>
