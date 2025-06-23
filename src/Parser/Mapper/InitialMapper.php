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
use CanyonGBS\Common\Parser\Part\Initial;

/**
 * single letter, possibly followed by a period
 */
class InitialMapper extends AbstractMapper
{
    protected $matchLastPart = false;

    private $combinedMax = 2;

    public function __construct(int $combinedMax = 2, bool $matchLastPart = false)
    {
        $this->matchLastPart = $matchLastPart;
        $this->combinedMax = $combinedMax;
    }

    /**
     * map intials in parts array
     *
     * @param array $parts the name parts
     * @return array the mapped parts
     */
    public function map(array $parts): array
    {
        $last = count($parts) - 1;

        for ($k = 0; $k < count($parts); $k++) {
            $part = $parts[$k];

            if ($part instanceof AbstractPart) {
                continue;
            }

            if (!$this->matchLastPart && $k === $last) {
                continue;
            }

            if (strtoupper($part) === $part) {
                $stripped = str_replace('.', '', $part);
                $length = strlen($stripped);

                if (1 < $length && $length <= $this->combinedMax) {
                    array_splice($parts, $k, 1, str_split($stripped));
                    $last = count($parts) - 1;
                    $part = $parts[$k];
                }
            }

            if ($this->isInitial($part)) {
                $parts[$k] = new Initial($part);
            }
        }

        return $parts;
    }

    /**
     * @param string $part
     * @return bool
     */
    protected function isInitial(string $part): bool
    {
        $length = strlen($part);

        if (1 === $length) {
            return true;
        }

        return ($length === 2 && substr($part, -1) ===  '.');
    }
}
