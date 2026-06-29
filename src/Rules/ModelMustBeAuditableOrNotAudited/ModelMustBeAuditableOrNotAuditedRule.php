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

namespace CanyonGBS\Common\Rules\ModelMustBeAuditableOrNotAudited;

use CanyonGBS\Common\Models\Attributes\NotAudited;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Ensures every Eloquent model (a subclass of Illuminate\Database\Eloquent\Model) is either
 * audited or has been explicitly, deliberately excluded from auditing.
 *
 * Most of our systems use "owen-it/laravel-auditing" to record changes to their models, so a
 * model satisfies this rule when it implements "OwenIt\Auditing\Contracts\Auditable" (directly
 * or through a parent class).
 *
 * Some models that users never interact with do not need to be audited. That must always be a
 * distinct, senior-approved decision rather than something silently ignored, so the only other
 * way to satisfy this rule is to apply the "#[NotAudited]" attribute directly to the model. The
 * attribute is honoured only on the exact class it is declared on; it is not inherited by
 * subclasses.
 *
 * This rule is intentionally NOT registered in the package's shared PHPStan extension. Projects
 * opt in by adding it to the "rules:" section of their own PHPStan configuration, which allows
 * the single project that does not use auditing to simply leave it out.
 *
 * Abstract models are skipped.
 *
 * @implements Rule<InClassNode>
 */
class ModelMustBeAuditableOrNotAuditedRule implements Rule
{
    public const string MODEL_CLASS = 'Illuminate\Database\Eloquent\Model';

    public const string AUDITABLE_INTERFACE = 'OwenIt\Auditing\Contracts\Auditable';

    public const string ERROR_MESSAGE = 'Eloquent models must implement "OwenIt\Auditing\Contracts\Auditable" so that changes to them are audited. Do NOT ignore or suppress this error. If this model genuinely should not be audited, that must be a deliberate decision approved by a senior engineer — in that case add the "#[\CanyonGBS\Common\Models\Attributes\NotAudited]" attribute to the model instead of ignoring this error.';

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

        if (! $classReflection->isSubclassOf(self::MODEL_CLASS)) {
            return [];
        }

        if ($this->isAuditable($classReflection)) {
            return [];
        }

        if ($this->isMarkedNotAudited($classReflection)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(self::ERROR_MESSAGE)
                ->identifier('Common.modelMustBeAuditableOrNotAudited')
                ->build(),
        ];
    }

    private function isAuditable(ClassReflection $classReflection): bool
    {
        return $classReflection->implementsInterface(self::AUDITABLE_INTERFACE);
    }

    private function isMarkedNotAudited(ClassReflection $classReflection): bool
    {
        return $classReflection->getNativeReflection()->getAttributes(NotAudited::class) !== [];
    }
}
