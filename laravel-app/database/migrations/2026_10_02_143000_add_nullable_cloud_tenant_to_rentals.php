<?php

use App\Services\Cloud\CloudTenantColumn;
use Illuminate\Database\Migrations\Migration;

/**
 * Group C. Rental booking headers. Line rows stay owned through the booking.
 * Does not touch property tenancies.
 */
class AddNullableCloudTenantToRentals extends Migration
{
    public function up()
    {
        CloudTenantColumn::add('bookings');
    }

    public function down()
    {
        CloudTenantColumn::remove('bookings');
    }
}
