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

use CanyonGBS\Common\Filament\Forms\Components\StockImagePicker;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\EditorCommand;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Tiptap\Core\Extension;

class StockImageRichContentPlugin implements RichContentPlugin
{
    protected string $stockImagesUrl;

    public function __construct(string $stockImagesUrl)
    {
        $this->stockImagesUrl($stockImagesUrl);
    }

    public static function make(string $stockImagesUrl): static
    {
        return app(static::class, [
            'stockImagesUrl' => $stockImagesUrl,
        ]);
    }

    /**
     * @return array<Extension>
     */
    public function getTipTapPhpExtensions(): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    public function getTipTapJsExtensions(): array
    {
        return [];
    }

    /**
     * @return array<RichEditorTool>
     */
    public function getEditorTools(): array
    {
        return [
            RichEditorTool::make('stockImage')
                ->icon(Heroicon::Photo)
                ->action('insertStockImage'),
        ];
    }

    /**
     * @return array<Action>
     */
    public function getEditorActions(): array
    {
        return [
            Action::make('insertStockImage')
                ->modalWidth(Width::ExtraLarge)
                ->modalHeading('Insert stock image')
                ->modalSubmitActionLabel('Insert')
                ->schema([
                    StockImagePicker::make('image')
                        ->required()
                        ->hiddenLabel()
                        ->url($this->getStockImagesUrl()),
                ])
                ->action(function (array $arguments, array $data, RichEditor $component): void {
                    $component->runCommands(
                        [
                            EditorCommand::make('insertContent', arguments: [[
                                'type' => 'image',
                                'attrs' => [
                                    'alt' => $data['image']['alt'] ?? null,
                                    'src' => $data['image']['src'] ?? null,
                                ],
                            ]]),
                        ],
                        editorSelection: $arguments['editorSelection'],
                    );
                }),
        ];
    }

    public function stockImagesUrl(string $url): static
    {
        $this->stockImagesUrl = $url;

        return $this;
    }

    public function getStockImagesUrl(): string
    {
        return $this->stockImagesUrl;
    }
}
