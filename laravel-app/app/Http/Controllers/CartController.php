<?php

namespace App\Http\Controllers;

use App\Biller;
use App\Booking;
use App\BookingProduct;
use App\CashRegister;
use App\Customer;
use App\GeneralSetting;
use App\Order;
use App\OrderProduct;
use App\Product;
use App\Product_Warehouse;
use App\ProductBatch;
use App\ProductVariant;
use App\StockDuration;
use App\Unit;
use App\User;
use App\Variant;
use App\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Psy\Command\WhereamiCommand;

class CartController extends Controller
{
    public function cart(){
        $best_selling = Product::where('is_active', true)->where('type', '!=', 'donation')->orderByDesc('qty')->take(4)->get();
        return view('frontend.cart', compact('best_selling'));
    }

    public function rentCart(){
        $best_selling = Product::where('is_active', true)->where('type', '!=', 'donation')->orderByDesc('qty')->take(4)->get();
        return view('frontend.rent-cart', compact('best_selling'));
    }

    public function checkout(){
        $best_selling = Product::where('is_active', true)->where('type', '!=', 'donation')->orderByDesc('qty')->take(5)->get();
        return view('frontend.checkout', compact('best_selling'));
    }

    public function rentCheckout(){
        $best_selling = Product::where('is_active', true)->where('type', '!=', 'donation')->orderByDesc('qty')->take(5)->get();
        return view('frontend.rent-checkout', compact('best_selling'));
    }

    public function deleteItem(Request $request)
    {
        $id = $request->id;
        $cart = session()->get('cart');
        unset($cart[$id]);
        session()->put('cart', $cart);
        return $cart;
    }

    public function updateQuantityyNumber(Request $request)
    {
        $id = $request->id;
        $cart = session()->get('cart');
        if (isset($cart[$id])) {
            $cart[$id]['quantity'] = $request->qty;
            session()->put('cart', $cart);
            return $cart;
        }
    }

    public function updateQuantityy(Request $request)
    {
        $id = $request->id;
        $cart = session()->get('cart');
        if (isset($cart[$id])) {
            $cart[$id]['quantity']++;
            session()->put('cart', $cart);
            return $cart;
        }
    }

    public function updateQuantityyminus(Request $request)
    {
        $id = $request->id;
        $cart = session()->get('cart');
        if (isset($cart[$id])) {
            $cart[$id]['quantity']--;
            if($cart[$id]['quantity'] <= 0) {
                $cart[$id]['quantity'] = 1;
            }
            session()->put('cart', $cart);
            return $cart;
        }

    }

    public function deleteItemRent(Request $request)
    {
        $id = $request->id;
        $cart = session()->get('rent_cart');
        unset($cart[$id]);
        session()->put('rent_cart', $cart);
        return $cart;
    }

    public function updateQuantityyNumberRent(Request $request)
    {
        $id = $request->id;
        $cart = session()->get('rent_cart');
        if (isset($cart[$id])) {
            $cart[$id]['quantity'] = $request->qty;
            session()->put('rent_cart', $cart);
            return $cart;
        }
    }

    public function updateQuantityyRent(Request $request)
    {
        $id = $request->id;
        $cart = session()->get('rent_cart');
        if (isset($cart[$id])) {
            $cart[$id]['quantity']++;
            session()->put('rent_cart', $cart);
            return $cart;
        }
    }

    public function updateQuantityyminusRent(Request $request)
    {
        $id = $request->id;
        $cart = session()->get('rent_cart');
        if (isset($cart[$id])) {
            $cart[$id]['quantity']--;
            if($cart[$id]['quantity'] <= 0) {
                $cart[$id]['quantity'] = 1;
            }
            session()->put('rent_cart', $cart);
            return $cart;
        }

    }


    public function addToCart(Request $request)
    {
        $quantity = $request->quantity ?? 1;
        $id = $request->id;

        $product = Product::where('id', $id)->first();

        $product_price = $product->price;

        $product_image = explode(",", $product->image);
        $product_image = htmlspecialchars($product_image[0]);

        $cart = Session::get('cart');

        if (!$cart) {

            $cart = [
                $id => [
                    "name" => $product->name,
                    "quantity" => $quantity,
                    "price" => $product_price,
                    "image" => $product_image,
                    "products_id" => $id,
                    "product_name" => $product->name,
                    "vendor_id" => $product->vendor_id,
                    "o_id" => '',

                ]
            ];
            Session::put('cart', $cart);
            if (Session::has('cart')) {
                foreach (session('cart') as $id => $detail) {

                    @$number += $detail['quantity'];
                    @$prices += $detail['price'] * $detail['quantity'];
                }
            }
            $response = array(
                "number" => $number,
                "price" => $prices,
            );
            return (json_encode($response));
            // return ('frontEnd.layouts.newheader');
        }
        // if cart not empty then check if this product exist then increment quantity

        if (isset($cart[$id])) {
            $cart[$id]['quantity'] += $quantity;
            Session::put('cart', $cart);
            if (Session::has('cart')) {

                foreach (session('cart') as $id => $detail) {

                    @$number += $detail['quantity'];
                    @$prices += $detail['price'] * $detail['quantity'];

                }
            }
            $response = array(
                "number" => $number,
                "price" => $prices,
            );


            // return view('frontEnd.layouts.newheader');
            return (json_encode($response));
        }
        // if item not exist in cart then add to cart with quantity = 1


        $cart[$id] = [
            "name" => $product->name,
            "quantity" => $quantity,
            "price" => $product_price,
            "image" => $product_image,
            "products_id" => $id,
            "product_name" => $product->name,
            "vendor_id" => $product->vendor_id,
            "o_id" => '',

        ];

        Session::put('cart', $cart);

        if (Session::has('cart')) {
            foreach (session('cart') as $id => $detail) {

                @$number += $detail['quantity'];
                @$prices += $detail['price'] * $detail['quantity'];

            }
        }
        $response = array(
            "number" => $number,
            "price" => $prices,
        );

        return (json_encode($response));
    }

