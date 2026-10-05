@props(['caption'])
@if ((string) $caption !== '')<h2>{{ $caption }}</h2>@endif
<div class="nx-box text-center">
<x-data-table :caption="$caption" captionHidden>{{ $slot }}</x-data-table>
</div>
