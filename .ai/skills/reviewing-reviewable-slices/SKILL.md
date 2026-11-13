---
name: reviewing-reviewable-slices
description: 'Use when reviewing a pull request that is a slice of a larger feature or part of a GitHub stack, when asked to check whether a pull request or planned slice is reviewable, when reviewing a stack plan, or when reviewing the slice that makes a feature available to customers (the keystone). Covers the reviewer protocol (review the pull request on its own, with its ticket, in any order), the five review-readiness tests, what to push back on (size, missing uses of new code, refactors mixed with behaviour, unfinished work reachable by customers), re-reviewing after a rebase, and reviewing the keystone. Can also be run on your own pull request before marking it ready. Do not use for: planning slices (use `planning-reviewable-slices`), building or merging a stack (use `building-and-landing-stacks`), or general code-quality review that has nothing to do with size or slicing.'
license: Elastic-2.0
metadata:
    author: canyongbs
---

# Reviewing Reviewable Slices

Review each slice on its own merits. Nobody reviews the whole stack: the author holds the whole feature in their head, the ticket gives reviewers the overall picture, and each slice's reviewer covers that slice. Different people reviewing different slices of one ticket is normal. The reasoning is in [Reviewable Slices](https://github.com/canyongbs/common/blob/main/docs/explanations/reviewable-slices.md).

## Review a slice

1. **Review this pull request on its own, with its ticket for context:** its description and its diff. You are responsible for what this slice adds, not for the slices around it, which get their own reviews, in any order. Assume the code it builds on does what its own pull request says.
2. **Open another slice only when you need to.** If a question comes up that another slice answers, you can look at it, but that should be the exception. If you cannot understand this slice without reading others, say so: it is not self-explanatory yet.
3. **Apply the five tests** from `planning-reviewable-slices`:
    1. **One idea:** the title needs no "and".
    2. **Self-explanatory:** the description, the diff, the code it builds on and the ticket are enough; nothing depends on reading another pull request.
    3. **Complete for its idea:** it has its own tests, and every new model, service, job or method is actually used in this slice. The exception is a foundation slice, or the lower part of a concept split because it was over budget: its new code may be used only by the slice directly above it, and its own tests must prove what it does.
    4. **Safe to land:** the application still works after it merges, migrations are tolerated by deployed code, **nothing unfinished is reachable by a customer**, and **nothing customers already use changes before its replacement is switched on**. Check the code, not the description: every new page, action, route, API field, notification and job trigger that exposes **unfinished** work is unwired, or gated behind the feature's inactive release flag **in this slice**, and each gated path is tested with the flag on and off. Finished work, including what the slice that finishes the feature makes available, may be reachable. Use the checklist in `hiding-unfinished-work`.
    5. **Sized for one sitting:** check the readable-lines comment on the pull request against the budget.
4. **Approve when the slice is right on its own.** Do not withhold approval because a slice above it does not exist yet; that is the stack working as intended.

## Push back on

- A description that says what code the slice contains ("adds the models") rather than what the product does after it merges.
- New models, services, jobs or methods with no caller in the slice (outside the foundation and split-concept exception), or "will be used in a later pull request".
- A refactor or mechanical change mixed with a behaviour change.
- A slice over 1,000 readable lines without a stated reason, or over 1,500 without serious justification. You may decline to review for size alone and ask for a split.
- Anything unfinished that a customer could reach, or a breaking change customers would see before it is switched on.
- The feature's release flag (one hiding unfinished work) activated before the slice that finishes the feature. Zero-downtime flags that a lower slice activates in the migration that makes its schema change are fine, as long as nothing unfinished becomes reachable.
- A pull request that only activates a flag or wires an entry point, when that change could have gone in the slice that finishes the feature. It is justified only when activation must wait for something outside the stack, or the wiring or activation is complex enough to deserve its own review.

## Re-review after a rebase

Approvals survive rebases that do not change a slice's diff. When a rebase does change it, the approval is dismissed and the author re-requests you. Review only GitHub's **changes since your last review**, which is usually small, and re-approve. Budget minutes, not another sitting.

## Review a stack plan

When someone asks you to look over a plan before coding starts (this is optional, never a required approval), answer:

- Are these the right concepts, and is each slice one of them?
- Is the foundation slice really shared, and is it used by the slice directly above it?
- Does the feature need a keystone, and is it in the slice that finishes the feature (or, if it is on its own, is the reason given)?
- Does every slice leave the application working, with nothing unfinished or breaking reachable?
- Is the landing mode right, and is a hold justified?

A comment is enough.

## Review the slice that makes the feature available

The keystone (wiring the entry point or activating the flag) normally comes with the slice that finishes the feature. Review that slice's own concept as usual, then also check:

- The keystone makes available only what this slice and the slices below have built and approved.
- The feature has been run end to end from the customer's entry point with the flag on. Run it yourself; do not just read it.
- The enable path (setting, permission, tenant) behaves correctly.
- The feature's cleanup task is now marked `ready: true`, and tests that activated the flag by hand rely on the activation migration instead.

## AI review

Copilot code review is requested on every pull request once its checks pass. Treat its comments as a checklist, not a verdict: resolve each with a reason (addressed, won't fix, incorrect). A bot comment is never an approval; a person approves every slice.

---

Related: `planning-reviewable-slices`, `building-and-landing-stacks`, `hiding-unfinished-work`.
