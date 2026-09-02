<?php

namespace App\Services;

use App\Application;
use App\BeyondUser;
use App\Customer;
use App\CustomerGroup;
use App\Support\WhatsAppPhone;
use App\TaskAssignment;
use App\TaskCc;
use App\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Unified directory for Task Manager assignees + CSV transfer of POS users/customers.
 */
class PeopleDirectoryService
{
    /**
     * Build a combined list of assignable people for Task Manager.
     * IDs are prefixed: beyond:{uuid}, user:{id}, customer:{id}
     */
    public function eligibleForTasks($filter = 'all', $search = '')
    {
        $out = collect();
        $term = trim((string) $search);
        $searching = $term !== '';

        // Always surface people who already received (or were CC'd on) a task.
        $prior = collect();
        $priorIds = $this->previousTaskUserIds();
        if ($priorIds->isNotEmpty()) {
            $this->pushBeyondUsers(
                $prior,
                BeyondUser::query()->whereIn('id', $priorIds),
                $filter,
                $term,
                null
            );
        }

        if ($filter === 'all' || $filter === 'staff') {
            $this->pushBeyondUsers(
                $out,
                BeyondUser::query(),
                $filter,
                $term,
                $searching ? 800 : 400
            );

            User::query()
                ->where(function ($q) {
                    $q->where('is_deleted', false)->orWhereNull('is_deleted');
                })
                ->where(function ($q) {
                    $q->where('is_active', true)->orWhereNull('is_active');
                })
                ->when($searching, function ($q) use ($term) {
                    $this->applyPersonSearch($q, $term, ['name', 'email', 'phone']);
                })
                ->orderBy('name')
                ->limit($searching ? 800 : 400)
                ->get(['id', 'name', 'email', 'phone', 'role_id'])
                ->each(function ($u) use ($out) {
                    $out->push([
                        'id' => 'user:' . $u->id,
                        'name' => $u->name ?: 'Untitled',
                        'email' => $u->email,
                        'phone' => $u->phone,
                        'address' => '',
                        'role' => 'staff',
                        'source' => 'User',
                    ]);
                });
        }

        if ($filter === 'all' || $filter === 'applicants') {
            $apps = Application::query()
                ->when($searching, function ($q) use ($term) {
                    $this->applyPersonSearch($q, $term, ['full_name', 'email', 'phone', 'whatsapp_number']);
                })
                ->orderByDesc('submitted_at')
                ->limit($searching ? 800 : 500)
                ->get(['id', 'full_name', 'email', 'phone', 'whatsapp_number', 'user_id']);

            $seen = [];
            foreach ($apps as $a) {
                $emailKey = strtolower(trim((string) $a->email));
                $phoneKey = preg_replace('/\D+/', '', (string) ($a->whatsapp_number ?: $a->phone));
                $dedupeKey = $emailKey !== '' ? 'e:'.$emailKey : ($phoneKey !== '' ? 'p:'.$phoneKey : 'id:'.$a->id);
                if (isset($seen[$dedupeKey])) {
                    continue;
                }
                $seen[$dedupeKey] = true;
                $phone = $a->whatsapp_number ?: $a->phone;
                $out->push([
                    'id' => 'applicant:' . $a->id,
                    'name' => $a->full_name ?: 'Untitled',
                    'email' => $a->email,
                    'phone' => $phone,
                    'address' => '',
                    'role' => 'applicant',
                    'source' => 'Applicant',
                ]);
            }
        }

        if ($filter === 'all' || $filter === 'customers') {
            $customerLimit = $searching ? 800 : ($filter === 'customers' ? 500 : 400);
            Customer::query()
                ->when(! $searching, function ($q) {
                    $q->where('is_active', true);
                })
                ->when($searching, function ($q) use ($term) {
                    $this->applyPersonSearch($q, $term, ['name', 'email', 'phone_number', 'company_name', 'address']);
                })
                ->orderByDesc('id')
                ->limit($customerLimit)
                ->get(['id', 'name', 'email', 'phone_number', 'address', 'company_name'])
                ->each(function ($c) use ($out) {
                    $out->push([
                        'id' => 'customer:' . $c->id,
                        'name' => $c->name ?: ($c->company_name ?: 'Untitled'),
                        'email' => $c->email,
                        'phone' => $c->phone_number,
                        'address' => $c->address,
                        'organization' => $c->company_name,
                        'role' => 'customer',
                        'source' => 'Customer',
                    ]);
                });
        }

        $combined = $prior->concat($out)->unique('id')->values();
        if ($searching) {
            return $combined->take(1500);
        }
        if ($filter === 'customers') {
            return $this->capKeepingPrior($combined, $prior, 500);
        }

        return $this->capKeepingPrior($combined, $prior, 800);
    }

