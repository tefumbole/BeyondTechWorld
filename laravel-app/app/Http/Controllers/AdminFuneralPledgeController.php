<?php

namespace App\Http\Controllers;

use App\FuneralPledge;
use App\Services\FuneralPledgeService;

class AdminFuneralPledgeController extends Controller
{
    public function index(FuneralPledgeService $service)
    {
        $data = $service->pageData();
        $pledges = FuneralPledge::with('item')
            ->orderByDesc('id')
            ->limit(300)
            ->get();

        return view('beyond.memorial.admin', [
            'data' => $data,
            'pledges' => $pledges,
            'publicUrl' => route('funeral.pangwayu'),
        ]);
    }
}
