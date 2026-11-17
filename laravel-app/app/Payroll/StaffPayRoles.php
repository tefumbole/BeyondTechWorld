<?php

namespace App\Payroll;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StaffPayRoles
{
    public static function defaults()
    {
        return [
            ['network_engineer', 'Eng. Network Engineer', 'monthly', 0, 0, 'senior_engineer'],
            ['maneuver', 'Maneuver / Unskilled Labourer', 'daily', 2500, 0, 'general_staff'],
            ['technician', 'Technician', 'daily', 3500, 0, 'engineer'],
            ['senior_technician', 'Senior Technician', 'daily', 4500, 0, 'senior_engineer'],
            ['engineer', 'Engineer', 'daily', 5000, 0, 'engineer'],
            ['senior_engineer', 'Senior Engineer', 'daily', 6000, 0, 'senior_engineer'],
        ];
    }

    public static function ensure()
    {
        if (! Schema::hasTable('cm_pay_roles')) {
            return;
        }
        foreach (self::defaults() as $row) {
            self::insertMissing($row[0], $row[1], $row[2], $row[3], $row[4], $row[5]);
        }
        self::importEvents();
    }

    public static function importEvents()
    {
        if (! Schema::hasTable('cm_pay_roles') || ! Schema::hasTable('event_worker_categories')) {
            return 0;
        }
        $count = 0;
        foreach (DB::table('event_worker_categories')->get() as $category) {
            $code = substr(preg_replace('/[^a-z0-9_]+/', '_', strtolower((string) $category->code)), 0, 40);
            if ($code === '') {
                continue;
            }
            $rate = (int) $category->default_daily_rate;
            $existing = DB::table('cm_pay_roles')->where('code', $code)->first();
            if ($existing) {
                if ($existing->pay_type === 'daily') {
                    DB::table('cm_pay_roles')->where('id', $existing->id)->update([
                        'name' => $category->name,
                        'daily_rate' => $rate,
                        'updated_at' => now(),
                    ]);
                }
            } else {
                self::insertMissing($code, $category->name, 'daily', $rate, 0, self::categoryFor($code));
            }
            $count++;
        }

        return $count;
    }

    public static function active()
    {
        if (! Schema::hasTable('cm_pay_roles')) {
            return collect();
        }
        self::ensure();

        return DB::table('cm_pay_roles')->where('is_active', 1)->orderBy('name')->get();
    }

    protected static function categoryFor($code)
    {
        $map = [
            'network_engineer' => 'senior_engineer',
            'senior_engineer' => 'senior_engineer',
            'senior_technician' => 'senior_engineer',
            'engineer' => 'engineer',
            'technician' => 'engineer',
            'maneuver' => 'general_staff',
        ];

        return isset($map[$code]) ? $map[$code] : 'general_staff';
    }

    protected static function insertMissing($code, $name, $payType, $daily, $monthly, $categoryCode)
    {
        if (DB::table('cm_pay_roles')->where('code', $code)->exists()) {
            return;
        }
        DB::table('cm_pay_roles')->insert([
            'code' => $code,
            'name' => $name,
            'pay_type' => $payType === 'monthly' ? 'monthly' : 'daily',
            'daily_rate' => (int) $daily,
            'monthly_salary' => (int) $monthly,
            'salary_category_code' => $categoryCode,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
