<?php

namespace App\Http\Controllers;

use App\FuneralEulogy;
use App\FuneralPledge;
use App\Services\FuneralPledgeService;
use App\Support\PaNgwayuBiography;
use Illuminate\Http\Request;

class AdminFuneralPledgeController extends Controller
{
    public function index(FuneralPledgeService $service)
    {
        $data = $service->pageData();
        $pledges = FuneralPledge::with('item')
            ->orderByDesc('id')
            ->limit(300)
            ->get();
        $eulogies = FuneralEulogy::orderByDesc('id')->limit(200)->get();

        return view('beyond.memorial.admin', [
            'data' => $data,
            'pledges' => $pledges,
            'eulogies' => $eulogies,
            'publicUrl' => route('funeral.pangwayu'),
            'rememberUrl' => route('funeral.pangwayu.remember'),
            'biographyUrl' => route('funeral.pangwayu.biography'),
        ]);
    }

    public function biography()
    {
        return view('beyond.memorial.biography-admin', [
            'bio' => PaNgwayuBiography::data(),
            'publicUrl' => route('funeral.pangwayu.biography'),
        ]);
    }

    public function saveBiography(Request $request)
    {
        $bio = PaNgwayuBiography::data();
        $bio['hero']['quote'] = trim((string) $request->input('hero_quote', $bio['hero']['quote']));
        $bio['hero']['line'] = trim((string) $request->input('hero_line', $bio['hero']['line']));
        $bio['intro']['title'] = trim((string) $request->input('intro_title', $bio['intro']['title']));
        $introParas = $this->splitParagraphs($request->input('intro_paragraphs'));
        if ($introParas) {
            $bio['intro']['paragraphs'] = $introParas;
        }
        $bio['intro']['quote'] = trim((string) $request->input('intro_quote', $bio['intro']['quote']));

        foreach ($bio['sections'] as $i => $section) {
            $bio['sections'][$i]['title'] = trim((string) $request->input('section.'.$i.'.title', $section['title']));
            $bio['sections'][$i]['subtitle'] = trim((string) $request->input('section.'.$i.'.subtitle', $section['subtitle'] ?? ''));
            $paras = $this->splitParagraphs($request->input('section.'.$i.'.paragraphs'));
            if ($paras) {
                $bio['sections'][$i]['paragraphs'] = $paras;
            }
            $bio['sections'][$i]['quote'] = trim((string) $request->input('section.'.$i.'.quote', $section['quote'] ?? ''));
            $bio['sections'][$i]['quote_attr'] = trim((string) $request->input('section.'.$i.'.quote_attr', $section['quote_attr'] ?? ''));
            $bio['sections'][$i]['placeholder_note'] = trim((string) $request->input('section.'.$i.'.placeholder_note', $section['placeholder_note'] ?? ''));
        }

        foreach ($bio['timeline']['events'] as $i => $event) {
            $bio['timeline']['events'][$i]['year'] = trim((string) $request->input('timeline.'.$i.'.year', $event['year']));
            $bio['timeline']['events'][$i]['title'] = trim((string) $request->input('timeline.'.$i.'.title', $event['title']));
            $bio['timeline']['events'][$i]['text'] = trim((string) $request->input('timeline.'.$i.'.text', $event['text']));
        }

        foreach ($bio['gallery']['items'] as $i => $item) {
            $bio['gallery']['items'][$i]['caption'] = trim((string) $request->input('gallery.'.$i.'.caption', $item['caption']));
            $bio['gallery']['items'][$i]['year'] = trim((string) $request->input('gallery.'.$i.'.year', $item['year']));
        }

        $bio['legacy_close']['paragraphs'] = $this->splitParagraphs($request->input('legacy_paragraphs')) ?: $bio['legacy_close']['paragraphs'];
        $bio['closing']['lines'] = array_values(array_filter(array_map('trim', explode("\n", (string) $request->input('closing_lines', implode("\n", $bio['closing']['lines']))))));

        PaNgwayuBiography::save($bio);

        return redirect()->route('funeral.biography.admin')->with('ok', 'Biography saved.');
    }

    protected function splitParagraphs($text)
    {
        $parts = preg_split("/\n\s*\n/", trim((string) $text));

        return array_values(array_filter(array_map('trim', $parts), function ($p) {
            return $p !== '';
        }));
    }
}
