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

namespace CanyonGBS\Common\Rules\NoLocalModelScope;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Forbids defining Eloquent local scopes on models (a subclass of
 * Illuminate\Database\Eloquent\Model).
 *
 * Local scopes are reported in both of their forms:
 *
 * - methods using the "scope" prefix convention (e.g. "scopeActive");
 * - methods annotated with the Illuminate\Database\Eloquent\Attributes\Scope attribute.
 *
 * Local scopes rely on magic methods, which static analysis tools struggle to reason
 * about. Tappable scopes should be used instead.
 *
 * Only methods declared directly on the class are inspected, so framework trait scopes
 * (e.g. "scopeWithoutTrashed") and scopes inherited from a parent are not reported on
 * every subclass.
 *
 * @implements Rule<InClassNode>
 */
class NoLocalModelScopeRule implements Rule
{
    public const string MODEL_CLASS = 'Illuminate\Database\Eloquent\Model';

    public const string SCOPE_ATTRIBUTE_CLASS = 'Illuminate\Database\Eloquent\Attributes\Scope';

    public const string ERROR_MESSAGE = 'Eloquent local scopes are not allowed. The "%s" method on "%s" defines a local scope, which relies on magic methods that static analysis cannot reason about. Use a tappable scope instead. See https://seankegel.com/elevate-your-laravel-eloquent-queries-with-tappable-scopes.';

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

        foreach ($node->getOriginalNode()->getMethods() as $method) {
            if (! $this->isLocalScope($method)) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(sprintf(
                self::ERROR_MESSAGE,
                $method->name->toString(),
                $classReflection->getName(),
            ))
                ->identifier('Common.noLocalModelScope')
                ->line($method->getStartLine())
                ->build();
        }

        return $errors;
    }

    private function isLocalScope(ClassMethod $method): bool
    {
        if (preg_match('/^scope[A-Z]/', $method->name->toString()) === 1) {
            return true;
        }

        foreach ($method->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                if ($attr->name->toString() === self::SCOPE_ATTRIBUTE_CLASS) {
                    return true;
                }
            }
        }

        return false;
    }
}
