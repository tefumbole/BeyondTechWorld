<?php

namespace App\Http\Controllers;

use App\Mix\MixAuditLog;
use App\Mix\MixBus;
use App\Mix\MixCatalog;
use App\Mix\MixConsole;
use App\Mix\MixImportFile;
use App\Mix\MixImportReview;
use App\Mix\MixImportService;
use App\Mix\MixInstrument;
use App\Mix\MixManufacturer;
use App\Mix\MixMasteringPreset;
use App\Mix\MixPreset;
use App\Mix\MixPresetService;
use App\Mix\MixPresetVersion;
use App\Mix\MixPresonusReport;
use App\Mix\MixSetting;
use App\Mix\MixShow;
use App\Mix\MixSupport;
use App\Mix\MixTemplate;
use App\Mix\MixTemplateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Spatie\Permission\Models\Role;

class MixAdminController extends Controller
{
    protected $permissions = [];

    public function __construct()
    {
        parent::__construct();
        $this->middleware(function ($request, $next) {
            if (Auth::check()) {
                $role = Role::find(Auth::user()->role_id);
                if ($role) {
                    foreach (Role::findByName($role->name)->permissions as $permission) {
                        $this->permissions[] = $permission->name;
                    }
                }
            }
            View::share('all_permission', $this->permissions);
            if (! $this->allows(['mix.view', 'mix.manage', 'mix.publish'])) {
                return redirect('dashboard')->with('not_permitted', 'Sorry! You are not allowed to access Mix & Master');
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('mix_categories')) {
                app(MixCatalog::class)->ensure();
            }

            return $next($request);
        });
    }

    public function dashboard()
    {
        return view('mix.admin.dashboard', [
            'presets' => MixPreset::count(),
            'published' => MixPreset::where('published', true)->count(),
            'templates' => MixTemplate::count(),
            'shows' => MixShow::count(),
            'imports' => MixImportFile::where('status', 'review')->count(),
        ]);
    }

    public function presets(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $rows = MixPreset::with(['instrument.category', 'console', 'currentVersion'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where('name', 'like', '%'.$q.'%');
            })
            ->orderBy('name')
            ->get();

        return view('mix.admin.presets', [
            'rows' => $rows,
            'q' => $q,
            'instruments' => MixInstrument::with('category')->orderBy('name')->get(),
        ]);
    }

    public function createPreset()
    {
        return $this->presetForm(new MixPreset());
    }

    public function editPreset($id)
    {
        return $this->presetForm(MixPreset::with('versions')->findOrFail($id));
    }

    public function storePreset(Request $request)
    {
        if ($deny = $this->denyManage()) {
            return $deny;
        }
        $preset = app(MixPresetService::class)->save(new MixPreset(), $this->presetInput($request), Auth::id());

        return redirect()->route('mix.presets.edit', $preset->id)->with('mix_status', 'Preset saved as version 1. It stays private until you publish it.');
    }

    public function updatePreset(Request $request, $id)
    {
        if ($deny = $this->denyManage()) {
            return $deny;
        }
        $preset = MixPreset::findOrFail($id);
        app(MixPresetService::class)->save($preset, $this->presetInput($request), Auth::id());

        return redirect()->route('mix.presets.edit', $preset->id)->with('mix_status', 'A new preset version was saved. Older show templates still use the previous version.');
    }

    public function publishPreset($id)
    {
        if (! $this->allows(['mix.publish'])) {
            return redirect()->back()->with('not_permitted', 'You need the publish permission.');
        }
        $preset = MixPreset::findOrFail($id);
        $preset->published = ! $preset->published;
        $preset->save();
        MixAuditLog::write($preset->published ? 'preset.publish' : 'preset.unpublish', $preset);

        return redirect()->back()->with('mix_status', $preset->published ? 'Preset is public.' : 'Preset is private.');
    }

    public function comparePreset(Request $request, $id)
    {
        $preset = MixPreset::with('versions')->findOrFail($id);
        $left = MixPresetVersion::with(['bands', 'dynamics', 'effects', 'routings'])->find($request->get('left')) ?: $preset->versions->first();
        $right = MixPresetVersion::with(['bands', 'dynamics', 'effects', 'routings'])->find($request->get('right')) ?: $preset->versions->last();

        return view('mix.admin.compare', compact('preset', 'left', 'right'));
    }

    public function templates()
    {
        return view('mix.admin.templates', [
            'rows' => MixTemplate::with('console', 'currentVersion')->orderBy('name')->get(),
        ]);
    }

