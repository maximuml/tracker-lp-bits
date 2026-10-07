<div>
    @if ($vm->rows !== [])
        @include('comments.table', ['vm' => $vm])
    @endif

    <br /><br />
    <div class="nx-box text-center">
        <div class="p-[10pt] text-center">
            <b>{{ __($type === 'offer' ? 'offers.text_quick_comment' : 'details.text_quick_comment') }}</b><br /><br />
            <form name="comment" wire:submit="post">
                <label class="nx-sr-only" for="body">{{ __('functions.row_body') }}</label><textarea id="body" name="body" cols="100" rows="8" data-ctrlenter="compose:qr" wire:model="text"></textarea>{{ $smileRow }}<br /><input type="submit" id="qr" class="nx-postbtn" value="{{ __($type === 'offer' ? 'offers.submit_add_comment' : 'details.submit_add_comment') }}" />
            </form>
            @if ($status !== '')
                <div class="nx-field__error" role="alert">{{ $status }}</div>
            @endif
        </div>
    </div>
</div>
