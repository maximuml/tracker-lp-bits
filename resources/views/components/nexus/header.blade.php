@props([
    'user' => null,
])

<header class="border-b border-nexus-border bg-nexus-surface">
    <nav class="mx-auto flex max-w-content items-center justify-between px-4 py-3" x-data="{ open: false }">
        <a href="{{ url('/') }}" class="text-lg font-bold text-nexus-primary hover:underline">
            {{ config('app.name') }}
        </a>

        @if ($actions ?? false)
            <div class="hidden items-center md:flex">
                {{ $actions }}
            </div>
        @endif

        <button
            type="button"
            class="inline-flex items-center justify-center rounded-md p-2 text-nexus-text hover:bg-nexus-surface-alt focus:outline-none focus:ring-2 focus:ring-nexus-primary md:hidden"
            @click="open = !open"
            aria-label="Toggle navigation"
        >
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
            </svg>
        </button>

        <div class="hidden items-center space-x-4 md:flex">
            <a href="{{ url('/torrents.php') }}" class="text-sm text-nexus-text hover:text-nexus-link">Torrents</a>
            <a href="{{ url('/forums.php') }}" class="text-sm text-nexus-text hover:text-nexus-link">Forums</a>
            <a href="{{ url('/rules.php') }}" class="text-sm text-nexus-text hover:text-nexus-link">Rules</a>
            <a href="{{ url('/faq.php') }}" class="text-sm text-nexus-text hover:text-nexus-link">FAQ</a>

            @if ($user)
                <a href="{{ url('/usercp.php') }}" class="text-sm text-nexus-text hover:text-nexus-link">{{ $user->username }}</a>
                <a href="{{ url('/logout.php') }}" class="text-sm text-nexus-text hover:text-nexus-link">Logout</a>
            @else
                <a href="{{ url('/login.php') }}" class="text-sm text-nexus-text hover:text-nexus-link">Login</a>
                <a href="{{ url('/signup.php') }}" class="text-sm text-nexus-text hover:text-nexus-link">Signup</a>
            @endif
        </div>

        <div
            class="absolute left-0 right-0 top-14 border-b border-nexus-border bg-nexus-surface px-4 py-2 md:hidden"
            x-show="open"
            x-cloak
            @click.away="open = false"
        >
            <div class="flex flex-col space-y-2">
                <a href="{{ url('/torrents.php') }}" class="text-sm text-nexus-text hover:text-nexus-link">Torrents</a>
                <a href="{{ url('/forums.php') }}" class="text-sm text-nexus-text hover:text-nexus-link">Forums</a>
                <a href="{{ url('/rules.php') }}" class="text-sm text-nexus-text hover:text-nexus-link">Rules</a>
                <a href="{{ url('/faq.php') }}" class="text-sm text-nexus-text hover:text-nexus-link">FAQ</a>

                @if ($user)
                    <a href="{{ url('/usercp.php') }}" class="text-sm text-nexus-text hover:text-nexus-link">{{ $user->username }}</a>
                    <a href="{{ url('/logout.php') }}" class="text-sm text-nexus-text hover:text-nexus-link">Logout</a>
                @else
                    <a href="{{ url('/login.php') }}" class="text-sm text-nexus-text hover:text-nexus-link">Login</a>
                    <a href="{{ url('/signup.php') }}" class="text-sm text-nexus-text hover:text-nexus-link">Signup</a>
                @endif
            </div>
        </div>
    </nav>
</header>