    public function addToRentCart(Request $request)
    {
        $quantity = $request->quantity ?? 1;
        $id = $request->id;

        $product = Product::where('id', $id)->first();

        $product_image = explode(",", $product->image);
        $product_image = htmlspecialchars($product_image[0]);

        $cart = Session::get('rent_cart');

        $method = 0;
        if ($product->rent_price_per_hour > 0) {
           $rent_price =  $product->rent_price_per_hour;
        } elseif ($product->rent_price_per_day > 0) {
            $method = 1;
            $rent_price =  $product->rent_price_per_day;
        } else {
            $rent_price =  $product->rent_price_per_month;
            $method = 2;
        }

        if (!$cart) {

            $cart = [
                $id => [
                    "name" => $product->name,
                    "quantity" => $quantity,
                    "price" => $rent_price,
                    "image" => $product_image,
                    "products_id" => $id,
                    "product_name" => $product->name,
                    "vendor_id" => $product->vendor_id,
                    "method" => $method,
                    "number" => 1,
                    "start" => '',
                    "end" => '',

                ]
            ];
            Session::put('rent_cart', $cart);
            if (Session::has('rent_cart')) {
                foreach (session('rent_cart') as $id => $detail) {

                    @$number += $detail['quantity'];
                    @$prices += $detail['price'] * $detail['quantity'];
                }
            }
            $response = array(
                "number" => $number,
                "price" => $prices,
            );
            return (json_encode($response));
            // return ('frontEnd.layouts.newheader');
        }
        // if cart not empty then check if this product exist then increment quantity

        if (isset($cart[$id])) {
            $cart[$id]['quantity'] += $quantity;
            Session::put('rent_cart', $cart);
            if (Session::has('rent_cart')) {

                foreach (session('rent_cart') as $id => $detail) {

                    @$number += $detail['quantity'];
                    @$prices += $detail['price'] * $detail['quantity'];

                }
            }
            $response = array(
                "number" => $number,
                "price" => $prices,
            );
            return (json_encode($response));
        }
        // if item not exist in cart then add to cart with quantity = 1


        $cart[$id] = [
            "name" => $product->name,
            "quantity" => $quantity,
            "price" => $rent_price,
            "image" => $product_image,
            "products_id" => $id,
            "product_name" => $product->name,
            "vendor_id" => $product->vendor_id,
            "method" => $method,
            "number" => 1,
            "start" => '',
            "end" => '',

        ];

        Session::put('rent_cart', $cart);

        if (Session::has('rent_cart')) {
            foreach (session('rent_cart') as $id => $detail) {

                @$number += $detail['quantity'];
                @$prices += $detail['price'] * $detail['quantity'];

            }
        }
        $response = array(
            "number" => $number,
            "price" => $prices,
        );

        return (json_encode($response));
    }

    public function otpScreen(){
//        dd(Session::get('otp'));
        return view('frontend.otp_screen');
    }

    private function createUser($data) {
        $password = 12345678;
        $split = explode(" ",$data['name']);
        $data['name'] = $split[0];
        $data['is_active'] = true;
        $data['is_deleted'] = false;
        $data['role_id'] = 5;
        $data['password'] = bcrypt($password);
        $data['phone'] = $data['phone'];
        $user = User::create($data);

        if ($user) {
            $data['user_id'] = $user->id;
            $data['customer_group_id'] = 1;
            $data['name'] = $data['name'];
            $data['phone_number'] = $data['phone'];
            $data['is_active'] = true;
            Customer::create($data);
            $this->sendWhatsappMsgForAccount($user, $password);
        }
        return $user;
    }

    public function otpResend()
    {
        $user_data = Session::get('user_data');

        if ($user_data) {
            $phone = $user_data['phone'];
        } else {
            $user = Auth::user();
            $phone = $user->phone;
        }
        $otp = $this->sendOTP($phone);
        Session::put('otp', $otp);

        return back()->with('success', 'OTP have been sent again to your whatsapp');

    }


