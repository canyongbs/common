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

namespace CanyonGBS\Common\Rules\ShouldBeUniqueJobMustDefineUniqueFor;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Ensures every queued job that implements "Illuminate\Contracts\Queue\ShouldBeUnique"
 * (directly, through inheritance, or via the more specific
 * "Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing") defines a "uniqueFor" value.
 *
 * When a job is unique, Laravel acquires a lock so that no other instance of the same job is
 * dispatched while one is already queued or running. If "uniqueFor" is not defined, Laravel
 * falls back to a lock duration of "0", meaning the lock never expires on its own and is only
 * released when the job finishes processing. If the worker dies catastrophically mid-process
 * (out of memory, SIGKILL, fatal error, etc.) the lock is never released and the job can no
 * longer be dispatched until the lock is cleared manually.
 *
 * Defining "uniqueFor" — as either a property or a method, whichever the job needs — gives the
 * lock a maximum lifetime so it is eventually released even when a job fails badly.
 *
 * Abstract classes are skipped.
 *
 * @implements Rule<InClassNode>
 */
class ShouldBeUniqueJobMustDefineUniqueForRule implements Rule
{
    public const string SHOULD_BE_UNIQUE_INTERFACE = 'Illuminate\Contracts\Queue\ShouldBeUnique';

    public const string ERROR_MESSAGE = 'Jobs that implement "Illuminate\Contracts\Queue\ShouldBeUnique" must define a "uniqueFor" property or method. Without it the unique lock never expires on its own, so a job that fails catastrophically mid-process can leave a lock that is never released and blocks all future dispatches. Add a "uniqueFor" property or method to this job or a parent class.';

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

        if ($classReflection->isAbstract()) {
            return [];
        }

        if (! $classReflection->implementsInterface(self::SHOULD_BE_UNIQUE_INTERFACE)) {
            return [];
        }

        if ($this->definesUniqueFor($classReflection)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(self::ERROR_MESSAGE)
                ->identifier('Common.shouldBeUniqueJobMustDefineUniqueFor')
                ->build(),
        ];
    }

    private function definesUniqueFor(ClassReflection $classReflection): bool
    {
        return $classReflection->hasProperty('uniqueFor') || $classReflection->hasMethod('uniqueFor');
    }
}
