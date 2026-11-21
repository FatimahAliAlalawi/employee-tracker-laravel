<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** الإعدادات الافتراضية فقط (بدون أي بيانات تجريبية) */
    public function run(): void
    {
        \ET_Database::activate();
    }
}
