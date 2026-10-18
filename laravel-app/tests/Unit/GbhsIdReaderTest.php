<?php

namespace Tests\Unit;

use App\Gbhs\GbhsBylaws;
use App\Gbhs\GbhsIdReader;
use Tests\TestCase;

class GbhsIdReaderTest extends TestCase
{
    public function test_it_reads_a_passport_and_a_national_id_without_keeping_an_image()
    {
        $reader = new GbhsIdReader();
        $passport = $reader->parse("PASSPORT\nSurname: MBOLA\nGiven names: TEFU\nPassport No: CM1234567\nDate of issue: 12/03/2019\nP<CMRMBOLA<<TEFU<<<<<<<<<<<<\nCM12345678CMR9001011M2901011");
        $this->assertSame('TEFU MBOLA', $passport['name']);
        $this->assertSame('CM1234567', $passport['number']);
        $this->assertSame('2019-03-12', $passport['issued_on']);

        $card = $reader->parse("REPUBLIQUE DU CAMEROUN\nNom: NGUM\nPrenoms: ADA\nN° 102938475\nDelivre le 04/11/2020");
        $this->assertSame('ADA NGUM', $card['name']);
        $this->assertSame('102938475', $card['number']);
        $this->assertSame('2020-11-04', $card['issued_on']);
    }

    public function test_bylaws_are_numbered_articles()
    {
        $articles = GbhsBylaws::articles();
        $this->assertSame(1, $articles[0]['n']);
        $this->assertSame('Name', $articles[0]['title']);
        $this->assertSame(7, $articles[6]['n']);
        $this->assertNotSame('', $articles[0]['body']);
    }
}
