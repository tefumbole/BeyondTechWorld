<?php

namespace App\Services\Property;

use App\Customer;
use App\Property\Property;
use App\Property\PropertyUnit;
use App\Property\Tenancy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TenancyService
{
    protected $log;

    public function __construct(PropertyActivityLogger $log)
    {
        $this->log = $log;
    }

    public function createProperty(array $data)
    {
        $row = Property::create([
            'name' => $data['name'],
            'code' => $data['code'],
            'address' => isset($data['address']) ? $data['address'] : null,
            'city' => isset($data['city']) ? $data['city'] : null,
            'country' => isset($data['country']) ? $data['country'] : null,
            'description' => isset($data['description']) ? $data['description'] : null,
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : true,
            'manager_employee_id' => isset($data['manager_employee_id']) ? $data['manager_employee_id'] : null,
            'created_by' => isset($data['created_by']) ? $data['created_by'] : null,
        ]);
        $this->log->write('property_created', 'property', $row->id, ['code' => $row->code]);

        return $row;
    }

    public function createUnit(array $data)
    {
        $property = Property::find($data['property_id']);
        if (! $property) {
            return ['ok' => false, 'error' => 'property_missing'];
        }
        $row = PropertyUnit::create([
            'property_id' => $property->id,
            'code' => $data['code'],
            'name' => $data['name'],
            'unit_type' => isset($data['unit_type']) ? $data['unit_type'] : 'unit',
            'description' => isset($data['description']) ? $data['description'] : null,
            'rent_amount' => isset($data['rent_amount']) ? $data['rent_amount'] : 0,
            'billing_frequency' => isset($data['billing_frequency']) ? $data['billing_frequency'] : 'monthly',
            'status' => isset($data['status']) ? $data['status'] : 'AVAILABLE',
        ]);
        $this->log->write('unit_created', 'unit', $row->id, ['code' => $row->code, 'property_id' => $property->id]);

        return ['ok' => true, 'unit' => $row];
    }

    public function open(array $data)
    {
        $customer = Customer::find(isset($data['customer_id']) ? $data['customer_id'] : 0);
        $unit = PropertyUnit::find(isset($data['unit_id']) ? $data['unit_id'] : 0);
        if (! $customer || ! $unit) {
            return ['ok' => false, 'error' => 'missing_party'];
        }
        $status = isset($data['status']) ? strtoupper((string) $data['status']) : 'ACTIVE';
        if (! in_array($status, ['DRAFT', 'ACTIVE', 'ENDING', 'ENDED', 'TERMINATED'], true)) {
            return ['ok' => false, 'error' => 'bad_status'];
        }
        if ($status === 'ACTIVE' && $this->unitTaken($unit->id, null)) {
            $this->log->write('tenancy_overlap_denied', 'unit', $unit->id, []);

            return ['ok' => false, 'error' => 'unit_occupied'];
        }
        $path = isset($data['agreement_path']) ? (string) $data['agreement_path'] : '';
        if ($path !== '' && ! $this->safeAgreement($path)) {
            return ['ok' => false, 'error' => 'bad_agreement_path'];
        }
        try {
            $row = DB::transaction(function () use ($data, $customer, $unit, $status, $path) {
                if ($status === 'ACTIVE' && $this->unitTaken($unit->id, null)) {
                    throw new \RuntimeException('unit_occupied');
                }
                $tenancy = Tenancy::create([
                    'customer_id' => $customer->id,
                    'property_id' => $unit->property_id,
                    'unit_id' => $unit->id,
                    'start_date' => $data['start_date'],
                    'end_date' => isset($data['end_date']) ? $data['end_date'] : null,
                    'rent_amount' => $data['rent_amount'],
                    'billing_frequency' => isset($data['billing_frequency']) ? $data['billing_frequency'] : 'monthly',
                    'security_deposit' => isset($data['security_deposit']) ? $data['security_deposit'] : null,
                    'due_day' => max(1, min(28, (int) (isset($data['due_day']) ? $data['due_day'] : 1))),
                    'status' => $status,
                    'agreement_path' => $path !== '' ? $path : null,
                    'created_by' => isset($data['created_by']) ? $data['created_by'] : null,
                ]);
                if ($status === 'ACTIVE') {
                    $unit->status = 'OCCUPIED';
                    $unit->save();
                }

                return $tenancy;
            });
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'unit_occupied'];
        }
        $this->log->write('tenancy_opened', 'tenancy', $row->id, [
            'customer_id' => $customer->id,
            'unit_id' => $unit->id,
            'status' => $status,
        ]);

        return ['ok' => true, 'tenancy' => $row];
    }

    public function unitTaken($unitId, $ignoreId)
    {
        $query = Tenancy::where('unit_id', (int) $unitId)->where('status', 'ACTIVE');
        if ($ignoreId) {
            $query->where('id', '!=', (int) $ignoreId);
        }

        return $query->exists();
    }

    public function safeAgreement($path)
    {
        if ($path === '' || preg_match('#\.\.|://#', $path)) {
            return false;
        }
        $real = realpath($path);
        $root = realpath(storage_path('app'));

        return $real && $root && is_file($real) && strpos($real, $root) === 0;
    }

    public function end(Tenancy $tenancy, $status)
    {
        $status = strtoupper((string) $status);
        if (! in_array($status, ['ENDING', 'ENDED', 'TERMINATED'], true)) {
            return ['ok' => false, 'error' => 'bad_status'];
        }
        $tenancy->status = $status;
        if (in_array($status, ['ENDED', 'TERMINATED'], true) && ! $tenancy->end_date) {
            $tenancy->end_date = Carbon::today()->toDateString();
        }
        $tenancy->save();
        if (in_array($status, ['ENDED', 'TERMINATED'], true)) {
            $unit = PropertyUnit::find($tenancy->unit_id);
            if ($unit && ! $this->unitTaken($unit->id, null)) {
                $unit->status = 'AVAILABLE';
                $unit->save();
            }
        }
        $this->log->write('tenancy_status', 'tenancy', $tenancy->id, ['status' => $status]);

        return ['ok' => true, 'tenancy' => $tenancy];
    }
}
