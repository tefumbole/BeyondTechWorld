<?php

namespace App\Services\Cloud;

use App\Cloud\CloudBillingInterval;
use App\Cloud\CloudModule;
use App\Cloud\CloudModuleCode;
use App\Cloud\CloudPaymentMethod;
use App\Cloud\CloudPaymentMethodCode;
use App\Cloud\CloudPlan;
use App\Cloud\CloudTrialUnit;

/**
 * Writes the global module and plan catalog.
 * Prices live in cloud_plans after this runs. Later screens must read those rows.
 * This does not create a CloudTenant and does not subscribe BeyondTechWorld.
 */
class CloudCatalog
{
    public static function install()
    {
        $modules = [
            [
                'code' => CloudModuleCode::WHATSAPP_HUB,
                'name' => 'WhatsApp Hub',
                'description' => 'Team WhatsApp inbox, conversations, MAI and supported automation.',
                'sort_order' => 1,
                'plan_code' => 'WHATSAPP_HUB_MONTHLY',
                'price' => '10000.00',
            ],
            [
                'code' => CloudModuleCode::SALES_INVOICES,
                'name' => 'Sales & Invoices',
                'description' => 'Products, customers, sales, quotations, invoices and related business operations.',
                'sort_order' => 2,
                'plan_code' => 'SALES_INVOICES_MONTHLY',
                'price' => '5000.00',
            ],
            [
                'code' => CloudModuleCode::RENTALS,
                'name' => 'Rentals',
                'description' => 'Rental inventory, bookings, events and rental operations.',
                'sort_order' => 3,
                'plan_code' => 'RENTALS_MONTHLY',
                'price' => '5000.00',
            ],
            [
                'code' => CloudModuleCode::MESSAGING,
                'name' => 'Messaging',
                'description' => 'Company messaging for customers and staff.',
                'sort_order' => 4,
                'plan_code' => 'MESSAGING_MONTHLY',
                'price' => '5000.00',
            ],
            [
                'code' => CloudModuleCode::QUOTATIONS,
                'name' => 'Quotations',
                'description' => 'Customer quotations, separate from invoices and rental bookings.',
                'sort_order' => 5,
                'plan_code' => 'QUOTATIONS_MONTHLY',
                'price' => '5000.00',
            ],
            [
                'code' => CloudModuleCode::DIGITAL_INVITATIONS,
                'name' => 'Digital Invitations',
                'description' => 'Digital invitations, guest links and RSVP.',
                'sort_order' => 6,
                'plan_code' => 'DIGITAL_INVITATIONS_MONTHLY',
                'price' => '5000.00',
            ],
        ];

        foreach ($modules as $row) {
            $module = CloudModule::firstOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'description' => $row['description'],
                    'active' => true,
                    'sort_order' => $row['sort_order'],
                ]
            );
            CloudPlan::firstOrCreate(
                ['code' => $row['plan_code']],
                [
                    'cloud_module_id' => $module->id,
                    'name' => $row['name'],
                    'billing_interval' => CloudBillingInterval::MONTH,
                    'price' => $row['price'],
                    'currency' => 'XAF',
                    'trial_value' => 24,
                    'trial_unit' => CloudTrialUnit::HOUR,
                    'active' => true,
                    'sort_order' => $row['sort_order'],
                ]
            );
        }

        self::installPaymentMethods();
    }

    /**
     * MoMo and VISA are the payment methods already used in this application.
     * MoMo charges through Campay. VISA charges through the existing Stripe card checkout.
     */
    protected static function installPaymentMethods()
    {
        $methods = [
            [
                'code' => CloudPaymentMethodCode::MOMO,
                'name' => 'MoMo',
                'provider' => 'campay',
                'sort_order' => 1,
            ],
            [
                'code' => CloudPaymentMethodCode::VISA,
                'name' => 'VISA',
                'provider' => 'stripe',
                'sort_order' => 2,
            ],
        ];

        foreach ($methods as $row) {
            CloudPaymentMethod::firstOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'provider' => $row['provider'],
                    'active' => true,
                    'sort_order' => $row['sort_order'],
                ]
            );
        }
    }
}
