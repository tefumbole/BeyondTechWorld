<?php

namespace App\Services\Wealth;

use Illuminate\Http\Request;

class WealthFilter
{
    public $entityType;
    public $billerId;
    public $userId;
    public $employeeId;
    public $programId;
    public $startDate;
    public $endDate;
    public $month;
    public $year;

    public static function fromRequest(Request $request)
    {
        $f = new self();
        $f->entityType = $request->get('entity_type', 'all');
        $f->billerId = $request->get('biller_id') ?: null;
        $f->userId = $request->get('user_id') ?: null;
        $f->employeeId = $request->get('employee_id') ?: null;
        $f->programId = $request->get('program_id') ?: null;
        $f->month = $request->filled('month') ? (int) $request->get('month') : null;
        $f->year = $request->filled('year') ? (int) $request->get('year') : null;

        $customRange = $request->filled('start_date') && $request->filled('end_date') && ! $request->filled('month');

        if (! $f->year) {
            $f->year = (int) date('Y');
        }

        if (! $f->month && ! $customRange) {
            $f->month = (int) date('n');
        }

        if ($f->month) {
            if ($f->month < 1 || $f->month > 12) {
                $f->month = (int) date('n');
            }
            $f->applyMonthYear();
        } elseif ($customRange) {
            $f->startDate = $request->get('start_date');
            $f->endDate = $request->get('end_date');
        } else {
            $f->month = (int) date('n');
            $f->applyMonthYear();
        }

        return $f;
    }

    public function applyMonthYear()
    {
        $this->startDate = sprintf('%04d-%02d-01', $this->year ?: date('Y'), $this->month);
        $this->endDate = date('Y-m-t', strtotime($this->startDate));
    }

    public function periodLabel()
    {
        if ($this->month) {
            return date('F Y', strtotime($this->startDate));
        }

        return $this->startDate.' – '.$this->endDate;
    }

    public function previousPeriod()
    {
        $p = clone $this;
        if ($this->month) {
            $ts = strtotime($this->startDate.' -1 month');
            $p->month = (int) date('n', $ts);
            $p->year = (int) date('Y', $ts);
            $p->applyMonthYear();

            return $p;
        }
        $start = strtotime($this->startDate);
        $end = strtotime($this->endDate);
        $days = max(1, (int) round(($end - $start) / 86400) + 1);
        $p->endDate = date('Y-m-d', strtotime($this->startDate.' -1 day'));
        $p->startDate = date('Y-m-d', strtotime($p->endDate.' -'.($days - 1).' days'));
        $p->month = null;
        $p->year = null;

        return $p;
    }

    public function periodKey()
    {
        $raw = $this->startDate.'_'.$this->endDate.'_'.($this->entityType ?: 'all').'_'
            .($this->billerId ?: '0').'_'.($this->userId ?: '0').'_'.($this->employeeId ?: '0').'_'
            .($this->programId ?: '0');

        return strlen($raw) <= 64 ? $raw : md5($raw);
    }

    public function applyIncome($query)
    {
        $query->whereDate('occurred_at', '>=', $this->startDate)
            ->whereDate('occurred_at', '<=', $this->endDate);
        if ($this->billerId) {
            $query->where('biller_id', $this->billerId);
        }
        if ($this->userId) {
            $query->where('user_id', $this->userId);
        }
        if ($this->employeeId) {
            $query->where('employee_id', $this->employeeId);
        }
        if ($this->programId) {
            $query->where('program_id', $this->programId);
        }
        if ($this->entityType === 'company') {
            $query->whereNotNull('biller_id');
        } elseif ($this->entityType === 'personal') {
            $query->whereNotNull('user_id')->whereNull('biller_id');
        } elseif ($this->entityType === 'staff') {
            $query->where(function ($q) {
                $q->whereNotNull('employee_id')->orWhereNotNull('user_id');
            });
        } elseif ($this->entityType === 'program') {
            $query->whereNotNull('program_id');
        }

        return $query;
    }

    public function applyExpense($query)
    {
        $query->whereDate('created_at', '>=', $this->startDate)
            ->whereDate('created_at', '<=', $this->endDate);
        if ($this->billerId) {
            $query->where('biller_id', $this->billerId);
        }
        if ($this->userId) {
            $query->where('user_id', $this->userId);
        }
        if ($this->employeeId) {
            $query->where('employee_id', $this->employeeId);
        }
        if ($this->programId) {
            $query->where('program_id', $this->programId);
        }
        if ($this->entityType === 'company') {
            $query->where(function ($q) {
                $q->where('entity_type', 'biller')->orWhere(function ($q2) {
                    $q2->whereNull('entity_type')->whereNotNull('biller_id');
                });
            });
        } elseif ($this->entityType === 'personal') {
            $query->where(function ($q) {
                $q->where('entity_type', 'user')->orWhere(function ($q2) {
                    $q2->whereNull('entity_type')->whereNull('biller_id');
                });
            });
        } elseif ($this->entityType === 'staff') {
            $query->where(function ($q) {
                $q->where('entity_type', 'employee')->orWhereNotNull('employee_id');
            });
        } elseif ($this->entityType === 'program') {
            $query->where(function ($q) {
                $q->where('entity_type', 'program')->orWhereNotNull('program_id');
            });
        }

        return $query;
    }
}
