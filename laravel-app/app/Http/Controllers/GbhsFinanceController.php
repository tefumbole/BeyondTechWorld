<?php

namespace App\Http\Controllers;

use App\Gbhs\GbhsBatch;
use App\Gbhs\GbhsContribution;
use App\Gbhs\GbhsDonate;
use App\Gbhs\GbhsPayment;
use App\Gbhs\GbhsProject;
use App\Gbhs\GbhsRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class GbhsFinanceController extends GbhsAdminController
{
    public function contributions()
    {
        $this->needAny(['gbhs.manage', 'gbhs.finance']);
        $query = GbhsContribution::orderBy('id', 'desc');
        if ($this->batchIds() !== null) {
            $query->whereIn('batch_id', $this->batchIds());
        }

        return view('gbhs.admin.contributions', [
            'rows' => $query->limit(300)->get(),
            'batches' => GbhsBatch::orderBy('year')->get(),
            'projects' => GbhsProject::orderBy('title')->get(),
        ]);
    }

    public function storeContribution(Request $request)
    {
        $this->needAny(['gbhs.manage', 'gbhs.finance']);
        $request->validate([
            'classification' => 'required|in:cash,bank,momo,card,pledge,in_kind,earmarked,logistics,adjustment,reversal',
            'amount' => 'nullable|integer|min:0',
            'contributor_name' => 'nullable|string|max:160',
        ]);
        GbhsContribution::create([
            'batch_id' => $request->batch_id ?: null,
            'project_id' => $request->project_id ?: null,
            'contributor_name' => $request->contributor_name,
            'classification' => $request->classification,
            'amount' => (int) $request->amount,
            'in_kind_qty' => $request->in_kind_qty,
            'in_kind_value' => $request->in_kind_value,
            'verified' => 0,
            'source_note' => $request->source_note,
            'anonymous' => $request->filled('anonymous') ? 1 : 0,
        ]);

        return back()->with('message', 'Line saved as unverified.');
    }

    public function verifyContribution(Request $request, $id)
    {
        $this->needAny(['gbhs.manage', 'gbhs.finance']);
        $row = GbhsContribution::findOrFail($id);
        $row->verified = $request->decision === 'cancel' ? $row->verified : 1;
        if ($request->decision === 'cancel') {
            $row->classification = 'reversal';
            $row->verified = 0;
        }
        $row->verified_by = Auth::id();
        $row->verified_at = now();
        if ($request->hasFile('receipt')) {
            $dir = public_path('gbhs/receipts');
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $name = $row->id.'-'.time().'.'.$request->file('receipt')->getClientOriginalExtension();
            $request->file('receipt')->move($dir, $name);
            $row->receipt = 'gbhs/receipts/'.$name;
        }
        $row->save();

        return back()->with('message', 'Contribution updated.');
    }

    public function reconcile()
    {
        $this->needAny(['gbhs.manage', 'gbhs.finance']);
        $rows = GbhsContribution::query()->get()->map(function ($row) {
            return $row->toArray();
        })->all();

        return view('gbhs.admin.reconcile', [
            'summary' => GbhsRules::summarize($rows),
            'rows' => GbhsContribution::orderBy('id')->get(),
        ]);
    }

    public function export()
    {
        $this->needAny(['gbhs.manage', 'gbhs.finance']);
        $handle = fopen('php://temp', 'w+');
        fputcsv($handle, ['id', 'contributor', 'classification', 'amount', 'in_kind_qty', 'in_kind_value', 'verified', 'note']);
        foreach (GbhsContribution::orderBy('id')->get() as $row) {
            fputcsv($handle, [
                $row->id, $row->contributor_name, $row->classification, $row->amount,
                $row->in_kind_qty, $row->in_kind_value, $row->verified ? 'yes' : 'no', $row->source_note,
            ]);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="gbhs-contributions.csv"',
        ]);
    }

    public function projects()
    {
        $this->needAny(['gbhs.manage', 'gbhs.finance', 'gbhs.communications']);
        $projects = GbhsProject::orderBy('id', 'desc')->get();
        $raised = [];
        foreach ($projects as $project) {
            $raised[$project->id] = (int) GbhsContribution::where('project_id', $project->id)
                ->where('verified', 1)->whereNotNull('payment_id')->sum('amount');
        }

        return view('gbhs.admin.projects', compact('projects', 'raised'));
    }

    public function storeProject(Request $request)
    {
        $this->needAny(['gbhs.manage', 'gbhs.finance']);
        $request->validate(['title' => 'required|string|max:160']);
        $project = GbhsProject::create([
            'title' => $request->title,
            'slug' => Str::slug($request->title).'-'.time(),
            'description' => $request->description,
            'target' => $request->target,
            'status' => $request->status ?: 'planned',
            'starts_on' => $request->starts_on,
            'ends_on' => $request->ends_on,
            'committee' => $request->committee,
            'published' => $request->filled('published') ? 1 : 0,
        ]);
        if ($request->hasFile('image')) {
            $dir = public_path('gbhs/projects');
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $name = $project->id.'.'.$request->file('image')->getClientOriginalExtension();
            $request->file('image')->move($dir, $name);
            $project->image = 'gbhs/projects/'.$name;
            $project->save();
        }

        return back()->with('message', 'Project saved.');
    }

    public function payments()
    {
        $this->needAny(['gbhs.manage', 'gbhs.finance']);

        return view('gbhs.admin.payments', [
            'payments' => GbhsPayment::orderBy('id', 'desc')->limit(100)->get(),
        ]);
    }

    public function recordBank($id)
    {
        $this->needAny(['gbhs.manage', 'gbhs.finance']);
        $payment = GbhsPayment::findOrFail($id);
        GbhsDonate::recordBank($payment, Auth::id());

        return back()->with('message', 'Bank transfer recorded as received.');
    }

    public function reports()
    {
        $this->needAny(['gbhs.manage', 'gbhs.finance']);
        $rows = GbhsContribution::query()->get()->map(function ($row) {
            return $row->toArray();
        })->all();

        return view('gbhs.admin.reports', [
            'summary' => GbhsRules::summarize($rows),
            'byBatch' => GbhsContribution::select('batch_id', \DB::raw('SUM(amount) as total'))
                ->whereNotIn('classification', ['reversal', 'in_kind'])
                ->groupBy('batch_id')
                ->get(),
            'batches' => GbhsBatch::pluck('name', 'id'),
        ]);
    }

}

