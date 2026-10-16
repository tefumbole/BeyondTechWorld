<?php

namespace App\Mix;

class MixCatalog
{
    public function ensure()
    {
        if (! MixCategory::query()->exists()) {
            $this->seedCatalog();
        }
        if (! MixTemplate::query()->exists()) {
            $console = MixConsole::orderBy('id')->first();
            $template = new MixTemplate();
            $template->console_id = $console ? $console->id : null;
            $template->name = 'Beyond Live Gospel — StudioLive 32SX';
            $template->slug = MixSupport::uniqueSlug(MixTemplate::class, $template->name);
            $template->channel_count = 32;
            $template->notes = 'Starter layout. Assign a saved preset version to each channel. Loading this template shows and exports the sheet. It does not write to the mixer.';
            $template->published = false;
            $template->save();
            app(MixTemplateService::class)->writeVersion($template, $this->starterChannels(), 'Original layout');
        }
    }

    protected function seedCatalog()
    {
        $groups = [
            'Drums' => ['Kick In', 'Kick Out', 'Snare Top', 'Snare Bottom', 'Hi-Hat', 'Tom 1', 'Tom 2', 'Tom 3', 'Tom 4', 'Floor Tom', 'Overheads', 'Drum Bus'],
            'Keys' => ['Piano', 'Keyboard', 'Organ', 'Synthesizer', 'Pads', 'Keys Bus'],
            'Vocals' => ['Lead Male', 'Lead Female', 'Backup Vocals', 'Choir', 'Pastor Microphone', 'Vocal Bus'],
            'Guitars' => ['Bass Guitar', 'Electric Guitar', 'Acoustic Guitar', 'Lead Guitar', 'Rhythm Guitar', 'Guitar Bus'],
            'Other' => ['Saxophone', 'Trumpet', 'Percussion', 'Traditional African Instruments'],
            'Buses' => ['Bass Bus', 'Instrument Bus', 'Main Mix Bus', 'Subwoofer Bus'],
        ];
        $sort = 0;
        foreach ($groups as $categoryName => $instruments) {
            $category = new MixCategory();
            $category->name = $categoryName;
            $category->slug = MixSupport::slug($categoryName);
            $category->sort = $sort++;
            $category->save();
            $i = 0;
            foreach ($instruments as $name) {
                $instrument = new MixInstrument();
                $instrument->category_id = $category->id;
                $instrument->name = $name;
                $instrument->slug = MixSupport::slug($name);
                $instrument->sort = $i++;
                $instrument->save();
            }
        }

        $beyond = new MixManufacturer();
        $beyond->name = 'Beyond Tech World';
        $beyond->slug = 'beyond';
        $beyond->save();
        $presonus = new MixManufacturer();
        $presonus->name = 'PreSonus';
        $presonus->slug = 'presonus';
        $presonus->save();

        $console = new MixConsole();
        $console->manufacturer_id = $presonus->id;
        $console->name = 'PreSonus StudioLive 32SX';
        $console->model = 'StudioLive 32SX';
        $console->channel_count = 32;
        $console->notes = 'Primary console. The channel count on a template is a setup size. It is not the mixer processing or routing limit.';
        $console->save();

        $buses = [
            ['Drum Bus', 'subgroup'],
            ['Bass Bus', 'subgroup'],
            ['Vocal Bus', 'subgroup'],
            ['Keys Bus', 'subgroup'],
            ['Instrument Bus', 'subgroup'],
            ['Main Mix Bus', 'output'],
            ['Subwoofer Bus', 'matrix'],
        ];
        foreach ($buses as $row) {
            $bus = new MixBus();
            $bus->name = $row[0];
            $bus->slug = MixSupport::slug($row[0]);
            $bus->bus_type = $row[1];
            $bus->save();
        }

        MixSetting::putValue('presonus_official_url', 'https://support.presonus.com/hc/en-us/articles/360050575871-StudioLive-Series-III-Lost-Missing-PreSonus-FAT-Channel-Presets');
        MixSetting::putValue('presonus_redistribution', 'link_only');
    }

    protected function starterChannels()
    {
        $names = [
            1 => 'Kick Out', 2 => 'Kick In', 3 => 'Snare Top', 4 => 'Snare Bottom',
            5 => 'Tom 1', 6 => 'Tom 2', 7 => 'Tom 3', 8 => 'Tom 4',
            9 => 'Floor Tom', 10 => 'Hi-Hat', 11 => 'Overhead L', 12 => 'Overhead R',
        ];
        $channels = [];
        for ($i = 1; $i <= 32; $i++) {
            $channels[] = [
                'position' => $i,
                'name' => isset($names[$i]) ? $names[$i] : 'Channel '.$i,
                'instrument_id' => null,
                'preset_version_id' => null,
                'bus_id' => null,
                'stereo_link' => null,
            ];
        }
        $channels[10]['stereo_link'] = 12;
        $channels[11]['stereo_link'] = 11;

        return $channels;
    }
}
