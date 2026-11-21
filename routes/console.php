<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
| المجدول: أضف سطر كرون واحد على السيرفر:
|   * * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
| (أو استخدم رابط الكرون الخارجي الظاهر في صفحة الإعدادات كبديل)
*/
Schedule::call(function () {
    \ET_Database::maybe_upgrade();
    do_action('et_daily_reminder_cron');
})->name('tracker-reminders')->everyFifteenMinutes()->withoutOverlapping();

Artisan::command('tracker:install', function () {
    $this->call('migrate', ['--force' => true]);
    \ET_Database::activate();
    \ET_Database::maybe_upgrade();
    $this->info('تم تجهيز قاعدة البيانات والإعدادات الافتراضية.');
    if (User::count() === 0) {
        $this->call('tracker:create-admin');
    }
})->purpose('تثبيت النظام: الجداول + الإعدادات الافتراضية + أول حساب مدير');

Artisan::command('tracker:create-admin {email?} {--name=}', function (?string $email = null) {
    $email = $email ?: $this->ask('البريد الإلكتروني للمدير');
    $name = $this->option('name') ?: $this->ask('الاسم', 'مدير النظام');
    $password = $this->secret('كلمة المرور (8 أحرف على الأقل)');
    if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen((string) $password) < 8) {
        $this->error('بريد غير صالح أو كلمة مرور قصيرة.');
        return 1;
    }
    User::updateOrCreate(['email' => $email], ['name' => $name, 'password' => $password]);
    $this->info('تم حفظ حساب المدير: ' . $email);
    return 0;
})->purpose('إنشاء/تحديث حساب مدير للوحة الإدارة');

Artisan::command('tracker:reminders', function () {
    do_action('et_daily_reminder_cron');
    $this->info('تم تشغيل فحص التذكيرات.');
})->purpose('تشغيل فحص التذكيرات يدويًا (نفس ما يشغّله المجدول كل 15 دقيقة)');
