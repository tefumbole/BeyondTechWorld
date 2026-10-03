<?php

namespace App\Services\Assistant;

use App\WhatsApp\WhatsAppContact;
use Illuminate\Support\Facades\Schema;

/**
 * How the assistant should speak to one person: who they are, the name you
 * use for them, and the language they speak. The WhatsApp saved name can differ.
 */
class AssistantContactVoice
{
    public function line($contact)
    {
        if (! $contact || ! $this->ready()) {
            return '';
        }
        $saved = trim((string) $contact->wa_name);
        $call = trim((string) $contact->call_name);
        $relation = trim((string) $contact->relationship);
        $language = trim((string) $contact->preferred_language);
        $note = trim((string) $contact->voice_note);
        if ($call === '' && $relation === '' && $language === '' && $note === '') {
            return '';
        }
        $parts = ['How to speak to this person.'];
        if ($relation !== '') {
            $parts[] = 'They are '.$relation.'.';
        }
        if ($call !== '') {
            $parts[] = 'Address them as '.$call.'.';
            if ($saved !== '' && strcasecmp($saved, $call) !== 0) {
                $parts[] = 'The name saved on WhatsApp is '.$saved.', which is not the name you use.';
            }
        }
        if ($language !== '') {
            $parts[] = 'They speak '.$language.'. Answer them in '.$language.'.';
        }
        if ($note !== '') {
            $parts[] = $note;
        }
        $parts[] = 'Use this only with this person. Do not start a new greeting just because you know their name.';

        return implode(' ', $parts);
    }

    public function preferredName($contact)
    {
        if (! $contact) {
            return null;
        }
        if ($this->ready()) {
            $call = trim((string) $contact->call_name);
            if ($call !== '') {
                return $call;
            }
        }

        return $contact->displayName();
    }

    public function save(WhatsAppContact $contact, array $input)
    {
        if (! $this->ready()) {
            return false;
        }
        $contact->relationship = $this->clip(isset($input['relationship']) ? $input['relationship'] : '', 80);
        $contact->call_name = $this->clip(isset($input['call_name']) ? $input['call_name'] : '', 80);
        $contact->preferred_language = $this->clip(isset($input['preferred_language']) ? $input['preferred_language'] : '', 80);
        $contact->voice_note = $this->clip(isset($input['voice_note']) ? $input['voice_note'] : '', 1000);
        $contact->save();

        return true;
    }

    public function ready()
    {
        return Schema::hasTable('whatsapp_contacts') && Schema::hasColumn('whatsapp_contacts', 'call_name');
    }

    protected function clip($value, $max)
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
