<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;

class BeyondController extends Controller
{
    public function home()
    {
        return view('beyond.home');
    }

    public function about()
    {
        return view('beyond.about');
    }

    public function services()
    {
        return view('beyond.services', [
            'services' => $this->servicesList(),
        ]);
    }

    public function projects()
    {
        return view('beyond.projects', [
            'projects' => [
                [
                    'url' => 'https://www.tiktok.com/@tefurolandmbole/video/7495818139272301829',
                    'title' => 'Project Highlight: Professional Installation',
                ],
                [
                    'url' => 'https://www.tiktok.com/@tefurolandmbole/video/7493245944540974341',
                    'title' => 'Advanced Networking Setup',
                ],
                [
                    'url' => 'https://www.tiktok.com/@tefurolandmbole/video/7492891748327361797',
                    'title' => 'Audio-Visual Excellence',
                ],
            ],
        ]);
    }

    public function gallery()
    {
        return view('beyond.gallery', [
            'items' => \App\GalleryItem::published()->ordered()->get(),
        ]);
    }

    public function contact()
    {
        return redirect(url('/about') . '#contact', 301);
    }

    public function events()
    {
        return view('beyond.events', ['events' => []]);
    }

    private function servicesList()
    {
        return [
            ['emoji' => '🤖', 'title' => 'Artificial Intelligence', 'description' => 'Cutting-edge AI solutions for business automation and intelligent decision-making'],
            ['emoji' => '☁️', 'title' => 'Cloud Computing', 'description' => 'Scalable cloud infrastructure and migration services for modern enterprises'],
            ['emoji' => '🔒', 'title' => 'Cyber Security', 'description' => 'Comprehensive security solutions to protect your digital assets and data'],
            ['emoji' => '💼', 'title' => 'General IT Consultancy', 'description' => 'Expert IT guidance and strategic consulting for digital transformation'],
            ['emoji' => '📞', 'title' => 'VoIP', 'description' => 'Reliable voice over IP solutions for seamless business communication'],
            ['emoji' => '🌐', 'title' => 'Network Infrastructure Design', 'description' => 'Robust network architecture and infrastructure planning for optimal performance'],
            ['emoji' => '📹', 'title' => 'CCTV and More', 'description' => 'Advanced surveillance and security systems for comprehensive monitoring'],
        ];
    }
}