    /**
     * Portal users who already appear on a task (assignee or CC), so they never drop off Assign To.
     */
    protected function previousTaskUserIds()
    {
        $assignees = TaskAssignment::query()->whereNotNull('user_id')->distinct()->pluck('user_id');
        $ccs = TaskCc::query()->whereNotNull('user_id')->distinct()->pluck('user_id');

        return $assignees->merge($ccs)->unique()->filter()->values();
    }

    protected function pushBeyondUsers($out, $query, $filter, $term, $limit = null)
    {
        $q = $query
            ->when($filter === 'staff', function ($q) {
                $q->whereIn('role', ['staff', 'admin', 'super_admin', 'task_assignee']);
            })
            ->when($filter === 'customers', function ($q) {
                $q->whereIn('role', ['customer', 'client']);
            })
            ->when($term !== '', function ($q) use ($term) {
                $this->applyPersonSearch($q, $term, ['name', 'email', 'phone']);
            })
            ->orderBy('name');

        if ($limit) {
            $q->limit($limit);
        }

        $q->get(['id', 'name', 'email', 'phone', 'address', 'role'])
            ->each(function ($u) use ($out) {
                $out->push([
                    'id' => 'beyond:' . $u->id,
                    'name' => $u->name ?: 'Untitled',
                    'email' => $u->email,
                    'phone' => $u->phone,
                    'address' => $u->address,
                    'role' => $u->role ?: 'staff',
                    'source' => 'Portal',
                ]);
            });
    }

