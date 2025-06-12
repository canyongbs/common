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
use CanyonGBS\Common\Parser\Part\Firstname;
use CanyonGBS\Common\Parser\Part\Initial;
use CanyonGBS\Common\Parser\Part\Lastname;
use CanyonGBS\Common\Parser\Part\Salutation;

class FirstnameMapper extends AbstractMapper
{
    /**
     * Map firstnames in parts array.
     *
     * @param array<int, string|AbstractPart> $parts The parts
     *
     * @return array<int, string|AbstractPart> The mapped parts
     */
    public function map(array $parts): array
    {
        if (count($parts) < 2) {
            return [$this->handleSinglePart($parts[0])];
        }

        $pos = $this->findFirstnamePosition($parts);

        if (null !== $pos) {
            $parts[$pos] = new Firstname($parts[$pos]);
        }

        return $parts;
    }

    /**
     * @param string|AbstractPart $part
     *
     * @return AbstractPart
     */
    protected function handleSinglePart($part): AbstractPart
    {
        if ($part instanceof AbstractPart) {
            return $part;
        }

        return new Firstname($part);
    }

    /**
     * @param array<int, AbstractPart|string|mixed> $parts
     *
     * @return int|null
     */
    protected function findFirstnamePosition(array $parts): ?int
    {
        $pos = null;

        $length = count($parts);
        $start = $this->getStartIndex($parts);

        for ($k = $start; $k < $length; $k++) {
            $part = $parts[$k];

            if ($part instanceof Lastname) {
                break;
            }

            if ($part instanceof Initial && null === $pos) {
                $pos = $k;
            }

            if ($part instanceof AbstractPart) {
                continue;
            }

            return $k;
        }

        return $pos;
    }

    /**
     * @param array<int, AbstractPart> $parts
     *
     * @return int
     */
    protected function getStartIndex(array $parts): int
    {
        $index = $this->findFirstMapped(Salutation::class, $parts);

        if (false === $index) {
            return 0;
        }

        if ($index === count($parts) - 1) {
            return 0;
        }

        return $index + 1;
    }
}
