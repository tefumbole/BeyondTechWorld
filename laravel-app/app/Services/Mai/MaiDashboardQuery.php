<?php

namespace App\Services\Mai;

use App\Mai\MaiSession;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MaiDashboardQuery
{
    public function summary()
    {
        $registered = $this->registeredIds();
        $signedIn = $this->signedInIds();
        $withSession = MaiSession::query()->distinct()->pluck('user_id')->all();
        $idle = array_values(array_diff($signedIn, $withSession, $this->subscribedUserIds()));

        return [
            'registered' => count($registered),
            'signed_in' => count($signedIn),
            'paid' => $this->subscriptionCount('ACTIVE'),
            'trials' => $this->subscriptionCount('TRIALING'),
            'connected' => MaiSession::where('status', 'CONNECTED')->count(),
            'signed_in_without_plan' => count($idle),
        ];
    }

    public function accounts($search, $filter, $page)
    {
        $ids = $this->registeredIds();
        if ($filter === 'signed_in') {
            $ids = $this->signedInIds();
        }
        $users = User::whereIn('id', $ids === [] ? [0] : $ids)->orderBy('name')->get();
        $rows = [];
        foreach ($users as $user) {
            $sessions = MaiSession::where('user_id', $user->id)->get();
            $row = $this->accountRow($user, $sessions);
            if ($search !== '' && stripos($row['name'].' '.$row['email'].' '.$row['phone'], $search) === false) {
                continue;
            }
            if ($filter === 'no_session' && $sessions->count() > 0) {
                continue;
            }
            if ($filter === 'trial' && $row['plan_status'] !== 'TRIALING') {
                continue;
            }
            if ($filter === 'paid' && $row['plan_status'] !== 'ACTIVE') {
                continue;
            }
            if ($filter === 'expired' && ! in_array($row['plan_status'], ['EXPIRED', 'CANCELLED', 'SUSPENDED', 'PAST_DUE'], true)) {
                continue;
            }
            if ($filter === 'disconnected' && $sessions->where('status', 'CONNECTED')->count() > 0) {
                continue;
            }
            $rows[] = $row;
        }
        $page = max(1, (int) $page);
        $slice = array_slice($rows, ($page - 1) * 20, 20);

        return ['rows' => $slice, 'total' => count($rows), 'page' => $page];
    }

    public function account($userId)
    {
        $user = User::find($userId);
        if (! $user || ! in_array((int) $user->id, $this->registeredIds(), true)) {
            return null;
        }
        $sessions = MaiSession::where('user_id', $user->id)->orderByDesc('id')->get();
        $account = $this->accountRow($user, $sessions);
        $account['sessions'] = [];
        foreach ($sessions as $session) {
            $state = MaiEntitlement::summary($session);
            $account['sessions'][] = [
                'name' => $session->display_name ?: ('WhatsApp '.$session->expected_phone),
                'public_id' => $session->public_id,
                'phone' => $session->phone_number ?: $session->expected_phone,
                'owner' => $user->name,
                'provider' => $session->provider,
                'connection' => $state['connection'],
                'subscription' => $state['subscription'],
                'api_sending' => $state['api_sending'],
                'activity' => $session->updated_at ? $session->updated_at->toDateTimeString() : '',
            ];
        }
        $account['tokens'] = $this->tokens($user->id);

        return $account;
    }

    protected function accountRow($user, $sessions)
    {
        $plan = $this->planFor($sessions);
        $signin = null;
        if (Schema::hasTable('mai_signins')) {
            $signin = DB::table('mai_signins')->where('user_id', $user->id)->max('signed_in_at');
        }

        return [
            'id' => (int) $user->id,
            'name' => $user->company_name ?: $user->name,
            'email' => $user->email,
            'phone' => (string) $user->phone,
            'signed_in_at' => $signin ? (string) $signin : '',
            'plan' => $plan['name'],
            'plan_status' => $plan['status'],
            'expires' => $plan['expires'],
            'sessions' => $sessions->count(),
            'session_limit' => config('cloud.mai_customer.session_limit'),
            'connected' => $sessions->where('status', 'CONNECTED')->count(),
        ];
    }

    protected function planFor($sessions)
    {
        $empty = ['name' => 'None', 'status' => 'none', 'expires' => ''];
        if (! Schema::hasTable('cloud_subscriptions')) {
            return $empty;
        }
        $tenantId = 0;
        foreach ($sessions as $session) {
            if ($session->cloud_tenant_id) {
                $tenantId = (int) $session->cloud_tenant_id;
                break;
            }
        }
        if ($tenantId === 0) {
            return $empty;
        }
        $subscription = DB::table('cloud_subscriptions')->where('cloud_tenant_id', $tenantId)->orderByDesc('id')->first();
        if (! $subscription) {
            return $empty;
        }
        $name = 'Plan';
        if (Schema::hasTable('cloud_plans') && ! empty($subscription->cloud_plan_id)) {
            $plan = DB::table('cloud_plans')->where('id', $subscription->cloud_plan_id)->first();
            if ($plan && ! empty($plan->name)) {
                $name = (string) $plan->name;
            }
        }
        $expires = ! empty($subscription->current_period_end) ? (string) $subscription->current_period_end : (string) $subscription->trial_ends_at;

        return ['name' => $name, 'status' => (string) $subscription->status, 'expires' => $expires];
    }

    protected function tokens($userId)
    {
        if (! Schema::hasTable('mai_api_keys')) {
            return [];
        }
        $rows = DB::table('mai_api_keys')->where('user_id', $userId)->orderByDesc('id')->get();
        $tokens = [];
        foreach ($rows as $row) {
            $tokens[] = [
                'id' => (int) $row->id,
                'label' => (string) $row->name,
                'prefix' => (string) $row->prefix,
                'scope' => (string) $row->scopes,
                'created' => (string) $row->created_at,
                'last_used' => $row->last_used_at ? (string) $row->last_used_at : '',
                'revoked' => $row->revoked_at ? (string) $row->revoked_at : '',
            ];
        }

        return $tokens;
    }

    protected function registeredIds()
    {
        $ids = [];
        foreach (['mai_sessions', 'mai_email_verifications', 'mai_api_keys', 'mai_signins'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach (DB::table($table)->pluck('user_id') as $id) {
                $ids[(int) $id] = (int) $id;
            }
        }

        return array_values($ids);
    }

    protected function signedInIds()
    {
        if (! Schema::hasTable('mai_signins')) {
            return [];
        }
        $ids = [];
        foreach (DB::table('mai_signins')->pluck('user_id') as $id) {
            $ids[(int) $id] = (int) $id;
        }

        return array_values($ids);
    }

    protected function subscribedUserIds()
    {
        if (! Schema::hasTable('cloud_subscriptions') || ! Schema::hasTable('mai_sessions')) {
            return [];
        }
        $tenants = DB::table('cloud_subscriptions')->pluck('cloud_tenant_id')->all();
        if (! $tenants) {
            return [];
        }

        return MaiSession::whereIn('cloud_tenant_id', $tenants)->pluck('user_id')->all();
    }

    protected function subscriptionCount($status)
    {
        if (! Schema::hasTable('cloud_subscriptions')) {
            return 0;
        }

        return (int) DB::table('cloud_subscriptions')->where('status', $status)->count();
    }
}
