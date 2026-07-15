<?php

namespace App\Services;

use App\BeyondUser;
use App\Customer;
use App\CustomerGroup;
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
        $like = '%' . $term . '%';

        if ($filter === 'all' || $filter === 'staff') {
            BeyondUser::query()
                ->when($filter === 'staff', function ($q) {
                    $q->whereIn('role', ['staff', 'admin', 'super_admin', 'task_assignee']);
                })
                ->when($term !== '', function ($q) use ($like) {
                    $q->where(function ($w) use ($like) {
                        $w->where('name', 'like', $like)
                            ->orWhere('email', 'like', $like)
                            ->orWhere('phone', 'like', $like);
                    });
                })
                ->orderBy('name')
                ->limit(150)
                ->get(['id', 'name', 'email', 'phone', 'address', 'role'])
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

            User::query()
                ->where(function ($q) {
                    $q->where('is_deleted', false)->orWhereNull('is_deleted');
                })
                ->where(function ($q) {
                    $q->where('is_active', true)->orWhereNull('is_active');
                })
                ->when($term !== '', function ($q) use ($like) {
                    $q->where(function ($w) use ($like) {
                        $w->where('name', 'like', $like)
                            ->orWhere('email', 'like', $like)
                            ->orWhere('phone', 'like', $like);
                    });
                })
                ->orderBy('name')
                ->limit(200)
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

        if ($filter === 'all' || $filter === 'customers') {
            Customer::query()
                ->where('is_active', true)
                ->when($term !== '', function ($q) use ($like) {
                    $q->where(function ($w) use ($like) {
                        $w->where('name', 'like', $like)
                            ->orWhere('email', 'like', $like)
                            ->orWhere('phone_number', 'like', $like)
                            ->orWhere('company_name', 'like', $like)
                            ->orWhere('address', 'like', $like);
                    });
                })
                ->orderBy('name')
                ->limit(300)
                ->get(['id', 'name', 'email', 'phone_number', 'address', 'company_name'])
                ->each(function ($c) use ($out) {
                    $out->push([
                        'id' => 'customer:' . $c->id,
                        'name' => $c->name ?: ($c->company_name ?: 'Untitled'),
                        'email' => $c->email,
                        'phone' => $c->phone_number,
                        'address' => $c->address,
                        'role' => 'customer',
                        'source' => 'Customer',
                    ]);
                });
        }

        return $out->unique('id')->values()->take(400);
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

        // Legacy plain UUID
        return $ref;
    }

    public function ensureBeyondFromCustomer(Customer $customer)
    {
        $email = trim((string) $customer->email);
        if ($email === '') {
            $email = 'c' . $customer->id . '@customers.beyondtechworld.com';
        }

        $existing = BeyondUser::where('email', $email)->first();
        if (! $existing && ! empty($customer->phone_number)) {
            $existing = BeyondUser::where('phone', $customer->phone_number)->first();
        }
        if ($existing) {
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
