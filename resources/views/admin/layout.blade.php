<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title }} — {{ $systemName }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@600;700;800&family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
<style>
:root{--et-surface:#fff;--et-navy:#3C3191;--et-gold:#6488C9;--et-line:#E4E7EC;--et-muted:#6B7684;--et-ink:#1B2532;}
*{box-sizing:border-box;}
html,body{margin:0;background:#F0F2F5;color:var(--et-ink);font-family:'Tajawal','Cairo',system-ui,-apple-system,sans-serif;font-size:14px;}
a{color:var(--et-navy);}
.adm-shell{display:flex;min-height:100vh;}
.adm-side{width:230px;flex-shrink:0;background:var(--et-navy);color:#fff;display:flex;flex-direction:column;position:sticky;top:0;height:100vh;}
.adm-brand{padding:20px 18px;font-family:'Cairo',sans-serif;font-weight:800;font-size:18px;border-bottom:1px solid rgba(255,255,255,.12);}
.adm-brand small{display:block;font-weight:500;font-size:11.5px;opacity:.75;margin-top:2px;}
.adm-nav{flex:1;padding:10px 8px;overflow:auto;}
.adm-nav a{display:block;color:#fff;text-decoration:none;padding:10px 12px;border-radius:9px;margin-bottom:2px;font-weight:600;opacity:.9;}
.adm-nav a:hover{background:rgba(255,255,255,.08);opacity:1;}
.adm-nav a.active{background:var(--et-gold);opacity:1;}
.adm-foot{padding:12px 14px;border-top:1px solid rgba(255,255,255,.12);font-size:12.5px;}
.adm-foot form{margin:6px 0 0;}
.adm-foot button,.adm-foot a{background:none;border:0;color:#fff;cursor:pointer;font:inherit;padding:0;text-decoration:underline;opacity:.85;}
.adm-main{flex:1;min-width:0;padding:8px 26px 40px;}
/* بدائل أنماط لوحة ووردبريس التي تستخدمها صفحات النظام */
.wrap{margin:10px 0;}
.button{display:inline-block;padding:7px 14px;border:1px solid var(--et-navy);border-radius:8px;background:#fff;color:var(--et-navy);font:inherit;font-size:13px;font-weight:700;cursor:pointer;text-decoration:none;line-height:1.4;}
.button:hover{background:#F5F4FC;}
.button-primary{background:var(--et-navy);color:#fff;}
.button-primary:hover{background:#4E42B8;}
.button-secondary{border-color:#C9CDD3;color:var(--et-ink);}
.button-link{background:none;border:0;color:#B3261E;cursor:pointer;font:inherit;text-decoration:underline;padding:0;}
.notice{background:#fff;border:1px solid var(--et-line);border-inline-start:4px solid #72aee6;border-radius:8px;padding:2px 14px;margin:12px 0;}
.notice p{margin:9px 0;}
.notice-success{border-inline-start-color:#1F9D7C;}
.notice-error{border-inline-start-color:#D6483F;}
.notice-warning{border-inline-start-color:#E2A93B;}
.notice-info{border-inline-start-color:#6488C9;}
.description{color:var(--et-muted);font-size:12px;}
input[type=text],input[type=email],input[type=number],input[type=date],input[type=time],input[type=password],input[type=url],select,textarea{font:inherit;}
code{background:#EEF0F3;padding:1px 6px;border-radius:5px;}
@media (max-width:820px){
  .adm-shell{flex-direction:column;}
  .adm-side{width:auto;height:auto;position:static;}
  .adm-nav{display:flex;flex-wrap:wrap;gap:4px;}
  .adm-nav a{margin:0;padding:8px 10px;font-size:13px;}
  .adm-main{padding:6px 12px 30px;}
  .et-form-grid,.et-detail-grid{grid-template-columns:1fr!important;}
  .et-table{display:block;overflow-x:auto;}
}
</style>
@include('admin.styles')
</head>
<body>
<div class="adm-shell">
  <aside class="adm-side">
    <div class="adm-brand">{{ $systemName }}<small>لوحة الإدارة</small></div>
    <nav class="adm-nav">
      @foreach ($menu as $slug => $label)
        <a href="{{ url('/admin/'.$slug) }}" class="{{ $slug === $page ? 'active' : '' }}">{{ $label }}</a>
      @endforeach
      <a href="{{ url('/') }}" target="_blank" rel="noopener">↗ صفحة الموظفين</a>
    </nav>
    <div class="adm-foot">
      {{ auth()->user()->name }}
      <form method="post" action="{{ route('logout') }}">
        <input type="hidden" name="_token" value="{{ csrf_token() }}">
        <button type="submit">تسجيل الخروج</button>
      </form>
    </div>
  </aside>
  <main class="adm-main">
    {!! $html !!}
  </main>
</div>
</body>
</html>
