@php
    /** @var \Laravel\Boost\Install\GuidelineAssist $assist */
@endphp

## Code Style & Static Analysis

Before considering any change complete, make it pass the project's automated checks — do not leave style or analysis violations for CI:

- Apply formatting fixes with `{{ $assist->composerCommand('format') }}`.
- Then run `{{ $assist->composerCommand('lint') }}` (PHPStan/Larastan static analysis) and resolve **everything** it reports.

CI runs `{{ $assist->composerCommand('checks') }}` (`format-dryrun` plus `lint`); running `format` then `lint` locally satisfies it without re-checking the formatting you just wrote.

@if ($assist->hasSkillsEnabled())
See the `code-style-and-static-analysis` skill for the tools involved and how to resolve common failures.
@endif
