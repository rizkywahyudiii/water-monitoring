<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-slate-800">
            {{ __('Pola Penggunaan Air') }}
        </h2>
    </x-slot>

    <div class="min-h-screen py-12 bg-slate-50">
        <div class="mx-auto space-y-6 max-w-7xl sm:px-6 lg:px-8">

            <!-- PERBANDINGAN KEDUA MODEL: Random Forest vs Linear Regression -->
            <div class="overflow-hidden bg-white border shadow-sm sm:rounded-2xl border-slate-200">
                <div class="p-6 border-b border-slate-200">
                    <h3 class="text-lg font-bold text-slate-700">Perbandingan Model Machine Learning</h3>
                    <p class="mt-1 text-sm text-slate-500">
                        Kedua model memprediksi <span class="font-semibold">water_level berikutnya</span> berdasarkan
                        jam, menit, level air saat ini, jarak sensor, dan kekeruhan.
                    </p>
                </div>

                <div class="grid gap-6 p-6 md:grid-cols-2">
                    <!-- Random Forest Model -->
                    <div class="p-6 border-2 rounded-xl border-violet-200 bg-violet-50/30">
                        <div class="flex items-center gap-2 mb-4">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                            </svg>
                            <h4 class="text-lg font-bold text-violet-700">Random Forest</h4>
                            <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-violet-100 text-violet-700">Ensemble</span>
                        </div>
                        <p class="mb-4 text-xs text-slate-600">
                            Model ensemble dengan 100 pohon keputusan (n_estimators=100, max_depth=10).
                            Cocok untuk menangkap pola non-linear yang kompleks.
                        </p>

                        @if($rfEvaluation)
                            <div class="grid gap-3">
                                <div class="flex items-center justify-between p-3 bg-white rounded-lg">
                                    <span class="text-sm font-medium text-slate-600">R² Score</span>
                                    <span class="text-lg font-bold text-emerald-600">{{ number_format($rfEvaluation->r2_score, 4) }}</span>
                                </div>
                                <div class="flex items-center justify-between p-3 bg-white rounded-lg">
                                    <span class="text-sm font-medium text-slate-600">RMSE</span>
                                    <span class="text-lg font-bold text-rose-500">{{ number_format($rfEvaluation->rmse, 4) }}</span>
                                </div>
                                <div class="flex items-center justify-between p-3 bg-white rounded-lg">
                                    <span class="text-sm font-medium text-slate-600">MAE</span>
                                    <span class="text-lg font-bold text-blue-500">{{ number_format($rfEvaluation->mae, 4) }}</span>
                                </div>
                                <div class="flex items-center justify-between p-3 bg-white rounded-lg">
                                    <span class="text-sm font-medium text-slate-600">Training Time</span>
                                    <span class="text-sm font-semibold text-slate-700">{{ number_format($rfEvaluation->training_time, 2) }}s</span>
                                </div>
                                <div class="pt-2 mt-2 text-xs text-slate-400 border-t border-slate-200">
                                    Sampel: {{ $rfEvaluation->training_samples }} | 
                                    Terakhir: {{ $rfEvaluation->created_at->format('d M Y H:i') }}
                                </div>
                            </div>
                        @else
                            <div class="p-4 text-sm text-center text-slate-400 bg-white rounded-lg">
                                Model Random Forest belum dilatih.
                            </div>
                        @endif
                    </div>

                    <!-- Linear Regression Model -->
                    <div class="p-6 border-2 rounded-xl border-blue-200 bg-blue-50/30">
                        <div class="flex items-center gap-2 mb-4">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                            <h4 class="text-lg font-bold text-blue-700">Linear Regression</h4>
                            <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-blue-100 text-blue-700">Linear</span>
                        </div>
                        <p class="mb-4 text-xs text-slate-600">
                            Model regresi linear sederhana. Cepat dan interpretable, cocok untuk pola linear.
                        </p>

                        @if($lrEvaluation)
                            <div class="grid gap-3">
                                <div class="flex items-center justify-between p-3 bg-white rounded-lg">
                                    <span class="text-sm font-medium text-slate-600">R² Score</span>
                                    <span class="text-lg font-bold text-emerald-600">{{ number_format($lrEvaluation->r2_score, 4) }}</span>
                                </div>
                                <div class="flex items-center justify-between p-3 bg-white rounded-lg">
                                    <span class="text-sm font-medium text-slate-600">RMSE</span>
                                    <span class="text-lg font-bold text-rose-500">{{ number_format($lrEvaluation->rmse, 4) }}</span>
                                </div>
                                <div class="flex items-center justify-between p-3 bg-white rounded-lg">
                                    <span class="text-sm font-medium text-slate-600">MAE</span>
                                    <span class="text-lg font-bold text-blue-500">{{ number_format($lrEvaluation->mae, 4) }}</span>
                                </div>
                                <div class="flex items-center justify-between p-3 bg-white rounded-lg">
                                    <span class="text-sm font-medium text-slate-600">Training Time</span>
                                    <span class="text-sm font-semibold text-slate-700">{{ number_format($lrEvaluation->training_time, 2) }}s</span>
                                </div>
                                <div class="pt-2 mt-2 text-xs text-slate-400 border-t border-slate-200">
                                    Sampel: {{ $lrEvaluation->training_samples }} | 
                                    Terakhir: {{ $lrEvaluation->created_at->format('d M Y H:i') }}
                                </div>
                            </div>
                        @else
                            <div class="p-4 text-sm text-center text-slate-400 bg-white rounded-lg">
                                Model Linear Regression belum dilatih.
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Kesimpulan Perbandingan -->
                @if($rfEvaluation && $lrEvaluation)
                    <div class="p-4 mx-6 mb-6 bg-slate-50 rounded-xl border border-slate-200">
                        <p class="text-sm font-semibold text-slate-700 mb-2">📊 Analisis Perbandingan:</p>
                        <ul class="space-y-1 text-xs text-slate-600">
                            <li>
                                • <span class="font-semibold">R² Score:</span>
                                @if($rfEvaluation->r2_score > $lrEvaluation->r2_score)
                                    Random Forest lebih baik ({{ number_format($rfEvaluation->r2_score, 4) }} vs {{ number_format($lrEvaluation->r2_score, 4) }})
                                @elseif($lrEvaluation->r2_score > $rfEvaluation->r2_score)
                                    Linear Regression lebih baik ({{ number_format($lrEvaluation->r2_score, 4) }} vs {{ number_format($rfEvaluation->r2_score, 4) }})
                                @else
                                    Kedua model memiliki R² Score yang sama
                                @endif
                            </li>
                            <li>
                                • <span class="font-semibold">RMSE:</span>
                                @if($rfEvaluation->rmse < $lrEvaluation->rmse)
                                    Random Forest lebih akurat ({{ number_format($rfEvaluation->rmse, 4) }} vs {{ number_format($lrEvaluation->rmse, 4) }})
                                @elseif($lrEvaluation->rmse < $rfEvaluation->rmse)
                                    Linear Regression lebih akurat ({{ number_format($lrEvaluation->rmse, 4) }} vs {{ number_format($rfEvaluation->rmse, 4) }})
                                @else
                                    Kedua model memiliki RMSE yang sama
                                @endif
                            </li>
                            <li>
                                • <span class="font-semibold">Kecepatan Training:</span>
                                @if($rfEvaluation->training_time < $lrEvaluation->training_time)
                                    Random Forest lebih cepat ({{ number_format($rfEvaluation->training_time, 2) }}s vs {{ number_format($lrEvaluation->training_time, 2) }}s)
                                @else
                                    Linear Regression lebih cepat ({{ number_format($lrEvaluation->training_time, 2) }}s vs {{ number_format($rfEvaluation->training_time, 2) }}s)
                                @endif
                            </li>
                        </ul>
                    </div>
                @endif
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


