@if($disclaimer->show)
<section class="nx-idx-card">
<h2>{{ $disclaimer->title }}</h2>
<div class="nx-text">
{{ $disclaimer->content }}</div>
</section>
@endif