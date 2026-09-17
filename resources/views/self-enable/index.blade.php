@extends('layouts.legacy')

@section('title', $title)

@section('content')
{{ \App\Support\Frame::open((string) $title, true, 10, '100%', 'center') }}
@if ($unit <= 0)
    <h3>{{ $t['featureDisabled'] ?? '' }}</h3>
@elseif ($enabled)
    <h3>{{ $t['statusNormal'] ?? '' }}</h3>
@elseif (! $latestBanLog)
    <h3>{{ $t['noBanInfo'] ?? '' }}</h3>
@elseif ($showError ?? false)
    @if (! $isUserBonusEnough)
        {{ \App\Support\Frame::stdMessage('Error', (string) $insufficientMessage, false) }}
    @endif
@else
    <h3>{{ $t['latestBanInfo'] ?? '' }}</h3>
    <table data-nx="data" id="ban-info" border="1" cellpadding="5" cellspacing="0"><tbody>
    <tr><th>UID：</th><td>{{ $latestBanLog->uid }}</td></tr>
    <tr><th>Username：</th><td>{{ $latestBanLog->username }}</td></tr>
    <tr><th>Reason：</th><td>{{ $latestBanLog->reason }}</td></tr>
    <tr><th>CreatedAt：</th><td>{{ $latestBanLog->created_at }}</td></tr>
    </tbody></table>
    <p>{{ $t['deductPerDay'] ?? '' }}</p>
    <p>{{ $t['deductTotal'] ?? '' }}</p>
    @if ($isUserBonusEnough)
        <p>{{ $t['enableDesc'] ?? '' }}</p>
        <form method="post"><input type="hidden" name="submit" value="1"><input type="submit" value="{{ $t['enableButton'] ?? '' }}"></form>
    @else
        <p>{{ $insufficientMessage }}</p>
    @endif
@endif
{{ \App\Support\Frame::close() }}
@endsection
