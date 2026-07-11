<?php

namespace App\Http\Controllers;

use App\Order;
use App\paymentRequest;
use App\Product;
use App\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Auth;

class ShopController extends Controller
{
    public function index() {
        $data = User::where('role_id', 12)->orderByDesc('id')->get();
        return view('shops.index', compact('data'));
    }

    public function delete($id) {
        User::where('id', $id)->delete();
        Order::with('orderProducts')->where('vendor_id', $id)->delete();
        Product::where('vendor_id', $id)->delete();
        PaymentRequest::where('vendor_id', $id)->delete();
        return back()->with('not_permitted','Shop deleted successfully');
    }

    public function edit($id) {
        $data = User::find($id);
        return view('shops.edit', compact('data'));
    }

    public function show($id) {
        $data = User::find($id);
        $pending_dues = PaymentRequest::where('vendor_id', $id)->where('status', 0)->sum('amount');
        $earning = PaymentRequest::where('vendor_id', $id)->where('status', 1)->sum('amount');

        $products = Product::where('vendor_id', $id)->where('is_active', 1)->count('id');
        $orders = Order::where('vendor_id', $id)->count('id');
        $payments = PaymentRequest::where('vendor_id', $id)->count('id');

        return view('shops.show', compact('data', 'pending_dues', 'earning', 'products', 'orders', 'payments'));
    }

    public function products($id) {
        $role = Role::find(Auth::user()->role_id);
        if($role->hasPermissionTo('products-index')){
            $permissions = Role::findByName($role->name)->permissions;
            foreach ($permissions as $permission)
                $all_permission[] = $permission->name;
            if(empty($all_permission))
                $all_permission[] = 'dummy text';
            return view('product.vendor_index', compact('all_permission', 'id'));
        }
        else
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
    }

    public function update(Request $request, $id)
    {
        $data = User::find($id);
        if($data->is_active != $request->is_active) {
            if($request->is_active == 1) {
                $msg = 'Dear '.$data->name.': Your account has been activated';
            } else {
                $msg = 'Dear '.$data->name.': Your account has been Disabled';
            }

            try{
                $this->wpMessage($data->phone, $msg);
            }
            catch(\Exception $e){
            }
        }

        $data->update($request->all());

        return redirect()->route('shop.index')->with('message','Shop Update successfully');
    }
}
