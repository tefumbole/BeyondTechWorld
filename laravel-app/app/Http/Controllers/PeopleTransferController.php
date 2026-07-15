<?php

namespace App\Http\Controllers;

use App\Services\PeopleDirectoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Spatie\Permission\Models\Role;

class PeopleTransferController extends Controller
{
    protected $all_permission = [];
    protected $people;

    public function __construct(PeopleDirectoryService $people)
    {
        $this->people = $people;
        $this->middleware(function ($request, $next) {
            if (Auth::check()) {
                $role = Role::find(Auth::user()->role_id);
                if ($role) {
                    foreach (Role::findByName($role->name)->permissions as $permission) {
                        $this->all_permission[] = $permission->name;
                    }
                }
            }
            View::share('all_permission', $this->all_permission);

            return $next($request);
        });
    }

    protected function canManage()
    {
        if (in_array('customers-index', $this->all_permission, true)
            || in_array('users-index', $this->all_permission, true)
            || in_array('customers-add', $this->all_permission, true)) {
            return;
        }
        abort(403);
    }

    public function index()
    {
        $this->canManage();

        return view('people.transfer', [
            'customerHeaders' => $this->people->customerExportHeaders(),
            'userHeaders' => $this->people->userExportHeaders(),
        ]);
    }

    public function exportCustomers()
    {
        $this->canManage();
        $csv = $this->people->exportCustomersCsv();
        $filename = 'beyond_customers_export_' . date('Ymd_His') . '.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function exportUsers()
    {
        $this->canManage();
        $csv = $this->people->exportUsersCsv();
        $filename = 'beyond_users_export_' . date('Ymd_His') . '.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function sampleCustomers()
    {
        $this->canManage();
        $headers = $this->people->customerExportHeaders();
        $sample = implode(',', $headers) . "\n"
            . "GENERAL,Jane Demo,Demo Co,jane@example.com,+237670000001,,Mile 6,Bamenda,NW,00237,Cameroon,0,0,1\n";

        return response($sample, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="sample_customers_import.csv"',
        ]);
    }

    public function sampleUsers()
    {
        $this->canManage();
        $headers = $this->people->userExportHeaders();
        $sample = implode(',', $headers) . "\n"
            . "John Staff,john@example.com,+237670000002,,Staff Co,Admin,1,ChangeMe123!\n";

        return response($sample, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="sample_users_import.csv"',
        ]);
    }

    public function importCustomers(Request $request)
    {
        $this->canManage();
        $request->validate(['file' => 'required|file']);
        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        if ($ext !== 'csv') {
            return back()->with('not_permitted', 'Please upload a CSV file for customers.');
        }
        try {
            $result = $this->people->importCustomersCsv($file->getRealPath());
        } catch (\Exception $e) {
            return back()->with('not_permitted', $e->getMessage());
        }

        return back()->with('message', 'Customers import done. Created: ' . $result['created'] . ', Updated: ' . $result['updated'] . '.');
    }

    public function importUsers(Request $request)
    {
        $this->canManage();
        $request->validate(['file' => 'required|file']);
        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        if ($ext !== 'csv') {
            return back()->with('not_permitted', 'Please upload a CSV file for users.');
        }
        try {
            $result = $this->people->importUsersCsv($file->getRealPath());
        } catch (\Exception $e) {
            return back()->with('not_permitted', $e->getMessage());
        }

        return back()->with('message', 'Users import done. Created: ' . $result['created'] . ', Updated: ' . $result['updated'] . '. Leave password blank to keep existing passwords on update; new users without password get ChangeMe123!.');
    }
}
