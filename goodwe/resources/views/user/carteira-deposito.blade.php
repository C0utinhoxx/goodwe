<x-user-layout>
    <x-slot name="header">
        <h1 class="text-3xl font-semibold text-gray-900 dark:text-white">
            {{ __('wallet.deposit_title') }}
        </h1>
        <p class="mt-2 text-sm text-gray-500 dark:text-[#a0a0a0]">
            {{ __('wallet.deposit_description') }}
        </p>
    </x-slot>

    <section class="mt-5 max-w-lg">
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-[#343843] dark:bg-[#1a1a1a]">
            <div class="flex items-center gap-4 mb-6">
                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-[#e53935]/10 text-[#e53935]">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 11.25v8.25a1.5 1.5 0 01-1.5 1.5H5.25a1.5 1.5 0 01-1.5-1.5v-8.25M12 4.875A2.625 2.625 0 109.375 7.5H12m0-2.625V7.5m0-2.625A2.625 2.625 0 1114.625 7.5H12m0 0V21m-8.625-9.75h18c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125h-18c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-[#a0a0a0]">
                        {{ __('wallet.available_balance_label') }}
                    </p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">
                        R$ {{ number_format($user->balance, 2, ',', '.') }}
                    </p>
                </div>
            </div>

            <form method="POST" action="{{ route('wallet.deposit.store') }}">
                @csrf
                <div class="mb-6">
                    <label for="amount" class="block text-sm font-medium text-gray-700 dark:text-[#a0a0a0] mb-2">
                        {{ __('wallet.deposit_amount') }}
                    </label>
                    <input
                        type="number"
                        id="amount"
                        name="amount"
                        min="1"
                        max="10000"
                        step="0.01"
                        value="{{ old('amount') }}"
                        class="w-full rounded-lg border border-gray-300 px-4 py-3 text-lg text-gray-900 dark:border-[#343843] dark:bg-[#22252d] dark:text-white focus:border-[#e53935] focus:ring-1 focus:ring-[#e53935] outline-none"
                        required
                    />
                    @error('amount')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center gap-3">
                    <a
                        href="{{ route('wallet.index') }}"
                        class="rounded-lg border border-gray-300 px-6 py-3 text-sm font-medium hover:bg-gray-100 dark:border-[#343843] dark:hover:bg-[#22252d] transition-colors text-gray-900 dark:text-white"
                    >
                        {{ __('wallet.back') }}
                    </a>
                    <button
                        type="submit"
                        class="rounded-lg bg-[#e53935] px-6 py-3 text-sm font-semibold text-white hover:bg-[#c62828] transition-colors"
                    >
                        {{ __('wallet.deposit_button') }}
                    </button>
                </div>
            </form>
        </div>
    </section>
</x-user-layout>
