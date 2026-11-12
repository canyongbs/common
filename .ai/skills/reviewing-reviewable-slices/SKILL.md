---
name: reviewing-reviewable-slices
description: 'Use when reviewing a pull request that is a slice of a larger feature or part of a GitHub stack, when asked to check whether a pull request or planned slice is reviewable, when reviewing a stack plan, or when reviewing the keystone slice that makes a feature available to customers. Covers the reviewer protocol (read the stack map top-down, review your slice bottom-up), the five review-readiness tests, what to push back on (size, missing uses of new code, refactors mixed with behaviour, unfinished work reachable by customers), re-reviewing after a rebase, and reviewing the keystone. Can also be run on your own pull request before marking it ready. Do not use for: planning slices (use `planning-reviewable-slices`), building or merging a stack (use `building-and-landing-stacks`), or general code-quality review that has nothing to do with size or slicing.'
license: Elastic-2.0
metadata:
    author: canyongbs
---

# Reviewing Reviewable Slices

Review each slice on its own merits. Nobody reviews the whole stack: the author holds the whole feature in their head, the stack plan (and a senior's look at it) covers the whole-feature questions, and each slice's reviewer covers that slice. Different people reviewing different slices of one ticket is normal. The reasoning is in [Reviewable Slices](https://github.com/canyongbs/common/blob/main/docs/explanations/reviewable-slices.md).

## Review a slice

1. **Orient, top-down (two to five minutes).** Open the stack map, read the pull request titles and the stack plan in the bottom pull request. Do not read the diffs of slices above yours.
2. **Review, bottom-up.** Review your slice's diff against its description and the slice below it. Treat the slices below as reviewed and trusted unless something in your slice proves otherwise.
3. **Apply the five tests** from `planning-reviewable-slices`:
    1. **One idea:** the title needs no "and".
    2. **Self-explanatory:** the description and diff are enough; nothing depends on a later pull request to make sense.
    3. **Complete for its idea:** it has its own tests, and every new model, service, job or method is actually used in this slice. The exception is a foundation slice, or the lower part of a concept split because it was over budget: its new code may be used only by the slice directly above it, and its own tests must prove what it does.
    4. **Safe to land:** the application still works after it merges, migrations are tolerated by deployed code, **nothing unfinished is reachable by a customer**, and **nothing customers already use changes before its replacement is switched on**. Check the code, not the description: every new page, action, route, API field, notification and job trigger is unwired, or gated behind the feature's inactive flag **in this slice**, with tests for the flag on and off. Use the checklist in `hiding-unfinished-work`.
    5. **Sized for one sitting:** check the readable-lines comment on the pull request against the budget.
4. **Approve when the slice is right on its own.** Do not withhold approval because a slice above it does not exist yet; that is the stack working as intended.

## Push back on

- A description that says what code the slice contains ("adds the models") rather than what the product does after it merges.
- New models, services, jobs or methods with no caller in the slice (outside the foundation and split-concept exception), or "will be used in a later pull request".
- A refactor or mechanical change mixed with a behaviour change.
- A slice over 1,000 readable lines without a stated reason, or over 1,500 without serious justification. You may decline to review for size alone and ask for a split.
- Anything unfinished that a customer could reach, or a breaking change customers would see before it is switched on.
- A flag activated anywhere other than the keystone slice while the stack is still open.

## Re-review after a rebase

Approvals survive rebases that do not change a slice's diff. When a rebase does change it, the approval is dismissed and the author re-requests you. Review only GitHub's **changes since your last review**, which is usually small, and re-approve. Budget minutes, not another sitting.

## Review a stack plan

When asked to look over a plan before coding starts (Kevin Ullyott or Dan Harrin, ideally), answer:

- Are these the right concepts, and is each slice one of them?
- Is the foundation slice really shared, and is it used by the slice directly above it?
- Does the feature need a keystone, and is it last?
- Does every slice leave the application working, with nothing unfinished or breaking reachable?
- Is the landing mode right, and is a hold justified?

A comment is enough.

## Review the keystone slice

The keystone wires an entry point or activates a flag, and nothing more. Check:

- It makes available only what the slices below have already approved.
- The feature has been run end to end from the customer's entry point with the flag on. Run it yourself; do not just read it.
- The enable path (setting, permission, tenant) behaves correctly.
- The feature's cleanup task is now marked `ready: true`, and tests that activated the flag by hand rely on the activation migration instead.

## AI review

Copilot code review is requested on every pull request once its checks pass. Treat its comments as a checklist, not a verdict: resolve each with a reason (addressed, won't fix, incorrect). A bot comment is never an approval; a person approves every slice.

---

Related: `planning-reviewable-slices`, `building-and-landing-stacks`, `hiding-unfinished-work`.
