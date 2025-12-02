<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-slate-800">
            {{ __('Pola Penggunaan Air') }}
        </h2>
    </x-slot>

    <div class="min-h-screen py-12 bg-slate-50">
        <div class="mx-auto space-y-6 max-w-7xl sm:px-6 lg:px-8">

            <!-- Ringkasan Model Random Forest -->
            <div class="overflow-hidden bg-white border shadow-sm sm:rounded-2xl border-slate-200">
                <div class="flex items-center justify-between p-6 border-b border-slate-200">
                    <div>
                        <h3 class="text-lg font-bold text-slate-700">Model Pola Penggunaan (Random Forest)</h3>
                        <p class="text-sm text-slate-500">
                            Model ini mempelajari pola <span class="font-semibold">depletion_rate</span> berdasarkan
                            jam, menit, level air, jarak sensor, dan kekeruhan.
                        </p>
                    </div>
                </div>

                <div class="grid gap-6 p-6 md:grid-cols-4">
                    <div class="p-4 bg-slate-50 rounded-xl">
                        <p class="text-xs font-semibold tracking-wide uppercase text-slate-400">RMSE</p>
                        <p class="mt-2 text-2xl font-bold text-rose-500">
                            {{ optional($evaluation)->rmse ? number_format($evaluation->rmse, 4) : '0.0000' }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500">Root Mean Squared Error</p>
                    </div>

                    <div class="p-4 bg-slate-50 rounded-xl">
                        <p class="text-xs font-semibold tracking-wide uppercase text-slate-400">R² Score</p>
                        <p class="mt-2 text-2xl font-bold text-emerald-500">
                            {{ optional($evaluation)->r2_score ? number_format($evaluation->r2_score, 4) : '0.0000' }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500">Kualitas kecocokan model</p>
                    </div>

                    <div class="p-4 bg-slate-50 rounded-xl">
                        <p class="text-xs font-semibold tracking-wide uppercase text-slate-400">MAE</p>
                        <p class="mt-2 text-2xl font-bold text-blue-500">
                            {{ optional($evaluation)->mae ? number_format($evaluation->mae, 4) : '0.0000' }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500">Mean Absolute Error</p>
                    </div>

                    <div class="p-4 bg-slate-50 rounded-xl">
                        <p class="text-xs font-semibold tracking-wide uppercase text-slate-400">Training</p>
                        <p class="mt-2 text-lg font-semibold text-slate-700">
                            {{ optional($evaluation)->training_time ? number_format($evaluation->training_time, 2).' s' : '-' }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500">
                            Sampel:
                            <span class="font-semibold">
                                {{ optional($evaluation)->training_samples ?? '-' }}
                            </span>
                        </p>
                        <p class="mt-1 text-xs text-slate-400">
                            Terakhir: {{ optional($evaluation?->created_at)->format('d M Y H:i') ?? '-' }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Insight Pola Waktu Penggunaan -->
            <div class="overflow-hidden bg-white border shadow-sm sm:rounded-2xl border-slate-200">
                <div class="p-6 border-b border-slate-200">
                    <h3 class="text-lg font-bold text-slate-700">Ringkasan Pola Waktu Penggunaan</h3>
                    <p class="mt-2 text-sm text-slate-500">
                        {{ $usageInsights['summary'] ?? 'Belum ada cukup data untuk menganalisis pola waktu penggunaan air.' }}
                    </p>

                    @if(!empty($usageInsights['top_hours']))
                        <div class="grid gap-3 mt-4 md:grid-cols-3">
                            @foreach($usageInsights['top_hours'] as $row)
                                @php
                                    $hour = (int) $row->hour;
                                    $start = str_pad($hour, 2, '0', STR_PAD_LEFT) . ':00';
                                    $end = str_pad(($hour + 1) % 24, 2, '0', STR_PAD_LEFT) . ':00';
                                @endphp
                                <div class="p-3 bg-slate-50 rounded-xl">
                                    <p class="text-xs font-semibold tracking-wide uppercase text-slate-400">Rentang Waktu</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-800">
                                        {{ $start }} - {{ $end }}
                                    </p>
                                    <p class="mt-1 text-xs text-slate-500">
                                        Rata-rata penurunan ≈
                                        <span class="font-mono">
                                            {{ number_format($row->avg_usage, 1) }}%/jam
                                        </span>
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <!-- Tabel Pola Penggunaan (Sampel Data Latih RF) -->
            <div class="overflow-hidden bg-white border shadow-sm sm:rounded-2xl border-slate-200">
                <div class="flex items-center justify-between p-6 border-b border-slate-200">
                    <div>
                        <h3 class="text-lg font-bold text-slate-700">Sampel Pola Penggunaan Air</h3>
                        <p class="text-sm text-slate-500">
                            Diambil dari <span class="font-semibold">100 riwayat terbaru</span> yang digunakan Random Forest
                            untuk belajar pola penurunan/pengisian air.
                        </p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left text-slate-500">
                        <thead class="text-xs uppercase bg-slate-100 text-slate-700">
                            <tr>
                                <th class="px-4 py-3 font-semibold">Waktu</th>
                                <th class="px-4 py-3 font-semibold text-center">Jam</th>
                                <th class="px-4 py-3 font-semibold text-center">Level (%)</th>
                                <th class="px-4 py-3 font-semibold text-center">Jarak (cm)</th>
                                <th class="px-4 py-3 font-semibold text-center">Kekeruhan (V)</th>
                                <th class="px-4 py-3 font-semibold text-center">Depletion Rate (%/jam)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($samples as $row)
                                <tr class="transition bg-white hover:bg-cyan-50">
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="text-sm font-medium text-slate-900">
                                            {{ $row->created_at->format('d M Y') }}
                                        </div>
                                        <div class="text-xs text-slate-400">
                                            {{ $row->created_at->format('H:i:s') }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="font-mono text-slate-700">
                                            {{ str_pad($row->hour, 2, '0', STR_PAD_LEFT) }}:{{ str_pad($row->minute, 2, '0', STR_PAD_LEFT) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="font-semibold text-cyan-700">
                                            {{ number_format($row->water_level, 1) }}%
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        {{ number_format($row->distance, 1) }} cm
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        {{ number_format($row->turbidity, 2) }} V
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        @php
                                            $rate = $row->depletion_rate;
                                        @endphp
                                        @if($rate > 0.5)
                                            <span class="font-mono font-semibold text-rose-600">
                                                ↓ {{ number_format($rate, 2) }}%/h
                                            </span>
                                        @elseif($rate < -0.5)
                                            <span class="font-mono font-semibold text-emerald-600">
                                                ↑ {{ number_format(abs($rate), 2) }}%/h
                                            </span>
                                        @else
                                            <span class="font-mono text-slate-400">
                                                ~ {{ number_format($rate, 2) }}%/h
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-sm text-center text-slate-400">
                                        Belum ada data yang cukup untuk membentuk pola penggunaan.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>