    public function donateStore(Request $request) {
        $product = Product::where('id', $request->product_id)->first();
        $data = $request->all();
        $data['vendor_id'] = $product->vendor_id;
        if(!Auth::user()) {
//            $data['phone'] = '+923'.$data['phone'];
            $data['phone'] = '+237'.$data['phone'];
            $data['product_id'] = $product->id;
        }
        $data['address'] = $data['address'] ? $data['address'] : 'customer address';
        $data['email'] = $data['email'] ? $data['email'] : 'guest@gmail.com';
        $data['city'] = $data['city'] ? $data['city'] : 'guest city';
        $data['state'] = $data['state'] ? $data['state'] : 'guest state';
        $data['mtn_phone'] = $data['mtn_phone'] ? '+237'.$data['mtn_phone'] : $data['phone'];
        if(Auth::user()) {
            $user = Auth::user();
        } else {
            $user = User::where('phone', $data['phone'])->where('role_id', 5)->where('is_active', true)->first();
        }
        if ($user == null) {
            $user = $this->createUser($data);
        }
        return $this->placeDonation($data, $product, $user, $request->donation_amount);
    }

    public function serviceStore(Request $request) {
        $product = Product::where('id', $request->product_id)->first();
        $data = $request->all();
        if(isset($data['total'])) {
            $price = $data['total'];
            unset($data['total']);
        } else {
            $price = $product->price;
        }

        if(!Auth::user()) {
//            $data['phone'] = '+923'.$data['phone'];
            $data['phone'] = '+237'.$data['phone'];
            $data['product_id'] = $product->id;
        }
        $data['address'] = $data['address'] ? $data['address'] : 'customer address';
        $data['email'] = $data['email'] ? $data['email'] : 'guest@gmail.com';
        $data['city'] = $data['city'] ? $data['city'] : 'guest city';
        $data['state'] = $data['state'] ? $data['state'] : 'guest state';
        $data['mtn_phone'] = $data['mtn_phone'] ? '+237'.$data['mtn_phone'] : $data['phone'];

        if(Auth::user()) {
            $user = Auth::user();
        } else {
            $user = User::where('phone', $data['phone'])->where('role_id', 5)->where('is_active', true)->first();
        }

        if ($user == null) {
            $user = $this->createUser($data);
        }
        $order = $this->placeService($data, $product, $user, $price);
        if($order == false) {
            return redirect()->route('service')->with('not_permitted', 'Phone number is incorrect');
        }

        if($data['payment_method'] == 'MTN') {
            $token = getenv("MOMO_TOKEN");
            $route = route('service.payment.check');
            $failure_route = route('service', ['products' => 12]);
            $link = $this->mobileMoneyOrderRequestLink($token, $order->grand_total, $route, $order->id, $failure_route, $data['mtn_phone']);
            if ($link == false) {
                return redirect()->to($failure_route)->with('not_permitted', 'Service is not completed, Something in wrong....!');
            }
            header("Location: $link");
            die();
        } else {
            $message = 'Congratulation You have place an Service Order. Admin will approve order.';
            return view('frontend.service_complete', compact('message', 'order', 'user'));
        }
    }

    public function order(Request $request) {
        $data = $request->all();
        $grand_total = 0;
        $order_array = [];
        if(!Auth::user()) {
//            $data['phone'] = '+923'.$data['phone'];
            $data['phone'] = '+237'.$data['phone'];
        }
        $data['email'] = $data['email'] ? $data['email'] : 'guest@gmail.com';
        $data['city'] = $data['city'] ? $data['city'] : 'guest city';
        $data['state'] = $data['state'] ? $data['state'] : 'guest state';
        $data['mtn_phone'] = $data['mtn_phone'] ? '+237'.$data['mtn_phone'] : $data['phone'];

        if(Auth::user()) {
            $user = Auth::user();
        } else {
            $user = User::where('phone', $data['phone'])->where('role_id', 5)->where('is_active', true)->first();
        }

        if ($user == null) {
            $user = $this->createUser($data);
        }
        $cart = Session::get('cart');

        foreach ($cart as $item) {
            $multimple_carts[$item['vendor_id']][] = $item;
            $grand_total += $item['price'] * $item['quantity'];
        }
        foreach($multimple_carts as $key => $cart) {
            $data['vendor_id'] = $key;
            $order = $this->placeOrder($data, $cart, $user);
            if($order == false) {
                return back()->with('not_permitted', 'Something went wrong.....!');
            }
            $order_array[] = $order->id;
        }
        if($data['payment_method'] == 'MTN') {
                $token = getenv("MOMO_TOKEN");
                $route = route('order.payment.check');
                $failure_route = url()->previous();
                $orders = implode(',', $order_array);
                $link = $this->mobileMoneyOrderRequestLink($token, $grand_total, $route, $orders, $failure_route, $data['mtn_phone']);

                if ($link == false) {
                    return back()->with('not_permitted', 'Order is not completed, Something in wrong....!');
                }
                Session::forget('cart');
                Session::forget('user_data');
                Session::forget('otp');
                header("Location: $link");
                die();
        } else {
            $message = 'You have placed an order. Please Clear your payment on delivery Time.';
        }

        Session::forget('cart');
        Session::forget('user_data');
        Session::forget('otp');
        return view('frontend.order_complete', compact('message', 'order', 'user'));
    }

