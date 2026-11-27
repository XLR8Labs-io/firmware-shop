<x-layouts::app :title="__('Wallet')">
    <div class="space-y-6">
        <div class="space-y-1">
            <flux:heading size="xl" level="1">{{ __('Wallet') }}</flux:heading>
            <flux:text>{{ __('Balance: :amount', ['amount' => '$'.$balance]) }}</flux:text>
        </div>

        @if ($entries->isEmpty())
            <flux:text>{{ __('No money has moved in or out of your wallet yet.') }}</flux:text>
        @else
            <ul class="divide-y divide-zinc-200 border-y border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                @foreach ($entries as $entry)
                    <li class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 py-3">
                        <span class="min-w-0">
                            <span class="font-medium">{{ $entry->type->getLabel() }}</span>
                            {{-- Purchase notes are the file title; admin reasons stay internal. --}}
                            @if ($entry->type === App\Enums\LedgerEntryType::Purchase && $entry->note)
                                <span class="text-zinc-600 break-words dark:text-zinc-400">: {{ $entry->note }}</span>
                            @endif
                        </span>
                        <span class="flex items-center gap-4 text-sm">
                            <x-local-time :at="$entry->created_at" format="j M Y, g:i A" class="text-zinc-500 dark:text-zinc-400" />
                            <span class="font-medium tabular-nums">{{ str_starts_with($entry->amount, '-') ? '−$'.ltrim($entry->amount, '-') : '+$'.$entry->amount }}</span>
                        </span>
                    </li>
                @endforeach
            </ul>
            {{ $entries->links() }}
        @endif
    </div>
</x-layouts::app>
