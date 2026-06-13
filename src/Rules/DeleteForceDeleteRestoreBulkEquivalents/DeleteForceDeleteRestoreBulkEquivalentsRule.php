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