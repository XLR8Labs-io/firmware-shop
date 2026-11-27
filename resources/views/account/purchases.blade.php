<x-layouts::app :title="__('My purchases')">
    <div class="space-y-6">
        <flux:heading size="xl" level="1">{{ __('My purchases') }}</flux:heading>

        @if ($purchases->isEmpty())
            <flux:text>{{ __('You have not bought any files yet.') }}</flux:text>
        @else
            <ul class="divide-y divide-zinc-200 border-y border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                @foreach ($purchases as $purchase)
                    <li class="space-y-1 py-4">
                        <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                            @if ($purchase->product->trashed())
                                <span class="font-medium break-words">{{ $purchase->product->title }}</span>
                            @else
                                <a href="{{ route('products.show', $purchase->product) }}" class="font-medium break-words hover:text-accent-content hover:underline">{{ $purchase->product->title }}</a>
                            @endif
                            <span class="flex items-center gap-3 text-sm text-zinc-500 dark:text-zinc-400">
                                <x-local-time :at="$purchase->updated_at" format="j M Y" />
                                <span class="font-medium text-zinc-900 dark:text-zinc-100">${{ $purchase->price_paid }}</span>
                                <flux:badge size="sm" :color="$purchase->status === App\Enums\PurchaseStatus::Active ? 'green' : 'zinc'">{{ $purchase->status->getLabel() }}</flux:badge>
                            </span>
                        </div>
                        @if ($purchase->status === App\Enums\PurchaseStatus::Active && filled($purchase->product->file_password))
                            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                                {{ __('File password:') }} <span class="font-mono break-all text-zinc-900 dark:text-zinc-100">{{ $purchase->product->file_password }}</span>
                            </p>
                        @endif
                    </li>
                @endforeach
            </ul>
            {{ $purchases->links() }}
        @endif
    </div>
</x-layouts::app>
