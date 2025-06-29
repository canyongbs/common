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

namespace CanyonGBS\Common\Parser\Language;

use CanyonGBS\Common\Parser\LanguageInterface;

class English implements LanguageInterface
{
    public const SUFFIXES = [
        '1st' => '1st',
        '2nd' => '2nd',
        '3rd' => '3rd',
        '4th' => '4th',
        '5th' => '5th',
        'i' => 'I',
        'ii' => 'II',
        'iii' => 'III',
        'iv' => 'IV',
        'v' => 'V',
        'apr' => 'APR',
        'cme' => 'CME',
        'dds' => 'DDS',
        'dmd' => 'DMD',
        'dvm' => 'DVM',
        'esq' => 'Esq',
        'jr' => 'Jr',
        'junior' => 'Junior',
        'ma' => 'MA',
        'md' => 'MD',
        'pe' => 'PE',
        'phd' => 'PhD',
        'rph' => 'RPh',
        'senior' => 'Senior',
        'sr' => 'Sr',
    ];

    public const SALUTATIONS = [
        'dr' => 'Dr.',
        'fr' => 'Fr.',
        'madam' => 'Madam',
        'master' => 'Mr.',
        'miss' => 'Miss',
        'mister' => 'Mr.',
        'mr' => 'Mr.',
        'mrs' => 'Mrs.',
        'ms' => 'Ms.',
        'mx' => 'Mx.',
        'rev' => 'Rev.',
        'sir' => 'Sir',
        'prof' => 'Prof.',
        'his honour' => 'His Honour',
        'her honour' => 'Her Honour',
    ];

    public const LASTNAME_PREFIXES = [
        'da' => 'da',
        'de' => 'de',
        'del' => 'del',
        'della' => 'della',
        'der' => 'der',
        'di' => 'di',
        'du' => 'du',
        'la' => 'la',
        'pietro' => 'pietro',
        'st' => 'st.',
        'ter' => 'ter',
        'van' => 'van',
        'vanden' => 'vanden',
        'vere' => 'vere',
        'von' => 'von',
    ];

    /**
     * @return array<string, string>
     */
    public function getSuffixes(): array
    {
        return self::SUFFIXES;
    }

    /**
     * @return array<string, string>
     */
    public function getSalutations(): array
    {
        return self::SALUTATIONS;
    }

    /**
     * @return array<string, string>
     */
    public function getLastnamePrefixes(): array
    {
        return self::LASTNAME_PREFIXES;
    }
}
