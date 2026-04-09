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

declare(strict_types = 1);

namespace CanyonGBS\Common\Support\PHPStan;

use function array_key_exists;
use function array_map;
use function array_merge;

use CanyonGBS\Common\Models\Concerns\CanBeArchived;
use Illuminate\Database\Eloquent\Relations\Relation;

use function in_array;

use Larastan\Larastan\Reflection\EloquentBuilderMethodReflection;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;
use PHPStan\Type\Generic\TemplateObjectType;
use PHPStan\Type\ThisType;

/**
 * Recognizes CanBeArchived methods (withoutArchived, onlyArchived, etc.) on
 * Eloquent Relation classes. When the relation has a concrete generic return
 * type (e.g., `@return BelongsTo<Project, $this>`), the extension can verify
 * whether the related model uses the CanBeArchived trait. When the concrete
 * type is not available (missing docblock or unresolvable generics), the
 * methods are not recognized.
 */
class CanBeArchivedRelationExtension implements MethodsClassReflectionExtension
{
    protected const METHODS = ['withoutArchived', 'onlyArchived', 'withoutArchivedAndUnused', 'archive', 'unarchive'];

    /** @var array<string, MethodReflection> */
    protected array $cache = [];

    public function hasMethod(ClassReflection $classReflection, string $methodName): bool
    {
        if (array_key_exists($classReflection->getCacheKey() . '-' . $methodName, $this->cache)) {
            return true;
        }

        $methodReflection = $this->findMethod($classReflection, $methodName);

        if ($methodReflection !== null) {
            $this->cache[$classReflection->getCacheKey() . '-' . $methodName] = $methodReflection;

            return true;
        }

        return false;
    }

    public function getMethod(ClassReflection $classReflection, string $methodName): MethodReflection
    {
        return $this->cache[$classReflection->getCacheKey() . '-' . $methodName];
    }

    protected function findMethod(ClassReflection $classReflection, string $methodName): MethodReflection|null
    {
        if (! $classReflection->is(Relation::class)) {
            return null;
        }

        if (! in_array($methodName, self::METHODS, true)) {
            return null;
        }

        $relatedModel = $classReflection->getActiveTemplateTypeMap()->getType('TRelatedModel');

        if ($relatedModel === null) {
            return null;
        }

        if ($relatedModel instanceof TemplateObjectType) {
            $relatedModel = $relatedModel->getBound();
        }

        $reflections = $relatedModel->getObjectClassReflections();

        if ($reflections === []) {
            return null;
        }

        if (! array_key_exists(
            CanBeArchived::class,
            array_merge(...array_map(
                static fn (ClassReflection $classRef) => $classRef->getTraits(true),
                $reflections,
            )),
        )) {
            return null;
        }

        return new EloquentBuilderMethodReflection(
            $methodName,
            $classReflection,
            [],
            new ThisType($classReflection),
        );
    }
}
