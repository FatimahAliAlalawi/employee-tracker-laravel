<?php

/*
| واجهة REST للموظف وتبويب الإدارة: /api/employee-tracker/v1/...
| المسارات تُسجَّل من نفس قائمة register_routes() في ET_REST_API (82 مسارًا)، و register_rest_route
| هنا يحوّل كل تعريف إلى مسار Laravel يمر عبر RestController.
| الحماية داخل كل دالة (توكن الموظف / توكن جلسة الإدارة) تمامًا كما في النسخة الأصلية.
*/

\ET_REST_API::instance()->register_routes();
