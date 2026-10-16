<?php

namespace App\Http\Controllers;

use App\Mix\MixCatalog;
use App\Mix\MixMasteringPreset;
use App\Mix\MixPreset;
use App\Mix\MixPresetService;
use App\Mix\MixSetting;
use App\Mix\MixShow;
use App\Mix\MixSupport;
use App\Mix\MixTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class MixPublicController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (Schema::hasTable('mix_categories')) {
                app(MixCatalog::class)->ensure();
            }

            return $next($request);
        });
    }

    public function home(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $category = trim((string) $request->get('category', ''));
        $presets = $this->publishedPresets($q, $category);

        return view('mix.public.home', [
            'q' => $q,
            'category' => $category,
            'presets' => $presets,
            'templates' => MixTemplate::with('console')->where('published', true)->orderBy('name')->get(),
            'shows' => MixShow::with('console')->where('published', true)->orderBy('name')->get(),
            'mastering' => MixMasteringPreset::where('published', true)->orderBy('name')->get(),
            'intro' => MixSetting::getValue('public_intro', 'Reference settings for live sound and studio work. Nothing on this page is sent to a mixer.'),
        ]);
    }

    public function preset($slug)
    {
        $preset = $this->findPreset($slug);
        $version = $preset->currentVersion;
        $version->load(['bands', 'dynamics', 'effects', 'routings']);

        return view('mix.public.preset', [
            'preset' => $preset,
            'version' => $version,
            'graph' => app(MixPresetService::class)->graphPayload($version),
            'copy' => app(MixPresetService::class)->copyText($preset, $version),
        ]);
    }

    public function presetPdf($slug)
    {
        $preset = $this->findPreset($slug);
        $version = $preset->currentVersion;
        $version->load(['bands', 'dynamics', 'effects', 'routings']);
        $pdf = \PDF::loadView('mix.pdf.preset', compact('preset', 'version'))->setPaper('a4', 'portrait');

        return $pdf->download(MixSupport::slug($preset->name).'.pdf');
    }

    public function compare(Request $request)
    {
        $left = $request->filled('a') ? $this->findPreset($request->get('a')) : null;
        $right = $request->filled('b') ? $this->findPreset($request->get('b')) : null;
        if ($left && $left->currentVersion) {
            $left->currentVersion->load(['bands', 'dynamics', 'effects']);
        }
        if ($right && $right->currentVersion) {
            $right->currentVersion->load(['bands', 'dynamics', 'effects']);
        }

        return view('mix.public.compare', [
            'left' => $left,
            'right' => $right,
            'choices' => MixPreset::where('published', true)->orderBy('name')->get(),
        ]);
    }

    public function template($slug)
    {
        $template = MixTemplate::with('console')->where('slug', $slug)->where('published', true)->firstOrFail();
        $version = $template->currentVersion;
        if (! $version) {
            abort(404);
        }
        $version->load(['channels.presetVersion.preset', 'channels.bus', 'channels.instrument']);

        return view('mix.public.template', compact('template', 'version'));
    }

    public function templatePdf($slug)
    {
        $template = MixTemplate::with('console')->where('slug', $slug)->where('published', true)->firstOrFail();
        $version = $template->currentVersion;
        if (! $version) {
            abort(404);
        }
        $version->load(['channels.presetVersion.preset', 'channels.bus', 'channels.instrument']);
        $pdf = \PDF::loadView('mix.pdf.sheet', [
            'template' => $template,
            'version' => $version,
            'show' => null,
        ])->setPaper('a4', 'portrait');

        return $pdf->download(MixSupport::slug($template->name).'.pdf');
    }

    public function show($slug)
    {
        $show = MixShow::with('console', 'templateVersion.template')->where('slug', $slug)->where('published', true)->firstOrFail();
        $version = $show->templateVersion;
        if ($version) {
            $version->load(['channels.presetVersion.preset', 'channels.bus']);
        }

        return view('mix.public.show', compact('show', 'version'));
    }

    public function mastering($slug)
    {
        $row = MixMasteringPreset::where('slug', $slug)->where('published', true)->firstOrFail();

        return view('mix.public.mastering', compact('row'));
    }

    protected function findPreset($slug)
    {
        $preset = MixPreset::with(['instrument.category', 'console', 'manufacturer', 'currentVersion'])
            ->where('slug', $slug)
            ->where('published', true)
            ->firstOrFail();
        if (! $preset->currentVersion) {
            abort(404);
        }

        return $preset;
    }

    protected function publishedPresets($q, $category)
    {
        return MixPreset::with(['instrument.category', 'console'])
            ->where('published', true)
            ->where(function ($query) use ($q) {
                if ($q !== '') {
                    $query->where('name', 'like', '%'.$q.'%')
                        ->orWhere('notes', 'like', '%'.$q.'%')
                        ->orWhereHas('instrument', function ($inner) use ($q) {
                            $inner->where('name', 'like', '%'.$q.'%');
                        });
                }
            })
            ->when($category !== '', function ($query) use ($category) {
                $query->whereHas('instrument.category', function ($inner) use ($category) {
                    $inner->where('slug', $category);
                });
            })
            ->orderBy('name')
            ->get();
    }
}
