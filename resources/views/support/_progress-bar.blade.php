@props(['segments'])
<img class="bar_left" src="pic/trans.gif" alt="" />@foreach($segments as $seg)<img class="{{ $seg['class'] }}" src="pic/trans.gif" width="{{ $seg['width'] }}" alt="" />@endforeach<img class="bar_right" src="pic/trans.gif" alt="" />
