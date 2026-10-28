@foreach ($fields as $fieldParams)
    @include('components.fields.upload_field', $fieldParams)
@endforeach
