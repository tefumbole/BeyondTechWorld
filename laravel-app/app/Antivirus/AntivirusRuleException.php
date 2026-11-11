<?php

namespace App\Antivirus;

class AntivirusRuleException extends \RuntimeException
{
    /** @var string */
    public $field;

    public function __construct($field, $message)
    {
        parent::__construct($message);
        $this->field = $field;
    }
}
