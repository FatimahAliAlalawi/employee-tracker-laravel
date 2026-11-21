<?php

namespace App\Support;

/**
 * يوقف تنفيذ صفحة إدارة مبكرًا (بدل exit في الكود الأصلي) مع الإبقاء على ما طُبع حتى الآن،
 * ليعرضه AdminController داخل القالب — مثل بطاقة "رمز دخول لوحة المتابعة".
 */
class AdminPageHalt extends \RuntimeException
{
}
