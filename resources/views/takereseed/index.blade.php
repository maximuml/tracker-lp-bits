@extends('layouts.legacy')

@section('title', $lang_takereseed['head_reseed_request'] ?? 'Reseed request')

@section('content')
<center>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($message ?? ($lang_takereseed['std_it_worked'] ?? 'Reseed request sent.')))</center>
@endsection
