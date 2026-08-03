@php
    /** @var \Laravel\Boost\Install\GuidelineAssist $assist */
@endphp

## Pest

- This project uses Pest for all tests.
- Run tests: `{{ $assist->artisanCommand('test --compact') }}` or filter: `{{ $assist->artisanCommand('test --compact --filter=testName') }}`.
- Do NOT delete tests without approval.
@if ($assist->hasSkillsEnabled())
- When writing, editing, or fixing tests, follow the `writing-tests` skill for file placement, structure, and conventions.
@endif
