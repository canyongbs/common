# Designing a stack

> **Replaced in Canyon GBS repositories.** The upstream version of this file plans a stack by technical layer (models, then API, then frontend, then integration tests). We slice by **concept** instead: each slice is one complete behaviour with its own tests, and a slice with nothing that uses it yet is not reviewable.

Before running `gh stack init`, plan the slices with the `planning-reviewable-slices` skill. Build, open and land the stack following the `building-and-landing-stacks` skill, whose conventions take precedence over this skill wherever they differ.

The reasoning is in [Reviewable Slices](https://github.com/canyongbs/common/blob/main/docs/explanations/reviewable-slices.md).
