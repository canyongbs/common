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

namespace CanyonGBS\Common\Rules\NoAssertSee;

use Illuminate\Mail\Mailable;
use Illuminate\Testing\TestComponent;
use Illuminate\Testing\TestResponse;
use Illuminate\Testing\TestView;
use Livewire\Component;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use PHPStan\Type\TypeCombinator;

/**
 * Flags calls to the assertSee family of test assertions (Livewire's testable, Laravel's
 * TestResponse/TestView/TestComponent, and Mailable). These only prove the given text appears
 * somewhere in the rendered output, not that it appears where the test expects, so they can pass
 * even when the feature under test is broken. Prefer a more precise assertion (e.g. asserting
 * component/view state, a scoped selector, or the specific structure produced). If you are
 * certain you specifically need one of these assertions, the rule can be silenced with a
 * specific inline ignore.
 *
 * Only flags calls made on a receiver known to actually carry these testing assertions, so
 * unrelated classes/namespaces that happen to define a same-named method are left alone.
 *
 * @implements Rule<MethodCall>
 */
class NoAssertSeeRule implements Rule
{
    public const string ERROR_MESSAGE = 'Avoid assertSee() and its alternatives: they only prove the text appears somewhere in the rendered output, not that it appears where the test actually expects it, so they can pass even when the feature under test is broken. Prefer a more precise assertion (e.g. asserting component/view state, a scoped selector, or the specific structure produced). If you are certain you specifically need this assertion, add an inline ignore for this rule (// @phpstan-ignore Common.noAssertSee).';

    /**
     * @var list<string>
     */
    private const array BANNED_METHODS = [
        'assertSee',
        'assertSeeText',
        'assertSeeHtml',
        'assertSeeHtmlInOrder',
        'assertSeeInOrder',
        'assertSeeTextInOrder',
        'assertDontSee',
        'assertDontSeeText',
        'assertDontSeeHtml',
        'assertSeeIn',
        'assertDontSeeIn',
        'assertSeeInHtml',
        'assertDontSeeInHtml',
        'assertSeeInText',
        'assertDontSeeInText',
        'assertSeeInOrderInHtml',
        'assertSeeInOrderInText',
    ];

    /**
     * Classes that actually carry the assertSee-family assertions we ban. `Laravel\Dusk\Browser`
     * is referenced as a plain string since Dusk is not a dependency of every consuming app; the
     * class does not need to exist for an ObjectType comparison against it to work.
     *
     * @var list<string>
     */
    private const array ALLOWED_RECEIVER_CLASSES = [
        TestResponse::class,
        TestView::class,
        TestComponent::class,
        Mailable::class,
        Component::class,
        'Laravel\Dusk\Browser',
    ];

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

        if (! in_array($node->name->toString(), self::BANNED_METHODS, true)) {
            return [];
        }

        if (! $this->isKnownAssertionReceiver($node, $scope)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(self::ERROR_MESSAGE)
                ->identifier('Common.noAssertSee')
                ->build(),
        ];
    }

    private function isKnownAssertionReceiver(MethodCall $node, Scope $scope): bool
    {
        $calledOnType = $scope->getType($node->var);

        $allowedType = TypeCombinator::union(
            ...array_map(static fn (string $class): ObjectType => new ObjectType($class), self::ALLOWED_RECEIVER_CLASSES)
        );

        return $allowedType->isSuperTypeOf($calledOnType)->yes();
    }
}
