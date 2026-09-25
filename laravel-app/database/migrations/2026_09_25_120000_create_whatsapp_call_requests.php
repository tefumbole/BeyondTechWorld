<?php

use App\Assistant\AssistantKnowledge;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWhatsappCallRequests extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('whatsapp_call_requests')) {
            Schema::create('whatsapp_call_requests', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('conversation_id')->index();
                $table->unsignedBigInteger('contact_id')->nullable()->index();
                $table->string('status', 32)->default('REQUESTED')->index();
                $table->unsignedInteger('assigned_user_id')->nullable()->index();
                $table->text('requested_body')->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('contacted_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('assistant_knowledge')) {
            return;
        }
        $rows = [
            ['Sound', 'sound', 'BeyondTechWorld provides sound reinforcement for events: mixers, speakers, and microphones. Stock and prices come from the rental catalogue, not from this note.'],
            ['Lighting', 'lighting', 'Lighting covers stage and event lighting. Availability and prices are read from the product tables when someone asks.'],
            ['Screens', 'screens', 'LED screens and display equipment are rented from the catalogue. This note does not list stock or prices.'],
            ['Stage', 'stage', 'Stage and event production support can be arranged with the rental team. Confirm dates and quantities from the ERP.'],
            ['Technical support', 'technical_support', 'Technicians can be requested with a rental. A person confirms technician availability. This note is not a booking.'],
            ['Company', 'company', 'BeyondTechWorld provides event production, equipment rental, IT services, training, and internships in Cameroon.'],
        ];
        foreach ($rows as $row) {
            $exists = AssistantKnowledge::where('title', $row[0])->where('category', $row[1])->first();
            if ($exists) {
                continue;
            }
            AssistantKnowledge::create([
                'title' => $row[0],
                'category' => $row[1],
                'content' => $row[2],
                'enabled' => true,
            ]);
        }
    }

    public function down()
    {
        Schema::dropIfExists('whatsapp_call_requests');
    }
}
