<x-manager-legacy-layout>
    <x-slot name="header"><div><p class="mb-2 text-sm font-semibold text-[#c62828] dark:text-red-300">MINHA CONTA</p><h1 class="text-3xl font-semibold">Meu perfil</h1><p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Atualize os dados pessoais da conta de gestor.</p></div></x-slot>
    <section class="gw-card mb-6 rounded-xl border border-gray-200 bg-white p-5 sm:p-7 dark:border-[#343843] dark:bg-[#1a1a1a]">
        <div class="flex flex-wrap items-center gap-4">
            <span class="flex h-14 w-14 items-center justify-center rounded-full bg-[#c62828] text-xl font-semibold text-white">{{ mb_strtoupper(mb_substr(Auth::user()->name ?? 'G', 0, 1)) }}</span>
            <div class="min-w-0">
                <p class="truncate text-lg font-semibold">{{ Auth::user()->name }}</p>
                <p class="truncate text-sm text-gray-500 dark:text-gray-400">{{ Auth::user()->email }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Gestor desde {{ Auth::user()->created_at?->format('m/Y') }}</p>
            </div>
        </div>
    </section>
    <form method="POST" action="{{ route('manager.profile.update') }}" class="gw-form space-y-6">
        @csrf @method('PATCH')
        <section id="dados-pessoais" class="gw-card rounded-xl border border-gray-200 bg-white p-5 sm:p-7 dark:border-[#343843] dark:bg-[#1a1a1a]">
            <h2 class="mb-5 text-lg font-semibold"><span class="gw-section-number">01</span>Informações pessoais</h2>
            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                <x-manager-field name="name" label="Nome completo" :value="Auth::user()->name" maxlength="255" autocomplete="name" />
                <x-manager-field name="email" label="E-mail" type="email" :value="Auth::user()->email" maxlength="255" autocomplete="email" />
                <x-manager-field name="phone" label="Telefone" type="tel" :value="Auth::user()->phone" maxlength="20" minlength="10" :required="false" autocomplete="tel" placeholder="(11) 99999-9999" />
            </div>
        </section>
        <div class="gw-form-actions"><button type="submit" class="gw-button gw-button-primary rounded-lg bg-[#c62828] px-6 py-3 text-sm font-semibold text-white hover:bg-[#b71c1c]">Salvar alterações</button></div>
    </form>
</x-manager-legacy-layout>
