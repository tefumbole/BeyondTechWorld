<?php

namespace App\Http\Controllers;

use App\Asset;
use App\AssetCategory;
use App\AssetExpense;
use App\AssetSale;
use App\AssetSaleDetail;
use App\AssetTransfer;
use App\Department;
use App\Dispose;
use App\Donor;
use App\GeneralSetting;
use App\ImageLibrary;
use App\Region;
use App\Station;
use App\Transfer;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Spatie\Permission\Models\Role;

class AssetController extends Controller
{
    public function __construct() {

        $this->middleware(function ($request, $next) {
            $role = Role::find(Auth::user()->role_id);
            $permissions = Role::findByName($role->name)->permissions;

            foreach ($permissions as $permission) {
                $all_permission[] = $permission->name;
            }
            View::share ( 'all_permission', $all_permission);

            return $next($request);
        });
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $data = Asset::with('category', 'donor', 'region', 'station')->where('is_active', 1)->orderByDesc('id')->get();

        return view('fixed_asset.asset.index', compact('data'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $category = AssetCategory::where('is_active', true)->get();
        $donor = Donor::where('is_active', true)->get();
        $department = Department::where('is_active', true)->get();
        $region = Region::get();
        $station = Station::get();

        return view('fixed_asset.asset.create', compact('category', 'donor', 'region', 'station', 'department'));

    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $data = $request->except('image');
        $data['is_active'] = true;
        if ($request->serial) {
            $data['serial_no'] = $request->serial;
        } else {
            $department = Department::where('id', $request->department_id)->select('code')->first();
            @$asset_id = Asset::where('department_id', $data['department_id'])->where('is_active', 1)->count('id');
            if(!$asset_id) {
                $asset_id = 0;
            }
            $zero = substr('0000000', strlen($asset_id));
            $asset_id++;
            $data['serial_no'] = $department->code . $zero . $asset_id;
        }
        $image = $request->image;
        if (isset($image[0])) {
            $imageName = date("Ymdhis").$image[0]->getClientOriginalName();
            $image[0]->move('public/images/assets', $imageName);
            $data['image'] = $imageName;
        }
        $asset = Asset::create($data);

        $images = $request->image;
        if ($images) {
            foreach ($images as $key => $image) {
                if($key == 0) {
                    continue;
                }
                $imageName = date("Ymdhis").$image->getClientOriginalName();
                $image->move('public/images/assets', $imageName);
                ImageLibrary::create(['asset_id' => $asset->id, 'image' => $imageName]);
            }
        }

        $message = 'Data inserted successfully';
        return redirect('asset')->with('message', $message);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Asset  $asset
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $data = Asset::with('category', 'donor', 'region', 'station', 'images')->where('id', $id)->first();
        $assetTransfer = AssetTransfer::with('assets', 'fromDepartment', 'toDepartment')->where('parent_id', $id)->get();

        return view('fixed_asset.asset.show', compact('data', 'assetTransfer'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Asset  $asset
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $category = AssetCategory::where('is_active', true)->get();
        $donor = Donor::where('is_active', true)->get();
        $department = Department::where('is_active', true)->get();
        $region = Region::get();
        $station = Station::get();
        $data = Asset::with('category', 'donor', 'region', 'station')->where('id', $id)->first();

        return view('fixed_asset.asset.edit', compact('category', 'donor', 'region', 'station', 'department', 'data'));
    }

    public function DepartmentSearch(Request $request){
        $id = $request->value;
        if ($id) {
            $department = Department::where('id', $id)->select('code')->first();
            $asset_id = Asset::where('department_id', $id)->where('is_active', 1)->count('id');
            if(!$asset_id) {
                $asset_id = 0;
            }
            $asset_id++;
            $zero = substr('0000000', strlen($asset_id));

            $data = $department->code . $zero . $asset_id;

            return $data;
        }
        return '';
    }
    public function update(Request $request, $id)
    {
        $input = $request->except('image');
        $image = $request->image;

        $data = Asset::findOrFail($id);
        $data->update($input);

        $images = $request->image;
        if ($image) {
            foreach ($images as $key => $image) {
                $imageName = date("Ymdhis").$image->getClientOriginalName();;
                $image->move('public/images/assets', $imageName);
                ImageLibrary::create(['asset_id' => $data->id, 'image' => $imageName]);
            }
        }
        return redirect('asset')->with('message','Data updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Asset  $asset
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $data = Asset::find($id);
        $data->is_active = false;
        $data->save();
        return back()->with('not_permitted','Data deleted successfully');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Asset  $asset
     * @return \Illuminate\Http\Response
     */
    public function assetImageDelete($id)
    {
        $data = ImageLibrary::find($id);
        $data->delete();
        return back()->with('not_permitted','Image deleted successfully');
    }

    public function Report (){
        $category_count = Asset::where('category_id', '!=', null)->where('is_active', 1)->count();
        $donor_count = Asset::where('donor_id', '!=', null)->where('is_active', 1)->count();
        $region_count = Asset::where('region_id', '!=', null)->where('is_active', 1)->count();
        $station_count = Asset::where('station_id', '!=', null)->where('is_active', 1)->count();
        $department_count = Asset::where('department_id', '!=', null)->where('is_active', 1)->count();

        $copy_count = AssetExpense::where('activity_type', 'copy')->count();
        $automobile_count = AssetExpense::where('activity_type', 'milage')->count();
        $repair_count = AssetExpense::where('activity_type', 'Repair')->count();
        $general_count = AssetExpense::where('activity_type', 'General Activity')->count();
        $dispose_count = Dispose::count();
        $transfer_count = AssetTransfer::count();

        return view('fixed_asset.report.asset_report', compact('repair_count', 'transfer_count', 'dispose_count', 'copy_count', 'automobile_count', 'general_count', 'category_count', 'donor_count', 'region_count', 'station_count', 'department_count'));
    }

//    public function Dashboard (){
//        $dataa = AssetCategory::where('is_active', true)->get();
//
//        $end_date = date("Y").'-'.date("m").'-'.date('t', mktime(0, 0, 0, date("m"), 1, date("Y")));
//
////        total
//        $copy_sum = AssetExpense::where('activity_type', 'copy')->sum('amount');
//        $automobile_sum = AssetExpense::where('activity_type', 'milage')->sum('amount');
//        $repair_sum = AssetExpense::where('activity_type', 'Repair')->sum('amount');
//        $general_sum = AssetExpense::where('activity_type', 'General Activity')->sum('amount');
//        $dispose_sum = Dispose::sum('price');
//        $transfer_sum = AssetTransfer::sum('price');
//        $total_sale_sum = AssetSale::sum('buyer_total_amount');
//        $total_asset_sum = Asset::where('is_active', 1)->sum('price');
//        $total_asset_purchase_sum = Asset::where('is_active', 1)->sum('price');
//        $expense_sum = AssetExpense::where('type', 'expense')->sum('amount');
//        $deprication = 0;
//        $one_day_deprication = 0;
//        $monthly_deprication = 0;
//        $yearly_deprication = 0;
//        $book_value = 0;
//
//        $assets = Asset::where('is_active', 1)->get();
//        foreach ($assets as $asset) {
//            $one_day_deprication += $this->depricationCaluculateForDays($asset, new DateTime('yesterday'));
//            $calculation = $this->depricationCaluculate($asset);
//            $deprication += $calculation['depreciation'];
//            $book_value += $calculation['book_value'];
//        }
//
////        yearly
//        $start_date = new DateTime(date("Y").'-01-01');
//        $curent_date = new DateTime();
//        foreach ($assets as $asset) {
//            $yearly_deprication += $this->depricationCaluculateForDays($asset, $start_date);
//        }
//        $start_date = date("Y").'-01-01';
//        $yearly_copy_sum = AssetExpense::where('activity_type', 'copy')->whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('amount');
//        $yearly_automobile_sum = AssetExpense::where('activity_type', 'milage')->whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('amount');
//        $yearly_repair_sum = AssetExpense::where('activity_type', 'Repair')->whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('amount');
//        $yearly_general_sum = AssetExpense::where('activity_type', 'General Activity')->whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('amount');
//        $yearly_dispose_sum = Dispose::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('price');
//        $yearly_transfer_sum = AssetTransfer::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('price');
//        $yearly_total_sale_sum = AssetSale::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('buyer_total_amount');
//        $yearly_total_asset_sum = Asset::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->where('is_active', 1)->sum('price');
//        $yearly_total_asset_purchase_sum = Asset::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('price');
//        $yearly_expense_sum = AssetExpense::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->where('type', 'expense')->sum('amount');
//
//        //        monthly
//        $start_date = new DateTime(date("Y").'-'.date("m").'-'.'01');
//        $curent_date = new DateTime();
//        foreach ($assets as $asset) {
//            $monthly_deprication += $this->depricationCaluculateForDays($asset, $start_date);
//        }
//        $start_date = date("Y").'-'.date("m").'-'.'01';
//        $monthly_copy_sum = AssetExpense::where('activity_type', 'copy')->whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('amount');
//        $monthly_automobile_sum = AssetExpense::where('activity_type', 'milage')->whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('amount');
//        $monthly_repair_sum = AssetExpense::where('activity_type', 'Repair')->whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('amount');
//        $monthly_general_sum = AssetExpense::where('activity_type', 'General Activity')->whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('amount');
//        $monthly_dispose_sum = Dispose::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('price');
//        $monthly_transfer_sum = AssetTransfer::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('price');
//        $monthly_total_sale_sum = AssetSale::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('buyer_total_amount');
//        $monthly_total_asset_sum = Asset::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->where('is_active', 1)->sum('price');
//        $monthly_total_asset_purchase_sum = Asset::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('price');
//        $monthly_expense_sum = AssetExpense::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->where('type', 'expense')->sum('amount');
//
//
//        //        daily
//
//        $start_date = new DateTime(date("Y-m-d"));
//        $curent_date = new DateTime();
//        $daily_deprication = $one_day_deprication * ($start_date->diff($curent_date)->days + 1);
//        $start_date = date("Y-m-d");
//        $daily_copy_sum = AssetExpense::where('activity_type', 'copy')->whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('amount');
//        $daily_automobile_sum = AssetExpense::where('activity_type', 'milage')->whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('amount');
//        $daily_repair_sum = AssetExpense::where('activity_type', 'Repair')->whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('amount');
//        $daily_general_sum = AssetExpense::where('activity_type', 'General Activity')->whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('amount');
//        $daily_dispose_sum = Dispose::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('price');
//        $daily_transfer_sum = AssetTransfer::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('price');
//        $daily_total_sale_sum = AssetSale::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('buyer_total_amount');
//        $daily_total_asset_sum = Asset::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->where('is_active', 1)->sum('price');
//        $daily_total_asset_purchase_sum = Asset::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->sum('price');
//        $daily_expense_sum = AssetExpense::whereDate('created_at', '>=' , $start_date)->whereDate('created_at', '<=' , $end_date)->where('type', 'expense')->sum('amount');
//
//        return view('fixed_asset.asset.asset_dashboard', compact(
//            'copy_sum', 'automobile_sum', 'repair_sum', 'general_sum', 'dispose_sum', 'transfer_sum', 'deprication', 'book_value',
//            'total_sale_sum', 'total_asset_sum', 'total_asset_purchase_sum', 'expense_sum',
//            'yearly_copy_sum', 'yearly_automobile_sum', 'yearly_repair_sum', 'yearly_general_sum', 'yearly_dispose_sum', 'yearly_transfer_sum',
//            'yearly_total_sale_sum', 'yearly_total_asset_sum', 'yearly_total_asset_purchase_sum', 'yearly_expense_sum', 'yearly_deprication',
//            'monthly_copy_sum', 'monthly_automobile_sum', 'monthly_repair_sum', 'monthly_general_sum', 'monthly_dispose_sum', 'monthly_transfer_sum',
//            'monthly_total_sale_sum', 'monthly_total_asset_sum', 'monthly_total_asset_purchase_sum', 'monthly_expense_sum', 'monthly_deprication',
//            'daily_copy_sum', 'daily_automobile_sum', 'daily_repair_sum', 'daily_general_sum', 'daily_dispose_sum', 'daily_transfer_sum',
//            'daily_total_sale_sum', 'daily_total_asset_sum', 'daily_total_asset_purchase_sum', 'daily_expense_sum', 'daily_deprication',
//            'dataa'
//        ));
//    }


    public function Dashboard(Request $request) {
        $dataa = AssetCategory::where('is_active', true)
            ->select('id', 'name', 'parent_id')
            ->get();

// Group categories by parent_id
        $groupedByParent = $dataa->groupBy('parent_id');

// Add child_ids array to each category
        $dataa = $dataa->map(function ($category) use ($groupedByParent) {
            $children = $groupedByParent->get($category->id, collect());
            $category->child_ids = $children->pluck('id')->toArray(); // just IDs
            return $category;
        });

        $departments = Department::where('is_active', true)->get();
        $donors = Donor::where('is_active', true)->get();
        $stations = Station::get();
        $regions = Region::get();
//        dd($request->all());
        $department_id = $request->department_id ?? null;
        $region_id = $request->region_id ?? null;
        $station_id = $request->station_id ?? null;
        $donor_id = $request->donor_id ?? null;


        // Check if user provided date filters, otherwise fetch min/max dates from DB
        $search_start_date = $start_date = $request->input('start_date');
        $search_end_date = $end_date = $request->input('end_date');

        // If no start_date or end_date is provided, fetch from the database
        $min_created_at = Asset::where('is_active', 1)->min('created_at');
        if (!$start_date || $start_date < $min_created_at) {
            $start_date = $min_created_at;
        }
        if (!$end_date) {
            $end_date = (new DateTime())->modify('+1 day')->format('Y-m-d');
        }
        // Convert dates to Y-m-d format
        $start_date = $start_date ? date('Y-m-d', strtotime($start_date)) : date('Y-01-01');
        $end_date = $end_date ? date('Y-m-d', strtotime($end_date)) : date('Y-m-d');


        // Fetch assets based on the filtered date range
        $assets = Asset::where('is_active', 1)->whereBetween('created_at', [$start_date, $end_date]);
        $assets = self::dashboardCalculation($department_id, $region_id, $station_id, $donor_id, $assets);
        $assets = $assets->get();

        $deprication = 0;
        $book_value = 0;

        // Depreciation Calculation for the filtered assets
        foreach ($assets as $asset) {
            // Depreciation Calculation for each asset
            $calculation = $this->depricationCaluculateForDashboard($asset, $start_date, $end_date);
            $deprication += $calculation['depreciation'];
            $book_value += $calculation['book_value'];
        }

        // Filtered Queries for expenses and asset-related data
        $copy_sum = AssetExpense::where('activity_type', 'copy')
            ->whereBetween('date', [$start_date, $end_date])
            ->sum('amount');

        $automobile_sum = AssetExpense::where('activity_type', 'milage')
            ->whereBetween('date', [$start_date, $end_date])
            ->sum('amount');

        $repair_sum = AssetExpense::where('activity_type', 'Repair')
            ->whereBetween('date', [$start_date, $end_date])
            ->sum('amount');

        $general_sum = AssetExpense::where('activity_type', 'General Activity')
            ->whereBetween('date', [$start_date, $end_date])
            ->sum('amount');

        $dispose_sum = Dispose::whereBetween('created_at', [$start_date, $end_date])
            ->sum('price');

        $transfer_sum = AssetTransfer::whereBetween('created_at', [$start_date, $end_date])
            ->sum('price');

        $total_sale_sum = AssetSale::whereBetween('created_at', [$start_date, $end_date])
            ->sum('buyer_total_amount');

        $total_asset_purchase_sum = Asset::whereBetween('created_at', [$start_date, $end_date])->where('is_active', 1);
        $total_asset_purchase_sum = self::dashboardCalculation($department_id, $region_id, $station_id, $donor_id, $total_asset_purchase_sum);
        $total_asset_purchase_sum = $total_asset_purchase_sum->sum('price');


        $total_asset_sum = Asset::where('is_active', 1);
        $total_asset_sum = self::dashboardCalculation($department_id, $region_id, $station_id, $donor_id, $total_asset_sum);
        $total_asset_sum = $total_asset_sum->sum('price');



        $expense_sum = AssetExpense::whereBetween('date', [$start_date, $end_date])
            ->where('type', 'expense')
            ->sum('amount');

        // Return the view with the filtered data
        return view('fixed_asset.asset.asset_dashboard', compact(
            'copy_sum', 'automobile_sum', 'repair_sum', 'general_sum',
            'dispose_sum', 'transfer_sum', 'total_sale_sum',
            'total_asset_sum', 'total_asset_purchase_sum', 'expense_sum',
            'dataa', 'deprication', 'book_value', 'assets', 'start_date', 'end_date', 'search_end_date', 'search_start_date',
            'departments', 'stations', 'regions', 'donors',
            'department_id', 'station_id', 'region_id', 'donor_id'
        ));
    }

    public static function dashboardCalculation($department_id = null, $region_id = null, $station_id = null, $donor_id = null, $query) {
        if ($department_id) {
            $query = $query->where('department_id', $department_id);
        }
        if ($region_id) {
            $query = $query->where('region_id', $region_id);
        }
        if ($station_id) {
            $query = $query->where('station_id', $station_id);
        }
        if ($donor_id) {
            $query = $query->where('donor_id', $donor_id);
        }
        return $query;
    }



    public function DashboardCategory($id)
    {
        $data = Asset::with('category', 'donor', 'region', 'station')->where('category_id', $id)->where('is_active', 1)->get();

        return view('fixed_asset.asset.dashboard_index', compact('data'));
    }

    public function Department (){
        $start_date = date('y-m-01');
        $end_date = date('y-m-d');
        $warehouse_id = 0;
        $dataa = Department::where('is_active', true)->get();
        return view('fixed_asset.report.department_report', compact('dataa', 'start_date', 'end_date', 'warehouse_id'));
    }
    public function DepartmentData (Request $request){

        $request_data = $request->all();
        $start_date = $request_data['start_date'];
        $end_date = $request_data['end_date'];
        $department_id = $request_data['department_id'];
        $filter_id = $request_data['filter_id'];
        $dataa = Department::where('is_active', true)->get();

        if ($end_date && $start_date) {
            $interval = strtotime($end_date) - strtotime($start_date);
            $diff_date = ($interval / 3600 / 24) + 1;
            $yesterday = date('Y-m-d', strtotime($start_date));
            $tomorrow = date('Y-m-d', strtotime($end_date . "+1 days"));

            $data = Asset::with('category', 'donor', 'region', 'station')
                ->where('is_active', 1);

            if($request_data['filter_id'] == 1) {
                $data = $data->whereBetween('purchase_date', [$yesterday, $tomorrow]);
            } else {
                $data = $data->whereBetween('created_at', [$yesterday, $tomorrow]);
            }

            if($department_id == 0) {
                $data = $data->where('department_id', '!=', null)->get();
            } else {
                $data = $data->where('department_id', $department_id)->get();
            }

            return view('fixed_asset.report.department_report',compact('start_date', 'end_date', 'data', 'dataa', 'department_id', 'filter_id'));
        }
        return back()->with('message', 'something went wrong');
    }
    public function Category (){
        $start_date = date('y-m-01');
        $end_date = date('y-m-d');
        $warehouse_id = 0;
        $dataa = AssetCategory::where('is_active', true)->get();
        return view('fixed_asset.report.category_report', compact('dataa', 'start_date', 'end_date', 'warehouse_id'));
    }
    public function CategoryData (Request $request){

        $request_data = $request->all();
        $start_date = $request_data['start_date'];
        $end_date = $request_data['end_date'];
        $category_id = $request_data['category_id'];
        $filter_id = $request_data['filter_id'];
        $dataa = AssetCategory::where('is_active', true)->get();

        if ($end_date && $start_date) {
            $interval = strtotime($end_date) - strtotime($start_date);
            $diff_date = ($interval / 3600 / 24) + 1;
            $yesterday = date('Y-m-d', strtotime($start_date));
            $tomorrow = date('Y-m-d', strtotime($end_date . "+1 days"));

            $data = Asset::with('category', 'donor', 'region', 'station')
                    ->where('is_active', 1);

            if ($filter_id == 0) {
                $data = $data->whereBetween('created_at', [$yesterday, $tomorrow]);
            } else if($filter_id == 1) {
                $data = $data->whereBetween('purchase_date', [$yesterday, $tomorrow]);
            } else if($filter_id == 2) {
                $data = $data->where('asset_type', '!=', 'land');
            }

            if($category_id == 0) {
                $data = $data->where('category_id', '!=', null)->get();
            } else {
                $data = $data->where('category_id', $category_id)->get();
            }

            return view('fixed_asset.report.category_report',compact('start_date', 'end_date', 'data', 'dataa', 'category_id', 'filter_id'));
        }
        return back()->with('message', 'something went wrong');
    }

    public function CategoryNew (){
        $start_date = date('y-m-01');
        $end_date = date('y-m-d');
        $warehouse_id = 0;
        $dataa = AssetCategory::where('is_active', true)->get();
        return view('fixed_asset.report.category_report_new', compact('dataa', 'start_date', 'end_date', 'warehouse_id'));
    }
    public function CategoryDataNew (Request $request){

        $request_data = $request->all();
        $start_date = $request_data['start_date'];
        $end_date = $request_data['end_date'];
        $category_id = $request_data['category_id'] ?? 0;
        $filter_id = $request_data['filter_id'];
        $dataa = AssetCategory::where('is_active', true)->get();

        if ($end_date && $start_date) {
            $interval = strtotime($end_date) - strtotime($start_date);
            $diff_date = ($interval / 3600 / 24) + 1;
            $yesterday = date('Y-m-d', strtotime($start_date));
            $tomorrow = date('Y-m-d', strtotime($end_date . "+1 days"));

            $data = Asset::with('category', 'donor', 'region', 'station')
                ->where('is_active', 1);

            if ($filter_id == 0) {
                $data = $data->whereBetween('created_at', [$yesterday, $tomorrow]);
            } else if($filter_id == 1) {
                $data = $data->whereBetween('purchase_date', [$yesterday, $tomorrow]);
            } else if($filter_id == 2) {
                $data = $data->where('asset_type', '!=', 'land');
            }

            if($category_id == 0) {
                $data = $data->where('category_id', '!=', null)->get();
            } else {
                $category_array = [$category_id];
                foreach (AssetCategory::where('is_active', true)->where('parent_id', $category_id)->get() as $value) {
                    $category_array[] = $value->id;
                }
                $data = $data->whereIn('category_id', $category_array)->get();
            }

            return view('fixed_asset.report.category_report_new',compact('start_date', 'end_date', 'data', 'dataa', 'category_id', 'filter_id'));
        }
        return back()->with('message', 'something went wrong');
    }
    public function Donor (){
        $start_date = date('y-m-01');
        $end_date = date('y-m-d');
        $warehouse_id = 0;
        $dataa = Donor::where('is_active', true)->get();
        return view('fixed_asset.report.donor_report', compact('dataa', 'start_date', 'end_date', 'warehouse_id'));
    }
    public function DonorData (Request $request){

        $request_data = $request->all();
        $start_date = $request_data['start_date'];
        $end_date = $request_data['end_date'];
        $donor_id = $request_data['donor_id'];
        $filter_id = $request_data['filter_id'];
        $dataa = Donor::where('is_active', true)->get();

        if ($end_date && $start_date) {
            $interval = strtotime($end_date) - strtotime($start_date);
            $diff_date = ($interval / 3600 / 24) + 1;
            $yesterday = date('Y-m-d', strtotime($start_date));
            $tomorrow = date('Y-m-d', strtotime($end_date . "+1 days"));

            $data = Asset::with('category', 'donor', 'region', 'station')
                ->where('is_active', 1);

            if($request_data['filter_id'] == 1) {
                $data = $data->whereBetween('purchase_date', [$yesterday, $tomorrow]);
            } else {
                $data = $data->whereBetween('created_at', [$yesterday, $tomorrow]);
            }

            if($donor_id == 0) {
                $data = $data->where('donor_id', '!=', null)->get();
            } else {
                $data = $data->where('donor_id', $donor_id)->get();
            }

            return view('fixed_asset.report.donor_report',compact('start_date', 'end_date', 'data', 'dataa', 'donor_id', 'filter_id'));
        }
        return back()->with('message', 'something went wrong');
    }
    public function Region (){
        $start_date = date('y-m-01');
        $end_date = date('y-m-d');
        $warehouse_id = 0;
        $dataa = Region::get();
        return view('fixed_asset.report.region_report', compact('dataa', 'start_date', 'end_date', 'warehouse_id'));
    }
    public function RegionData (Request $request){

        $request_data = $request->all();
        $start_date = $request_data['start_date'];
        $end_date = $request_data['end_date'];
        $region_id = $request_data['region_id'];
        $filter_id = $request_data['filter_id'];
        $dataa = Region::get();

        if ($end_date && $start_date) {
            $interval = strtotime($end_date) - strtotime($start_date);
            $diff_date = ($interval / 3600 / 24) + 1;
            $yesterday = date('Y-m-d', strtotime($start_date));
            $tomorrow = date('Y-m-d', strtotime($end_date . "+1 days"));

            $data = Asset::with('category', 'donor', 'region', 'station')
                ->where('is_active', 1);

            if($request_data['filter_id'] == 1) {
                $data = $data->whereBetween('purchase_date', [$yesterday, $tomorrow]);
            } else {
                $data = $data->whereBetween('created_at', [$yesterday, $tomorrow]);
            }

            if($region_id == 0) {
                $data = $data->where('region_id', '!=', null)->get();
            } else {
                $data = $data->where('region_id', $region_id)->get();
            }

            return view('fixed_asset.report.region_report',compact('start_date', 'end_date', 'data', 'dataa', 'region_id', 'filter_id'));
        }
        return back()->with('message', 'something went wrong');
    }
    public function Station (){
        $start_date = date('y-m-01');
        $end_date = date('y-m-d');
        $warehouse_id = 0;
        $dataa = Station::get();
        return view('fixed_asset.report.station_report', compact('dataa', 'start_date', 'end_date', 'warehouse_id'));
    }
    public function StationData (Request $request){

        $request_data = $request->all();
        $start_date = $request_data['start_date'];
        $end_date = $request_data['end_date'];
        $station_id = $request_data['station_id'];
        $filter_id = $request_data['filter_id'];
        $dataa = Station::get();

        if ($end_date && $start_date) {
            $interval = strtotime($end_date) - strtotime($start_date);
            $diff_date = ($interval / 3600 / 24) + 1;
            $yesterday = date('Y-m-d', strtotime($start_date));
            $tomorrow = date('Y-m-d', strtotime($end_date . "+1 days"));

            $data = Asset::with('category', 'donor', 'region', 'station')
                ->where('is_active', 1);

            if($request_data['filter_id'] == 1) {
                $data = $data->whereBetween('purchase_date', [$yesterday, $tomorrow]);
            } else {
                $data = $data->whereBetween('created_at', [$yesterday, $tomorrow]);
            }

            if($station_id == 0) {
                $data = $data->where('station_id', '!=', null)->get();
            } else {
                $data = $data->where('station_id', $station_id)->get();
            }

            return view('fixed_asset.report.station_report',compact('start_date', 'end_date', 'data', 'dataa', 'station_id', 'filter_id'));
        }
        return back()->with('message', 'something went wrong');
    }

    public function expenseReport (){
        $start_date = date('y-m-01');
        $end_date = date('y-m-d');

        $assets = Asset::where('is_active', 1)->get();
        $department = Department::where('is_active', true)->get();
        $donor = Donor::where('is_active', true)->get();
        $assetCategory = AssetCategory::where('is_active', true)->get();
        $station = Station::get();
        $region = Region::get();

        return view('fixed_asset.report.expense_report', compact('assets', 'department', 'donor', 'assetCategory', 'station', 'region', 'start_date', 'end_date'));
    }

    public function ExpenseData (Request $request){

        $request_data = $request->all();
        $start_date = $request_data['start_date'];
        $end_date = $request_data['end_date'];
        $filter_id = $request_data['filter_id'];

        $station_id = $request_data['station_id'];
        $department_id = $request_data['department_id'];
        $donor_id = $request_data['donor_id'];
        $region_id = $request_data['region_id'];
        $category_id = $request_data['category_id'];
        $asset_id = $request_data['asset_id'] ?? null;

        $assets = Asset::where('is_active', 1)->get();
        $department = Department::where('is_active', true)->get();
        $donor = Donor::where('is_active', true)->get();
        $assetCategory = AssetCategory::where('is_active', true)->get();
        $station = Station::get();
        $region = Region::get();

        if ($end_date && $start_date) {
            $interval = strtotime($end_date) - strtotime($start_date);
            $diff_date = ($interval / 3600 / 24) + 1;
            $yesterday = date('Y-m-d', strtotime($start_date));
            $tomorrow = date('Y-m-d', strtotime($end_date . "+1 days"));

            $data = DB::table('asset_expenses')
                        ->leftJoin('assets', 'assets.id', '=', 'asset_expenses.asset_id')
                        ->where('is_active', true)
                        ->where('assets.asset_type',  'vehicle')
                        ->whereBetween('asset_expenses.date', [$yesterday, $tomorrow])
                        ->select('asset_id');

            if ($asset_id && $asset_id[0] != 0){
                $data = $data->whereIn('assets.id', $asset_id);
            }

            if ($filter_id == 'Station') {
                $data = $data->where('assets.station_id', $request_data['station_id']);
            }
            if ($filter_id == 'Department') {
                $data = $data->where('assets.department_id', $request_data['department_id']);
            }
            if ($filter_id == 'Donor') {
                $data = $data->where('assets.donor_id', $request_data['donor_id']);
            }
            if ($filter_id == 'Region') {
                $data = $data->where('assets.region_id', $request_data['region_id']);
            }
            if ($filter_id == 'Category') {
                $data = $data->where('assets.category_id', $request_data['category_id']);
            }

            $data = $data->groupBy('asset_id')->get();

            return view('fixed_asset.report.expense_report', compact('tomorrow', 'yesterday', 'assets', 'asset_id', 'station_id', 'department_id', 'donor_id', 'region_id', 'category_id', 'department', 'donor', 'assetCategory', 'station', 'region', 'data', 'filter_id', 'start_date', 'end_date'));

        }
        return back()->with('message', 'something went wrong');
    }

    public function photocopy (){
        $start_date = date('y-m-01');
        $end_date = date('y-m-d');

        $assets = Asset::where('is_active', 1)->get();
        $department = Department::where('is_active', true)->get();
        $donor = Donor::where('is_active', true)->get();
        $assetCategory = AssetCategory::where('is_active', true)->get();
        $station = Station::get();
        $region = Region::get();

        return view('fixed_asset.report.photocopy_report', compact('assets', 'department', 'donor', 'assetCategory', 'station', 'region', 'start_date', 'end_date'));
    }

    public function photocopyData (Request $request){

        $request_data = $request->all();
        $start_date = $request_data['start_date'];
        $end_date = $request_data['end_date'];
        $filter_id = $request_data['filter_id'];

        $station_id = $request_data['station_id'];
        $department_id = $request_data['department_id'];
        $donor_id = $request_data['donor_id'];
        $region_id = $request_data['region_id'];
        $category_id = $request_data['category_id'];
        $asset_id = $request_data['asset_id'] ?? null;

        $assets = Asset::where('is_active', 1)->get();
        $department = Department::where('is_active', true)->get();
        $donor = Donor::where('is_active', true)->get();
        $assetCategory = AssetCategory::where('is_active', true)->get();
        $station = Station::get();
        $region = Region::get();

        if ($end_date && $start_date) {
            $interval = strtotime($end_date) - strtotime($start_date);
            $diff_date = ($interval / 3600 / 24) + 1;
            $yesterday = date('Y-m-d', strtotime($start_date));
            $tomorrow = date('Y-m-d', strtotime($end_date . "+1 days"));

            $data = DB::table('asset_expenses')
                ->leftJoin('assets', 'assets.id', '=', 'asset_expenses.asset_id')
                ->where('is_active', true)
                ->where('activity_type',  'copy')
                ->whereBetween('asset_expenses.date', [$yesterday, $tomorrow])
                ->select('asset_id');


            if ($asset_id && $asset_id[0] != 0){
                $data = $data->whereIn('assets.id', $asset_id);
            }

            if ($filter_id == 'Station') {
                $data = $data->where('assets.station_id', $request_data['station_id']);
            }
            if ($filter_id == 'Department') {
                $data = $data->where('assets.department_id', $request_data['department_id']);
            }
            if ($filter_id == 'Donor') {
                $data = $data->where('assets.donor_id', $request_data['donor_id']);
            }
            if ($filter_id == 'Region') {
                $data = $data->where('assets.region_id', $request_data['region_id']);
            }
            if ($filter_id == 'Category') {
                $data = $data->where('assets.category_id', $request_data['category_id']);
            }

            $data = $data->groupBy('asset_id')->get();
            return view('fixed_asset.report.photocopy_report', compact('tomorrow', 'yesterday', 'assets', 'asset_id', 'station_id', 'department_id', 'donor_id', 'region_id', 'category_id', 'department', 'donor', 'assetCategory', 'station', 'region', 'data', 'filter_id', 'start_date', 'end_date'));

        }
        return back()->with('message', 'something went wrong');
    }

    public function repair() {
        $start_date = date('y-m-01');
        $end_date = date('y-m-d');

        $assets = Asset::where('is_active', 1)->get();
        $department = Department::where('is_active', true)->get();
        $donor = Donor::where('is_active', true)->get();
        $assetCategory = AssetCategory::where('is_active', true)->get();
        $station = Station::get();
        $region = Region::get();

        return view('fixed_asset.report.repair_report', compact('assets', 'department', 'donor', 'assetCategory', 'station', 'region', 'start_date', 'end_date'));
    }

    public function repairData (Request $request){

        $request_data = $request->all();
        $start_date = $request_data['start_date'];
        $end_date = $request_data['end_date'];
        $filter_id = $request_data['filter_id'];

        $station_id = $request_data['station_id'];
        $department_id = $request_data['department_id'];
        $donor_id = $request_data['donor_id'];
        $region_id = $request_data['region_id'];
        $category_id = $request_data['category_id'];
        $asset_id = $request_data['asset_id'] ?? null;

        $assets = Asset::where('is_active', 1)->get();
        $department = Department::where('is_active', true)->get();
        $donor = Donor::where('is_active', true)->get();
        $assetCategory = AssetCategory::where('is_active', true)->get();
        $station = Station::get();
        $region = Region::get();

        if ($end_date && $start_date) {
            $interval = strtotime($end_date) - strtotime($start_date);
            $diff_date = ($interval / 3600 / 24) + 1;
            $yesterday = date('Y-m-d', strtotime($start_date));
            $tomorrow = date('Y-m-d', strtotime($end_date . "+1 days"));

            $data = DB::table('asset_expenses')
                ->leftJoin('assets', 'assets.id', '=', 'asset_expenses.asset_id')
                ->where('is_active', true)
                ->where('activity_type',  'repair')
                ->whereBetween('asset_expenses.date', [$yesterday, $tomorrow])
                ->select('asset_id');


            if ($asset_id && $asset_id[0] != 0){
                $data = $data->whereIn('assets.id', $asset_id);
            }

            if ($filter_id == 'Station') {
                $data = $data->where('assets.station_id', $request_data['station_id']);
            }
            if ($filter_id == 'Department') {
                $data = $data->where('assets.department_id', $request_data['department_id']);
            }
            if ($filter_id == 'Donor') {
                $data = $data->where('assets.donor_id', $request_data['donor_id']);
            }
            if ($filter_id == 'Region') {
                $data = $data->where('assets.region_id', $request_data['region_id']);
            }
            if ($filter_id == 'Category') {
                $data = $data->where('assets.category_id', $request_data['category_id']);
            }

            $data = $data->groupBy('asset_id')->get();
            return view('fixed_asset.report.repair_report', compact('tomorrow', 'yesterday', 'assets', 'asset_id', 'station_id', 'department_id', 'donor_id', 'region_id', 'category_id', 'department', 'donor', 'assetCategory', 'station', 'region', 'data', 'filter_id', 'start_date', 'end_date'));

        }
        return back()->with('message', 'something went wrong');
    }

    public function general (){
        $start_date = date('y-m-01');
        $end_date = date('y-m-d');

        $assets = Asset::where('is_active', 1)->get();
        $department = Department::where('is_active', true)->get();
        $donor = Donor::where('is_active', true)->get();
        $assetCategory = AssetCategory::where('is_active', true)->get();
        $station = Station::get();
        $region = Region::get();

        return view('fixed_asset.report.general_report', compact('assets', 'department', 'donor', 'assetCategory', 'station', 'region', 'start_date', 'end_date'));
    }

    public function generalData (Request $request){

        $request_data = $request->all();
        $start_date = $request_data['start_date'];
        $end_date = $request_data['end_date'];
        $filter_id = $request_data['filter_id'];

        $station_id = $request_data['station_id'];
        $department_id = $request_data['department_id'];
        $donor_id = $request_data['donor_id'];
        $region_id = $request_data['region_id'];
        $category_id = $request_data['category_id'];
        $asset_id = $request_data['asset_id'] ?? null;

        $assets = Asset::where('is_active', 1)->get();
        $department = Department::where('is_active', true)->get();
        $donor = Donor::where('is_active', true)->get();
        $assetCategory = AssetCategory::where('is_active', true)->get();
        $station = Station::get();
        $region = Region::get();

        if ($end_date && $start_date) {
            $interval = strtotime($end_date) - strtotime($start_date);
            $diff_date = ($interval / 3600 / 24) + 1;
            $yesterday = date('Y-m-d', strtotime($start_date));
            $tomorrow = date('Y-m-d', strtotime($end_date . "+1 days"));

            $data = DB::table('asset_expenses')
                ->leftJoin('assets', 'assets.id', '=', 'asset_expenses.asset_id')
                ->where('is_active', true)
                ->where('activity_type',  'General Activity')
                ->whereBetween('asset_expenses.date', [$yesterday, $tomorrow])
                ->select('asset_id');

            if ($asset_id && $asset_id[0] != 0){
                $data = $data->whereIn('assets.id', $asset_id);
            }

            if ($filter_id == 'Station') {
                $data = $data->where('assets.station_id', $request_data['station_id']);
            }
            if ($filter_id == 'Department') {
                $data = $data->where('assets.department_id', $request_data['department_id']);
            }
            if ($filter_id == 'Donor') {
                $data = $data->where('assets.donor_id', $request_data['donor_id']);
            }
            if ($filter_id == 'Region') {
                $data = $data->where('assets.region_id', $request_data['region_id']);
            }
            if ($filter_id == 'Category') {
                $data = $data->where('assets.category_id', $request_data['category_id']);
            }

            $data = $data->groupBy('asset_id')->get();
            return view('fixed_asset.report.general_report', compact('tomorrow', 'yesterday', 'assets', 'asset_id', 'station_id', 'department_id', 'donor_id', 'region_id', 'category_id', 'department', 'donor', 'assetCategory', 'station', 'region', 'data', 'filter_id', 'start_date', 'end_date'));

        }
        return back()->with('message', 'something went wrong');
    }

    public function destroyAsset($id)
    {
        $data = Asset::where('id', $id)->first();
        $assets = Asset::with('department')->where('is_active',1)->get();

        return view('fixed_asset.asset.destroy', compact('assets', 'data'));
    }

    public function destroyAssetAll()
    {
        $assets = Asset::with('department')->where('is_active', 1)->get();

        return view('fixed_asset.asset.destroy', compact('assets'));
    }

    public function destroyAssetData(Request $request)
    {
        foreach ($request->asset_id as $id) {
            if($id == null) {
                continue;
            }
            Dispose::create([
                'asset_id' => $id,
                'method' => $request->type,
                'other' => $request->other,
                'price' => $request->price ?? 0,
                'date' => $request->date,
                'remarks' => $request->remarks,
            ]);
            Asset::where('id', $id)->update(['is_active' => 2]);
        }

        $message = 'Assets Dispose successfully';
        return redirect()->route('asset.dispose.list')->with('message', $message);
    }

    public function destroyAssetEdit($id)
    {
        $data = Dispose::with('assets')->where('id', $id)->first();

        return view('fixed_asset.asset.destroy_edit', compact('data'));
    }

    public function destroyAssetUpdate(Request $request)
    {
        Dispose::find($request->id)->update([
            'asset_id' => $request->id,
            'method' => $request->type,
            'other' => $request->other,
            'price' => $request->price ?? 0,
            'date' => $request->date,
            'remarks' => $request->remarks,
        ]);
        $message = 'Assets Dispose updated successfully';
        return redirect()->route('asset.dispose.list')->with('message', $message);
    }

    public function transferAssetData(Request $request)
    {
        foreach ($request->asset_id as $key=>$id) {
            if($id == null) {
                continue;
            }
            $asset = Asset::where('id', $id)->first();
            $from = $asset->department_id;

            if($asset->is_transfer != null) {
                $parent_id = AssetTransfer::where('asset_id', $asset->is_transfer)->first()->parent_id;
            } else {
                $parent_id = $id;
            }
            AssetTransfer::create([
                'asset_id' => $id,
                'parent_id' => $parent_id,
                'from' => $from,
                'to' => $request->to[$key],
                'date' => $request->date[$key],
                'price' => $request->price[$key],
                'life_span' => $request->life_span[$key],
                'remarks' => $request->remarks[$key],
                'prepared_by' => $request->prepared_by,
                'checked_by' => $request->checked_by,
            ]);

//            create new asset
            $newAsset = $asset->replicate();
            $newAsset->price = $request->price[$key];
            $newAsset->department_id = $request->to[$key];
            $newAsset->life_span = $request->life_span[$key];
            $newAsset->service_date = $request->date[$key];
            $newAsset->purchase_date = $request->date[$key];
            $newAsset->is_transfer = $id;
            $newAsset->save();

//            update asset
            $asset->update(['is_active' => 3, 'transfer_at' => $request->date[$key]]);
        }
        $message = 'Assets Transfer successfully';
        return redirect()->route('asset.transfer.list')->with('message', $message);
    }

    public function transferAssetEdit($id)
    {
        $data = AssetTransfer::with('assets')->where('id', $id)->first();
        $department = Department::all();

        return view('fixed_asset.asset.transfer_edit', compact('data', 'department'));
    }

    public function transferAssetUpdate(Request $request)
    {
        AssetTransfer::where('id', $request->id)->update([
            'to' => $request->to,
            'date' => $request->date,
            'price' => $request->price,
            'life_span' => $request->life_span,
            'remarks' => $request->remarks,
            'prepared_by' => $request->prepared_by,
            'checked_by' => $request->checked_by,
        ]);

        $message = 'Assets Transfer updated successfully';
        return redirect()->route('asset.transfer.list')->with('message', $message);
    }

    public function destroyAssetList()
    {
        $data = Dispose::with('assets')->get();

        return view('fixed_asset.asset.dispose_list', compact('data'));
    }

    public function transferAssetList() {

        $data = Asset::with('category', 'donor', 'region', 'station', 'AssetTransfers')->where('is_active', 3)->orderByDesc('id')->get();
        return view('fixed_asset.asset.transfer_list', compact('data'));
    }

    public function transferAssetAll(){

        $assets = Asset::with('department')->where('is_active', 1)->get();
        $department = Department::all();
        return view('fixed_asset.asset.transfer', compact('assets', 'department'));
    }

    public function transferAsset($id){

        $assets = Asset::with('department')->where('is_active', 1)->get();
        $asset = Asset::with('department')->find($id);
        $data = $this->depricationCaluculate($asset);
        $department = Department::all();
        return view('fixed_asset.asset.transfer', compact('assets', 'department', 'data'));
    }

    public function transferLetterAsset($id){

        $asset = AssetTransfer::with('assets', 'fromDepartment', 'toDepartment')->where('asset_id', $id)->first();
        $data = $this->depricationCaluculate($asset);
        $department = Department::all();

        $general_setting = GeneralSetting::first();
        $header = $general_setting->email_header;
        $footer = $general_setting->email_footer;
        $water_mark = $general_setting->email_water_mark;
        return view('fixed_asset.asset.transfer_letter', compact('header', 'general_setting', 'footer', 'water_mark', 'department', 'data', 'asset'));
    }

    public function saleAssetList() {


        $data = Asset::with('category', 'donor', 'region', 'station', 'AssetSaleDetails')->where('is_active', 4)->orderByDesc('id')->get();

//        $data = AssetSale::orderByDesc('id')->get();
        return view('fixed_asset.asset.sale_list', compact('data'));
    }

    public function saleAssetAll(){

        $assets = Asset::with('department')->where('is_active', 1)->get();
        return view('fixed_asset.asset.sale', compact('assets'));
    }

    public function saleAsset($id){

        $assets = Asset::with('department')->where('is_active', 1)->get();
        $asset = Asset::with('department')->find($id);
        $data = $this->depricationCaluculate($asset);
        $department = Department::all();
        return view('fixed_asset.asset.sale', compact('assets', 'department', 'data'));
    }

    public function saleLetterAsset($id){

        $data = AssetSale::with('saleDetails')->where('id', $id)->first();
        $general_setting = GeneralSetting::first();
        $user = Auth::user();
        $words =  $this->numberTowords($data->buyer_total_amount);

        $header = $general_setting->email_header;
        $footer = $general_setting->email_footer;
        $water_mark = $general_setting->email_water_mark;
        return view('fixed_asset.asset.sale_letter', compact('words', 'user', 'header', 'general_setting', 'footer', 'water_mark', 'data'));
    }

    // Create a function for converting the amount in words
    private function numberTowords(float $amount)
    {
        $amount_after_decimal = round($amount - ($num = floor($amount)), 2) * 100;
        // Check if there is any number after decimal
        $amt_hundred = null;
        $count_length = strlen($num);
        $x = 0;
        $string = array();
        $change_words = array(0 => '', 1 => 'One', 2 => 'Two',
            3 => 'Three', 4 => 'Four', 5 => 'Five', 6 => 'Six',
            7 => 'Seven', 8 => 'Eight', 9 => 'Nine',
            10 => 'Ten', 11 => 'Eleven', 12 => 'Twelve',
            13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen',
            16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen',
            19 => 'Nineteen', 20 => 'Twenty', 30 => 'Thirty',
            40 => 'Forty', 50 => 'Fifty', 60 => 'Sixty',
            70 => 'Seventy', 80 => 'Eighty', 90 => 'Ninety');
        $here_digits = array('', 'Hundred','Thousand','Lakh', 'Crore');
        while( $x < $count_length ) {
            $get_divider = ($x == 2) ? 10 : 100;
            $amount = floor($num % $get_divider);
            $num = floor($num / $get_divider);
            $x += $get_divider == 10 ? 1 : 2;
            if ($amount) {
                $add_plural = (($counter = count($string)) && $amount > 9) ? 's' : null;
                $amt_hundred = ($counter == 1 && $string[0]) ? ' and ' : null;
                $string [] = ($amount < 21) ? $change_words[$amount].' '. $here_digits[$counter]. $add_plural.'
         '.$amt_hundred:$change_words[floor($amount / 10) * 10].' '.$change_words[$amount % 10]. '
         '.$here_digits[$counter].$add_plural.' '.$amt_hundred;
            }else $string[] = null;
        }
        $implode_to_Rupees = implode('', array_reverse($string));
        $get_paise = ($amount_after_decimal > 0) ? "And " . ($change_words[$amount_after_decimal / 10] . "
   " . $change_words[$amount_after_decimal % 10]) . ' ' : '';
        return ($implode_to_Rupees ? $implode_to_Rupees . ' ' : '') . $get_paise;
    }

    public function saleAssetSearch($id){
        $asset = Asset::select('id', 'price', 'life_span', 'service_date', 'asset_type')->find($id);
        $depricationCaluculate = $this->depricationCaluculate($asset);

        return $depricationCaluculate;
    }

    public function saleAssetShow($id){
        $data = AssetSale::with('saleDetails')->find($id);
        return view('fixed_asset.asset.sale_show', compact('data'));
    }

    public function saleAssetEdit($id){
        $data = AssetSale::with('saleDetails')->find($id);
        return view('fixed_asset.asset.sale_edit', compact('data'));
    }

    public function saleAssetData(Request $request)
    {
        $assetSale = AssetSale::create([
            'buyer_title' => $request->buyer_title,
            'buyer_name' => $request->buyer_name,
            'buyer_number' => $request->buyer_number,
            'buyer_address' => $request->buyer_address,
            'buyer_email' => $request->buyer_email,
            'buyer_id' => $request->buyer_id,
            'buyer_id_date' => $request->buyer_id_date,
            'buyer_to' => $request->buyer_to,

            'saller_title' => $request->saller_title,
            'saller_name' => $request->saller_name,
            'saller_number' => $request->saller_number,
            'saller_address' => $request->saller_address,
            'saller_email' => $request->saller_email,
            'saller_id' => $request->saller_id,
            'saller_id_date' => $request->saller_id_date,
            'saller_to' => $request->saller_to,

            'date' => $request->date,
            'buyer_total_amount' => $request->buyer_total_amount,
            'buyer_remark' => $request->buyer_remark,
        ]);

        foreach ($request->asset_id as $key=>$id) {
            if($id == null) {
                continue;
            }
            $asset = Asset::where('id', $id)->first();

            AssetSaleDetail::create([
                'asset_sale_id' => $assetSale->id,
                'asset_id' => $id,
                'asset_name' => $asset->name,
                'buyer_name' => $request->buyer_name,
                'price' => $request->price[$key],
                'remark' => $request->remark[$key],
                'life_span' => $request->life_span[$key]
            ]);

//            update asset
            $asset->update(['is_active' => 4]);
        }
        $message = 'Assets Sold successfully';
        return redirect()->route('asset.sale.list')->with('message', $message);
    }

    public function saleAssetDataUpdate(Request $request)
    {
        $assetSale = AssetSale::find($request->id);
        $assetSale->update([
            'buyer_title' => $request->buyer_title,
            'buyer_name' => $request->buyer_name,
            'buyer_number' => $request->buyer_number,
            'buyer_address' => $request->buyer_address,
            'buyer_email' => $request->buyer_email,
            'buyer_id' => $request->buyer_id,
            'buyer_id_date' => $request->buyer_id_date,
            'buyer_to' => $request->buyer_to,

            'saller_title' => $request->saller_title,
            'saller_name' => $request->saller_name,
            'saller_number' => $request->saller_number,
            'saller_address' => $request->saller_address,
            'saller_email' => $request->saller_email,
            'saller_id' => $request->saller_id,
            'saller_id_date' => $request->saller_id_date,
            'saller_to' => $request->saller_to,

            'date' => $request->date,
            'buyer_total_amount' => $request->buyer_total_amount,
            'buyer_remark' => $request->buyer_remark,
        ]);
        foreach ($request->asset_id as $key=>$id) {

            AssetSaleDetail::find($id)->update([
                'price' => $request->price[$key],
                'remark' => $request->remark[$key],
                'life_span' => $request->life_span[$key]
            ]);

        }
        $message = 'Assets sale record updated successfully';
        return redirect()->route('asset.sale.list')->with('message', $message);
    }

    public function dispose (){
        $start_date = date('y-m-01');
        $end_date = date('y-m-d');

        $assets = Asset::where('is_active', 2)->get();
        $department = Department::where('is_active', true)->get();
        $donor = Donor::where('is_active', true)->get();
        $assetCategory = AssetCategory::where('is_active', true)->get();
        $station = Station::get();
        $region = Region::get();

        return view('fixed_asset.report.dispose_report', compact('assets', 'department', 'donor', 'assetCategory', 'station', 'region', 'start_date', 'end_date'));
    }

    public function disposeData (Request $request){

        $request_data = $request->all();
        $start_date = $request_data['start_date'];
        $end_date = $request_data['end_date'];
        $filter_id = $request_data['filter_id'];

        $station_id = $request_data['station_id'];
        $department_id = $request_data['department_id'];
        $donor_id = $request_data['donor_id'];
        $region_id = $request_data['region_id'];
        $category_id = $request_data['category_id'];
        $asset_id = $request_data['asset_id'] ?? null;

        $assets = Asset::where('is_active', 2)->get();
        $department = Department::where('is_active', true)->get();
        $donor = Donor::where('is_active', true)->get();
        $assetCategory = AssetCategory::where('is_active', true)->get();
        $station = Station::get();
        $region = Region::get();

        if ($end_date && $start_date) {
            $interval = strtotime($end_date) - strtotime($start_date);
            $diff_date = ($interval / 3600 / 24) + 1;
            $yesterday = date('Y-m-d', strtotime($start_date));
            $tomorrow = date('Y-m-d', strtotime($end_date . "+1 days"));

            $data = DB::table('disposes')
                ->leftJoin('assets', 'assets.id', '=', 'disposes.asset_id')
                ->whereBetween('disposes.date', [$yesterday, $tomorrow])
                ->select('assets.serial_no', 'assets.name', 'disposes.*');

            if ($asset_id && $asset_id[0] != 0){
                $data = $data->whereIn('assets.id', $asset_id);
            }

            if ($filter_id == 'Station') {
                $data = $data->where('assets.station_id', $request_data['station_id']);
            }
            if ($filter_id == 'Department') {
                $data = $data->where('assets.department_id', $request_data['department_id']);
            }
            if ($filter_id == 'Donor') {
                $data = $data->where('assets.donor_id', $request_data['donor_id']);
            }
            if ($filter_id == 'Region') {
                $data = $data->where('assets.region_id', $request_data['region_id']);
            }
            if ($filter_id == 'Category') {
                $data = $data->where('assets.category_id', $request_data['category_id']);
            }

            $data = $data->get();
            return view('fixed_asset.report.dispose_report', compact('assets', 'asset_id', 'station_id', 'department_id', 'donor_id', 'region_id', 'category_id', 'department', 'donor', 'assetCategory', 'station', 'region', 'data', 'filter_id', 'start_date', 'end_date'));

        }
        return back()->with('message', 'something went wrong');
    }


    public function transfer (){
        $start_date = date('y-m-01');
        $end_date = date('y-m-d');

        $assets = Asset::where('is_active', 3)->get();
        $department = Department::where('is_active', true)->get();
        $donor = Donor::where('is_active', true)->get();
        $assetCategory = AssetCategory::where('is_active', true)->get();
        $station = Station::get();
        $region = Region::get();

        return view('fixed_asset.report.transfer_report', compact('assets', 'department', 'donor', 'assetCategory', 'station', 'region', 'start_date', 'end_date'));
    }

    public function transferData (Request $request){

        $request_data = $request->all();
        $start_date = $request_data['start_date'];
        $end_date = $request_data['end_date'];
        $filter_id = $request_data['filter_id'];

        $station_id = $request_data['station_id'];
        $department_id = $request_data['department_id'];
        $donor_id = $request_data['donor_id'];
        $region_id = $request_data['region_id'];
        $category_id = $request_data['category_id'];
        $asset_id = $request_data['asset_id'] ?? null;

        $assets = Asset::where('is_active', 3)->get();
        $department = Department::where('is_active', true)->get();
        $donor = Donor::where('is_active', true)->get();
        $assetCategory = AssetCategory::where('is_active', true)->get();
        $station = Station::get();
        $region = Region::get();

        if ($end_date && $start_date) {
            $interval = strtotime($end_date) - strtotime($start_date);
            $diff_date = ($interval / 3600 / 24) + 1;
            $yesterday = date('Y-m-d', strtotime($start_date));
            $tomorrow = date('Y-m-d', strtotime($end_date . "+1 days"));

            $data = DB::table('asset_transfers')
                ->leftJoin('assets', 'assets.id', '=', 'asset_transfers.asset_id')
                ->whereBetween('asset_transfers.date', [$yesterday, $tomorrow])
                ->select('assets.serial_no', 'assets.name', 'asset_transfers.*');

            if ($asset_id && $asset_id[0] != 0){
                $data = $data->whereIn('assets.id', $asset_id);
            }

            if ($filter_id == 'Station') {
                $data = $data->where('assets.station_id', $request_data['station_id']);
            }
            if ($filter_id == 'Department') {
                $data = $data->where('assets.department_id', $request_data['department_id']);
            }
            if ($filter_id == 'Donor') {
                $data = $data->where('assets.donor_id', $request_data['donor_id']);
            }
            if ($filter_id == 'Region') {
                $data = $data->where('assets.region_id', $request_data['region_id']);
            }
            if ($filter_id == 'Category') {
                $data = $data->where('assets.category_id', $request_data['category_id']);
            }

            $data = $data->get();
            return view('fixed_asset.report.transfer_report', compact('assets', 'asset_id', 'station_id', 'department_id', 'donor_id', 'region_id', 'category_id', 'department', 'donor', 'assetCategory', 'station', 'region', 'data', 'filter_id', 'start_date', 'end_date'));

        }
        return back()->with('message', 'something went wrong');
    }

    public function transferAssetSearch($id){
        $asset = Asset::select('id', 'price', 'life_span', 'service_date', 'asset_type')->find($id);
        $depricationCaluculate = $this->depricationCaluculate($asset);

        return $depricationCaluculate;
    }

    private function depricationCaluculateForDays($asset, $start_date){

        $depreciation = 0;
        $d1 = $start_date;
        $service_date = new DateTime($asset->service_date);
        $created_at = new DateTime($asset->created_at);

        if ($asset->service_date != null) {
            if ($service_date > $start_date) {
                $d1 = new DateTime($asset->service_date);
            }
        } else {
            if ($service_date > $start_date) {
                $d1 = new DateTime($asset->created_at);
            }
        }

        $d2 = new DateTime();
        $interval = $d1->diff($d2);

        $consume = $interval->days;

        if($asset->life_span != null && $asset->price != null) {
            $total_life_span = $asset->life_span * 365;
            $price = $asset->price;
            if ($consume > $total_life_span) {
                $depreciation = $price;
            } else {
                $depreciation = ($consume/$total_life_span) * $asset->price;
            }

            if($asset->asset_type == 'land') {
                $apprication_increase_percentage = ($consume / 365) * $asset->appreciation;
                $apprication_increase_value = ($apprication_increase_percentage/100) * $asset->price;
                $book_value = $apprication_increase_value + $asset->price;
                $depreciation = - $apprication_increase_value ;
            }

            $depreciation = round($depreciation,4);
        }

        return $depreciation;
    }

    private function depricationCaluculate($asset){

        $depreciation = 0;
        $book_value = $asset->price;
        $available = 0;
        $available_in_year = 0;

        if ($asset->service_date != null){
            $d1 = new DateTime($asset->service_date);
        } else {
            $d1 = new DateTime($asset->created_at);
        }

        $d2 = new DateTime();
        $interval = $d1->diff($d2);

        $consume = $interval->days;
        if($asset->life_span != null) {
            $total_life_span = $asset->life_span * 365;
            if ($consume > $total_life_span) {
                $depreciation = $asset->price;
                $available = 0;
                $book_value = 0;
            } else {
                $depreciation = ($consume/$total_life_span) * $asset->price;
                $available = $total_life_span - $consume;
                $book_value = ($available/$total_life_span) * $asset->price;
            }
        }
        if($asset->asset_type == 'land') {
            $apprication_increase_percentage = ($consume / 365) * $asset->appreciation;
            $apprication_increase_value = ($apprication_increase_percentage/100) * $asset->price;
            $book_value = $apprication_increase_value + $asset->price;
            $depreciation = - $apprication_increase_value ;
        }

        $depreciation = round($depreciation,2);
        $book_value = round($book_value,2);
        $consume_in_year = round(($consume/365),2);
        $available_in_year = round(($available/365),2);


        return [
            'asset_id' => $asset->id,
            'depreciation' => $depreciation,
            'book_value' => $book_value,
            'consume_in_year' => $consume_in_year,
            'available_in_year' => $available_in_year
        ];
    }

    private function depricationCaluculateForDashboard($asset, $start_date = null, $end_date = null)
    {
        $depreciation = 0;
        $book_value = $asset->price;
        $available = 0;

        $created_at = new DateTime($asset->created_at);
        $today = new DateTime();

        // Determine the start date
        if ($start_date === null || new DateTime($start_date) < $created_at) {
            $d1 = $created_at;
        } else {
            $d1 = new DateTime($start_date);
        }

        // If service_date exists, use it instead of start_date
        if (!empty($asset->service_date)) {
            $d1 = new DateTime($asset->service_date);
        }

        // Determine the end date
        if ($end_date !== null) {
            $d2 = new DateTime($end_date);
        } else {
            $d2 = new DateTime();
        }

        // Ensure start_date is not beyond end_date
        if ($d1 > $d2) {
            return [
                'asset_id' => $asset->id,
                'depreciation' => 0,
                'book_value' => $book_value,
                'consume_in_year' => 0,
                'available_in_year' => 0
            ];
        }

        // Calculate depreciation duration
        $interval = $d1->diff($d2);
        $consume = $interval->days + 1; // Days passed
//        dd($d1, $d2, $consume);

        if ($asset->life_span != null) {
            $total_life_span = $asset->life_span * 365; // Convert life span to days

            if ($consume > $total_life_span) {
                $depreciation = $asset->price;
                $available = 0;
                $book_value = 0;
            } else {
                $depreciation = ($consume / $total_life_span) * $asset->price;
//                $available = $total_life_span - $created_at->diff($d2)->days;
                $available = $total_life_span - $consume;
                $book_value = ($available / $total_life_span) * $asset->price;
            }
        }

//         Special case for land appreciation
        if ($asset->asset_type == 'land') {
            $appreciation_increase_percentage = ($consume / 365) * $asset->appreciation;
            $appreciation_increase_value = ($appreciation_increase_percentage / 100) * $asset->price;
            $book_value = $appreciation_increase_value + $asset->price;
            $depreciation = -$appreciation_increase_value;
        }

        // Round values for precision
        $depreciation = round($depreciation, 2);
        $book_value = round($book_value, 2);
        $consume_in_year = round(($consume / 365), 2);
        $available_in_year = round(($available / 365), 2);

        return [
            'asset_id' => $asset->id,
            'depreciation' => $depreciation,
            'book_value' => $book_value,
            'consume_in_year' => $consume_in_year,
            'available_in_year' => $available_in_year
        ];
    }
}
