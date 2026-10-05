<div>
    <input class="nx-postbtn" type="button" wire:click="thank" value="{{ $vm->buttonLabel }}"@if ($vm->hasThanked) disabled="disabled"@endif />
    &nbsp;&nbsp;<span>@if ($vm->noThanks){{ $vm->noThanksLabel }}@endif</span><span>@foreach ($vm->thanksBy as $name){{ $name }} @endforeach{{ $vm->andMore }}</span>
    @if ($status !== '')
        <div class="nx-field__error" role="alert">{{ $status }}</div>
    @endif
</div>
