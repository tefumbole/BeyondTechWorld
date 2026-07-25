<?php

namespace App\Http\Controllers;

use App\Leader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Intervention\Image\Facades\Image;

class LeaderController extends Controller
{
    protected function authorizeAdmin()
    {
        if (! Auth::check() || ! in_array((int) Auth::user()->role_id, [1, 2], true)) {
            abort(403, 'You are not allowed to manage leadership profiles.');
        }
    }

    public function index()
    {
        $this->authorizeAdmin();

        return view('leaders.index', [
            'leaders' => Leader::ordered()->get(),
            'countryFlags' => Leader::countryFlags(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin();
        $data = $this->validated($request, true);
        $data['photo_url'] = $this->storePhoto($request->file('photo'));
        $data['sort_order'] = ((int) Leader::max('sort_order')) + 1;
        $data['is_published'] = (string) $request->input('is_published', '1') === '1';

        Leader::create($data);

        return redirect()->route('leaders.index')->with('message', 'Leader profile added.');
    }

    public function update(Request $request, $id)
    {
        $this->authorizeAdmin();
        $leader = Leader::findOrFail($id);
        $data = $this->validated($request, false);

        if ($request->hasFile('photo')) {
            $data['photo_url'] = $this->storePhoto($request->file('photo'), $leader->photo_url);
        }

        $data['is_published'] = (string) $request->input('is_published', '0') === '1';

        $leader->update($data);

        return redirect()->route('leaders.index')->with('message', 'Leader profile updated.');
    }

    public function destroy($id)
    {
        $this->authorizeAdmin();
        $leader = Leader::findOrFail($id);
        $this->deleteLocalPhoto($leader->photo_url);
        $leader->delete();

        return redirect()->route('leaders.index')->with('message', 'Leader profile removed.');
    }

    public function reorder(Request $request)
    {
        $this->authorizeAdmin();
        $order = array_map('intval', (array) $request->input('order', []));
        foreach ($order as $pos => $id) {
            Leader::where('id', $id)->update(['sort_order' => $pos]);
        }

        return redirect()->route('leaders.index')->with('message', 'Leadership order saved.');
    }

    protected function validated(Request $request, $photoRequired)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'country' => 'nullable|string|max:100',
            'photo' => ($photoRequired ? 'required' : 'nullable').'|image|mimes:jpeg,jpg,png,webp,gif|max:5120',
        ];

        $data = $request->validate($rules);

        $country = isset($data['country']) ? trim((string) $data['country']) : '';
        if ($country === '' || strcasecmp($country, 'Other') === 0) {
            $country = null;
        }

        return [
            'name' => trim($data['name']),
            'title' => trim($data['title']),
            'description' => isset($data['description']) ? trim($data['description']) : null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'country' => $country,
        ];
    }

    protected function storePhoto($file, $oldPath = null)
    {
        if (! $file || ! $file->isValid()) {
            return $oldPath;
        }

        $dir = public_path('images/leaders');
        if (! is_dir($dir)) {
            File::makeDirectory($dir, 0775, true);
        }

        $name = 'leader_'.time().'_'.bin2hex(random_bytes(4)).'.jpg';
        $full = $dir.'/'.$name;

        try {
            $img = Image::make($file->getRealPath());
            $img->fit(800, 800, function ($constraint) {
                $constraint->upsize();
            });
            $img->encode('jpg', 78)->save($full);
        } catch (\Throwable $e) {
            $file->move($dir, $name);
        }

        $this->deleteLocalPhoto($oldPath);

        return 'images/leaders/'.$name;
    }

    protected function deleteLocalPhoto($path)
    {
        if (! $path || preg_match('#^https?://#i', $path) || strpos($path, 'data:') === 0) {
            return;
        }
        $relative = ltrim(str_replace('public/', '', $path), '/');
        $full = public_path($relative);
        if (is_file($full)) {
            @unlink($full);
        }
    }
}
