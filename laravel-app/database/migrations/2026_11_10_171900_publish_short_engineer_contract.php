<?php

use App\ContractTemplate;
use App\ContractTemplateVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PublishShortEngineerContract extends Migration
{
    public function up()
    {
        $enterprise = $this->enterpriseHtml();
        $tpl = ContractTemplate::where('code', 'ENG-EVENT')->first();
        if ($tpl) {
            $tpl->name = 'Engineer engagement';
            $tpl->description = 'Short event contract: role, days, daily pay, hours, helmet and boots, and feeding allowance.';
            $tpl->active = true;
            $version = ContractTemplateVersion::create([
                'id' => (string) Str::uuid(),
                'template_id' => $tpl->id,
                'version_no' => ((int) $tpl->versions()->max('version_no')) + 1,
                'content_html' => $enterprise,
                'placeholder_schema' => [
                    'required' => ['party_b.name', 'event.name', 'worker.role', 'work.estimated_days', 'worker.daily_rate'],
                    'optional' => ['worker.food_allowance'],
                ],
                'signature_workflow_json' => [
                    'mode' => 'sequential',
                    'stages' => [
                        ['stage' => 1, 'roles' => ['party_b']],
                        ['stage' => 2, 'roles' => ['admin', 'party_a']],
                    ],
                ],
                'checksum' => hash('sha256', $enterprise),
                'published_at' => now(),
            ]);
            $tpl->current_version_id = $version->id;
            $tpl->save();
        }

        $body = $this->eventHtml();
        $existing = DB::table('event_contract_templates')->where('contract_type', 'engineer')->first();
        if ($existing) {
            DB::table('event_contract_templates')->where('id', $existing->id)->update([
                'name' => 'Engineer engagement',
                'header' => 'Beyond Group of Companies',
                'body' => $body,
                'footer' => 'Sr. Engr. Tefu R. Mbole, CEO / Director, Beyond Group of Companies',
                'is_active' => true,
                'updated_at' => now(),
            ]);
        } else {
            DB::table('event_contract_templates')->insert([
                'id' => (string) Str::uuid(),
                'name' => 'Engineer engagement',
                'contract_type' => 'engineer',
                'header' => 'Beyond Group of Companies',
                'body' => $body,
                'footer' => 'Sr. Engr. Tefu R. Mbole, CEO / Director, Beyond Group of Companies',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down()
    {
    }

    protected function enterpriseHtml()
    {
        return <<<'HTML'
<h1 style="text-align:center;color:#0b3f90;font-size:22px;">Engineer engagement</h1>
<p><strong>{{party_b.name}}</strong> will work on <strong>{{event.name}}</strong> as <strong>{{worker.role}}</strong> for <strong>{{work.estimated_days}}</strong> day(s).</p>
<p>Daily pay is <strong>{{worker.daily_rate}} XAF</strong> for each of those days.</p>
<p>Working time at Beyond is <strong>7:00am to 6:00pm</strong>. Overtime may occur.</p>
<p>During working hours, engineers on duty wear a <strong>helmet</strong> and <strong>boots</strong>. Gloves are recommended. No boots: a fine of <strong>500 XAF</strong>. No helmet: a fine of <strong>500 XAF</strong>. Please follow these rules.</p>
<p>Feeding allowance is <strong>1,000 XAF</strong> a day. If food is provided by the business official or by administration, the feeding allowance is not paid.</p>
<p style="margin-top:28px;line-height:1.4;"><strong>Sr. Engr. Tefu R. Mbole</strong><br>CEO / Director<br>Beyond Group of Companies</p>
HTML;
    }

    protected function eventHtml()
    {
        return <<<'HTML'
<h1 style="text-align:center;color:#0b3f90;font-size:22px;">Engineer engagement</h1>
<p><strong>{{worker_name}}</strong> will work on <strong>{{event_name}}</strong> as <strong>{{role}}</strong> for <strong>{{expected_days}}</strong> day(s).</p>
<p>Daily pay is <strong>{{daily_rate}} XAF</strong> for each of those days.</p>
<p>Working time at Beyond is <strong>7:00am to 6:00pm</strong>. Overtime may occur.</p>
<p>During working hours, engineers on duty wear a <strong>helmet</strong> and <strong>boots</strong>. Gloves are recommended. No boots: a fine of <strong>500 XAF</strong>. No helmet: a fine of <strong>500 XAF</strong>. Please follow these rules.</p>
<p>Feeding allowance is <strong>{{food_allowance}} XAF</strong> a day. If food is provided by the business official or by administration, the feeding allowance is not paid.</p>
HTML;
    }
}
