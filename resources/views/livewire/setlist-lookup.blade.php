<div class="nexus-input-box">
    <div>
        <input type="text" id="name" name="name" size="60" value="{{ $name }}" wire:model="name" aria-label="{{ __('legacy/upload.row_torrent_name') }}"@if ($invalid) aria-invalid="true" aria-describedby="name-error"@endif>
        <span class="medium">{{ __('legacy/upload.text_torrent_name_note') }}</span>
    </div>
    <div>
        <input type="button" class="nexus-action-btn nx-postbtn" value="{{ __('legacy/upload.fill_setlist') }}" wire:click="lookup" wire:loading.attr="disabled" wire:target="lookup">
        <span class="medium" wire:loading wire:target="lookup">Loading...</span>
    </div>
</div>
