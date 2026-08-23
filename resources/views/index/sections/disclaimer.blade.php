@if($disclaimer['show'])
<h2>{{ $disclaimer['title'] }}</h2>
<table width="100%"><tr><td class="text">
{{ $disclaimer['content'] }}</td></tr></table>
@endif
