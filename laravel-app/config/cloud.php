<?php

return [
    /*
    | Temporary compatibility for the existing BeyondTechWorld installation.
    | It resolves the INTERNAL company by slug, never by a hardcoded id.
    | It is not a license to query every company when context is missing.
    */
    'legacy_internal_context' => env('CLOUD_LEGACY_INTERNAL_CONTEXT', true),

    'internal_slug' => 'beyondtechworld',

    /*
    | Public "Build Your Own Company" stays off until an approved production opening.
    | Tests may turn this on. The default must stay false.
    */
    'public_onboarding' => env('CLOUD_PUBLIC_ONBOARDING', false),

    /*
    | Live subscription charges stay off until a provider webhook is validated.
    */
    'payments_live' => env('CLOUD_PAYMENTS_LIVE', false),

    'trial_welcome' => 'Your selected services are available free during your trial.',

    'trial_ended_notice' => 'Your free trial has ended. Your information remains available in read-only mode. Please contact BeyondTechWorld to activate or renew your subscription.',

    'payment_pending_notice' => 'Online renewal is not available yet. Please contact BeyondTechWorld to activate or renew a subscription.',

    /*
    | Directly owned models filter by the active company.
    | A missing company matches nothing. It does not match every row.
    */
    'isolate_queries' => env('CLOUD_ISOLATE_QUERIES', true),

    /*
    | Hours after a paid period ends before PAST_DUE becomes EXPIRED.
    | Zero means the period ends in PAST_DUE (read-only) until payment or an admin action.
    | Trial end without payment becomes EXPIRED immediately. No data is deleted.
    */
    'grace_hours' => (int) env('CLOUD_GRACE_HOURS', 0),

    /*
    | Sandbox billing accepts test webhook events. Live Campay and Stripe
    | confirmation stays in CloudCheckoutService. Default is off.
    */
    'billing_sandbox' => env('CLOUD_BILLING_SANDBOX', false),
];