    public function editTemplate($id)
    {
        $template = MixTemplate::with('versions')->findOrFail($id);
        $version = $template->currentVersion;
        if ($version) {
            $version->load(['channels.instrument', 'channels.presetVersion.preset', 'channels.bus']);
        }

        return view('mix.admin.template_form', [
            'template' => $template,
            'version' => $version,
            'consoles' => MixConsole::orderBy('name')->get(),
            'instruments' => MixInstrument::orderBy('name')->get(),
            'presets' => MixPreset::with('currentVersion')->orderBy('name')->get(),
            'buses' => MixBus::orderBy('name')->get(),
        ]);
    }

    public function updateTemplate(Request $request, $id)
    {
        if ($deny = $this->denyManage()) {
            return $deny;
        }
        $template = MixTemplate::findOrFail($id);
        $template->name = trim($request->input('name'));
        $template->console_id = $request->input('console_id') ?: null;
        $template->notes = MixSupport::text($request->input('notes'));
        $count = max(1, min(128, (int) $request->input('channel_count', 32)));
        $template->channel_count = $count;
        $template->save();
        $channels = [];
        $posted = $request->input('channels', []);
        foreach ($posted as $channel) {
            if (! is_array($channel)) {
                continue;
            }
            $channels[] = $channel;
        }
        usort($channels, function ($a, $b) {
            return (int) $a['position'] - (int) $b['position'];
        });
        $channels = array_slice($channels, 0, $count);
        while (count($channels) < $count) {
            $position = count($channels) + 1;
            $channels[] = [
                'position' => $position,
                'name' => 'Channel '.$position,
                'instrument_id' => null,
                'preset_version_id' => null,
                'bus_id' => null,
                'stereo_link' => null,
            ];
        }
        app(MixTemplateService::class)->writeVersion($template, $channels, $request->input('version_notes'));

        return redirect()->route('mix.templates.edit', $template->id)->with('mix_status', 'Template version saved. Previous versions are unchanged.');
    }

    public function duplicateTemplate($id)
    {
        if ($deny = $this->denyManage()) {
            return $deny;
        }
        $copy = app(MixTemplateService::class)->duplicate(MixTemplate::findOrFail($id));

        return redirect()->route('mix.templates.edit', $copy->id)->with('mix_status', 'Template duplicated.');
    }

    public function upgradeTemplate($id)
    {
        if ($deny = $this->denyManage()) {
            return $deny;
        }
        app(MixTemplateService::class)->upgrade(MixTemplate::findOrFail($id));

        return redirect()->back()->with('mix_status', 'A new template version now points at the current preset versions.');
    }

    public function publishTemplate($id)
    {
        if (! $this->allows(['mix.publish'])) {
            return redirect()->back()->with('not_permitted', 'You need the publish permission.');
        }
        $template = MixTemplate::findOrFail($id);
        $template->published = ! $template->published;
        $template->save();

        return redirect()->back()->with('mix_status', $template->published ? 'Template is public.' : 'Template is private.');
    }

    public function printTemplate($id)
    {
        $template = MixTemplate::with('console')->findOrFail($id);
        $version = $this->templateVersionOrFail($template);
        $version->load(['channels.presetVersion.preset', 'channels.bus', 'channels.instrument']);

        return view('mix.admin.print_sheet', compact('template', 'version'));
    }

    public function pdfTemplate($id)
    {
        $template = MixTemplate::with('console')->findOrFail($id);
        $version = $this->templateVersionOrFail($template);
        $version->load(['channels.presetVersion.preset', 'channels.bus', 'channels.instrument']);
        $pdf = \PDF::loadView('mix.pdf.sheet', compact('template', 'version'))->setPaper('a4', 'portrait');

        return $pdf->download(MixSupport::slug($template->name).'.pdf');
    }

    public function shows()
    {
        return view('mix.admin.shows', [
            'rows' => MixShow::with('console', 'templateVersion.template')->orderBy('name')->get(),
            'consoles' => MixConsole::orderBy('name')->get(),
            'versions' => \App\Mix\MixTemplateVersion::with('template')->orderByDesc('id')->get(),
            'types' => ['Sunday Worship', 'Live Gospel Concert', 'Praise & Worship', 'Church Conference', 'Wedding Reception', 'Corporate Event', 'Full Live Band', 'Playback Performance'],
        ]);
    }

    public function storeShow(Request $request)
    {
        if ($deny = $this->denyManage()) {
            return $deny;
        }
        $show = new MixShow();
        $this->fillShow($show, $request);
        $show->save();
        MixAuditLog::write('show.save', $show);

        return redirect()->route('mix.shows')->with('mix_status', 'Show template saved. Loading it displays the sheet. It is not sent to the mixer.');
    }

    public function updateShow(Request $request, $id)
    {
        if ($deny = $this->denyManage()) {
            return $deny;
        }
        $show = MixShow::findOrFail($id);
        $this->fillShow($show, $request);
        $show->save();

        return redirect()->route('mix.shows')->with('mix_status', 'Show template updated.');
    }

