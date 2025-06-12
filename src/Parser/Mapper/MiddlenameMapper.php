<?php

namespace CanyonGBS\Common\Parser\Mapper;

use CanyonGBS\Common\Parser\Part\AbstractPart;
use CanyonGBS\Common\Parser\Part\Firstname;
use CanyonGBS\Common\Parser\Part\Lastname;
use CanyonGBS\Common\Parser\Part\Middlename;

class MiddlenameMapper extends AbstractMapper
{
    protected bool $mapWithoutLastname = false;

    public function __construct(bool $mapWithoutLastname = false)
    {
        $this->mapWithoutLastname = $mapWithoutLastname;
    }

    /**
     * Map middlenames in the parts array
     *
     * @param array<int, string|AbstractPart> $parts the name parts
     * @return array<int, string|AbstractPart|Firstname|Middlename> the mapped parts
     */
    public function map(array $parts): array
    {
        // If we don't expect a lastname, match a mimimum of 2 parts
        $minumumParts = ($this->mapWithoutLastname ? 2 : 3);

        if (count($parts) < $minumumParts) {
            return $parts;
        }

        $start = $this->findFirstMapped(Firstname::class, $parts);

        if (false === $start) {
            return $parts;
        }

        return $this->mapFrom($start, $parts);
    }

   /**
     * @param int $start
     * @param array<int, string|AbstractPart> $parts
     * @return array<int, string|AbstractPart|Middlename>
     */
    protected function mapFrom($start, $parts): array
    {
        // If we don't expect a lastname, include the last part,
        // otherwise skip the last (-1) because it should be a lastname
        $length = count($parts) - ($this->mapWithoutLastname ? 0 : 1);

        for ($k = $start; $k < $length; $k++) {
            $part = $parts[$k];

            if ($part instanceof Lastname) {
                break;
            }

            if ($part instanceof AbstractPart) {
                continue;
            }

            $parts[$k] = new Middlename($part);
        }

        return $parts;
    }
}
