@props(['cls', 'tip', 'text', 'domtt'])
 <b>[<span class='{{ $cls }}'{{ $tip }}>{{ $text }}</span>@if($domtt !== null)<template class="nx-tt">{{ $domtt }}</template>@endif</b>
