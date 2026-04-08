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
      of the licensor in the software. Any use of the licensor's trademarks is subject
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

namespace CanyonGBS\Common\Filament\Forms\RichContentPlugins\TipTapExtensions;

use Tiptap\Core\Node;
use Tiptap\Utils\HTML;

class VideoEmbedExtension extends Node
{
    /**
     * @var string
     */
    public static $name = 'videoEmbed';

    /**
     * @return array<array<string, mixed>>
     */
    public function addOptions(): array
    {
        return [
            'HTMLAttributes' => [],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function addAttributes(): array
    {
        return [
            'src' => [
                'default' => null,
                'parseHTML' => fn ($DOMNode) => $DOMNode->getAttribute('data-video-src')
                    ?: ($DOMNode->getElementsByTagName('iframe')->item(0)?->getAttribute('src')
                        ?? $DOMNode->getElementsByTagName('video')->item(0)?->getAttribute('src')),
            ],
            'type' => [
                'default' => 'video',
                'parseHTML' => fn ($DOMNode) => $DOMNode->getAttribute('data-video-type') ?: 'video',
            ],
            'width' => [
                'default' => '100%',
                'parseHTML' => fn ($DOMNode) => $DOMNode->getAttribute('data-video-width') ?: '100%',
            ],
            'height' => [
                'default' => '315',
                'parseHTML' => fn ($DOMNode) => $DOMNode->getAttribute('data-video-height') ?: '315',
            ],
        ];
    }

    /**
     * @return array<array<string, mixed>>
     */
    public function parseHTML(): array
    {
        return [
            [
                'tag' => 'div[data-video-embed]',
            ],
        ];
    }

    /**
     * @param  object  $node
     * @param  array<string, mixed>  $HTMLAttributes
     * @return array<mixed>
     */
    public function renderHTML($node, array $HTMLAttributes = []): array
    {
        $attrs = $node->attrs ?? [];
        $src = $attrs->src ?? '';
        $type = $attrs->type ?? 'video';
        $width = $attrs->width ?? '100%';
        $height = $attrs->height ?? '315';

        $wrapperAttrs = [
            'data-video-embed' => '',
            'data-video-type' => $type,
            'data-video-src' => $src,
            'data-video-width' => $width,
            'data-video-height' => $height,
        ];

        if ($type === 'youtube' || $type === 'vimeo') {
            return [
                'div',
                HTML::mergeAttributes($this->options['HTMLAttributes'], $wrapperAttrs, $HTMLAttributes, [
                    'style' => 'position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; max-width: 100%;',
                ]),
                [
                    'iframe',
                    [
                        'src' => $src,
                        'width' => '100%',
                        'height' => $height,
                        'frameborder' => '0',
                        'allowfullscreen' => 'true',
                        'allow' => 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture',
                        'style' => 'position: absolute; top: 0; left: 0; width: 100%; height: 100%;',
                    ],
                ],
            ];
        }

        return [
            'div',
            HTML::mergeAttributes($this->options['HTMLAttributes'], $wrapperAttrs, $HTMLAttributes, [
                'style' => 'max-width: 100%;',
            ]),
            [
                'video',
                [
                    'src' => $src,
                    'controls' => 'true',
                    'width' => '100%',
                ],
            ],
        ];
    }
}
