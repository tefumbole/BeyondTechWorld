<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Keygen;
use App\Brand;
use App\Category;
use App\Unit;
use App\Tax;
use App\Warehouse;
use App\Supplier;
use App\Product;
use App\ProductBatch;
use App\Product_Warehouse;
use App\Product_Supplier;
use Auth;
use DNS1D;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Validation\Rule;
use DB;
use App\Variant;
use App\ProductVariant;

class ProductController extends Controller
{
    public function index()
    {
        $role = Role::find(Auth::user()->role_id);
        if($role->hasPermissionTo('products-index')){
            $permissions = Role::findByName($role->name)->permissions;
            foreach ($permissions as $permission)
                $all_permission[] = $permission->name;
            if(empty($all_permission))
                $all_permission[] = 'dummy text';
            return view('product.index', compact('all_permission'));
        }
        else
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
    }

    public function productData(Request $request)
    {
        $columns = array(
            2 => 'name',
            3 => 'code',
            4 => 'brand_id',
            5 => 'category_id',
            6 => 'qty',
            7 => 'unit_id',
            8 => 'price',
            9 => 'cost',
            10 => 'stock_worth'
        );

        $vendor_id = null;
        if (Auth::user()->role_id == 12) {
            $vendor_id =  Auth::user()->id;
        }

        if($vendor_id == null) {
            $totalData = Product::where('is_active', true)->count();
        } else {
            $totalData = Product::where('is_active', true)->where('vendor_id', $vendor_id)->count();
        }

        $totalFiltered = $totalData;

        if($request->input('length') != -1)
            $limit = $request->input('length');
        else
            $limit = $totalData;
        $start = $request->input('start');
        $order = 'products.'.$columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');
        if(empty($request->input('search.value'))){
            if($vendor_id == null) {
                $products = Product::with('category', 'brand', 'unit')->offset($start)
                    ->where('is_active', true)
                    ->limit($limit)
                    ->orderBy($order, $dir)
                    ->get();
            } else {
                $products = Product::with('category', 'brand', 'unit')->offset($start)
                    ->where('is_active', true)
                    ->where('vendor_id', $vendor_id)
                    ->limit($limit)
                    ->orderBy($order, $dir)
                    ->get();
            }
        }
        else
        {
            $search = $request->input('search.value');
            $products = Product::select('products.*')
                ->with('category', 'brand', 'unit')
                ->join('categories', 'products.category_id', '=', 'categories.id')
                ->leftjoin('brands', 'products.brand_id', '=', 'brands.id');

            if($vendor_id == null) {
                $products = $products->where([
                    ['products.name', 'LIKE', "%{$search}%"],
                    ['products.is_active', true]
                ])
                    ->orWhere([
                        ['products.code', 'LIKE', "%{$search}%"],
                        ['products.is_active', true]
                    ])
                    ->orWhere([
                        ['categories.name', 'LIKE', "%{$search}%"],
                        ['categories.is_active', true],
                        ['products.is_active', true]
                    ])
                    ->orWhere([
                        ['brands.title', 'LIKE', "%{$search}%"],
                        ['brands.is_active', true],
                        ['products.is_active', true]
                    ])
                    ->offset($start)
                    ->limit($limit)
                    ->orderBy($order, $dir)->get();
            } else {

                $products = $products->where([
                    ['products.name', 'LIKE', "%{$search}%"],
                    ['products.is_active', true],
                    ['products.vendor_id', $vendor_id]
                ])
                    ->orWhere([
                        ['products.code', 'LIKE', "%{$search}%"],
                        ['products.is_active', true],
                        ['products.vendor_id', $vendor_id]
                    ])
                    ->orWhere([
                        ['categories.name', 'LIKE', "%{$search}%"],
                        ['categories.is_active', true],
                        ['products.is_active', true],
                        ['products.vendor_id', $vendor_id]
                    ])
                    ->orWhere([
                        ['brands.title', 'LIKE', "%{$search}%"],
                        ['brands.is_active', true],
                        ['products.is_active', true],
                        ['products.vendor_id', $vendor_id]
                    ])
                    ->offset($start)
                    ->limit($limit)
                    ->orderBy($order, $dir)->get();
            }


            if($vendor_id == null) {
            $totalFiltered = Product::
            join('categories', 'products.category_id', '=', 'categories.id')
                ->leftjoin('brands', 'products.brand_id', '=', 'brands.id')
                ->where([
                    ['products.name','LIKE',"%{$search}%"],
                    ['products.is_active', true]
                ])
                ->orWhere([
                    ['products.code', 'LIKE', "%{$search}%"],
                    ['products.is_active', true]
                ])
                ->orWhere([
                    ['categories.name', 'LIKE', "%{$search}%"],
                    ['categories.is_active', true],
                    ['products.is_active', true]
                ])
                ->orWhere([
                    ['brands.title', 'LIKE', "%{$search}%"],
                    ['brands.is_active', true],
                    ['products.is_active', true]
                ])
                ->count();
            } else {
                $totalFiltered = Product::
                join('categories', 'products.category_id', '=', 'categories.id')
                    ->leftjoin('brands', 'products.brand_id', '=', 'brands.id')
                    ->where([
                        ['products.name','LIKE',"%{$search}%"],
                        ['products.is_active', true],
                        ['products.vendor_id', $vendor_id]
                    ])
                    ->orWhere([
                        ['products.code', 'LIKE', "%{$search}%"],
                        ['products.is_active', true],
                        ['products.vendor_id', $vendor_id]
                    ])
                    ->orWhere([
                        ['categories.name', 'LIKE', "%{$search}%"],
                        ['categories.is_active', true],
                        ['products.is_active', true],
                        ['products.vendor_id', $vendor_id]
                    ])
                    ->orWhere([
                        ['brands.title', 'LIKE', "%{$search}%"],
                        ['brands.is_active', true],
                        ['products.is_active', true],
                        ['products.vendor_id', $vendor_id]
                    ])
                    ->count();
            }
        }

        $data = array();
        if(!empty($products))
        {
            foreach ($products as $key=>$product)
            {
                $nestedData['id'] = $product->id;
                $nestedData['key'] = $key;
                $product_image = explode(",", $product->image);
                $product_image = htmlspecialchars($product_image[0]);
                $nestedData['image'] = '<img src="'.url('public/images/product', $product_image).'" height="80" width="80">';
                $nestedData['name'] = $product->name;
                $nestedData['code'] = $product->code;
                if($product->brand_id)
                    $nestedData['brand'] = $product->brand->title;
                else
                    $nestedData['brand'] = "N/A";
                $nestedData['category'] = $product->category->name;
                $nestedData['qty'] = $product->qty;
                if($product->unit_id)
                    $nestedData['unit'] = $product->unit->unit_name;
                else
                    $nestedData['unit'] = 'N/A';

                $nestedData['price'] = $product->price;
                $nestedData['cost'] = $product->cost;

                if(config('currency_position') == 'prefix')
                    $nestedData['stock_worth'] = config('currency').' '.($product->qty * $product->price).' / '.config('currency').' '.($product->qty * $product->cost);
                else
                    $nestedData['stock_worth'] = ($product->qty * $product->price).' '.config('currency').' / '.($product->qty * $product->cost).' '.config('currency');
                //$nestedData['stock_worth'] = ($product->qty * $product->price).'/'.($product->qty * $product->cost);

                $nestedData['options'] = '<div class="btn-group">
                            <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">'.trans("file.action").'
                              <span class="caret"></span>
                              <span class="sr-only">Toggle Dropdown</span>
                            </button>
                            <ul class="dropdown-menu edit-options dropdown-menu-right dropdown-default" user="menu">
                            <li>
                                <button="type" class="btn btn-link view"><i class="fa fa-eye"></i> '.trans('file.View').'</button>
                            </li>';
                if(in_array("products-edit", $request['all_permission']))
                    $nestedData['options'] .= '<li>
                            <a href="'.route('products.edit', $product->id).'" class="btn btn-link"><i class="fa fa-edit"></i> '.trans('file.edit').'</a>
                        </li>';
                if(in_array("products-delete", $request['all_permission']))
                    $nestedData['options'] .= \Form::open(["route" => ["products.destroy", $product->id], "method" => "DELETE"] ).'
                            <li>
                              <button type="submit" class="btn btn-link" onclick="return confirmDelete()"><i class="fa fa-trash"></i> '.trans("file.delete").'</button>
                            </li>'.\Form::close().'
                        </ul>
                    </div>';
                // data for product details by one click
                if($product->tax_id)
                    $tax = Tax::find($product->tax_id)->name ?? 'NAN';
                else
                    $tax = "N/A";

                if($product->tax_method == 1)
                    $tax_method = trans('file.Exclusive');
                else
                    $tax_method = trans('file.Inclusive');

                $nestedData['product'] = array( '[ "'.$product->type.'"', ' "'.$product->name.'"', ' "'.$product->code.'"', ' "'.$nestedData['brand'].'"', ' "'.$nestedData['category'].'"', ' "'.$nestedData['unit'].'"', ' "'.$product->cost.'"', ' "'.$product->price.'"', ' "'.$tax.'"', ' "'.$tax_method.'"', ' "'.$product->alert_quantity.'"', ' "'.preg_replace('/\s+/S', " ", $product->product_details).'"', ' "'.$product->id.'"', ' "'.$product->product_list.'"', ' "'.$product->qty_list.'"', ' "'.$product->price_list.'"', ' "'.$product->qty.'"', ' "'.$product->image.'"', ' "'.$product->rent_price_per_hour.'"', ' "'.$product->rent_price_per_day.'"', ' "'.$product->rent_price_per_month.'"]'
                );
                //$nestedData['imagedata'] = DNS1D::getBarcodePNG($product->code, $product->barcode_symbology);
                $data[] = $nestedData;
            }
        }
        $json_data = array(
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => intval($totalFiltered),
            "data"            => $data
        );

        echo json_encode($json_data);
    }

