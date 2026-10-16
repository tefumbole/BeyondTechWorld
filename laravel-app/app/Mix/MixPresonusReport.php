<?php

namespace App\Mix;

class MixPresonusReport
{
    const URL = 'https://support.presonus.com/hc/en-us/articles/360050575871-StudioLive-Series-III-Lost-Missing-PreSonus-FAT-Channel-Presets';

    public static function findings()
    {
        return [
            'source' => self::URL,
            'updated' => '2 December 2020',
            'what_it_is' => 'PreSonus tells Series III owners how to restore factory Fat Channel presets that were deleted or moved. The article is a customer restore guide, not a public preset specification.',
            'format' => 'Option 2 is a download named FatChannelPresets.zip. The article says the ZIP contains a README and files that Universal Control copies from a computer folder named Fat onto the mixer. No JSON, CSV, or other documented parameter schema is published on that page.',
            'decoded' => 'EQ, compressor, and gate values were not extracted. The ZIP is not stored in this application, and the article does not document the file layout well enough to decode a preset safely.',
            'compatibility' => 'The article covers StudioLive Series III consoles and rack mixers. StudioLive 32SX is a Series III console, so the factory library is the one PreSonus intends for that family. Individual files were not byte-checked against a 32SX.',
            'export' => 'Universal Control can copy presets between the computer and the mixer. This site does not generate a native StudioLive preset and does not send settings to a console.',
            'license' => 'The ZIP is offered to customers restoring their own mixer. That article does not grant permission to host or redistribute the factory presets. This module links to the official article and keeps the Beyond collection separate.',
        ];
    }
}
