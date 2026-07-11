<?php

namespace App\Http\Controllers;

use App\Region;
use App\Station;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Spatie\Permission\Models\Role;

class StationController extends Controller
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
    public function index()
    {
        $data = Station::with('regions')->get();
        return view('fixed_asset.station.index',compact('data'));
    }

    public function create() {
        $regions = Region::all();
        return view('fixed_asset.station.create', compact('regions'));
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => [
                'max:255'
            ]
        ]);
        Station::create(['name' => $request->name, 'region_id' => $request->region_id ?? 0 ]);
        return redirect('station')->with('message', 'Station inserted successfully');
    }

    public function edit($id)
    {
        $data = Station::findOrFail($id)->first();
        $regions = Region::all();
        return view('fixed_asset.station.edit',compact('data', 'regions'));
    }

    public function update(Request $request, $id)
    {
        $this->validate($request,[
            'name' => [
                'max:255'
            ]
        ]);

        $lims_category_data = Station::findOrFail($id);
        $lims_category_data->update(['name' => $request->name, 'region_id' => $request->region_id ?? 0 ]);
        return redirect('station')->with('message', 'Station updated successfully');
    }

    public function destroy($id)
    {
        $lims_category_data = Station::findOrFail($id)->delete();
        return redirect('station')->with('not_permitted', 'Station deleted successfully');
    }
}
