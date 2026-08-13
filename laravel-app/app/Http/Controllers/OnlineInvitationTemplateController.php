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
            'border_color' => ['nullable', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'font_color' => ['nullable', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'font_size' => 'nullable|integer|min:12|max:28',
        ]);

        $background = null;
        if ($request->hasFile('background_image')) {
            $background = $this->storeTemplateBackgroundImage($request->file('background_image'));
        }

        $fontSize = (int) ($request->font_size ?: 16);
        if ($fontSize < 12 || $fontSize > 28) {
            $fontSize = 16;
        }

        OnlineInvitationTemplate::create([
            'name' => $request->name,
            'background' => $background,
            'border_color' => trim((string) $request->border_color) ?: '#c8a75e',
            'font_color' => trim((string) $request->font_color) ?: '#f3e7c1',
            'font_size' => $fontSize,
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
            'border_color' => ['nullable', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'font_color' => ['nullable', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'font_size' => 'nullable|integer|min:12|max:28',
        ]);

        $template = OnlineInvitationTemplate::findOrFail($id);
        $fontSize = (int) ($request->font_size ?: 16);
        if ($fontSize < 12 || $fontSize > 28) {
            $fontSize = 16;
        }
        $updates = [
            'name' => $request->name,
            'border_color' => trim((string) $request->border_color) ?: '#c8a75e',
            'font_color' => trim((string) $request->font_color) ?: '#f3e7c1',
            'font_size' => $fontSize,
        ];

        if ($request->hasFile('background_image')) {
            $this->deleteExistingTemplateBackgroundIfLocal($template->background);
            $updates['background'] = $this->storeTemplateBackgroundImage($request->file('background_image'));
        }

        $template->update($updates);

        return redirect()->route('online_invitation.templates.index')->with('message', 'Template updated successfully');
    }

    /**
     * Save upload under public/images/... and auto-fit to 1024×1536 (cover-crop).
     * Returns CSS background value: url('...').
     */
    private function storeTemplateBackgroundImage($image): string
    {
        if (! $image) {
            throw ValidationException::withMessages([
                'background_image' => 'No image uploaded.',
            ]);
        }

        $imageDir = public_path('images/online_invitation/templates');
        if (! File::exists($imageDir)) {
            File::makeDirectory($imageDir, 0755, true);
        }

        $ext = strtolower((string) $image->getClientOriginalExtension());
        $mime = strtolower((string) ($image->getMimeType() ?? ''));
        $isSvg = ($ext === 'svg' || $mime === 'image/svg+xml');

        if ($isSvg) {
            $imageName = time().'_'.Str::random(12).'.svg';
            $image->move($imageDir, $imageName);
        } else {
            // Always store as JPEG after resize for reliable DomPDF backgrounds.
            $imageName = time().'_'.Str::random(12).'.jpg';
            $destPath = $imageDir.DIRECTORY_SEPARATOR.$imageName;
            $this->resizeTemplateBackgroundToCanvas($image->getRealPath(), $destPath, 1024, 1536);
        }

        $publicUrl = OnlineInvitationUrl::publicAsset('images/online_invitation/templates/'.$imageName);

        return "url('{$publicUrl}')";
    }

    /**
     * Cover-crop source image onto a 1024×1536 canvas using GD.
     */
    private function resizeTemplateBackgroundToCanvas(?string $sourcePath, string $destPath, int $targetW, int $targetH): void
    {
        if (! $sourcePath || ! is_file($sourcePath)) {
            throw ValidationException::withMessages([
                'background_image' => 'Could not read uploaded image.',
            ]);
        }
        if (! function_exists('imagecreatetruecolor')) {
            // Fallback: keep original file if GD missing (rare on production).
            if (! @copy($sourcePath, $destPath)) {
                throw ValidationException::withMessages([
                    'background_image' => 'Could not save uploaded image (GD unavailable).',
                ]);
            }

            return;
        }

        $info = @getimagesize($sourcePath);
        if (! is_array($info) || empty($info[0]) || empty($info[1])) {
            throw ValidationException::withMessages([
                'background_image' => 'Invalid image file.',
            ]);
        }

        $srcW = (int) $info[0];
        $srcH = (int) $info[1];
        $type = (int) ($info[2] ?? 0);

        switch ($type) {
            case IMAGETYPE_JPEG:
                $src = @imagecreatefromjpeg($sourcePath);
                break;
            case IMAGETYPE_PNG:
                $src = @imagecreatefrompng($sourcePath);
                break;
            case IMAGETYPE_GIF:
                $src = @imagecreatefromgif($sourcePath);
                break;
            case IMAGETYPE_WEBP:
                $src = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false;
                break;
            case IMAGETYPE_BMP:
                $src = function_exists('imagecreatefrombmp') ? @imagecreatefrombmp($sourcePath) : false;
                break;
            default:
                $src = false;
        }

        if (! $src) {
            throw ValidationException::withMessages([
                'background_image' => 'Unsupported image format. Use JPG, PNG, GIF, or WEBP.',
            ]);
        }

        // Cover-crop: scale so image fills canvas, then center-crop overflow.
        $scale = max($targetW / max(1, $srcW), $targetH / max(1, $srcH));
        $scaledW = (int) ceil($srcW * $scale);
        $scaledH = (int) ceil($srcH * $scale);
        $dstX = (int) floor(($targetW - $scaledW) / 2);
        $dstY = (int) floor(($targetH - $scaledH) / 2);

        $dst = imagecreatetruecolor($targetW, $targetH);
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefill($dst, 0, 0, $white);
        imagecopyresampled($dst, $src, $dstX, $dstY, 0, 0, $scaledW, $scaledH, $srcW, $srcH);

        $ok = @imagejpeg($dst, $destPath, 90);
        imagedestroy($src);
        imagedestroy($dst);

        if (! $ok || ! is_file($destPath)) {
            throw ValidationException::withMessages([
                'background_image' => 'Could not process uploaded image.',
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
