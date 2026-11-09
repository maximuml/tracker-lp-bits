@extends('layouts.app')

@section('title', __('unco.title'))

@section('content')
<x-page :title="__('unco.title')" width="list">
@if (! empty($rows ?? []))
    <x-frame :center="false">
    <x-data-table :caption="__('unco.title')" captionHidden>
        @if ($status ?? '')
            <tr>
                <td class="whitespace-nowrap align-top px-2.5 py-1.5 text-right font-semibold text-nxm-text-dim" colspan="5"><span class="text-nxm-danger text-[10px]">{{ __('unco.text_account_updated') }}</span></td>
            </tr>
        @endif
        <tr>
            <td class="whitespace-nowrap align-top px-2.5 py-1.5 text-right font-semibold text-nxm-text-dim text-center">{{ __('unco.col_name') }}</td>
            <td class="whitespace-nowrap align-top px-2.5 py-1.5 text-right font-semibold text-nxm-text-dim text-center">{{ __('unco.col_email') }}</td>
            <td class="whitespace-nowrap align-top px-2.5 py-1.5 text-right font-semibold text-nxm-text-dim text-center">{{ __('unco.col_added') }}</td>
            <td class="whitespace-nowrap align-top px-2.5 py-1.5 text-right font-semibold text-nxm-text-dim text-center">{{ __('unco.col_set_status') }}</td>
            <td class="whitespace-nowrap align-top px-2.5 py-1.5 text-right font-semibold text-nxm-text-dim text-center">{{ __('unco.col_confirm') }}</td>
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
                            <option value="pending">{{ __('unco.status_pending') }}</option>
                            <option value="confirmed">{{ __('unco.status_confirmed') }}</option>
                        </select>
                    </td>
                    <td class="text-center"><input type="submit" class="nx-btn nx-btn--primary" value="{{ __('unco.submit_go') }}"></td>
                </form>
            </tr>
        @endforeach
    </x-data-table>
    </x-frame>
@endif
</x-page>
@endsection
