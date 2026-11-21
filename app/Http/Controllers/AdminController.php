<?php

namespace App\Http\Controllers;

use App\Support\AdminPageHalt;
use Illuminate\Http\Request;

/**
 * لوحة الإدارة (بديل صفحات "متابعة" داخل wp-admin). كل صفحة تُرسم بنفس دوال ET_Admin الأصلية
 * (page_dashboard, page_employees ...) وتُعرض داخل قالب Laravel بشريط جانبي.
 */
class AdminController extends Controller
{
    public const PAGES = [
        'employee-tracker' => ['لوحة المتابعة', 'page_dashboard'],
        'et-updates'       => ['التحديثات اليومية', 'page_updates'],
        'et-problems'      => ['المشاكل', 'page_problems'],
        'et-employees'     => ['الموظفون', 'page_employees'],
        'et-departments'   => ['الأقسام', 'page_departments'],
        'et-weekly-tasks'  => ['المهام الأسبوعية', 'page_weekly_tasks'],
        'et-reports'       => ['التقارير', 'page_reports'],
        'et-settings'      => ['الإعدادات', 'page_settings'],
    ];

    public function show(Request $request, string $page = 'employee-tracker')
    {
        abort_unless(isset(self::PAGES[$page]), 404);

        // الكود الأصلي يقرأ اسم الصفحة من $_GET['page'] كما في wp-admin/admin.php?page=...
        $_GET['page'] = $page;
        $_REQUEST['page'] = $page;

        $admin = \ET_Admin::instance();
        $admin->handle_export(); // يرسل ملف CSV (استثناء) عند طلب تصدير

        [$title, $method] = self::PAGES[$page];

        $level = ob_get_level();
        ob_start();
        try {
            $admin->{$method}();
        } catch (AdminPageHalt $e) {
            // توقف مقصود (مثل بطاقة رمز الدخول) — نعرض ما طُبع حتى الآن
        } catch (\Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $e;
        }
        $html = '';
        while (ob_get_level() > $level) {
            $html = ob_get_clean() . $html;
        }

        return self::render($html, $page, $title);
    }

    /** قائمة الشريط الجانبي: صفحات النظام + صفحة حسابات المديرين */
    public static function menu(): array
    {
        $menu = array_map(fn ($p) => $p[0], self::PAGES);
        $menu['et-admins'] = 'المديرون';

        return $menu;
    }

    /** يعرض محتوى صفحة داخل قالب لوحة الإدارة */
    public static function render(string $html, string $page, string $title)
    {
        return response()->view('admin.layout', [
            'html'       => $html,
            'page'       => $page,
            'title'      => $title,
            'systemName' => (string) \ET_Settings::get()['system_name'],
            'menu'       => self::menu(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }
}
