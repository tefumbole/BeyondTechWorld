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
    | Public "Build Your Own Company" stays closed until a later phase.
    */
    'public_onboarding' => env('CLOUD_PUBLIC_ONBOARDING', false),

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
