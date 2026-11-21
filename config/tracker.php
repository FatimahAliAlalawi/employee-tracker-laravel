<?php

return [
    // المنطقة الزمنية للنظام (تحل محل "توقيت الموقع" في ووردبريس). كل حسابات الحضور والتأخير تعتمد عليها.
    'timezone' => env('TRACKER_TIMEZONE', 'Asia/Riyadh'),

    // بادئة أسماء الجداول. الافتراضي "" ⇒ et_employees, et_departments ...
    // لو نقلت جداول ووردبريس كما هي (wp_et_*) ضع هنا: wp_
    'table_prefix' => env('TRACKER_TABLE_PREFIX', ''),

    // خلف Cloudflare/بروكسي: اسم متغير الخادم الذي يحمل IP الزائر الحقيقي (مثال HTTP_CF_CONNECTING_IP).
    'trusted_proxy_header' => env('TRACKER_TRUSTED_PROXY_HEADER', ''),

    // مجلد المرفقات المحمية (خارج public — لا يمكن فتحه إلا عبر فحص الصلاحية).
    'private_storage' => storage_path('app/private/et-files'),

    // مجلد المرفقات القديمة المنقولة من ووردبريس (اختياري، للملفات التي تبدأ روابطها بـ legacy/).
    'legacy_storage' => storage_path('app/private/et-legacy'),

    // اسم الموقع المستخدم كبديل get_bloginfo('name')
    'site_name' => env('APP_NAME', 'متابعة'),
];
