<?php

namespace App\Gbhs;

use App\CustomerGroup;
use App\LetterOrganization;
use Illuminate\Support\Facades\Schema;

class GbhsCatalog
{
    public static function ensure()
    {
        if (! GbhsTenant::ready()) {
            return;
        }
        GbhsTenant::bind();
        if (! GbhsSetting::query()->exists()) {
            GbhsSetting::create([
                'hero_title' => 'GBHS Kumbo Ex-Students',
                'hero_subtitle' => 'Golden Jubilee, 1976–2026',
                'seo_title' => 'GBHS Kumbo Ex-Students Association',
                'seo_description' => 'The Golden Jubilee association of Government Bilingual High School Kumbo ex-students.',
                'president_title' => 'President',
            ]);
        }
        self::sections();
        self::batches();
        self::history();
        self::letterOrganization();
        self::customerGroup();
    }

    public static function customerGroup()
    {
        if (! Schema::hasTable('customer_groups')) {
            return null;
        }
        $group = CustomerGroup::where('name', 'GBHS EX-students')->first();
        if ($group) {
            return $group;
        }

        return CustomerGroup::create([
            'name' => 'GBHS EX-students',
            'percentage' => '0',
            'is_active' => 1,
        ]);
    }

    public static function letterOrganization()
    {
        if (! Schema::hasTable('letter_organizations')) {
            return null;
        }
        $org = LetterOrganization::where('name', 'GBHS Kumbo Ex-Students Association')->first();
        if (! $org) {
            $org = LetterOrganization::create([
                'name' => 'GBHS Kumbo Ex-Students Association',
                'reference_prefix' => 'GBHS/L-',
            ]);
        }
        $setting = GbhsSetting::query()->first();
        if ($setting && ! $setting->letter_organization_id) {
            $setting->letter_organization_id = $org->id;
            $setting->save();
        }

        return $org;
    }

    protected static function sections()
    {
        $pages = [
            'home' => ['Welcome', 'Government Bilingual High School Kumbo opened in 1976. This association brings those batches together for the Golden Jubilee in 2026. The words, photographs, and leaders on these pages are edited by the association.'],
            'about' => ['About the association', 'The GBHS Kumbo Ex-Students Association is the body of former students of Government Bilingual High School Kumbo. Add the association story here.'],
            'jubilee' => ['Golden Jubilee', 'The school year 1976 is the first batch. The jubilee marks fifty years, through 2026. Programme, venue, and dates are published here when the committee sets them.'],
            'membership' => ['Membership', 'Registration uses your phone, a WhatsApp code, the published bylaws, and your signature. An application is reviewed before a membership number is issued.'],
            'batches' => ['Batches', 'Each year from 1976 can have a representative and a contribution target. A year stays unconfirmed until the association records it.'],
            'contributions' => ['Contributions', 'Public totals count only lines the association has verified. Historical figures stay provisional until they are checked against a receipt or a bank record.'],
            'projects' => ['Projects', 'Fundraising progress is the sum of verified payments linked to a project.'],
            'contact' => ['Contact', 'Write to the association using the form. The office address and phone are set in Association Settings.'],
        ];
        $sort = 0;
        foreach ($pages as $slug => $pair) {
            $sort++;
            if (GbhsSection::where('slug', $slug)->exists()) {
                continue;
            }
            GbhsSection::create([
                'slug' => $slug,
                'title' => $pair[0],
                'kind' => 'page',
                'body' => $pair[1],
                'sort' => $sort,
                'published' => true,
            ]);
        }
    }

    protected static function batches()
    {
        if (GbhsBatch::query()->exists()) {
            return;
        }
        $represented = [1976, 1977, 1978, 1979, 1980, 1981, 1982, 1984, 1985, 1986, 1987, 1988, 1991, 1992, 1993, 1994, 1998, 2005, 2013];
        for ($year = 1976; $year <= 2026; $year++) {
            GbhsBatch::create([
                'year' => $year,
                'name' => 'Batch '.$year,
                'status' => in_array($year, $represented, true) ? 'represented' : 'unconfirmed',
            ]);
        }
        GbhsBatch::create([
            'year' => 0,
            'name' => 'Unknown batch',
            'status' => 'unconfirmed',
        ]);
    }

    protected static function history()
    {
        if (GbhsContribution::where('historical', 1)->exists()) {
            return;
        }
        foreach (GbhsHistory::rows() as $row) {
            $batch = GbhsBatch::where('year', (int) $row['year'])->first();
            GbhsContribution::create([
                'batch_id' => $batch ? $batch->id : null,
                'contributor_name' => $row['name'] ?: ($batch ? $batch->name : 'Unknown batch'),
                'classification' => $row['classification'],
                'amount' => (int) $row['amount'],
                'in_kind_qty' => $row['qty'],
                'in_kind_value' => $row['value'],
                'verified' => false,
                'historical' => true,
                'source_note' => $row['note'],
            ]);
        }
    }

    public static function section($slug)
    {
        self::ensure();

        return GbhsSection::where('slug', $slug)->where('published', 1)->first();
    }

    public static function setting()
    {
        self::ensure();

        return GbhsSetting::query()->first();
    }
}
