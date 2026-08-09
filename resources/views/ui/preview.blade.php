@extends('layouts.nexus')

@section('title', 'UI Preview - ' . config('app.name'))

@section('content')
    <h1 class="mb-4 text-2xl font-bold text-nexus-primary">Nexus Design System Preview</h1>

    <section class="mb-6 border border-nexus-border bg-nexus-surface p-4">
        <h2 class="mb-2 text-lg font-semibold text-nexus-text">Header &amp; Navigation</h2>
        <p class="text-sm text-nexus-muted">
            The header above is responsive. Resize the viewport to see the mobile hamburger menu.
        </p>
    </section>

    <section class="mb-6 border border-nexus-border bg-nexus-surface p-4">
        <h2 class="mb-2 text-lg font-semibold text-nexus-text">Buttons</h2>
        <div class="flex flex-wrap gap-2">
            <button class="bg-nexus-primary px-4 py-2 text-nexus-primary-text">Primary</button>
            <button class="border border-nexus-border bg-nexus-surface-alt px-4 py-2 text-nexus-text">Secondary</button>
            <button class="bg-nexus-danger px-4 py-2 text-white">Danger</button>
            <button class="bg-nexus-success px-4 py-2 text-white">Success</button>
            <button class="bg-nexus-warning px-4 py-2 text-white">Warning</button>
        </div>
    </section>

    <section class="mb-6 border border-nexus-border bg-nexus-surface p-4">
        <h2 class="mb-2 text-lg font-semibold text-nexus-text">Surface &amp; Tokens</h2>
        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
            <div class="h-16 bg-nexus-bg border border-nexus-border"></div>
            <div class="h-16 bg-nexus-surface border border-nexus-border"></div>
            <div class="h-16 bg-nexus-surface-alt border border-nexus-border"></div>
            <div class="h-16 bg-nexus-primary text-nexus-primary-text flex items-center justify-center">Primary</div>
        </div>
    </section>

    <section class="border border-nexus-border bg-nexus-surface p-4">
        <h2 class="mb-2 text-lg font-semibold text-nexus-text">Typography</h2>
        <p class="text-nexus-text">Body text uses the configured sans-serif font stack.</p>
        <p class="text-sm text-nexus-muted">Muted text for secondary information.</p>
        <a href="#" class="text-nexus-link hover:underline">Link example</a>
    </section>
@endsection
