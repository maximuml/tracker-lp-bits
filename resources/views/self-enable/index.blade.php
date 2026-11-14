@extends('layouts.app')

@section('title', $vm->title)

@section('content')
<x-page :title="$vm->title" width="form">
<x-frame>
@if ($vm->unit <= 0)
    <h3>{{ $vm->t['featureDisabled'] ?? '' }}</h3>
@elseif ($vm->enabled)
    <h3>{{ $vm->t['statusNormal'] ?? '' }}</h3>
@elseif (! $vm->latestBanLog)
    <h3>{{ $vm->t['noBanInfo'] ?? '' }}</h3>
@elseif ($vm->showError ?? false)
    @if (! $vm->isUserBonusEnough)
        <x-std-message heading="Error" :text="\App\Support\Html\SafeHtml::fromUntrustedHtml($vm->insufficientMessage)" :htmlstrip="false" />
    @endif
@else
    <h3>{{ $vm->t['latestBanInfo'] ?? '' }}</h3>
    <x-data-table :caption="$vm->title" captionHidden id="ban-info">
    <tr><th scope="row">UID：</th><td>{{ $vm->latestBanLog->uid }}</td></tr>
    <tr><th scope="row">Username：</th><td>{{ $vm->latestBanLog->username }}</td></tr>
    <tr><th scope="row">Reason：</th><td>{{ $vm->latestBanLog->reason }}</td></tr>
    <tr><th scope="row">CreatedAt：</th><td>{{ $vm->latestBanLog->created_at }}</td></tr>
    </x-data-table>
    <p>{{ $vm->t['deductPerDay'] ?? '' }}</p>
    <p>{{ $vm->t['deductTotal'] ?? '' }}</p>
    @if ($vm->isUserBonusEnough)
        <p>{{ $vm->t['enableDesc'] ?? '' }}</p>
        <form method="post">@csrf<input type="hidden" name="submit" value="1"><input type="submit" class="nx-btn nx-btn--primary" value="{{ $vm->t['enableButton'] ?? '' }}" data-submit-once></form>
    @else
        <p>{{ $vm->insufficientMessage }}</p>
    @endif
@endif
</x-frame>
</x-page>
@endsection
