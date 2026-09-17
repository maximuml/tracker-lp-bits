@extends('layouts.legacy')

@section('title', __('legacy/takereseed.head_reseed_request'))

@section('content')
<center>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($message ?? (__('legacy/takereseed.std_it_worked'))))</center>
@endsection
