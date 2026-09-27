<?php

namespace App\Http\Controllers;

use App\EventPackage;
use App\EventPackageComponent;
use App\EventPricingRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class EventPackageAdminController extends Controller
{
    public function index()
    {
        if (! Auth::check()) {
            return redirect('/admin/login');
        }
        $packages = Schema::hasTable('event_packages')
            ? EventPackage::orderBy('category')->orderBy('sort_order')->with('components')->get()
            : collect();
        $rules = Schema::hasTable('event_pricing_rules')
            ? EventPricingRule::orderBy('group')->orderBy('key')->get()
            : collect();

        return view('event_packages.index', compact('packages', 'rules'));
    }

    public function updatePackage(Request $request, $id)
    {
        if (! Auth::check()) {
            return redirect('/admin/login');
        }
        $pkg = EventPackage::findOrFail($id);
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:2000',
            'base_price' => 'required|numeric|min:0',
            'active' => 'nullable|boolean',
            'icon' => 'nullable|string|max:16',
            'sort_order' => 'nullable|integer|min:0',
        ]);
        $pkg->name = $data['name'];
        $pkg->description = isset($data['description']) ? $data['description'] : $pkg->description;
        $pkg->base_price = $data['base_price'];
        $pkg->active = $request->has('active');
        if (isset($data['icon'])) {
            $pkg->icon = $data['icon'];
        }
        if (isset($data['sort_order'])) {
            $pkg->sort_order = $data['sort_order'];
        }
        $pkg->save();

        return redirect()->route('event-packages.index')->with('message', 'Package updated: '.$pkg->name);
    }

    public function updateRule(Request $request, $id)
    {
        if (! Auth::check()) {
            return redirect('/admin/login');
        }
        $rule = EventPricingRule::findOrFail($id);
        $data = $request->validate([
            'label' => 'required|string|max:120',
            'amount' => 'required|numeric|min:0',
            'active' => 'nullable|boolean',
        ]);
        $rule->label = $data['label'];
        $rule->amount = $data['amount'];
        $rule->active = $request->has('active');
        $rule->save();

        return redirect()->route('event-packages.index')->with('message', 'Pricing rule updated: '.$rule->key);
    }

    public function storeComponent(Request $request, $packageId)
    {
        if (! Auth::check()) {
            return redirect('/admin/login');
        }
        $pkg = EventPackage::findOrFail($packageId);
        $data = $request->validate([
            'category_key' => 'required|string|max:64',
            'search_query' => 'nullable|string|max:120',
            'qty' => 'required|integer|min:1',
            'product_id' => 'nullable|integer',
        ]);
        EventPackageComponent::create([
            'event_package_id' => $pkg->id,
            'component_type' => ! empty($data['product_id']) ? 'PRODUCT' : 'CATEGORY',
            'category_key' => $data['category_key'],
            'search_query' => isset($data['search_query']) ? $data['search_query'] : null,
            'qty' => $data['qty'],
            'product_id' => isset($data['product_id']) ? $data['product_id'] : null,
            'required' => true,
        ]);

        return redirect()->route('event-packages.index')->with('message', 'Component added to '.$pkg->name);
    }
}
