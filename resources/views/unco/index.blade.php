@extends('layouts.legacy')

@section('title', 'Unconfirmed Users')

@section('content')
@php
if (\App\Support\UserDisplay::currentClass() < UC_MODERATOR) {
    \App\Support\LegacyResponse::abort('Sorry', 'Access denied.');
}
$status = \App\Support\SupportContext::getQuery('status');
if ($status) {
    \App\Support\LegacyResponse::assertId($status, true);
}
$rows = \App\Models\User::query()->where('status', 'pending')->orderBy('username')->get();
@endphp

@if ($rows->isNotEmpty())
    @php \App\Support\Html::beginFrame(''); @endphp
    <table width="100%" border="1" cellspacing="0" cellpadding="5">
        @if ($status)
            <tr>
                <td class="rowhead" colspan="5"><font color="red" size="1">The User account has been updated!</font></td>
            </tr>
        @endif
        <tr>
            <td class="rowhead"><center>Name</center></td>
            <td class="rowhead"><center>eMail</center></td>
            <td class="rowhead"><center>Added</center></td>
            <td class="rowhead"><center>Set Status</center></td>
            <td class="rowhead"><center>Confirm</center></td>
        </tr>
        @foreach ($rows as $userRow)
            @php
            $row = $userRow->getAttributes();
            $id = $row['id'];
            @endphp
            <tr>
                <form method="post" action="modtask.php">
                    <input type="hidden" name="action" value="confirmuser">
                    <input type="hidden" name="userid" value="{{ $id }}">
                    <a href="userdetails.php?id={{ $row['id'] }}"><td><center>{{ $row['username'] }}</center></td></a>
                    <td align="center">&nbsp;&nbsp;&nbsp;&nbsp;{{ $row['email'] }}</td>
                    <td align="center">&nbsp;&nbsp;&nbsp;&nbsp;{{ $row['added'] }}</td>
                    <td align="center">
                        <select name="confirm">
                            <option value="pending">pending</option>
                            <option value="confirmed">confirmed</option>
                        </select>
                    </td>
                    <td align="center"><input type="submit" value="-Go-" style="height: 20px; width: 40px"></td>
                </form>
            </tr>
        @endforeach
    </table>
    @php \App\Support\Html::endFrame(); @endphp
@else
    @if ($status)
        @php \App\Support\LegacyResponse::abort('Updated!', 'The user account has been updated.'); @endphp
    @else
        @php \App\Support\LegacyResponse::abort('Ups!', 'Nothing Found...'); @endphp
    @endif
@endif
@endsection
