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

use CanyonGBS\Common\Support\ConvertLiteralMergeTags;

/**
 * @param array<array-key, mixed> ...$nodes
 *
 * @return array<string, mixed>
 */
function mergeTagDoc(array ...$nodes): array
{
    return [
        'type' => 'doc',
        'content' => array_values($nodes),
    ];
}

/**
 * @param array<array-key, mixed> ...$nodes
 *
 * @return array<string, mixed>
 */
function mergeTagParagraph(array ...$nodes): array
{
    return [
        'type' => 'paragraph',
        'content' => array_values($nodes),
    ];
}

/**
 * @param array<int, mixed>|null $marks
 *
 * @return array<string, mixed>
 */
function mergeTagText(string $text, ?array $marks = null): array
{
    $node = [
        'type' => 'text',
        'text' => $text,
    ];

    if ($marks !== null) {
        $node['marks'] = $marks;
    }

    return $node;
}

/**
 * @param array<int, mixed>|null $marks
 *
 * @return array<string, mixed>
 */
function mergeTagNode(string $identifier, ?array $marks = null): array
{
    $node = [
        'type' => 'mergeTag',
        'attrs' => ['id' => $identifier],
    ];

    if ($marks !== null) {
        $node['marks'] = $marks;
    }

    return $node;
}

/**
 * @return array<string, string>
 */
function mergeTagDefinitions(): array
{
    return [
        'recipient name' => "recipient's name",
        'author name' => "author's name",
    ];
}

