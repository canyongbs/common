---
status: 'accepted'
date: 2026-05-26
decision-makers: Kevin Ullyott, Dan Harrin
consulted: Joseph Licata
---

# How to Display Dates and Times Consistently Across the Application

## Context and Problem Statement

The codebase had accumulated many different datetime format strings across Filament tables, infolists, Blade views, notifications, and exporters. Formats varied widely — `m/d/Y H:i:s`, `g:ia - M j, Y`, `d-m-Y`, `M d, Y h:i A`, `l, F j, Y \a\t g:i A`, `Y-m-d H:i:s`, and more — making the UI inconsistent and harder to maintain. There was no single source of truth for how dates and times should be presented to users.

## Considered Options

- Centralised format defaults in `FilamentServiceProvider` with a standardised format
- Per-component format strings defined at each usage site
- A configuration file or constant that components reference

## Decision Outcome

Chosen option: "Centralised format defaults in `FilamentServiceProvider`", because it allows the entire application's datetime display to be controlled from a single location, components automatically inherit the correct format without developers needing to remember it, and Filament's `configureUsing` API is purpose-built for this pattern.

The standardised formats are:

| Context | Format string | Example output |
|---------|--------------|----------------|
| Date only | `M j, Y` | May 5, 2026 |
| Time only | `g:i a` | 2:30 pm |
| Time with seconds | `g:i:s a` | 2:30:00 pm |
| DateTime | `M j, Y g:i a (T)` | May 5, 2026 2:30 pm (UTC) |
| DateTime with seconds | `M j, Y g:i:s a (T)` | May 5, 2026 2:30:00 pm (UTC) |
| DateTime (tables/infolists) | Date on first line, time + timezone on second line | Rendered as HTML with `.fi-datetime-description` styling |
| DateTimePicker display | `M j, Y g:i a` | May 5, 2026 2:30 pm |
| DateTimePicker with seconds | `M j, Y g:i:s a` | May 5, 2026 2:30:00 pm |

Key formatting rules:

- Use `j` (day without leading zero), not `d`
- Use `M` (abbreviated month name), not `m` (numeric) or `F` (full name)
- Use `g` (12-hour without leading zero) with lowercase `a` (am/pm), not `H` (24-hour) or `A` (uppercase)
- Always include a space before `a` — use `g:i a`, not `g:ia`
- Include the timezone abbreviation `(T)` in datetime displays outside of DateTimePickers (pickers show a timezone hint icon instead)
- When the datetime is displayed standalone (not inline within a sentence), render the date and time on separate lines. When the datetime is part of a sentence or inline context, keep it on a single line
- Only deviate from these formats if explicitly asked to in a request. Do not invent custom formats for individual components

### Consequences

- Good, because users see a consistent format everywhere, reducing cognitive load
- Good, because changing the format project-wide requires editing one location rather than 70+ files
- Good, because including `(T)` makes the timezone unambiguous for users
- Good, because the two-line table format (date above, time below) is easier to scan in narrow columns
- Good, because developers no longer need to choose or remember a format — calling `->dateTime()` or `->date()` with no arguments does the right thing
- Bad, because the HTML datetime format in tables requires `TextColumn` to render as HTML, which is set automatically but may surprise developers unfamiliar with the pattern
- Neutral, because manual `->format()` calls in Blade views and notifications still require the developer to use the correct format string

### Confirmation

Compliance with this ADR can be confirmed through:

1. **Code review**: Verify that `->dateTime()` and `->date()` calls in tables and infolists do not pass custom format arguments unless there is a genuinely unique display requirement
2. **Search for format strings**: Grep for common incorrect patterns (`m/d/Y`, `d-m-Y`, `M d,`, `H:i`, `g:iA`, `g:ia` without a space) to catch regressions
3. **Visual inspection**: Spot-check tables and detail pages to confirm dates render in the expected format with timezone

## Pros and Cons of the Options

### Centralised Format Defaults in `FilamentServiceProvider`

Set `defaultDateTimeDisplayFormat`, `defaultTimeDisplayFormat`, and related defaults globally via `Table::configureUsing()`, `Schema::configureUsing()`, and `DateTimePicker::configureUsing()`.

- Good, because one change propagates everywhere automatically
- Good, because components use `->dateTime()` / `->date()` with no arguments, making code cleaner
- Good, because Filament's API explicitly supports this pattern
- Bad, because developers must know not to pass custom format arguments unless intentional
- Bad, because the HTML datetime format is non-obvious when reading the service provider

### Per-Component Format Strings

Define the format string at each usage site, e.g. `->dateTime('M j, Y g:i a (T)')`.

- Good, because each component is self-documenting
- Good, because no global configuration to understand
- Bad, because format strings drift over time as developers copy-paste different variations
- Bad, because changing the format requires a project-wide find-and-replace
- Bad, because there is no enforcement mechanism

### Configuration File or Constant

Define formats in a config file or class constant and reference them everywhere.

- Good, because format is defined once
- Good, because it's explicit at each usage site what constant is being used
- Bad, because developers still need to remember to reference the constant
- Bad, because it doesn't leverage Filament's built-in default mechanism, adding unnecessary boilerplate
- Bad, because it doesn't help with `->dateTime()` calls that should inherit a default

## More Information

### Implementation Notes

1. Defaults are set in `FilamentServiceProvider::boot()` using `->defaultDateTimeDisplayFormat()` and `->defaultTimeDisplayFormat()`.

2. In tables and infolists, `TextColumn` / `TextEntry` is automatically set to `->html()` when `isDateTime()` is true, enabling the two-line date/time rendering.

3. The `.fi-datetime-description` CSS class styles the time line in muted gray (`text-gray-500 dark:text-gray-400`).

4. When computing state via `->getStateUsing()`, return `CarbonInterface|null` rather than a pre-formatted string. Use `->placeholder()` for fallback text (e.g., "N/A", "Not Sent") instead of returning a string from the state closure.

5. Remove `->displayFormat()` from individual DateTimePickers — they inherit the project default.

### References

- [ADVAPP-2650](https://canyongbs.atlassian.net/browse/ADVAPP-2650)
- [AIDAPP-1151](https://canyongbs.atlassian.net/browse/AIDAPP-1151)
- [OLYMPUS-1330](https://canyongbs.atlassian.net/browse/OLYMPUS-1330)
