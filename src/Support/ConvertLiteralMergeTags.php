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

namespace CanyonGBS\Common\Support;

use Illuminate\Support\Str;

class ConvertLiteralMergeTags
{
    /**
     * @var array<int, string>
     */
    protected const DISALLOWED_ANCESTOR_TYPES = ['codeBlock'];

    /**
     * @var array<int, string>
     */
    protected const LITERAL_MARK_TYPES = ['code'];

    protected const PATTERN = '/(?<!\{)\{\{(?<inner>[^{}]*)\}\}(?!\})/u';

    /**
     * @param array<array-key, mixed>|null $document
     * @param array<string, string>|array<int, string> $mergeTags
     *
     * @return array<array-key, mixed>|null
     */
    public function __invoke(?array $document, array $mergeTags): ?array
    {
        if ($document === null || blank($document)) {
            return $document;
        }

        $lookup = $this->lookup($mergeTags);

        if ($lookup === []) {
            return $document;
        }

        if (array_is_list($document)) {
            return $this->convertNodes($document, $lookup, areMergeTagsAllowed: true);
        }

        return $this->convertNode($document, $lookup, areMergeTagsAllowed: true);
    }

    /**
     * @param array<array-key, mixed> $mergeTags
     *
     * @return array<string, string>
     */
    protected function lookup(array $mergeTags): array
    {
        $lookup = [];

        foreach ($mergeTags as $key => $label) {
            if (! is_string($label)) {
                continue;
            }

            $normalized = $this->normalize($label);

            if ($normalized === '') {
                continue;
            }

            $lookup[$normalized] ??= is_string($key) ? $key : $label;
        }

        foreach ($mergeTags as $key => $label) {
            if (! is_string($label)) {
                continue;
            }

            $identifier = is_string($key) ? $key : $label;

            $normalized = $this->normalize($identifier);

            if ($normalized === '') {
                continue;
            }

            $lookup[$normalized] = $identifier;
        }

        return $lookup;
    }

    protected function normalize(string $value): string
    {
        return Str::lower(Str::squish(str_replace(['’', '‘', 'ʼ', '‛'], "'", $value)));
    }

    /**
     * @param array<array-key, mixed> $node
     * @param array<string, string> $lookup
     *
     * @return array<array-key, mixed>
     */
    protected function convertNode(array $node, array $lookup, bool $areMergeTagsAllowed): array
    {
        $content = $node['content'] ?? null;

        if (! is_array($content) || ! array_is_list($content)) {
            return $node;
        }

        if (in_array($node['type'] ?? null, static::DISALLOWED_ANCESTOR_TYPES, true)) {
            $areMergeTagsAllowed = false;
        }

        $converted = $this->convertNodes($content, $lookup, $areMergeTagsAllowed);

        if ($converted === $content) {
            return $node;
        }

        $node['content'] = $converted;

        return $node;
    }

    /**
     * @param array<int, mixed> $nodes
     * @param array<string, string> $lookup
     *
     * @return array<int, mixed>
     */
    protected function convertNodes(array $nodes, array $lookup, bool $areMergeTagsAllowed): array
    {
        $converted = [];
        $run = [];

        foreach ($nodes as $node) {
            if ($this->isTextNode($node)) {
                /** @var array<array-key, mixed> $node */
                if (filled($run) && ! $this->marksMatch($run[0]['marks'] ?? null, $node['marks'] ?? null)) {
                    $this->flushRun($run, $converted, $lookup, $areMergeTagsAllowed);
                }

                $run[] = $node;

                continue;
            }

            $this->flushRun($run, $converted, $lookup, $areMergeTagsAllowed);

            $converted[] = is_array($node)
                ? $this->convertNode($node, $lookup, $areMergeTagsAllowed)
                : $node;
        }

        $this->flushRun($run, $converted, $lookup, $areMergeTagsAllowed);

        return $converted;
    }

    /**
     * @param array<int, array<array-key, mixed>> $run
     * @param array<int, mixed> $converted
     * @param array<string, string> $lookup
     */
    protected function flushRun(array &$run, array &$converted, array $lookup, bool $areMergeTagsAllowed): void
    {
        if (blank($run)) {
            return;
        }

        foreach ($this->convertTextRun($run, $lookup, $areMergeTagsAllowed) as $node) {
            $converted[] = $node;
        }

        $run = [];
    }

    /**
     * @param array<int, array<array-key, mixed>> $run
     * @param array<string, string> $lookup
     *
     * @return array<int, array<array-key, mixed>>
     */
    protected function convertTextRun(array $run, array $lookup, bool $areMergeTagsAllowed): array
    {
        $marks = $run[0]['marks'] ?? null;

        if (! $areMergeTagsAllowed || $this->isLiteral($marks)) {
            return $run;
        }

        $text = implode('', array_map(
            fn (array $node): string => is_string($node['text'] ?? null) ? $node['text'] : '',
            $run,
        ));

        if (! str_contains($text, '{{')) {
            return $run;
        }

        if (preg_match_all(static::PATTERN, $text, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) === false) {
            return $run;
        }

        $converted = [];
        $offset = 0;

        foreach ($matches as $match) {
            [$matched, $start] = $match[0];

            $identifier = $lookup[$this->normalize($match['inner'][0])] ?? null;

            if ($identifier === null) {
                continue;
            }

            $before = substr($text, $offset, $start - $offset);

            if ($before !== '') {
                $converted[] = $this->textNode($before, $marks);
            }

            $converted[] = $this->mergeTagNode($identifier, $marks);

            $offset = $start + strlen($matched);
        }

        if (blank($converted)) {
            return $run;
        }

        $after = substr($text, $offset);

        if ($after !== '') {
            $converted[] = $this->textNode($after, $marks);
        }

        return $converted;
    }

    /**
     * @return array<string, mixed>
     */
    protected function textNode(string $text, mixed $marks): array
    {
        $node = [
            'type' => 'text',
            'text' => $text,
        ];

        if (is_array($marks) && filled($marks)) {
            $node['marks'] = $marks;
        }

        return $node;
    }

    /**
     * @return array<string, mixed>
     */
    protected function mergeTagNode(string $identifier, mixed $marks): array
    {
        $node = [
            'type' => 'mergeTag',
            'attrs' => ['id' => $identifier],
        ];

        if (is_array($marks) && filled($marks)) {
            $node['marks'] = $marks;
        }

        return $node;
    }

    protected function isTextNode(mixed $node): bool
    {
        return is_array($node)
            && ($node['type'] ?? null) === 'text'
            && is_string($node['text'] ?? null);
    }

    protected function isLiteral(mixed $marks): bool
    {
        if (! is_array($marks)) {
            return false;
        }

        foreach ($marks as $mark) {
            if (is_array($mark) && in_array($mark['type'] ?? null, static::LITERAL_MARK_TYPES, true)) {
                return true;
            }
        }

        return false;
    }

    protected function marksMatch(mixed $marks, mixed $otherMarks): bool
    {
        return $this->marksSignature($marks) === $this->marksSignature($otherMarks);
    }

    protected function marksSignature(mixed $marks): string
    {
        if (! is_array($marks) || blank($marks)) {
            return '';
        }

        $signatures = array_map(fn (mixed $mark): string => json_encode($mark) ?: '', $marks);

        sort($signatures);

        return implode('|', $signatures);
    }
}
