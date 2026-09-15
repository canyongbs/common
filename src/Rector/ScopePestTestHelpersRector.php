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
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Nop;
use PhpParser\NodeFinder;
use Rector\PhpParser\Node\FileNode;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class ScopePestTestHelpersRector extends AbstractRector
{
    private const array PEST_REGISTRATION_FUNCTIONS = [
        'afterAll',
        'afterEach',
        'beforeAll',
        'beforeEach',
        'dataset',
        'describe',
        'it',
        'test',
    ];

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Scope namespace-less helper functions in stand-alone Pest test files to the closures that use them.',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
                        function helper(): string
                        {
                            return 'value';
                        }

                        it('uses the helper', function () {
                            expect(helper())->toBe('value');
                        });
                        CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
                        $helper = function (): string {
                            return 'value';
                        };

                        it('uses the helper', function () use ($helper) {
                            expect($helper())->toBe('value');
                        });
                        CODE_SAMPLE,
                ),
            ],
        );
    }

    /** @return array<class-string<Node>> */
    public function getNodeTypes(): array
    {
        return [FileNode::class];
    }

    /** @param FileNode $node */
    public function refactor(Node $node): ?FileNode
    {
        if (! $this->shouldRefactorFile()) {
            return null;
        }

        $helperFunctions = [];

        foreach ($node->stmts as $statement) {
            if ($statement instanceof Function_) {
                $helperFunctions[$statement->name->toString()] = $statement;
            }
        }

        if ($helperFunctions === []) {
            return null;
        }

        $helperFunctions = $this->withoutRecursiveHelpers($helperFunctions);

        if ($helperFunctions === []) {
            return null;
        }

        $statements = array_values(array_filter(
            $node->stmts,
            static fn (Node $statement): bool => ! $statement instanceof Function_
                || ! array_key_exists($statement->name->toString(), $helperFunctions),
        ));
        $insertionIndex = $this->findFirstUsageOrRegistrationIndex($statements, array_keys($helperFunctions));

        if ($insertionIndex === count($statements)) {
            return null;
        }

        $helperNames = array_keys($helperFunctions);
        $assignments = [];
        $dependencies = [];

        foreach ($helperFunctions as $helperName => $function) {
            $closure = new Closure([
                'byRef' => $function->byRef,
                'params' => $function->params,
                'returnType' => $function->returnType,
                'stmts' => $function->stmts ?? [],
                'attrGroups' => $function->attrGroups,
            ], ['comments' => $function->getComments()]);

            $dependencies[$helperName] = $this->rewriteHelperCalls($closure, $helperNames, $helperName);
            $assignments[$helperName] = new Expression(
                new Assign(new Variable($helperName), $closure),
                ['comments' => $function->getComments()],
            );
        }

        foreach ((new NodeFinder())->findInstanceOf($statements, Closure::class) as $closure) {
            $this->rewriteHelperCalls($closure, $helperNames);
        }

        foreach ((new NodeFinder())->findInstanceOf($statements, ArrowFunction::class) as $arrowFunction) {
            $this->rewriteHelperCalls($arrowFunction, $helperNames);
        }

        $this->rewriteHelperCallsInStatements($statements, $helperNames);

        $helperStatements = [];

        foreach ($this->sortHelperNames($helperNames, $dependencies) as $helperName) {
            $helperStatements[] = $assignments[$helperName];
        }

        $prefixStatements = [];

        if ($helperStatements !== []) {
            $registrationComments = $statements[$insertionIndex]->getComments();
            $statements[$insertionIndex]->setAttribute('comments', []);

            if ($registrationComments !== []) {
                $header = new Nop();
                $header->setAttribute('comments', $registrationComments);
                $prefixStatements[] = $header;
            }

            foreach ($helperStatements as $helperStatement) {
                $prefixStatements[] = $helperStatement;
                $prefixStatements[] = new Nop();
            }
        }

        array_splice($statements, $insertionIndex, 0, $prefixStatements);
        $node->stmts = $statements;

        return $node;
    }

    private function shouldRefactorFile(): bool
    {
        $filePath = str_replace('\\', '/', $this->getFile()->getFilePath());

        return ! str_ends_with($filePath, '/Pest.php')
            && ! str_ends_with($filePath, '/Helpers.php')
            && ! str_contains($filePath, '/Helpers/');
    }

    /**
     * @param array<string, Function_> $helperFunctions
     *
     * @return array<string, Function_>
     */
    private function withoutRecursiveHelpers(array $helperFunctions): array
    {
        $dependencies = [];

        foreach ($helperFunctions as $helperName => $function) {
            $dependencies[$helperName] = $this->findHelperCalls($function, array_keys($helperFunctions));
        }

        $recursiveHelpers = [];

        foreach (array_keys($helperFunctions) as $helperName) {
            if ($this->hasDependencyPath($helperName, $helperName, $dependencies, [])) {
                $recursiveHelpers[$helperName] = true;
            }
        }

        foreach (array_keys($recursiveHelpers) as $helperName) {
            $this->markDependenciesAsUntransformable($helperName, $dependencies, $recursiveHelpers);
        }

        return array_filter(
            $helperFunctions,
            static fn (string $helperName): bool => ! isset($recursiveHelpers[$helperName]),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * @param array<string, list<string>> $dependencies
     * @param array<string, true> $visited
     */
    private function hasDependencyPath(string $origin, string $current, array $dependencies, array $visited): bool
    {
        foreach ($dependencies[$current] ?? [] as $dependency) {
            if ($dependency === $origin) {
                return true;
            }

            if (! isset($visited[$dependency])) {
                $visited[$dependency] = true;

                if ($this->hasDependencyPath($origin, $dependency, $dependencies, $visited)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<string, list<string>> $dependencies
     * @param array<string, true> $untransformableHelpers
     */
    private function markDependenciesAsUntransformable(string $helperName, array $dependencies, array &$untransformableHelpers): void
    {
        foreach ($dependencies[$helperName] ?? [] as $dependency) {
            if (isset($untransformableHelpers[$dependency])) {
                continue;
            }

            $untransformableHelpers[$dependency] = true;
            $this->markDependenciesAsUntransformable($dependency, $dependencies, $untransformableHelpers);
        }
    }

    /**
     * @param list<Node> $statements
     * @param list<string> $helperNames
     */
    private function findFirstUsageOrRegistrationIndex(array $statements, array $helperNames): int
    {
        foreach ($statements as $index => $statement) {
            if ($this->findHelperCalls($statement, $helperNames) !== []) {
                return $index;
            }

            foreach ((new NodeFinder())->findInstanceOf([$statement], FuncCall::class) as $call) {
                foreach (self::PEST_REGISTRATION_FUNCTIONS as $functionName) {
                    if ($this->isName($call, $functionName)) {
                        return $index;
                    }
                }
            }
        }

        return count($statements);
    }

    /**
     * @param list<string> $helperNames
     *
     * @return list<string>
     */
    private function rewriteHelperCalls(Closure | ArrowFunction $closure, array $helperNames, ?string $currentHelper = null): array
    {
        $dependencies = [];

        foreach ((new NodeFinder())->findInstanceOf($this->functionStatements($closure), FuncCall::class) as $call) {
            foreach ($helperNames as $helperName) {
                $isHelperCall = $this->isName($call, $helperName)
                    || ($call->name instanceof Variable && $call->name->name === $helperName);

                if (! $isHelperCall) {
                    continue;
                }

                $call->name = new Variable($helperName);

                if ($helperName !== $currentHelper) {
                    $dependencies[] = $helperName;

                    if ($closure instanceof Closure) {
                        $this->addClosureUse($closure, $helperName);
                    }
                }
            }
        }

        return array_values(array_unique($dependencies));
    }

    /**
     * @param list<Node> $statements
     * @param list<string> $helperNames
     */
    private function rewriteHelperCallsInStatements(array $statements, array $helperNames): void
    {
        foreach ((new NodeFinder())->findInstanceOf(
            array_filter($statements, static fn (Node $statement): bool => ! $statement instanceof Function_),
            FuncCall::class,
        ) as $call) {
            foreach ($helperNames as $helperName) {
                if ($this->isName($call, $helperName)) {
                    $call->name = new Variable($helperName);
                }
            }
        }
    }

    /**
     * @param list<string> $helperNames
     *
     * @return list<string>
     */
    private function findHelperCalls(Node $node, array $helperNames): array
    {
        $calls = [];

        foreach ((new NodeFinder())->findInstanceOf([$node], FuncCall::class) as $call) {
            foreach ($helperNames as $helperName) {
                if ($this->isName($call, $helperName)) {
                    $calls[] = $helperName;
                }
            }
        }

        return array_values(array_unique($calls));
    }

    /** @return list<Node> */
    private function functionStatements(Closure | ArrowFunction $function): array
    {
        return $function instanceof Closure ? $function->getStmts() : [$function->expr];
    }

    /**
     * @param list<string> $helperNames
     * @param array<string, list<string>> $dependencies
     *
     * @return list<string>
     */
    private function sortHelperNames(array $helperNames, array $dependencies): array
    {
        $sorted = [];
        $visiting = [];

        $visit = function (string $helperName) use (&$visit, &$sorted, &$visiting, $dependencies): void {
            if (in_array($helperName, $sorted, true) || isset($visiting[$helperName])) {
                return;
            }

            $visiting[$helperName] = true;

            foreach ($dependencies[$helperName] ?? [] as $dependency) {
                $visit($dependency);
            }
            unset($visiting[$helperName]);
            $sorted[] = $helperName;
        };

        foreach ($helperNames as $helperName) {
            $visit($helperName);
        }

        return $sorted;
    }

    private function addClosureUse(Closure $closure, string $variableName): void
    {
        foreach ($closure->uses as $use) {
            if ($use->var->name === $variableName) {
                return;
            }
        }

        $closure->uses[] = new Node\ClosureUse(new Variable($variableName));
    }
}
