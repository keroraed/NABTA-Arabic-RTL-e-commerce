<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /**
     * A null `permissions` column means "follow the role". The form needs that
     * expressed as a toggle plus a pre-filled checkbox list, so it is unpacked
     * on the way in and re-packed by the form on the way out.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['use_role_defaults'] = ($data['permissions'] ?? null) === null;
        $data['permissions'] = $this->record->effectivePermissions();

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->label(__('users.delete')),
        ];
    }
}
