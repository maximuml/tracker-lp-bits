<x-filament-panels::page>
    <div class="mb-4 space-y-2">
        <x-filament::button id="nx-passkey-create" icon="heroicon-o-plus">
            {{ __('passkey.passkey_create') }}
        </x-filament::button>
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('passkey.passkey_desc') }}</p>
    </div>

    {{ $this->table }}

    <script src="{{ asset('js/passkey.js') }}"></script>
    <script>
        document.getElementById('nx-passkey-create').addEventListener('click', function () {
            if (!Passkey.supported()) {
                window.alert(@json(__('passkey.passkey_not_supported')));
                return;
            }
            Passkey.createRegistration()
                .then(function () { location.reload(); })
                .catch(function (e) { window.alert(e.message); });
        });
    </script>
</x-filament-panels::page>
