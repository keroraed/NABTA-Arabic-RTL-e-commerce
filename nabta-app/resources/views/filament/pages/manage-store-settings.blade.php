<x-filament-panels::page>
    <form wire:submit="save" class="fi-form grid gap-6">
        {{ $this->form }}

        <div class="flex justify-start">
            <x-filament::button type="submit" wire:loading.attr="disabled">
                حفظ
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
