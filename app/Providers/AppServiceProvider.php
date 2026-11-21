<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // $wpdb الذي يستخدمه منطق النظام — مبني على اتصال Laravel (لا يفتح اتصالًا حتى أول استعلام)
        $GLOBALS['wpdb'] = new \Wpdb();

        $proxyHeader = (string) config('tracker.trusted_proxy_header', '');
        if ($proxyHeader !== '' && ! defined('ET_TRUSTED_PROXY_HEADER')) {
            define('ET_TRUSTED_PROXY_HEADER', $proxyHeader);
        }

        $this->registerTrackerHooks();
    }

    /** نفس خطافات البلجن: مهام الخلفية + فحص التذكيرات الدوري */
    private function registerTrackerHooks(): void
    {
        add_action('et_bg_whatsapp_admin', function ($args) {
            if (! empty($args['message'])) {
                \ET_WhatsApp::notify_admin($args['message'], true);
            }
        });
        add_action('et_bg_email_admin', function ($args) {
            if (! empty($args['message'])) {
                \ET_EmailNotify::notify_admin($args['message'], false, $args['subject'] ?? null);
            }
        });
        add_action('et_bg_email', function ($args) {
            if (! empty($args['to']) && ! empty($args['message'])) {
                \ET_EmailNotify::send($args['to'], $args['subject'] ?? '', $args['message'], false);
            }
        });

        // كل 15 دقيقة (مجدول Laravel أو رابط الكرون الخارجي /cron/tick):
        // تذكير صباحي/مسائي لمن لم يرسل تحديثه + تذكير مهام الأمس غير المنجزة
        add_action('et_daily_reminder_cron', function () {
            update_option('et_last_cron_tick', time());

            $settings = \ET_Settings::get();
            $now      = current_time('H:i');
            $today    = current_time('Y-m-d');

            // نسجّل "تم الإرسال اليوم" قبل بدء الإرسال لا بعده، حتى لا يتكرر التذكير لو انقطع التنفيذ
            if (function_exists('set_time_limit')) {
                @set_time_limit(300);
            }
            ignore_user_abort(true);

            $morning_time = et_normalize_hhmm($settings['reminder_time'] ?? '13:30');
            if ($morning_time !== '' && $now >= $morning_time && get_option('et_last_reminder_morning_date', '') !== $today) {
                update_option('et_last_reminder_morning_date', $today);
                do_action('et_reminder_time_reached', \ET_Updates::reminder_recipients('morning'), 'morning');
            }

            $evening_time = et_normalize_hhmm($settings['evening_reminder_time'] ?? '');
            if ($evening_time !== '' && $now >= $evening_time && get_option('et_last_reminder_evening_date', '') !== $today) {
                update_option('et_last_reminder_evening_date', $today);
                do_action('et_reminder_time_reached', \ET_Updates::reminder_recipients('evening'), 'evening');
            }

            $task_reminder_time = et_normalize_hhmm($settings['task_reminder_time'] ?? '08:00');
            if (! empty($settings['task_reminder_enabled']) && $task_reminder_time !== '' && $now >= $task_reminder_time
                && get_option('et_last_task_reminder_date', '') !== $today) {
                update_option('et_last_task_reminder_date', $today);
                $grouped = \ET_WeeklyTasks::incomplete_by_employee_as_of(\ET_Time::date('Y-m-d', '-1 day'));
                if (! empty($grouped)) {
                    et_send_task_reminders($grouped, $settings['task_reminder_subject'] ?? '');
                }
            }
        });

        add_action('et_reminder_time_reached', function ($not_submitted, $shift = null) {
            $settings = \ET_Settings::get();
            if (empty($settings['employee_reminder_enabled']) || empty($not_submitted)) {
                return;
            }
            et_send_employee_reminders($not_submitted, $settings['employee_reminder_subject'] ?? '');
        }, 10, 2);
    }
}
