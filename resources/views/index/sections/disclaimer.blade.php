@if($disclaimer->show)
<section class="nx-idx-card">
<h2>{{ $disclaimer->title }}</h2>
<div class="p-[10pt]">
{{ $disclaimer->content }}</div>
</section>
@endif