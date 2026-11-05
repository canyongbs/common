@php
    /** @var \Laravel\Boost\Install\GuidelineAssist $assist */
@endphp

## Reviewable Slices: Pull Request Size and Stacks

A pull request should be small enough for a reviewer to understand fully in one sitting. Most tickets fit in **one** pull request, and that is the default. When a ticket will be, or has become, too large, deliver it as a sequence of small pull requests ("reviewable slices"), stacked on GitHub when one builds on another.

- **Decide when to slice:** up front, when the work will clearly be large, or part-way through, as soon as work in progress grows past the budget. Stop and plan the remaining slices rather than continuing to grow one branch.
- **Size budget, in readable lines** (lockfiles, generated files, snapshots, images, pure renames and copyright headers excluded): up to 500 is the target; 500–1,000 is sometimes acceptable; 1,000–1,500 is large and needs a stated reason; over 1,500 is too large and needs an explanation and serious thought about splitting.
- **Slice by concept, not by layer.** Each slice is one complete behaviour with its tests, and includes a real use of every new class or method it adds. Never mix a refactor with a behaviour change.
- **Every pull request passes all CI on its own.** A pull request that builds on another unmerged branch must be a **GitHub stacked pull request** whose bottom targets `main` or `release/*`; never target someone's feature branch with a plain pull request.
- **Nothing unfinished may reach a customer.** Leave it unwired, or gate it behind an inactive feature flag.
- **Open pull requests as drafts.** The author marks them ready for review once CI is green, which triggers reviewer assignment.
- **Describe only this slice:** what the product does after it merges, what a reviewer should pay attention to, and how it was tested. The bottom pull request of a stack also carries the stack plan.

@if ($assist->hasSkillsEnabled())
Follow `planning-reviewable-slices` to decide and plan slices, `building-and-landing-stacks` to build and open a stack, `hiding-unfinished-work` to keep unfinished work from customers, and `reviewing-reviewable-slices` when reviewing. The reasoning is in [Reviewable Slices](https://github.com/canyongbs/common/blob/main/docs/explanations/reviewable-slices.md).
@else
The reasoning and full process are in [Reviewable Slices](https://github.com/canyongbs/common/blob/main/docs/explanations/reviewable-slices.md).
@endif
