@if ($type === \App\Support\CustomField::TYPE_TEXT)<input type="text" name="{{ $name }}" value="{{ $value }}" />
@elseif ($type === \App\Support\CustomField::TYPE_TEXTAREA)<textarea name="{{ $name }}" rows="4">{{ $value }}</textarea>
@elseif ($type === \App\Support\CustomField::TYPE_RADIO || $type === \App\Support\CustomField::TYPE_CHECKBOX)@include('fields._radio_group', ['type' => $type === \App\Support\CustomField::TYPE_RADIO ? 'radio' : 'checkbox', 'options' => $options, 'selfClose' => true])
@elseif ($type === \App\Support\CustomField::TYPE_SELECT)<select name="{{ $name }}">@foreach ($options as $o)<option value="{{ $o['value'] }}"{{ $o['checked'] ? ' selected' : '' }}>{{ $o['label'] }}</option>@endforeach</select>
@elseif ($type === \App\Support\CustomField::TYPE_IMAGE)@include('fields._image_preview')
@endif
