<x-user-layout>
    <x-slot name="header">
        <h1 class="text-3xl font-semibold text-gray-900 dark:text-white">
            {{ __('manager.dashboard') }}
        </h1>
        <p class="mt-2 text-sm text-gray-500 dark:text-[#a0a0a0]">
            {{ $establishment->name }} &bull; {{ $establishment->address }}
        </p>
    </x-slot>

    <section class="mt-7 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <div class="relative min-h-[130px] overflow-hidden rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-[#343843] dark:bg-[#1a1a1a]">
            <div class="flex items-center gap-4">
                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-[#e53935]/10 text-[#e53935]">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-[#a0a0a0]">{{ __('manager.total_sessions') }}</p>
                    <p class="mt-1 text-3xl font-semibold text-gray-900 dark:text-white">{{ $totalSessions }}</p>
                </div>
            </div>
        </div>

        <div class="relative min-h-[130px] overflow-hidden rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-[#343843] dark:bg-[#1a1a1a]">
            <div class="flex items-center gap-4">
                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-emerald-500/20 text-emerald-400">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-[#a0a0a0]">{{ __('manager.total_revenue') }}</p>
                    <p class="mt-1 text-3xl font-semibold text-gray-900 dark:text-white">R$ {{ number_format($totalRevenue, 2, ',', '.') }}</p>
                </div>
            </div>
        </div>

        <div class="relative min-h-[130px] overflow-hidden rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-[#343843] dark:bg-[#1a1a1a]">
            <div class="flex items-center gap-4">
                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-amber-500/20 text-amber-400">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-[#a0a0a0]">{{ __('manager.total_energy') }}</p>
                    <p class="mt-1 text-3xl font-semibold text-gray-900 dark:text-white">{{ number_format($totalEnergy, 1, ',', '.') }} kWh</p>
                </div>
            </div>
        </div>

        <div class="relative min-h-[130px] overflow-hidden rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-[#343843] dark:bg-[#1a1a1a]">
            <div class="flex items-center gap-4">
                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-violet-500/20 text-violet-400">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 15.75V18m-7.5-6.75h.008v.008H8.25v-.008zm0 2.25h.008v.008H8.25V13.5zm0 2.25h.008v.008H8.25v-.008zm0 2.25h.008v.008H8.25V18zm2.498-6.75h.007v.008h-.007v-.008zm0 2.25h.007v.008h-.007V13.5zm0 2.25h.007v.008h-.007v-.008zm0 2.25h.007v.008h-.007V18zm2.504-6.75h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008V13.5zm0 2.25h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008V18zm2.498-6.75h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008V13.5zM8.25 6h7.5v2.25h-7.5V6zM12 2.25c-1.892 0-3.758.11-5.593.322C5.307 2.7 4.5 3.65 4.5 4.757V19.5a2.25 2.25 0 002.25 2.25h10.5a2.25 2.25 0 002.25-2.25V4.757c0-1.108-.806-2.057-1.907-2.185A48.507 48.507 0 0012 2.25z" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-[#a0a0a0]">{{ __('manager.avg_cost') }}</p>
                    <p class="mt-1 text-3xl font-semibold text-gray-900 dark:text-white">R$ {{ number_format($avgCostPerKwh, 2, ',', '.') }}/kWh</p>
                </div>
            </div>
        </div>
    </section>

    <section class="mt-5 grid grid-cols-1 gap-5 xl:grid-cols-2">
        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-[#343843] dark:bg-[#1a1a1a]">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                {{ __('manager.energy_usage') }}
            </h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-[#a0a0a0]">{{ __('manager.last_30_days') }}</p>
            <div class="mt-4" style="height: 280px;">
                <canvas id="energyChart"></canvas>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-[#343843] dark:bg-[#1a1a1a]">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                {{ __('manager.revenue_chart') }}
            </h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-[#a0a0a0]">{{ __('manager.last_30_days') }}</p>
            <div class="mt-4" style="height: 280px;">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>
    </section>

    <section class="mt-5 grid grid-cols-1 gap-5 xl:grid-cols-2">
        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-[#343843] dark:bg-[#1a1a1a]">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                {{ __('manager.charger_distribution') }}
            </h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-[#a0a0a0]">{{ __('manager.sessions_by_charger') }}</p>
            <div class="mt-4 flex items-center justify-center" style="height: 280px;">
                <canvas id="chargerChart"></canvas>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-[#343843] dark:bg-[#1a1a1a]">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                {{ __('manager.status_distribution') }}
            </h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-[#a0a0a0]">{{ __('manager.sessions_by_status') }}</p>
            <div class="mt-4 flex items-center justify-center" style="height: 280px;">
                <canvas id="statusChart"></canvas>
            </div>
        </div>
    </section>

    <section class="mt-5 rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-[#343843] dark:bg-[#1a1a1a]">
        <div class="flex items-center justify-between gap-4">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                {{ __('manager.establishment_info') }}
            </h2>
        </div>

        <div class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <p class="text-sm text-gray-500 dark:text-[#a0a0a0]">{{ __('manager.info_name') }}</p>
                <p class="mt-1 font-medium text-gray-900 dark:text-white">{{ $establishment->name }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-500 dark:text-[#a0a0a0]">{{ __('manager.info_address') }}</p>
                <p class="mt-1 font-medium text-gray-900 dark:text-white">{{ $establishment->address }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-500 dark:text-[#a0a0a0]">{{ __('manager.info_type') }}</p>
                <p class="mt-1 font-medium text-gray-900 dark:text-white">{{ ucfirst($establishment->type) }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-500 dark:text-[#a0a0a0]">{{ __('manager.info_hours') }}</p>
                <p class="mt-1 font-medium text-gray-900 dark:text-white">{{ $establishment->operating_hours }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-500 dark:text-[#a0a0a0]">{{ __('manager.info_phone') }}</p>
                <p class="mt-1 font-medium text-gray-900 dark:text-white">{{ $establishment->phone ?? '-' }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-500 dark:text-[#a0a0a0]">{{ __('manager.info_cost') }}</p>
                <p class="mt-1 font-medium text-gray-900 dark:text-white">R$ {{ number_format($establishment->cost_per_kwh, 4, ',', '.') }}/kWh</p>
            </div>
        </div>
    </section>

    <section class="mt-5 rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-[#343843] dark:bg-[#1a1a1a]">
        <div class="flex items-center justify-between gap-4">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                {{ __('manager.recent_sessions') }}
            </h2>
        </div>

        <div class="mt-5 overflow-x-auto">
            <table class="w-full min-w-[700px] text-left text-sm">
                <thead class="border-b border-gray-200 text-gray-500 dark:border-[#343843] dark:text-[#a0a0a0]">
                    <tr>
                        <th class="py-3 font-medium">{{ __('manager.table_date') }}</th>
                        <th class="py-3 font-medium">{{ __('manager.table_user') }}</th>
                        <th class="py-3 font-medium">{{ __('manager.table_charger') }}</th>
                        <th class="py-3 font-medium">{{ __('manager.table_energy') }}</th>
                        <th class="py-3 font-medium">{{ __('manager.table_value') }}</th>
                        <th class="py-3 font-medium">{{ __('manager.table_status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentSessions as $session)
                        <tr class="border-b border-gray-200 dark:border-[#343843]">
                            <td class="py-4 text-gray-900 dark:text-white">{{ $session->created_at->format('d/m/Y H:i') }}</td>
                            <td class="py-4 text-gray-900 dark:text-white">{{ $session->user->name ?? '-' }}</td>
                            <td class="py-4 text-gray-900 dark:text-white">{{ $session->charger_name }}</td>
                            <td class="py-4 text-gray-900 dark:text-white">{{ number_format($session->energy_kwh, 1, ',', '.') }} kWh</td>
                            <td class="py-4 text-gray-900 dark:text-white">R$ {{ number_format($session->amount_charged, 2, ',', '.') }}</td>
                            <td class="py-4">
                                @if($session->status === 'completed')
                                    <span class="rounded-lg bg-emerald-500/10 px-3 py-1 text-emerald-500">{{ __('manager.status_completed') }}</span>
                                @elseif($session->status === 'pending')
                                    <span class="rounded-lg bg-amber-500/10 px-3 py-1 text-amber-400">{{ __('manager.status_pending') }}</span>
                                @else
                                    <span class="rounded-lg bg-red-500/10 px-3 py-1 text-red-400">{{ __('manager.status_cancelled') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-gray-500 dark:text-[#a0a0a0]">
                                {{ __('manager.no_sessions') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const isDark = document.documentElement.classList.contains('dark');
            const gridColor = isDark ? '#343843' : '#e3e3e0';
            const textColor = isDark ? '#a0a0a0' : '#706f6c';

        const labels = @json($dailyLabels);
        const kwhData = @json($dailyKwh);
        const revenueData = @json($dailyRevenue);
        const chargerData = @json($chargerDistribution);
        const statusData = @json($statusDistribution);

        new Chart(document.getElementById('energyChart'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'kWh',
                    data: kwhData,
                    borderColor: '#e53935',
                    backgroundColor: 'rgba(229, 57, 53, 0.1)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 3,
                    pointBackgroundColor: '#e53935',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { color: gridColor }, ticks: { color: textColor, maxTicksLimit: 10 } },
                    y: { grid: { color: gridColor }, ticks: { color: textColor }, beginAtZero: true }
                }
            }
        });

        new Chart(document.getElementById('revenueChart'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'R$',
                    data: revenueData,
                    backgroundColor: 'rgba(229, 57, 53, 0.7)',
                    borderColor: '#e53935',
                    borderWidth: 1,
                    borderRadius: 4,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { color: gridColor }, ticks: { color: textColor, maxTicksLimit: 10 } },
                    y: { grid: { color: gridColor }, ticks: { color: textColor }, beginAtZero: true }
                }
            }
        });

        const chargerColors = ['#e53935', '#8b5cf6', '#f59e0b', '#10b981', '#3b82f6', '#ec4899'];

        new Chart(document.getElementById('chargerChart'), {
            type: 'pie',
            data: {
                labels: chargerData.map(c => c.charger_name),
                datasets: [{
                    data: chargerData.map(c => c.total),
                    backgroundColor: chargerColors.slice(0, chargerData.length),
                    borderWidth: 0,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { color: textColor, padding: 16 } }
                }
            }
        });

        const statusColors = { completed: '#10b981', pending: '#f59e0b', cancelled: '#ef4444' };
        const statusLabels = { completed: 'Concluida', pending: 'Pendente', cancelled: 'Cancelada' };

        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: statusData.map(s => statusLabels[s.status] || s.status),
                datasets: [{
                    data: statusData.map(s => s.total),
                    backgroundColor: statusData.map(s => statusColors[s.status] || '#6b7280'),
                    borderWidth: 0,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { color: textColor, padding: 16 } }
                }
            }
});
    });
</script>
</x-user-layout>