    public function productDataVendor(Request $request)
    {
        $columns = array(
            2 => 'name',
            3 => 'code',
            4 => 'brand_id',
            5 => 'category_id',
            6 => 'qty',
            7 => 'unit_id',
            8 => 'price',
            9 => 'cost',
            10 => 'stock_worth'
        );

        $vendor_id = $request->id;

        if($vendor_id == null) {
            $totalData = Product::where('is_active', true)->count();
        } else {
            $totalData = Product::where('is_active', true)->where('vendor_id', $vendor_id)->count();
        }

        $totalFiltered = $totalData;

        if($request->input('length') != -1)
            $limit = $request->input('length');
        else
            $limit = $totalData;
        $start = $request->input('start');
        $order = 'products.'.$columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');
        if(empty($request->input('search.value'))){
            if($vendor_id == null) {
                $products = Product::with('category', 'brand', 'unit')->offset($start)
                    ->where('is_active', true)
                    ->limit($limit)
                    ->orderBy($order, $dir)
                    ->get();
            } else {
                $products = Product::with('category', 'brand', 'unit')->offset($start)
                    ->where('is_active', true)
                    ->where('vendor_id', $vendor_id)
                    ->limit($limit)
                    ->orderBy($order, $dir)
                    ->get();
            }
        }
        else
        {
            $search = $request->input('search.value');
            $products = Product::select('products.*')
                ->with('category', 'brand', 'unit')
                ->join('categories', 'products.category_id', '=', 'categories.id')
                ->leftjoin('brands', 'products.brand_id', '=', 'brands.id');

            if($vendor_id == null) {
                $products = $products->where([
                    ['products.name', 'LIKE', "%{$search}%"],
                    ['products.is_active', true]
                ])
                    ->orWhere([
                        ['products.code', 'LIKE', "%{$search}%"],
                        ['products.is_active', true]
                    ])
                    ->orWhere([
                        ['categories.name', 'LIKE', "%{$search}%"],
                        ['categories.is_active', true],
                        ['products.is_active', true]
                    ])
                    ->orWhere([
                        ['brands.title', 'LIKE', "%{$search}%"],
                        ['brands.is_active', true],
                        ['products.is_active', true]
                    ])
                    ->offset($start)
                    ->limit($limit)
                    ->orderBy($order, $dir)->get();
            } else {

                $products = $products->where([
                    ['products.name', 'LIKE', "%{$search}%"],
                    ['products.is_active', true],
                    ['products.vendor_id', $vendor_id]
                ])
                    ->orWhere([
                        ['products.code', 'LIKE', "%{$search}%"],
                        ['products.is_active', true],
                        ['products.vendor_id', $vendor_id]
                    ])
                    ->orWhere([
                        ['categories.name', 'LIKE', "%{$search}%"],
                        ['categories.is_active', true],
                        ['products.is_active', true],
                        ['products.vendor_id', $vendor_id]
                    ])
                    ->orWhere([
                        ['brands.title', 'LIKE', "%{$search}%"],
                        ['brands.is_active', true],
                        ['products.is_active', true],
                        ['products.vendor_id', $vendor_id]
                    ])
                    ->offset($start)
                    ->limit($limit)
                    ->orderBy($order, $dir)->get();
            }


            if($vendor_id == null) {
                $totalFiltered = Product::
                join('categories', 'products.category_id', '=', 'categories.id')
                    ->leftjoin('brands', 'products.brand_id', '=', 'brands.id')
                    ->where([
                        ['products.name','LIKE',"%{$search}%"],
                        ['products.is_active', true]
                    ])
                    ->orWhere([
                        ['products.code', 'LIKE', "%{$search}%"],
                        ['products.is_active', true]
                    ])
                    ->orWhere([
                        ['categories.name', 'LIKE', "%{$search}%"],
                        ['categories.is_active', true],
                        ['products.is_active', true]
                    ])
                    ->orWhere([
                        ['brands.title', 'LIKE', "%{$search}%"],
                        ['brands.is_active', true],
                        ['products.is_active', true]
                    ])
                    ->count();
            } else {
                $totalFiltered = Product::
                join('categories', 'products.category_id', '=', 'categories.id')
                    ->leftjoin('brands', 'products.brand_id', '=', 'brands.id')
                    ->where([
                        ['products.name','LIKE',"%{$search}%"],
                        ['products.is_active', true],
                        ['products.vendor_id', $vendor_id]
                    ])
                    ->orWhere([
                        ['products.code', 'LIKE', "%{$search}%"],
                        ['products.is_active', true],
                        ['products.vendor_id', $vendor_id]
                    ])
                    ->orWhere([
                        ['categories.name', 'LIKE', "%{$search}%"],
                        ['categories.is_active', true],
                        ['products.is_active', true],
                        ['products.vendor_id', $vendor_id]
                    ])
                    ->orWhere([
                        ['brands.title', 'LIKE', "%{$search}%"],
                        ['brands.is_active', true],
                        ['products.is_active', true],
                        ['products.vendor_id', $vendor_id]
                    ])
                    ->count();
            }
        }

        $data = array();
        if(!empty($products))
        {
            foreach ($products as $key=>$product)
            {
                $nestedData['id'] = $product->id;
                $nestedData['key'] = $key;
                $product_image = explode(",", $product->image);
                $product_image = htmlspecialchars($product_image[0]);
                $nestedData['image'] = '<img src="'.url('public/images/product', $product_image).'" height="80" width="80">';
                $nestedData['name'] = $product->name;
                $nestedData['code'] = $product->code;
                if($product->brand_id)
                    $nestedData['brand'] = $product->brand->title;
                else
                    $nestedData['brand'] = "N/A";
                $nestedData['category'] = $product->category->name;
                $nestedData['qty'] = $product->qty;
                if($product->unit_id)
                    $nestedData['unit'] = $product->unit->unit_name;
                else
                    $nestedData['unit'] = 'N/A';

                $nestedData['price'] = $product->price;
                $nestedData['cost'] = $product->cost;

                if(config('currency_position') == 'prefix')
                    $nestedData['stock_worth'] = config('currency').' '.($product->qty * $product->price).' / '.config('currency').' '.($product->qty * $product->cost);
                else
                    $nestedData['stock_worth'] = ($product->qty * $product->price).' '.config('currency').' / '.($product->qty * $product->cost).' '.config('currency');
                //$nestedData['stock_worth'] = ($product->qty * $product->price).'/'.($product->qty * $product->cost);

                $nestedData['options'] = '<div class="btn-group">
                            <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">'.trans("file.action").'
                              <span class="caret"></span>
                              <span class="sr-only">Toggle Dropdown</span>
                            </button>
                            <ul class="dropdown-menu edit-options dropdown-menu-right dropdown-default" user="menu">
                            <li>
                                <button="type" class="btn btn-link view"><i class="fa fa-eye"></i> '.trans('file.View').'</button>
                            </li>';
                if(in_array("products-edit", $request['all_permission']))
                    $nestedData['options'] .= '<li>
                            <a href="'.route('products.edit', $product->id).'" class="btn btn-link"><i class="fa fa-edit"></i> '.trans('file.edit').'</a>
                        </li>';
                if(in_array("products-delete", $request['all_permission']))
                    $nestedData['options'] .= \Form::open(["route" => ["products.destroy", $product->id], "method" => "DELETE"] ).'
                            <li>
                              <button type="submit" class="btn btn-link" onclick="return confirmDelete()"><i class="fa fa-trash"></i> '.trans("file.delete").'</button>
                            </li>'.\Form::close().'
                        </ul>
                    </div>';
                // data for product details by one click
                if($product->tax_id)
                    $tax = Tax::find($product->tax_id)->name ?? 'NAN';
                else
                    $tax = "N/A";

                if($product->tax_method == 1)
                    $tax_method = trans('file.Exclusive');
                else
                    $tax_method = trans('file.Inclusive');

                $nestedData['product'] = array( '[ "'.$product->type.'"', ' "'.$product->name.'"', ' "'.$product->code.'"', ' "'.$nestedData['brand'].'"', ' "'.$nestedData['category'].'"', ' "'.$nestedData['unit'].'"', ' "'.$product->cost.'"', ' "'.$product->price.'"', ' "'.$tax.'"', ' "'.$tax_method.'"', ' "'.$product->alert_quantity.'"', ' "'.preg_replace('/\s+/S', " ", $product->product_details).'"', ' "'.$product->id.'"', ' "'.$product->product_list.'"', ' "'.$product->qty_list.'"', ' "'.$product->price_list.'"', ' "'.$product->qty.'"', ' "'.$product->image.'"', ' "'.$product->rent_price_per_hour.'"', ' "'.$product->rent_price_per_day.'"', ' "'.$product->rent_price_per_month.'"]'
                );
                //$nestedData['imagedata'] = DNS1D::getBarcodePNG($product->code, $product->barcode_symbology);
                $data[] = $nestedData;
            }
        }
        $json_data = array(
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => intval($totalFiltered),
            "data"            => $data
        );

        echo json_encode($json_data);
    }

