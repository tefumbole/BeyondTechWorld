<?php

namespace App\Nbc;

class NbcBylaw extends NbcModel
{
    protected $table = 'nbc_bylaws';

    protected $fillable = ['version', 'body'];

    public static function current()
    {
        $row = static::orderByDesc('version')->first();
        if ($row) {
            return $row;
        }
        $row = new static();
        $row->version = 1;
        $row->body = static::starter();
        $row->save();

        return $row;
    }

    public static function starter()
    {
        return "Nkwen Baptist Church Praise Team\n\nThese bylaws are a starting draft. The team owner should replace this text with the adopted praise-team bylaws before inviting members.\n\n1. Membership is for people who serve in the praise team of Nkwen Baptist Church.\n2. A person joins by reading these bylaws and signing that they agree.\n3. Members attend rehearsals and services, and they record attendance when they arrive and when they leave.\n4. Members keep their part, instrument, and contact details up to date.\n5. Leaders may assign tasks, send letters, prepare quotations, and publish announcements for the team.\n6. A person who does not agree to these bylaws does not continue into the team.";
    }
}
