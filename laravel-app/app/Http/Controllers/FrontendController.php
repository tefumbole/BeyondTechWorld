<?php

namespace App\Http\Controllers;

use App\Booking;
use App\Brand;
use App\Category;
use App\Customer;
use App\GeneralSetting;
use App\Order;
use App\Product;
use App\Product_Warehouse;
use App\Review;
use App\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class FrontendController extends Controller
{

    public function __construct(Session $session)
    {
        $this->middleware(function ($request, $next) {
            $otp = Session::get('otp');
            if(isset($otp) && Auth::user())  {
                return redirect()->route('otp_screen');
            }

            return $next($request);
        });
    }

    public function logout()
    {
        Auth::logout();
        return redirect('/');
    }

    public function forgotPassword()
    {
        return view('frontend.forgot-password');
    }

    public function forgotPasswordStore(Request $request)
    {
        $user = User::where('phone', $request->phone)->where('role_id', 5)->where('is_active', true)->first();
        if ($user) {
            $otp = $this->sendOTP($user->phone);
            Session::put('otp', $otp);
            Session::put('user', $user);
            return view('frontend.otp_screen_forgot_password');
        }

        return back()->with('not_permitted', 'Your phone number is incorrect...!');
    }

    public function forgotPasswordCheck(Request $request)
    {
        if ($request->otp == Session::get('otp')) {
            Session::forget('otp');
            return view('frontend.password_change');
        }
        return back()->with('not_permitted', 'OTP is incorrect...!');
    }

    public function forgotPasswordCheckStore(Request $request) {
        $data = $request->all();
        $password = $data['password'];

        if($data['password'] != $data['confirm_password']) {
            $not_permitted = 'Password and confirm password does not match';
            return view('frontend.password_change', compact('not_permitted'));
        }

        $user = Session::get('user');
        User::where('id', $user->id)->update([
            'password' => bcrypt($password)
        ]);
        Session::forget('user');

        $msg = '*Dear* :'. $user->name .' \n\n';
        $msg .= '*Your new password is:* '. $password . '\n\n';

        try{
            $this->wpMessage($user->phone, $msg);
        }
        catch(\Exception $e){
        }

        return redirect()->route('shop.login')->with('success', 'Congratulaton: Your password has been updated');
    }

    public function index() {

        $checkPayments = $this->checkOrderPayment();
        $new_arrival = Product::where('is_active', true)->where('price', '>', 0)->where('type','!=' , 'donation')->where('type','!=' , 'service')->orderByDesc('id')->take(6)->get();
        $best_selling = Product::where('is_active', true)->where('price', '>', 0)->where('type','!=' , 'donation')->where('type','!=' , 'service')->orderByDesc('qty')->take(6)->get();
        $hot_deal = Product::where('is_active', true)->where('price', '>', 0)->where('type','!=' , 'donation')->where('type','!=' , 'service')->orderBy('price')->take(6)->get();

        $categories = Category::where('is_active', 1)->take('9')->get();
        $first_category = Product::where('is_active', true)->where('price', '>', 0)->where('category_id', $categories[0]->id)->take(4)->get();
        $second_category = Product::where('is_active', true)->where('price', '>', 0)->where('category_id', $categories[1]->id)->take(4)->get();
        $third_category = Product::where('is_active', true)->where('price', '>', 0)->where('category_id', $categories[2]->id)->take(4)->get();
        $forth_category = Product::where('is_active', true)->where('price', '>', 0)->where('category_id', $categories[3]->id)->take(4)->get();

        return view('frontend.index', compact('new_arrival', 'best_selling', 'hot_deal', 'categories', 'first_category', 'second_category', 'third_category', 'forth_category'));
    }

    private function checkOrderPayment(){
        $orders = Order::select('id', 'reference', 'name', 'user_id', 'vendor_id', 'grand_total', 'payment_method', 'payment_status')->where('payment_method', 'MTN')->where('payment_status', 0)->get();
        $general_setting = GeneralSetting::first();
        $token = getenv("MOMO_TOKEN");
        foreach ($orders as $order) {
            $status = $this->mobileMoneyStatus($token, $order->reference);
            if($status == 1) {
                $order->update(['payment_status' => 1]);
                if($order->is_donation == 1) {
                    $this->sendWhatsappMsgMomoPaymentSuccessDonation($general_setting, $order, $order->grand_total);
                    $this->sendWhatsappMsgMomoPaymentSuccessDonationSeller($general_setting, $order);
                } else {
                    $user = User::find($order->user_id);
                    $this->sendWhatsappMsgMomoPaymentSuccess($user->phone, $order->grand_total);
                    $this->sendWhatsappMsgForPlacingOrderToBuyer($order);
                    $this->sendWhatsappMsgForPlacingOrderToSaller($order);
                }
                foreach ($order->orderProducts as $product) {
                    $lims_product_data = Product::where('id', $product->product_id)->first();

                    if($lims_product_data->type == 'standard') {
                        $warehouse_data = Product_Warehouse::where([
                            ['product_id', $lims_product_data->id],
                            ['warehouse_id', 1],
                        ])->first();

                        if($warehouse_data) {
                            $warehouse_data->qty -= $product->quantity;
                            $warehouse_data->save();
                        }

                        $lims_product_data->qty -= $product->quantity;
                        $lims_product_data->save();

                    }
                }
            }
            if($status == 2) {
                $order->update(['payment_status' => 2]);
            }
        }

        $bookings = Booking::where('payment_method', 'MTN')->where('payment_status', 1)->get();

        if($bookings->isEmpty()) {
            return true;
        }

        $token = getenv("MOMO_TOKEN");
        foreach ($bookings as $order) {
            if($order->reference == '') {
                continue;
            }
            $status = $this->mobileMoneyStatus($token, $order->reference);
            if($status == 1) {
                $order->update([
                    'payment_status' => 4,
                    'paid_amount' => $order->grand_total
                ]);
                $user = Customer::find($order->customer_id);
                $this->sendWhatsappMsgMomoPaymentSuccess($user->phone_number, $order->grand_total);
                $this->sendWhatsappMsgForPlacingOrderToBuyerBooking($order);
//                $this->sendWhatsappMsgForPlacingOrderToSallerBooking($order);
            }
            if($status == 2) {
                $order->update(['payment_status' => 2]);
            }
        }
    }

    public function mobileMoneyStatus($token, $reference){


        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://www.campay.net/api/transaction/'.$reference.'/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Authorization: Token ' . $token,
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);
        $response_decode = json_decode($response, true);

        curl_close($curl);

        if($response_decode && isset($response_decode['status'])) {
            if($response_decode['status'] == 'SUCCESSFUL') {
                return 1;
            }
            if($response_decode['status'] == 'FAILED') {
                return 2;
            }
        }

        return 0;

    }

    public function product($id) {
        $product = Product::where('id', $id)->first();
        $one_star_count = Review::where('product_id', $id)->where('rating', 1)->count('id');
        $two_star_count = Review::where('product_id', $id)->where('rating', 2)->count('id');
        $three_star_count = Review::where('product_id', $id)->where('rating', 3)->count('id');
        $four_star_count = Review::where('product_id', $id)->where('rating', 4)->count('id');
        $five_star_count = Review::where('product_id', $id)->where('rating', 5)->count('id');
        $total_count = Review::where('product_id', $id)->count('id');
        return view('frontend.single-product', compact('product', 'one_star_count', 'two_star_count', 'three_star_count', 'four_star_count', 'five_star_count', 'total_count'));
    }

    public function donate($id) {
        $product = Product::where('id', $id)->first();
        return view('frontend.donate-checkout', compact('product'));
    }

    public function singleService($id) {
        $product = Product::where('id', $id)->first();


        $varientProducts = Product::where('is_active', true)
            ->where('type' , 'service')
            ->where('is_variant' , 1)
            ->orderByDesc('id')
            ->get();
        return view('frontend.service-form', compact('product', 'varientProducts'));
    }

    public function serviceOrder(Request $request) {
        $data = $request->all();
        $product = Product::where('id', $request->product_id)->first();
        return view('frontend.custom-service-checkout', compact('product', 'data'));
    }


    public function donateDetail($id) {
        $product = Product::where('id', $id)->first();
        $one_star_count = Review::where('product_id', $id)->where('rating', 1)->count('id');
        $two_star_count = Review::where('product_id', $id)->where('rating', 2)->count('id');
        $three_star_count = Review::where('product_id', $id)->where('rating', 3)->count('id');
        $four_star_count = Review::where('product_id', $id)->where('rating', 4)->count('id');
        $five_star_count = Review::where('product_id', $id)->where('rating', 5)->count('id');
        $total_count = Review::where('product_id', $id)->count('id');
        return view('frontend.single-donation', compact('product', 'one_star_count', 'two_star_count', 'three_star_count', 'four_star_count', 'five_star_count', 'total_count'));

    }

    public function serviceDetail($id) {
        $product = Product::where('id', $id)->first();
        $one_star_count = Review::where('product_id', $id)->where('rating', 1)->count('id');
        $two_star_count = Review::where('product_id', $id)->where('rating', 2)->count('id');
        $three_star_count = Review::where('product_id', $id)->where('rating', 3)->count('id');
        $four_star_count = Review::where('product_id', $id)->where('rating', 4)->count('id');
        $five_star_count = Review::where('product_id', $id)->where('rating', 5)->count('id');
        $total_count = Review::where('product_id', $id)->count('id');
        return view('frontend.single-service', compact('product', 'one_star_count', 'two_star_count', 'three_star_count', 'four_star_count', 'five_star_count', 'total_count'));

    }


    public function login(){
        return view('frontend.login');
    }

    public function signup(){
        return view('frontend.signup');
    }

    public function signupStore(Request $request){
        $data = $request->all();
        $password = $data['password'];
        $data['phone'] = '+237'.$data['phone'];

        if($data['password'] != $data['confirm_password']) {
            return redirect()->route('shop.signup')->with('not_permitted', 'Password and confirm password does not match');
        }

        $data['email'] = $data['email'] ? $data['email'] : 'guest@gmail.com';
        $data['city'] = $data['city'] ? $data['city'] : 'guest city';
        $data['state'] = $data['state'] ? $data['state'] : 'guest state';
        $data['mtn_phone'] =  $data['phone'];
        $data['is_active'] = true;
        $data['is_deleted'] = false;
        $data['role_id'] = 5;
        $data['password'] = bcrypt($password);
        $user = User::create($data);

        if ($user) {
            $data['user_id'] = $user->id;
            $data['customer_group_id'] = 1;
            $data['phone_number'] = $data['phone'];
            $data['is_active'] = true;
            Customer::create($data);
            $this->sendWhatsappMsgForAccount($user, $password);
        }

        return redirect()->route('shop.login')->with('success', 'Congratulaton: Your account has been created');
    }


    public function createShop(){
        return view('frontend.create-shop');
    }

    public function createShopStore(Request $request){
        $data = $request->all();
        $password = $data['password'];
//        $data['phone'] = '+923'.$data['phone'];
        $data['phone'] = '+237'.$data['phone'];

        $sign = $request->sign;
        if ($sign) {
            $ext = pathinfo($sign->getClientOriginalName(), PATHINFO_EXTENSION);
            $imageName = preg_replace('/[^a-zA-Z0-9]/', '', $request['sign']);
            $imageName = $imageName . '.' . $ext;
            $sign->move('public/images/user', $imageName);

            $data['sign'] = $imageName;
        }

        if($data['password'] != $data['confirm_password']) {
            return redirect()->route('shop.signup')->with('not_permitted', 'Password and confirm password does not match');
        }

        $data['email'] = $data['email'] ? $data['email'] : 'vendor@gmail.com';
        $data['city'] = $data['city'] ? $data['city'] : 'vendor city';
        $data['state'] = $data['state'] ? $data['state'] : 'vendor state';
        $data['address'] = $data['address'] ? $data['address'] : 'vendor address';
        $data['mtn_phone'] =  $data['phone'];
        $data['is_active'] = false;
        $data['is_deleted'] = false;
        $data['role_id'] = 12;
        $data['password'] = bcrypt($password);
        $user = User::create($data);

        if ($user) {
            $data['user_id'] = $user->id;
            $data['customer_group_id'] = 1;
            $data['phone_number'] = $data['phone'];
            $data['is_active'] = true;
            Customer::create($data);
            $this->sendWhatsappMsgForVendorAccount($user, $password);
            $this->sendWhatsappMsgForVendorAccountToAdmin($user, $password);
        }

        return redirect()->route('shop.login')->with('success', 'Congratulaton: Your shop has been created, And it is under review');
    }

    public function shop($products_pick, $category = null, $brand = null) {
        $categories = Category::where('is_active', 1)->take('5')->get();
        $next_categories = Category::where('is_active', 1)->take('5')->skip(5)->get();

        $brands = Brand::where('is_active', 1)->take('5')->get();
        $next_brands = Brand::where('is_active', 1)->take('5')->skip(5)->get();

        $products = Product::where('is_active', true)->where('price', '>', 0)->where('type', '!=', 'donation')->where('type','!=' , 'service');

        if($category != null && $category != 'null') {
            $products = $products->where('category_id', $category);
        }

        if($brand != null && $brand != 'null') {
            $products = $products->where('brand_id', $brand);
        }

        $products = $products->orderByDesc('id');

        $products_count = $products->count();

        if($products_pick > $products_count) {
            $products_pick = $products_count;
        }
        $products = $products->paginate($products_pick);


        return view('frontend.shop', compact('products', 'products_pick', 'products_count', 'categories', 'next_categories', 'brands', 'next_brands'));
    }

    public function vendorProducts($id) {

        $products_pick = 12;
        $products = Product::where('is_active', true)->where('price', '>', 0)->where('type', '!=', 'donation')->where('type','!=' , 'service')->where('vendor_id', $id)->orderByDesc('id');

        $products_count = $products->count();

        $products = $products->paginate(12);


        return view('frontend.vendor_shop', compact('products', 'products_pick', 'products_count'));
    }

    public function donation($products_pick) {
        $categories = Category::where('is_active', 1)->take('5')->get();
        $next_categories = Category::where('is_active', 1)->take('5')->skip(5)->get();

        $brands = Brand::where('is_active', 1)->take('5')->get();
        $next_brands = Brand::where('is_active', 1)->take('5')->skip(5)->get();

        $products = Product::where('type', 'donation')->where('is_active', true);
        $products = $products->orderByDesc('id');

        $products_count = $products->count();

        if($products_pick > $products_count) {
            $products_pick = $products_count;
        }
        $products = $products->paginate($products_pick);


        return view('frontend.donation', compact('products', 'products_pick', 'products_count', 'categories', 'next_categories', 'brands', 'next_brands'));
    }

    public function vendors($vendors_pick) {

        $vendors = User::where('role_id', 12)->where('is_active', true)->orderByDesc('id');
        $vendors_count = $vendors->count();

        if($vendors_pick > $vendors_count) {
            $vendors_pick = $vendors_count;
        }
        $vendors = $vendors->paginate($vendors_pick);


        return view('frontend.vendor', compact('vendors', 'vendors_pick', 'vendors_count'));
    }

    public function productSearchVendor(Request $request, User $user) {

        $vendors = $user->where([
            ['name', 'LIKE', "%$request->search%"],
            ['is_active', true],
            ['role_id', 12],
        ])
        ->orWhere([
            ['company_name', 'LIKE', "%$request->search%"],
            ['is_active', true],
            ['role_id', 12],
        ]);
        $vendors_count = $vendors->count();
        $vendors_pick = 12;

        if($vendors_pick > $vendors_count) {
            $vendors_pick = $vendors_count;
        }
        $vendors = $vendors->paginate($vendors_pick);


        return view('frontend.vendor', compact('vendors', 'vendors_pick', 'vendors_count'));
    }

    public function rent($products_pick) {
        $categories = Category::where('is_active', 1)->take('5')->get();
        $next_categories = Category::where('is_active', 1)->take('5')->skip(5)->get();

        $brands = Brand::where('is_active', 1)->take('5')->get();
        $next_brands = Brand::where('is_active', 1)->take('5')->skip(5)->get();

        $products = Product::where('is_active', true)
            ->where('type', '!=', 'donation')
            ->where('type','!=' , 'service')
            ->where('rent_price_per_hour', '>', 0)
            ->orWhere('rent_price_per_day', '>', 0)
            ->orWhere('rent_price_per_month', '>', 0)
            ->orderByDesc('id');

        $products_count = $products->count();

        if($products_pick > $products_count) {
            $products_pick = $products_count;
        }
        $products = $products->paginate($products_pick);


        return view('frontend.shop-rent', compact('products', 'products_pick', 'products_count', 'categories', 'next_categories', 'brands', 'next_brands'));
    }

    public function service($products_pick) {
        $categories = Category::where('is_active', 1)->take('5')->get();
        $next_categories = Category::where('is_active', 1)->take('5')->skip(5)->get();

        $brands = Brand::where('is_active', 1)->take('5')->get();
        $next_brands = Brand::where('is_active', 1)->take('5')->skip(5)->get();

        $products = Product::where('is_active', true)
            ->where('type' , 'service')
            ->where('is_variant' , 1)
            ->orderByDesc('id');

        $products_count = $products->count();
        $products = $products->paginate($products_pick);

        $varientProducts = Product::where('is_active', true)
            ->where('type' , 'service')
            ->where('is_variant' , 1)
            ->orderByDesc('id')
            ->get();


        return view('frontend.service', compact('varientProducts', 'products', 'products_pick', 'products_count', 'categories', 'next_categories', 'brands', 'next_brands'));
    }

    public function serviceVarient($id) {
        $html = '';
        $product_variants = Product::with('variant')->where('id', $id)->first();
        $start_date = date('Y-m-d H:i:s');
        $date = strtotime($start_date);
        if($product_variants && $product_variants->variant) {
            foreach ($product_variants->variant as $item) {
                $new_date = strtotime($item->name, $date);
                $display_date = $item->name . ' / ' .date('D Y-M-d h:i A', $new_date);
                if($item->pivot->additional_price == 0){
                    $html .= "<option value='".$display_date."' data-price='".$item->pivot->additional_price."' selected>".$display_date."</option>";
                } else {
                    $html .= "<option value='".$display_date."' data-price='".$item->pivot->additional_price."'>".$display_date."</option>";
                }
            }
        }
        return response()->json([
            'html' => $html,
            'price' => $product_variants->price
        ]);
    }

    public function shopProductSearchByPrice(Request $request) {
        $data = '';
        $min = (int)$request->min;
        $max = (int)$request->max;
        $products = Product::where('is_active', true)
            ->whereBetween('price', [$min, $max])
            ->get();

        foreach($products as $product) {
            $data .= '<div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 col-12">';
            $data .= '<div class="card-grid-style-3"><div class="card-grid-inner"><div class="image-box">';
            $data .= '<a href="' . route('product', ['id' => $product->id]) . '">';
            $product_image = explode(",", $product->image);
            $product_image = htmlspecialchars($product_image[0]);
            $data .= '<img src="'. url('public/images/product', $product_image) .'" alt="Ecom"></a></div>';
            $data .= '<div class="info-right"><a class="font-xs color-gray-500" href="">'. @$product->category->name .'</a><br>';
            $data .= '<a class="color-brand-3 font-sm-bold" href="'. route('product', ['id' => $product->id]) .'">'. $product->name .'</a>';
            $data .= '<div class="price-info"><strong class="font-lg-bold color-brand-3 price-main">'. number_format($product->price, 2) .' CFA</strong></div>';
            if($product->qty > 0 || $product->type == 'digital') {
            $data .= '<div class="mt-20 box-btn-cart"><a class="btn btn-cart" onclick="addtocart(`'. $product->id .'`,`/addToCart`)">Add To Cart</a></div>';
            } else {
            $data .= '<div class="mt-20 box-btn-cart">Out Of Stock</div>';
            }
            $data .= '<div>'. $product->product_detail .'</div></div></div></div></div>';
        }

        return $data;

    }

    public function contact() {
        return view('frontend.contact');
    }

    public function contactMessage(Request $request) {
        $msg = $request->message;
        $phone = $request->phone;
        try {
            $this->wpMessage($phone, $msg);
        } catch (\Exception $e) {
            return back()->with('not_permitted', 'Something went wrong...!');
        }
        return back()->with('message', 'We Received your valuable message, we will get back to you shortly...!');

    }

    public function productSearch(Request $request) {

        $products_pick = 28;
        $categories = Category::where('is_active', 1)->take('5')->get();
        $next_categories = Category::where('is_active', 1)->take('5')->skip(5)->get();

        $brands = Brand::where('is_active', 1)->take('5')->get();
        $next_brands = Brand::where('is_active', 1)->take('5')->skip(5)->get();

        $products = Product::where('is_active', true)->where('type', '!=', 'donation')->where('type','!=' , 'service');
        if($request->category != 0) {
            $products = $products->where('category_id', $request->category);
        }
        if($request->search != null) {
            $products = $products->where('name', 'LIKE', "%{$request->search}%") ;
        }
        $products =  $products->orderByDesc('id')->paginate($products_pick);

        $products_count = $products->count();

        if($products_pick > $products_count) {
            $products_pick = $products_count;
        }

        return view('frontend.shop', compact('products', 'products_pick', 'products_count', 'categories', 'next_categories', 'brands', 'next_brands'));

    }

    public function productSearchDonation(Request $request) {

        $products_pick = 28;

        $products = Product::where('is_active', true)->where('type', 'donation');

        if($request->search != null) {
            $products = $products->where('name', 'LIKE', "%{$request->search}%") ;
        }
        $products =  $products->orderByDesc('id')->paginate($products_pick);

        $products_count = $products->count();

        if($products_pick > $products_count) {
            $products_pick = $products_count;
        }

        return view('frontend.donation', compact('products', 'products_pick', 'products_count'));

    }

    public function productSearchRent(Request $request, Product $product) {

        $products_pick = 28;


        $products = $product;

        $products = $products->where([
                                ['name', 'LIKE', "%{$request->search}%"],
                                ['type', '!=', 'donation'],
                                ['type', '!=', 'service'],
                                ['is_active', true],
                                ['rent_price_per_month', '>', 0],
                            ])
                            ->orWhere([
                                ['name', 'LIKE', "%{$request->search}%"],
                                ['type', '!=', 'donation'],
                                ['type', '!=', 'service'],
                                ['is_active', true],
                                ['rent_price_per_day', '>', 0],
                            ])
                            ->orWhere([
                                ['name', 'LIKE', "%{$request->search}%"],
                                ['type', '!=', 'donation'],
                                ['type', '!=', 'service'],
                                ['is_active', true],
                                ['rent_price_per_hour', '>', 0],
                            ]);

        $products =  $products->orderByDesc('id')->paginate($products_pick);

        $products_count = $products->count();

        if($products_pick > $products_count) {
            $products_pick = $products_count;
        }

        return view('frontend.shop-rent', compact('products', 'products_pick', 'products_count'));

    }


}
