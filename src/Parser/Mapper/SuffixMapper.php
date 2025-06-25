<?php

/*
<COPYRIGHT>

    Copyright © 2016-2025, Canyon GBS LLC. All rights reserved.

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
    protected function isMatchingSinglePart($parts): bool
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
    protected function isSuffix($part): bool
    {
        if ($part instanceof AbstractPart) {
            return false;
        }

        return (array_key_exists($this->getKey($part), $this->suffixes));
    }
}
