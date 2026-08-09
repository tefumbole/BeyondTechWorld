<?php

namespace App\Support;

use App\InternshipProgram;
use App\InternshipProgramTask;

class InternshipHandbook
{
    /**
     * Map program code → handbook folder + filename prefix under
     * database/data/internship/handbooks/
     */
    public static function catalog()
    {
        return [
            'ARTIFICIAL_INTELLIGENCE' => ['folder' => 'ai', 'prefix' => 'Beyond_AI_Internship'],
            'MACHINE_LEARNING' => ['folder' => 'machine_learning', 'prefix' => 'Beyond_ML_Internship'],
            'DATA_SCIENCE' => ['folder' => 'data_science', 'prefix' => 'Beyond_DataScience_Internship'],
            'SOFTWARE_DEVELOPMENT' => ['folder' => 'software_development', 'prefix' => 'Beyond_SoftwareDev_Internship'],
            'NETWORKING' => ['folder' => 'networking', 'prefix' => 'Beyond_Networking_Internship'],
            'CYBER_SECURITY' => ['folder' => 'cyber_security', 'prefix' => 'Beyond_CyberSecurity_Internship'],
            'CLOUD_COMPUTING' => ['folder' => 'cloud_computing', 'prefix' => 'Beyond_Cloud_Internship'],
            'LIVE_SOUND_ENGINEERING' => ['folder' => 'live_sound', 'prefix' => 'Beyond_LiveSound_Internship'],
            'LIGHTING_ENGINEERING' => ['folder' => 'lighting', 'prefix' => 'Beyond_Lighting_Internship'],
            'SCREENS_VIDEO' => ['folder' => 'screens_video', 'prefix' => 'Beyond_ScreensVideo_Internship'],
            'INTERCOM' => ['folder' => 'intercom', 'prefix' => 'Beyond_Intercom_Internship'],
        ];
    }

    public static function absolutePath(InternshipProgram $program, InternshipProgramTask $task)
    {
        $code = strtoupper(trim((string) $program->code));
        $map = self::catalog();
        if (! isset($map[$code])) {
            return null;
        }
        $day = (int) $task->day_number;
        $filename = sprintf('%s_Day_%03d_Student_Handbook.docx', $map[$code]['prefix'], $day);
        $path = database_path('data/internship/handbooks/'.$map[$code]['folder'].'/'.$filename);
        if (! is_file($path)) {
            return null;
        }

        return $path;
    }

    public static function downloadName(InternshipProgram $program, InternshipProgramTask $task)
    {
        $code = strtoupper(trim((string) $program->code));
        $map = self::catalog();
        if (! isset($map[$code])) {
            return 'Day_Handbook.docx';
        }

        return sprintf('%s_Day_%03d_Student_Handbook.docx', $map[$code]['prefix'], (int) $task->day_number);
    }
}
