---
name: building-and-landing-stacks
description: 'Use when building, opening, updating, rebasing, holding, merging or cancelling a stack of pull requests for planned slices; when running `gh stack` commands in a Canyon GBS repository; when opening a pull request for a slice or writing its description; when a pull request fails the "Stack policy" or "No hold label" check; when adding or removing the `hold` label; or when an approval was dismissed after a rebase. Covers our conventions on top of the generic `gh-stack` skill: draft-first pull requests, branch and title naming, describing a slice, responding to review in the right layer, approvals after rebases, landing as approved versus holding, merging bottom-up through the merge queue, hotfix stacks on `release/*`, and cancelling part-way. Do not use for: deciding how to split work (use `planning-reviewable-slices`), keeping unfinished work from customers (use `hiding-unfinished-work`), or reviewing a slice (use `reviewing-reviewable-slices`).'
license: Elastic-2.0
metadata:
    author: canyongbs
---

# Building and Landing Stacks

A **stack** is a chain of pull requests where each targets the branch of the one below it and the bottom one targets `main` (or `release/*` for a hotfix). Use one whenever a planned slice builds on another slice that has not merged yet. Plan the slices first with `planning-reviewable-slices`. The reasoning is in [Reviewable Slices](https://github.com/canyongbs/common/blob/main/docs/explanations/reviewable-slices.md).

**These conventions take precedence over the generic `gh-stack` skill** wherever they differ. In particular, a layer is a reviewable slice (usually a whole concept), not a technical layer such as "models" or "API".

## Rules that are enforced

- **Every pull request passes all CI on its own.** GitHub evaluates every pull request in a stack against the required checks and reviews of the stack's base branch: `main`, or the `release/*` branch for a hotfix stack. Run the repository's formatting, static analysis and relevant tests on **each** slice before submitting it, not only on the top one.
- **A pull request may only target `main`, `release/*`, or the slice below it in a GitHub stack**, and the stack's bottom must target `main` or `release/*`. Never open a plain pull request against someone else's branch. The required **Stack policy** check fails otherwise; see [Pull Request Checks](https://github.com/canyongbs/common/blob/main/docs/references/pr-checks.md).

## Build bottom-up

Set up once per machine: `gh extension install github/gh-stack` and `git config rerere.enabled true`.

```bash
gh stack init ADVAPP-3100-1        # first slice, branched from main
# …build and commit the first slice…
gh stack add ADVAPP-3100-2         # next slice, branched from the current one
# …build and commit…
gh stack submit --auto             # push every branch and open DRAFT pull requests, linked as a stack
```

- **Branch names:** `<KEY-N>-<position>` (optionally with a short slug) is recommended so a stack's branches sort together. It is not required.
- **Hotfixes:** a stack for a hotfix uses the release branch as its trunk: `gh stack init --base release/1.2.3 <branch>`.
- **Stage deliberately.** `git add` the files that belong to the current slice; do not sweep in everything with `-A`. Every commit in a branch serves that slice.
- **Open each pull request as soon as its slice is ready.** You do not need to finish the whole stack first.
- **Already opened plain pull requests that chain?** Link them into a stack with the "Create stack" banner on GitHub, or adopt the branches with `gh stack init <bottom> <next> …` and run `gh stack submit --auto`. Then re-run the Stack policy check.

## Draft first, then ready for review

1. `gh stack submit --auto` opens every new pull request as a **draft** (for a single pull request, use `gh pr create --draft`). CI and Copilot code review run on drafts, so you see their results before involving a person.
2. When CI is green and you are happy with the slice, mark it ready (`gh pr ready <number>`). That triggers reviewer auto-assignment.
3. **As an agent, never mark a pull request ready for review unless the user asks you to.** Leave that to the author.

## Title and description

- **Title:** `[KEY-N]: <what this slice does>`, for example `[ADVAPP-3100]: Send pre-meeting reminder emails`. Use `[Chore]: …` or `[Bugfix]: …` when there is no ticket. Do not number slices in the title (the stack map shows the order), and never use "and".
- **Description:** fill in the repository's pull request template. In the technical description, cover **only this slice**:
    1. What the product does after this slice merges.
    2. What a reviewer should pay attention to.
    3. How it was tested.
    4. The ticket's acceptance criteria this slice covers, where the ticket has any.
    5. If it is over 1,000 readable lines, why it is not split.
- **Bottom pull request only:** also include the stack plan from `planning-reviewable-slices`.
- **Cleanup tasks:** list any cleanup task the slice adds or changes, and whether it is marked `ready` (see `managing-cleanup-tasks`).

## Respond to review in the right slice

Make each change in the slice it belongs to, then carry it upward:

```bash
gh stack checkout ADVAPP-3100-1    # the slice that owns the change
git add … && git commit -m "…"
gh stack rebase --upstack          # replay every slice above onto the change
gh stack push
gh stack top
```

- **Approvals survive rebases that do not change a slice's diff.** A rebase that does change a slice's diff dismisses its approval. Re-request the person who approved it before (`gh pr edit <number> --add-reviewer <login>`); they review only GitHub's "changes since your last review".
- **Review bottom-up.** Ask for review of the bottom slice first, because feedback there can change the slices above it; upper slices can be reviewed in parallel. When landing as approved, merge each lower slice as soon as it is approved. When holding, get every slice approved, then remove the label.
- When `main` moves ahead, use **Rebase stack** on GitHub or `gh stack sync`. After a lower slice merges, GitHub rebases and retargets the rest automatically; run `gh stack sync --prune` locally.

## Landing

Choose the landing mode in the plan.

**Land as approved (the default).** Merge each slice as soon as it is approved and green. Merging is bottom-up: merging a slice merges every unmerged slice below it, usually through the merge queue as one group. Use the merge button on the pull request or `gh stack merge <number> --yes`. **Never use `gh pr merge` on a stacked pull request**; it cannot merge a stack.

**Hold and land together.** Use it only for timing: when product is still deciding, or when a set of slices should be signed off as a whole before any of it lands. State the reason in the plan. A hold is never a safety mechanism. If a slice is ejected from the merge queue, the slices below it can still land without it, so every slice must be safe on `main` by itself.

```bash
gh pr edit <bottom-pr-number> --add-label hold      # nothing in the stack can merge
gh pr edit <bottom-pr-number> --remove-label hold   # release it
```

- While `hold` is on a pull request, the required **No hold label** check fails, so it cannot merge, and neither can any slice above it. Reviews and CI carry on as normal.
- A hold has no time limit and comes off only when someone removes the label.
- To hold only part of a stack, put `hold` on the lowest slice that must wait; the slices below it can still land.
- Holding costs rebases: every time `main` moves, the stack needs **Rebase stack** before it can merge.

## If the ticket is cancelled part-way

- With **hold**, close the stack's pull requests.
- With **land as approved**, some slices have already merged. Nothing unfinished is reachable, so there is no emergency, but merged slices may already be in use: finished behaviour customers can reach, or a foundation another ticket builds on. Before reverting, check what customers can reach and what other work uses each merged slice. Keep those, and revert only the unused, unreachable parts, each in its own pull request, newest first. For schema, remove the code that reads a table or column first, and drop it in a later release. Prefer a small cleanup ticket so the removal is tracked.
- If product may revive the feature, merged slices can stay dormant behind the inactive flag, provided someone owns the decision to remove them later. Say so on the ticket.

## Do / Don't

- **Do** open stacked pull requests as drafts with `gh stack submit --auto`.
- **Do** run the repository's checks on every slice.
- **Do** put the stack plan in the bottom pull request.
- **Don't** target another branch with a plain pull request.
- **Don't** merge a slice from the top with `gh pr merge`, or merge out of order.
- **Don't** mark a pull request ready for review on the author's behalf.

---

Related: `planning-reviewable-slices`, `hiding-unfinished-work`, `reviewing-reviewable-slices`, `managing-cleanup-tasks`.
