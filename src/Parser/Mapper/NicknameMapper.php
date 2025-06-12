<?php

namespace CanyonGBS\Common\Parser\Mapper;

use CanyonGBS\Common\Parser\Part\AbstractPart;
use CanyonGBS\Common\Parser\Part\Nickname;

class NicknameMapper extends AbstractMapper
{
    /**
     * @var array<string, string>
     */
    protected array $delimiters = [
        '[' => ']',
        '{' => '}',
        '(' => ')',
        '<' => '>',
        '"' => '"',
        '\'' => '\''
    ];

    /**
     * @param array<string, string> $delimiters
     */
    public function __construct(array $delimiters = [])
    {
        if (!empty($delimiters)) {
            $this->delimiters = $delimiters;
        }
    }

    /**
     * Map nicknames in the parts array
     *
     * @param array<int, string|AbstractPart> $parts the name parts
     * @return array<int, string|AbstractPart|Nickname> the mapped parts
     */
    public function map(array $parts): array
    {
        $isEncapsulated = false;

        $regexp = $this->buildRegexp();

        $closingDelimiter = '';

        foreach ($parts as $k => $part) {
            if ($part instanceof AbstractPart) {
                continue;
            }

            if (preg_match($regexp, $part, $matches)) {
                $isEncapsulated = true;
                $part = substr($part, 1);
                $closingDelimiter = $this->delimiters[$matches[1]];
            }

            if (!$isEncapsulated) {
                continue;
            }

            if ($closingDelimiter === substr($part, -1)) {
                $isEncapsulated = false;
                $part = substr($part, 0, -1);
            }

            $parts[$k] = new Nickname(str_replace(['"', '\''], '', $part));
        }

        return $parts;
    }

    /**
     * @return string
     */
    protected function buildRegexp()
    {
        $regexp = '/^([';

        foreach ($this->delimiters as $opening => $closing) {
            $regexp .= sprintf('\\%s', $opening);
        }

        $regexp .= '])/';

        return $regexp;
    }
}
