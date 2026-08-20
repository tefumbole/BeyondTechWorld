<?php

return [
    /*
    | Supervisor review service level, counted in the intern's own working days.
    | A submission left unreviewed past this window is auto-accepted so the
    | placement keeps moving. Set to 0 to disable auto-acceptance entirely
    | (submissions then wait for a supervisor for as long as it takes).
    */
    'review_sla_working_days' => (int) env('INTERNSHIP_REVIEW_SLA_DAYS', 2),

    /*
    | Working days a submission may wait before the supervisor gets a nudge.
    | Must be lower than the SLA to be useful; 0 disables the nudge.
    */
    'review_reminder_working_days' => (int) env('INTERNSHIP_REVIEW_REMINDER_DAYS', 1),

    /*
    | Score recorded on an auto-accepted submission. Null uses the task pass mark,
    | so an unreviewed day counts as a bare pass and never inflates a portfolio.
    */
    'auto_accept_score' => env('INTERNSHIP_AUTO_ACCEPT_SCORE') !== null
        ? (int) env('INTERNSHIP_AUTO_ACCEPT_SCORE')
        : null,
];
