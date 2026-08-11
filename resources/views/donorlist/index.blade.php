@extends('layouts.legacy')

@section('title', 'Donorlist')

@section('content')
@php
if (\App\Support\UserDisplay::currentClass() <= UC_MODERATOR) {
    \App\Support\LegacyResponse::abort('Sorry', 'Access denied!');
}
$count = \App\Models\User::query()->where('donor', 'yes')->count();
[$pagertop, $pagerbottom, , $offset, $rpp] = \App\Support\Pagination::pager(50, $count, 'donorlist.php?');
$rows = \App\Models\User::query()
    ->where('donor', 'yes')
    ->orderByDesc('id')
    ->offset($offset)
    ->limit($rpp)
    ->get(['id', 'username', 'email', 'added', 'donated'])
    ->map(fn ($r) => (array) $r);
$users = number_format($count);
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
            <td align="left">{{ \App\Support\UserDisplay::username($arr['id']) }}</td>
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
