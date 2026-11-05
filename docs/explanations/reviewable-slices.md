# Reviewable Slices: How We Split, Review and Land Large Work

Most of our changes are small enough to review in one pull request, and they should stay that way. This document is about the ones that are not: the feature that would otherwise arrive as a single 3,000-line pull request. We deliver that work as a sequence of small, individually understandable pull requests, called **reviewable slices**, each reviewed on its own and stacked on GitHub when one builds on another. The feature is still one ticket and customers still see it all at once. Only the unit of review changes.

This page explains **why** we work this way and the ideas behind it. The rules themselves are written as agent skills that are meant to be read by people too, so there is one source of truth for each:

| When you are…                                                    | Read                                                                                                                                                              |
| ---------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Deciding whether a ticket needs slicing, and planning the slices | [`planning-reviewable-slices`](../../.ai/skills/planning-reviewable-slices/SKILL.md)                                                                              |
| Building the stack, opening the pull requests and landing them   | [`building-and-landing-stacks`](../../.ai/skills/building-and-landing-stacks/SKILL.md)                                                                            |
| Reviewing a slice                                                | [`reviewing-reviewable-slices`](../../.ai/skills/reviewing-reviewable-slices/SKILL.md)                                                                            |
| Keeping unfinished work away from customers while it lands       | [Landing Work Customers Can't See Yet](./landing-work-customers-cant-see-yet.md) and [`hiding-unfinished-work`](../../.ai/skills/hiding-unfinished-work/SKILL.md) |

**Required reading:** Google's [Small CLs](https://google.github.io/eng-practices/review/developer/small-cls.html) (ten minutes). Most of what follows builds on it.

## Why we split work

Code review is how two people arrive at a shared understanding of a change. The author built that understanding over days, one piece at a time; the reviewer meets the whole thing cold. Working memory is finite for everyone, and a large change holds more than one person can carry at once. Splitting the work gives the reviewer the same path the author took, so the understanding is genuinely shared rather than approximated.

**What the evidence says.**

- The most-cited study of code review, [SmartBear's analysis of reviews at Cisco](https://tekin.co.uk/2020/05/proof-your-thousand-line-pull-requests-create-more-bugs), found that the rate at which reviewers find defects falls off once a review grows past roughly **200 to 400 lines**, and again after about **60 to 90 minutes** of continuous reviewing. The dataset was small and the exact thresholds have been [fairly criticised](https://blog.wesleyac.com/posts/bogus-code-review-data), but the direction is not in dispute.
- [Google's data](https://research.google/pubs/modern-code-review-a-case-study-at-google/) agrees: across 9 million reviewed changes, the median change was about 24 lines and roughly 90% touched fewer than 10 files.
- [Microsoft Research](https://microsoft.com/en-us/research/?p=164195) found that **understanding** the change is the central act of reviewing. Catching bugs, suggesting alternatives and spreading knowledge all depend on the reviewer first building an accurate model of what changed and why. A 9,000-line pull request cannot be modelled, only skimmed. It gets approved because it looks plausible, and the team gains none of the knowledge transfer that is review's main outcome.

**Why it matters more now.** AI assistance has made producing lines cheap while leaving the cost of understanding them unchanged. [DORA's 2025 research](https://newsletter.getdx.com/p/doras-2025-research-on-the-impact) lists working in small batches as one of the capabilities that decide whether AI helps or hurts a team, and found code review becoming the bottleneck where batches grew. GitHub [shipped native stacked pull requests](https://github.blog/changelog/2026-10-06-stacked-pull-requests-generally-available/) in 2026 for the same reason.

## Not every change needs slicing

Most tickets fit comfortably in one pull request, and a single pull request is always the default. Slicing is a tool for work that would otherwise be too big to review well. It is decided in one of two ways:

- **Up front**, during sprint or tech planning, or when an author (or their agent) starts a ticket and can see it will be large.
- **Part-way through**, when work in progress turns out bigger than expected. Stop, plan the remaining slices, and split what you have before it grows further.

The signals that a ticket needs slicing, and how to plan it, are in [`planning-reviewable-slices`](../../.ai/skills/planning-reviewable-slices/SKILL.md).

## What a reviewable slice is

A reviewable slice is one self-contained change that a reviewer can fully understand in a single sitting, using only the pull request, its description, the existing codebase, the slices already reviewed below it and, usually, the ticket. That is [Google's definition of "small"](https://google.github.io/eng-practices/review/developer/small-cls.html#what-is-small): it is about concepts, not line counts. We use line counts as a proxy because concepts are hard to measure.

There are three kinds of slice:

- A **concept slice** is one complete behaviour carried through all its layers: its data, logic, job, notification, policy, user interface and tests. Almost every slice is one of these.
- A **foundation slice** is shared groundwork (a migration, a base model, a factory, a flag class) that two or more concept slices depend on, shipped first and used immediately by the slice above it.
- A **keystone slice** is the slice that makes the feature available to customers, by wiring up its entry point or activating its feature flag. It is always last, and not every feature needs one.

A slice is not a commit (a slice may have several commits), not a ticket (a ticket may have many slices), and not a file type ("all the migrations" is a layer, not a slice).

**Nothing unfinished reaches a customer.** A slice may include customer-facing surface, such as a Filament page or an action, as long as whatever customers can reach is finished and whatever is not finished is unreachable. That lets each slice merge as soon as it is approved. [Landing Work Customers Can't See Yet](./landing-work-customers-cant-see-yet.md) covers how.

## Slice by concept, not by layer

Split a feature into complete, encapsulated concepts, not technical layers. [Google calls this splitting vertically](https://google.github.io/eng-practices/review/developer/small-cls.html#splitting).

**A reviewer can judge a concept.** "The pre-meeting reminder email" comes with a clear question: does this send the right email to the right people at the right time, and do the tests prove it? A layer has no such question. "All the new models" asks the reviewer to imagine the code that will use them, which is exactly the context they do not have. Google makes the same point from the author's side:

> If you add a new API, you should include a usage of the API in the same CL so that reviewers can better understand how the API will be used. This also prevents checking in unused APIs.
>
> — [Google Engineering Practices: Small CLs](https://google.github.io/eng-practices/review/developer/small-cls.html#what-is-small) (CC-BY 3.0)

**A concept lands; a layer waits.** A concept slice is a working increment. Its tests pass on their own and it can merge, unreachable by customers, as soon as it is approved. A horizontal layer is inert until the layers above it exist, so it tends to sit unmerged.

**When layers make sense.** Horizontal slices are the exception, for two cases: shared foundation that several concepts depend on, and a single concept that is still too big after slicing. Google's grid is how we decide. Put the layers down the side and the concepts across the top; every cell is a candidate slice. Slicing by concept merges a whole column into one slice when it fits the budget. Slicing horizontally ships a row on its own.

| Layer                                     | Shared                                                | Pre-meeting reminder | Post-meeting follow-up |
| ----------------------------------------- | ----------------------------------------------------- | -------------------- | ---------------------- |
| Keystone (makes it customer-available)    | Migration that activates the feature's flag (slice 4) |                      |                        |
| Filament UI, gated behind the flag        |                                                       | in slice 2           | in slice 3             |
| Job, mailable, audit record               |                                                       | in slice 2           | in slice 3             |
| Scheduling and business rules             |                                                       | in slice 2           | in slice 3             |
| Shared foundation: migration, model, flag | slice 1                                               |                      |                        |

Four slices: foundation, reminder, follow-up, keystone. If the reminder column alone came out over budget, it would split into two cells (rules first, then job, mail and UI), and nothing else would change. The decision rules and more examples are in [`planning-reviewable-slices`](../../.ai/skills/planning-reviewable-slices/SKILL.md).

## Our size budget, and why

We count **readable lines**: the lines a reviewer actually has to read. Lockfiles, generated files, snapshots, baselines, images, pure renames and our copyright headers do not count; a deleted file counts as one line. A comment on every pull request estimates the number (see [Pull Request Checks](../references/pr-checks.md)).

| Readable lines | Verdict                                                                                       |
| -------------- | --------------------------------------------------------------------------------------------- |
| Up to 500      | 🟢 **Target.** The research's sweet spot is 300 to 500.                                       |
| 500 to 1,000   | 🟡 **Sometimes acceptable.** Fine if it is still one concept; ask whether a split is clearer. |
| 1,000 to 1,500 | 🟠 **Large.** Heavily discouraged; the description must say why it is not split.              |
| Over 1,500     | 🔴 **Too large.** Needs an explanation and serious thought about splitting.                   |

The target follows the research rather than stretching it: SmartBear's defect-detection curve flattens between 200 and 400 lines, and we give a little room on top because our slices carry whole concepts through a boilerplate-heavy stack. Above 1,000 lines the cost of a split is almost always lower than the cost of a review nobody can finish with full attention.

Two more things affect size. **Files:** Google's data shows about 90% of changes touch fewer than 10 files; a 200-line change across 40 files is harder to review than 300 lines in 4, because each file is a context switch. **Time:** a slice is the right size when a reviewer can finish it, with full attention, in one sitting. There is no minimum: [no change is too small](https://google.github.io/eng-practices/review/developer/small-cls.html), and a stack of many tiny slices is fine.

Large is acceptable when the change is mechanical: deleting whole files, or a change generated by a tool you trust completely (a framework upgrade, a codemod), where the reviewer only confirms the intent. Keep those separate from hand-written changes.

## Stacks on GitHub

When one slice builds on another that has not merged yet, the slices form a **stack**: a chain of pull requests where each targets the branch of the one below it, and the bottom one targets `main` (or a `release/*` branch for a hotfix). GitHub's [native stacked pull requests](https://docs.github.com/en/pull-requests/get-started/about-stacked-prs) show each reviewer only their slice's diff, show a map of the whole stack, keep the branches rebased, and merge from the bottom up.

Two rules have no exceptions:

1. **Every pull request passes all CI on its own.** GitHub evaluates every pull request in a stack against the rules of the stack's base, normally `main`: the same required reviews, required checks and code owners as any other pull request. A slice that only goes green with the slice above it is not a slice.
2. **A pull request that builds on another unmerged branch must be a GitHub stacked pull request**, never a plain pull request targeting someone's feature branch. The only other allowed bases are `main` and `release/*`. A required check enforces this (see [Pull Request Checks](../references/pr-checks.md)).

Some useful properties of GitHub stacks:

- **Merging is bottom-up and atomic.** Merging a slice merges every unmerged slice below it, in one operation, through the merge queue as a single group. A slice cannot be merged out of order.
- **Approvals survive rebases that do not change a slice's diff.** We keep "dismiss stale approvals" on. When `main` moves or a lower slice merges, GitHub [rebases the stack and keeps approvals](https://docs.github.com/en/pull-requests/reference/stacked-pull-requests#rebasing) on every slice whose diff is unchanged. Only a rebase that actually changes a slice's diff, typically after review feedback on a lower slice, dismisses that slice's approval.
- **CI runs for every slice** as if it targeted `main`, so a stack of five slices runs CI five times. That is the cost of every slice passing on its own.

## Landing modes

Each stack picks one of two ways to land.

- **Land as approved (the default).** Every slice merges as soon as it is approved and green. Lower slices are unreachable by customers until the keystone slice, if there is one, makes the feature available. This is [how Meta, Google and Shopify run stacks](https://gearflow.com/blog/making-a-version-of-stacked-diffs-work-for-a-github-org): the stack exists only during review, and `main` integrates continuously.
- **Hold and land together.** For when an intermediate state would be unsafe on `main` even though customers cannot reach it, or when product is still deciding. Put the `hold` label on the bottom slice. A required check fails while the label is present, and since merging any slice merges everything below it, nothing in the stack can merge. Reviews and CI carry on as normal. The hold has no time limit and comes off only when someone removes the label.

We use a label rather than draft status for holds because "draft" already means something else in our flow: _not ready for review yet_. A draft is not auto-assigned a reviewer; a held pull request should be. The label says exactly one thing, is visible in the pull request list, and is enforced by the same workflow as the stack rule.

## Unblocking other tickets

Slicing also helps when one ticket blocks another. During sprint and tech planning, and while working, look for tickets that depend on part of another ticket's work, and plan so the dependency lands first:

1. The author of the **blocking** ticket puts the slice the other ticket needs at the **bottom** of their stack, and gets it opened, reviewed and merged early, before the rest of their feature.
2. Meanwhile, the author of the **blocked** ticket works on the parts of their ticket that do not depend on it.
3. As soon as that slice merges, the blocked ticket continues from `main` as normal.

**An example.** In Aiding App, AIDAPP-1638 replaced deleting contacts with **archiving** them: a new archived state on the contact model, plus a dozen behaviours built on it (hiding archived contacts from lists and pickers, restoring them when re-created, stopping archived contacts from signing in, and so on). AIDAPP-1639 added full contact management to the REST API, and those API endpoints needed to know about archiving. The API ticket was blocked until archiving existed.

The API work did not need all of archiving. It needed the foundation: the archived state on the model, and how to read it. Planned with this in mind, that foundation is the first slice of the archiving stack, reviewed and merged within a day or two. While it is in review, the API author builds the endpoints that do not touch archiving (listing, creating and updating contacts, request validation, documentation). Once the foundation is on `main`, they add the archive-aware behaviour behind the same flag. Neither ticket waits for the other to finish.

## Who reviews what

- **The author holds the whole feature in their head; reviewers do not.** Each slice is reviewed on its own merits by whoever GitHub's auto-assignment picks, and different people reviewing different slices of one ticket is normal. That is how [Google](https://google.github.io/eng-practices/review/developer/small-cls.html#splitting) and [Meta](https://newsletter.pragmaticengineer.com/p/stacked-diffs) work, and it is what makes a stack cheaper to review than a monolith rather than the same work in more tabs.
- **A senior look at the plan is heavily recommended** for every stack: Kevin Ullyott or Dan Harrin reads the slice plan before coding starts. It is the cheapest moment to catch a wrong boundary: twenty minutes instead of an afternoon.
- **The keystone slice** is small by construction, and is where the feature is run end to end before customers get it.
- **Copilot code review** is requested automatically once a pull request's checks pass, including on drafts, so authors see its comments before asking a person to review. A bot comment is never an approval; a person approves every slice.

## Breaking work down before coding

> **Placeholder — not yet written.** This section will describe how to break a ticket down into slices before writing code, with or without AI assistance. Until it is written, plan with the grid above and [`planning-reviewable-slices`](../../.ai/skills/planning-reviewable-slices/SKILL.md).

## References

| Source                                                                                                                                                                                                                                                                                                                                                                         | What it supports                                                                                       |
| ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------ |
| [Google Engineering Practices: Small CLs](https://google.github.io/eng-practices/review/developer/small-cls.html) (CC-BY 3.0)                                                                                                                                                                                                                                                  | What "small" means, including a use of every new API, splitting vertically and horizontally, the grid. |
| [Sadowski et al., Modern Code Review: A Case Study at Google](https://research.google/pubs/modern-code-review-a-case-study-at-google/)                                                                                                                                                                                                                                         | Median change of about 24 lines; about 90% under 10 files.                                             |
| [Bacchelli and Bird, Expectations, Outcomes, and Challenges of Modern Code Review](https://microsoft.com/en-us/research/?p=164195)                                                                                                                                                                                                                                             | Understanding is the core act of review; knowledge transfer is its main outcome.                       |
| [Tekin Suleyman: Proof your thousand-line pull requests create more bugs](https://tekin.co.uk/2020/05/proof-your-thousand-line-pull-requests-create-more-bugs) and [Wesley Aptekar-Cassels: A bogus study on code review](https://blog.wesleyac.com/posts/bogus-code-review-data)                                                                                              | The SmartBear figures behind 200 to 400 lines and 60 to 90 minutes, and why they are a heuristic.      |
| [DORA 2025, via DX](https://newsletter.getdx.com/p/doras-2025-research-on-the-impact) and [DORA: Trunk-based development](https://dora.dev/capabilities/trunk-based-development/)                                                                                                                                                                                              | Small batches as a core capability; review as the bottleneck under AI.                                 |
| [Martin Fowler: Patterns for Managing Source Code Branches](https://martinfowler.com/articles/branching-patterns.html)                                                                                                                                                                                                                                                         | Integration frequency as the reason to land early.                                                     |
| [GitHub Docs: About stacked pull requests](https://docs.github.com/en/pull-requests/get-started/about-stacked-prs), [Stacked pull requests reference](https://docs.github.com/en/pull-requests/reference/stacked-pull-requests), [Merging stacked pull requests](https://docs.github.com/en/pull-requests/how-tos/merge-and-close-pull-requests/merging-stacked-pull-requests) | Rules evaluated against the stack base, bottom-up atomic merges, approvals kept on unchanged rebases.  |
| [GitHub Changelog: Stacked pull requests generally available](https://github.blog/changelog/2026-10-06-stacked-pull-requests-generally-available/)                                                                                                                                                                                                                             | Approvals preserved on rebase; stacks entering the merge queue as one group.                           |
| [Gergely Orosz: Stacked Diffs](https://newsletter.pragmaticengineer.com/p/stacked-diffs) and [Gearflow: Making a version of stacked diffs work for a GitHub org](https://gearflow.com/blog/making-a-version-of-stacked-diffs-work-for-a-github-org)                                                                                                                            | How Meta, Google and Shopify review and land stacks.                                                   |
