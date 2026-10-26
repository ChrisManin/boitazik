<?php

namespace App\Enums\Concerns;

// For enums with a label(): Filament selects and columns show the same label as the Vue front
trait HasFilamentLabel
{
    public function getLabel(): string
    {
        return $this->label();
    }
}
