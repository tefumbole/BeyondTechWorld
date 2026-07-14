<?php

namespace App\Http\Controllers;

use App\SiteSetting;
use App\Support\SiteContent;
use App\Support\SiteMenu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SiteContentController extends Controller
{
    /** Restrict Site Content management to Admin / Owner roles. */
    protected function authorizeAdmin()
    {
        if (! Auth::check() || ! in_array((int) Auth::user()->role_id, [1, 2], true)) {
            abort(403, 'You are not allowed to manage site content.');
        }
    }

    public function index(Request $request)
    {
        $this->authorizeAdmin();

        $tab = $request->get('tab', 'landing-menu');

        $data = [
            'tab'          => $tab,
            'landing'      => SiteMenu::landingItems(),
            'side'         => SiteMenu::sideItems(),
            'landingOrder' => SiteMenu::landingOrder(),
            'sideOrder'    => SiteMenu::sideOrder(),
            'schema'       => SiteContent::schema(),
            'pageSchema'   => SiteContent::pageSchema($tab),
        ];

        return view('site_content.index', $data);
    }

    public function saveLandingMenu(Request $request)
    {
        $this->authorizeAdmin();
        $this->saveOrder($request, 'landing_menu_order', SiteMenu::landingItems());

        return redirect('/admin/site-content?tab=landing-menu')->with('message', 'Landing menu order saved.');
    }

    public function saveSideMenu(Request $request)
    {
        $this->authorizeAdmin();
        $this->saveOrder($request, 'side_menu_order', SiteMenu::sideItems());

        return redirect('/admin/site-content?tab=side-menu')->with('message', 'Side menu order saved.');
    }

    public function saveContent(Request $request, $page)
    {
        $this->authorizeAdmin();

        $pageSchema = SiteContent::pageSchema($page);
        if (! $pageSchema) {
            abort(404);
        }

        $content = (array) $request->input('content', []);

        foreach ($pageSchema['fields'] as $key => [$type, $label, $default]) {
            if ($type === 'image') {
                $file = $request->file('image.' . $key);
                if ($file && $file->isValid()) {
                    $dir = public_path('images/site');
                    if (! is_dir($dir)) {
                        @mkdir($dir, 0775, true);
                    }
                    $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
                    $name = 'site_' . $page . '_' . $key . '_' . time() . '.' . $ext;
                    $file->move($dir, $name);
                    SiteContent::put($page . '.' . $key, 'images/site/' . $name);
                }
                // no file uploaded -> keep existing value
                continue;
            }

            if (array_key_exists($key, $content)) {
                SiteContent::put($page . '.' . $key, $content[$key]);
            }
        }

        return redirect('/admin/site-content?tab=' . $page)->with('message', ucfirst($page) . ' content saved.');
    }

    private function saveOrder(Request $request, $settingKey, array $items)
    {
        $order = (array) $request->input('order', []);
        $valid = array_keys($items);
        $order = array_values(array_filter($order, function ($k) use ($valid) {
            return in_array($k, $valid, true);
        }));
        SiteSetting::setValue($settingKey, $order);
    }
}
