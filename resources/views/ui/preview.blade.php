@extends('layouts.nexus')

@section('title', 'UI Preview - ' . config('app.name'))

@section('content')
    <h1 class="mb-4 text-2xl font-bold text-nexus-primary">Nexus Design System Preview</h1>

    <x-nexus.alert variant="info" class="mb-6" title="Design system status">
        This preview exercises the new Nexus component library. Resize the viewport to verify responsive behavior.
    </x-nexus.alert>

    <x-nexus.card title="Buttons" class="mb-6">
        <div class="flex flex-wrap gap-2">
            <x-nexus.button variant="primary">Primary</x-nexus.button>
            <x-nexus.button variant="secondary">Secondary</x-nexus.button>
            <x-nexus.button variant="success">Success</x-nexus.button>
            <x-nexus.button variant="warning">Warning</x-nexus.button>
            <x-nexus.button variant="danger">Danger</x-nexus.button>
            <x-nexus.button variant="ghost" href="#">Link</x-nexus.button>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-2">
            <x-nexus.button variant="primary" size="sm">Small</x-nexus.button>
            <x-nexus.button variant="primary" size="md">Medium</x-nexus.button>
            <x-nexus.button variant="primary" size="lg">Large</x-nexus.button>
            <x-nexus.button variant="secondary" disabled="true">Disabled</x-nexus.button>
        </div>
    </x-nexus.card>

    <x-nexus.card title="Badges" class="mb-6">
        <div class="flex flex-wrap gap-2">
            <x-nexus.badge>Default</x-nexus.badge>
            <x-nexus.badge variant="primary">Primary</x-nexus.badge>
            <x-nexus.badge variant="success">Success</x-nexus.badge>
            <x-nexus.badge variant="warning">Warning</x-nexus.badge>
            <x-nexus.badge variant="danger">Danger</x-nexus.badge>
        </div>
    </x-nexus.card>

    <x-nexus.card title="Table" class="mb-6">
        <x-nexus.table>
            <x-slot:head>
                <x-nexus.table.cell header>Torrent</x-nexus.table.cell>
                <x-nexus.table.cell header>Size</x-nexus.table.cell>
                <x-nexus.table.cell header>Status</x-nexus.table.cell>
            </x-slot:head>

            <x-nexus.table.row>
                <x-nexus.table.cell>Ubuntu ISO</x-nexus.table.cell>
                <x-nexus.table.cell>1.2 GB</x-nexus.table.cell>
                <x-nexus.table.cell><x-nexus.badge variant="success">Seeding</x-nexus.badge></x-nexus.table.cell>
            </x-nexus.table.row>
            <x-nexus.table.row>
                <x-nexus.table.cell>Sample release</x-nexus.table.cell>
                <x-nexus.table.cell>450 MB</x-nexus.table.cell>
                <x-nexus.table.cell><x-nexus.badge variant="warning">Leeching</x-nexus.badge></x-nexus.table.cell>
            </x-nexus.table.row>
        </x-nexus.table>
    </x-nexus.card>

    <x-nexus.card title="Form row" class="mb-6">
        <form class="space-y-4" onsubmit="return false;">
            <x-nexus.form.row label="Username" for="username" required help="Between 3 and 20 characters.">
                <input id="username" type="text" class="w-full border border-nexus-border bg-nexus-bg px-3 py-2 text-nexus-text focus:outline-none focus:ring-2 focus:ring-nexus-primary" />
            </x-nexus.form.row>

            <x-nexus.form.row label="Email" for="email" error="This email is already in use.">
                <input id="email" type="email" class="w-full border border-nexus-border bg-nexus-bg px-3 py-2 text-nexus-text focus:outline-none focus:ring-2 focus:ring-nexus-primary" />
            </x-nexus.form.row>

            <x-nexus.form.row>
                <x-nexus.button variant="primary" type="submit">Submit</x-nexus.button>
            </x-nexus.form.row>
        </form>
    </x-nexus.card>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <x-nexus.alert variant="success" title="Success">
            Operation completed successfully.
        </x-nexus.alert>

        <x-nexus.alert variant="warning" title="Warning">
            This action cannot be undone.
        </x-nexus.alert>
    </div>
@endsection