dataset('converted rich content documents', function () {
    return [
        'label match splits a text node into five nodes' => [
            'mergeTags' => mergeTagDefinitions(),
            'input' => mergeTagDoc(mergeTagParagraph(
                mergeTagText("Hey {{ recipient's name }}, my name is {{ author's name }}!"),
            )),
            'expectation' => mergeTagDoc(mergeTagParagraph(
                mergeTagText('Hey '),
                mergeTagNode('recipient name'),
                mergeTagText(', my name is '),
                mergeTagNode('author name'),
                mergeTagText('!'),
            )),
        ],
        'identifier match' => [
            'mergeTags' => mergeTagDefinitions(),
            'input' => mergeTagDoc(mergeTagParagraph(mergeTagText('Hey {{ recipient name }}!'))),
            'expectation' => mergeTagDoc(mergeTagParagraph(
                mergeTagText('Hey '),
                mergeTagNode('recipient name'),
                mergeTagText('!'),
            )),
        ],
        'curly apostrophe within the content' => [
            'mergeTags' => mergeTagDefinitions(),
            'input' => mergeTagDoc(mergeTagParagraph(mergeTagText('{{ recipient’s name }}'))),
            'expectation' => mergeTagDoc(mergeTagParagraph(mergeTagNode('recipient name'))),
        ],
        'curly apostrophe within the label' => [
            'mergeTags' => ['recipient name' => 'recipient’s name'],
            'input' => mergeTagDoc(mergeTagParagraph(mergeTagText("{{ recipient's name }}"))),
            'expectation' => mergeTagDoc(mergeTagParagraph(mergeTagNode('recipient name'))),
        ],
        'case insensitive' => [
            'mergeTags' => mergeTagDefinitions(),
            'input' => mergeTagDoc(mergeTagParagraph(mergeTagText('{{ RECIPIENT NAME }}'))),
            'expectation' => mergeTagDoc(mergeTagParagraph(mergeTagNode('recipient name'))),
        ],
        'case insensitive label' => [
            'mergeTags' => mergeTagDefinitions(),
            'input' => mergeTagDoc(mergeTagParagraph(mergeTagText("{{ Recipient's Name }}"))),
            'expectation' => mergeTagDoc(mergeTagParagraph(mergeTagNode('recipient name'))),
        ],
        'without inner whitespace' => [
            'mergeTags' => mergeTagDefinitions(),
            'input' => mergeTagDoc(mergeTagParagraph(mergeTagText('{{recipient name}}'))),
            'expectation' => mergeTagDoc(mergeTagParagraph(mergeTagNode('recipient name'))),
        ],
        'with ragged whitespace' => [
            'mergeTags' => mergeTagDefinitions(),
            'input' => mergeTagDoc(mergeTagParagraph(mergeTagText("{{   recipient \n   name   }}"))),
            'expectation' => mergeTagDoc(mergeTagParagraph(mergeTagNode('recipient name'))),
        ],
        'with a non breaking space' => [
            'mergeTags' => mergeTagDefinitions(),
            'input' => mergeTagDoc(mergeTagParagraph(mergeTagText("{{ recipient\u{00A0}name }}"))),
            'expectation' => mergeTagDoc(mergeTagParagraph(mergeTagNode('recipient name'))),
        ],
        'merge tags defined as a list' => [
            'mergeTags' => ['contact full name', 'contact email'],
            'input' => mergeTagDoc(mergeTagParagraph(mergeTagText('Hi {{ contact full name }}'))),
            'expectation' => mergeTagDoc(mergeTagParagraph(
                mergeTagText('Hi '),
                mergeTagNode('contact full name'),
            )),
        ],
        'an identifier takes precedence over another tag label' => [
            'mergeTags' => ['status' => 'state', 'legacy status' => 'status'],
            'input' => mergeTagDoc(mergeTagParagraph(mergeTagText('{{ status }}'))),
            'expectation' => mergeTagDoc(mergeTagParagraph(mergeTagNode('status'))),
        ],
        'the first of two duplicate labels wins' => [
            'mergeTags' => ['first' => 'name', 'second' => 'name'],
            'input' => mergeTagDoc(mergeTagParagraph(mergeTagText('{{ name }}'))),
            'expectation' => mergeTagDoc(mergeTagParagraph(mergeTagNode('first'))),
        ],
        'adjacent merge tags do not produce empty text nodes' => [
            'mergeTags' => mergeTagDefinitions(),
            'input' => mergeTagDoc(mergeTagParagraph(mergeTagText('{{ recipient name }}{{ author name }}'))),
            'expectation' => mergeTagDoc(mergeTagParagraph(
                mergeTagNode('recipient name'),
                mergeTagNode('author name'),
            )),
        ],
        'an unknown tag alongside a known tag is left as text' => [
            'mergeTags' => mergeTagDefinitions(),
            'input' => mergeTagDoc(mergeTagParagraph(mergeTagText('{{ recipient name }} and {{ bogus }}'))),
            'expectation' => mergeTagDoc(mergeTagParagraph(
                mergeTagNode('recipient name'),
                mergeTagText(' and {{ bogus }}'),
            )),
        ],
        'within a heading' => [
            'mergeTags' => mergeTagDefinitions(),
            'input' => mergeTagDoc([
                'type' => 'heading',
                'attrs' => ['level' => 2],
                'content' => [mergeTagText('Hello {{ recipient name }}')],
            ]),
            'expectation' => mergeTagDoc([
                'type' => 'heading',
                'attrs' => ['level' => 2],
                'content' => [
                    mergeTagText('Hello '),
                    mergeTagNode('recipient name'),
                ],
            ]),
        ],
        'within a bullet list' => [
            'mergeTags' => mergeTagDefinitions(),
            'input' => mergeTagDoc([
                'type' => 'bulletList',
                'content' => [[
                    'type' => 'listItem',
                    'content' => [mergeTagParagraph(mergeTagText('{{ recipient name }} attended'))],
                ]],
            ]),
            'expectation' => mergeTagDoc([
                'type' => 'bulletList',
                'content' => [[
                    'type' => 'listItem',
                    'content' => [mergeTagParagraph(
                        mergeTagNode('recipient name'),
                        mergeTagText(' attended'),
                    )],
                ]],
            ]),
        ],
        'within a blockquote, leaving sibling paragraphs untouched' => [
            'mergeTags' => mergeTagDefinitions(),
            'input' => mergeTagDoc(
                mergeTagParagraph(mergeTagText('Nothing to convert here.')),
                [
                    'type' => 'blockquote',
                    'content' => [mergeTagParagraph(mergeTagText('Quoting {{ author name }}'))],
                ],
            ),
            'expectation' => mergeTagDoc(
                mergeTagParagraph(mergeTagText('Nothing to convert here.')),
                [
                    'type' => 'blockquote',
                    'content' => [mergeTagParagraph(
                        mergeTagText('Quoting '),
                        mergeTagNode('author name'),
                    )],
                ],
            ),
        ],
        'marks are preserved on every node produced by the split' => [
            'mergeTags' => mergeTagDefinitions(),
            'input' => mergeTagDoc(mergeTagParagraph(
                mergeTagText('Hey {{ recipient name }}!', [['type' => 'bold']]),
            )),
            'expectation' => mergeTagDoc(mergeTagParagraph(
                mergeTagText('Hey ', [['type' => 'bold']]),
                mergeTagNode('recipient name', [['type' => 'bold']]),
                mergeTagText('!', [['type' => 'bold']]),
            )),
        ],
        'a link mark is preserved on the merge tag' => [
            'mergeTags' => mergeTagDefinitions(),
            'input' => mergeTagDoc(mergeTagParagraph(
                mergeTagText('{{ recipient name }}', [['type' => 'link', 'attrs' => ['href' => 'https://canyongbs.com']]]),
            )),
            'expectation' => mergeTagDoc(mergeTagParagraph(
                mergeTagNode('recipient name', [['type' => 'link', 'attrs' => ['href' => 'https://canyongbs.com']]]),
            )),
        ],
        'an empty marks array is omitted from the produced nodes' => [
            'mergeTags' => mergeTagDefinitions(),
            'input' => mergeTagDoc(mergeTagParagraph(mergeTagText('{{ recipient name }}', []))),
            'expectation' => mergeTagDoc(mergeTagParagraph(mergeTagNode('recipient name'))),
        ],
        'a merge tag spread across text nodes carrying the same marks' => [
            'mergeTags' => mergeTagDefinitions(),
            'input' => mergeTagDoc(mergeTagParagraph(
                mergeTagText('Hey {{ recipient', [['type' => 'bold']]),
                mergeTagText("'s name }}!", [['type' => 'bold']]),
            )),
            'expectation' => mergeTagDoc(mergeTagParagraph(
                mergeTagText('Hey ', [['type' => 'bold']]),
                mergeTagNode('recipient name', [['type' => 'bold']]),
                mergeTagText('!', [['type' => 'bold']]),
            )),
        ],
        'alongside an existing merge tag node' => [
            'mergeTags' => mergeTagDefinitions(),
            'input' => mergeTagDoc(mergeTagParagraph(
                mergeTagNode('recipient name'),
                mergeTagText(', from {{ author name }}'),
            )),
            'expectation' => mergeTagDoc(mergeTagParagraph(
                mergeTagNode('recipient name'),
                mergeTagText(', from '),
                mergeTagNode('author name'),
            )),
        ],
    ];
});

