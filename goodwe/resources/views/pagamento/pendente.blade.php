<x-user-layout>
    <x-slot name="header">
        <h1 class="text-3xl font-semibold text-gray-900 dark:text-white">
            {{ __('payment.pending_title') }}
        </h1>
    </x-slot>

    <section class="mt-5 max-w-lg">
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-[#343843] dark:bg-[#1a1a1a]">
            <div class="flex items-center gap-4 mb-6">
                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-yellow-500/10 text-yellow-500">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <p class="text-sm text-gray-500 dark:text-[#a0a0a0]">
                    {{ __('payment.pending_description') }}
                </p>
            </div>

            <a href="{{ route('wallet.index') }}" class="inline-flex items-center justify-center rounded-lg bg-[#e53935] px-4 py-2.5 text-sm font-medium text-white hover:bg-[#e53935]/90">
                {{ __('payment.back_to_wallet') }}
            </a>
        </div>
    </section>
</x-user-layout>
