<?php

namespace App\Filament\Widgets\Concerns;

trait HasMobileFriendlyChart
{
    protected function getMobileFriendlyOptions(array $options = []): array
    {
        return array_replace_recursive([
            'responsive' => true,
            'maintainAspectRatio' => false,
        ], $options);
    }
}
