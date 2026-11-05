# Pull Request Checks

The checks that support [Reviewable Slices](../explanations/reviewable-slices.md). They are reusable GitHub Actions workflows in this repository, called by a small workflow in each application repository so that the rules and thresholds live in one place.

| Check                           | Blocks merging?    | Purpose                                                                                                |
| ------------------------------- | ------------------ | ------------------------------------------------------------------------------------------------------ |
| [Stack policy](#stack-policy)   | Yes, once required | A pull request targets `main`, `release/*`, or the slice below it in a GitHub stack, and nothing else. |
| [No hold label](#no-hold-label) | Yes, once required | A pull request with the `hold` label cannot merge, and neither can any slice stacked above it.         |

## Stack policy

**What it checks.**

1. A pull request whose base is **not** `main` or a `release/*` branch must be part of a GitHub stack, and that stack's bottom pull request must target `main` or `release/*`.
2. A pull request whose base **is** `main` or `release/*` must not contain any pull request that was merged into its branch as a plain pull request (outside a GitHub stack). That is the old pattern of chaining pull requests into each other's branches and merging them there, which bypasses `main`'s required reviews and checks. Release branches merging back into `main` (the hotfix flow) are not checked.

Rule 2 is what actually blocks. A plain pull request into a feature branch can always be merged (that branch has no protection), but the work it brings can no longer reach `main`.

**If it fails.**

- _"Not a stacked pull request"_: link the pull requests into a stack. On GitHub, use the **Create stack** banner on any pull request in the chain; from the command line, run `gh stack init <bottom-branch> <next-branch> …` then `gh stack submit --auto`. Then **re-run the check**: it reads the current state, so no new commit is needed.
- _"Stack is not based on main or release/\*"_: retarget the stack's bottom pull request to `main` (or the release branch), then re-run the check.
- _"Contains pull requests merged outside a stack"_: the listed pull requests reached this branch without `main`'s rules. Recreate the work as a GitHub stack, or as one pull request, and close this one.

**Merge queue.** The check passes immediately for merge queue runs; it has already been enforced on each pull request before it was queued.

## No hold label

**What it checks.** The pull request does not have the `hold` label.

**Why.** This is how a stack is held to land together (see `building-and-landing-stacks`). Merging a stacked pull request merges every unmerged pull request below it, and every pull request in a stack must pass `main`'s required checks, so `hold` on the bottom pull request stops the whole stack from merging. Reviews, reviewer assignment and CI carry on as normal.

**To release a hold,** remove the label. The check re-runs when labels change; if it does not, re-run it. A hold has no time limit.

**Merge queue.** The check passes immediately for merge queue runs.

## Enabling the checks in a repository

1. Add this workflow as `.github/workflows/pr-checks.yml`, pinning `common` to a commit SHA (Renovate or Dependabot keep the pin current):

    ```yaml
    name: PR Checks

    on:
        pull_request:
            types: [opened, reopened, synchronize, edited, labeled, unlabeled, ready_for_review]
        merge_group:
            types: [checks_requested]

    permissions: {}

    concurrency:
        group: ${{ github.workflow }}-${{ github.event.pull_request.number || github.ref }}
        cancel-in-progress: true

    jobs:
        policy:
            name: PR Policy
            uses: canyongbs/common/.github/workflows/pr-policy.yml@<commit-sha> # <tag>
            permissions:
                contents: read # compare commits
                pull-requests: read # read stacks and labels
    ```

2. Create the `hold` label (Settings → Labels, or `gh label create hold --description "Do not merge yet; lands together later" --color B60205`).
3. Open pull requests and confirm the checks behave as described above before making them required.
4. Make **`PR Policy / Stack policy`** and **`PR Policy / No hold label`** required status checks for `main` (in classic branch protection or the `main` ruleset) and in the `release/*` ruleset. GitHub evaluates every pull request in a stack against the rules of the stack's base branch, so requiring them there covers every slice of every stack.

Every repository with a merge queue must keep `merge_group` in the workflow's triggers, or required checks will never report for queued pull requests and the queue will stall.
