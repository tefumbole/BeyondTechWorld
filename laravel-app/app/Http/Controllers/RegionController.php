<?php

namespace App\Http\Controllers;

use App\Region;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Spatie\Permission\Models\Role;

class RegionController extends Controller
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
        $data = Region::get();
        return view('fixed_asset.region.index',compact('data'));
    }

    public function create() {
        return view('fixed_asset.region.create');
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => [
                'max:255'
            ]
        ]);
        Region::create($request->all());
        return redirect('region')->with('message', 'Region inserted successfully');
    }

    public function edit($id)
    {
        $data = Region::findOrFail($id)->first();
        return view('fixed_asset.region.edit',compact('data'));
    }

    public function update(Request $request, $id)
    {
        $this->validate($request,[
            'name' => [
                'max:255'
            ]
        ]);

        $lims_category_data = Region::findOrFail($id);
        $lims_category_data->update($request->all());
        return redirect('region')->with('message', 'Region updated successfully');
    }

    public function destroy($id)
    {
        $lims_category_data = Region::findOrFail($id)->delete();
        return redirect('region')->with('not_permitted', 'Region deleted successfully');
    }
}
