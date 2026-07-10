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
      of the licensor in the software. Any use of the licensor's trademarks is subject
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

declare(strict_types = 1);

namespace CanyonGBS\Common\Rules\SettingsPropertiesMustHaveDefaults;

use PhpParser\Node;
use PhpParser\Node\Stmt\Property;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Enforces that all properties declared in Spatie Settings classes have a PHP default value.
 *
 * Spatie's LoadingSettings event relies on PHP reflection to populate settings properties
 * that have not yet been persisted to the database. If a property has no declared default,
 * reflection returns null regardless of the declared type, causing silent type errors at runtime.
 *
 * @implements Rule<Property>
 */
class SettingsPropertiesMustHaveDefaultsRule implements Rule
{
    public const string ERROR_MESSAGE = 'Property $%s in a Spatie Settings class must have a default value. Properties without defaults cause silent type errors when the value has not yet been saved to the database.';

    /**
     * @return class-string<Node>
     */
    public function getNodeType(): string
    {
        return Property::class;
    }

    /**
     * @param Property $node
     *
     * @return array<RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $classReflection = $scope->getClassReflection();

        if ($classReflection === null) {
            return [];
        }

        if (
            ! $classReflection->isSubclassOf('Spatie\LaravelSettings\Settings')
            && $classReflection->getName() !== 'Spatie\LaravelSettings\Settings'
        ) {
            return [];
        }

        $errors = [];

        foreach ($node->props as $prop) {
            if ($prop->default !== null) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(sprintf(self::ERROR_MESSAGE, $prop->name->name))
                ->identifier('Common.settingsPropertyMissingDefault')
                ->build();
        }

        return $errors;
    }
}
