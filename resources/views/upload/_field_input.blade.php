<div class="nexus-input-box">
    <div>
        <input type="text" id="{{ $name }}" name="{{ $name }}" value="{{ $value }}">
        @if ($noteText !== '')<span class="medium">{{ $noteText }}</span>@endif
    </div>
    @if ($btnText !== '')<div><input type="button" class="nexus-action-btn" value="{{ $btnText }}"{{ $btnId !== '' ? ' id="'.e($btnId).'"' : '' }}></div>@endif
</div>