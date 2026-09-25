# WhatsApp Group Intelligence

Date: 25 September 2026

This stays on the existing WhatsApp Hub. There is no second assistant, webhook, or provider. Stage 9 was not started. Discovered groups are not monitored until an owner or admin enables them.

## Owner control

An owner command is accepted only when the private sender resolves to an ERP user who is listed in `whatsapp_owner_users` and whose role has `whatsapp.owner` (or `whatsapp.ai.manage`). The WaSender session number is not an owner by itself.

Exact commands `AI ON`, `AI OFF`, `AI FIRST ON`, `AI FIRST OFF`, `AI STATUS`, and `GROUPS` run tools directly. Natural wording can request the same tools. `set_ai_enabled` and `set_ai_first` change settings for new chats and do not rewrite open conversations. A bulk switch first states how many chats are eligible and how many stay human. Only a later `YES` runs `ConversationAiSwitchService`. Any other reply cancels that pending action.

“Take over Daniel” or a phone number sets that one private conversation to HUMAN and assigns the owner. Several matches ask for a clearer name. “Return Daniel to AI” sets AI and clears the owner’s assignment.

## Group webhook

`messages-group.received` uses the current webhook. The group JID stays separate from the participant phone (`cleanedParticipantPn` or the participant JID). A group message does not open a private conversation and does not create a customer or a lead.

`whatsapp_groups` rows discovered from the provider start as `OFF`. Enabling a group sets `MONITOR` unless the owner explicitly chooses Mention only or Active. Monitor stores messages and does not speak. Mention only speaks on `@Beyond`. Active still stays silent unless the message is a mention, a direct question, an ERP question, or a summary request.

## Intelligence

Summaries, decisions, issues, and the morning brief read stored messages for enabled groups and the requested time window. A proposal is not reported as a decision. Suggested actions stay `SUGGESTED` until the owner confirms them. ERP tasks are created only through `TaskService::createTasks()`, and only when an assignee exists.

A stock number said in a group is discussion. An inventory answer comes from the product tables. The reply can say the group said one figure and the ERP records another.

Each group has its own `memory_json`. Sensitive requests (salary, payslip, OTP, contract, tenant balance) are not answered in the group. The group is told “I can help with that privately.” Group capability flags can deny inventory, events, finance, payroll, or internship even when the person asks.

A draft is sent only after the owner confirms the named group. “Send it” with no draft, or a different group name, does not post.

Raw messages are pruned by `whatsapp:prune-group-messages` using each group’s raw retention days. Confirmed actions keep their source message id.

## Tests

`./vendor/bin/phpunit --filter WhatsApp` — 153 tests, 852 assertions, OK.

Live validation was not run. AI was not turned on for any production group.
