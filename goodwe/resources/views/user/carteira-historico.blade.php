<x-user-layout>
    <x-slot name="header">
        <h1 class="text-3xl font-semibold text-gray-900 dark:text-white">
            {{ __('wallet.full_history') }}
        </h1>
    </x-slot>

    <section class="mt-5 grid grid-cols-1 gap-5 lg:grid-cols-2">
        <div class="relative min-h-[100px] overflow-hidden rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-[#343843] dark:bg-[#1a1a1a]">
            <div class="flex items-center gap-4">
                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-[#e53935]/10 text-[#e53935]">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 11.25v8.25a1.5 1.5 0 01-1.5 1.5H5.25a1.5 1.5 0 01-1.5-1.5v-8.25M12 4.875A2.625 2.625 0 109.375 7.5H12m0-2.625V7.5m0-2.625A2.625 2.625 0 1114.625 7.5H12m0 0V21m-8.625-9.75h18c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125h-18c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-[#a0a0a0]">{{ __('wallet.available_balance_label') }}</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">
                        R$ {{ number_format($user->balance, 2, ',', '.') }}
                    </p>
                </div>
            </div>
        </div>

        <div class="relative min-h-[100px] overflow-hidden rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-[#343843] dark:bg-[#1a1a1a]">
            <div class="flex items-center gap-4">
                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-amber-500/20 text-amber-400">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-[#a0a0a0]">{{ __('wallet.total_points_label') }}</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">
                        {{ number_format($user->points, 0, ',', '.') }} pts
                    </p>
                </div>
            </div>
        </div>
    </section>

    <section class="mt-5 rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-[#343843] dark:bg-[#1a1a1a]">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
            {{ __('wallet.history') }}
        </h2>

        @if ($history->isEmpty())
            <div class="mt-5 text-center text-sm text-gray-500 dark:text-[#a0a0a0]">
                {{ __('wallet.no_transactions') }}
            </div>
        @else
            <div class="mt-5 overflow-x-auto">
                <table class="w-full min-w-[620px] text-left text-sm">
                    <thead class="border-b border-gray-200 text-gray-500 dark:border-[#343843] dark:text-[#a0a0a0]">
                        <tr>
                            <th class="py-3 font-medium">{{ __('wallet.date') }}</th>
                            <th class="py-3 font-medium">{{ __('wallet.type') }}</th>
                            <th class="py-3 font-medium">{{ __('wallet.description') }}</th>
                            <th class="py-3 font-medium">{{ __('wallet.value') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($history as $transaction)
                            <tr class="border-b border-gray-200 dark:border-[#343843]">
                                <td class="py-4 text-gray-900 dark:text-white">
                                    {{ $transaction->created_at->format('d/m/Y H:i') }}
                                </td>
                                <td class="py-4">
                                    @if ($transaction->type === 'deposit')
                                        <span class="rounded-lg bg-green-500/10 px-3 py-1 text-green-600 dark:text-green-400">
                                            {{ __('wallet.deposit') }}
                                        </span>
                                    @elseif ($transaction->type === 'cashback')
                                        <span class="rounded-lg bg-amber-500/10 px-3 py-1 text-amber-600 dark:text-amber-400">
                                            {{ __('wallet.cashback') }}
                                        </span>
                                    @elseif ($transaction->type === 'charge_payment')
                                        <span class="rounded-lg bg-[#e53935]/10 px-3 py-1 text-[#e53935]">
                                            {{ __('wallet.charge_payment') }}
                                        </span>
                                    @else
                                        <span class="rounded-lg bg-blue-500/10 px-3 py-1 text-blue-600 dark:text-blue-400">
                                            {{ __('wallet.points_redeem') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="py-4 text-gray-900 dark:text-white">
                                    {{ $transaction->description }}
                                </td>
                                <td class="py-4 font-medium text-gray-900 dark:text-white">
                                    @if ($transaction->type === 'charge_payment' || $transaction->type === 'points_redeem')
                                        - R$ {{ number_format($transaction->amount, 2, ',', '.') }}
                                    @else
                                        + R$ {{ number_format($transaction->amount, 2, ',', '.') }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-user-layout>
