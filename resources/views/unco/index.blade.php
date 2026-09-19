@extends('layouts.legacy')

@section('title', 'Unconfirmed Users')

@section('content')
@if (! empty($rows ?? []))
    {{ \App\Support\Frame::open('', false, 10, '100%', 'left') }}
    <table data-nx="data" width="100%" border="1" cellspacing="0" cellpadding="5">
        @if ($status ?? '')
            <tr>
                <td class="rowhead" colspan="5"><span class="nx-color-red nx-size-1">The User account has been updated!</span></td>
            </tr>
        @endif
        <tr>
            <td class="rowhead"><center>Name</center></td>
            <td class="rowhead"><center>eMail</center></td>
            <td class="rowhead"><center>Added</center></td>
            <td class="rowhead"><center>Set Status</center></td>
            <td class="rowhead"><center>Confirm</center></td>
        </tr>
        @foreach ($rows as $row)
            <tr>
                <form method="post" action="modtask.php">
                    <input type="hidden" name="action" value="confirmuser">
                    <input type="hidden" name="userid" value="{{ $row['id'] }}">
                    <a href="userdetails.php?id={{ $row['id'] }}"><td><center>{{ $row['username'] }}</center></td></a>
                    <td align="center">&nbsp;&nbsp;&nbsp;&nbsp;{{ $row['email'] }}</td>
                    <td align="center">&nbsp;&nbsp;&nbsp;&nbsp;{{ $row['added'] }}</td>
                    <td align="center">
                        <select name="confirm">
                            <option value="pending">pending</option>
                            <option value="confirmed">confirmed</option>
                        </select>
                    </td>
                    <td align="center"><input type="submit" value="-Go-"></td>
                </form>
            </tr>
        @endforeach
    </table>
    {{ \App\Support\Frame::close() }}
@endif
@endsection
