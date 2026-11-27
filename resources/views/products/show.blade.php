<x-layouts::site :title="$product->title" :description="$product->short_description ?: __('Download :title.', ['title' => $product->title])">
    <div class="mx-auto w-full max-w-6xl space-y-8 px-4 py-10 sm:px-6">
        <div class="space-y-3">
            <x-breadcrumbs :folders="$breadcrumbs" />
            <h1 class="text-2xl font-semibold break-words">{{ $product->title }}</h1>
            @if ($product->short_description)
                <p class="max-w-3xl text-zinc-600 dark:text-zinc-400">{{ $product->short_description }}</p>
            @endif
        </div>

        <div class="grid gap-8 lg:grid-cols-3">
            <div class="space-y-8 lg:col-span-2">
                <dl class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-2 text-sm">
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('File name') }}</dt>
                    <dd class="break-all">{{ $product->file_name }}</dd>
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Size') }}</dt>
                    <dd>{{ Number::fileSize($product->file_size, precision: 2) }}</dd>
                    @if ($product->md5)
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('MD5') }}</dt>
                        <dd class="font-mono break-all">{{ $product->md5 }}</dd>
                    @endif
                    @if ($product->published_at)
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Published') }}</dt>
                        <dd><x-local-time :at="$product->published_at" /></dd>
                    @endif
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Downloads') }}</dt>
                    <dd>{{ number_format($product->download_count) }}</dd>
                    @if ($showPassword)
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('File password') }}</dt>
                        <dd class="font-mono break-all">{{ $product->file_password }}</dd>
                    @endif
                </dl>

                @if ($product->description)
                    {{-- Raw HTML from the description is escaped and unsafe links are dropped. --}}
                    <div class="max-w-3xl space-y-4 leading-relaxed [&_a]:text-accent-content [&_a]:underline [&_h2]:text-lg [&_h2]:font-semibold [&_h3]:font-semibold [&_ol]:list-decimal [&_ol]:pl-5 [&_ul]:list-disc [&_ul]:pl-5">
                        {!! Str::markdown($product->description, ['html_input' => 'escape', 'allow_unsafe_links' => false]) !!}
                    </div>
                @endif

                <flux:callout variant="warning" icon="exclamation-triangle" :heading="__('Before you flash')">
                    <flux:callout.text>
                        <ul class="list-disc space-y-1 pl-5">
                            <li>{{ __('Back up the Security and Persist partitions first.') }}</li>
                            <li>{{ __('Devices with anti-rollback protection must not be downgraded.') }}</li>
                            <li>{{ __('Sharing purchased files leads to a permanent ban.') }}</li>
                            <li>{{ __('You flash at your own risk; we are not responsible for damage.') }}</li>
                        </ul>
                    </flux:callout.text>
                </flux:callout>
            </div>

            {{-- On phones the price and the way to buy come before the details. --}}
            <aside class="order-first space-y-3 self-start rounded-lg border border-zinc-200 p-5 lg:order-last dark:border-zinc-700">
                <p class="text-2xl font-semibold">{{ $product->displayPrice() }}</p>
                @guest
                    <flux:button :href="route('login')" variant="primary" class="w-full">
                        {{ $product->isFree() ? __('Log in to download') : __('Log in to buy') }}
                    </flux:button>
                    @if (Route::has('register'))
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">
                            {{ __('No account yet?') }} <a href="{{ route('register') }}" class="font-medium text-accent-content hover:underline">{{ __('Register') }}</a>
                        </p>
                    @endif
                @endguest
                @auth
                    @if (session('download_error'))
                        <flux:callout variant="danger" icon="x-circle" :heading="session('download_error')" />
                    @endif
                    @if (($ownsProduct || $product->isFree()) && auth()->user()->hasVerifiedEmail())
                        <form method="POST" action="{{ route('downloads.store', $product) }}">
                            @csrf
                            <flux:button type="submit" variant="primary" icon="arrow-down-tray" class="w-full">{{ __('Download') }}</flux:button>
                        </form>
                    @endif
                    @if ($ownsProduct)
                        <flux:callout variant="success" icon="check-circle" :heading="__('You own this file')" />
                    @elseif (! $product->isFree())
                        @if (auth()->user()->hasVerifiedEmail())
                            <livewire:buy-product :product="$product" />
                        @else
                            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                                {{ __('Confirm your email address to buy files.') }}
                                <a href="{{ route('verification.notice') }}" class="font-medium text-accent-content hover:underline">{{ __('Confirm now') }}</a>
                            </p>
                        @endif
                    @endif
                @endauth
            </aside>
        </div>

        @if ($relatedProducts->isNotEmpty())
            <section aria-labelledby="related-heading" class="space-y-4">
                <h2 id="related-heading" class="text-lg font-semibold">{{ __('More files in :folder', ['folder' => $product->folder->name]) }}</h2>
                <x-product-list :products="$relatedProducts" />
            </section>
        @endif
    </div>
</x-layouts::site>
