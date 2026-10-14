<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

class UpdateMsataAssistantBrief extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('assistant_briefs')) {
            return;
        }

        $brief = \App\Assistant\AssistantBrief::query()
            ->where('title', 'MSATA 2026 — My Sacrifice At The Altar')
            ->first();
        if (! $brief) {
            return;
        }

        $brief->details = <<<'TXT'
This is a live recording concert by Spirit Waves Music. It is a worship and praise gathering. Beyond Enterprise does not organise, produce, or power this concert. Beyond only provides the free seat-booking page.

People also call this Ma Caro's Concert, Caroline Forbah's Concert, and The Flames concert. Those names are this same MSATA 2026 event.

When: Sunday, 8 November 2026, 1:00 PM prompt. Arrive on time.
Where: Ntamulung Presbyterian Hall, Veterinary Junction, Bamenda. It is directly opposite the entrance to Franco Alliance, Bamenda.
Entry: Free. A seat still has to be booked. There is no ticket price.
Booking page: https://beyondtechworld.com/caro
Picture guide: https://beyondtechworld.com/caro/help
Event questions: +237 6 71 14 70 99. That number is for Spirit Waves Music. It is not the Beyond chat line.

How to book: open the booking page, enter a WhatsApp number, choose Guest, Rev. / Pastor, Guest Minister, or Gospel Minister, keep or change the suggested seat, then tap Assign seat. One WhatsApp invitation is sent. The card shows the full seat address, for example B-01-02 (section B, row 01, chair 02). The same WhatsApp number cannot receive a second invitation.

The hall has sections A, B, C, and D. Each section has 20 rows and 8 chairs. Section B row 1 is for Guest Ministers. Section B rows 2 and 3 are for Rev. / Pastors. Section C rows 1 to 3 are for Gospel Ministers. Every other open seat is a standard Guest seat. A person can only take a seat for the role they chose. Some chairs in sections B and C, rows 10 and 11, are the sound desk and are not seats.

The invitation QR shows that it is valid, plus the guest name, role, and full seat address. On Sunday, 8 November 2026 only, the scanned page has a Mark present button. After a guest is marked present, that guest receives one WhatsApp thank-you. A second thank-you is not sent.

If someone asks for a livestream link, a paid ticket, or a Beyond-produced show, say entry is free, the concert is in the hall and will be recorded live, and Beyond is not the organiser. Give the booking link and +237 6 71 14 70 99 for anything that is not written here.
TXT;
        $brief->enabled = true;
        $brief->save();
    }

    public function down()
    {
    }
}
