@foreach($items as $node)
@if($node['children'] !== null)
<li><div class='{{ $node['type'] }}'><a href='#' class='js-info-toggle'> + <span class=title>[{{ $node['item'] }}]</span> <span class='icon'>({{ ucfirst($node['type']) }})</span> <span class=length>[{{ $node['length'] }}]</span></a></div>
<ul class='nx-hidden'>@include('components.torrent-structure', ['items' => $node['children']])</ul></li>
@else
<li><div class={{ $node['type'] }}> - <span class=title>[{{ $node['item'] }}]</span> <span class=icon>({{ ucfirst($node['type']) }})</span> <span class=length>[{{ $node['length'] }}]</span>: <span class=value>{{ $node['value'] }}</span></div></li>
@endif
@endforeach
