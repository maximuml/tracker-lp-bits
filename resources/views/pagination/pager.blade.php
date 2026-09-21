@if ($vm->links === [])
<p class="nexus-pagination nx-center">@include('pagination._nav', ['vm' => $vm])</p>
@elseif ($top)
<p class="nexus-pagination nx-center">@include('pagination._nav', ['vm' => $vm])<br />@include('pagination._links', ['vm' => $vm])</p>
@else
<p class="nexus-pagination nx-center">@include('pagination._links', ['vm' => $vm])<br />@include('pagination._nav', ['vm' => $vm])</p>
@endif
