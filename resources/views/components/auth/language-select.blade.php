@props(['label', 'languages', 'langFolder'])
<label for="sitelanguage">{{ $label }}</label>
<select id="sitelanguage" name="sitelanguage">
    @foreach ($languages as $row)
        <option value="{{ $row['id'] }}" @if (($row['site_lang_folder'] ?? '') === $langFolder) selected @endif>
            {{ $row['lang_name'] }}
        </option>
    @endforeach
</select>