dataset('unconverted rich content documents', function () {
    return [
        'an unknown merge tag' => [
            'mergeTags' => mergeTagDefinitions(),
            'input' => mergeTagDoc(mergeTagParagraph(mergeTagText('Hey {{ not a tag }}!'))),
        ],
        'an unclosed merge tag' => [
            'mergeTags' => mergeTagDefinitions(),
            'input' => mergeTagDoc(mergeTagParagraph(mergeTagText('Hey {{ recipient name'))),
        ],
        'an opening brace on its own' => [
            'mergeTags' => mergeTagDefinitions(),
            'input' => mergeTagDoc(mergeTagParagraph(mergeTagText('Hey {{'))),
        ],
        'empty braces' => [
            'mergeTags' => mergeTagDefinitions(),
            'input' => mergeTagDoc(mergeTagParagraph(mergeTagText('Hey {{}} there'))),
        ],
        'triple braces' => [
            'mergeTags' => mergeTagDefinitions(),
            'input' => mergeTagDoc(mergeTagParagraph(mergeTagText('Hey {{{ recipient name }}}'))),
        ],
        'no registered merge tags' => [
            'mergeTags' => [],
            'input' => mergeTagDoc(mergeTagParagraph(mergeTagText('Hey {{ recipient name }}!'))),
        ],
        'text within a code block' => [
            'mergeTags' => mergeTagDefinitions(),
            'input' => mergeTagDoc([
                'type' => 'codeBlock',
                'content' => [mergeTagText('Hey {{ recipient name }}!')],
            ]),
        ],
        'text carrying the code mark' => [
            'mergeTags' => mergeTagDefinitions(),
            'input' => mergeTagDoc(mergeTagParagraph(
                mergeTagText('Hey {{ recipient name }}!', [['type' => 'code']]),
            )),
        ],
        'the configuration of a custom block' => [
            'mergeTags' => mergeTagDefinitions(),
            'input' => mergeTagDoc([
                'type' => 'customBlock',
                'attrs' => [
                    'id' => 'button',
                    'config' => ['label' => 'Hey {{ recipient name }}'],
                ],
            ]),
        ],
        'a merge tag spread across text nodes carrying different marks' => [
            'mergeTags' => mergeTagDefinitions(),
            'input' => mergeTagDoc(mergeTagParagraph(
                mergeTagText('Hey {{ recipient', [['type' => 'bold']]),
                mergeTagText("'s name }}!"),
            )),
        ],
        'adjacent text nodes without any merge tags' => [
            'mergeTags' => mergeTagDefinitions(),
            'input' => mergeTagDoc(mergeTagParagraph(
                mergeTagText('Hey there, '),
                mergeTagText('how are you?'),
            )),
        ],
        'a document already containing merge tag nodes' => [
            'mergeTags' => mergeTagDefinitions(),
            'input' => mergeTagDoc(mergeTagParagraph(
                mergeTagText('Hey '),
                mergeTagNode('recipient name'),
                mergeTagText('!'),
            )),
        ],
        'a malformed document' => [
            'mergeTags' => mergeTagDefinitions(),
            'input' => mergeTagDoc([
                'type' => 'paragraph',
                'content' => [
                    'not a node',
                    ['type' => 'text', 'text' => ['not', 'a', 'string']],
                    ['type' => 'paragraph', 'content' => ['type' => 'text']],
                ],
            ]),
        ],
        'a bare text node as the document root' => [
            'mergeTags' => mergeTagDefinitions(),
            'input' => mergeTagText('Hey {{ recipient name }}!'),
        ],
    ];
});

