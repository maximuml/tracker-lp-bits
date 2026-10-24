@if ($items !== [])
<div class="nti-kv">
@foreach ($items as $k => $v)
    <div class="nti-row"><span class="nti-k">{{ $k }}</span><span class="nti-v">{{ $v }}</span></div>
@endforeach
</div>
@endif
