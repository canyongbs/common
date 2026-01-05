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

namespace CanyonGBS\Common\Parser\Part;

abstract class AbstractPart
{
    /**
     * @var string the wrapped value
     */
    protected $value;

    /**
     * Constructor allows passing the value to wrap
     *
     * @param string|AbstractPart $value
     */
    public function __construct(string|AbstractPart $value)
    {
        $this->setValue($value);
    }

    /**
     * set the value to wrap
     * (can take string or part instance)
     *
     * @param string|AbstractPart $value
     *
     * @return $this
     */
    public function setValue($value): AbstractPart
    {
        if ($value instanceof AbstractPart) {
            $value = $value->getValue();
        }

        $this->value = $value;

        return $this;
    }

    /**
     * get the wrapped value
     *
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * get the normalized value
     *
     * @return string
     */
    public function normalize(): string
    {
        return $this->getValue();
    }

    /**
     * helper for camelization of values
     * to be used during normalize
     *
     * @param $word
     *
     * @return string
     */
    protected function camelcase(string $word): string
    {
        if (preg_match('/\p{L}(\p{Lu}*\p{Ll}\p{Ll}*\p{Lu}|\p{Ll}*\p{Lu}\p{Lu}*\p{Ll})\p{L}*/u', $word)) {
            return $word;
        }

        return preg_replace_callback('/[\p{L}0-9]+/ui', [$this, 'camelcaseReplace'], $word);
    }

    /**
     * camelcasing callback
     *
     * @param array<int, string> $matches
     *
     * @return string
     */
    protected function camelcaseReplace(array $matches): string
    {
        if (function_exists('mb_convert_case')) {
            return mb_convert_case($matches[0], MB_CASE_TITLE, 'UTF-8');
        }

        return ucfirst(strtolower($matches[0]));
    }
}
