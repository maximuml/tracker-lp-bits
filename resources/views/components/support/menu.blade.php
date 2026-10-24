@props(['items'])
<div id="nav"><ul id="mainmenu" class="menu">@foreach($items as $item){{ $item }}@endforeach</ul></div>
