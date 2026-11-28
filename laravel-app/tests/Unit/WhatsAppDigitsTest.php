<?php

namespace Tests\Unit;

use App\Support\CountryDialCodes;
use Tests\TestCase;

class WhatsAppDigitsTest extends TestCase
{
    public function test_any_country_keeps_its_own_code()
    {
        $this->assertSame('14155552671', CountryDialCodes::whatsappDigits('4155552671', '1'));
        $this->assertSame('447911123456', CountryDialCodes::whatsappDigits('07911123456', '44'));
        $this->assertSame('8613800138000', CountryDialCodes::whatsappDigits('13800138000', '86'));
        $this->assertSame('250788000111', CountryDialCodes::whatsappDigits('788000111', '250'));
        $this->assertSame('33612345678', CountryDialCodes::whatsappDigits('+33 6 12 34 56 78', '237'));
    }

    public function test_a_full_number_is_not_prefixed_again()
    {
        $this->assertSame('14155552671', CountryDialCodes::whatsappDigits('14155552671', '1'));
        $this->assertSame('8613800138000', CountryDialCodes::whatsappDigits('8613800138000', '86'));
    }

    public function test_an_old_cameroon_mobile_gains_the_leading_six()
    {
        $this->assertSame('237675321739', CountryDialCodes::whatsappDigits('75321739', '237'));
        $this->assertSame('237675321739', CountryDialCodes::whatsappDigits('675321739', '237'));
        $this->assertSame('237670000111', CountryDialCodes::whatsappDigits('670000111', '237'));
    }

    public function test_a_short_number_without_a_country_is_not_assumed_to_be_cameroon()
    {
        $this->assertSame('4155552671', CountryDialCodes::whatsappDigits('4155552671', ''));
    }
}
