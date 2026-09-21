@extends('layouts.legacy')

@section('title', 'Unconfirmed Users')

@section('content')
@if (! empty($rows ?? []))
    <x-frame :center="false">
    <table data-nx="data"><caption class="nx-sr-only">Unconfirmed Users</caption>
        @if ($status ?? '')
            <tr>
                <td class="rowhead" colspan="5"><span class="nx-color-red nx-size-1">The User account has been updated!</span></td>
            </tr>
        @endif
        <tr>
            <td class="rowhead nx-center">Name</td>
            <td class="rowhead nx-center">eMail</td>
            <td class="rowhead nx-center">Added</td>
            <td class="rowhead nx-center">Set Status</td>
            <td class="rowhead nx-center">Confirm</td>
        </tr>
        @foreach ($rows as $row)
            <tr>
                <form method="post" action="modtask.php">
                    <input type="hidden" name="action" value="confirmuser">
                    <input type="hidden" name="userid" value="{{ $row['id'] }}">
                    <td><a href="userdetails.php?id={{ $row['id'] }}">{{ $row['username'] }}</a></td>
                    <td class="nx-center">&nbsp;&nbsp;&nbsp;&nbsp;{{ $row['email'] }}</td>
                    <td class="nx-center">&nbsp;&nbsp;&nbsp;&nbsp;{{ $row['added'] }}</td>
                    <td class="nx-center">
                        <select name="confirm">
                            <option value="pending">pending</option>
                            <option value="confirmed">confirmed</option>
                        </select>
                    </td>
                    <td class="nx-center"><input type="submit" value="-Go-"></td>
                </form>
            </tr>
        @endforeach
    </table>
    </x-frame>
@endif
@endsection
