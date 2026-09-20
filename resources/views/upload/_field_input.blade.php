<div class="nexus-input-box">
    <div>
        <input type="text" id="{{ $name }}" name="{{ $name }}" value="{{ $value }}">
        @if ((string) $noteText !== '')<span class="medium">{{ $noteText }}</span>@endif
    </div>
    @if ($btnText !== '')<div><input type="button" class="nexus-action-btn" value="{{ $btnText }}"@if ($btnId !== '') id="{{ $btnId }}"@endif></div>@endif
</div>