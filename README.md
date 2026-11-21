# نظام متابعة | Employee Work Tracker — نسخة Laravel

تحويل كامل لبلجن ووردبريس `employee-tracker` (الإصدار 2.9.1) إلى تطبيق **Laravel 12 مستقل** لا يحتاج ووردبريس إطلاقًا،
مع **إزالة طبقة التطبيق (PWA)** بالكامل.

## ما الذي تغيّر؟

| في ووردبريس | في Laravel |
|---|---|
| الشورت كود `[employee_tracker]` | الصفحة الرئيسية `/` |
| `wp-json/employee-tracker/v1/...` | `api/employee-tracker/v1/...` (نفس المسارات الـ 82، ونفس صيغة الردود) |
| صفحات "متابعة" داخل `wp-admin` | `/admin/...` بشريط جانبي (لوحة المتابعة، التحديثات، المشاكل، الموظفون، الأقسام، المهام الأسبوعية، التقارير، الإعدادات) |
| تسجيل دخول ووردبريس للمدير | حسابات مديرين في جدول `users` (`/login`) |
| `dbDelta` عند التفعيل | `php artisan migrate` |
| WP-Cron كل 15 دقيقة | مجدول Laravel (`schedule:run`) + رابط الكرون الخارجي كما هو |
| `wp_mail` | Laravel Mail (إعدادات `MAIL_*` في `.env`) |
| `wp_remote_post` (واتساب) | Laravel HTTP Client |
| مجلد مرفقات عشوائي داخل `uploads` | `storage/app/private/et-files` (خارج `public`، لا يُفتح إلا بعد فحص الصلاحية) |
| خيارات `wp_options` | جدول `et_options` |

**تمت إزالتها (طبقة التطبيق PWA):** ملف Manifest، الـ Service Worker، زر "تثبيت التطبيق"، شاشة التحميل (Splash)،
تنبيه "يتوفر تحديث"، تنبيه "دون اتصال"، شريط التنقل السفلي للجوال، نافذة تعليمات آيفون، إشعارات Web Push،
جدول `push_subscriptions`، صفحة إعدادات PWA، ومكتبة `minishlink/web-push`. على الجوال تتحول القائمة الجانبية
إلى صف أفقي أعلى المحتوى بدل الشريط السفلي. مركز الإشعارات الداخلي (🔔) والبريد وواتساب تعمل كما هي.

**لم يتغيّر:** كل منطق العمل — الحضور بالـ GPS والتأخير وساعات العمل، الإجازات والمرضية والاستئذانات، المهام
الأسبوعية والجماعية والمشاريع والـ Kanban، الأداء الشهري، التذكيرات، رموز الدخول المشفّرة والقفل المؤقت، التصدير CSV.

## البنية

```
app/Tracker/          كلاسات منطق العمل (ET_Employees, ET_Attendance, ET_REST_API, ET_Admin ...) منقولة كما هي
app/Support/helpers.php   طبقة توافق: دوال بنفس أسماء دوال ووردبريس لكن منفّذة فوق Laravel (DB/Cache/Mail/Auth)
app/Support/Compat/   Wpdb (فوق اتصال Laravel)، WP_Error، WP_REST_Request
app/Http/Controllers/ AppController (صفحة الموظفين + المرفقات)، RestController، AdminController، AuthController
resources/views/      tracker/app.php (صفحة الموظفين)، admin/layout.blade.php، auth/login.blade.php
public/tracker/       tracker.css و tracker.js (واجهة الموظف وتبويب الإدارة)
database/migrations/  كل جداول النظام
routes/               web.php، api.php، console.php (المجدول + أوامر التثبيت)
```

> لماذا طبقة توافق بدل إعادة كتابة بـ Eloquent؟ منطق العمل 17 ألف سطر فيه حسابات حساسة (التأخير، أرصدة الإجازات،
> الأداء). نقله حرفيًا يضمن نفس النتائج تمامًا، بينما كل ما يخص البنية (المسارات، الجلسات، الجداول، البريد،
> المجدول، الملفات، الدخول) صار Laravel أصيلًا. يمكن لاحقًا تحويل أي كلاس إلى Eloquent تدريجيًا دون كسر الباقي.

## التثبيت

المتطلبات: PHP 8.2+ (مع `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`) و Composer و MySQL/MariaDB.

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
# عدّل DB_* و MAIL_* و TRACKER_TIMEZONE في .env
php artisan tracker:install        # الجداول + الإعدادات الافتراضية + أول حساب مدير
```

اجعل جذر الموقع (Document Root) يشير إلى مجلد `public/`، ثم أضف كرون واحد على السيرفر:

```
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

- صفحة الموظفين: `https://your-domain/`
- لوحة الإدارة: `https://your-domain/admin` (حساب إضافي: `php artisan tracker:create-admin`)

## نقل البيانات من ووردبريس

1. عبّئ `WP_DB_*` في `.env` ببيانات قاعدة ووردبريس القديمة.
2. شغّل:

```bash
php artisan tracker:import-wordpress --uploads=/path/to/wp-content/uploads --old-url=https://old-site.com
```

ينقل كل الجداول، والإعدادات، والسر المستخدم لتوقيع الجلسات (فتبقى رموز الموظفين ورمز الإدارة كما هي)، والمرفقات
المحمية، ويحدّث روابط المرفقات إلى النطاق الجديد. الخيار `--fresh` يمسح البيانات الحالية أولًا.

## أوامر مفيدة

```bash
php artisan tracker:reminders      # تشغيل فحص التذكيرات الآن
php artisan tracker:create-admin   # إضافة/تحديث حساب مدير
php artisan route:list --path=api  # كل مسارات الـ API
```
