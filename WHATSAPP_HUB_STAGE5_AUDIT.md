# WhatsApp Hub — Stage 5 audit

Date: 23 September 2026

WhatsApp is another way into the existing internship programme. It does not create a second programme, task list, grading engine, or next-task release.

## Reused

- Identity: `WhatsAppIdentityService` (an intern link is added when any enrolment exists). Stage 5 tools then require `internship_enrolments.status = active`. More than one active enrolment asks the intern to choose.
- Current task, locked days, and tomorrow use `InternshipEnrolment::nextCurriculumDay()` and `internship_task_assignments.released_at`. Unreleased rows do not send a title or instructions.
- Materials use `InternshipHandbook` and the task’s own instructions. Files go out through `WhatsAppProviderInterface`.
- Official submit calls `InternshipProgramService::submitAssignment`. That method notifies the student and supervisors. If that notification log exists and is not failed, the assistant does not send a second confirmation (`skip_assistant_reply`).
- The next task is still released only from supervisor grading (`tryReleaseNext`). WhatsApp does not call it.
- Supervisor review stays on the existing submission screen. WhatsApp origin is stored on `internship_submissions.source`, `whatsapp_conversation_id`, and `whatsapp_message_ids` when those columns exist.

## Added

- Intake tables: `whatsapp_internship_intakes`, `whatsapp_internship_intake_files`, `whatsapp_internship_activities`.
- Tools: summary, current task, instructions, progress, materials, submission status, prepare, submit, attach file, attach link, supervisor handover.
- Media download runs only in queued `RetrieveInternshipMedia` (HTTPS). The webhook still persists and returns 200.
- Rejected types include php, phtml, phar, exe, sh, bat, cmd, js, jar, html, htm, svg, and htaccess. Voice files are kept only when the task asks for audio. Limit: `services.whatsapp.internship_max_bytes` (default 20MB).
- GitHub URLs are stored as links. The server does not clone or run intern code.
- “Done” without the required file does not create `InternshipSubmission`.
- Grade, pass, and release tools are refused (`grading_forbidden`).
- “I need my supervisor”, “I don’t understand this assignment”, upload failure, grade disagreement, and “I need help” hand the chat to HUMAN and assign the supervisor user when one is on the enrolment.
- Hub: conversation panel, Internship operations page, command center counts, diagnostics. Permissions `whatsapp.internship`, `.view`, `.submit`, `.manage`, `.media` for roles 1–2. Supervisor grading permissions are unchanged.
- `whatsapp:prune-webhooks` still deletes only `whatsapp_webhook_events`.

## Tests

`cd laravel-app && ./vendor/bin/phpunit --filter WhatsApp`

87 tests, 388 assertions, OK. This includes Stage 5 and Stages 1–4.

## Live checks A–W

NOT RUN. Do not mark these passed until a real WhatsApp chat is tested against a real internship enrolment.

## Out of scope

Stage 6 attendance and field operations was not started.
