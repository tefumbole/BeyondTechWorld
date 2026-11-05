<?php

namespace App\Travel;

class TravelException extends \RuntimeException
{
    public $error;

    public function __construct($error, $message)
    {
        $this->error = $error;
        parent::__construct($message);
    }
}
