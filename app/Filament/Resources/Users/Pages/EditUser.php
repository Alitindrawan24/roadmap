<?php

namespace App\Filament\Resources\Users\Pages;

use Filament\Actions\DeleteAction;
use Filament\Support\Colors\Color;
use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\EditRecord;
use STS\FilamentImpersonate\Actions\Impersonate;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Impersonate::make()->color(Color::Gray),
            DeleteAction::make(),
        ];
    }
}
