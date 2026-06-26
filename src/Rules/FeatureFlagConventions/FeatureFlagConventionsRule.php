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

namespace CanyonGBS\Common\Rules\FeatureFlagConventions;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Enforces the conventions for Laravel Pennant feature flag classes:
 *
 * 1. Every concrete class within the configured feature namespace (e.g. "App\Features")
 *    must extend one of the configured feature flag base classes.
 * 2. Every concrete class that extends one of the configured feature flag base classes
 *    must have a name ending in "Feature".
 *
 * Abstract classes, interfaces, enums, and traits are ignored.
 *
 * @implements Rule<InClassNode>
 */
class FeatureFlagConventionsRule implements Rule
{
    public const string MUST_EXTEND_BASE_ERROR_MESSAGE = 'Classes in the "%s" namespace must extend one of the feature flag base classes: %s.';

    public const string MUST_END_IN_FEATURE_ERROR_MESSAGE = 'Feature flag classes must have a name ending in "Feature".';

    /**
     * @param array<int, string> $baseClasses
     */
    public function __construct(
        private array $baseClasses,
        private string $featuresNamespace,
    ) {}

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

        if (! $classReflection->isClass()) {
            return [];
        }

        if ($classReflection->isAbstract()) {
            return [];
        }

        $className = $classReflection->getName();

        $extendsBaseClass = false;

        foreach ($this->baseClasses as $baseClass) {
            if ($classReflection->isSubclassOf($baseClass)) {
                $extendsBaseClass = true;

                break;
            }
        }

        $inFeaturesNamespace = str_starts_with($className, $this->featuresNamespace . '\\');

        $errors = [];

        if ($inFeaturesNamespace && ! $extendsBaseClass) {
            $errors[] = RuleErrorBuilder::message(sprintf(
                self::MUST_EXTEND_BASE_ERROR_MESSAGE,
                $this->featuresNamespace,
                implode(', ', $this->baseClasses),
            ))
                ->identifier('Common.featureMustExtendFeatureFlagAbstracts')
                ->build();
        }

        if ($extendsBaseClass && ! str_ends_with($classReflection->getNativeReflection()->getShortName(), 'Feature')) {
            $errors[] = RuleErrorBuilder::message(self::MUST_END_IN_FEATURE_ERROR_MESSAGE)
                ->identifier('Common.featureFlagClassMustEndInFeature')
                ->build();
        }

        return $errors;
    }
}
