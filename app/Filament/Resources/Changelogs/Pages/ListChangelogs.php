<?php

namespace App\Filament\Resources\Changelogs\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\Changelogs\ChangelogResource;

class ListChangelogs extends ListRecords
{
    protected static string $resource = ChangelogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
