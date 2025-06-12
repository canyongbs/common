<?php

namespace CanyonGBS\Common\Parser\Mapper;

use CanyonGBS\Common\Parser\Part\AbstractPart;
use CanyonGBS\Common\Parser\Part\Salutation;

class SalutationMapper extends AbstractMapper
{
    /**
     * @var array<string, string>
     */
    protected array $salutations = [];

    protected int $maxIndex = 0;

    /**
     * @param array<int|string> $salutations
     * @param int $maxIndex
     */
    public function __construct(array $salutations, $maxIndex = 0)
    {
        $this->salutations = $salutations;
        $this->maxIndex = $maxIndex;
    }

    /**
     * Map salutations in the parts array.
     *
     * @param array<int, string> $parts The name parts.
     * @return array<int, string> The mapped parts.
     */
    public function map(array $parts): array
    {
        $max = ($this->maxIndex > 0) ? $this->maxIndex : floor(count($parts) / 2);

        for ($k = 0; $k < $max; $k++) {
            if ($parts[$k] instanceof AbstractPart) {
                break;
            }

            $parts = $this->substituteWithSalutation($parts, $k);
        }

        return $parts;
    }

    /**
     * Substitute salutation in the given parts array starting at a given index.
     *
     * @param array<int, string> $parts
     * @param int $start
     * @return array<int, string|AbstractPart>
     */
    protected function substituteWithSalutation(array $parts, int $start): array
    {
        if ($this->isSalutation($parts[$start])) {
            $parts[$start] = new Salutation($parts[$start], $this->salutations[$this->getKey($parts[$start])]);
            return $parts;
        }

        foreach ($this->salutations as $key => $salutation) {
            $keys = explode(' ', $key);
            $length = count($keys);

            $subset = array_slice($parts, $start, $length);

            if ($this->isMatchingSubset($keys, $subset)) {
                array_splice($parts, $start, $length, [new Salutation(implode(' ', $subset), $salutation)]);
                return $parts;
            }
        }

        return $parts;
    }

    /**
     * Check if the subset matches the given keys.
     *
     * @param array<int, string> $keys
     * @param array<int, string|AbstractPart> $subset
     * @return bool
     */
    private function isMatchingSubset(array $keys, array $subset): bool
    {
        for ($i = 0; $i < count($subset); $i++) {
            if ($this->getKey($subset[$i]) !== $keys[$i]) {
                return false;
            }
        }

        return true;
    }

    /**
     * check if the given word is a viable salutation
     *
     * @param string $word the word to check
     * @return bool
     */
    protected function isSalutation($word): bool
    {
        return (array_key_exists($this->getKey($word), $this->salutations));
    }
}
