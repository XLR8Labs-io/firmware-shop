@props(['title' => null, 'description' => null, 'image' => null])

@inject('siteSettings', App\Services\SiteSettings::class)

@php
    $siteName = $siteSettings->siteName();
    $tickerText = $siteSettings->get('ticker_text');
    $tickerAnnouncements = App\Models\Announcement::query()->published()->where('show_in_ticker', true)->latest('published_at')->limit(5)->get(['id', 'title']);
    $footerPages = App\Models\Page::query()->published()->orderBy('title')->get(['title', 'slug']);
    $supportContactText = $siteSettings->get('support_contact_text');
    $socialLinks = $siteSettings->socialLinks();
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="flex min-h-screen flex-col bg-white text-zinc-900 antialiased dark:bg-zinc-900 dark:text-zinc-100">
        @if (filled($tickerText) || $tickerAnnouncements->isNotEmpty())
            {{-- Hovering or focusing the bar pauses it; with reduced motion the text sits still and wraps. --}}
            <div
                class="group overflow-hidden border-b border-zinc-200 bg-zinc-50 py-2 text-sm text-zinc-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-zinc-400 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200"
                role="region"
                aria-label="{{ __('Notices') }}"
                tabindex="0"
            >
                <p class="inline-block animate-ticker whitespace-nowrap pl-[100%] group-hover:[animation-play-state:paused] group-focus-within:[animation-play-state:paused] motion-reduce:animate-none motion-reduce:whitespace-normal motion-reduce:px-4">
                    @if (filled($tickerText))
                        <span>{{ $tickerText }}</span>
                    @endif
                    @foreach ($tickerAnnouncements as $announcement)
                        <span aria-hidden="true" class="px-3">•</span>
                        <a href="{{ route('announcements.show', $announcement) }}" class="font-medium underline-offset-4 hover:underline">{{ $announcement->title }}</a>
                    @endforeach
                </p>
            </div>
        @endif

        <header class="border-b border-zinc-200 dark:border-zinc-700">
            <div class="mx-auto flex h-16 w-full max-w-6xl items-center gap-3 px-4 sm:px-6">
                <a href="{{ route('home') }}" class="flex shrink-0 items-center" wire:navigate>
                    <x-brand-logo class="h-10" :alt="$siteName" />
                </a>

                <flux:spacer />

                @auth
                    <flux:dropdown position="bottom" align="end">
                        <flux:profile :initials="auth()->user()->initials()" icon:trailing="chevron-down" :aria-label="__('Account menu')" />

                        <flux:menu>
                            <div class="px-2 py-1.5 text-sm">
                                {{ __('Balance') }}: <span class="font-semibold tabular-nums">${{ app(App\Services\Wallet\WalletService::class)->balance(auth()->user()) }}</span>
                            </div>
                            <flux:menu.separator />
                            <flux:menu.item :href="route('dashboard')" icon="layout-grid" wire:navigate>
                                {{ __('Dashboard') }}
                            </flux:menu.item>
                            <flux:menu.item :href="route('account.purchases')" icon="shopping-bag" wire:navigate>
                                {{ __('My purchases') }}
                            </flux:menu.item>
                            <flux:menu.item :href="route('account.add-funds')" icon="plus-circle" wire:navigate>
                                {{ __('Add funds') }}
                            </flux:menu.item>
                            <flux:menu.item :href="route('account.wallet')" icon="wallet" wire:navigate>
                                {{ __('Wallet') }}
                            </flux:menu.item>
                            <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                                {{ __('Settings') }}
                            </flux:menu.item>
                            @if (auth()->user()->is_admin)
                                <flux:menu.item :href="route('filament.admin.pages.dashboard')" icon="shield-check">
                                    {{ __('Admin panel') }}
                                </flux:menu.item>
                            @endif

                            <flux:menu.separator />

                            <form method="POST" action="{{ route('logout') }}" class="w-full">
                                @csrf
                                <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer">
                                    {{ __('Log out') }}
                                </flux:menu.item>
                            </form>
                        </flux:menu>
                    </flux:dropdown>
                @else
                    <flux:button :href="route('login')" variant="ghost" size="sm" wire:navigate>{{ __('Log in') }}</flux:button>
                    @if (Route::has('register'))
                        <flux:button :href="route('register')" variant="primary" size="sm" wire:navigate>{{ __('Register') }}</flux:button>
                    @endif
                @endauth
            </div>

            <div class="mx-auto flex w-full max-w-6xl flex-wrap items-center gap-x-6 gap-y-3 px-4 pb-4 sm:px-6">
                <form method="GET" action="{{ route('search') }}" role="search" class="w-full sm:max-w-md">
                    <flux:input
                        name="q"
                        :value="request()->routeIs('search') ? request()->string('q')->limit(100, '')->toString() : ''"
                        icon="magnifying-glass"
                        maxlength="100"
                        :placeholder="__('Model, model number or codename')"
                        :aria-label="__('Search files')"
                    />
                </form>
                <nav aria-label="{{ __('Browse') }}" class="flex flex-wrap gap-x-5 gap-y-2 text-sm font-medium">
                    <a href="{{ route('recent') }}" class="hover:text-accent-content hover:underline">{{ __('Recent files') }}</a>
                    <a href="{{ route('top') }}" class="hover:text-accent-content hover:underline">{{ __('Top files') }}</a>
                    <a href="{{ route('file-requests.create') }}" class="hover:text-accent-content hover:underline">{{ __('Request a file') }}</a>
                    <a href="{{ route('agents.index') }}" class="hover:text-accent-content hover:underline">{{ __('World agents') }}</a>
                    <a href="{{ route('blog.index') }}" class="hover:text-accent-content hover:underline">{{ __('Blog') }}</a>
                </nav>
            </div>
        </header>

        <main class="flex-1">
            {{ $slot }}
        </main>

        <footer class="border-t border-zinc-200 dark:border-zinc-700">
            <div class="mx-auto w-full max-w-6xl space-y-4 px-4 py-8 text-sm text-zinc-600 sm:px-6 dark:text-zinc-400">
                @if ($socialLinks !== [])
                    <nav aria-label="{{ __('Community') }}">
                        <ul class="flex flex-wrap gap-x-6 gap-y-2">
                            @foreach ($socialLinks as $label => $url)
                                <li>
                                    <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="font-medium text-zinc-900 underline-offset-4 hover:underline dark:text-zinc-100">{{ $label }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </nav>
                @endif

                @if (filled($supportContactText))
                    <p class="whitespace-pre-line">{{ $supportContactText }}</p>
                @endif

                <p>{{ __('We are not responsible for damage caused by flashing files from this site. Back up your device before you flash it.') }}</p>

                <nav aria-label="{{ __('About the store') }}">
                    <ul class="flex flex-wrap gap-x-6 gap-y-2">
                        <li><a href="{{ route('announcements.index') }}" class="hover:underline">{{ __('Announcements') }}</a></li>
                        @foreach ($footerPages as $page)
                            <li><a href="{{ route('pages.show', $page) }}" class="hover:underline">{{ $page->title }}</a></li>
                        @endforeach
                        <li><a href="{{ route('rss') }}" class="hover:underline">{{ __('RSS feed of recent files') }}</a></li>
                    </ul>
                </nav>

                <p>&copy; {{ now()->year }} {{ $siteName }}</p>
            </div>
        </footer>

        @fluxScripts
    </body>
</html>
