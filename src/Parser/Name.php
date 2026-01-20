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

namespace CanyonGBS\Common\Parser;

use CanyonGBS\Common\Parser\Part\AbstractPart;

class Name
{
    private const PARTS_NAMESPACE = 'CanyonGBS\Common\Parser\Part';

    /**
     * @var array<string, mixed>
     */
    protected array $parts = [];

    /**
     * constructor takes the array of parts this name consists of
     *
     * @param array<string, mixed>|null $parts
     */
    public function __construct(?array $parts = null)
    {
        if (null !== $parts) {
            $this->setParts($parts);
        }
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return implode(' ', $this->getAll(true));
    }

    /**
     * set the parts this name consists of
     *
     * @param array<string, mixed> $parts
     *
     * @return $this
     */
    public function setParts(array $parts): Name
    {
        $this->parts = $parts;

        return $this;
    }

    /**
     * get the parts this name consists of
     *
     * @return array<string, mixed>
     */
    public function getParts(): array
    {
        return $this->parts;
    }

    /**
     * @param bool $format
     *
     * @return array<string, mixed>
     */
    public function getAll(bool $format = false): array
    {
        $results = [];
        $keys = [
            'salutation' => [],
            'firstname' => [],
            'nickname' => [$format],
            'middlename' => [],
            'initials' => [],
            'lastname' => [],
            'suffix' => [],
        ];

        foreach ($keys as $key => $args) {
            $method = sprintf('get%s', ucfirst($key));

            if ($value = call_user_func_array([$this, $method], $args)) {
                $results[$key] = $value;
            }
        }

        return $results;
    }

    /**
     * get the given name (first name, middle names and initials)
     * in the order they were entered while still applying normalisation
     *
     * @return string
     */
    public function getGivenName(): string
    {
        return $this->export('GivenNamePart');
    }

    /**
     * get the given name followed by the last name (including any prefixes)
     *
     * @return string
     */
    public function getFullName(): string
    {
        return sprintf('%s %s', $this->getGivenName(), $this->getLastname());
    }

    /**
     * get the first name
     *
     * @return string
     */
    public function getFirstname(): string
    {
        return $this->export('Firstname');
    }

    /**
     * get the last name
     *
     * @param bool $pure
     *
     * @return string
     */
    public function getLastname(bool $pure = false): string
    {
        return $this->export('Lastname', $pure);
    }

    /**
     * get the last name prefix
     *
     * @return string
     */
    public function getLastnamePrefix(): string
    {
        return $this->export('LastnamePrefix');
    }

    /**
     * get the initials
     *
     * @return string
     */
    public function getInitials(): string
    {
        return $this->export('Initial');
    }

    /**
     * get the suffix(es)
     *
     * @return string
     */
    public function getSuffix(): string
    {
        return $this->export('Suffix');
    }

    /**
     * get the salutation(s)
     *
     * @return string
     */
    public function getSalutation(): string
    {
        return $this->export('Salutation');
    }

    /**
     * get the nick name(s)
     *
     * @param bool $wrap
     *
     * @return string
     */
    public function getNickname(bool $wrap = false): string
    {
        if ($wrap) {
            return sprintf('(%s)', $this->export('Nickname'));
        }

        return $this->export('Nickname');
    }

    /**
     * get the middle name(s)
     *
     * @return string
     */
    public function getMiddlename(): string
    {
        return $this->export('Middlename');
    }

    /**
     * helper method used by getters to extract and format relevant name parts
     *
     * @param string $type
     * @param bool $strict
     *
     * @return string
     */
    protected function export(string $type, bool $strict = false): string
    {
        $matched = [];

        foreach ($this->parts as $part) {
            if ($part instanceof AbstractPart && $this->isType($part, $type, $strict)) {
                $matched[] = $part->normalize();
            }
        }

        return implode(' ', $matched);
    }

    /**
     * helper method to check if a part is of the given type
     *
     * @param AbstractPart $part
     * @param string $type
     * @param bool $strict
     *
     * @return bool
     */
    protected function isType(AbstractPart $part, string $type, bool $strict = false): bool
    {
        $className = sprintf('%s\\%s', self::PARTS_NAMESPACE, $type);

        if ($strict) {
            return get_class($part) === $className;
        }

        return is_a($part, $className);
    }
}
