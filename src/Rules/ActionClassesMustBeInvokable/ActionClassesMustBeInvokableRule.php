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

namespace CanyonGBS\Common\Rules\ActionClassesMustBeInvokable;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use ReflectionMethod;

/**
 * Enforces Laravel action-pattern classes to expose a single public invocation entrypoint.
 *
 * Classes matched by configured include patterns (and not matched by exclude patterns) must:
 *
 * - define a public "__invoke" method;
 * - not expose any other public methods except "__construct".
 *
 * Private and protected helper methods are allowed.
 *
 * Abstract classes are ignored.
 *
 * @implements Rule<InClassNode>
 */
class ActionClassesMustBeInvokableRule implements Rule
{
    public const string MISSING_INVOKE_ERROR_MESSAGE = 'Action class "%s" must define a public "__invoke" method. Action classes should be accessed via invocation and keep other behavior in private/protected helpers.';

    public const string DISALLOWED_PUBLIC_METHOD_ERROR_MESSAGE = 'Action class "%s" defines disallowed public method "%s". Only "__invoke" and "__construct" may be public on action classes.';

    /**
     * @param list<string> $includePatterns
     * @param list<string> $excludePatterns
     */
    public function __construct(
        private array $includePatterns,
        private array $excludePatterns,
    ) {}

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
     * @return list<RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $classReflection = $node->getClassReflection();

        if (! $classReflection->isClass() || $classReflection->isAbstract()) {
            return [];
        }

        $className = $classReflection->getName();

        if (! $this->isActionClass($className)) {
            return [];
        }

        $errors = [];

        if (! $classReflection->hasNativeMethod('__invoke') || ! $classReflection->getNativeMethod('__invoke')->isPublic()) {
            $errors[] = RuleErrorBuilder::message(sprintf(
                self::MISSING_INVOKE_ERROR_MESSAGE,
                $className,
            ))
                ->identifier('Common.actionClassMustBeInvokable')
                ->build();
        }

        $nativeReflection = $classReflection->getNativeReflection();

        foreach ($nativeReflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $methodName = $method->getName();

            if (in_array($methodName, ['__invoke', '__construct'], true)) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(sprintf(
                self::DISALLOWED_PUBLIC_METHOD_ERROR_MESSAGE,
                $className,
                $methodName,
            ))
                ->identifier('Common.actionClassHasDisallowedPublicMethod')
                ->build();
        }

        return $errors;
    }

    private function isActionClass(string $className): bool
    {
        $namespace = $this->extractNamespace($className);

        if ($namespace === null) {
            return false;
        }

        if (! $this->matchesAnyPattern($namespace, $this->includePatterns)) {
            return false;
        }

        return ! $this->matchesAnyPattern($namespace, $this->excludePatterns);
    }

    private function extractNamespace(string $className): ?string
    {
        $position = strrpos($className, '\\');

        if ($position === false) {
            return null;
        }

        return substr($className, 0, $position);
    }

    /**
     * @param list<string> $patterns
     */
    private function matchesAnyPattern(string $namespace, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($this->matchesPattern($namespace, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function matchesPattern(string $namespace, string $pattern): bool
    {
        $normalizedPattern = trim($pattern, '\\');

        if ($normalizedPattern === '') {
            return false;
        }

        $segments = explode('\\', $normalizedPattern);
        $regexSegments = array_map(static function (string $segment): string {
            return str_replace('\\*', '[^\\\\]*', preg_quote($segment, '#'));
        }, $segments);

        $regex = '#^' . implode('\\\\', $regexSegments) . '(?:\\\\|$)#';

        return preg_match($regex, $namespace) === 1;
    }
}
