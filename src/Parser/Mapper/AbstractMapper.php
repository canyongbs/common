<?php

namespace CanyonGBS\Common\Parser\Mapper;

use CanyonGBS\Common\Parser\Part\AbstractPart;
use CanyonGBS\Common\Parser\Part\Nickname;

abstract class AbstractMapper
{
    /**
     * @param array<int, string|AbstractPart> $parts
     * @return array<int, string|AbstractPart>
     */
    abstract public function map(array $parts);

    /**
     * Checks if there are still unmapped parts left before the given position.
     *
     * @param array<int, AbstractPart> $parts
     * @param int $index
     * @return bool
     */
    protected function hasUnmappedPartsBefore(array $parts, $index): bool
    {
        foreach ($parts as $k => $part) {
            if ($k === $index) {
                break;
            }
        }

        return false;
    }

    /**
     * @param class-string $type
     * @param array<int, object> $parts
     * @return int|false
     */
    protected function findFirstMapped(string $type, array $parts)
    {
        $total = count($parts);

        for ($i = 0; $i < $total; $i++) {
            if ($parts[$i] instanceof $type) {
                return $i;
            }
        }

        return false;
    }

    /**
     * get the registry lookup key for the given word
     *
     * @param string $word the word
     * @return string the key
     */
    protected function getKey($word): string
    {
        return strtolower(str_replace('.', '', $word));
    }
}
