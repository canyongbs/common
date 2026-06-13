<?php

namespace CanyonGBS\Common\Rules\DeleteForceDeleteRestoreBulkEquivalents;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use RuntimeException;

/**
 * @implements Rule<Class_>
 */
class DeleteForceDeleteRestoreBulkEquivalentsRule implements Rule
{
    public const string ERROR_MESSAGE_TEMPLATE = 'Policy "%s" defines "%s()" but is missing the corresponding bulk authorization method "%s()".';

    public const array BULK_EQUIVALENTS = [
            'delete' => 'deleteAny',
            'forceDelete' => 'forceDeleteAny',
            'restore' => 'restoreAny',
        ];

    /**
     * @return class-string<Node>
     */
    public function getNodeType(): string
    {
        return Class_::class;
    }

    /**
     * @param Class_ $node
     * 
     * @return array<RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if ($node->namespacedName === null) {
            return [];
        }

        $className = $node->namespacedName->toString();

        if (! str_ends_with($className, 'Policy')) {
            return [];
        }

        $methods = [];

        foreach ($node->getMethods() as $method) {
            $methods[$method->name->toString()] = true;
        }

        $errors = [];

        foreach (self::BULK_EQUIVALENTS as $single => $bulk) {
            if (isset($methods[$single]) && ! isset($methods[$bulk])) {
                $errors[] =  RuleErrorBuilder::message(
                    sprintf(
                        self::ERROR_MESSAGE_TEMPLATE,
                        $className,
                        $single,
                        $bulk,
                    ),
                )
                    ->identifier('Common.deleteForceDeleteRestoreBulkEquivalents')
                    ->build();
            }
        }

        return $errors;
    }
}