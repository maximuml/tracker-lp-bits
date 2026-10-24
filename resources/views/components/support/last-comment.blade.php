@props(['isNew', 'newLabel', 'byLabel', 'user', 'time'])
@if($isNew)<b>(<span class='new'>{{ $newLabel }}</span>)</b> @endif{{ $byLabel }}{{ $user }}{{ $time }}<br />
