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

namespace CanyonGBS\Common\Rules\UseClientIpResolver;

use CanyonGBS\Common\Support\ClientIp;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

/**
 * Flags direct request IP accessor usage so consumers consistently resolve client IPs through
 * ClientIp::resolve(), which accounts for Cloudflare + ALB forwarding, including request()
 * helper calls and Request facade access.
 *
 * @implements Rule<Expr>
 */
class UseClientIpResolverRule implements Rule
{
    public const string ERROR_MESSAGE = 'Avoid direct request IP accessors (ip(), ips(), getClientIp(), getClientIps()). Use CanyonGBS\\Common\\Support\\ClientIp::resolve() instead so client IP resolution remains Cloudflare and ALB aware.';

    /** @var array<int, string> */
    private const array BLOCKED_METHODS = ['ip', 'ips', 'getclientip', 'getclientips'];

    /**
     * @return class-string<Node>
     */
    public function getNodeType(): string
    {
        return Expr::class;
    }

    /**
     * @param Expr $node
     *
     * @return array<RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node instanceof MethodCall && ! $node instanceof StaticCall) {
            return [];
        }

        if (! $node->name instanceof Identifier) {
            return [];
        }

        if ($scope->getClassReflection()?->getName() === ClientIp::class) {
            return [];
        }

        if (! in_array($node->name->toLowerString(), self::BLOCKED_METHODS, true)) {
            return [];
        }

        if (
            ! $this->isRequestMethodCall($node, $scope)
            && ! $this->isRequestFacadeStaticCall($node, $scope)
        ) {
            return [];
        }

        return [
            RuleErrorBuilder::message(self::ERROR_MESSAGE)
                ->identifier('Common.useClientIpResolver')
                ->build(),
        ];
    }

    private function isRequestMethodCall(MethodCall|StaticCall $node, Scope $scope): bool
    {
        if (! $node instanceof MethodCall) {
            return false;
        }

        if ($node->var instanceof FuncCall && $node->var->name instanceof Name && $node->var->name->toLowerString() === 'request') {
            return true;
        }

        $requestType = new ObjectType('Illuminate\\Http\\Request');
        $symfonyRequestType = new ObjectType('Symfony\\Component\\HttpFoundation\\Request');
        $receiverType = $scope->getType($node->var);

        return $requestType->isSuperTypeOf($receiverType)->yes()
            || $symfonyRequestType->isSuperTypeOf($receiverType)->yes();
    }

    private function isRequestFacadeStaticCall(MethodCall|StaticCall $node, Scope $scope): bool
    {
        if (! $node instanceof StaticCall || ! $node->class instanceof Name) {
            return false;
        }

        return $scope->resolveName($node->class) === 'Illuminate\\Support\\Facades\\Request';
    }
}