    /**
     * Match name tokens and phone last 9 digits so +237 / 237 / local numbers all find the same person.
     */
    protected function applyPersonSearch($q, $term, array $columns)
    {
        $allowed = ['name', 'email', 'phone', 'address', 'full_name', 'phone_number', 'company_name', 'whatsapp_number'];
        $columns = array_values(array_intersect($columns, $allowed));
        if (empty($columns)) {
            return;
        }

        $tokens = preg_split('/\s+/', trim((string) $term), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $digits = preg_replace('/\D+/', '', (string) $term);
        $phoneCols = array_values(array_filter($columns, function ($col) {
            return stripos($col, 'phone') !== false || $col === 'whatsapp_number';
        }));

        $q->where(function ($outer) use ($tokens, $digits, $columns, $phoneCols) {
            if (! empty($tokens)) {
                $outer->where(function ($andTokens) use ($tokens, $columns) {
                    foreach ($tokens as $token) {
                        $like = '%' . $token . '%';
                        $andTokens->where(function ($w) use ($like, $columns) {
                            foreach ($columns as $i => $col) {
                                if ($i === 0) {
                                    $w->where($col, 'like', $like);
                                } else {
                                    $w->orWhere($col, 'like', $like);
                                }
                            }
                        });
                    }
                });
            }
            if (strlen($digits) >= 6 && ! empty($phoneCols)) {
                $tail = substr($digits, -9);
                $outer->orWhere(function ($w) use ($tail, $phoneCols) {
                    foreach ($phoneCols as $i => $col) {
                        $sql = "REPLACE(REPLACE(REPLACE(REPLACE(COALESCE({$col}, ''), '+', ''), ' ', ''), '-', ''), '.', '') LIKE ?";
                        if ($i === 0) {
                            $w->whereRaw($sql, ['%' . $tail . '%']);
                        } else {
                            $w->orWhereRaw($sql, ['%' . $tail . '%']);
                        }
                    }
                });
            }
        });
    }

    protected function capKeepingPrior($combined, $prior, $cap)
    {
        $prior = $prior->unique('id')->values();
        $rest = $combined->reject(function ($row) use ($prior) {
            return $prior->contains('id', $row['id']);
        })->values();

        return $prior->concat($rest->take(max(0, $cap - $prior->count())))->values();
    }

    /**
     * Resolve a prefixed assignee ref to a BeyondUser id (creates portal user if needed).
     */
    public function resolveToBeyondUserId($ref)
    {
        $ref = (string) $ref;
        if (Str::startsWith($ref, 'beyond:')) {
            return substr($ref, 7);
        }
        if (Str::startsWith($ref, 'user:')) {
            $user = User::find((int) substr($ref, 5));
            if (! $user) {
                return null;
            }

            return $this->ensureBeyondFromPosUser($user)->id;
        }
        if (Str::startsWith($ref, 'customer:')) {
            $customer = Customer::find((int) substr($ref, 9));
            if (! $customer) {
                return null;
            }

            return $this->ensureBeyondFromCustomer($customer)->id;
        }
        if (Str::startsWith($ref, 'applicant:')) {
            $application = Application::find(substr($ref, 10));
            if (! $application) {
                return null;
            }
            if (! empty($application->user_id) && BeyondUser::find($application->user_id)) {
                return $application->user_id;
            }

            return $this->ensureBeyondFromApplicant($application)->id;
        }

        // Legacy plain UUID
        return $ref;
    }

    public function ensureBeyondFromApplicant(Application $application)
    {
        $email = trim((string) $application->email);
        if ($email === '') {
            $email = 'a' . substr((string) $application->id, 0, 8) . '@applicants.beyondtechworld.com';
        }

        $existing = BeyondUser::where('email', $email)->first();
        $phone = $application->whatsapp_number ?: $application->phone;
        if (! $existing && ! empty($phone)) {
            $existing = BeyondUser::where('phone', $phone)->first();
        }
        if ($existing) {
            if (($existing->role ?? '') === '' || $existing->role === 'staff') {
                // Don't demote admins; only tag plain staff/empty as applicant when from apply flow.
            }
            return $existing;
        }

        $user = BeyondUser::create([
            'id' => (string) Str::uuid(),
            'email' => $email,
            'username' => explode('@', $email)[0] . '_a' . substr((string) $application->id, 0, 6),
            'password_hash' => Hash::make(Str::random(16)),
            'name' => $application->full_name ?: ('Applicant ' . substr((string) $application->id, 0, 8)),
            'role' => 'applicant',
            'status' => 'active',
            'phone' => $phone,
            'address' => null,
            'must_change_credentials' => true,
        ]);

        if (empty($application->user_id)) {
            $application->user_id = $user->id;
            $application->save();
        }

        return $user;
    }

    /**
     * Resolve prefixed directory IDs into full person snapshots (for letters / messaging).
     */
    public function resolveDirectoryPeople(array $ids)
    {
        $ids = array_values(array_unique(array_filter(array_map('strval', $ids))));
        if (empty($ids)) {
            return [];
        }

        $map = [];
        foreach ($this->eligibleForTasks('all', '') as $u) {
            $map[$u['id']] = $u;
        }

        $out = [];
        foreach ($ids as $id) {
            if (isset($map[$id])) {
                $out[] = $map[$id];
                continue;
            }
            if (Str::startsWith($id, 'applicant:')) {
                $application = Application::find(substr($id, 10));
                if ($application) {
                    $out[] = [
                        'id' => $id,
                        'name' => $application->full_name ?: 'Untitled',
                        'email' => $application->email,
                        'phone' => $application->whatsapp_number ?: $application->phone,
                        'address' => '',
                        'role' => 'applicant',
                        'source' => 'Applicant',
                    ];
                }
                continue;
            }
            try {
                $beyondId = $this->resolveToBeyondUserId($id);
                if ($beyondId) {
                    $user = BeyondUser::find($beyondId);
                    if ($user) {
                        $out[] = [
                            'id' => $id,
                            'name' => $user->name,
                            'email' => $user->email,
                            'phone' => $user->phone,
                            'address' => $user->address ?? '',
                            'role' => $user->role ?? '',
                            'source' => 'Resolved',
                        ];
                    }
                }
            } catch (\Exception $e) {
                // skip
            }
        }

        return $out;
    }

    /**
     * Resolve a typed phone to system customer + mobile-money holder (Campay/PawaPay).
     *
     * @return array{ok:bool,found:bool,name:string,address:string,system_name:string,system_address:string,original_name:string,original_address:string,source:?string}
     */
    public function lookupPhoneForForm($raw)
    {
        $empty = [
            'ok' => true,
            'found' => false,
            'name' => '',
            'address' => '',
            'system_name' => '',
            'system_address' => '',
            'original_name' => '',
            'original_address' => '',
            'source' => null,
        ];

        try {
            $phone = WhatsAppPhone::sanitizeForStorage($raw);
        } catch (\Throwable $e) {
            $phone = preg_replace('/\D/', '', (string) $raw);
        }
        if (strlen(preg_replace('/\D/', '', (string) $phone)) < 8) {
            $empty['ok'] = false;

            return $empty;
        }

        $customer = $this->findCustomerByLoosePhone($phone);
        $momo = ['name' => null, 'address' => null, 'source' => null];
        try {
            $momo = app(MobileMoneyHolderService::class)->lookup($phone);
        } catch (\Throwable $e) {
            // Keep directory match even if MoMo is down.
        }

        $systemName = $customer ? trim((string) $customer->name) : '';
        $systemAddress = $customer ? $this->usableAddress($customer->address) : '';
        $originalName = ! empty($momo['name']) ? trim((string) $momo['name']) : '';
        $originalAddress = ! empty($momo['address']) ? $this->usableAddress($momo['address']) : '';

        return [
            'ok' => true,
            'found' => (bool) $customer,
            'name' => $systemName !== '' ? $systemName : $originalName,
            'address' => $systemAddress !== '' ? $systemAddress : $originalAddress,
            'system_name' => $systemName,
            'system_address' => $systemAddress,
            'original_name' => $originalName,
            'original_address' => $originalAddress,
            'source' => $customer ? 'system' : ($momo['source'] ?? null),
        ];
    }

    protected function usableAddress($value)
    {
        $val = trim((string) $value);
        if ($val === '' || strtoupper($val) === 'N/A' || strtoupper($val) === 'NAN') {
            return '';
        }

        return $val;
    }

    public function findCustomerByLoosePhone($phone)
    {
        try {
            $normalized = WhatsAppPhone::sanitizeForStorage($phone);
        } catch (\Throwable $e) {
            $normalized = preg_replace('/\D/', '', (string) $phone);
        }
        $digits = preg_replace('/\D/', '', (string) $normalized);
        if (strlen($digits) < 8) {
            return null;
        }
        $tail = substr($digits, -9);

        $existing = Customer::where('phone_number', $normalized)->first()
            ?: Customer::where('phone_number', $digits)->first()
            ?: Customer::where('phone_number', '+'.$digits)->first();
        if ($existing) {
            return $existing;
        }
        if (strlen($tail) >= 8) {
            return Customer::whereRaw(
                "RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(phone_number,''), '+', ''), ' ', ''), '-', ''), '(', ''), 9) = ?",
                [$tail]
            )->orderByDesc('is_active')->orderByDesc('id')->first();
        }

        return null;
    }

    /**
     * Create or reuse a real POS Customer row (People → Customers) from a quick-add form.
     * Used by Announcements, Job Board supervisors, etc. so the contact is system-wide.
     *
     * @return array{customer:\App\Customer, created:bool, person:array}
     */
    public function findOrCreateCustomerQuick(array $data)
    {
        $name = trim((string) ($data['name'] ?? ''));
        $phone = WhatsAppPhone::sanitizeForStorage($data['phone'] ?? '');
        $email = trim((string) ($data['email'] ?? ''));
        $address = trim((string) ($data['address'] ?? ''));

        if ($name === '' || $phone === '') {
            throw new \InvalidArgumentException('Name and a valid phone / WhatsApp number are required.');
        }

        $groupId = CustomerGroup::where('is_active', true)->value('id')
            ?: CustomerGroup::value('id');
        if (! $groupId) {
            throw new \RuntimeException('No customer group found. Create one under Customers first.');
        }

        // Prefer phone match (including inactive / last-9-digit variants), then exact email.
        $existing = Customer::where('phone_number', $phone)->first();
        if (! $existing) {
            $tail = substr(preg_replace('/\D+/', '', $phone), -9);
            if (strlen($tail) >= 9) {
                $existing = Customer::whereRaw(
                    "REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(phone_number, ''), '+', ''), ' ', ''), '-', ''), '.', '') LIKE ?",
                    ['%' . $tail . '%']
                )->orderByDesc('is_active')->orderByDesc('id')->first();
            }
        }
        if (! $existing && $email !== '') {
            $existing = Customer::whereRaw('LOWER(email) = ?', [strtolower($email)])
                ->orderByDesc('is_active')
                ->first();
        }

        $created = false;
        if ($existing) {
            $customer = $existing;
            // Keep the record active and fill blank optional fields when provided.
            $dirty = false;
            if (! $customer->is_active) {
                $customer->is_active = 1;
                $dirty = true;
            }
            if ($email !== '' && trim((string) $customer->email) === '') {
                $customer->email = $email;
                $dirty = true;
            }
            if ($address !== '' && in_array(trim((string) $customer->address), ['', 'N/A', 'NAN'], true)) {
                $customer->address = $address;
                $dirty = true;
            }
            if ($dirty) {
                $customer->save();
            }
        } else {
            $customer = Customer::create([
                'customer_group_id' => $groupId,
                'user_id' => null,
                'name' => $name,
                'company_name' => null,
                'email' => $email !== '' ? $email : null,
                'phone_number' => $phone,
                'tax_no' => null,
                'address' => $address !== '' ? $address : 'N/A',
                'city' => 'N/A',
                'state' => null,
                'postal_code' => null,
                'country' => null,
                'points' => 0,
                'deposit' => 0,
                'expense' => 0,
                'credit_limit' => 0,
                'is_active' => 1,
            ]);
            $created = true;
        }

        try {
            $this->ensureBeyondFromCustomer($customer);
        } catch (\Throwable $e) {
            // Non-fatal for directory pickers.
        }

        return [
            'customer' => $customer->fresh(),
            'created' => $created,
            'person' => [
                'id' => 'customer:'.$customer->id,
                'name' => $customer->name,
                'email' => $customer->email ?: '',
                'phone' => $customer->phone_number ?: $phone,
                'address' => $customer->address ?: '',
                'role' => 'customer',
                'source' => 'Customer',
            ],
        ];
    }

    public function ensureBeyondFromCustomer(Customer $customer)
    {
        $email = trim((string) $customer->email);
        if ($email === '') {
            $email = 'c' . $customer->id . '@customers.beyondtechworld.com';
        }

        $existing = BeyondUser::whereRaw('LOWER(email) = ?', [strtolower($email)])->first();
        if (! $existing && ! empty($customer->phone_number)) {
            $existing = BeyondUser::where('phone', $customer->phone_number)->first();
        }
        if (! $existing && ! empty($customer->phone_number)) {
            try {
                $existing = app(BeyondAuthService::class)->findByPhone($customer->phone_number);
            } catch (\Throwable $e) {
                $existing = null;
            }
        }
        if ($existing) {
            if (empty($existing->phone) && ! empty($customer->phone_number)) {
                $existing->phone = $customer->phone_number;
                $existing->save();
            }

            return $existing;
        }

        return BeyondUser::create([
            'id' => (string) Str::uuid(),
            'email' => $email,
            'username' => explode('@', $email)[0] . '_' . $customer->id,
            'password_hash' => Hash::make(Str::random(16)),
            'name' => $customer->name ?: ('Customer ' . $customer->id),
            'role' => 'customer',
            'status' => 'active',
            'phone' => $customer->phone_number,
            'address' => $customer->address,
            'must_change_credentials' => true,
        ]);
    }

    public function ensureBeyondFromPosUser(User $user)
    {
        $email = trim((string) $user->email);
        if ($email === '') {
            $email = 'u' . $user->id . '@users.beyondtechworld.com';
        }

        $existing = BeyondUser::where('email', $email)->first();
        if (! $existing && ! empty($user->phone)) {
            $existing = BeyondUser::where('phone', $user->phone)->first();
        }
        if ($existing) {
            return $existing;
        }

        return BeyondUser::create([
            'id' => (string) Str::uuid(),
            'email' => $email,
            'username' => explode('@', $email)[0] . '_u' . $user->id,
            'password_hash' => Hash::make(Str::random(16)),
            'name' => $user->name ?: ('User ' . $user->id),
            'role' => 'staff',
            'status' => 'active',
            'phone' => $user->phone,
            'address' => null,
            'must_change_credentials' => true,
        ]);
    }

    public function customerExportHeaders()
    {
        return [
            'customer_group',
            'name',
            'company_name',
            'email',
            'phone_number',
            'tax_no',
            'address',
            'city',
            'state',
            'postal_code',
            'country',
            'credit_limit',
            'points',
            'is_active',
        ];
    }

    public function userExportHeaders()
    {
        return [
            'name',
            'email',
            'phone',
            'additional_phone',
            'company_name',
            'role_name',
            'is_active',
            'password',
        ];
    }

    public function exportCustomersCsv()
    {
        $headers = $this->customerExportHeaders();
        $rows = [implode(',', $headers)];
        $customers = Customer::with([])->orderBy('name')->get();
        $groups = CustomerGroup::pluck('name', 'id');

        foreach ($customers as $c) {
            $rows[] = $this->csvLine([
                $groups[$c->customer_group_id] ?? 'GENERAL',
                $c->name,
                $c->company_name,
                $c->email,
                $c->phone_number,
                $c->tax_no,
                $c->address,
                $c->city,
                $c->state,
                $c->postal_code,
                $c->country,
                $c->credit_limit,
                $c->points,
                $c->is_active ? '1' : '0',
            ]);
        }

        return implode("\n", $rows) . "\n";
    }

    public function exportUsersCsv()
    {
        $headers = $this->userExportHeaders();
        $rows = [implode(',', $headers)];
        $users = User::where(function ($q) {
            $q->where('is_deleted', false)->orWhereNull('is_deleted');
        })->orderBy('name')->get();
        $roles = Role::pluck('name', 'id');

        foreach ($users as $u) {
            $rows[] = $this->csvLine([
                $u->name,
                $u->email,
                $u->phone,
                $u->additional_phone,
                $u->company_name,
                $roles[$u->role_id] ?? '',
                ($u->is_active || $u->is_active === null) ? '1' : '0',
                '', // password blank on export — set on import if needed
            ]);
        }

        return implode("\n", $rows) . "\n";
    }

    public function importCustomersCsv($path)
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            throw new \RuntimeException('Could not open CSV file.');
        }
        $header = fgetcsv($handle);
        if (! $header) {
            fclose($handle);
            throw new \RuntimeException('CSV is empty.');
        }
        $keys = array_map(function ($h) {
            return preg_replace('/[^a-z_]/', '', strtolower(trim($h)));
        }, $header);

