@props(['errors' => null])
@if ($errors !== null && $errors->any())
    <div class="nx-auth__error">
        @foreach ($errors->all() as $message)
            <div>{{ $message }}</div>
        @endforeach
    </div>
@endif
