<?php

/*
<COPYRIGHT>

    Copyright © 2016-2026, Canyon GBS LLC. All rights reserved.

    Canyon GBS Common is licensed under the Elastic License 2.0. For more details,
    see https://github.com/canyongbs/common/blob/main/LICENSE.

    Notice:

    - You may not provide the software to third parties as a hosted or managed
      service, where the service provides users with access to any substantial set of
      the features or functionality of the software.
    - You may not move, change, disable, or circumvent the license key functionality
      in the software, and you may not remove or obscure any functionality in the
      software that is protected by the license key.
    - You may not alter, remove, or obscure any licensing, copyright, or other notices
      of the licensor in the software. Any use of the licensor’s trademarks is subject
      to applicable law.
    - Canyon GBS LLC respects the intellectual property rights of others and expects the
      same in return. Canyon GBS™ and Canyon GBS Common are registered trademarks of
      Canyon GBS LLC, and we are committed to enforcing and protecting our trademarks
      vigorously.
    - The software solution, including services, infrastructure, and code, is offered as a
      Software as a Service (SaaS) by Canyon GBS LLC.
    - Use of this software implies agreement to the license terms and conditions as stated
      in the Elastic License 2.0.

    For more information or inquiries please visit our website at
    https://www.canyongbs.com or contact us via email at legal@canyongbs.com.

</COPYRIGHT>
*/

/**
 * Guards the common-authored skills in `.ai/skills` against the objective
 * limits of the Agent Skills specification (https://agentskills.io/specification).
 *
 * The Copilot CLI silently drops any skill whose frontmatter violates these
 * limits — a too-long `description` disables the whole skill with no error
 * surfaced — so we fail here, before the guidance is ever published.
 *
 * Lengths are measured in characters (`mb_strlen`), i.e. Unicode code points.
 * This was verified empirically against the Copilot CLI skill loader: a
 * description of 1024 characters but 2048 bytes loaded successfully, while
 * 1025 ASCII characters was rejected — so the limit is code points, not bytes.
 *
 * Canyon GBS' own frontmatter conventions (license, author) live in a separate
 * `SkillConventions` test, since those are our preferences and will grow over
 * time independently of the spec.
 */
it('has a SKILL.md file', function (string $name, string $path) {
    expect($path)->toBeFile();
})->with('skills');

it('has a `name` that matches its directory', function (string $name, string $path) {
    $frontmatter = skillFrontmatter($path);

    expect($frontmatter)->toHaveKey('name')
        ->and($frontmatter['name'])->toBe($name);
})->with('skills');

it('has a `name` no longer than 64 characters', function (string $name, string $path) {
    $value = skillFrontmatter($path)['name'] ?? null;

    expect($value)->toBeString()
        ->and(mb_strlen((string) $value, 'UTF-8'))->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(64);
})->with('skills');

it('has a `name` containing only lowercase letters, numbers, and hyphens', function (string $name, string $path) {
    $value = skillFrontmatter($path)['name'] ?? null;

    expect($value)->toBeString()
        ->and($value)->toMatch('/^[a-z0-9-]+$/');
})->with('skills');

it('has a `name` that does not start or end with a hyphen', function (string $name, string $path) {
    $value = skillFrontmatter($path)['name'] ?? null;

    expect($value)->toBeString()
        ->and(str_starts_with((string) $value, '-'))->toBeFalse()
        ->and(str_ends_with((string) $value, '-'))->toBeFalse();
})->with('skills');

it('has a `name` that does not contain consecutive hyphens', function (string $name, string $path) {
    $value = skillFrontmatter($path)['name'] ?? null;

    expect($value)->toBeString()
        ->and(str_contains((string) $value, '--'))->toBeFalse();
})->with('skills');

it('has a non-empty `description` within the 1024-character limit', function (string $name, string $path) {
    $value = skillFrontmatter($path)['description'] ?? null;

    expect($value)->toBeString()
        ->and(mb_strlen((string) $value, 'UTF-8'))->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(1024);
})->with('skills');

it('has a `compatibility` value within the 500-character limit', function (string $name, string $path) {
    $frontmatter = skillFrontmatter($path);

    if (! array_key_exists('compatibility', $frontmatter)) {
        expect(true)->toBeTrue();

        return;
    }

    expect($frontmatter['compatibility'])->toBeString()
        ->and(mb_strlen((string) $frontmatter['compatibility'], 'UTF-8'))->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(500);
})->with('skills');
