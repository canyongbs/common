<?php

namespace CanyonGBS\Common\Filament\Forms\Components;

use Closure;
use Filament\Forms\Components\Field;

class StockImagePicker extends Field
{
    protected string $view = 'common::filament.forms.components.stock-image-picker';

    protected string | Closure | null $url = null;

    public function url(string | Closure | null $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->evaluate($this->url);
    }
}
