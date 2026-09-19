@props(['tags'])
@foreach ($tags as $tag)<span class="nx-tag" title="{{ $tag->description }}">{{ $tag->name }}</span>@endforeach
