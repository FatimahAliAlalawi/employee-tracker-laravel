<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جداول نظام المتابعة — نفس بنية جداول البلجن (wp_et_*) بالضبط، بدون جدول اشتراكات Web Push.
 * الأسماء: {TRACKER_TABLE_PREFIX}et_xxx (الافتراضي بلا بادئة).
 */
return new class extends Migration
{
    private function t(string $name): string
    {
        return config('tracker.table_prefix', '') . 'et_' . $name;
    }

    public function up(): void
    {
        Schema::create($this->t('options'), function (Blueprint $table) {
            $table->id();
            $table->string('option_name', 191)->unique();
            $table->longText('option_value')->nullable();
        });

        Schema::create($this->t('departments'), function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->string('manager_name', 191)->default('');
            $table->string('status', 20)->default('active')->index();
            $table->dateTime('created_at');
        });

        Schema::create($this->t('employees'), function (Blueprint $table) {
            $table->id();
            $table->string('name', 191)->index();
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->string('manager_name', 191)->default('');
            $table->string('phone', 50)->default('');
            $table->string('email', 191)->default('');
            $table->string('shift', 20)->default('both');
            $table->string('access_code', 255)->default('');
            $table->string('status', 20)->default('active')->index();
            $table->string('att_morning_start_override', 5)->nullable();
            $table->string('att_morning_end_override', 5)->nullable();
            $table->string('att_evening_start_override', 5)->nullable();
            $table->string('att_evening_end_override', 5)->nullable();
            $table->dateTime('created_at');
        });

        Schema::create($this->t('daily_updates'), function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->index();
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->date('update_date')->index();
            $table->text('achievements')->nullable();
            $table->text('current_tasks')->nullable();
            $table->boolean('has_problem')->default(0);
            $table->text('problem_description')->nullable();
            $table->string('problem_priority', 20)->nullable();
            $table->string('problem_impact', 50)->nullable();
            $table->boolean('assistance_required')->default(0);
            $table->text('assistance_description')->nullable();
            $table->text('tomorrow_plan')->nullable();
            $table->text('attachments')->nullable();
            $table->text('ai_summary')->nullable();
            $table->string('source', 20)->default('manual');
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
        });

        Schema::create($this->t('problems'), function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('update_id')->nullable();
            $table->unsignedBigInteger('employee_id')->index();
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->string('title', 255)->default('');
            $table->text('description')->nullable();
            $table->string('priority', 20)->default('medium')->index();
            $table->string('impact', 50)->nullable();
            $table->string('status', 20)->default('new')->index();
            $table->string('assigned_to', 191)->default('');
            $table->text('solution')->nullable();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
            $table->dateTime('resolved_at')->nullable();
        });

        Schema::create($this->t('problem_logs'), function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('problem_id')->index();
            $table->string('action', 100);
            $table->string('old_value', 255)->default('');
            $table->string('new_value', 255)->default('');
            $table->text('notes')->nullable();
            $table->dateTime('created_at');
        });

        Schema::create($this->t('weekly_tasks'), function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->index();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->date('week_start_date')->index();
            $table->string('task_day', 20)->nullable()->index();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('priority', 20)->default('medium')->index();
            $table->string('status', 20)->default('pending')->index();
            $table->dateTime('completed_at')->nullable();
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->unsignedBigInteger('project_id')->nullable()->index();
            $table->unsignedInteger('sequence_order')->nullable();
            $table->date('due_date')->nullable();
            $table->dateTime('created_at');
        });

        Schema::create($this->t('task_comments'), function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id')->index();
            $table->string('author', 50)->default('الإدارة');
            $table->text('message');
            $table->dateTime('created_at');
        });

        Schema::create($this->t('task_updates'), function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id')->index();
            $table->unsignedBigInteger('employee_id')->index();
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->string('work_status', 20)->default('in_progress');
            $table->text('achievements')->nullable();
            $table->text('current_work')->nullable();
            $table->boolean('has_problem')->default(0);
            $table->text('problem_description')->nullable();
            $table->boolean('needs_help')->default(0);
            $table->text('help_description')->nullable();
            $table->dateTime('created_at');
        });

        Schema::create($this->t('task_members'), function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id');
            $table->unsignedBigInteger('employee_id')->index();
            $table->string('role_label', 100)->default('');
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->dateTime('completion_notified_at')->nullable();
            $table->dateTime('created_at');
            $table->unique(['task_id', 'employee_id'], 'task_employee');
        });

        Schema::create($this->t('projects'), function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->dateTime('created_at');
        });

        Schema::create($this->t('task_checklist'), function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id')->index();
            $table->string('text', 255);
            $table->boolean('done')->default(0);
            $table->dateTime('created_at');
        });

        Schema::create($this->t('task_attachments'), function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id')->index();
            $table->string('name', 255);
            $table->string('url', 500);
            $table->dateTime('created_at');
        });

        Schema::create($this->t('help_requests'), function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id')->index();
            $table->unsignedBigInteger('from_employee_id');
            $table->unsignedBigInteger('to_employee_id')->index();
            $table->string('help_type', 100)->default('');
            $table->text('message')->nullable();
            $table->string('attachment_url', 500)->nullable();
            $table->string('attachment_name', 255)->nullable();
            $table->string('status', 20)->default('pending');
            $table->dateTime('created_at');
            $table->dateTime('resolved_at')->nullable();
        });

        Schema::create($this->t('notifications'), function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('target_employee_id')->nullable()->index();
            $table->boolean('target_admin')->default(0)->index();
            $table->text('message');
            $table->boolean('is_read')->default(0);
            $table->dateTime('created_at');
        });

        Schema::create($this->t('audit_log'), function (Blueprint $table) {
            $table->id();
            $table->text('message');
            $table->dateTime('created_at');
        });

        Schema::create($this->t('update_comments'), function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('update_id')->index();
            $table->string('author', 50)->default('الإدارة');
            $table->text('message');
            $table->dateTime('created_at');
        });

        Schema::create($this->t('leave_balances'), function (Blueprint $table) {
            $table->unsignedBigInteger('employee_id')->primary();
            $table->date('accrual_start_date');
            $table->decimal('manual_adjustment_days', 6, 2)->default(0);
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
        });

        Schema::create($this->t('leave_requests'), function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->index();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('days_count', 6, 2)->default(0);
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->decimal('balance_before', 6, 2)->default(0);
            $table->text('admin_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->boolean('added_by_admin')->default(0);
            $table->dateTime('decided_at')->nullable();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
        });

        Schema::create($this->t('sick_leave_requests'), function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->index();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('days_count', 6, 2)->default(0);
            $table->text('notes')->nullable();
            $table->string('attachment_url', 500)->nullable();
            $table->string('attachment_name', 255)->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->text('admin_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->dateTime('decided_at')->nullable();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
        });

        Schema::create($this->t('permission_requests'), function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->index();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->date('perm_date');
            $table->string('time_out', 10)->default('');
            $table->string('time_return', 10)->default('');
            $table->string('reason', 255)->default('');
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->text('admin_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->dateTime('decided_at')->nullable();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
        });

        Schema::create($this->t('attendance_records'), function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('attendance_date')->index();
            $table->dateTime('check_in_time')->nullable();
            $table->dateTime('check_out_time')->nullable();
            $table->decimal('check_in_lat', 10, 7)->nullable();
            $table->decimal('check_in_lng', 10, 7)->nullable();
            $table->decimal('check_in_accuracy', 10, 2)->nullable();
            $table->decimal('check_in_distance', 10, 2)->nullable();
            $table->string('check_in_ip', 64)->default('');
            $table->decimal('check_out_lat', 10, 7)->nullable();
            $table->decimal('check_out_lng', 10, 7)->nullable();
            $table->decimal('check_out_accuracy', 10, 2)->nullable();
            $table->decimal('check_out_distance', 10, 2)->nullable();
            $table->string('check_out_ip', 64)->default('');
            $table->string('status', 20)->default('present')->index();
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('worked_minutes')->nullable();
            $table->string('source', 20)->default('gps');
            $table->boolean('is_manual')->default(0);
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
            $table->unique(['employee_id', 'attendance_date'], 'employee_date');
        });

        Schema::create($this->t('attendance_audit_logs'), function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->index();
            $table->date('attendance_date')->index();
            $table->string('old_status', 20)->default('');
            $table->string('new_status', 20)->default('');
            $table->dateTime('old_check_in')->nullable();
            $table->dateTime('new_check_in')->nullable();
            $table->dateTime('old_check_out')->nullable();
            $table->dateTime('new_check_out')->nullable();
            $table->text('reason');
            $table->text('notes')->nullable();
            $table->string('admin_label', 191)->default('');
            $table->unsignedBigInteger('admin_user_id')->default(0);
            $table->dateTime('created_at');
        });
    }

    public function down(): void
    {
        foreach (['attendance_audit_logs', 'attendance_records', 'permission_requests', 'sick_leave_requests',
            'leave_requests', 'leave_balances', 'update_comments', 'audit_log', 'notifications', 'help_requests',
            'task_attachments', 'task_checklist', 'projects', 'task_members', 'task_updates', 'task_comments',
            'weekly_tasks', 'problem_logs', 'problems', 'daily_updates', 'employees', 'departments', 'options'] as $name) {
            Schema::dropIfExists($this->t($name));
        }
    }
};