    public function create()
    {
        $role = Role::firstOrCreate(['id' => Auth::user()->role_id]);
        if ($role->hasPermissionTo('products-add')){
            $lims_product_list = Product::where([ ['is_active', true], ['type', 'standard'] ])->get();
            $lims_brand_list = Brand::where('is_active', true)->get();
            $lims_category_list = Category::where('is_active', true)->get();
            $lims_unit_list = Unit::where('is_active', true)->get();
            $lims_tax_list = Tax::where('is_active', true)->get();
            $lims_warehouse_list = Warehouse::where('is_active', true)->get();
            return view('product.create',compact('lims_product_list', 'lims_brand_list', 'lims_category_list', 'lims_unit_list', 'lims_tax_list', 'lims_warehouse_list', 'role'));
        }
        else
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'code' => [
                'max:255',
                Rule::unique('products')->where(function ($query) {
                    return $query->where('is_active', 1);
                }),
            ],
            'name' => [
                'max:255',
                Rule::unique('products')->where(function ($query) {
                    return $query->where('is_active', 1);
                }),
            ]
        ]);
        $data = $request->except('image', 'file');
        if (Auth::user()->role_id == 12) {
            $data['vendor_id'] =  Auth::user()->id;
        }
        $data['name'] = htmlspecialchars(trim($data['name']));
        if($data['type'] == 'combo'){
            $data['product_list'] = implode(",", $data['product_id']);
            $data['qty_list'] = implode(",", $data['product_qty']);
            $data['price_list'] = implode(",", $data['unit_price']);
            $data['cost'] = $data['unit_id'] = $data['purchase_unit_id'] = $data['sale_unit_id'] = 0;
        }
        elseif($data['type'] == 'donation') {
            $donation_unit = Unit::where('unit_code', 'donation')->first();
            $donation_category = Category::where('name', 'donation')->first();
            $data['unit_id'] = $donation_unit->id;
            $data['sale_unit_id'] = $donation_unit->id;
            $data['purchase_unit_id'] = $donation_unit->id;
            $data['category_id'] = $donation_category->id;
        }
        elseif($data['type'] == 'service') {
            $service_unit = Unit::where('unit_code', 'service')->first();
            $service_category = Category::where('name', 'SERVICES')->first();
            $data['unit_id'] = $service_unit->id;
            $data['sale_unit_id'] = $service_unit->id;
            $data['purchase_unit_id'] = $service_unit->id;
            $data['category_id'] = $service_category->id;
        }
        elseif($data['type'] == 'digital')
            $data['cost'] = $data['unit_id'] = $data['purchase_unit_id'] = $data['sale_unit_id'] = 0;

        $data['product_details'] = str_replace('"', '@', $data['product_details']);

        $data['rent_price_per_hour'] = $data['rent_price_per_hour'] ?? 0;
        $data['rent_price_per_day'] =  $data['rent_price_per_day'] ?? 0;
        $data['rent_price_per_month'] =  $data['rent_price_per_month'] ?? 0;

        if($data['starting_date'])
            $data['starting_date'] = date('Y-m-d', strtotime($data['starting_date']));
        if($data['last_date'])
            $data['last_date'] = date('Y-m-d', strtotime($data['last_date']));
        $data['is_active'] = true;
        $images = $request->image;
        $image_names = [];
        if($images) {
            foreach ($images as $key => $image) {
                $imageName = $image->getClientOriginalName();
                $image->move('public/images/product', $imageName);
                $image_names[] = $imageName;
            }
            $data['image'] = implode(",", $image_names);
        }
        else {
            $data['image'] = 'zummXD2dvAtI.png';
        }
        $file = $request->file;
        if ($file) {
            $ext = pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION);
            $fileName = strtotime(date('Y-m-d H:i:s'));
            $fileName = $fileName . '.' . $ext;
            $file->move('public/product/files', $fileName);
            $data['file'] = $fileName;
        }
        $data['location'] = $request->product_location;
        $lims_product_data = Product::create($data);
        //dealing with product variant
        if(!isset($data['is_batch']))
            $data['is_batch'] = null;
        if(isset($data['is_variant'])) {
            foreach ($data['variant_name'] as $key => $variant_name) {
                $data['is_variant'] = null;
                $data['code'] = $data['item_code'][$key];
                $data['name'] = $variant_name.' ['.$lims_product_data->name.']';
                $data['price'] = $lims_product_data->price + $data['additional_price'][$key];
                Product::create($data);
            }
        }


        $warehouse = Warehouse::where('is_active', true)->first();
        $check_warehouse = Product_Warehouse::where('product_id', $lims_product_data->id)->where('warehouse_id', $warehouse->id)->first();
        if(!$check_warehouse) {
            Product_Warehouse::create([
                "product_id" => $lims_product_data->id,
                "warehouse_id" => $warehouse->id,
                "qty" => $data['qty'],
            ]);
        } else {
            $check_warehouse->update(['qty' => $data['qty']]);
        }

        if(isset($data['is_diffPrice'])) {
            foreach ($data['diff_price'] as $key => $diff_price) {
                if($diff_price) {
                    Product_Warehouse::create([
                        "product_id" => $lims_product_data->id,
                        "warehouse_id" => $data["warehouse_id"][$key],
                        "qty" => 0,
                        "price" => $diff_price
                    ]);
                }
            }
        }
        \Session::flash('create_message', 'Product created successfully');
    }

    public function edit($id)
    {
        $role = Role::firstOrCreate(['id' => Auth::user()->role_id]);
        if ($role->hasPermissionTo('products-edit')) {
            $lims_product_list = Product::where([ ['is_active', true], ['type', 'standard'] ])->get();
            $lims_brand_list = Brand::where('is_active', true)->get();
            $lims_category_list = Category::where('is_active', true)->get();
            $lims_unit_list = Unit::where('is_active', true)->get();
            $lims_tax_list = Tax::where('is_active', true)->get();
            $lims_product_data = Product::where('id', $id)->first();
            $lims_product_variant_data = $lims_product_data->variant()->orderBy('position')->get();
            $lims_warehouse_list = Warehouse::where('is_active', true)->get();

            return view('product.edit',compact('lims_product_list', 'lims_brand_list', 'lims_category_list', 'lims_unit_list', 'lims_tax_list', 'lims_product_data', 'lims_product_variant_data', 'lims_warehouse_list', 'role'));
        }
        else
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
    }

    public function updateProduct(Request $request)
    {
        $this->validate($request, [
            'name' => [
                'max:255',
                Rule::unique('products')->ignore($request->input('id'))->where(function ($query) {
                    return $query->where('is_active', 1);
                }),
            ],

            'code' => [
                'max:255',
                Rule::unique('products')->ignore($request->input('id'))->where(function ($query) {
                    return $query->where('is_active', 1);
                }),
            ]
        ]);

        $lims_product_data = Product::findOrFail($request->input('id'));
        $data = $request->except('image', 'file', 'prev_img');
        $data['name'] = htmlspecialchars(trim($data['name']));

        if($data['type'] == 'combo') {
            $data['product_list'] = implode(",", $data['product_id']);
            $data['qty_list'] = implode(",", $data['product_qty']);
            $data['price_list'] = implode(",", $data['unit_price']);
            $data['cost'] = $data['unit_id'] = $data['purchase_unit_id'] = $data['sale_unit_id'] = 0;
        }
        elseif($data['type'] == 'digital')
            $data['cost'] = $data['unit_id'] = $data['purchase_unit_id'] = $data['sale_unit_id'] = 0;

        if(!isset($data['featured']))
            $data['featured'] = 0;

        if(!isset($data['promotion']))
            $data['promotion'] = null;

        if(!isset($data['is_batch']))
            $data['is_batch'] = null;

        $data['product_details'] = str_replace('"', '@', $data['product_details']);
        $data['product_details'] = $data['product_details'];
        $data['location'] = $request->product_location;
        if($data['starting_date'])
            $data['starting_date'] = date('Y-m-d', strtotime($data['starting_date']));
        if($data['last_date'])
            $data['last_date'] = date('Y-m-d', strtotime($data['last_date']));

        //dealing with previous images
        if($request->prev_img) {
            $lims_product_data->image = implode(",", $request->prev_img);
            $lims_product_data->save();
        }
        //dealing with new images
        $images = $request->image;
        $image_names = [];
        if($images) {
            foreach ($images as $key => $image) {
                $imageName = $image->getClientOriginalName();
                $image->move('public/images/product', $imageName);
                $image_names[] = $imageName;
            }
            if($lims_product_data->image != 'zummXD2dvAtI.png') {
                $data['image'] = $lims_product_data->image.','.implode(",", $image_names);
            }
            else{
                $data['image'] = implode(",", $image_names);
            }
        }
        else {
            $data['image'] = $lims_product_data->image;
        }

        $file = $request->file;
        if ($file) {
            $ext = pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION);
            $fileName = strtotime(date('Y-m-d H:i:s'));
            $fileName = $fileName . '.' . $ext;
            $file->move('public/product/files', $fileName);
            $data['file'] = $fileName;
        }

        $lims_product_variant_data = ProductVariant::where('product_id', $request->input('id'))->select('id', 'variant_id')->get();
        foreach ($lims_product_variant_data as $key => $value) {
            if (!in_array($value->variant_id, $data['variant_id'])) {
                ProductVariant::find($value->id)->delete();
            }
        }
        //dealing with product variant
        if(isset($data['is_variant'])) {
            foreach ($data['variant_name'] as $key => $variant_name) {
                if($data['product_variant_id'][$key] == 0) {
                    $lims_variant_data = Variant::firstOrCreate(['name' => $data['variant_name'][$key]]);
                    $lims_product_variant_data = new ProductVariant();

                    $lims_product_variant_data->product_id = $lims_product_data->id;
                    $lims_product_variant_data->variant_id = $lims_variant_data->id;

                    $lims_product_variant_data->position = $key + 1;
                    $lims_product_variant_data->item_code = $data['item_code'][$key];
                    $lims_product_variant_data->additional_price = $data['additional_price'][$key];
                    $lims_product_variant_data->qty = 0;
                    $lims_product_variant_data->save();
                }
                else {
                    Variant::find($data['variant_id'][$key])->update(['name' => $variant_name]);
                    ProductVariant::find($data['product_variant_id'][$key])->update([
                        'position' => $key+1,
                        'item_code' => $data['item_code'][$key],
                        'additional_price' => $data['additional_price'][$key]
                    ]);
                }
            }
        }
        else {
            $data['is_variant'] = null;
            $product_variants = ProductVariant::where('product_id', $lims_product_data->id)->get();
            foreach ($product_variants as $key => $product_variant) {
                $product_variant->delete();
            }
        }
        if(isset($data['is_diffPrice'])) {
            foreach ($data['diff_price'] as $key => $diff_price) {
                if($diff_price) {
                    $lims_product_warehouse_data = Product_Warehouse::FindProductWithoutVariant($lims_product_data->id, $data['warehouse_id'][$key])->first();
                    if($lims_product_warehouse_data) {
                        $lims_product_warehouse_data->price = $diff_price;
                        $lims_product_warehouse_data->save();
                    }
                    else {
                        Product_Warehouse::create([
                            "product_id" => $lims_product_data->id,
                            "warehouse_id" => $data["warehouse_id"][$key],
                            "qty" => 0,
                            "price" => $diff_price
                        ]);
                    }
                }
            }
        }
        else {
            $data['is_diffPrice'] = false;
            if(isset($data['warehouse_id'])) {
                foreach ($data['warehouse_id'] as $key => $warehouse_id) {
                    $lims_product_warehouse_data = Product_Warehouse::FindProductWithoutVariant($lims_product_data->id, $warehouse_id)->first();
                    if($lims_product_warehouse_data) {
                        $lims_product_warehouse_data->price = null;
                        $lims_product_warehouse_data->save();
                    }
                }
            }
        }
        $lims_product_data->update($data);

        $warehouse = Warehouse::where('is_active', true)->first();
        $check_warehouse = Product_Warehouse::where('product_id', $lims_product_data->id)->where('warehouse_id', $warehouse->id)->first();
        if(!$check_warehouse) {
            Product_Warehouse::create([
                "product_id" => $lims_product_data->id,
                "warehouse_id" => $warehouse->id,
                "qty" => $data['qty'],
            ]);
        } else {
            $check_warehouse->update(['qty' => $data['qty']]);
        }

        \Session::flash('edit_message', 'Product updated successfully');

    }

    public function generateCode()
    {
        $id = Keygen::numeric(8)->generate();
        return $id;
    }

    public function search(Request $request)
    {
        $product_code = explode(" ", $request['data']);
        $lims_product_data = Product::where('code', $product_code[0])->first();

        $product[] = $lims_product_data->name;
        $product[] = $lims_product_data->code;
        $product[] = $lims_product_data->qty;
        $product[] = $lims_product_data->price;
        $product[] = $lims_product_data->id;
        return $product;
    }

    public function saleUnit($id)
    {
        $unit = Unit::where("base_unit", $id)->orWhere('id', $id)->pluck('unit_name','id');
        return json_encode($unit);
    }

    public function getData($id)
    {
        $data = Product::select('name', 'code')->where('id', $id)->get();
        return $data[0];
    }

    public function productWarehouseData($id)
    {
        $warehouse = [];
        $qty = [];
        $warehouse_name = [];
        $variant_name = [];
        $variant_qty = [];
        $product_warehouse = [];
        $product_variant_warehouse = [];
        $lims_product_data = Product::select('id', 'is_variant')->find($id);
        if($lims_product_data->is_variant) {
            $lims_product_variant_warehouse_data = Product_Warehouse::where('product_id', $lims_product_data->id)->orderBy('warehouse_id')->get();
            $lims_product_warehouse_data = Product_Warehouse::select('warehouse_id', DB::raw('sum(qty) as qty'))->where('product_id', $id)->groupBy('warehouse_id')->get();
            foreach ($lims_product_variant_warehouse_data as $key => $product_variant_warehouse_data) {
                $lims_warehouse_data = Warehouse::find($product_variant_warehouse_data->warehouse_id);
                $lims_variant_data = Variant::find($product_variant_warehouse_data->variant_id);
                $warehouse_name[] = $lims_warehouse_data->name;
                $variant_name[] = $lims_variant_data->name;
                $variant_qty[] = $product_variant_warehouse_data->qty;
            }
        }
        else {
            $lims_product_warehouse_data = Product_Warehouse::where('product_id', $id)->orderBy('warehouse_id', 'asc')->get();
        }
        foreach ($lims_product_warehouse_data as $key => $product_warehouse_data) {
            $lims_warehouse_data = Warehouse::find($product_warehouse_data->warehouse_id);
            if($product_warehouse_data->product_batch_id) {
                $product_batch_data = ProductBatch::select('batch_no', 'expired_date')->find($product_warehouse_data->product_batch_id);
                $batch_no = $product_batch_data->batch_no ?? 'N/N';
                $expiredDate = date(config('date_format'), strtotime($product_batch_data->expired_date ?? 'N/N'));
            }
            else {
                $batch_no = 'N/A';
                $expiredDate = 'N/A';
            }
            $warehouse[] = $lims_warehouse_data->name;
            $batch[] = $batch_no;
            $expired_date[] = $expiredDate;
            $qty[] = $product_warehouse_data->qty;
        }

        $product_warehouse = [$warehouse, $qty, $batch, $expired_date];
        $product_variant_warehouse = [$warehouse_name, $variant_name, $variant_qty];
        return ['product_warehouse' => $product_warehouse, 'product_variant_warehouse' => $product_variant_warehouse];
    }

    public function printBarcode()
    {
        $lims_product_list_without_variant = $this->productWithoutVariant();
        $lims_product_list_with_variant = $this->productWithVariant();
        return view('product.print_barcode', compact('lims_product_list_without_variant', 'lims_product_list_with_variant'));
    }

    public function productWithoutVariant()
    {
        return Product::ActiveStandard()->select('id', 'name', 'code')
            ->whereNull('is_variant')->get();
    }

    public function productWithVariant()
    {
        return Product::join('product_variants', 'products.id', 'product_variants.product_id')
            ->ActiveStandard()
            ->whereNotNull('is_variant')
            ->select('products.id', 'products.name', 'product_variants.item_code')
            ->orderBy('position')->get();
    }

    public function limsProductSearch(Request $request)
    {
        $product_code = explode("(", $request['data']);
        $product_code[0] = rtrim($product_code[0], " ");

        $lims_product_data = Product::where('code', $product_code[0])->first();
        if(!$lims_product_data) {
            $lims_product_data = Product::join('product_variants', 'products.id', 'product_variants.product_id')
                ->select('products.*', 'product_variants.item_code')
                ->where('product_variants.item_code', $product_code[0])
                ->first();
        }
        $product[] = $lims_product_data->name;
        if($lims_product_data->is_variant)
            $product[] = $lims_product_data->item_code;
        else
            $product[] = $lims_product_data->code;
        $product[] = $lims_product_data->price;
        $product[] = DNS1D::getBarcodePNG($lims_product_data->code, $lims_product_data->barcode_symbology);
        $product[] = $lims_product_data->promotion_price;
        $product[] = config('currency');
        $product[] = config('currency_position');
        return $product;
    }

    /*public function getBarcode()
    {
        return DNS1D::getBarcodePNG('72782608', 'C128');
    }*/

    public function checkBatchAvailability($product_id, $batch_no, $warehouse_id)
    {
        $product_batch_data = ProductBatch::where([
            ['product_id', $product_id],
            ['batch_no', $batch_no]
        ])->first();
        if($product_batch_data) {
            $product_warehouse_data = Product_Warehouse::select('qty')
                ->where([
                    ['product_batch_id', $product_batch_data->id],
                    ['warehouse_id', $warehouse_id]
                ])->first();
            if($product_warehouse_data) {
                $data['qty'] = $product_warehouse_data->qty;
                $data['product_batch_id'] = $product_batch_data->id;
                $data['message'] = 'ok';
            }
            else {
                $data['qty'] = 0;
                $data['message'] = 'This Batch does not exist in the selected warehouse!';
            }
        }
        else {
            $data['message'] = 'Wrong Batch Number!';
        }
        return $data;
    }

    public function importProduct(Request $request)
    {
        //get file
        $upload=$request->file('file');
        $ext = pathinfo($upload->getClientOriginalName(), PATHINFO_EXTENSION);
        if($ext != 'csv')
            return redirect()->back()->with('message', 'Please upload a CSV file');

        $filePath=$upload->getRealPath();
        //open and read
        $file=fopen($filePath, 'r');
        $header= fgetcsv($file);
        $escapedHeader=[];
        //validate
        foreach ($header as $key => $value) {
            $lheader=strtolower($value);
            $escapedItem=preg_replace('/[^a-z]/', '', $lheader);
            array_push($escapedHeader, $escapedItem);
        }
        //looping through other columns
        while($columns=fgetcsv($file))
        {
            foreach ($columns as $key => $value) {
                $value=preg_replace('/\D/','',$value);
            }
            $data= array_combine($escapedHeader, $columns);

            if($data['brand'] != 'N/A' && $data['brand'] != ''){
                $lims_brand_data = Brand::firstOrCreate(['title' => $data['brand'], 'is_active' => true]);
                $brand_id = $lims_brand_data->id;
            }
            else
                $brand_id = null;

            $lims_category_data = Category::firstOrCreate(['name' => $data['category'], 'is_active' => true]);

            $lims_unit_data = Unit::where('unit_name', $data['unitcode'])->first();
            if(!$lims_unit_data)
                return redirect()->back()->with('not_permitted', 'Unit code does not exist in the database.');

            $product = Product::firstOrNew([ 'name'=>$data['name'], 'is_active'=>true ]);
            if($data['image'])
                $product->image = $data['image'];
            else
                $product->image = 'zummXD2dvAtI.png';

            $product->name = $data['name'];
            $product->code = $data['code'];
            $product->type = strtolower($data['type']);
            $product->barcode_symbology = 'C128';
            $product->brand_id = $brand_id;
            $product->category_id = $lims_category_data->id;
            $product->unit_id = $lims_unit_data->id;
            $product->purchase_unit_id = $lims_unit_data->id;
            $product->sale_unit_id = $lims_unit_data->id;
            $product->cost = $data['cost'];
            $product->price = $data['price'];
            $product->tax_method = 1;
            $product->qty = 0;
            $product->product_details = $data['productdetails'] ?? '';
            $product->is_active = true;
            $product->save();

            if(isset($data['variantname'])) {
                //dealing with variants
                $variant_names = explode(",", $data['variantname']);
                $item_codes = explode(",", $data['itemcode']);
                $additional_prices = explode(",", $data['additionalprice']);
                foreach ($variant_names as $key => $variant_name) {
                    $variant = Variant::firstOrCreate(['name' => $variant_name]);
                    if($data['itemcode'])
                        $item_code = $item_codes[$key];
                    else
                        $item_code = $variant_name . '-' . $data['code'];

                    if($data['additionalprice'])
                        $additional_price = $additional_prices[$key];
                    else
                        $additional_price = 0;

                    ProductVariant::create([
                        'product_id' => $product->id,
                        'variant_id' => $variant->id,
                        'position' => $key + 1,
                        'item_code' => $item_code,
                        'additional_price' => $additional_price,
                        'qty' => 0
                    ]);
                }
                $product->is_variant = true;
                $product->save();
            }
        }
        return redirect('products')->with('import_message', 'Product imported successfully');
    }

    public function deleteBySelection(Request $request)
    {
        $product_id = $request['productIdArray'];
        foreach ($product_id as $id) {
            $lims_product_data = Product::findOrFail($id);
            $lims_product_data->is_active = false;
            $lims_product_data->save();
        }
        return 'Product deleted successfully!';
    }

    public function editBySelection(Request $request)
    {
        if ($request->ids == null) {
            return redirect()->back()->with('not_permitted', 'Please select at least one product!');
        }
        $product_id = explode(',', $request->ids);
        $warehouse_id = $request->warehouse;
        $lims_product_data = Product::whereIn('id', $product_id)
//            ->orderBy('name')
            ->get();
        $lims_warehouse_list = Warehouse::where('is_active', true)->get();
        $lims_category_list = Category::where('is_active', true)->get();
        $lims_unit_list = Unit::where('is_active', true)->get();
        return view('product.bulkEdit', compact('lims_product_data', 'lims_unit_list', 'lims_category_list', 'lims_warehouse_list', 'warehouse_id'));
    }

    public function editBySelectionPage(Request $request)
    {
        $warehouse_id = $request->warehouse;
        if ($request->dir == 1) {
            $lims_product_data = Product::where('id', '>', $request->last)->take($request->total)
//                ->orderBy('name')
                ->get();
        } else {
            $lims_product_data = Product::where('id', '<', $request->last)->take($request->total)->orderBYDesc('id')
//                ->orderBy('name')
                ->get();
        }

        if ($lims_product_data->isEmpty()) {
            return back()->with('not_permitted', 'No product found');
        }
        $lims_warehouse_list = Warehouse::where('is_active', true)->get();
        $lims_category_list = Category::where('is_active', true)->get();
        $lims_unit_list = Unit::where('is_active', true)->get();
        return view('product.bulkEdit', compact('lims_product_data', 'lims_unit_list', 'lims_category_list', 'lims_warehouse_list', 'warehouse_id'));
    }

    public function updateBySelection(Request $request)
    {
        $products = $request->all();

        $bulk_expiry = '+'.env('BULK_EDIT_BACTH_EXPIRY_IN_DAYS').' days';
        foreach ($products['id'] as $key=>$data) {

            $qty = isset($products['qty'][$key]) ? $products['qty'][$key] : 0;
            $batch = isset($products['is_batch'][$key]) && ($products['is_batch'][$key] == 'on' || $products['is_batch'][$key]) == 1 ? 1 : 0;

            $product = Product::where('id', $products['id'][$key])->first();
//          batch to unbatch
            if ($batch == 0 && isset($products['warehouse_id'][$key]) && $products['warehouse_id'][$key] != null) {
                if ($product->is_batch == 1) {
                    $this->batchToUnbatch($key);
                }
            }
//          unbatch to batch
            if ($batch == 1 && isset($products['warehouse_id'][$key]) && $products['warehouse_id'][$key] != null) {
                if ($product->is_batch == 0) {
                    $this->unbatchToBatch($key, $bulk_expiry, $products['warehouse_id'][$key], $products);
                }
            }

            if ($products['type'][$key] == 'standard' && isset($products['warehouse_id'][$key]) && $products['warehouse_id'][$key] != null) {
                if ($batch == 1) {
                    $this->handleSelectedWarehousesForBatch($key, $products['warehouse_id'][$key], $products);
                } else {
                    $check_warehouse = Product_Warehouse::where('product_id', $data)
                        ->where('warehouse_id', $products['warehouse_id'][$key])
                        ->where('product_batch_id', null)
                        ->first();

                    if (!$check_warehouse) {
                        Product_Warehouse::create([
                            "product_id" => $data,
                            "warehouse_id" => $products['warehouse_id'][$key],
                            "qty" => $qty,
                            "product_batch_id" => null
                        ]);
                    } else {
                        $check_warehouse->update(['qty' => $qty, "product_batch_id" => null]);
                    }
                }

                $qty = Product_Warehouse::where('product_id', $data)->sum('qty');
                Product::where('id', $data)->update([
                    'name' => $products['name'][$key],
                    'type' => $products['type'][$key],
                    'category_id' => $products['category'][$key],
                    'unit_id' => $products['unit'][$key],
                    'cost' => $products['cost'][$key],
                    'price' => $products['price'][$key],
                    'location' => $products['product_location'][$key],
                    'is_batch' => $batch,
                    'qty' => $qty
                ]);
            } else {
                Product::where('id', $data)->update([
                    'name' => $products['name'][$key],
                    'type' => $products['type'][$key],
                    'category_id' => $products['category'][$key],
                    'unit_id' => $products['unit'][$key],
                    'cost' => $products['cost'][$key],
                    'price' => $products['price'][$key],
                    'location' => $products['product_location'][$key],
                    'is_batch' => $batch
                ]);
            }
        }

        return back()->with('message', 'Products has been updated...!');
    }

    private function batchToUnbatch($product_id)
    {
        $warehouse_products = Product_Warehouse::select('warehouse_id', DB::raw('sum(qty) as total_qty'))
            ->where('product_id', $product_id)
            ->groupBy('warehouse_id')
            ->get();
        foreach ($warehouse_products as $warehouse_product) {
            $warehouse_product_single = Product_Warehouse::where('product_id', $product_id)
                ->where('warehouse_id', $warehouse_product->warehouse_id)
                ->where('product_batch_id', null)
                ->first();
            if ($warehouse_product_single) {
                $warehouse_product_single->qty = $warehouse_product->total_qty;
                $warehouse_product_single->save();
            } else {
                Product_Warehouse::create([
                    'product_id' => $product_id,
                    'warehouse_id' => $warehouse_product->warehouse_id,
                    'qty' => $warehouse_product->total_qty,
                    'product_batch_id' => null
                ]);
            }
        }

//                  making quantity zero
        ProductBatch::where('product_id', $product_id)->update(['qty' => 0]);
        Product_Warehouse::where('product_id', $product_id)->where('product_batch_id', '!=', null)->update(['qty' => 0]);
    }

    private function unbatchToBatch($product_id, $bulk_expiry, $selected_warehouse, $products)
    {
        $warehouse_products = Product_Warehouse::select('warehouse_id', DB::raw('sum(qty) as total_qty'))
            ->where('product_id', $product_id)
            ->groupBy('warehouse_id')
            ->get();
        foreach ($warehouse_products as $warehouse_product) {
            if ($warehouse_product->warehouse_id == $selected_warehouse) {
                $this->handleSelectedWarehousesForBatch($product_id, $warehouse_product->warehouse_id, $products);
            } else {
                $this->handleOtherWarehousesForBatch($product_id, $warehouse_product, $bulk_expiry);
            }

        }
        Product_Warehouse::where('product_id', $product_id)->where('product_batch_id', null)->update(['qty' => 0]);
    }

    private function handleSelectedWarehousesForBatch($product_id, $warehouse_product_id, $products)
    {
        if (!isset($products['batch_no'][$product_id])) {
            return false;
        }

        $delete_batches = ProductBatch::join('product_warehouse', 'product_batches.id', '=', 'product_warehouse.product_batch_id')
            ->where('product_batches.product_id', $product_id)->whereNotIn('product_batches.batch_no', $products['batch_no'][$product_id])
            ->where('product_warehouse.warehouse_id', $products['warehouse_id'][$product_id])->select('product_batches.*', 'product_warehouse.warehouse_id')->get();
        foreach ($delete_batches as $delete_batch) {
            Product_Warehouse::where('product_batch_id', $delete_batch->id)->delete();
            $delete_batch->delete();
        }

        foreach ($products['batch_no'][$product_id] as $key => $batch_no) {
            $warehouse_product_single = Product_Warehouse::join('product_batches', 'product_warehouse.product_batch_id', '=', 'product_batches.id')
                ->where('product_warehouse.product_id', $product_id)
                ->where('product_warehouse.warehouse_id', $warehouse_product_id)
                ->Where('product_batches.batch_no', $batch_no)
                ->select('product_warehouse.*', 'product_batches.batch_no')
                ->first();
            if ($warehouse_product_single) {
                $warehouse_product_single->qty = $products['batch_qty'][$product_id][$key];
                $warehouse_product_single->save();
                ProductBatch::where('batch_no', $batch_no)->where('product_id', $product_id)->update(['qty' => $products['batch_qty'][$product_id][$key]]);
            } else {
                $product_batch =  ProductBatch::create([
                    'product_id' => $product_id,
                    'batch_no' => $batch_no,
                    'expired_date' => $products['batch_expire'][$product_id][$key],
                    'qty' => $products['batch_qty'][$product_id][$key]
                ]);

                Product_Warehouse::create([
                    'product_id' => $product_id,
                    'warehouse_id' => $warehouse_product_id,
                    'qty' => $products['batch_qty'][$product_id][$key],
                    'product_batch_id' => $product_batch->id
                ]);
            }
        }
    }
    private function handleOtherWarehousesForBatch($product_id, $warehouse_product, $bulk_expiry)
    {
        $warehouse_product_single = Product_Warehouse::join('product_batches', 'product_warehouse.product_batch_id', '=', 'product_batches.id')
            ->where('product_warehouse.product_id', $product_id)
            ->where('product_warehouse.warehouse_id', $warehouse_product->warehouse_id)
            ->Where('product_batches.batch_no', 'Null-01')
            ->select('product_warehouse.*', 'product_batches.batch_no')
            ->first();
        if ($warehouse_product_single) {
            $warehouse_product_single->qty = $warehouse_product->total_qty;
            $warehouse_product_single->save();
        } else {
            $product_batch =  ProductBatch::create([
                'product_id' => $product_id,
                'batch_no' => 'Null-01',
                'expired_date' => date('Y-m-d', strtotime($bulk_expiry)),
                'qty' => $warehouse_product->total_qty
            ]);

            Product_Warehouse::create([
                'product_id' => $product_id,
                'warehouse_id' => $warehouse_product->warehouse_id,
                'qty' => $warehouse_product->total_qty,
                'product_batch_id' => $product_batch->id
            ]);
        }
        return redirect()->route('products.index')->with('message', 'Products has been updated...!');
    }

    public function warehouseProducts(Request $request)
    {
        $batches = DB::table('product_warehouse')->join('product_batches', 'product_batches.id', 'product_warehouse.product_batch_id')
            ->where('product_warehouse.product_id', $request->product_id)
            ->where('product_warehouse.warehouse_id', $request->warehouse_id)
            ->where('product_batch_id', '!=', null)
            ->select('product_warehouse.qty', 'product_warehouse.id', 'product_batches.batch_no', 'product_batches.expired_date')
            ->get();

        return response()->json(['batches' => $batches]);
    }

    public function destroy($id)
    {
        $lims_product_data = Product::findOrFail($id);
        $lims_product_data->is_active = false;
        /*if($lims_product_data->image != 'zummXD2dvAtI.png') {
            $images = explode(",", $lims_product_data->image);
            foreach ($images as $key => $image) {
                unlink('public/images/product/'.$image);
            }
        }*/
        $lims_product_data->delete();
        return redirect('products')->with('message', 'Product deleted successfully');
    }
}
