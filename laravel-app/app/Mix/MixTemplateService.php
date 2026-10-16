<?php

namespace App\Mix;

class MixTemplateService
{
    public function writeVersion(MixTemplate $template, array $channels, $notes = null)
    {
        $number = (int) MixTemplateVersion::where('template_id', $template->id)->max('version_number') + 1;
        $version = new MixTemplateVersion();
        $version->template_id = $template->id;
        $version->version_number = $number;
        $version->notes = MixSupport::text($notes);
        $version->save();
        foreach ($channels as $channel) {
            $row = new MixTemplateChannel();
            $row->template_version_id = $version->id;
            $row->position = (int) $channel['position'];
            $row->name = trim((string) $channel['name']) !== '' ? trim((string) $channel['name']) : 'Channel '.$row->position;
            $row->instrument_id = ! empty($channel['instrument_id']) ? (int) $channel['instrument_id'] : null;
            $row->preset_version_id = ! empty($channel['preset_version_id']) ? (int) $channel['preset_version_id'] : null;
            $row->bus_id = ! empty($channel['bus_id']) ? (int) $channel['bus_id'] : null;
            $row->stereo_link = isset($channel['stereo_link']) && $channel['stereo_link'] !== '' && $channel['stereo_link'] !== null
                ? (int) $channel['stereo_link'] : null;
            $row->save();
        }
        $template->current_version_id = $version->id;
        $template->save();
        MixAuditLog::write('template.version', $template, 'Version '.$number);

        return $version;
    }

    public function duplicate(MixTemplate $template)
    {
        $copy = new MixTemplate();
        $copy->console_id = $template->console_id;
        $copy->name = $template->name.' copy';
        $copy->slug = MixSupport::uniqueSlug(MixTemplate::class, $copy->name);
        $copy->channel_count = $template->channel_count;
        $copy->notes = $template->notes;
        $copy->published = false;
        $copy->save();
        $source = $template->currentVersion ? $template->currentVersion->channels : collect();
        $channels = [];
        foreach ($source as $channel) {
            $channels[] = [
                'position' => $channel->position,
                'name' => $channel->name,
                'instrument_id' => $channel->instrument_id,
                'preset_version_id' => $channel->preset_version_id,
                'bus_id' => $channel->bus_id,
                'stereo_link' => $channel->stereo_link,
            ];
        }
        $this->writeVersion($copy, $channels, 'Duplicated from '.$template->name);

        return $copy;
    }

    public function upgrade(MixTemplate $template)
    {
        $current = $template->currentVersion;
        if (! $current) {
            return null;
        }
        $channels = [];
        foreach ($current->channels as $channel) {
            $versionId = $channel->preset_version_id;
            if ($versionId) {
                $pinned = MixPresetVersion::find($versionId);
                if ($pinned) {
                    $latest = MixPresetVersion::where('preset_id', $pinned->preset_id)->orderByDesc('version_number')->first();
                    if ($latest) {
                        $versionId = $latest->id;
                    }
                }
            }
            $channels[] = [
                'position' => $channel->position,
                'name' => $channel->name,
                'instrument_id' => $channel->instrument_id,
                'preset_version_id' => $versionId,
                'bus_id' => $channel->bus_id,
                'stereo_link' => $channel->stereo_link,
            ];
        }

        return $this->writeVersion($template, $channels, 'Upgraded pinned presets to their current versions');
    }
}
