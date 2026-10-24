{{-- Passkey list for the security section. Data: $security->passkeys, $security->cspNonce. --}}
<button type="button" id="passkey_create">{{ \App\Support\Locale::trans('passkey.passkey_create', [], null) }}</button><br>{{ \App\Support\Locale::trans('passkey.passkey_desc', [], null) }}
<x-data-table :caption="\App\Support\Locale::trans('passkey.passkey', [], null)" captionHidden>
@if (empty($security->passkeys))
<tr><td>{{ \App\Support\Locale::trans('passkey.passkey_empty', [], null) }}</td></tr>
@else
@foreach ($security->passkeys as $pk)
<tr>
    <td>
        <div>
            <img src="{{ $pk->iconUrl }}" alt="{{ $pk->iconAlt }}" /><div><b>{{ $pk->displayName }}</b>@if ($pk->showCredentialId) ({{ $pk->credentialId }})@endif
            <br><b>{{ \App\Support\Locale::trans('passkey.passkey_created_at', [], null) }}</b><x-time :value="$pk->createdAt" /></div>
            <button type="button" data-passkey-id="{{ $pk->credentialId }}">{{ \App\Support\Locale::trans('passkey.passkey_delete', [], null) }}</button>
        </div>
    </td>
</tr>
@endforeach
@endif
</x-data-table>
<script @if ($security->cspNonce !== '') nonce="{{ $security->cspNonce }}"@endif>
    document.addEventListener("DOMContentLoaded", function () {
        document.getElementById('passkey_create').addEventListener('click', () => {
            if (!Passkey.supported()) {
                layer.alert('{{ \App\Support\Locale::trans('passkey.passkey_not_supported', [], null) }}');
            } else {
                layer.load(2, {shade: 0.3});
                Passkey.createRegistration().then(() => {
                    location.reload();
                }).catch((e) => {
                    layer.alert(e.message);
                }).finally(() => {
                    layer.closeAll('loading');
                });
            }
        })
        document.querySelectorAll('button[data-passkey-id]').forEach((button) => {
            button.addEventListener('click', () => {
                const credentialId = button.getAttribute('data-passkey-id');
                layer.confirm('{{ \App\Support\Locale::trans('passkey.passkey_delete_confirm', [], null) }}', {}, function () {
                    layer.load(2, {shade: 0.3});
                    Passkey.deleteRegistration(credentialId).then(() => {
                        location.reload();
                    }).catch((e) => {
                        layer.alert(e.message);
                    }).finally(() => {
                        layer.closeAll('loading');
                    });
                });
            });
        });
    });
</script>
