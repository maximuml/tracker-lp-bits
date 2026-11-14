@props(['caption', 'applyLink' => false])
<x-frame :center="false">
<x-slot:caption>{{ $caption }}@if ($applyLink)<span class="small"> - [<a class="altlink" href="/web/contactstaff"><b>{{ __('staff.text_apply_for_it') }}</b></a>]</span>@endif</x-slot>
{{ $note ?? '' }}
<br /><br />
<x-data-table :caption="$caption" captionHidden>
{{ $slot }}
</x-data-table>
</x-frame>
