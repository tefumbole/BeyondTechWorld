<?php

namespace App\Http\Controllers\Wealth;

use App\Services\Wealth\WealthFilter;
use App\Services\Wealth\WealthReportService;
use Illuminate\Http\Request;
use PDF;

class WealthReportController extends WealthBaseController
{
    public function index(Request $request, WealthReportService $reports)
    {
        if (! $this->can('wealth.reports.view')) {
            return $this->deny();
        }
        $filter = WealthFilter::fromRequest($request);
        $kind = $request->get('kind', 'summary');
        $data = $reports->summary($filter);

        return view('wealth.reports', $this->lookups() + [
            'wmTab' => 'reports',
            'filter' => $filter,
            'kind' => $kind,
            'data' => $data,
        ]);
    }

    public function pdf(Request $request, WealthReportService $reports)
    {
        if (! $this->can('wealth.reports.view')) {
            return $this->deny();
        }
        $filter = WealthFilter::fromRequest($request);
        $data = $reports->summary($filter);
        $pdf = PDF::loadView('wealth.report_pdf', compact('filter', 'data'))->setPaper('A4', 'portrait');

        return $pdf->download('wealth-report-'.$filter->startDate.'-'.$filter->endDate.'.pdf');
    }

    public function csv(Request $request, WealthReportService $reports)
    {
        if (! $this->can('wealth.reports.view')) {
            return $this->deny();
        }
        $filter = WealthFilter::fromRequest($request);
        $data = $reports->summary($filter);
        $kind = $request->get('kind', 'income');
        $filename = 'wealth-'.$kind.'-'.$filter->startDate.'.csv';
        $callback = function () use ($data, $kind) {
            $out = fopen('php://output', 'w');
            if ($kind === 'expenses') {
                fputcsv($out, ['Date', 'Reference', 'Title', 'Category', 'Amount', 'Bucket']);
                foreach ($data['expense_rows'] as $row) {
                    fputcsv($out, [
                        $row->created_at, $row->reference_no, $row->title,
                        optional($row->expenseCategory)->name, $row->amount, $row->allocation_bucket_id,
                    ]);
                }
            } else {
                fputcsv($out, ['Date', 'Source', 'Reference', 'Customer', 'Amount', 'Status']);
                foreach ($data['income_rows'] as $row) {
                    fputcsv($out, [
                        $row->occurred_at, $row->source_type, $row->reference,
                        optional($row->customer)->name, $row->amount, $row->status,
                    ]);
                }
            }
            fclose($out);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
