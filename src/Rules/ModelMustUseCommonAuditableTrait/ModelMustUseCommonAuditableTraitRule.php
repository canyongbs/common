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

namespace CanyonGBS\Common\Rules\ModelMustUseCommonAuditableTrait;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use ReflectionClass;

/**
 * Ensures Eloquent models never use a raw auditing trait (such as "OwenIt\Auditing\Auditable")
 * directly, and instead use "CanyonGBS\Common\Models\Concerns\Auditable", which wraps it with the
 * many-to-many audit overrides the rest of the application relies on.
 *
 * The list of forbidden traits is configurable via the "forbiddenAuditableTraits" parameter and
 * defaults to the vendor auditing trait. Only traits applied directly to the model or one of its
 * ancestor classes are considered — traits pulled in indirectly by the common trait itself are not
 * flagged, so a model that correctly uses the common trait passes.
 *
 * Abstract models are skipped.
 *
 * @implements Rule<InClassNode>
 */
class ModelMustUseCommonAuditableTraitRule implements Rule
{
    public const string MODEL_CLASS = 'Illuminate\Database\Eloquent\Model';

    public const string COMMON_AUDITABLE_TRAIT = 'CanyonGBS\Common\Models\Concerns\Auditable';

    public const string ERROR_MESSAGE = 'Eloquent models must not use the "%s" trait directly. Use "CanyonGBS\Common\Models\Concerns\Auditable" instead, which wraps it with the many-to-many audit overrides.';

    /**
     * @var list<string>
     */
    private array $forbiddenAuditableTraits;

    /**
     * @param list<string> $forbiddenAuditableTraits
     */
    public function __construct(array $forbiddenAuditableTraits)
    {
        $this->forbiddenAuditableTraits = array_map(
            static fn (string $trait): string => ltrim($trait, '\\'),
            $forbiddenAuditableTraits,
        );
    }

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

        $forbiddenTrait = $this->findForbiddenTrait($classReflection);

        if ($forbiddenTrait === null) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(self::ERROR_MESSAGE, $forbiddenTrait))
                ->identifier('Common.modelMustUseCommonAuditableTrait')
                ->build(),
        ];
    }

    private function findForbiddenTrait(ClassReflection $classReflection): ?string
    {
        $current = $classReflection->getNativeReflection();

        while ($current instanceof ReflectionClass) {
            foreach ($current->getTraitNames() as $traitName) {
                $normalized = ltrim($traitName, '\\');

                if (in_array($normalized, $this->forbiddenAuditableTraits, true)) {
                    return $normalized;
                }
            }

            $current = $current->getParentClass() ?: null;
        }

        return null;
    }
}
