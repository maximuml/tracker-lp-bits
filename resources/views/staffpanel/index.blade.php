@php
$langFile = \App\Support\Locale::scriptFilePath('staffpanel');
if (is_file($langFile)) {
    require $langFile;
}
$langStaffpanel = $lang_staffpanel ?? [];
$siteName = \App\Models\Setting::getSiteName();
@endphp

@extends('layouts.nexus_legacy')

@section('title', ($langStaffpanel['Administration'] ?? 'Administration') . ' :: ' . $siteName)

@section('content')
    <x-nexus.card :title="$langStaffpanel['Administration'] ?? 'Administration'">
        @if (get_user_class() < UC_MODERATOR)
            <x-nexus.alert variant="danger" title="Error">
                Access denied!!!
            </x-nexus.alert>
        @else
            @if (get_user_class() >= UC_SYSOP)
                <h2 class="mb-2 text-lg font-semibold text-nexus-text">..:: {{ $langStaffpanel['For SysOp Only'] ?? 'For SysOp Only' }} ::..</h2>
                <x-nexus.table class="mb-6">
                    <x-slot:head>
                        <x-nexus.table.cell header>{{ $langStaffpanel['Option Name'] ?? 'Option Name' }}</x-nexus.table.cell>
                        <x-nexus.table.cell header>{{ $langStaffpanel['Info'] ?? 'Info' }}</x-nexus.table.cell>
                    </x-slot:head>
                    <x-slot:body>
                        @foreach (\Nexus\Database\NexusDB::table('sysoppanel')->get() as $panelRow)
                            @php $row = (array) $panelRow; @endphp
                            <x-nexus.table.row>
                                <x-nexus.table.cell>
                                    <a href="{{ $row['url'] }}" class="font-semibold text-nexus-link hover:underline">{{ $langStaffpanel[$row['name']] ?? $row['name'] }}</a>
                                </x-nexus.table.cell>
                                <x-nexus.table.cell>{{ $langStaffpanel[$row['info']] ?? $row['info'] }}</x-nexus.table.cell>
                            </x-nexus.table.row>
                        @endforeach
                    </x-slot:body>
                </x-nexus.table>
            @endif

            @if (get_user_class() >= UC_ADMINISTRATOR)
                <h2 class="mb-2 text-lg font-semibold text-nexus-text">..:: {{ $langStaffpanel['For Administrator Only'] ?? 'For Administrator Only' }} ::..</h2>
                <x-nexus.table class="mb-6">
                    <x-slot:head>
                        <x-nexus.table.cell header>{{ $langStaffpanel['Option Name'] ?? 'Option Name' }}</x-nexus.table.cell>
                        <x-nexus.table.cell header>{{ $langStaffpanel['Info'] ?? 'Info' }}</x-nexus.table.cell>
                    </x-slot:head>
                    <x-slot:body>
                        @foreach (\Nexus\Database\NexusDB::table('adminpanel')->get() as $panelRow)
                            @php $row = (array) $panelRow; @endphp
                            <x-nexus.table.row>
                                <x-nexus.table.cell>
                                    <a href="{{ $row['url'] }}" class="font-semibold text-nexus-link hover:underline">{{ $langStaffpanel[$row['name']] ?? $row['name'] }}</a>
                                </x-nexus.table.cell>
                                <x-nexus.table.cell>{{ $langStaffpanel[$row['info']] ?? $row['info'] }}</x-nexus.table.cell>
                            </x-nexus.table.row>
                        @endforeach
                    </x-slot:body>
                </x-nexus.table>
            @endif

            @if (get_user_class() >= UC_MODERATOR)
                <h2 class="mb-2 text-lg font-semibold text-nexus-text">..:: {{ $langStaffpanel['For Moderator Only'] ?? 'For Moderator Only' }} ::..</h2>
                <x-nexus.table>
                    <x-slot:head>
                        <x-nexus.table.cell header>{{ $langStaffpanel['Option Name'] ?? 'Option Name' }}</x-nexus.table.cell>
                        <x-nexus.table.cell header>{{ $langStaffpanel['Info'] ?? 'Info' }}</x-nexus.table.cell>
                    </x-slot:head>
                    <x-slot:body>
                        @foreach (\Nexus\Database\NexusDB::table('modpanel')->get() as $panelRow)
                            @php $row = (array) $panelRow; @endphp
                            <x-nexus.table.row>
                                <x-nexus.table.cell>
                                    <a href="{{ $row['url'] }}" class="font-semibold text-nexus-link hover:underline">{{ $langStaffpanel[$row['name']] ?? $row['name'] }}</a>
                                </x-nexus.table.cell>
                                <x-nexus.table.cell>{{ $langStaffpanel[$row['info']] ?? $row['info'] }}</x-nexus.table.cell>
                            </x-nexus.table.row>
                        @endforeach
                    </x-slot:body>
                </x-nexus.table>
            @endif
        @endif
    </x-nexus.card>
@endsection
