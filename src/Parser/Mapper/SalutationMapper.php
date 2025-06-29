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
     *
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
     *
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
     * check if the given word is a viable salutation
     *
     * @param string $word the word to check
     *
     * @return bool
     */
    protected function isSalutation($word): bool
    {
        return (array_key_exists($this->getKey($word), $this->salutations));
    }

    /**
     * Check if the subset matches the given keys.
     *
     * @param array<int, string> $keys
     * @param array<int, string|AbstractPart> $subset
     *
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
}
