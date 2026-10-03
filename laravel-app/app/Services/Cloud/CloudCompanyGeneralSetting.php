<?php

namespace App\Services\Cloud;

use App\Cloud\CloudTenant;
use App\Support\BrandingImage;
use Illuminate\Http\Request;

/**
 * The settings form for one customer company.
 * It uses the same window as BeyondTechWorld and never writes the shared settings row or .env.
 */
class CloudCompanyGeneralSetting
{
    public function present(CloudTenant $tenant)
    {
        $row = new \stdClass();
        $row->site_title = $tenant->system_name ?: $tenant->name;
        $row->site_logo = $this->value($tenant, 'site_logo');
        $row->email_header = $this->value($tenant, 'email_header');
        $row->email_footer = $this->value($tenant, 'email_footer');
        $row->email_water_mark = $this->value($tenant, 'email_water_mark');
        $row->currency = $this->value($tenant, 'currency_id');
        $row->currency_position = $this->value($tenant, 'currency_position', 'suffix');
        $row->staff_access = $this->value($tenant, 'staff_access', 'own');
        $row->date_format = $this->value($tenant, 'date_format', 'd-m-Y');
        $row->developed_by = '';
        $row->invoice_format = $this->value($tenant, 'invoice_format', 'standard');
        $row->state = $this->value($tenant, 'state', '1');
        $row->unit = $this->value($tenant, 'unit_id');
        $row->category = $this->value($tenant, 'category_id');
        $row->default_warehouse_id = $this->value($tenant, 'default_warehouse_id');
        $row->default_biller_id = '';
        $row->profit_percentage = $this->value($tenant, 'profit_percentage');
        $row->letter_serial_no = $this->value($tenant, 'letter_serial_no');
        $row->commission = $this->value($tenant, 'commission');

        return $row;
    }

    public function save(CloudTenant $tenant, Request $request)
    {
        $title = trim((string) $request->input('site_title', ''));
        if ($title !== '') {
            $tenant->system_name = $title;
        }
        $timezone = trim((string) $request->input('timezone', ''));
        if ($timezone !== '' && in_array($timezone, timezone_identifiers_list(), true)) {
            $tenant->timezone = $timezone;
        }
        $tenant->save();

        $portal = app(CloudPortalService::class);
        $fields = [
            'currency_id' => (string) $request->input('currency', ''),
            'currency_position' => (string) $request->input('currency_position', 'suffix'),
            'staff_access' => (string) $request->input('staff_access', 'own'),
            'date_format' => (string) $request->input('date_format', 'd-m-Y'),
            'invoice_format' => (string) $request->input('invoice_format', 'standard'),
            'state' => (string) $request->input('state', '1'),
            'unit_id' => (string) $request->input('unit', ''),
            'category_id' => (string) $request->input('category', ''),
            'default_warehouse_id' => (string) $request->input('default_warehouse_id', ''),
            'profit_percentage' => (string) $request->input('profit_percentage', ''),
            'letter_serial_no' => (string) $request->input('letter_serial_no', ''),
            'commission' => (string) $request->input('commission', ''),
        ];
        foreach ($fields as $key => $value) {
            $portal->putSetting($tenant, $key, $value);
        }

        $logoDir = base_path('public/logo');
        if (! is_dir($logoDir)) {
            mkdir($logoDir, 0775, true);
        }
        $slots = [
            'site_logo' => 'logo',
            'email_header' => 'header',
            'email_footer' => 'footer',
            'email_water_mark' => 'watermark',
        ];
        foreach ($slots as $input => $slot) {
            if (! $request->hasFile($input)) {
                continue;
            }
            $name = BrandingImage::storeFitted(
                $request->file($input),
                $logoDir,
                $input,
                'c'.$tenant->id.'_'.$slot
            );
            $portal->putSetting($tenant, $input, $name);
        }
    }

    protected function value(CloudTenant $tenant, $key, $default = '')
    {
        return app(CloudPortalService::class)->setting($tenant, $key, $default);
    }
}
