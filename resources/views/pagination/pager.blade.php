@if ($vm->links === [])
<p align="center" class='nexus-pagination'>@include('pagination._nav', ['vm' => $vm])</p>
@elseif ($top)
<p align="center" class='nexus-pagination'>@include('pagination._nav', ['vm' => $vm])<br />@include('pagination._links', ['vm' => $vm])</p>
@else
<p align="center" class='nexus-pagination'>@include('pagination._links', ['vm' => $vm])<br />@include('pagination._nav', ['vm' => $vm])</p>
@endif
