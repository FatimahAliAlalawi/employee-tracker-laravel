<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * ينقل بيانات البلجن من قاعدة ووردبريس القديمة إلى هذا النظام:
 *   php artisan tracker:import-wordpress --uploads=/path/to/wp-content/uploads --old-url=https://old-site.com
 * إعدادات الاتصال من .env: WP_DB_HOST / WP_DB_DATABASE / WP_DB_USERNAME / WP_DB_PASSWORD / WP_DB_PREFIX
 */
class ImportWordPress extends Command
{
    protected $signature = 'tracker:import-wordpress
        {--uploads= : مسار مجلد wp-content/uploads لنسخ المرفقات المحمية}
        {--old-url= : رابط موقع ووردبريس القديم لتحديث روابط المرفقات إلى الرابط الجديد}
        {--fresh : حذف بيانات النظام الحالية قبل الاستيراد}';

    protected $description = 'استيراد كل بيانات نظام المتابعة من قاعدة ووردبريس (الجداول + الإعدادات + المرفقات)';

    private const TABLES = [
        'departments', 'employees', 'daily_updates', 'problems', 'problem_logs', 'weekly_tasks', 'task_comments',
        'task_updates', 'task_members', 'projects', 'task_checklist', 'task_attachments', 'help_requests',
        'notifications', 'audit_log', 'update_comments', 'leave_balances', 'leave_requests', 'sick_leave_requests',
        'permission_requests', 'attendance_records', 'attendance_audit_logs',
    ];

    public function handle(): int
    {
        config(['database.connections.wordpress' => [
            'driver'    => 'mysql',
            'host'      => env('WP_DB_HOST', '127.0.0.1'),
            'port'      => env('WP_DB_PORT', '3306'),
            'database'  => env('WP_DB_DATABASE'),
            'username'  => env('WP_DB_USERNAME'),
            'password'  => env('WP_DB_PASSWORD', ''),
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'    => '',
            'strict'    => false,
        ]]);
        $wp = DB::connection('wordpress');
        $wpPrefix = (string) env('WP_DB_PREFIX', 'wp_');

        try {
            $wp->getPdo();
        } catch (\Throwable $e) {
            $this->error('تعذّر الاتصال بقاعدة ووردبريس: ' . $e->getMessage());
            return self::FAILURE;
        }

        foreach (self::TABLES as $name) {
            $from = $wpPrefix . 'et_' . $name;
            $to = \ET_Database::table($name);
            if (! $wp->getSchemaBuilder()->hasTable($from)) {
                $this->warn("تخطي {$from} (غير موجود)");
                continue;
            }
            if ($this->option('fresh')) {
                DB::table($to)->delete();
            }
            $columns = Schema::getColumnListing($to);
            $count = 0;
            $orderBy = $name === 'leave_balances' ? 'employee_id' : 'id';
            $wp->table($from)->orderBy($orderBy)->chunk(500, function ($rows) use ($to, $columns, &$count) {
                $batch = [];
                foreach ($rows as $row) {
                    $batch[] = array_intersect_key((array) $row, array_flip($columns));
                }
                DB::table($to)->insertOrIgnore($batch);
                $count += count($batch);
            });
            $this->line("✓ {$name}: {$count}");
        }

        // الإعدادات والأسرار (نفس التوكنات ⇒ لا حاجة لإعادة إصدار رموز الموظفين)
        $options = $wp->table($wpPrefix . 'options')
            ->where('option_name', 'like', 'et\_%')
            ->where('option_name', 'not like', 'et\_pwa%')
            ->get(['option_name', 'option_value']);
        foreach ($options as $opt) {
            $value = maybe_unserialize($opt->option_value);
            if ($opt->option_name === 'et_settings' && is_array($value)) {
                $value = array_filter($value, fn ($k) => ! str_starts_with((string) $k, 'pwa_'), ARRAY_FILTER_USE_KEY);
            }
            update_option($opt->option_name, $value);
        }
        $this->line('✓ الإعدادات: ' . $options->count());

        // المرفقات المحمية
        if ($uploads = $this->option('uploads')) {
            $privateName = get_option('et_private_dir');
            if ($privateName && is_dir($uploads . '/' . $privateName)) {
                File::copyDirectory($uploads . '/' . $privateName, \ET_Files::base_dir());
                $this->line('✓ نُسخت المرفقات المحمية');
            }
            $legacyName = get_option('et_legacy_uploads_dir');
            if ($legacyName && is_dir($uploads . '/' . $legacyName)) {
                File::copyDirectory($uploads . '/' . $legacyName, (string) config('tracker.legacy_storage'));
                $this->line('✓ نُسخت المرفقات القديمة');
            }
        }

        // تحديث روابط المرفقات المحفوظة إلى نطاق هذا النظام
        if ($oldUrl = rtrim((string) $this->option('old-url'), '/')) {
            $newUrl = rtrim(url('/'), '/');
            $targets = [['help_requests', 'attachment_url'], ['sick_leave_requests', 'attachment_url'],
                ['task_attachments', 'url'], ['daily_updates', 'attachments'], ['notifications', 'message']];
            foreach ($targets as [$table, $column]) {
                $t = \ET_Database::table($table);
                foreach ([[$oldUrl . '/', $newUrl . '/'], [str_replace('/', '\\/', $oldUrl . '/'), str_replace('/', '\\/', $newUrl . '/')]] as [$from, $to]) {
                    DB::update("UPDATE `{$t}` SET `{$column}` = REPLACE(`{$column}`, ?, ?) WHERE `{$column}` LIKE ?", [$from, $to, '%' . $from . '%']);
                }
            }
            $this->line("✓ حُدّثت روابط المرفقات: {$oldUrl} ⇒ {$newUrl}");
        }

        $this->info('اكتمل الاستيراد.');
        return self::SUCCESS;
    }
}
