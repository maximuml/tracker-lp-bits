<x-frame :center="false">
<x-slot:caption>{{ __('legacy/staff.text_firstline_support') }}<span class="small"> - [<a class=altlink href=/web/contactstaff><b>{{ __('legacy/staff.text_apply_for_it') }}</b></a>]</span></x-slot>
{{ __('legacy/staff.text_firstline_support_note') }}
<br /><br />
<x-data-table :caption="__('legacy/staff.text_firstline_support')" captionHidden>
    <tr>
        <td class="embedded"><b>{{ __('legacy/staff.text_username')}}</b></td>
        <td class="embedded text-center"><b>{{ __('legacy/staff.text_country')}}</b></td>
        <td class="embedded text-center"><b>{{ __('legacy/staff.text_online_or_offline')}}</b></td>
        <td class="embedded text-center"><b>{{ __('legacy/staff.text_contact')}}</b></td>
        <td class="embedded text-center"><b>{{ __('legacy/staff.text_language')}}</b></td>
        <td class="embedded"><b>{{ __('legacy/staff.text_support_for')}}</b></td>
    </tr>
    <tr><td class="embedded" colspan=6><hr color="#4040c0"></td></tr>
    @foreach ($supportRows as $row)
    <tr>
@include('staff._cells')
        @foreach ($row['extras'] ?? [] as $e)<td class="embedded">{{ $e }}</td>@endforeach
    </tr>
    @endforeach
</x-data-table>
</x-frame>

<x-frame :center="false">
<x-slot:caption>{{ __('legacy/staff.text_movie_critics') }}<span class="small"> - [<a class=altlink href=/web/contactstaff><b>{{ __('legacy/staff.text_apply_for_it') }}</b></a>]</span></x-slot>
{{ __('legacy/staff.text_movie_critics_note') }}
<br /><br />
<x-data-table :caption="__('legacy/staff.text_movie_critics')" captionHidden>
    <tr>
        <td class="embedded"><b>{{ __('legacy/staff.text_username')}}</b></td>
        <td class="embedded text-center"><b>{{ __('legacy/staff.text_country')}}</b></td>
        <td class="embedded text-center"><b>{{ __('legacy/staff.text_online_or_offline')}}</b></td>
        <td class="embedded text-center"><b>{{ __('legacy/staff.text_contact')}}</b></td>
        <td class="embedded"><b>{{ __('legacy/staff.text_responsible_for')}}</b></td>
    </tr>
    <tr><td class="embedded" colspan=5><hr color="#4040c0"></td></tr>
    @foreach ($pickerRows as $row)
    <tr>
@include('staff._cells')
        @foreach ($row['extras'] ?? [] as $e)<td class="embedded">{{ $e }}</td>@endforeach
    </tr>
    @endforeach
</x-data-table>
</x-frame>

<x-frame :center="false">
<x-slot:caption>{{ __('legacy/staff.text_forum_moderators') }}<span class="small"> - [<a class=altlink href=/web/contactstaff><b>{{ __('legacy/staff.text_apply_for_it') }}</b></a>]</span></x-slot>
{{ __('legacy/staff.text_forum_moderators_note') }}
<br /><br />
<x-data-table :caption="__('legacy/staff.text_forum_moderators')" captionHidden>
    <tr>
        <td class="embedded"><b>{{ __('legacy/staff.text_username')}}</b></td>
        <td class="embedded text-center"><b>{{ __('legacy/staff.text_country')}}</b></td>
        <td class="embedded text-center"><b>{{ __('legacy/staff.text_online_or_offline')}}</b></td>
        <td class="embedded text-center"><b>{{ __('legacy/staff.text_contact')}}</b></td>
        <td class="embedded"><b>{{ __('legacy/staff.text_forums')}}</b></td>
    </tr>
    <tr><td class="embedded" colspan=5><hr color="#4040c0"></td></tr>
    @foreach ($forumModRows as $row)
    <tr>
@include('staff._cells')
        <td class="embedded">@foreach (($row['forums'] ?? []) as $f)<a href=/forums?action=viewforum&forumid={{ $f['id'] }}>{{ $f['name'] }}</a>{{ $loop->last ? '' : ', ' }}@endforeach</td>
    </tr>
    @endforeach
</x-data-table>
</x-frame>

<x-frame :center="false">
<x-slot:caption>{{ __('legacy/staff.text_general_staff') }}<span class="small"> - [<a class=altlink href=/web/contactstaff><b>{{ __('legacy/staff.text_apply_for_it') }}</b></a>]</span></x-slot>
{{ __('legacy/staff.text_general_staff_note') }} <a href=/web/faq><b>{{ __('legacy/staff.text_faq') }}</b></a> {{ __('legacy/staff.text_general_staff_note_two') }}<br /><br />{{ __('legacy/staff.text_general_staff_note_three') }} <a href=/web/contactstaff><b>{{ __('legacy/staff.text_here') }}</b></a>
<br /><br />
<x-data-table :caption="__('legacy/staff.text_general_staff')" captionHidden>
    @foreach ($staffRows as $row)
        @if (isset($row['header']))
            @if (! $loop->first)<tr height=15><td class="embedded text-right" colspan=5>&nbsp;</td></tr>@endif
            <tr height=15><td class="embedded text-right" colspan=5>{{ $row['class_name'] ?? '' }}</td></tr>
            <tr>
                <td class="embedded"><b>{{ __('legacy/staff.text_username')}}</b></td>
                <td class="embedded text-center"><b>{{ __('legacy/staff.text_country')}}</b></td>
                <td class="embedded text-center"><b>{{ __('legacy/staff.text_online_or_offline')}}</b></td>
                <td class="embedded text-center"><b>{{ __('legacy/staff.text_contact')}}</b></td>
                <td class="embedded"><b>{{ __('legacy/staff.text_duties')}}</b></td>
            </tr>
            <tr height=15><td class="embedded" colspan=5><hr color="#4040c0"></td></tr>
        @else
            <tr>
@include('staff._cells')
                @foreach ($row['extras'] ?? [] as $e)<td class="embedded">{{ $e }}</td>@endforeach
            </tr>
        @endif
    @endforeach
</x-data-table>
</x-frame>

<x-frame :center="false">
<x-slot:caption>{{ __('legacy/staff.text_vip') }}</x-slot>
{{ sprintf(__('legacy/staff.text_vip_note'), $siteName) }}
<br /><br />
<x-data-table :caption="__('legacy/staff.text_vip')" captionHidden>
    <tr>
        <td class="embedded"><b>{{ __('legacy/staff.text_username')}}</b></td>
        <td class="embedded text-center"><b>{{ __('legacy/staff.text_country')}}</b></td>
        <td class="embedded text-center"><b>{{ __('legacy/staff.text_online_or_offline')}}</b></td>
        <td class="embedded text-center"><b>{{ __('legacy/staff.text_contact')}}</b></td>
        <td class="embedded"><b>{{ __('legacy/staff.text_reason')}}</b></td>
    </tr>
    <tr><td class="embedded" colspan=5><hr color="#4040c0"></td></tr>
    @foreach ($vipRows as $row)
    <tr>
@include('staff._cells')
        @foreach ($row['extras'] ?? [] as $e)<td class="embedded">{{ $e }}</td>@endforeach
    </tr>
    @endforeach
</x-data-table>
</x-frame>
