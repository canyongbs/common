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

namespace CanyonGBS\Common;

use Illuminate\Support\ServiceProvider;

class CommonBoostServiceProvider extends ServiceProvider
{
    /**
     * Third-party package guidelines that may be overridden from common by
     * publishing a matching file into the app's `.ai/guidelines` directory.
     * Boost cannot replace these by path (unlike first-party guidelines), so
     * we exclude the original only while an override file is present.
     *
     * @var list<string>
     */
    protected array $overridablePackageGuidelines = [
        'filament/filament',
        'spatie/laravel-medialibrary',
    ];

    /**
     * Boost guideline keys to remove entirely across every app.
     * Use this for core or package guidelines that should never be published.
     *
     * @var list<string>
     */
    protected array $excludedGuidelines = [
        'deployments',
    ];

    /**
     * Boost skill keys to remove entirely across every app. This only affects
     * bundled and third-party skills; skills published by common are controlled
     * by their presence in common's `.ai/skills` directory instead.
     *
     * @var list<string>
     */
    protected array $excludedSkills = [
        // Replaced by common's `writing-tests` skill.
        'pest-testing',
        'medialibrary-development',
    ];

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $excludedGuidelines = [...config('boost.guidelines.exclude', []), ...$this->excludedGuidelines];

        foreach ($this->overridablePackageGuidelines as $guideline) {
            foreach (['blade.php', 'md'] as $extension) {
                if (file_exists(base_path(".ai/guidelines/{$guideline}.{$extension}"))) {
                    $excludedGuidelines[] = $guideline;

                    break;
                }
            }
        }

        config([
            'boost.guidelines.exclude' => array_values(array_unique($excludedGuidelines)),
            'boost.skills.exclude' => array_values(array_unique([...config('boost.skills.exclude', []), ...$this->excludedSkills])),
        ]);
    }
}