    public function rentOrder(Request $request) {
        $data = $request->all();

        $cart = Session::get('rent_cart');
        if (!Auth::user()) {
//            $data['phone'] = '+923'.$data['phone'];
            $data['phone'] = '+237'.$data['phone'];
        }
        $data['email'] = $data['email'] ? $data['email'] : 'guest@gmail.com';
        $data['city'] = $data['city'] ? $data['city'] : 'guest city';
        $data['state'] = $data['state'] ? $data['state'] : 'guest state';
        $data['mtn_phone'] = $data['mtn_phone'] ? '+237'.$data['mtn_phone'] : $data['phone'];

        if(Auth::user()) {
            $user = Auth::user();
        } else {
            $user = User::where('phone', $data['phone'])->where('role_id', 5)->where('is_active', true)->first();
        }

        if ($user == null) {
            $user = $this->createUser($data);
        }
        $data['user_id'] = $user->id;
        $order = $this->placeRentOrder($data, $cart);

        Session::forget('rent_cart');
        if($order == false) {
            return back()->with('not_permitted', 'Phone number is incorrect');
        }
        if($data['payment_method'] == 'MTN') {
            $token = getenv("MOMO_TOKEN");
            $route = route('booking.payment.check');
            $failure_route = url()->previous();
            $link = $this->mobileMoneyOrderRequestLink($token, $order->grand_total, $route, $order->id, $failure_route, $data['mtn_phone']);

            if ($link == false) {
                return back()->with('not_permitted', 'Booking is not completed, Something in wrong....!');
            }
            header("Location: $link");
            die();
        } else {
            $message = 'You have placed an Booking. Please Clear your payment on delivery Time.';
        }
        session(['booking_invoice_' . $order->id => true]);
        return view('frontend.rent_order_complete', compact('message', 'order', 'user'));
    }

    public function placeRentOrder($data, $cart){
        $customer = Customer::where('user_id', $data['user_id'])->first();
        if(!$customer) {
            return false;
        }
        $data['customer_id'] = $customer->id;
        $data['booking_status'] = 2;
        $data['is_frontend'] = 1;
        $data['warehouse_id'] = Warehouse::where('is_active', true)->first()->id;
        $data['biller_id'] = Biller::where('is_active', true)->first()->id;
        $data['cash_register_id'] = CashRegister::where('status', true)->first()->id;
        $data['total_discount'] = 0;
        $data['total_tax'] = 0;
        $data['tax_rate'] = 0;
        $data['item'] = 0;
        $data['total_price'] = 0;

        foreach ($cart as $item) {
            $data['item'] += $item['quantity'];
            $data['total_price'] += $item['price'] * $item['number'] * $item['quantity'];
        }
        $data['grand_total'] = $data['total_price'];
        $data['paid_amount'] = $data['total_price'];
        $data['total_qty'] = $data['item'];


        $data['payment_status'] = 1;
        if ($data['payment_method'] == 'COD') {
            $data['payment_status'] = 4;
            $data['reference_no'] = 'br-' . date("Ymd") . '-'. date("his");
        } else {
            $data['reference_no'] = '';
        }

        $lims_sale_data = Booking::create($data);

        foreach ($cart as $id => $item) {
            $product_sale['multi_product_batch_id'] = null;
            $product_sale['multi_product_batch_qty'] = null;
            $lims_product_data = Product::where('id', $id)->first();
            $product_sale['variant_id'] = null;
            $product_sale['product_batch_id'] = null;


            $lims_sale_unit_data  = Unit::where('id', $lims_product_data->sale_unit_id)->first();
            if($lims_sale_unit_data == null) {
                $lims_sale_unit_data  = Unit::where('id', $lims_product_data->unit_id)->first();
                if($lims_sale_unit_data == null) {
                    $lims_sale_unit_data  = Unit::first();
                }
            }
            $sale_unit_id = $lims_product_data->sale_unit_id;
            if($lims_product_data->is_variant) {
                $lims_product_variant_data = ProductVariant::select('id', 'variant_id', 'qty')->FindExactProductWithCode($id, $lims_product_data->product_code)->first();
                $product_sale['variant_id'] = $lims_product_variant_data->variant_id;
            }
            if($lims_product_data->type == 'digital')
                $mail_data['file'][$id] = url('/public/product/files').'/'.$lims_product_data->file;
            else
                $mail_data['file'][$id] = '';

            $mail_data['unit'][$id] = $lims_sale_unit_data->unit_code;
            $product_sale['booking_id'] = $lims_sale_data->id ;
            $product_sale['category_id'] = $lims_product_data->category_id;
            $product_sale['warehouse_id'] = (int)$data['warehouse_id'];
            $product_sale['product_id'] = $id;
            $product_sale['qty'] = $mail_data['qty'][$id] = $item['quantity'];
            $product_sale['sale_unit_id'] = $sale_unit_id;
            $product_sale['net_unit_price'] = $item['price'];
            $product_sale['discount'] = $data['total_discount'];
            $product_sale['tax_rate'] = $data['tax_rate'];
            $product_sale['number_duration'] = $mail_data['number'][$id] = $item['number'];
            $product_sale['start'] = $mail_data['start'][$id] = $item['start'];
            $product_sale['end'] = $mail_data['end'][$id] = $item['end'];
            $product_sale['booking_method'] = $mail_data['booking_method'][$id] = $item['method'];
            $product_sale['tax'] = $data['total_tax'];
            $product_sale['total'] = $mail_data['total'][$id] = $data['total_price'];
            BookingProduct::create($product_sale);
        }


        if($data['payment_method'] == 'COD') {
            $this->sendWhatsappMsgForPlacingOrderToBuyerBooking($lims_sale_data);
            $this->sendWhatsappMsgForPlacingOrderToAdminBooking($lims_sale_data);
        }

        return $lims_sale_data;
    }

