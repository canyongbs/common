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

namespace CanyonGBS\Common\Rector;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Expression;
use PHPStan\Type\ObjectType;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class FlattenAfterColumnGroupingRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Flatten the Blueprint "->after()" column grouping in schema migrations by hoisting the grouped column definitions out of the closure, as PostgreSQL does not support positioning columns.',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
                        Schema::table('users', function (Blueprint $table) {
                            $table->after('first_name', function (Blueprint $table) {
                                $table->string('middle_name');
                                $table->string('suffix');
                            });
                        });
                        CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
                        Schema::table('users', function (Blueprint $table) {
                            $table->string('middle_name');
                            $table->string('suffix');
                        });
                        CODE_SAMPLE,
                ),
            ],
        );
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [Expression::class];
    }

    /**
     * @param Expression $node
     *
     * @return array<Node\Stmt>|null
     */
    public function refactor(Node $node): ?array
    {
        $methodCall = $node->expr;

        if (! $methodCall instanceof MethodCall) {
            return null;
        }

        if (! $this->isName($methodCall->name, 'after')) {
            return null;
        }

        if (! $this->isObjectType($methodCall->var, new ObjectType('Illuminate\Database\Schema\Blueprint'))) {
            return null;
        }

        // The receiver must be a plain variable so the grouped columns can be hoisted
        // and the inner closure's Blueprint parameter aligned to it. Anything else is
        // left for the NoBlueprintAfterGroupingRule PHPStan rule to flag.
        if (! $methodCall->var instanceof Variable || ! is_string($methodCall->var->name)) {
            return null;
        }

        $arguments = $methodCall->getArgs();

        if (count($arguments) < 2) {
            return null;
        }

        $callback = $arguments[1]->value;

        if ($callback instanceof Closure) {
            $statements = $callback->stmts;
        } elseif ($callback instanceof ArrowFunction) {
            $statements = [new Expression($callback->expr)];
        } else {
            return null;
        }

        if (count($callback->params) < 1) {
            return null;
        }

        $innerParameter = $callback->params[0]->var;

        if (! $innerParameter instanceof Variable || ! is_string($innerParameter->name)) {
            return null;
        }

        $outerName = $methodCall->var->name;
        $innerName = $innerParameter->name;

        if ($innerName !== $outerName) {
            // Renaming would merge two distinct variables; leave it for PHPStan to flag.
            if ($this->bodyUsesVariableNamed($statements, $outerName)) {
                return null;
            }

            $this->renameVariable($statements, $innerName, $outerName);
        }

        return $statements;
    }

    /**
     * @param array<Node\Stmt> $statements
     */
    private function bodyUsesVariableNamed(array $statements, string $name): bool
    {
        $found = false;

        $this->traverseNodesWithCallable($statements, function (Node $node) use ($name, &$found): null {
            if ($node instanceof Variable && is_string($node->name) && $node->name === $name) {
                $found = true;
            }

            return null;
        });

        return $found;
    }

    /**
     * @param array<Node\Stmt> $statements
     */
    private function renameVariable(array $statements, string $from, string $to): void
    {
        $this->traverseNodesWithCallable($statements, function (Node $node) use ($from, $to): null {
            if ($node instanceof Variable && is_string($node->name) && $node->name === $from) {
                $node->name = $to;
            }

            return null;
        });
    }
}
