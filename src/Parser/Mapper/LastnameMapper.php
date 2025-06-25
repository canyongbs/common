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
use CanyonGBS\Common\Parser\Part\Lastname;
use CanyonGBS\Common\Parser\Part\LastnamePrefix;
use CanyonGBS\Common\Parser\Part\Nickname;
use CanyonGBS\Common\Parser\Part\Salutation;
use CanyonGBS\Common\Parser\Part\Suffix;

class LastnameMapper extends AbstractMapper
{
    /** @var array<string> */
    protected array $prefixes = [];

    protected bool $matchSinglePart = false;

    /**
     * @param array<string> $prefixes
     */
    public function __construct(array $prefixes, bool $matchSinglePart = false)
    {
        $this->prefixes = $prefixes;
        $this->matchSinglePart = $matchSinglePart;
    }

    /**
     * Map lastnames in the parts array
     *
     * @param array<string> $parts The name parts
     * @return array<string|AbstractPart|Lastname>
     */
    public function map(array $parts): array
    {
        if (!$this->matchSinglePart && count($parts) < 2) {
            return $parts;
        }

        return $this->mapParts($parts);
    }

    /**
     * We map the parts in reverse order because it makes more
     * sense to parse for the lastname starting from the end
     *
     * @param array<string|AbstractPart> $parts
     * @return array<string|AbstractPart|Lastname>
     */
    protected function mapParts(array $parts): array
    {
        $k = $this->skipIgnoredParts($parts) + 1;
        $remapIgnored = true;

        while (--$k >= 0) {
            $part = $parts[$k];

            if ($part instanceof AbstractPart) {
                break;
            }

            if ($this->isFollowedByLastnamePart($parts, $k)) {
                if ($mapped = $this->mapAsPrefixIfPossible($parts, $k)) {
                    $parts[$k] = $mapped;
                    continue;
                }

                if ($this->shouldStopMapping($parts, $k)) {
                    break;
                }
            }

            $parts[$k] = new Lastname($part);
            $remapIgnored = false;
        }

        if ($remapIgnored) {
            $parts = $this->remapIgnored($parts);
        }

        return $parts;
    }

    /**
     * Try to map this part as a lastname prefix or as a combined
     * lastname part containing a prefix
     *
     * @param array<int, string|AbstractPart> $parts
     * @param int $k
     * @return Lastname|null
     */
    private function mapAsPrefixIfPossible(array $parts, int $k): ?Lastname
    {
        if ($this->isApplicablePrefix($parts, $k)) {
            return new LastnamePrefix($parts[$k], $this->prefixes[$this->getKey($parts[$k])]);
        }

        if ($this->isCombinedWithPrefix($parts[$k])) {
            return new Lastname($parts[$k]);
        }

        return null;
    }

    /**
     * check if the given part is a combined lastname part
     * that ends in a lastname prefix
     *
     * @param string $part
     * @return bool
     */
    private function isCombinedWithPrefix(string $part): bool
    {
        $pos = strpos($part, '-');

        if (false === $pos) {
            return false;
        }

        return $this->isPrefix(substr($part, $pos + 1));
    }

    /**
     * Skip through the parts we want to ignore and return the start index
     *
     * @param array<int, string|AbstractPart> $parts
     * @return int
     */
    protected function skipIgnoredParts(array $parts): int
    {
        $k = count($parts);

        while (--$k >= 0) {
            if (!$this->isIgnoredPart($parts[$k])) {
                break;
            }
        }

        return $k;
    }

    /**
     * Indicates if we should stop mapping at the given index $k
     *
     * The assumption is that lastname parts have already been found
     * but we want to see if we should add more parts
     *
     * @param array<int, AbstractPart> $parts
     * @param int $k
     * @return bool
     */
    protected function shouldStopMapping(array $parts, int $k): bool
    {
        if ($k < 1) {
            return true;
        }

        $lastPart = $parts[$k + 1];

        if ($lastPart instanceof LastnamePrefix) {
            return true;
        }



        return strlen($lastPart->getValue()) >= 3;
    }

    /**
     * Indicates if the given part should be ignored (skipped) during mapping.
     *
     * @param string|AbstractPart $part
     * @return bool
     */
    protected function isIgnoredPart($part) {
        return $part instanceof Suffix || $part instanceof Nickname || $part instanceof Salutation;
    }

    /**
     * Remap ignored parts as lastname.
     *
     * If the mapping did not derive any lastname, this is called to transform
     * any previously ignored parts into lastname parts.
     *
     * @param array<int, string|AbstractPart> $parts
     * @return array<int, AbstractPart>
     */
    protected function remapIgnored(array $parts): array
    {
        $k = count($parts);

        while (--$k >= 0) {
            $part = $parts[$k];

            if (!$this->isIgnoredPart($part)) {
                break;
            }

            $parts[$k] = new Lastname($part);
        }

        return $parts;
    }

    /**
     * Check if the part at the given index is followed by a Lastname.
     *
     * @param array<int, string|AbstractPart> $parts
     * @param int $index
     * @return bool
     */
    protected function isFollowedByLastnamePart(array $parts, int $index): bool
    {
        $next = $this->skipNicknameParts($parts, $index + 1);

        return (isset($parts[$next]) && $parts[$next] instanceof Lastname);
    }

    /**
     * Assuming that the part at the given index is matched as a prefix,
     * determines if the prefix should be applied to the lastname.
     *
     * We only apply it to the lastname if we already have at least one
     * lastname part and there are other parts left in
     * the name (this effectively prioritises firstname over prefix matching).
     *
     * This expects the parts array and index to be in the original order.
     *
     * @param array<int, string|AbstractPart> $parts
     * @param int $index
     * @return bool
     */
    protected function isApplicablePrefix(array $parts, int $index): bool
    {
        if (!$this->isPrefix($parts[$index])) {
            return false;
        }

        return $this->hasUnmappedPartsBefore($parts, $index);
    }

    /**
     * check if the given word is a lastname prefix
     *
     * @param string $word the word to check
     * @return bool
     */
    protected function isPrefix($word): bool
    {
        return (array_key_exists($this->getKey($word), $this->prefixes));
    }

    /**
     * Find the next non-nickname index in parts.
     *
     * @param array<int, string|AbstractPart> $parts
     * @param int $startIndex
     * @return int
     */
    protected function skipNicknameParts($parts, $startIndex)
    {
        $total = count($parts);

        for ($i = $startIndex; $i < $total; $i++) {
            if (!($parts[$i] instanceof Nickname)) {
                return $i;
            }
        }

        return $total - 1;
    }
}
