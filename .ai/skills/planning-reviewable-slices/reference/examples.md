# Reviewable Slice Examples

Each row is a candidate pull request, judged against the five review-readiness tests and the size budget in [SKILL.md](../SKILL.md).

| Candidate pull request                                                                                                                                                    | Verdict             | Why                                                                                                                          |
| ------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------- | ---------------------------------------------------------------------------------------------------------------------------- |
| Pre-meeting reminder email: rules, job, mailable, audit record, Filament action gated behind `MeetingEmailsFeature`, tests (~450 lines)                                   | Good slice          | One concept, complete including its UI, tested with the flag on and off, unreachable while the flag is inactive.             |
| Add all models and migrations for the meeting-email feature (~900 lines)                                                                                                  | Horizontal; split   | Nothing uses them yet, so the reviewer must imagine the callers. Keep only what the next slice needs, as a foundation slice. |
| Foundation: `meeting_email_templates` migration, model, factory, `MeetingEmailsFeature` flag and its cleanup task, used by the reminder slice directly above (~220 lines) | Good slice          | A row is fine when two concepts share it and the next slice uses it immediately. The migration does not activate the flag.   |
| Follow-up email slice that also activates `MeetingEmailsFeature` and marks its cleanup task ready (~480 lines)                                                            | Good slice, last    | It finishes the feature, so it carries the keystone. Its reviewer runs the feature end to end with the flag on.              |
| A pull request that only activates `MeetingEmailsFeature`, after every slice has merged (~30 lines)                                                                       | Usually fold it in  | Costs a review and delays the feature. Put it in the slice that finishes it, unless activation must wait or is complex.      |
| Reminder email and follow-up email together (~1,400 lines)                                                                                                                | Split               | "And" in the title; two product behaviours. One slice per concept column.                                                    |
| Rename `MeetingNotifier` to `MeetingMailer` across 30 files, plus add retry logic to it (~400 lines)                                                                      | Split               | Refactor mixed with a behaviour change. Rename first (verified with a tool), retry logic second (read carefully).            |
| Bump Filament and fix the resulting deprecations (~1,200 lines)                                                                                                           | Acceptable as large | Tool-driven and mechanical. Keep hand-written changes out of it and say it is tool-generated.                                |
| Add `ExportMeetingsJob` with no dispatcher, "will be wired up in a later pull request" (~200 lines)                                                                       | Not a slice         | Unused code. At ~200 lines the export fits the budget, so there is no reason to split it; include its dispatcher.            |
| Follow-up email, but the job alone is 900 lines of rules                                                                                                                  | Split the column    | Over budget for one concept. Rules and model first; job, mail and UI on top, both still within the follow-up column.         |
| New Filament page for a feature, with no flag check, nothing else finished (~300 lines)                                                                                   | Not safe to land    | Reachable by customers while unfinished. Gate it behind the feature's flag or leave it unregistered until the keystone.      |
| Fix a typo in a Blade view (3 lines)                                                                                                                                      | Good slice          | Small is never a problem.                                                                                                    |

## A worked plan

**Ticket:** "Send pre-meeting and post-meeting emails from the scheduling system."

**Grid:** two concept columns (reminder, follow-up) and one shared foundation row. The keystone goes in the follow-up slice, which finishes the feature.

**Stack, bottom to top** (branch names follow the recommended `<KEY-N>-<position>` form):

1. `ADVAPP-3100-1`, **foundation.** Migration, `MeetingEmailTemplate` model, factory, tenant scoping, the `MeetingEmailsFeature` flag with its cleanup task (`ready: false`), tests. The product does nothing new; the flag is inactive, and the next slice uses all of it.
2. `ADVAPP-3100-2`, **concept.** Scheduling rule, `SendPreMeetingReminder` job dispatched with `dispatch(new SendPreMeetingReminder($meeting))`, mailable, audit record, Filament action gated on the flag, tests with the flag on and off. The product can send reminders, but no customer can reach them.
3. `ADVAPP-3100-3`, **concept, with the keystone.** The same shape for follow-ups, plus a migration that activates `MeetingEmailsFeature`; the cleanup task is marked `ready: true`, and tests that activated the flag by hand now rely on the migration. The product sends both emails to customers. The ticket closes when this ships.

**Landing mode:** land as approved. Slices 1 and 2 merge as each is approved; the flag keeps them unreachable. Slice 3 merges once its reviewer has run the whole feature end to end with the flag on. A cleanup pull request after release removes the flag. Had product wanted to choose the launch date, the activation would have been a separate final slice, merged when product gave the go-ahead, and the plan would have said so.
