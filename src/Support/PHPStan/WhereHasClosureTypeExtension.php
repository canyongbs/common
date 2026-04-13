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

use function count;
use function explode;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

use function in_array;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\OutOfClassScope;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\Native\NativeParameterReflection;
use PHPStan\Reflection\ParameterReflection;
use PHPStan\Reflection\PassedByReference;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\ClosureType;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\Generic\TemplateObjectType;
use PHPStan\Type\MethodParameterClosureTypeExtension;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

use function str_contains;

class WhereHasClosureTypeExtension implements MethodParameterClosureTypeExtension
{
    protected const SUPPORTED_METHODS = [
        'whereHas',
        'orWhereHas',
        'whereDoesntHave',
        'orWhereDoesntHave',
        'withWhereHas',
        'whereRelation',
    ];

    public function __construct(
        protected ReflectionProvider $reflectionProvider,
    ) {}

    public function isMethodSupported(MethodReflection $methodReflection, ParameterReflection $parameter): bool
    {
        if (! $methodReflection->getDeclaringClass()->is(EloquentBuilder::class)) {
            return false;
        }

        if (! in_array($methodReflection->getName(), self::SUPPORTED_METHODS, true)) {
            return false;
        }

        $parameterName = $parameter->getName();

        if ($methodReflection->getName() === 'whereRelation') {
            return $parameterName === 'column';
        }

        return $parameterName === 'callback';
    }

    public function getTypeFromMethodCall(
        MethodReflection $methodReflection,
        MethodCall $methodCall,
        ParameterReflection $parameter,
        Scope $scope,
    ): ?Type {
        $callerType = $scope->getType($methodCall->var);

        $modelType = $this->resolveModelType($callerType);

        if ($modelType === null) {
            return null;
        }

        $args = $methodCall->getArgs();

        if ($args === []) {
            return null;
        }

        $relationNameType = $scope->getType($args[0]->value);
        $constantStrings = $relationNameType->getConstantStrings();

        if ($constantStrings === []) {
            return null;
        }

        $relationName = $constantStrings[0]->getValue();

        $relatedModelType = $this->resolveRelatedModel($modelType, $relationName);

        if ($relatedModelType === null) {
            return null;
        }

        $relatedModelReflections = $relatedModelType->getObjectClassReflections();

        if ($relatedModelReflections === []) {
            return null;
        }

        $relatedModelClassName = $relatedModelReflections[0]->getName();

        $builderName = $this->determineBuilderName($relatedModelClassName);
        $builderType = new GenericObjectType($builderName, [new ObjectType($relatedModelClassName)]);

        return new ClosureType(
            [
                new NativeParameterReflection( // @phpstan-ignore phpstanApi.constructor
                    'query',
                    false,
                    $builderType,
                    PassedByReference::createNo(),
                    false,
                    null,
                ),
            ],
            new MixedType(),
        );
    }

    protected function resolveModelType(Type $callerType): ?Type
    {
        $classReflections = $callerType->getObjectClassReflections();

        foreach ($classReflections as $classReflection) {
            $modelType = $this->resolveModelTypeFromReflection($classReflection);

            if ($modelType !== null) {
                return $modelType;
            }
        }

        return null;
    }

    protected function resolveModelTypeFromReflection(ClassReflection $classReflection): ?Type
    {
        $loopReflection = $classReflection;

        do {
            $modelType = $loopReflection->getActiveTemplateTypeMap()->getType('TModel');

            if ($modelType !== null) {
                return $modelType;
            }

            $loopReflection = $loopReflection->getParentClass();
        } while ($loopReflection !== null);

        return null;
    }

    protected function resolveRelatedModel(Type $modelType, string $relationName): ?Type
    {
        if (str_contains($relationName, '.')) {
            return $this->resolveNestedRelation($modelType, $relationName);
        }

        return $this->resolveDirectRelation($modelType, $relationName);
    }

    protected function resolveNestedRelation(Type $modelType, string $relationName): ?Type
    {
        $segments = explode('.', $relationName);
        $currentModelType = $modelType;

        foreach ($segments as $segment) {
            $currentModelType = $this->resolveDirectRelation($currentModelType, $segment);

            if ($currentModelType === null) {
                return null;
            }
        }

        return $currentModelType;
    }

    protected function resolveDirectRelation(Type $modelType, string $relationName): ?Type
    {
        if ($modelType instanceof TemplateObjectType) {
            $modelType = $modelType->getBound();
        }

        $modelReflections = $modelType->getObjectClassReflections();

        if ($modelReflections === []) {
            return null;
        }

        $modelReflection = $modelReflections[0];

        if (! $modelReflection->hasMethod($relationName)) {
            return null;
        }

        $relationMethod = $modelReflection->getMethod($relationName, new OutOfClassScope());
        $returnType = $relationMethod->getVariants()[0]->getReturnType();

        $returnTypeReflections = $returnType->getObjectClassReflections();

        foreach ($returnTypeReflections as $returnTypeReflection) {
            if (! $returnTypeReflection->is(Relation::class)) {
                continue;
            }

            $relatedModel = $returnTypeReflection->getActiveTemplateTypeMap()->getType('TRelatedModel');

            if ($relatedModel !== null) {
                return $relatedModel;
            }
        }

        return null;
    }

    protected function determineBuilderName(string $modelClassName): string
    {
        if (! $this->reflectionProvider->hasClass($modelClassName)) {
            return EloquentBuilder::class;
        }

        $modelReflection = $this->reflectionProvider->getClass($modelClassName);

        if (! $modelReflection->hasNativeMethod('newEloquentBuilder')) {
            return EloquentBuilder::class;
        }

        $method = $modelReflection->getNativeMethod('newEloquentBuilder');

        if ($method->getDeclaringClass()->getName() === Model::class) {
            return EloquentBuilder::class;
        }

        $returnType = $method->getVariants()[0]->getReturnType();
        $classNames = $returnType->getObjectClassNames();

        if (count($classNames) === 1) {
            return $classNames[0];
        }

        return EloquentBuilder::class;
    }
}
