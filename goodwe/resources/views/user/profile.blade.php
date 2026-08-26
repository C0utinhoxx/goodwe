<x-app-layout>
    <div class="max-w-2xl">
        <div class="mb-8">
            <h1 class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">{{ __('profile.title') }}</h1>
            <p class="text-sm text-gray-500 dark:text-[#a0a0a0] mt-1">
                {{ __('profile.subtitle') }}
            </p>
        </div>

        <div class="rounded-xl border border-gray-200 dark:border-[#343843] bg-white dark:bg-[#1a1a1a] p-6">
            <div class="flex items-center gap-4 mb-6 pb-6 border-b border-gray-200 dark:border-[#343843]">
                <div class="flex h-16 w-16 items-center justify-center rounded-full bg-[#e53935]/10 text-[#e53935] text-xl font-bold">
                    {{ strtoupper(substr(Auth::user()->name, 0, 2)) }}
                </div>
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ Auth::user()->name }}</h2>
                    <p class="text-sm text-gray-500 dark:text-[#a0a0a0]">{{ Auth::user()->email }}</p>
                </div>
            </div>

            <div class="space-y-5">
                <div>
                    <label class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-[#a0a0a0]">{{ __('profile.name') }}</label>
                    <p class="mt-1.5 text-sm text-gray-900 dark:text-white">{{ Auth::user()->name }}</p>
                </div>

                <div>
                    <label class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-[#a0a0a0]">{{ __('profile.email') }}</label>
                    <p class="mt-1.5 text-sm text-gray-900 dark:text-white">{{ Auth::user()->email }}</p>
                </div>

                <div>
                    <label class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-[#a0a0a0]">{{ __('profile.member_since') }}</label>
                    <p class="mt-1.5 text-sm text-gray-900 dark:text-white">{{ Auth::user()->created_at->format('d/m/Y') }}</p>
                </div>

                @if(Auth::user()->email_verified_at)
                    <div>
                        <label class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-[#a0a0a0]">{{ __('profile.verified') }}</label>
                        <p class="mt-1.5 text-sm text-[#e53935]">{{ __('profile.verified_yes') }}</p>
                    </div>
                @endif
            </div>
        </div>

        <div class="mt-6 rounded-xl border border-gray-200 dark:border-[#343843] bg-white dark:bg-[#1a1a1a] p-6">
            <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">{{ __('profile.account_actions') }}</h3>

            <div class="flex flex-col sm:flex-row gap-3">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg border border-[#e53935]/30 px-5 py-2.5 text-sm font-medium text-[#e53935] transition-all hover:bg-[#e53935]/10 hover:border-[#e53935]/50 active:scale-[0.98]">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                        </svg>
                        {{ __('app.logout') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