        $created = 0;
        $updated = 0;
        $defaultGroup = CustomerGroup::where('name', 'GENERAL')->first()
            ?: CustomerGroup::orderBy('id')->first();

        while (($cols = fgetcsv($handle)) !== false) {
            if (! isset($cols[0]) || trim((string) $cols[0]) === '') {
                continue;
            }
            $data = [];
            foreach ($keys as $i => $key) {
                $data[$key] = isset($cols[$i]) ? trim((string) $cols[$i]) : '';
            }
            $name = $data['name'] ?? '';
            $phone = $data['phone_number'] ?? ($data['phonenumber'] ?? '');
            if ($name === '' && $phone === '') {
                continue;
            }

            $groupName = $data['customer_group'] ?? ($data['customergroup'] ?? 'GENERAL');
            $group = CustomerGroup::where('name', $groupName)->first() ?: $defaultGroup;

            $customer = null;
            if ($phone !== '') {
                $customer = Customer::where('phone_number', $phone)->first();
            }
            if (! $customer && $name !== '') {
                $customer = Customer::where('name', $name)->where('phone_number', $phone ?: null)->first()
                    ?: Customer::firstOrNew(['name' => $name, 'phone_number' => $phone ?: 'N/A']);
            }
            if (! $customer) {
                $customer = new Customer();
            }
            $isNew = ! $customer->exists;

            $customer->customer_group_id = $group ? $group->id : ($customer->customer_group_id ?: 1);
            $customer->name = $name ?: $customer->name ?: 'Imported Customer';
            $customer->company_name = $data['company_name'] ?? ($data['companyname'] ?? $customer->company_name);
            $customer->email = $data['email'] ?? $customer->email;
            $customer->phone_number = $phone ?: ($customer->phone_number ?: 'N/A');
            $customer->tax_no = $data['tax_no'] ?? ($data['taxno'] ?? $customer->tax_no);
            $customer->address = $data['address'] ?? ($customer->address ?: 'NAN');
            $customer->city = $data['city'] ?? ($customer->city ?: 'NAN');
            $customer->state = $data['state'] ?? $customer->state;
            $customer->postal_code = $data['postal_code'] ?? ($data['postalcode'] ?? $customer->postal_code);
            $customer->country = $data['country'] ?? $customer->country;
            if (isset($data['credit_limit']) && $data['credit_limit'] !== '') {
                $customer->credit_limit = (float) $data['credit_limit'];
            }
            if (isset($data['points']) && $data['points'] !== '') {
                $customer->points = (int) $data['points'];
            }
            $customer->is_active = ! isset($data['is_active']) || $data['is_active'] === '' || in_array($data['is_active'], ['1', 'true', 'yes', 'YES'], true);
            $customer->save();
            $isNew ? $created++ : $updated++;
        }
        fclose($handle);

