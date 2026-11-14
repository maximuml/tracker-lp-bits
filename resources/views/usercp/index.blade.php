
@extends('layouts.app', ['chromeVariant' => 'modern'])

@section('title', $title ?? (__('usercp.head_control_panel')))

@section('content')
<h1 class="nx-sr-only">{{ $title ?? __('usercp.head_control_panel') }}</h1>
@if ($errors->any())
<x-alert type="error" :title="__('functions.std_error')">
    @foreach ($errors->all() as $message)<div>{{ $message }}</div>@endforeach
</x-alert>
@endif
@if (($vm->action ?? '') === 'personal')
@include('usercp.sections.personal', ['personal' => $vm->personal, 'type' => $vm->type])
@elseif (($vm->action ?? '') === 'tracker')
@include('usercp.sections.tracker', ['tracker' => $vm->tracker, 'type' => $vm->type])
@elseif (($vm->action ?? '') === 'forum')
@include('usercp.sections.forum', ['forum' => $vm->forum, 'type' => $vm->type])
@elseif (($vm->action ?? '') === 'security')
@include('usercp.sections.security', ['security' => $vm->security, 'curUser' => $vm->curUser, 'type' => $vm->type])
@else
@include('usercp.sections.home', ['home' => $vm->home, 'curUser' => $vm->curUser])
@endif
@endsection
