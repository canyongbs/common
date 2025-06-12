<?php

namespace CanyonGBS\Common\Parser;

use CanyonGBS\Common\Parser\Language\English;
use CanyonGBS\Common\Parser\Mapper\NicknameMapper;
use CanyonGBS\Common\Parser\Mapper\SalutationMapper;
use CanyonGBS\Common\Parser\Mapper\SuffixMapper;
use CanyonGBS\Common\Parser\Mapper\InitialMapper;
use CanyonGBS\Common\Parser\Mapper\LastnameMapper;
use CanyonGBS\Common\Parser\Mapper\FirstnameMapper;
use CanyonGBS\Common\Parser\Mapper\MiddlenameMapper;

class Parser
{
    /**
     * @var string
     */
    protected string $whitespace = " \r\n\t";

    /**
     * @var array<int, FirstnameMapper|LastnameMapper|MiddlenameMapper|SalutationMapper|SuffixMapper|NicknameMapper|InitialMapper>
     */
    protected array $mappers = [];

    /**
     * @var array<string>
     */
    protected array $languages = [];

    /**
     * @var array<string, string>
     */
    protected array $nicknameDelimiters = [];

    /**
     * @var int
     */
    protected int $maxSalutationIndex = 0;

    /**
     * @var int
     */
    protected $maxCombinedInitials = 2;

    /**
     * @param array<string> $languages
     */
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
     * Handles split-parsing of comma-separated name parts
     *
     * @param string $first  The name part on the left
     * @param string $second The name part in the middle
     * @param string $third  The name part on the right
     *
     * @return Name
     */
    protected function parseSplitName(string $first, string $second, string $third): Name
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
     * Get the mappers for this parser
     *
     * @return array<int, FirstnameMapper|LastnameMapper|MiddlenameMapper|SalutationMapper|SuffixMapper|NicknameMapper|InitialMapper>
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
     * @param array<int, FirstnameMapper|LastnameMapper|MiddlenameMapper|SalutationMapper|SuffixMapper|NicknameMapper|InitialMapper> $mappers
     *
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

        return preg_replace('/[' . preg_quote($whitespace, '/') . ']+/', ' ', $name);
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
     * @param string $whitespace
     * @return Parser
     */
    public function setWhitespace(string $whitespace): Parser
    {
        $this->whitespace = $whitespace;

        return $this;
    }

    /**
     * @return array<string, string>
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
     * @return array<int, string>
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
     * @return array<string, string>
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
     * @return array<string, string>
     */
    public function getNicknameDelimiters(): array
    {
        return $this->nicknameDelimiters;
    }

    /**
     * @param array<string, string> $nicknameDelimiters
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