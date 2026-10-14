<?php

namespace Tests\Unit;

use App\Services\Assistant\AssistantLanguage;
use Tests\TestCase;

class AssistantLanguageTest extends TestCase
{
    public function test_a_new_language_replaces_english()
    {
        $language = new AssistantLanguage();
        $english = $language->follow('Hello, I need speakers for a wedding', 'English', '');
        $this->assertSame('English', $english['language']);

        $french = $language->follow('Bonjour, je veux une sonorisation pour samedi', 'English', 'Hello, I need speakers');
        $this->assertSame('French', $french['language']);
        $this->assertStringContainsString('French', $language->rule($french['language'], $french['sample']));

        $other = $language->follow('Preciso de som para o casamento no sábado', 'English', 'Hello');
        $this->assertSame('match', $other['language']);
        $this->assertStringContainsString('Preciso de som', $language->rule($other['language'], $other['sample']));
    }

    public function test_a_short_reply_keeps_the_language_already_in_use()
    {
        $language = new AssistantLanguage();
        $kept = $language->follow('1', 'French', 'Bonjour');
        $this->assertSame('French', $kept['language']);
    }
}