    public function buses()
    {
        return view('mix.admin.buses', [
            'rows' => MixBus::with('processing.preset')->orderBy('name')->get(),
            'versions' => MixPresetVersion::with('preset')->orderByDesc('id')->limit(200)->get(),
            'types' => ['subgroup', 'aux', 'matrix', 'dca', 'output', 'fx'],
        ]);
    }

    public function storeBus(Request $request)
    {
        if ($deny = $this->denyManage()) {
            return $deny;
        }
        $bus = new MixBus();
        $this->fillBus($bus, $request);
        $bus->save();

        return redirect()->route('mix.buses')->with('mix_status', 'Bus saved.');
    }

    public function updateBus(Request $request, $id)
    {
        if ($deny = $this->denyManage()) {
            return $deny;
        }
        $bus = MixBus::findOrFail($id);
        $this->fillBus($bus, $request);
        $bus->save();

        return redirect()->route('mix.buses')->with('mix_status', 'Bus updated.');
    }

    public function routing()
    {
        $template = MixTemplate::where('published', true)->orderByDesc('id')->first() ?: MixTemplate::orderBy('id')->first();
        $version = $template ? $template->currentVersion : null;
        if ($version) {
            $version->load(['channels.bus', 'channels.presetVersion.preset']);
        }

        return view('mix.admin.routing', [
            'template' => $template,
            'version' => $version,
            'buses' => MixBus::orderBy('name')->get(),
        ]);
    }

    public function effects()
    {
        $presets = MixPreset::with(['currentVersion.effects', 'instrument'])->orderBy('name')->get();

        return view('mix.admin.effects', compact('presets'));
    }

    public function mastering()
    {
        return view('mix.admin.mastering', [
            'rows' => MixMasteringPreset::orderBy('name')->get(),
            'processors' => ['EQ', 'Compression', 'Multiband compression', 'Saturation', 'Stereo processing', 'Limiter', 'Loudness measurement'],
        ]);
    }

    public function storeMastering(Request $request)
    {
        if ($deny = $this->denyManage()) {
            return $deny;
        }
        $row = new MixMasteringPreset();
        $this->fillMastering($row, $request);
        $row->save();

        return redirect()->route('mix.mastering')->with('mix_status', 'Mastering preset saved as a reference chain. It does not master audio.');
    }

    public function updateMastering(Request $request, $id)
    {
        if ($deny = $this->denyManage()) {
            return $deny;
        }
        $row = MixMasteringPreset::findOrFail($id);
        $this->fillMastering($row, $request);
        $row->save();

        return redirect()->route('mix.mastering')->with('mix_status', 'Mastering preset updated.');
    }

    public function presonus()
    {
        return view('mix.admin.presonus', [
            'report' => MixPresonusReport::findings(),
            'rows' => MixPreset::where('collection', 'presonus')->orderBy('name')->get(),
        ]);
    }

    public function imports()
    {
        app(MixImportService::class)->syncSources();
        $files = MixImportFile::with('reviews')->orderBy('original_name')->get();

        return view('mix.admin.imports', compact('files'));
    }

    public function saveImport(Request $request, $id)
    {
        if ($deny = $this->denyManage()) {
            return $deny;
        }
        $file = MixImportFile::findOrFail($id);
        $posted = $request->input('rows', []);
        foreach ($file->reviews as $review) {
            $row = isset($posted[$review->id]) ? $posted[$review->id] : [];
            $review->confirmed = ! empty($row['confirmed']);
            $review->confirmed_value = MixSupport::text(isset($row['confirmed_value']) ? $row['confirmed_value'] : null);
            $review->save();
        }
        if ($request->input('commit') === '1') {
            $created = app(MixImportService::class)->commitFile($file, Auth::id());

            return redirect()->route('mix.imports')->with('mix_status', count($created).' unpublished presets were created from confirmed rows.');
        }

        return redirect()->route('mix.imports')->with('mix_status', 'Review saved. Nothing was written to a preset until you create presets.');
    }

    public function collections()
    {
        return view('mix.admin.collections', [
            'beyond' => MixPreset::where('collection', 'beyond')->orderBy('name')->get(),
            'presonus' => MixPreset::where('collection', 'presonus')->orderBy('name')->get(),
        ]);
    }

    public function settings()
    {
        return view('mix.admin.settings', [
            'consoles' => MixConsole::orderBy('name')->get(),
            'report' => MixPresonusReport::findings(),
        ]);
    }

    public function saveSettings(Request $request)
    {
        if ($deny = $this->denyManage()) {
            return $deny;
        }
        MixSetting::putValue('default_console_id', (string) $request->input('default_console_id'));
        MixSetting::putValue('public_intro', (string) $request->input('public_intro'));

        return redirect()->route('mix.settings')->with('mix_status', 'Settings saved.');
    }

