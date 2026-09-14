<x-filament-widgets::widget class="fi-account-widget">
    <x-filament::section>
        <x-filament-panels::avatar.user
            size="lg"
            :user="filament()->auth()->user()"
            loading="lazy"
        />

        <div class="fi-account-widget-main">
            <h2 class="fi-account-widget-heading">
                {{ __('filament-panels::widgets/account-widget.welcome', ['app' => config('app.name')]) }}
            </h2>

            <p class="fi-account-widget-user-name">
                {{ filament()->getUserName(filament()->auth()->user()) . ' (' . filament()->auth()->user()->classText . ')' }}
            </p>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
