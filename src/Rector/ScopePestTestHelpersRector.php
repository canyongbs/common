<?php

/*
<COPYRIGHT>

    Copyright © 2016-2026, Canyon GBS LLC. All rights reserved.

    Canyon GBS Common is licensed under the Elastic License 2.0. For more details,
    see https://github.com/canyongbs/common/blob/main/LICENSE.

</COPYRIGHT>
*/

namespace CanyonGBS\Common\Rector;

use PhpParser\Node;
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

        $statements = array_values(array_filter(
            $node->stmts,
            static fn (Node $statement): bool => ! $statement instanceof Function_,
        ));
        $insertionIndex = $this->findFirstRegistrationIndex($statements);

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

    /** @param list<Node> $statements */
    private function findFirstRegistrationIndex(array $statements): int
    {
        foreach ($statements as $index => $statement) {
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
    private function rewriteHelperCalls(Closure $closure, array $helperNames, ?string $currentHelper = null): array
    {
        $dependencies = [];

        foreach ((new NodeFinder())->findInstanceOf($closure->getStmts(), FuncCall::class) as $call) {
            foreach ($helperNames as $helperName) {
                $isHelperCall = $this->isName($call, $helperName)
                    || ($call->name instanceof Variable && $call->name->name === $helperName);

                if (! $isHelperCall) {
                    continue;
                }

                $call->name = new Variable($helperName);

                if ($helperName !== $currentHelper) {
                    $dependencies[] = $helperName;
                    $this->addClosureUse($closure, $helperName);
                }
            }
        }

        return array_values(array_unique($dependencies));
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