        return compact('created', 'updated');
    }

    public function importUsersCsv($path)
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            throw new \RuntimeException('Could not open CSV file.');
        }
        $header = fgetcsv($handle);
        if (! $header) {
            fclose($handle);
            throw new \RuntimeException('CSV is empty.');
        }
        $keys = array_map(function ($h) {
            return preg_replace('/[^a-z_]/', '', strtolower(trim($h)));
        }, $header);

        $created = 0;
        $updated = 0;
        $defaultRole = Role::where('name', 'Customer')->first() ?: Role::find(5);

        while (($cols = fgetcsv($handle)) !== false) {
            if (! isset($cols[0]) || trim((string) $cols[0]) === '') {
                continue;
            }
            $data = [];
            foreach ($keys as $i => $key) {
                $data[$key] = isset($cols[$i]) ? trim((string) $cols[$i]) : '';
            }
            $email = $data['email'] ?? '';
            $name = $data['name'] ?? '';
            if ($email === '' && $name === '') {
                continue;
            }

            $user = $email !== '' ? User::where('email', $email)->where(function ($q) {
                $q->where('is_deleted', false)->orWhereNull('is_deleted');
            })->first() : null;
            if (! $user) {
                $user = new User();
                $isNew = true;
            } else {
                $isNew = false;
            }

            $roleName = $data['role_name'] ?? ($data['rolename'] ?? '');
            $role = $roleName !== '' ? Role::where('name', $roleName)->first() : $defaultRole;

            $user->name = $name ?: ($user->name ?: explode('@', $email)[0]);
            $user->email = $email ?: ($user->email ?: ('import_' . Str::random(6) . '@beyondtechworld.com'));
            $user->phone = $data['phone'] ?? ($data['phone_number'] ?? ($data['phonenumber'] ?? $user->phone));
            $user->additional_phone = $data['additional_phone'] ?? ($data['additionalphone'] ?? $user->additional_phone);
            $user->company_name = $data['company_name'] ?? ($data['companyname'] ?? $user->company_name);
            $user->role_id = $role ? $role->id : ($user->role_id ?: 5);
            $user->is_active = ! isset($data['is_active']) || $data['is_active'] === '' || in_array($data['is_active'], ['1', 'true', 'yes', 'YES'], true);
            $user->is_deleted = false;

            $password = $data['password'] ?? '';
            if ($isNew || $password !== '') {
                $user->password = Hash::make($password !== '' ? $password : 'ChangeMe123!');
            }
            $user->save();
            $isNew ? $created++ : $updated++;
        }
        fclose($handle);

        return compact('created', 'updated');
    }

    protected function csvLine(array $fields)
    {
        return implode(',', array_map(function ($v) {
            $v = (string) ($v ?? '');
            if (strpos($v, ',') !== false || strpos($v, '"') !== false || strpos($v, "\n") !== false) {
                return '"' . str_replace('"', '""', $v) . '"';
            }

            return $v;
        }, $fields));
    }
}
