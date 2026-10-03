@props(['slotsLabel', 'unlimited' => null, 'max' => null])
<span class='color_slots'>{{ $slotsLabel }}</span>@if($max !== null)<a href='faq.php#id215'>{{ $max }}</a>@else{{ $unlimited }}@endif
