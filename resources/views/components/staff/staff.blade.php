<x-staff.section :caption="__('staff.text_firstline_support')" :apply-link="true">
    <x-slot:note>{{ __('staff.text_firstline_support_note') }}</x-slot:note>
    <tr>
        @include('components.staff.head-cells')
        <td class="nx-embedded text-center"><b>{{ __('staff.text_language')}}</b></td>
        <td class="nx-embedded"><b>{{ __('staff.text_support_for')}}</b></td>
    </tr>
    <tr><td class="nx-embedded" colspan="6"><hr class="border-nxm-accent"></td></tr>
    @foreach ($supportRows as $row)
    <tr>
@include('components.staff.cells')
        @foreach ($row['extras'] ?? [] as $e)<td class="nx-embedded">{{ $e }}</td>@endforeach
    </tr>
    @endforeach
</x-staff.section>

<x-staff.section :caption="__('staff.text_forum_moderators')" :apply-link="true">
    <x-slot:note>{{ __('staff.text_forum_moderators_note') }}</x-slot:note>
    <tr>
        @include('components.staff.head-cells')
        <td class="nx-embedded"><b>{{ __('staff.text_forums')}}</b></td>
    </tr>
    <tr><td class="nx-embedded" colspan="5"><hr class="border-nxm-accent"></td></tr>
    @foreach ($forumModRows as $row)
    <tr>
@include('components.staff.cells')
        <td class="nx-embedded">@foreach (($row['forums'] ?? []) as $f)<a href="/forums?action=viewforum&forumid={{ $f['id'] }}">{{ $f['name'] }}</a>{{ $loop->last ? '' : ', ' }}@endforeach</td>
    </tr>
    @endforeach
</x-staff.section>

<x-staff.section :caption="__('staff.text_general_staff')" :apply-link="true">
    <x-slot:note>{{ __('staff.text_general_staff_note') }} <a href="/web/faq"><b>{{ __('staff.text_faq') }}</b></a> {{ __('staff.text_general_staff_note_two') }}<br /><br />{{ __('staff.text_general_staff_note_three') }} <a href="/web/contactstaff"><b>{{ __('staff.text_here') }}</b></a></x-slot:note>
    @foreach ($staffRows as $row)
        @if (isset($row['header']))
            @if (! $loop->first)<tr height="15"><td class="nx-embedded text-right" colspan="5">&nbsp;</td></tr>@endif
            <tr height="15"><td class="nx-embedded text-right" colspan="5">{{ $row['class_name'] ?? '' }}</td></tr>
            <tr>
                @include('components.staff.head-cells')
                <td class="nx-embedded"><b>{{ __('staff.text_duties')}}</b></td>
            </tr>
            <tr height="15"><td class="nx-embedded" colspan="5"><hr class="border-nxm-accent"></td></tr>
        @else
            <tr>
@include('components.staff.cells')
                @foreach ($row['extras'] ?? [] as $e)<td class="nx-embedded">{{ $e }}</td>@endforeach
            </tr>
        @endif
    @endforeach
</x-staff.section>

<x-staff.section :caption="__('staff.text_vip')">
    <x-slot:note>{{ sprintf(__('staff.text_vip_note'), $siteName) }}</x-slot:note>
    <tr>
        @include('components.staff.head-cells')
        <td class="nx-embedded"><b>{{ __('staff.text_reason')}}</b></td>
    </tr>
    <tr><td class="nx-embedded" colspan="5"><hr class="border-nxm-accent"></td></tr>
    @foreach ($vipRows as $row)
    <tr>
@include('components.staff.cells')
        @foreach ($row['extras'] ?? [] as $e)<td class="nx-embedded">{{ $e }}</td>@endforeach
    </tr>
    @endforeach
</x-staff.section>
