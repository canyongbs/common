<?php

namespace CanyonGBS\Common\Parser\Mapper;

use CanyonGBS\Common\Parser\Part\AbstractPart;
use CanyonGBS\Common\Parser\Part\Suffix;

class SuffixMapper extends AbstractMapper
{
    /** 
     * @var array<string> 
     */
    protected array $suffixes = [];

    protected bool $matchSinglePart = false;

    protected int $reservedParts = 2;

    /**
     * @param array<int, string> $suffixes
     */
    public function __construct(array $suffixes, bool $matchSinglePart = false, int $reservedParts = 2)
    {
        $this->suffixes = $suffixes;
        $this->matchSinglePart = $matchSinglePart;
        $this->reservedParts = $reservedParts;
    }

    /**
     * @param array<int, string|AbstractPart> $parts
     * @return array<int, Suffix|string>
     */
    public function map(array $parts): array
    {
        if ($this->isMatchingSinglePart($parts)) {
            $parts[0] = new Suffix($parts[0], $this->suffixes[$this->getKey($parts[0])]);
            return $parts;
        }

        $start = count($parts) - 1;

        for ($k = $start; $k > $this->reservedParts - 1; $k--) {
            $part = $parts[$k];

            if (!$this->isSuffix($part)) {
                break;
            }

            $parts[$k] = new Suffix($part, $this->suffixes[$this->getKey($part)]);
        }

        return $parts;
    }

    /**
     * @param array<string> $parts
     * @return bool
     */
    protected function isMatchingSinglePart(array $parts): bool
    {
        if (!$this->matchSinglePart) {
            return false;
        }

        if (1 !== count($parts)) {
            return false;
        }

        return $this->isSuffix($parts[0]);
    }

    /**
     * @param string|AbstractPart $part
     * @return bool
     */
    protected function isSuffix(string|AbstractPart $part): bool
    {
        if ($part instanceof AbstractPart) {
            return false;
        }

        return (array_key_exists($this->getKey($part), $this->suffixes));
    }
}