    protected function presetForm(MixPreset $preset)
    {
        $version = $preset->exists ? $preset->currentVersion : null;
        if ($version) {
            $version->load(['bands', 'dynamics', 'effects', 'routings']);
        }

        return view('mix.admin.preset_form', [
            'preset' => $preset,
            'version' => $version,
            'instruments' => MixInstrument::with('category')->orderBy('name')->get(),
            'consoles' => MixConsole::orderBy('name')->get(),
            'manufacturers' => MixManufacturer::orderBy('name')->get(),
        ]);
    }

    protected function presetInput(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:160',
            'instrument_id' => 'nullable|integer',
            'collection' => 'required|in:beyond,presonus',
        ]);

        $input = $request->all();
        $custom = trim((string) $request->input('new_instrument'));
        if ($custom !== '') {
            $slug = MixSupport::slug($custom);
            $instrument = MixInstrument::where('slug', $slug)->first();
            if (! $instrument) {
                $category = \App\Mix\MixCategory::where('slug', 'other')->first();
                $instrument = new MixInstrument();
                $instrument->category_id = $category ? $category->id : MixInstrument::orderBy('id')->value('category_id');
                $instrument->name = $custom;
                $instrument->slug = MixSupport::uniqueSlug(MixInstrument::class, $custom);
                $instrument->sort = 200;
                $instrument->save();
            }
            $input['instrument_id'] = $instrument->id;
        }

        return $input;
    }

    protected function templateVersionOrFail(MixTemplate $template)
    {
        $version = $template->currentVersion;
        if (! $version) {
            abort(404);
        }

        return $version;
    }

    protected function fillShow(MixShow $show, Request $request)
    {
        $request->validate(['name' => 'required|string|max:160']);
        $show->name = trim($request->input('name'));
        if (! $show->exists) {
            $show->slug = MixSupport::uniqueSlug(MixShow::class, $show->name);
        }
        $show->event_type = MixSupport::text($request->input('event_type'));
        $show->console_id = $request->input('console_id') ?: null;
        $show->template_version_id = $request->input('template_version_id') ?: null;
        $show->version_label = MixSupport::text($request->input('version_label'));
        $show->main_notes = MixSupport::text($request->input('main_notes'));
        $show->monitor_notes = MixSupport::text($request->input('monitor_notes'));
        $show->subwoofer_notes = MixSupport::text($request->input('subwoofer_notes'));
        $show->bus_notes = MixSupport::text($request->input('bus_notes'));
        $show->notes = MixSupport::text($request->input('notes'));
        $show->published = $request->input('published') === '1' && $this->allows(['mix.publish']);
    }

    protected function fillBus(MixBus $bus, Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:120',
            'bus_type' => 'required|in:subgroup,aux,matrix,dca,output,fx',
        ]);
        $bus->name = trim($request->input('name'));
        if (! $bus->exists) {
            $bus->slug = MixSupport::uniqueSlug(MixBus::class, $bus->name);
        }
        $bus->bus_type = $request->input('bus_type');
        $bus->preset_version_id = $request->input('preset_version_id') ?: null;
        $bus->limiter_threshold_db = MixSupport::num($request->input('limiter_threshold_db'));
        $bus->limiter_release_ms = MixSupport::num($request->input('limiter_release_ms'));
        $bus->notes = MixSupport::text($request->input('notes'));
    }

    protected function fillMastering(MixMasteringPreset $row, Request $request)
    {
        $request->validate(['name' => 'required|string|max:160']);
        $row->name = trim($request->input('name'));
        if (! $row->exists) {
            $row->slug = MixSupport::uniqueSlug(MixMasteringPreset::class, $row->name);
        }
        $chain = array_values(array_filter((array) $request->input('chain', [])));
        $row->chain_json = json_encode($chain);
        $row->target_lufs = MixSupport::num($request->input('target_lufs'));
        $row->true_peak_db = MixSupport::num($request->input('true_peak_db'));
        $row->output_format = MixSupport::text($request->input('output_format'));
        $row->sample_rate = MixSupport::num($request->input('sample_rate'));
        $row->bit_depth = MixSupport::num($request->input('bit_depth'));
        $row->notes = MixSupport::text($request->input('notes'));
        $row->published = $request->input('published') === '1' && $this->allows(['mix.publish']);
    }

    protected function allows(array $names)
    {
        foreach ($names as $name) {
            if (in_array($name, $this->permissions, true)) {
                return true;
            }
        }

        return false;
    }

    protected function denyManage()
    {
        if ($this->allows(['mix.manage'])) {
            return null;
        }

        return redirect()->back()->with('not_permitted', 'You can view Mix & Master, but not change it.');
    }
}
