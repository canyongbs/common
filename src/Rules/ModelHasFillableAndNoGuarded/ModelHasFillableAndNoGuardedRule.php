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

namespace CanyonGBS\Common\Rules\ModelHasFillableAndNoGuarded;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Ensures every Eloquent model (a subclass of Illuminate\Database\Eloquent\Model)
 * defines a "$fillable" property and does NOT define a "$guarded" property. This keeps
 * mass-assignment protection explicit and consistent across the codebase.
 *
 * A "$fillable" property may be inherited from a parent model (abstract or concrete);
 * only the framework's default declarations on the base model are ignored. A model that
 * inherits a "$guarded" property from a parent is exempt from the "$fillable" requirement.
 *
 * A "$guarded" property is only reported when it is declared directly on the analyzed
 * class; a "$guarded" property inherited from a parent is not reported (the parent is
 * reported when it is analyzed itself). This includes abstract models: an abstract model
 * that declares its own "$guarded" property is reported.
 *
 * Abstract models are exempt from the "$fillable" requirement.
 *
 * @implements Rule<InClassNode>
 */
class ModelHasFillableAndNoGuardedRule implements Rule
{
    public const string MODEL_CLASS = 'Illuminate\Database\Eloquent\Model';

    /**
     * Declarations of "$fillable" / "$guarded" originating from these classes are the
     * framework's defaults and do not count as user-defined.
     *
     * @var list<string>
     */
    public const array BASE_DECLARATIONS = [
        'Illuminate\Database\Eloquent\Model',
        'Illuminate\Database\Eloquent\Concerns\GuardsAttributes',
    ];

    public const string MISSING_FILLABLE_ERROR_MESSAGE = 'Eloquent models must define a "$fillable" property. Add a "$fillable" property to this model or a parent model.';

    public const string HAS_GUARDED_ERROR_MESSAGE = 'Eloquent models must not define a "$guarded" property. Remove the "$guarded" property declared on "%s" and use "$fillable" instead.';

    /**
     * @return class-string<Node>
     */
    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /**
     * @param InClassNode $node
     *
     * @return array<RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $classReflection = $node->getClassReflection();

        if (! $classReflection->isSubclassOf(self::MODEL_CLASS)) {
            return [];
        }

        $errors = [];

        $guardedDeclaringClass = $this->userDefinedPropertyDeclaringClass($classReflection, 'guarded', $scope);
        $hasOwnGuarded = $guardedDeclaringClass === $classReflection->getName();
        $hasInheritedGuarded = $guardedDeclaringClass !== null && ! $hasOwnGuarded;

        if (
            ! $classReflection->isAbstract()
            && ! $hasInheritedGuarded
            && ! $this->isPropertyDefinedByUser($classReflection, 'fillable', $scope)
        ) {
            $errors[] = RuleErrorBuilder::message(self::MISSING_FILLABLE_ERROR_MESSAGE)
                ->identifier('Common.modelMissingFillable')
                ->build();
        }

        if ($hasOwnGuarded) {
            $errors[] = RuleErrorBuilder::message(sprintf(
                self::HAS_GUARDED_ERROR_MESSAGE,
                $guardedDeclaringClass,
            ))
                ->identifier('Common.modelHasGuarded')
                ->build();
        }

        return $errors;
    }

    private function isPropertyDefinedByUser(ClassReflection $classReflection, string $propertyName, Scope $scope): bool
    {
        return $this->userDefinedPropertyDeclaringClass($classReflection, $propertyName, $scope) !== null;
    }

    private function userDefinedPropertyDeclaringClass(ClassReflection $classReflection, string $propertyName, Scope $scope): ?string
    {
        if (! $classReflection->hasProperty($propertyName)) {
            return null;
        }

        $declaringClass = $classReflection->getProperty($propertyName, $scope)->getDeclaringClass()->getName();

        if (in_array($declaringClass, self::BASE_DECLARATIONS, true)) {
            return null;
        }

        return $declaringClass;
    }
}
