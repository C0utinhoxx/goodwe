@props(['variant' => ''])

<div class="relative">
    <button type="button" id="lang-toggle" aria-label="Selecionar idioma: {{ __('language.'.app()->getLocale()) }}" aria-expanded="false" aria-controls="lang-menu" class="flex items-center gap-2 {{ $variant === 'boxed' ? 'rounded-lg border border-gray-200 dark:border-[#343843] bg-gray-50 dark:bg-[#181b22] px-3 py-2' : '' }} text-sm text-gray-600 dark:text-[#A1A09A] hover:text-[#e53935] dark:hover:text-[#e53935] transition-colors cursor-pointer">
        <span class="text-base leading-none">@if(app()->getLocale() === 'pt') 🇧🇷 @elseif(app()->getLocale() === 'en') 🇺🇸 @else 🇪🇸 @endif</span>
        <span>{{ __('language.' . app()->getLocale()) }}</span>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
    </button>
    <div id="lang-menu" class="hidden absolute right-0 top-full mt-2 bg-white dark:bg-[#1a1a1a] border border-gray-200 dark:border-[#343843] rounded-xl shadow-xl z-50 min-w-[170px] overflow-hidden">
        <a href="{{ route('locale.set', 'pt') }}" class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 dark:text-white hover:bg-[#e53935]/10 hover:text-[#e53935] transition-colors {{ app()->getLocale() === 'pt' ? 'font-bold bg-[#e53935]/5 text-[#e53935]' : '' }}">
            <span class="text-base leading-none">🇧🇷</span> {{ __('language.pt') }}
        </a>
        <a href="{{ route('locale.set', 'en') }}" class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 dark:text-white hover:bg-[#e53935]/10 hover:text-[#e53935] transition-colors {{ app()->getLocale() === 'en' ? 'font-bold bg-[#e53935]/5 text-[#e53935]' : '' }}">
            <span class="text-base leading-none">🇺🇸</span> {{ __('language.en') }}
        </a>
        <a href="{{ route('locale.set', 'es') }}" class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 dark:text-white hover:bg-[#e53935]/10 hover:text-[#e53935] transition-colors {{ app()->getLocale() === 'es' ? 'font-bold bg-[#e53935]/5 text-[#e53935]' : '' }}">
            <span class="text-base leading-none">🇪🇸</span> {{ __('language.es') }}
        </a>
    </div>
</div>
