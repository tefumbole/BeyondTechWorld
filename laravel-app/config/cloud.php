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
];
