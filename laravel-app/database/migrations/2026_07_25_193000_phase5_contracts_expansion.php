<?php

use App\ContractClause;
use App\ContractSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class Phase5ContractsExpansion extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('contract_clauses')) {
            Schema::create('contract_clauses', function (Blueprint $table) {
                $table->string('id', 36)->primary();
                $table->string('code', 60)->unique();
                $table->string('title');
                $table->string('category', 80)->nullable()->index();
                $table->text('body_html');
                $table->boolean('active')->default(true)->index();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        ContractSetting::setValue('expiry_alert_days', 30);
        ContractSetting::setValue('expiry_alerts_enabled', true);

        $perms = ['contracts.dashboard', 'contracts.clauses', 'contracts.report', 'contracts.bulk'];
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        foreach (Role::all() as $role) {
            $names = $role->permissions->pluck('name')->all();
            if (in_array('contracts_module', $names, true)
                || in_array('contracts.view', $names, true)
                || in_array($role->name, ['Admin', 'Super Admin'], true)) {
                try {
                    $role->givePermissionTo($perms);
                } catch (\Throwable $e) {
                    // ignore
                }
            }
        }

        $clauses = [
            ['CONF-01', 'Confidentiality', 'legal', '<h3>Confidentiality</h3><p>Each party agrees to keep confidential all non-public information received from the other party in connection with this Agreement and not to disclose it to third parties without prior written consent, except as required by law.</p>'],
            ['FORCE-01', 'Force Majeure', 'legal', '<h3>Force Majeure</h3><p>Neither party shall be liable for failure or delay in performance due to circumstances beyond its reasonable control, including acts of God, war, terrorism, epidemic, government action, or failure of utilities, provided the affected party gives prompt notice and uses reasonable efforts to mitigate.</p>'],
            ['GOV-RW', 'Governing Law (Rwanda)', 'legal', '<h3>Governing Law</h3><p>This Agreement is governed by the laws of Rwanda. Disputes shall first be attempted to be resolved amicably; failing that, the competent courts of Rwanda shall have jurisdiction.</p>'],
            ['GOV-CM', 'Governing Law (Cameroon)', 'legal', '<h3>Governing Law</h3><p>This Agreement is governed by the laws of the Republic of Cameroon. Disputes shall first be attempted to be resolved amicably; failing that, the competent courts shall have jurisdiction.</p>'],
            ['TERM-01', 'Termination for Convenience', 'ops', '<h3>Termination</h3><p>Either party may terminate this Agreement by written notice if the other party materially breaches and fails to cure within fourteen (14) days of notice, or immediately for insolvency. Obligations accrued before termination survive.</p>'],
            ['IP-01', 'Intellectual Property', 'legal', '<h3>Intellectual Property</h3><p>Unless otherwise agreed in writing, each party retains ownership of its pre-existing intellectual property. Deliverables created specifically under this Agreement transfer to the Client upon full payment, subject to any third-party licenses.</p>'],
            ['PAY-01', 'Late Payment', 'finance', '<h3>Late Payment</h3><p>Amounts unpaid after the due date may incur a late fee and/or suspension of services until payment is received. The Client remains responsible for all amounts owed for work already performed.</p>'],
        ];
        foreach ($clauses as $i => [$code, $title, $cat, $html]) {
            ContractClause::firstOrCreate(
                ['code' => $code],
                [
                    'id' => (string) Str::uuid(),
                    'title' => $title,
                    'category' => $cat,
                    'body_html' => $html,
                    'active' => true,
                    'sort_order' => $i + 1,
                ]
            );
        }
    }

    public function down()
    {
        Schema::dropIfExists('contract_clauses');
    }
}