    public function stockDurationSave($id, $qty) {
        $stockDuration = StockDuration::where([
            'product_id' => $id,
            'restock' => null
        ])->first();
        if ($qty == 0.0) {
            if(!$stockDuration) {
                StockDuration::create([
                    'product_id' => $id,
                    'out_of_stock' => date('Y-m-d')
                ]);
            }
        } else {
            if ($stockDuration) {
                $stockDuration->update(['restock' => date('Y-m-d')]);
            }
        }
    }

    public function orderRceived($id) {
        Order::where('id', $id)->update(['order_received' => 1]);
        return back()->with('message', 'Thank you, You have confirmed order delivery');
    }

    public function orderPayment($id) {
        $order = Order::where('id', $id)->first();

        $token = getenv("MOMO_TOKEN");
        $route = route('order.payment.check');
        $failure_route = url()->previous();
        $link = $this->mobileMoneyOrderRequestLink($token, $order->grand_total, $route, $order->id, $failure_route, $order->mtn_phone);

        if ($link == false) {
            return back()->with('not_permitted', 'Order is not completed, Something in wrong....!');
        }
        header("Location: $link");
        die();
    }

    public function servicePayment($id) {
        $order = Order::where('id', $id)->first();

        $token = getenv("MOMO_TOKEN");
        $route = route('service.payment.check');
        $failure_route = url()->previous();
        $link = $this->mobileMoneyOrderRequestLink($token, $order->grand_total, $route, $order->id, $failure_route, $order->mtn_phone);

        if ($link == false) {
            return back()->with('not_permitted', 'Service Order is not completed, Something in wrong....!');
        }
        header("Location: $link");
        die();
    }

    public function otpVerify(Request $request, Session $session) {
        if ($session::get('otp') == $request->otp) {
            if($request->user() == null) {
                $cart = $session::get('cart');
                $user_data = $session::get('user_data');
                $user = $this->createUser($user_data);
                if(!empty($cart)) {
                    $order = $this->placeOrder($user_data, $cart, $user);
                    if($order == false) {
                        return back()->with('not_permitted', 'Phone number is incorrect');
                    }
                    $message = 'Please Dial *126# for MTN or #150* or Orange and enter your password to approve order.';
                    $session::forget('otp');
                    return view('frontend.order_complete', compact('message', 'order', 'user'));
                } else {
                    $product = Product::where('id', $user_data['product_id'])->first();
                    $order = $this->placeDonation($user_data, $product, $user, $user_data['amount']);
                    if($order == false) {
                        return back()->with('not_permitted', 'Phone number is incorrect');
                    }
                    $message = 'Please Dial *126# for MTN or #150* or Orange and enter your password to approve donation.';
                    $session::forget('otp');
                    return view('frontend.donation_complete', compact('message', 'order', 'user'));
                }
            } else {
                $session::forget('otp');
                return redirect()->route('frontend.home');
            }
        }
        Auth::logout();
        return redirect()->route('shop.login')->with('not_permitted', 'OTP is Incorrect, please try again');
    }

    public function mobileMoneyRequest($token, $number, $amount){

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://www.campay.net/api/collect/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS =>'{"amount":"'.$amount.'","from":"'.$number.'","description":"Order Payment","external_reference": ""}',
            CURLOPT_HTTPHEADER => array(
                'Authorization: Token ' . $token,
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);
        $response_decode = json_decode($response, true);

        curl_close($curl);

        if($response_decode && isset($response_decode['reference'])) {
            return $response_decode['reference'];
        }

        return false;
    }

