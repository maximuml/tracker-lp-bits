<footer {{ $attributes->merge(['class' => 'border-t border-nexus-border bg-nexus-surface py-4 text-center text-sm text-nexus-muted']) }}>
    <p>&copy; {{ date('Y') }} {{ config('app.name') }}</p>
</footer>
