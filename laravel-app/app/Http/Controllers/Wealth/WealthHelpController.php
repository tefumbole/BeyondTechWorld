<?php

namespace App\Http\Controllers\Wealth;

class WealthHelpController extends WealthBaseController
{
    public function index()
    {
        if (! $this->canAny(['wealth.view', 'wealth.manage'])) {
            return $this->deny();
        }

        return view('wealth.help', $this->lookups() + [
            'wmTab' => 'help',
        ]);
    }
}
