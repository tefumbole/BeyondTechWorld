<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CreateEventContractsTables extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('event_contract_templates')) {
            Schema::create('event_contract_templates', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('contract_type', 64)->default('worker_agreement');
                $table->text('header')->nullable();
                $table->longText('body');
                $table->text('footer')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('event_contracts')) {
            Schema::create('event_contracts', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('event_id');
                $table->uuid('assignment_id');
                $table->uuid('template_id')->nullable();
                $table->string('reference_no', 32)->unique();
                $table->string('title');
                $table->longText('rendered_body');
                $table->string('status', 32)->default('draft');
                $table->string('signature_token', 64)->unique();
                $table->dateTime('worker_signed_at')->nullable();
                $table->longText('worker_signature')->nullable();
                $table->dateTime('admin_signed_at')->nullable();
                $table->unsignedInteger('admin_signed_by')->nullable();
                $table->longText('admin_signature')->nullable();
                $table->string('signed_pdf_path')->nullable();
                $table->dateTime('sent_at')->nullable();
                $table->dateTime('approved_at')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['event_id', 'status']);
                $table->index('assignment_id');
            });
        }

        $defaultBody = <<<'HTML'
<h3>Event Worker Agreement</h3>
<p>This agreement is between <strong>{{company_name}}</strong> and <strong>{{worker_name}}</strong> for the event <strong>{{event_name}}</strong> ({{event_reference}}).</p>
<ul>
<li><strong>Role:</strong> {{role}}</li>
<li><strong>Venue:</strong> {{venue}}</li>
<li><strong>Event dates:</strong> {{event_start}} to {{event_end}}</li>
<li><strong>Daily rate:</strong> {{daily_rate}} XAF</li>
<li><strong>Expected days:</strong> {{expected_days}}</li>
<li><strong>Total compensation:</strong> {{total_amount}} XAF</li>
</ul>
<p>The worker agrees to perform assigned duties professionally and follow Beyond Enterprise safety and conduct policies.</p>
HTML;

        if (DB::table('event_contract_templates')->count() === 0) {
            DB::table('event_contract_templates')->insert([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'name' => 'Standard Worker Agreement',
                'contract_type' => 'worker_agreement',
                'header' => 'Beyond Enterprise — Event Worker Contract',
                'body' => $defaultBody,
                'footer' => 'Signed electronically via Beyond Enterprise Events.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach (['event_contracts.view', 'event_contracts.create', 'event_contracts.send', 'event_contracts.approve'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        foreach (Role::whereIn('id', [1, 2])->get() as $role) {
            foreach (['event_contracts.view', 'event_contracts.create', 'event_contracts.send', 'event_contracts.approve'] as $perm) {
                if (! $role->hasPermissionTo($perm)) {
                    $role->givePermissionTo($perm);
                }
            }
        }
    }

    public function down()
    {
        Schema::dropIfExists('event_contracts');
        Schema::dropIfExists('event_contract_templates');
    }
}
