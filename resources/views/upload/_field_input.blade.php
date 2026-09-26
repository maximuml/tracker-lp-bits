<div class="nexus-input-box">
    <div>
        <input type="text" id="{{ $name }}" name="{{ $name }}" size="60" value="{{ $value }}"@if (isset($errors) && $errors->has($name)) aria-invalid="true" aria-describedby="{{ $name }}-error"@endif>
        @if ((string) $noteText !== '')<span class="medium">{{ $noteText }}</span>@endif
    </div>
    @if ($btnText !== '')<div><input type="button" class="nexus-action-btn" value="{{ $btnText }}"@if ($btnId !== '') id="{{ $btnId }}"@endif></div>@endif
</div>