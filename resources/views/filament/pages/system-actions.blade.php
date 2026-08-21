<x-filament-panels::page>
    <div class="space-y-6">
        <div>
            <h2 class="text-lg font-semibold text-danger">{{ __('admin.system_actions.delete_account') }}</h2>
            <p class="text-sm text-gray-500 mb-4">{{ __('admin.system_actions.delete_account_desc') }}</p>
            <form wire:submit="submitDelacct">
                {{ $this->delacctForm }}
                <x-filament::button type="submit" color="danger" icon="heroicon-o-trash">
                    {{ __('admin.system_actions.delete_account') }}
                </x-filament::button>
            </form>
        </div>

        <hr />

        <div>
            <h2 class="text-lg font-semibold">{{ __('admin.system_actions.mass_mail') }}</h2>
            <p class="text-sm text-gray-500 mb-4">{{ __('admin.system_actions.mass_mail_desc') }}</p>
            <form wire:submit="submitMassmail">
                {{ $this->massmailForm }}
                <x-filament::button type="submit" icon="heroicon-o-envelope">
                    {{ __('admin.system_actions.send_mass_mail') }}
                </x-filament::button>
            </form>
        </div>

        <hr />

        <div>
            <h2 class="text-lg font-semibold text-warning">{{ __('admin.system_actions.delete_disabled') }}</h2>
            <p class="text-sm text-gray-500">{{ __('admin.system_actions.delete_disabled_desc') }}</p>
            <div class="mt-4 p-4 bg-warning-50 border border-warning-200 rounded-lg">
                <p class="text-sm text-warning-800">{{ __('admin.system_actions.delete_disabled_warning') }}</p>
            </div>
        </div>
    </div>
</x-filament-panels::page>