    public function mobileMoneyRequestLinkInController($token, $amount, $route, $order_id, $failure_route, $number){

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://www.campay.net/api/get_payment_link/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS =>'{
                "amount": "'.$amount.'",
                "from": "'.$number.'",
                "currency": "XAF",
                "external_reference": "'.$order_id.',' .$failure_route.'",
                "redirect_url": "'.$route.'",
                "payment_options":"MOMO,CARD",
                "failure_redirect_url": "'.$route.'"
            }',
            CURLOPT_HTTPHEADER => array(
                'Authorization: Token ' . $token,
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);
        $response_decode = json_decode($response, true);

        curl_close($curl);

        if($response_decode && isset($response_decode['link'])) {
            return $response_decode['link'];
        }
        return false;
    }


    private function placeDonation($user_data, $cart, $user, $amount) {
        $data = $user_data;
        $data['user_id'] = $user->id;
        $data['is_donation'] = 1;
        $data['payment_status'] = 2;
        $data['grand_total'] = $amount;

        $token = getenv("MOMO_TOKEN");
        $route = route('donation.payment.check');
        $failure_route = url()->previous();


        unset($data['product_id']);
        unset($data['donation_amount']);
        $order = Order::create($data);

        $product_sale['order_id'] = $order->id;
        $product_sale['product_id'] = $cart->id;
        $product_sale['quantity'] = 1;
        $product_sale['price'] =  $amount;
        $product_sale['sub_total'] = $amount;

        OrderProduct::create($product_sale);

        $link = $this->mobileMoneyRequestLinkInController($token, $amount, $route, $order->id, $failure_route, $data['mtn_phone']);

        if ($link == false) {
            return back()->with('not_permitted', 'Donation is not completed, Something in wrong....!');
        }
        header("Location: $link");
        die();
    }

    public function placeServiceAfterPayment(Request $request)
    {
        if($request->external_reference) {
            $external_reference = explode(',', $request->external_reference);
        }

        $reference = $request->reference;
        $previous = $external_reference[0];
        $order_id = $external_reference[1];

        if($request->status == 'SUCCESSFUL') {
            $status = 1;
        } elseif ($request->status == 'FAILED') {
            $status = 2;
        } elseif ($request->status == 'PENDING') {
            $status = 0;
        } else {
            return redirect()->to($previous)->with('not_permitted', 'Service is not completed, Something went wrong....!');
        }

        Order::where('id', $order_id)->update(['payment_status'=>$status, 'reference' => $reference]);
        $order = Order::where('id', $order_id)->first();

        if($status == 2) {
            return redirect()->to($previous)->with('not_permitted', 'Payment is Rejected....!');
        }
        if($status == 1) {
            $this->sendWhatsappMsgForPlacingServiceToBuyer($order);
            $this->sendWhatsappMsgForPlacingServiceToSaller($order);
            Session::put('order', $order);
            return redirect()->route('service.payment.check.display');
        }
        return redirect()->to($previous)->with('not_permitted', 'Service is not completed, Something in wrong....!');
    }

    public function placeServiceAfterPaymentDisplay()
    {
        $order = Session::get('order');
        $user = User::where('id', $order->user_id)->first();
        $message = 'Thank you for your Service Order.';
        Session::forget('order');
        return view('frontend.service_complete', compact('message', 'order', 'user'));
    }

    public function placeDonationAfterPayment(Request $request)
    {
        $general_setting = GeneralSetting::first();

        if($request->external_reference) {
            $external_reference = explode(',', $request->external_reference);
        }

        $reference = $request->reference;
        $previous = $external_reference[1];
        $order_id = $external_reference[0];

        if($request->status == 'SUCCESSFUL') {
            $status = 1;
        } elseif ($request->status == 'FAILED') {
            $status = 2;
        } elseif ($request->status == 'PENDING') {
            $status = 0;
        } else {
            return redirect()->to($previous)->with('not_permitted', 'Donation is not completed, Something in wrong....!');
        }

        Order::where('id', $order_id)->update(['payment_status'=>$status, 'reference' => $reference]);
        $order = Order::where('id', $order_id)->first();

        if($status == 2) {
            return redirect()->to($previous)->with('not_permitted', 'Donation is Rejected....!');
        }
        if($status == 1) {
            $this->sendWhatsappMsgMomoPaymentSuccessDonation($general_setting, $order, $order->grand_total);
            $this->sendWhatsappMsgMomoPaymentSuccessDonationSeller($general_setting, $order);

            return redirect()->route('donation.payment.check.display');
        }
        return redirect()->to($previous)->with('not_permitted', 'Donation is not completed, Something in wrong....!');
    }

    public function placeDonationAfterPaymentDisplay()
    {
        $message = 'Thank you for your worthy donation.';
        return view('frontend.donation_complete', compact('message'));
    }

    public function placeBookingAfterPayment(Request $request)
    {
        if($request->external_reference) {
            $external_reference = explode(',', $request->external_reference);
        }

        $reference = $request->reference;
        $previous = $external_reference[0];
        array_shift($external_reference);

        if($request->status == 'SUCCESSFUL') {
            $status = 4;
        } elseif ($request->status == 'FAILED') {
            $status = 2;
        } elseif ($request->status == 'PENDING') {
            $status = 0;
        } else {
            return redirect()->to($previous)->with('not_permitted', 'Booking is not completed, Something in wrong....!');
        }

        foreach ($external_reference as $order_id) {
            Booking::where('id', $order_id)->update(['payment_status'=>$status, 'reference_no' => $reference]);
            $order = Booking::where('id', $order_id)->first();

            if($status == 4) {
                $this->sendWhatsappMsgForPlacingOrderToBuyerBooking($order);
                $this->sendWhatsappMsgForPlacingOrderToAdminBooking($order);
            }
        }
        if($status == 4) {
            Session::put('order', $order);
            return redirect()->route('booking.payment.check.display');
        } else {
            return redirect()->to($previous)->with('not_permitted', 'Booking is not completed, Payment is Rejected....!');
        }
    }

    public function placeBookingAfterPaymentDisplay()
    {
        $order = Session::get('order');
        if (!$order) {
            return redirect()->route('shop', ['products' => 12])
                ->with('not_permitted', 'Booking session expired. Please contact support if you completed payment.');
        }
        session(['booking_invoice_' . $order->id => true]);
        $user = User::where('id', $order->user_id)->first();
        $message = 'Thank you for your Booking.';
        Session::forget('order');
        return view('frontend.rent_order_complete', compact('message', 'order', 'user'));
    }

    public function placeOrderAfterPayment(Request $request)
    {
        if($request->external_reference) {
            $external_reference = explode(',', $request->external_reference);
        }

        $reference = $request->reference;
        $previous = $external_reference[0];
        array_shift($external_reference);

        if($request->status == 'SUCCESSFUL') {
            $status = 1;
        } elseif ($request->status == 'FAILED') {
            $status = 2;
        } elseif ($request->status == 'PENDING') {
            $status = 0;
        } else {
            return redirect()->to($previous)->with('not_permitted', 'Order is not completed, Something in wrong....!');
        }


        foreach ($external_reference as $order_id) {
            Order::where('id', $order_id)->update(['payment_status'=>$status, 'reference' => $reference]);
            $order = Order::where('id', $order_id)->first();

            if($status == 1) {
                $this->sendWhatsappMsgForPlacingOrderToBuyer($order);
                $this->sendWhatsappMsgForPlacingOrderToSaller($order);
            }
        }
        if($status == 2) {
            return redirect()->to($previous)->with('not_permitted', 'Order is not completed, Payment is Rejected....!');
        }
        if($status == 1) {
            Session::put('order', $order);
            return redirect()->route('order.payment.check.display');
        }

        return redirect()->to($previous)->with('not_permitted', 'Order is not completed, Payment is Rejected....!');
    }

    public function placeOrderAfterPaymentDisplay()
    {
        $order = Session::get('order');
        $user = User::where('id', $order->user_id)->first();
        $message = 'Thank you for your Order.';
        Session::forget('order');
        return view('frontend.order_complete', compact('message', 'order', 'user'));
    }

    private function placeService($user_data, $cart, $user, $amount) {
        $data = $user_data;
        $data['user_id'] = $user->id;
        $data['is_service'] = 1;
        if (isset($data['customer_doc'])) {
            $image = $data['customer_doc'];
            $imageName = date("Ymdhis").$image->getClientOriginalName();
            $image->move('public/images/customer/docs', $imageName);
            $data['customer_doc'] = $imageName;
        }

        if (isset($data['sample_doc'])) {
            $image = $data['sample_doc'];
            $imageName = date("Ymdhis").$image->getClientOriginalName();
            $image->move('public/images/customer/docs', $imageName);
            $data['sample_doc'] = $imageName;
        }

        $data['grand_total'] = $amount;
        if ($data['payment_method'] == 'COD') {
            $data['payment_status'] = 0;
            $data['is_approve'] = 0;
        } else {
            $data['payment_status'] = 2;
            $data['is_approve'] = 1;
            $data['reference'] = '';
        }
        unset($data['product_id']);
        unset($data['donation_amount']);
        $lims_sale_data = Order::create($data);

        $lims_product_data = $cart;

        $product_sale['order_id'] = $lims_sale_data->id;;
        $product_sale['product_id'] = $lims_product_data->id;
        $product_sale['quantity'] = 1;
        $product_sale['price'] =  $amount;
        $product_sale['sub_total'] = $amount;

        OrderProduct::create($product_sale);

        if ($data['payment_method'] == 'COD') {
            $this->sendWhatsappMsgForPlacingServiceToBuyer($lims_sale_data);
            $this->sendWhatsappMsgForPlacingServiceToSaller($lims_sale_data);
        }

        return $lims_sale_data;
    }

    private function placeOrder($user_data, $cart, $user) {
        $data = $user_data;
        $data['user_id'] = $user->id;
        $grand_total = 0;
        $total_qty = 0;
        foreach ($cart as $item) {
            $grand_total += $item['price'] * $item['quantity'];
            $total_qty += $item['quantity'];

        }
        $data['grand_total'] = $grand_total;
        if ($data['payment_method'] != 'COD') {
            $data['payment_status'] = 2;
        } else {
            $data['is_approve'] = 0;
        }
        $lims_sale_data = Order::create($data);

        //collecting male data
        $mail_data['email'] = $data['email'];
        $mail_data['reference_no'] = 'or-'.date('d').$lims_sale_data->id;
        $mail_data['sale_status'] = 'pending';
        $mail_data['payment_status'] = 'pending';
        $mail_data['total_qty'] = $total_qty;
        $mail_data['total_price'] = $grand_total;
        $mail_data['order_tax'] = 0;
        $mail_data['order_tax_rate'] = 'nill';
        $mail_data['order_discount'] = 0;
        $mail_data['shipping_cost'] = 0;
        $mail_data['grand_total'] = $grand_total;
        $mail_data['paid_amount'] = 0;


        foreach ($cart as $i => $item) {
            $id = $item['products_id'];
            $lims_product_data = Product::where('id', $id)->first();
//            if($lims_product_data->type == 'combo' && $data['payment_method'] == 'COD'){
//                $product_list = explode(",", $lims_product_data->product_list);
//                $qty_list = explode(",", $lims_product_data->qty_list);
//                $price_list = explode(",", $lims_product_data->price_list);
//
//                foreach ($product_list as $key=>$child_id) {
//                    $child_data = Product::find($child_id);
//                    $child_warehouse_data = Product_Warehouse::where([
//                        ['product_id', $child_id],
//                        ['warehouse_id', 1 ],
//                    ])->first();
//
//                    $child_data->qty -= $item['quantity'] * $qty_list[$key];
//                    $child_warehouse_data->qty -= $item['quantity'] * $qty_list[$key];
//                    if($child_warehouse_data->qty < 0) {
//                        $child_warehouse_data->qty = 0;
//                    }
//
//                    $child_data->save();
//                    $child_warehouse_data->save();
//                }
//            }

            if($lims_product_data->type == 'standard' && $data['payment_method'] == 'COD') {
                $warehouse_data = Product_Warehouse::where([
                    ['product_id', $lims_product_data->id],
                    ['warehouse_id', 1],
                ])->first();

                if($warehouse_data) {
                    $warehouse_data->qty -= $item['quantity'];
                    $warehouse_data->save();
                }

                $lims_product_data->qty -= $item['quantity'];
                $lims_product_data->save();
            }

            $sale_unit_id = $lims_product_data->sale_unit_id;
            $lims_sale_unit_data = Unit::where('id', $sale_unit_id)->first();
            $mail_data['products'][$i] = $lims_product_data->name;
            if($lims_product_data->type == 'digital')
                $mail_data['file'][$i] = url('/public/product/files').'/'.$lims_product_data->file;
            else
                $mail_data['file'][$i] = '';

            if($sale_unit_id)
                $mail_data['unit'][$i] = $lims_sale_unit_data->unit_code;
            else
                $mail_data['unit'][$i] = '';

            $product_sale['order_id'] = $lims_sale_data->id ;
            $product_sale['product_id'] = $id;
            $product_sale['quantity'] = $mail_data['qty'][$i] = $item['quantity'];
            $product_sale['price'] = $mail_data['price'][$i] = $item['price'];
            $product_sale['sub_total'] = $item['quantity'] * $item['price'];

            OrderProduct::create($product_sale);
        }

        if ($data['payment_method'] == 'COD') {
            $this->sendWhatsappMsgForPlacingOrderToBuyer($lims_sale_data);
            $this->sendWhatsappMsgForPlacingOrderToSaller($lims_sale_data);
        }

        if($mail_data['email']) {
            try {
                Mail::send( 'mail.sale_details', $mail_data, function( $message ) use ($mail_data)
                {
                    $message->to( $mail_data['email'] )->subject( 'Sale Details' );
                });
            }
            catch(\Exception $e){
                $message = 'Please setup your <a href="setting/mail_setting">mail setting</a> to send mail.';
            }
        }
        return $lims_sale_data;
    }

    public function getProductPriceByDuration(Request $request)
    {
        $method = $request->method;
        $number = $request->number;
        $start = $request->start;
        $end = $request->end;
        $product = Product::find($request->id);
        $id = $product->id;
        $price = $product->price;

        if($method == 0) {
            $price = $product->rent_price_per_hour;
        }
        elseif($method == 1) {
            $price = $product->rent_price_per_day;
        }
        elseif($method == 2) {
            $price = $product->rent_price_per_month;
        }
        $cart = Session::get('rent_cart');

        $cart[$id]['price'] = $price;
        $cart[$id]['method'] = $method;
        $cart[$id]['number'] = $number;
        $cart[$id]['start'] = $start;
        $cart[$id]['end'] = $end;
        Session::put('rent_cart', $cart);

        return $price;
    }
}
