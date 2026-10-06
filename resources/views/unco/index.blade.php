@extends('layouts.app', ['chromeVariant' => 'legacy'])

@section('title', 'Unconfirmed Users')

@section('content')
@if (! empty($rows ?? []))
    <x-frame :center="false">
    <x-data-table caption="Unconfirmed Users" captionHidden>
        @if ($status ?? '')
            <tr>
                <td class="whitespace-nowrap align-top px-2.5 py-1.5 text-right font-semibold text-nxm-text-dim" colspan="5"><span class="text-nxm-danger text-[10px]">The User account has been updated!</span></td>
            </tr>
        @endif
        <tr>
            <td class="whitespace-nowrap align-top px-2.5 py-1.5 text-right font-semibold text-nxm-text-dim text-center">Name</td>
            <td class="whitespace-nowrap align-top px-2.5 py-1.5 text-right font-semibold text-nxm-text-dim text-center">eMail</td>
            <td class="whitespace-nowrap align-top px-2.5 py-1.5 text-right font-semibold text-nxm-text-dim text-center">Added</td>
            <td class="whitespace-nowrap align-top px-2.5 py-1.5 text-right font-semibold text-nxm-text-dim text-center">Set Status</td>
            <td class="whitespace-nowrap align-top px-2.5 py-1.5 text-right font-semibold text-nxm-text-dim text-center">Confirm</td>
        </tr>
        @foreach ($rows as $row)
            <tr>
                <form method="post" action="/web/staff/modtask">@csrf
                    <input type="hidden" name="action" value="confirmuser">
                    <input type="hidden" name="userid" value="{{ $row['id'] }}">
                    <td><a href="/userdetails?id={{ $row['id'] }}">{{ $row['username'] }}</a></td>
                    <td class="text-center">&nbsp;&nbsp;&nbsp;&nbsp;{{ $row['email'] }}</td>
                    <td class="text-center">&nbsp;&nbsp;&nbsp;&nbsp;{{ $row['added'] }}</td>
                    <td class="text-center">
                        <select name="confirm">
                            <option value="pending">pending</option>
                            <option value="confirmed">confirmed</option>
                        </select>
                    </td>
                    <td class="text-center"><input type="submit" value="-Go-"></td>
                </form>
            </tr>
        @endforeach
    </x-data-table>
    </x-frame>
@endif
@endsection
