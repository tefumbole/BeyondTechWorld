<?php

namespace App\Gbhs;

use App\Customer;
use App\Letter;
use App\LetterOrganization;
use App\Support\LetterReference;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class GbhsMembership
{
    public static function findCustomerByPhone($phone)
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        $tail = substr($digits, -9);
        if (strlen($tail) < 9) {
            return null;
        }

        return Customer::where('phone_number', 'like', '%'.$tail)->orderBy('id')->first();
    }

    public static function linkCustomer(Customer $customer, $gbhsGroupId)
    {
        $keep = GbhsRules::pricingGroupId($customer->customer_group_id, $gbhsGroupId);
        if ($keep && (int) $customer->customer_group_id !== (int) $keep) {
            $customer->customer_group_id = $keep;
            $customer->save();
        }
        if ($gbhsGroupId && Schema::hasTable('customer_group_memberships')) {
            CustomerGroupMembership::firstOrCreate([
                'customer_id' => $customer->id,
                'customer_group_id' => $gbhsGroupId,
            ]);
        }

        return $customer->fresh();
    }

    public static function openDraft($phone)
    {
        if (! GbhsRules::mayStorePersonal(true)) {
            return null;
        }
        $existing = GbhsApplication::where('phone', $phone)->whereIn('status', ['draft', 'more_info'])->orderBy('id', 'desc')->first();
        if ($existing) {
            $existing->phone_verified = true;
            $existing->save();

            return $existing;
        }
        $reference = 'GBHS-A-'.strtoupper(Str::random(8));

        return GbhsApplication::create([
            'reference' => $reference,
            'phone' => $phone,
            'phone_verified' => true,
            'status' => 'draft',
        ]);
    }

    public static function approve(GbhsApplication $application, $reviewerId, $note = null)
    {
        return DB::transaction(function () use ($application, $reviewerId, $note) {
            $locked = GbhsApplication::where('id', $application->id)->lockForUpdate()->first();
            if (! $locked) {
                return null;
            }
            if ($locked->member_id && GbhsRules::shouldCreateMember($locked->member_id) === false) {
                return GbhsMember::find($locked->member_id);
            }
            $group = GbhsCatalog::customerGroup();
            $customer = $locked->customer_id ? Customer::find($locked->customer_id) : self::findCustomerByPhone($locked->phone);
            if (! $customer) {
                $customer = Customer::create([
                    'name' => $locked->full_name ?: 'GBHS applicant',
                    'phone_number' => $locked->phone,
                    'email' => $locked->email,
                    'address' => $locked->address ?: '-',
                    'city' => $locked->city ?: '-',
                    'state' => $locked->region,
                    'country' => $locked->country ?: 'Cameroon',
                    'is_active' => 1,
                ]);
            }
            if ($group) {
                self::linkCustomer($customer, $group->id);
            }
            $number = self::nextNumber();
            $member = GbhsMember::create([
                'application_id' => $locked->id,
                'customer_id' => $customer->id,
                'batch_id' => $locked->batch_id,
                'membership_number' => $number,
                'status' => 'active',
                'approved_at' => now(),
            ]);
            $letterId = $locked->letter_id;
            if (! $letterId) {
                $letterId = self::acceptanceLetter($locked, $member, $customer, $reviewerId);
            }
            $locked->customer_id = $customer->id;
            $locked->member_id = $member->id;
            $locked->letter_id = $letterId;
            $locked->status = 'approved';
            $locked->reviewer_id = $reviewerId;
            $locked->reviewed_at = now();
            $locked->review_note = $note;
            $locked->save();
            GbhsAuditLog::create([
                'actor_id' => $reviewerId,
                'action' => 'membership.approved',
                'summary' => $member->membership_number.' issued for '.$locked->reference,
            ]);

            return $member;
        });
    }

    protected static function nextNumber()
    {
        $year = date('Y');
        $prefix = 'GBHS-'.$year.'-';
        $last = GbhsMember::where('membership_number', 'like', $prefix.'%')->orderBy('id', 'desc')->lockForUpdate()->first();
        $serial = 1;
        if ($last && preg_match('/(\d+)$/', $last->membership_number, $m)) {
            $serial = ((int) $m[1]) + 1;
        }

        return $prefix.str_pad((string) $serial, 4, '0', STR_PAD_LEFT);
    }

    protected static function acceptanceLetter(GbhsApplication $application, GbhsMember $member, Customer $customer, $reviewerId)
    {
        if (! Schema::hasTable('letters')) {
            return null;
        }
        $setting = GbhsSetting::query()->first();
        $org = null;
        if ($setting && $setting->letter_organization_id && Schema::hasTable('letter_organizations')) {
            $org = LetterOrganization::find($setting->letter_organization_id);
        }
        $prefix = $org && $org->reference_prefix ? $org->reference_prefix : 'GBHS/L-';
        $reference = LetterReference::next('letter', $prefix);
        $signature = '';
        if ($setting && $setting->president_signature) {
            $signature = '<p><img src="'.url('public/'.$setting->president_signature).'" alt="Signature" style="height:70px"></p>';
        }
        $who = $setting && $setting->president_name ? $setting->president_name : 'The President';
        $title = $setting && $setting->president_title ? $setting->president_title : 'President';
        $body = '<p>Dear '.e($application->full_name).',</p>'
            .'<p>Your application '.$application->reference.' is accepted. Your membership number is <strong>'.e($member->membership_number).'</strong>.</p>'
            .'<p>This letter is issued by the GBHS Kumbo Ex-Students Association.</p>'
            .$signature
            .'<p>'.$who.'<br>'.$title.'</p>';
        $letter = new Letter();
        $letter->reference = $reference;
        $letter->name = $application->full_name ?: $customer->name;
        $letter->to = $application->full_name ?: $customer->name;
        $letter->subject = 'Acceptance of membership';
        $letter->body = $body;
        $letter->is_active = 1;
        $letter->is_approve = 1;
        $letter->is_sign = $setting && $setting->president_signature ? 1 : 0;
        $letter->created_by = $reviewerId ?: 1;
        $letter->people_type = 'customer';
        if ($org && Schema::hasColumn('letters', 'organization_id')) {
            $letter->organization_id = $org->id;
        }
        $letter->save();

        return $letter->id;
    }

    public static function notifyApproved(GbhsApplication $application, GbhsMember $member)
    {
        if (! $application->consent_announcements || ! $application->phone) {
            return;
        }
        try {
            app(\App\Services\BeyondWasenderService::class)->sendText(
                $application->phone,
                'GBHS Kumbo: application '.$application->reference.' is accepted. Membership number '.$member->membership_number.'.'
            );
        } catch (\Throwable $e) {
        }
    }
}
