@extends('layouts.app', ['chromeVariant' => 'legacy'])

@section('title', $title)

@section('content')
<x-frame :caption="$title" caption-align="center">
@if ($unit <= 0)
    <h3>{{ $t['featureDisabled'] ?? '' }}</h3>
@elseif ($enabled)
    <h3>{{ $t['statusNormal'] ?? '' }}</h3>
@elseif (! $latestBanLog)
    <h3>{{ $t['noBanInfo'] ?? '' }}</h3>
@elseif ($showError ?? false)
    @if (! $isUserBonusEnough)
        <x-std-message heading="Error" :text="\App\Support\Html\SafeHtml::fromUntrustedHtml($insufficientMessage)" :htmlstrip="false" />
    @endif
@else
    <h3>{{ $t['latestBanInfo'] ?? '' }}</h3>
    <x-data-table :caption="$title" captionHidden id="ban-info">
    <tr><th scope="row">UID：</th><td>{{ $latestBanLog->uid }}</td></tr>
    <tr><th scope="row">Username：</th><td>{{ $latestBanLog->username }}</td></tr>
    <tr><th scope="row">Reason：</th><td>{{ $latestBanLog->reason }}</td></tr>
    <tr><th scope="row">CreatedAt：</th><td>{{ $latestBanLog->created_at }}</td></tr>
    </x-data-table>
    <p>{{ $t['deductPerDay'] ?? '' }}</p>
    <p>{{ $t['deductTotal'] ?? '' }}</p>
    @if ($isUserBonusEnough)
        <p>{{ $t['enableDesc'] ?? '' }}</p>
        <form method="post"><input type="hidden" name="submit" value="1"><input type="submit" value="{{ $t['enableButton'] ?? '' }}"></form>
    @else
        <p>{{ $insufficientMessage }}</p>
    @endif
@endif
</x-frame>
@endsection
