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

namespace CanyonGBS\Common\Parser;

use CanyonGBS\Common\Parser\Language\English;
use CanyonGBS\Common\Parser\Mapper\FirstnameMapper;
use CanyonGBS\Common\Parser\Mapper\InitialMapper;
use CanyonGBS\Common\Parser\Mapper\LastnameMapper;
use CanyonGBS\Common\Parser\Mapper\MiddlenameMapper;
use CanyonGBS\Common\Parser\Mapper\NicknameMapper;
use CanyonGBS\Common\Parser\Mapper\SalutationMapper;
use CanyonGBS\Common\Parser\Mapper\SuffixMapper;

class Parser
{
    /**
     * @var string
     */
    protected $whitespace = " \r\n\t";

    /**
     * @var array
     */
    protected $mappers = [];

    /**
     * @var array
     */
    protected $languages = [];

    /**
     * @var array
     */
    protected $nicknameDelimiters = [];

    /**
     * @var int
     */
    protected $maxSalutationIndex = 0;

    /**
     * @var int
     */
    protected $maxCombinedInitials = 2;

    public function __construct(array $languages = [])
    {
        if (empty($languages)) {
            $languages = [new English()];
        }

        $this->languages = $languages;
    }

    /**
     * split full names into the following parts:
     * - prefix / salutation  (Mr., Mrs., etc)
     * - given name / first name
     * - middle initials
     * - surname / last name
     * - suffix (II, Phd, Jr, etc)
     *
     * @param string $name
     * @return Name
     */
    public function parse($name): Name
    {
        $name = $this->normalize($name);

        $segments = explode(',', $name);

        if (1 < count($segments)) {
            return $this->parseSplitName($segments[0], $segments[1], $segments[2] ?? '');
        }

        $parts = explode(' ', $name);

        foreach ($this->getMappers() as $mapper) {
            $parts = $mapper->map($parts);
        }

        return new Name($parts);
    }

    /**
     * handles split-parsing of comma-separated name parts
     *
     * @param $left - the name part left of the comma
     * @param $right - the name part right of the comma
     *
     * @return Name
     */
    protected function parseSplitName($first, $second, $third): Name
    {
        $parts = array_merge(
            $this->getFirstSegmentParser()->parse($first)->getParts(),
            $this->getSecondSegmentParser()->parse($second)->getParts(),
            $this->getThirdSegmentParser()->parse($third)->getParts()
        );

        return new Name($parts);
    }

    /**
     * @return Parser
     */
    protected function getFirstSegmentParser(): Parser
    {
        $parser = new Parser();

        $parser->setMappers([
            new SalutationMapper($this->getSalutations(), $this->getMaxSalutationIndex()),
            new SuffixMapper($this->getSuffixes(), false, 2),
            new LastnameMapper($this->getPrefixes(), true),
            new FirstnameMapper(),
            new MiddlenameMapper(),
        ]);

        return $parser;
    }

    /**
     * @return Parser
     */
    protected function getSecondSegmentParser(): Parser
    {
        $parser = new Parser();

        $parser->setMappers([
            new SalutationMapper($this->getSalutations(), $this->getMaxSalutationIndex()),
            new SuffixMapper($this->getSuffixes(), true, 1),
            new NicknameMapper($this->getNicknameDelimiters()),
            new InitialMapper($this->getMaxCombinedInitials(), true),
            new FirstnameMapper(),
            new MiddlenameMapper(true),
        ]);

        return $parser;
    }

    protected function getThirdSegmentParser(): Parser
    {
        $parser = new Parser();

        $parser->setMappers([
            new SuffixMapper($this->getSuffixes(), true, 0),
        ]);

        return $parser;
    }

    /**
     * get the mappers for this parser
     *
     * @return array
     */
    public function getMappers(): array
    {
        if (empty($this->mappers)) {
            $this->setMappers([
                new NicknameMapper($this->getNicknameDelimiters()),
                new SalutationMapper($this->getSalutations(), $this->getMaxSalutationIndex()),
                new SuffixMapper($this->getSuffixes()),
                new InitialMapper($this->getMaxCombinedInitials()),
                new LastnameMapper($this->getPrefixes()),
                new FirstnameMapper(),
                new MiddlenameMapper(),
            ]);
        }

        return $this->mappers;
    }

    /**
     * set the mappers for this parser
     *
     * @param array $mappers
     * @return Parser
     */
    public function setMappers(array $mappers): Parser
    {
        $this->mappers = $mappers;

        return $this;
    }

    /**
     * normalize the name
     *
     * @param string $name
     * @return string
     */
    protected function normalize(string $name): string
    {
        $whitespace = $this->getWhitespace();

        $name = trim($name);

        return preg_replace('/[' . preg_quote($whitespace) . ']+/', ' ', $name);
    }

    /**
     * get a string of characters that are supposed to be treated as whitespace
     *
     * @return string
     */
    public function getWhitespace(): string
    {
        return $this->whitespace;
    }

    /**
     * set the string of characters that are supposed to be treated as whitespace
     *
     * @param $whitespace
     * @return Parser
     */
    public function setWhitespace($whitespace): Parser
    {
        $this->whitespace = $whitespace;

        return $this;
    }

    /**
     * @return array
     */
    protected function getPrefixes()
    {
        $prefixes = [];

        /** @var LanguageInterface $language */
        foreach ($this->languages as $language) {
            $prefixes += $language->getLastnamePrefixes();
        }

        return $prefixes;
    }

    /**
     * @return array
     */
    protected function getSuffixes()
    {
        $suffixes = [];

        /** @var LanguageInterface $language */
        foreach ($this->languages as $language) {
            $suffixes += $language->getSuffixes();
        }

        return $suffixes;
    }

    /**
     * @return array
     */
    protected function getSalutations()
    {
        $salutations = [];

        /** @var LanguageInterface $language */
        foreach ($this->languages as $language) {
            $salutations += $language->getSalutations();
        }

        return $salutations;
    }

    /**
     * @return array
     */
    public function getNicknameDelimiters(): array
    {
        return $this->nicknameDelimiters;
    }

    /**
     * @param array $nicknameDelimiters
     * @return Parser
     */
    public function setNicknameDelimiters(array $nicknameDelimiters): Parser
    {
        $this->nicknameDelimiters = $nicknameDelimiters;

        return $this;
    }

    /**
     * @return int
     */
    public function getMaxSalutationIndex(): int
    {
        return $this->maxSalutationIndex;
    }

    /**
     * @param int $maxSalutationIndex
     * @return Parser
     */
    public function setMaxSalutationIndex(int $maxSalutationIndex): Parser
    {
        $this->maxSalutationIndex = $maxSalutationIndex;

        return $this;
    }

    /**
     * @return int
     */
    public function getMaxCombinedInitials(): int
    {
        return $this->maxCombinedInitials;
    }

    /**
     * @param int $maxCombinedInitials
     * @return Parser
     */
    public function setMaxCombinedInitials(int $maxCombinedInitials): Parser
    {
        $this->maxCombinedInitials = $maxCombinedInitials;

        return $this;
    }
}
