# Upstream: `gh-stack` skill

This skill is copied from GitHub's [`github/gh-stack`](https://github.com/github/gh-stack) repository, so every Canyon GBS repository and agent gets it through `common:publish` without each developer installing it.

| | |
| --- | --- |
| Source | [`skills/gh-stack`](https://github.com/github/gh-stack/tree/bd0a32504f9ae9f795875a9e6bb90ff7408fda08/skills/gh-stack) |
| Commit | `bd0a32504f9ae9f795875a9e6bb90ff7408fda08` |
| Skill version | `0.2.0` (from its frontmatter) |
| Licence | MIT, Copyright GitHub, Inc. (see [LICENSE](LICENSE)) |

## Local changes

- `references/stack-design.md` is **replaced** with a pointer to our `planning-reviewable-slices` and `building-and-landing-stacks` skills. Upstream plans stacks by technical layer; we slice by concept.

Every other file is unchanged. The skill keeps its own frontmatter, so it is exempt from our skill convention tests (`license: Elastic-2.0`, `author: canyongbs`) but still checked against the Agent Skills specification.

## Updating

1. Copy `skills/gh-stack/` from the new upstream commit over this folder.
2. Restore the replaced `references/stack-design.md` above and re-check that `SKILL.md` still points to it.
3. Update the commit and version in this file, and review the upstream diff like any other change.
