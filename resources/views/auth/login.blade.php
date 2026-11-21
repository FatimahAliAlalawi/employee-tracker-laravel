<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>تسجيل دخول الإدارة — {{ $systemName }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@700;800&family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;}
body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#F5F6F8;font-family:'Tajawal','Cairo',system-ui,sans-serif;color:#1B2532;padding:16px;}
.card{background:#fff;border:1px solid #E4E7EC;border-radius:16px;padding:30px 28px;width:100%;max-width:400px;box-shadow:0 6px 24px rgba(60,49,145,.06);}
h1{font-family:'Cairo',sans-serif;color:#3C3191;font-size:21px;margin:0 0 4px;}
p.sub{color:#6B7684;font-size:13px;margin:0 0 20px;}
label{display:block;font-size:12.5px;font-weight:700;color:#3C3191;margin:0 0 5px;}
input[type=email],input[type=password]{width:100%;padding:11px 12px;border:1px solid #E4E7EC;border-radius:10px;font:inherit;margin-bottom:14px;}
input:focus{outline:2px solid #6488C9;border-color:transparent;}
.remember{display:flex;gap:6px;align-items:center;font-size:13px;color:#6B7684;margin-bottom:16px;font-weight:500;}
button{width:100%;padding:12px;border:0;border-radius:10px;background:#3C3191;color:#fff;font:inherit;font-weight:700;font-size:15px;cursor:pointer;}
button:hover{background:#4E42B8;}
.err{background:#fde8e8;color:#B3261E;border-radius:8px;padding:9px 12px;font-size:13px;margin-bottom:14px;}
.back{display:block;text-align:center;margin-top:16px;font-size:13px;color:#3C3191;}
</style>
</head>
<body>
<form class="card" method="post" action="{{ route('login') }}">
  <input type="hidden" name="_token" value="{{ csrf_token() }}">
  <h1>🔐 لوحة الإدارة</h1>
  <p class="sub">{{ $systemName }} — سجّل دخولك بحساب المدير</p>
  @if ($errors->any())
    <div class="err">{{ $errors->first() }}</div>
  @endif
  <label for="email">البريد الإلكتروني</label>
  <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
  <label for="password">كلمة المرور</label>
  <input id="password" type="password" name="password" required autocomplete="current-password">
  <label class="remember"><input type="checkbox" name="remember" value="1"> تذكّرني</label>
  <button type="submit">دخول</button>
  <a class="back" href="{{ url('/') }}">‹ صفحة الموظفين</a>
</form>
</body>
</html>
