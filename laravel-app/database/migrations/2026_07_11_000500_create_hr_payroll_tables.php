<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

/**
 * Core HR / payroll tables needed for public payslip verification.
 * DDL mirrors the legacy beyondtechworld Node API (CHAR(36) UUID keys) so the
 * same schema stays compatible if that service is ever pointed at this DB.
 */
class CreateHrPayrollTables extends Migration
{
    public function up()
    {
        $tables = [
            'hr_staff_categories' => "CREATE TABLE IF NOT EXISTS hr_staff_categories (
                id CHAR(36) NOT NULL PRIMARY KEY,
                code VARCHAR(50) NOT NULL UNIQUE,
                name VARCHAR(120) NOT NULL,
                description TEXT DEFAULT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'hr_staff_profiles' => "CREATE TABLE IF NOT EXISTS hr_staff_profiles (
                id CHAR(36) NOT NULL PRIMARY KEY,
                user_id CHAR(36) DEFAULT NULL,
                staff_code VARCHAR(40) NOT NULL UNIQUE,
                first_name VARCHAR(120) NOT NULL,
                last_name VARCHAR(120) NOT NULL,
                email VARCHAR(255) DEFAULT NULL,
                phone VARCHAR(40) DEFAULT NULL,
                category_id CHAR(36) NOT NULL,
                position VARCHAR(120) DEFAULT NULL,
                department VARCHAR(120) DEFAULT NULL,
                payment_type ENUM('monthly','daily') NOT NULL DEFAULT 'daily',
                daily_rate DECIMAL(12,2) DEFAULT NULL,
                monthly_salary DECIMAL(12,2) DEFAULT NULL,
                contract_start DATE DEFAULT NULL,
                contract_end DATE DEFAULT NULL,
                hire_date DATE DEFAULT NULL,
                bank_name VARCHAR(120) DEFAULT NULL,
                bank_account VARCHAR(80) DEFAULT NULL,
                status ENUM('active','inactive','terminated') NOT NULL DEFAULT 'active',
                notes TEXT DEFAULT NULL,
                created_by CHAR(36) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_hr_staff_profiles_category (category_id),
                INDEX idx_hr_staff_profiles_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'hr_payroll_runs' => "CREATE TABLE IF NOT EXISTS hr_payroll_runs (
                id CHAR(36) NOT NULL PRIMARY KEY,
                run_type ENUM('job','monthly') NOT NULL,
                title VARCHAR(255) NOT NULL,
                job_id CHAR(36) DEFAULT NULL,
                period_start DATE DEFAULT NULL,
                period_end DATE DEFAULT NULL,
                status ENUM('draft','review','approved','finance','partially_paid','paid','rejected') NOT NULL DEFAULT 'draft',
                total_gross DECIMAL(14,2) NOT NULL DEFAULT 0,
                total_net DECIMAL(14,2) NOT NULL DEFAULT 0,
                notes TEXT DEFAULT NULL,
                created_by CHAR(36) DEFAULT NULL,
                reviewed_by CHAR(36) DEFAULT NULL,
                approved_by CHAR(36) DEFAULT NULL,
                forwarded_to_finance_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_hr_payroll_runs_type (run_type),
                INDEX idx_hr_payroll_runs_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'hr_payroll_items' => "CREATE TABLE IF NOT EXISTS hr_payroll_items (
                id CHAR(36) NOT NULL PRIMARY KEY,
                payroll_run_id CHAR(36) NOT NULL,
                staff_profile_id CHAR(36) NOT NULL,
                basic_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
                daily_rate DECIMAL(12,2) DEFAULT NULL,
                days_worked DECIMAL(6,2) DEFAULT NULL,
                hours_expected DECIMAL(8,2) DEFAULT NULL,
                hours_actual DECIMAL(8,2) DEFAULT NULL,
                overtime_hours DECIMAL(8,2) DEFAULT NULL,
                gross_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
                total_allowances DECIMAL(12,2) NOT NULL DEFAULT 0,
                total_deductions DECIMAL(12,2) NOT NULL DEFAULT 0,
                total_advances DECIMAL(12,2) NOT NULL DEFAULT 0,
                net_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
                payment_status ENUM('pending','approved_for_payment','partially_paid','paid','rejected') NOT NULL DEFAULT 'pending',
                notes TEXT DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_hr_payroll_items_run (payroll_run_id),
                INDEX idx_hr_payroll_items_staff (staff_profile_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'hr_payslips' => "CREATE TABLE IF NOT EXISTS hr_payslips (
                id CHAR(36) NOT NULL PRIMARY KEY,
                payroll_item_id CHAR(36) NOT NULL UNIQUE,
                verification_code VARCHAR(32) NOT NULL UNIQUE,
                pdf_path VARCHAR(500) DEFAULT NULL,
                sent_email_at DATETIME DEFAULT NULL,
                sent_whatsapp_at DATETIME DEFAULT NULL,
                generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_hr_payslips_item (payroll_item_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];

        foreach ($tables as $name => $ddl) {
            if (! Schema::hasTable($name)) {
                DB::statement($ddl);
            }
        }

        $this->seedDemo();
    }

    private function seedDemo()
    {
        if (DB::table('hr_payslips')->count() > 0) {
            return;
        }

        $categoryId = DB::table('hr_staff_categories')->value('id');
        if (! $categoryId) {
            $categoryId = (string) Str::uuid();
            DB::table('hr_staff_categories')->insert([
                'id' => $categoryId,
                'code' => 'PERM',
                'name' => 'Permanent Staff',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $staffId = (string) Str::uuid();
        DB::table('hr_staff_profiles')->insert([
            'id' => $staffId,
            'staff_code' => 'BE-0001',
            'first_name' => 'Jean',
            'last_name' => 'Bosco',
            'email' => 'jean.bosco@beyondtechworld.com',
            'phone' => '+237650000000',
            'category_id' => $categoryId,
            'position' => 'Network Engineer',
            'department' => 'Technical',
            'payment_type' => 'monthly',
            'monthly_salary' => 450000,
            'hire_date' => '2023-03-01',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $runId = (string) Str::uuid();
        DB::table('hr_payroll_runs')->insert([
            'id' => $runId,
            'run_type' => 'monthly',
            'title' => 'Monthly Payroll — '.now()->format('F Y'),
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'status' => 'paid',
            'total_gross' => 450000,
            'total_net' => 420000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $itemId = (string) Str::uuid();
        DB::table('hr_payroll_items')->insert([
            'id' => $itemId,
            'payroll_run_id' => $runId,
            'staff_profile_id' => $staffId,
            'basic_amount' => 450000,
            'gross_amount' => 450000,
            'total_deductions' => 30000,
            'net_amount' => 420000,
            'payment_status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('hr_payslips')->insert([
            'id' => (string) Str::uuid(),
            'payroll_item_id' => $itemId,
            'verification_code' => 'BE-PS-DEMO01',
            'generated_at' => now(),
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('hr_payslips');
        Schema::dropIfExists('hr_payroll_items');
        Schema::dropIfExists('hr_payroll_runs');
        Schema::dropIfExists('hr_staff_profiles');
        Schema::dropIfExists('hr_staff_categories');
    }
}