it('converts literal merge tags into merge tag nodes', function (array $mergeTags, array $input, array $expectation) {
    expect((new ConvertLiteralMergeTags())($input, $mergeTags))->toBe($expectation);
})->with('converted rich content documents');

it('returns the document unchanged when there is nothing to convert', function (array $mergeTags, array $input) {
    expect((new ConvertLiteralMergeTags())($input, $mergeTags))->toBe($input);
})->with('unconverted rich content documents');

it('accepts a bare list of nodes as the document root', function () {
    $converted = (new ConvertLiteralMergeTags())(
        [mergeTagParagraph(mergeTagText('Hey {{ recipient name }}!'))],
        mergeTagDefinitions(),
    );

    expect($converted)->toBe([mergeTagParagraph(
        mergeTagText('Hey '),
        mergeTagNode('recipient name'),
        mergeTagText('!'),
    )]);
});

it('returns null when the document is null', function () {
    expect((new ConvertLiteralMergeTags())(null, mergeTagDefinitions()))->toBeNull();
});

it('returns an empty document unchanged', function () {
    expect((new ConvertLiteralMergeTags())([], mergeTagDefinitions()))->toBe([]);
});

it('ignores merge tags that are not defined as strings', function () {
    $input = mergeTagDoc(mergeTagParagraph(mergeTagText('Hey {{ recipient name }}!')));

    expect((new ConvertLiteralMergeTags())($input, ['recipient name' => fn () => 'Jane Doe']))->toBe($input);
});

it('converts every merge tag within a document containing many nodes', function () {
    $converted = (new ConvertLiteralMergeTags())(
        mergeTagDoc(
            mergeTagParagraph(mergeTagText("Hey {{ recipient's name }},")),
            mergeTagParagraph(mergeTagText('Nothing to convert here.')),
            mergeTagParagraph(mergeTagText("Regards, {{ author's name }}")),
        ),
        mergeTagDefinitions(),
    );

    expect($converted)->toBe(mergeTagDoc(
        mergeTagParagraph(mergeTagText('Hey '), mergeTagNode('recipient name'), mergeTagText(',')),
        mergeTagParagraph(mergeTagText('Nothing to convert here.')),
        mergeTagParagraph(mergeTagText('Regards, '), mergeTagNode('author name')),
    ));
});
