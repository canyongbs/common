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

namespace CanyonGBS\Common\Rules\NoBlueprintAfterGrouping;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

/**
 * Flags Blueprint "->after()" column groupings that the FlattenAfterColumnGroupingRector
 * cannot rewrite automatically, so they are surfaced for manual restructuring.
 *
 * The "auto-flattenable" conditions below are intentionally kept identical to
 * CanyonGBS\Common\Rector\FlattenAfterColumnGroupingRector — keep the two in sync.
 *
 * @implements Rule<MethodCall>
 */
class NoBlueprintAfterGroupingRule implements Rule
{
    public const string ERROR_MESSAGE = 'The Blueprint "after()" column grouping cannot be automatically flattened here. Restructure the migration to define the columns directly, as PostgreSQL does not support positioning columns.';

    /**
     * @return class-string<Node>
     */
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    /**
     * @param MethodCall $node
     *
     * @return array<RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node->name instanceof Identifier) {
            return [];
        }

        if ($node->name->toLowerString() !== 'after') {
            return [];
        }

        if (! (new ObjectType('Illuminate\Database\Schema\Blueprint'))->isSuperTypeOf($scope->getType($node->var))->yes()) {
            return [];
        }

        $arguments = $node->getArgs();

        if (count($arguments) < 2) {
            return [];
        }

        $callback = $arguments[1]->value;

        if (! $callback instanceof Closure && ! $callback instanceof ArrowFunction) {
            return [];
        }

        if ($this->isAutoFlattenable($node, $callback)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(self::ERROR_MESSAGE)
                ->identifier('Common.blueprintAfterGrouping')
                ->build(),
        ];
    }

    private function isAutoFlattenable(MethodCall $node, Closure|ArrowFunction $callback): bool
    {
        if (! $node->var instanceof Variable || ! is_string($node->var->name)) {
            return false;
        }

        if (count($callback->params) < 1) {
            return false;
        }

        $innerParameter = $callback->params[0]->var;

        if (! $innerParameter instanceof Variable || ! is_string($innerParameter->name)) {
            return false;
        }

        $outerName = $node->var->name;
        $innerName = $innerParameter->name;

        if ($innerName === $outerName) {
            return true;
        }

        $statements = $callback instanceof Closure ? $callback->stmts : [$callback->expr];

        return ! $this->bodyUsesVariableNamed($statements, $outerName);
    }

    /**
     * @param array<Node> $statements
     */
    private function bodyUsesVariableNamed(array $statements, string $name): bool
    {
        $match = (new NodeFinder())->findFirst(
            $statements,
            fn (Node $node): bool => $node instanceof Variable && is_string($node->name) && $node->name === $name,
        );

        return $match !== null;
    }
}
