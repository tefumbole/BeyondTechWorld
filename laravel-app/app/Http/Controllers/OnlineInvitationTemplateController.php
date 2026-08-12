<?php

namespace App\Http\Controllers;

use App\OnlineInvitationTemplate;
use App\Support\OnlineInvitationUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Models\Role;

class OnlineInvitationTemplateController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $role = Role::find(Auth::user()->role_id);
            $permissions = Role::findByName($role->name)->permissions;
            foreach ($permissions as $permission) {
                $all_permission[] = $permission->name;
            }
            View::share('all_permission', $all_permission ?? []);
            return $next($request);
        });
    }

    private function ensureAccess()
    {
        try {
            $role = Role::find(Auth::user()->role_id);
            return $role && $role->hasPermissionTo('online_invitation_template');
        } catch (PermissionDoesNotExist $e) {
            return false;
        }
    }

    public function index(Request $request)
    {
        if (!$this->ensureAccess()) {
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
        }

        $query = OnlineInvitationTemplate::where('is_active', 1)->orderBy('id', 'desc');

        $search = trim((string) $request->get('q', ''));
        if ($search !== '') {
            $query->where(function ($sub) use ($search) {
                $sub->where('name', 'like', '%' . $search . '%')
                    ->orWhere('background', 'like', '%' . $search . '%');
            });
        }

        $data = $query->paginate(12);
        return view('online_invitation.template.index', compact('data'));
    }

    public function create()
    {
        if (!$this->ensureAccess()) {
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
        }
        return view('online_invitation.template.create');
    }

    public function store(Request $request)
    {
        if (!$this->ensureAccess()) {
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
        }

        $request->name = preg_replace('/\s+/', ' ', $request->name);
        $this->validate($request, [
            'name' => [
                'required',
                'max:255',
                Rule::unique('online_invitation_templates')->where(function ($query) {
                    return $query->where('is_active', 1);
                }),
            ],
            'background_image' => 'nullable|image|mimes:jpg,jpeg,png,gif,webp,bmp,svg|max:5120',
        ]);

        $background = null;
        if ($request->hasFile('background_image')) {
            $this->validateTemplateBackgroundImageDimensions($request->file('background_image'));

            $image = $request->file('background_image');
            $imageName = time() . '_' . Str::random(12) . '.' . $image->getClientOriginalExtension();
            $imageDir = public_path('images/online_invitation/templates');
            if (!File::exists($imageDir)) {
                File::makeDirectory($imageDir, 0755, true);
            }
            $image->move($imageDir, $imageName);
            $publicUrl = asset('images/online_invitation/templates/' . $imageName);
            $publicUrl = OnlineInvitationUrl::ensurePublicInAppUrl($publicUrl);
            $background = "url('{$publicUrl}')";
        }

        OnlineInvitationTemplate::create([
            'name' => $request->name,
            'background' => $background,
            'is_active' => 1,
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('online_invitation.templates.index')->with('message', 'Template created successfully');
    }

    public function edit($id)
    {
        if (!$this->ensureAccess()) {
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
        }
        $data = OnlineInvitationTemplate::findOrFail($id);
        return view('online_invitation.template.edit', compact('data'));
    }

    public function update(Request $request, $id)
    {
        if (!$this->ensureAccess()) {
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
        }

        $request->name = preg_replace('/\s+/', ' ', $request->name);
        $this->validate($request, [
            'name' => [
                'required',
                'max:255',
                Rule::unique('online_invitation_templates')->ignore($id)->where(function ($query) {
                    return $query->where('is_active', 1);
                }),
            ],
            'background_image' => 'nullable|image|mimes:jpg,jpeg,png,gif,webp,bmp,svg|max:5120',
        ]);

        $template = OnlineInvitationTemplate::findOrFail($id);
        $updates = [
            'name' => $request->name,
        ];

        if ($request->hasFile('background_image')) {
            $this->validateTemplateBackgroundImageDimensions($request->file('background_image'));

            $this->deleteExistingTemplateBackgroundIfLocal($template->background);

            $image = $request->file('background_image');
            $imageName = time() . '_' . Str::random(12) . '.' . $image->getClientOriginalExtension();
            $imageDir = public_path('images/online_invitation/templates');
            if (!File::exists($imageDir)) {
                File::makeDirectory($imageDir, 0755, true);
            }
            $image->move($imageDir, $imageName);
            $publicUrl = asset('images/online_invitation/templates/' . $imageName);
            $publicUrl = OnlineInvitationUrl::ensurePublicInAppUrl($publicUrl);
            $updates['background'] = "url('{$publicUrl}')";
        }

        $template->update($updates);

        return redirect()->route('online_invitation.templates.index')->with('message', 'Template updated successfully');
    }

    private function validateTemplateBackgroundImageDimensions($image): void
    {
        if (!$image) {
            return;
        }

        $ext = strtolower((string) $image->getClientOriginalExtension());
        $mime = strtolower((string) ($image->getMimeType() ?? ''));
        if ($ext === 'svg' || $mime === 'image/svg+xml') {
            // SVG does not have reliable pixel dimensions from getimagesize().
            return;
        }

        $path = $image->getRealPath();
        if (!$path) {
            throw ValidationException::withMessages([
                'background_image' => 'Could not read uploaded image.',
            ]);
        }

        $size = @getimagesize($path);
        if (!is_array($size) || empty($size[0]) || empty($size[1])) {
            throw ValidationException::withMessages([
                'background_image' => 'Invalid image file.',
            ]);
        }

        $width = (int) $size[0];
        $height = (int) $size[1];

        // Enforce exact template background dimensions to match the invitation design.
        $requiredWidth = 1024;
        $requiredHeight = 1536;

        if ($height < $width) {
            throw ValidationException::withMessages([
                'background_image' => "Background image must be portrait. Required: {$requiredWidth}x{$requiredHeight}px. Uploaded: {$width}x{$height}px.",
            ]);
        }

        if ($width !== $requiredWidth || $height !== $requiredHeight) {
            throw ValidationException::withMessages([
                'background_image' => "Background image must be exactly {$requiredWidth}x{$requiredHeight}px. Uploaded: {$width}x{$height}px.",
            ]);
        }
    }

    private function deleteExistingTemplateBackgroundIfLocal(?string $background): void
    {
        $background = trim((string) $background);
        if ($background === '') {
            return;
        }

        $ref = $background;
        if (preg_match('/url\\(([^)]+)\\)/i', $background, $matches)) {
            $ref = $matches[1];
        }
        $ref = trim($ref, " \t\n\r\0\x0B'\"");

        if (preg_match('#^https?://#i', $ref)) {
            $path = parse_url($ref, PHP_URL_PATH) ?: '';
            $ref = $path;
        }

        if (preg_match('#^/public/#i', $ref)) {
            $ref = substr($ref, 7) ?: '/';
        }

        if (substr($ref, 0, 35) !== '/images/online_invitation/templates/') {
            return;
        }

        $localPath = public_path(ltrim($ref, '/'));
        if (is_file($localPath)) {
            @unlink($localPath);
        }
    }

    public function destroy($id)
    {
        if (!$this->ensureAccess()) {
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
        }

        $template = OnlineInvitationTemplate::findOrFail($id);
        $template->update(['is_active' => 0]);

        return redirect()->route('online_invitation.templates.index')->with('message', 'Template deleted successfully');
    }
}
