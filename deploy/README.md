# النشر على سيرفر المعهد (XAMPP)

## 1) قاعدة البيانات (MySQL الخاصة بـ XAMPP)
- من phpMyAdmin أنشئ قاعدة `employee_tracker` بترميز `utf8mb4_unicode_ci`.
- من User accounts أنشئ مستخدم `tracker_app` (Host: `localhost`) بكلمة مرور قوية، وأعطه كل الصلاحيات على `employee_tracker` فقط.
- ضع كلمة مرور لمستخدم `root`، ولا تفتح منفذ 3306 في جدار الحماية (القاعدة للسيرفر نفسه فقط).
- خذ نسخة احتياطية يومية (`mysqldump`) واحفظها خارج السيرفر.

## 2) سيرفر المعهد (XAMPP)
1. انسخ مجلد المشروع إلى `C:\xampp\htdocs\employee-tracker`.
2. انسخ `deploy/env.production.example` إلى جذر المشروع باسم `.env` وعبّئ القيم، ثم:
   ```
   C:\xampp\php\php.exe artisan key:generate
   C:\xampp\php\php.exe artisan tracker:install
   C:\xampp\php\php.exe artisan config:cache
   C:\xampp\php\php.exe artisan route:cache
   ```
3. أضف محتوى `deploy/apache-vhost.conf` لإعدادات Apache وأعد تشغيله.
4. شغّل `deploy/setup-scheduler.bat` كمسؤول (التذكيرات التلقائية).
5. في XAMPP Control Panel ← Config: فعّل تشغيل Apache و MySQL تلقائيًا مع الجهاز.

## 3) HTTPS (ضروري للحضور بالـ GPS)
- الأفضل: دومين فرعي (مثل `tracker.alrowad.edu.sa`) يشير إلى IP المعهد، مع شهادة Let's Encrypt (مثلًا عبر win-acme).
- للاستخدام داخل الشبكة فقط: شهادة داخلية من المعهد مثبّتة على أجهزة الموظفين.

## 4) بعد التشغيل
- لوحة الإدارة ← الإعدادات ← الحضور: أضف IP شبكة المعهد في "عناوين IP المسموحة للحضور".
