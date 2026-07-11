<?php

namespace App\Http\Controllers;

use App\AssetCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Spatie\Permission\Models\Role;

class AssetCategoryController extends Controller
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
        $lims_categories = AssetCategory::where('is_active', true)->pluck('name', 'id');
        $lims_category_all = AssetCategory::where('is_active', true)->get();
        return view('fixed_asset.asset_category.index',compact('lims_categories', 'lims_category_all'));
    }

    public function create() {
        return view('fixed_asset.asset_category.create');
    }

    public function store(Request $request)
    {
        $request->name = preg_replace('/\s+/', ' ', $request->name);
        $this->validate($request, [
            'name' => [
                'max:255'
            ],
            'image' => 'image|mimes:jpg,jpeg,png,gif',
        ]);
        $image = $request->image;
        if ($image) {
            $ext = pathinfo($image->getClientOriginalName(), PATHINFO_EXTENSION);
            $imageName = date("Ymdhis");
            $imageName = $imageName . '.' . $ext;
            $image->move('public/images/assetCategory', $imageName);

            $lims_category_data['image'] = $imageName;
        }
        $lims_category_data['name'] = $request->name;
        $lims_category_data['parent_id'] = $request->parent_id;
        $lims_category_data['is_active'] = true;
        AssetCategory::create($lims_category_data);
        return redirect('assetCategory')->with('message', 'Category inserted successfully');
    }

    public function edit($id)
    {
        $lims_category_data = AssetCategory::findOrFail($id);
        $lims_parent_data = AssetCategory::where('id', $lims_category_data['parent_id'])->first();
        return view('fixed_asset.asset_category.edit',compact('lims_category_data', 'lims_parent_data'));
    }

    public function update(Request $request, $id)
    {
        $this->validate($request,[
            'name' => [
                'max:255'
            ],
            'image' => 'image|mimes:jpg,jpeg,png,gif',
        ]);

        $input = $request->except('image');
        $image = $request->image;
        if ($image) {
            $ext = pathinfo($image->getClientOriginalName(), PATHINFO_EXTENSION);
            $imageName = date("Ymdhis");
            $imageName = $imageName . '.' . $ext;
            $image->move('public/images/assetCategory', $imageName);
            $input['image'] = $imageName;
        }
        $lims_category_data = AssetCategory::findOrFail($id);
        $lims_category_data->update($input);
        return redirect('assetCategory')->with('message', 'Category updated successfully');
    }

    public function destroy($id)
    {
        $lims_category_data = AssetCategory::findOrFail($id);
        $lims_category_data->is_active = false;
        if($lims_category_data->image) {
            unlink('public/images/assetCategory/'.$lims_category_data->image);
        }
        $lims_category_data->save();
        return redirect('assetCategory')->with('not_permitted', 'Category deleted successfully');
    }
}
