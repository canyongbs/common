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

namespace CanyonGBS\Common\Filament\Forms\RichContentPlugins;

use CanyonGBS\Common\Filament\Forms\RichContentPlugins\TipTapExtensions\VideoEmbedExtension;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\EditorCommand;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Forms\Components\TextInput;
use Filament\Support\Enums\Width;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Icons\Heroicon;
use Tiptap\Core\Extension;

class VideoRichContentPlugin implements RichContentPlugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * @return array<Extension>
     */
    public function getTipTapPhpExtensions(): array
    {
        return [
            new VideoEmbedExtension(),
        ];
    }

    /**
     * @return array<string>
     */
    public function getTipTapJsExtensions(): array
    {
        return [
            FilamentAsset::getScriptSrc('rich-content-plugins/video-embed', 'common'),
        ];
    }

    /**
     * @return array<RichEditorTool>
     */
    public function getEditorTools(): array
    {
        return [
            RichEditorTool::make('video')
                ->icon(Heroicon::Film)
                ->action('insertVideo'),
        ];
    }

    /**
     * @return array<Action>
     */
    public function getEditorActions(): array
    {
        return [
            Action::make('insertVideo')
                ->modalWidth(Width::Large)
                ->modalHeading('Insert video')
                ->modalSubmitActionLabel('Insert')
                ->schema([
                    TextInput::make('url')
                        ->label('Video URL')
                        ->required()
                        ->url()
                        ->helperText('Supports YouTube, Vimeo, or direct video file URLs (.mp4, .webm, .ogg, .mov)')
                        ->rules([
                            function () {
                                return function (string $attribute, mixed $value, Closure $fail) {
                                    if (! is_string($value)) {
                                        $fail('The URL must be a string.');

                                        return;
                                    }

                                    if (static::detectVideoType($value) === null) {
                                        $fail('The URL must be a valid YouTube, Vimeo, or direct video file URL (.mp4, .webm, .ogg, .mov).');
                                    }
                                };
                            },
                        ]),
                ])
                ->action(function (array $arguments, array $data, RichEditor $component): void {
                    $url = $data['url'];
                    $type = static::detectVideoType($url);
                    $embedUrl = static::getEmbedUrl($url, $type);

                    $component->runCommands(
                        [
                            EditorCommand::make('insertContent', arguments: [[
                                'type' => 'videoEmbed',
                                'attrs' => [
                                    'src' => $embedUrl,
                                    'type' => $type,
                                ],
                            ]]),
                        ],
                        editorSelection: $arguments['editorSelection'],
                    );
                }),
        ];
    }

    public static function detectVideoType(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        $host = $host ? strtolower($host) : '';

        if (preg_match('/(?:youtube\.com|youtu\.be)$/i', $host)) {
            return 'youtube';
        }

        if (preg_match('/vimeo\.com$/i', $host)) {
            return 'vimeo';
        }

        $path = parse_url($url, PHP_URL_PATH) ?? '';
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (in_array($extension, ['mp4', 'webm', 'ogg', 'mov'])) {
            return 'video';
        }

        return null;
    }

    public static function getEmbedUrl(string $url, string $type): string
    {
        if ($type === 'youtube') {
            return static::getYouTubeEmbedUrl($url);
        }

        if ($type === 'vimeo') {
            return static::getVimeoEmbedUrl($url);
        }

        return $url;
    }

    protected static function getYouTubeEmbedUrl(string $url): string
    {
        $videoId = null;

        if (preg_match('/youtube\.com\/embed\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return $url;
        }

        if (preg_match('/youtube\.com\/watch\?.*v=([a-zA-Z0-9_-]+)/', $url, $matches)) {
            $videoId = $matches[1];
        } elseif (preg_match('/youtu\.be\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
            $videoId = $matches[1];
        } elseif (preg_match('/youtube\.com\/shorts\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
            $videoId = $matches[1];
        }

        if (! $videoId) {
            return $url;
        }

        return "https://www.youtube.com/embed/{$videoId}";
    }

    protected static function getVimeoEmbedUrl(string $url): string
    {
        if (str_contains($url, 'player.vimeo.com/video/')) {
            return $url;
        }

        if (preg_match('/vimeo\.com\/(\d+)/', $url, $matches)) {
            return "https://player.vimeo.com/video/{$matches[1]}";
        }

        return $url;
    }
}
