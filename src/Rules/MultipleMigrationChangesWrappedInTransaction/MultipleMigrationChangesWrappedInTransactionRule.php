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

namespace CanyonGBS\Common\Rules\MultipleMigrationChangesWrappedInTransaction;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Flags migration "up()" and "down()" methods that contain more than one root statement.
 *
 * We use PostgreSQL for all projects, which allows almost all data and schema changes to be
 * performed inside a transaction. Migrations that make multiple changes should wrap them in a
 * single "DB::transaction(...)" call so that, if any step fails, none of the other changes are
 * committed. This keeps the migration idempotent and safe to re-run once the issue is fixed.
 *
 * A correctly wrapped migration method has exactly one root statement (the transaction call),
 * so more than one root statement signals that the changes are not wrapped in a transaction.
 *
 * @implements Rule<InClassNode>
 */
class MultipleMigrationChangesWrappedInTransactionRule implements Rule
{
    public const string MIGRATION_CLASS = 'Illuminate\Database\Migrations\Migration';

    public const string ERROR_MESSAGE = 'Migrations that make multiple changes must wrap them in a single "DB::transaction(...)" call so that a failure rolls back every change and the migration stays idempotent. Move the statements in this "%s()" method into a "DB::transaction(...)" closure.';

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

        if (! $classReflection->isSubclassOf(self::MIGRATION_CLASS)) {
            return [];
        }

        $errors = [];

        foreach ($node->getOriginalNode()->getMethods() as $method) {
            $methodName = $method->name->toLowerString();

            if ($methodName !== 'up' && $methodName !== 'down') {
                continue;
            }

            if (! $this->hasMultipleRootStatements($method)) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(sprintf(self::ERROR_MESSAGE, $method->name->toString()))
                ->identifier('Common.multipleMigrationChangesNotWrappedInTransaction')
                ->line($method->getStartLine())
                ->build();
        }

        return $errors;
    }

    private function hasMultipleRootStatements(ClassMethod $method): bool
    {
        if ($method->stmts === null) {
            return false;
        }

        return count($method->stmts) > 1;
    }
}
