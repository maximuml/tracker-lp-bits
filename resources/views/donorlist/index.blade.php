@extends('layouts.legacy')

@section('title', 'Donorlist')

@section('content')
@php
\App\Support\Html::beginFrame("Donor List ({$users})", true);
\App\Support\Html::beginTable();
@endphp
{!! $pagerbottom !!}
<form method="post">
    <tr>
        <td class="colhead">ID</td>
        <td class="colhead" align="left">Username</td>
        <td class="colhead" align="left">e-mail</td>
        <td class="colhead" align="left">Joined</td>
        <td class="colhead" align="left">How much?</td>
    </tr>
    @foreach ($rows as $arr)
        <tr>
            <td>{{ $arr['id'] }}</td>
            <td align="left">{!! \App\Support\UserDisplay::username($arr['id']) !!}</td>
            <td align="left"><a href="mailto:{{ $arr['email'] }}">{{ $arr['email'] }}</a></td>
            <td align="left">{{ $arr['added'] }}</td>
            <td align="left">${{ $arr['donated'] }}</td>
        </tr>
    @endforeach
</form>
@php
\App\Support\Html::endTable();
\App\Support\Html::endFrame();
@endphp
@endsection
