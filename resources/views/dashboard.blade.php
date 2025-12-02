<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-slate-800">
                {{ __('Water Monitoring System') }}
            </h2>

            <!-- INDICATOR LIVE CONNECTION -->
            <!-- Berubah warna/status berdasarkan data terakhir dari sensor -->
            <div class="flex items-center gap-2 px-3 py-1 text-sm transition-colors duration-500 border rounded-full bg-slate-100 border-slate-200" id="conn-container">
                <span class="relative flex w-3 h-3">
                  <span id="conn-ping" class="absolute inline-flex hidden w-full h-full rounded-full opacity-75 animate-ping bg-emerald-400"></span>
                  <span id="conn-dot" class="relative inline-flex w-3 h-3 transition-colors duration-500 rounded-full bg-slate-400"></span>
                </span>
                <span id="conn-text" class="font-medium transition-colors duration-500 text-slate-500">Connecting...</span>
            </div>
        </div>
    </x-slot>

    <!-- Load Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <div class="min-h-screen py-12 bg-slate-50">
        <div class="mx-auto space-y-6 max-w-7xl sm:px-6 lg:px-8">

            <!-- ROW 1: STATUS CARDS -->
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">

                <!-- Card 1: Water Level (DINAMIS UPDATE) -->
                <div class="relative p-6 overflow-hidden transition duration-300 bg-white border-b-4 shadow-sm sm:rounded-2xl border-cyan-500 hover:-translate-y-1 group">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs font-bold tracking-wider uppercase text-slate-400">Level Air</p>
                            <h3 class="flex items-end gap-1 mt-2 text-3xl font-bold text-slate-800">
                                <span id="val-level">0</span><span class="text-lg text-slate-500">%</span>
                            </h3>

                            <!-- INDIKATOR FLOW (MENGISI/BERKURANG) -->
                            <!-- Ini akan berubah warna & teks lewat Javascript -->
                            <div id="flow-indicator" class="mt-1 inline-flex items-center gap-1 text-xs font-bold px-2 py-0.5 rounded-full bg-slate-100 text-slate-500 transition-all duration-500">
                                <span id="flow-icon">▬</span>
                                <span id="flow-text">Stabil</span>
                            </div>

                        </div>
                        <div class="p-3 transition-colors duration-500 bg-cyan-50 rounded-xl text-cyan-600" id="level-icon-bg">
                            <!-- Icon Water Drop -->
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.384-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                            </svg>
                        </div>
                    </div>

                    <!-- Progress Bar -->
                    <div class="mt-4 w-full bg-slate-100 rounded-full h-2.5 overflow-hidden relative">
                        <div id="bar-level" class="bg-cyan-500 h-2.5 rounded-full transition-all duration-1000 relative overflow-hidden" style="width: 0%">
                             <!-- Efek Shimmer/Kilap (Muncul saat mengisi) -->
                             <div id="bar-shimmer" class="absolute top-0 left-0 w-full h-full transition-opacity duration-300 -translate-x-full opacity-0 bg-gradient-to-r from-transparent via-white/50 to-transparent"></div>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Status Tangki -->
                <div class="p-6 overflow-hidden transition duration-300 bg-white border-b-4 shadow-sm sm:rounded-2xl border-emerald-500 hover:-translate-y-1">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs font-bold tracking-wider uppercase text-slate-400">Status Tangki</p>
                            <h3 class="mt-2 text-2xl font-bold text-emerald-600" id="val-status">
                                Memuat...
                            </h3>
                        </div>
                        <div class="p-3 bg-emerald-50 rounded-xl text-emerald-600">
                            <!-- Icon Shield -->
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                    <p class="mt-4 text-xs text-slate-500">Kondisi operasional</p>
                </div>

                <!-- Card 3: Prediksi Waktu -->
                <div class="p-6 overflow-hidden transition duration-300 bg-white border-b-4 shadow-sm sm:rounded-2xl border-violet-500 hover:-translate-y-1">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs font-bold tracking-wider uppercase text-slate-400">Estimasi Waktu</p>
                            <h3 class="mt-2 text-2xl font-bold text-violet-600" id="val-time">
                                -- Jam
                            </h3>
                        </div>
                        <div class="p-3 bg-violet-50 rounded-xl text-violet-600">
                            <!-- Icon Clock -->
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                    <p class="mt-4 text-xs text-slate-500">Prediksi AI / Pompa</p>
                </div>

                <!-- Card 4: Kekeruhan -->
                <div class="p-6 overflow-hidden transition duration-300 bg-white border-b-4 shadow-sm sm:rounded-2xl border-amber-500 hover:-translate-y-1">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs font-bold tracking-wider uppercase text-slate-400">Kualitas Air</p>
                            <h3 class="mt-2 text-2xl font-bold text-amber-600" id="val-turbidity">
                                --
                            </h3>
                        </div>
                        <div class="p-3 bg-amber-50 rounded-xl text-amber-600">
                            <!-- Icon Eye -->
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </div>
                    </div>
                    <p class="mt-4 text-xs text-slate-500" id="val-turbidity-raw">Voltage: 0V</p>
                </div>
            </div>

            <!-- ROW 2: MAIN CHART (CENTERED) -->
            <div class="mt-6">
                <div class="max-w-4xl p-6 mx-auto bg-white border shadow-sm rounded-2xl border-slate-100">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-slate-700">Grafik Level & Prediksi</h3>
                        <span class="px-2 py-1 text-xs font-medium rounded bg-slate-100 text-slate-500">Realtime Update</span>
                    </div>
                    <div class="relative w-full h-72">
                        <canvas id="mainChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JAVASCRIPT LOGIC -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // ================= CONFIGURATION =================
            const CHART_COLORS = {
                level: 'rgba(6, 182, 212, 0.2)', // Cyan
                levelBorder: 'rgba(6, 182, 212, 1)',
                rate: 'rgba(139, 92, 246, 1)',   // Violet
            };

            // Simpan level sebelumnya untuk deteksi naik/turun di sisi frontend
            let lastLevel = null;

            // ================= INIT CHART =================
            const ctx = document.getElementById('mainChart').getContext('2d');
            const mainChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [
                        {
                            label: 'Level Air (%)',
                            data: [],
                            borderColor: CHART_COLORS.levelBorder,
                            backgroundColor: CHART_COLORS.level,
                            borderWidth: 2,
                            fill: true,
                            tension: 0.4,
                            pointRadius: 2
                        },
                        {
                            label: 'Laju Perubahan (%/jam)',
                            data: [],
                            borderColor: CHART_COLORS.rate,
                            borderWidth: 1,
                            borderDash: [5, 5],
                            fill: false,
                            tension: 0.4,
                            pointRadius: 0,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: { position: 'top', align: 'end' }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            title: { display: true, text: 'Level (%)' }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            grid: { drawOnChartArea: false },
                            title: { display: true, text: 'Rate (%/h)' }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });

            // ================= FETCH DATA FUNCTIONS =================

            // 1. Update Kartu-kartu Statistik
            async function fetchStats() {
                try {
                    const response = await fetch("{{ route('dashboard.stats') }}");
                    const data = await response.json();

                    // --- UPDATE KARTU LEVEL AIR ---
                    const currentLevel = parseFloat(data.water_level ?? 0);
                    document.getElementById('val-level').innerText = isFinite(currentLevel) ? currentLevel : 0;
                    const barLevel = document.getElementById('bar-level');
                    barLevel.style.width = (isFinite(currentLevel) ? currentLevel : 0) + '%';

                    // Logic Indikator Flow (Mengisi / Berkurang / Stabil)
                    const flowInd = document.getElementById('flow-indicator');
                    const flowIcon = document.getElementById('flow-icon');
                    const flowText = document.getElementById('flow-text');
                    const iconBg = document.getElementById('level-icon-bg');
                    const shimmer = document.getElementById('bar-shimmer');

                    // Reset Class sebelum di-set ulang
                    flowInd.className = "mt-1 inline-flex items-center gap-1 text-xs font-bold px-2 py-0.5 rounded-full transition-all duration-500";
                    shimmer.classList.remove('animate-shimmer');
                    shimmer.classList.add('opacity-0');

                    // Tentukan arah flow berdasarkan perubahan level sebelumnya
                    let direction = 'STABIL';
                    const threshold = 0.3; // threshold perubahan % agar tidak terlalu sensitif

                    if (lastLevel !== null) {
                        const diff = currentLevel - lastLevel;
                        if (diff > threshold) {
                            direction = 'MENGISI';
                        } else if (diff < -threshold) {
                            direction = 'BERKURANG';
                        }
                    }
                    lastLevel = currentLevel;

                    // Ambil nilai flow_rate dari API jika ada, fallback ke diff
                    const rawRate = parseFloat(data.flow_rate ?? 0);
                    const safeRate = isFinite(rawRate) ? rawRate : 0;

                    if (direction === 'MENGISI') {
                        // Gaya Hijau (Emerald)
                        flowInd.classList.add('bg-emerald-100', 'text-emerald-600');
                        flowIcon.innerHTML = "▲"; // Panah Atas
                        flowText.innerText = "Mengisi (" + safeRate.toFixed(1) + "%)";
                        iconBg.className = "p-3 bg-emerald-50 rounded-xl text-emerald-600 transition-colors duration-500";
                        barLevel.className = "bg-emerald-500 h-2.5 rounded-full transition-all duration-1000 relative overflow-hidden";

                        // Efek Shimmer saat mengisi (Penting)
                        shimmer.classList.remove('opacity-0');
                        shimmer.classList.add('animate-shimmer', 'opacity-50');

                    } else if (direction === 'BERKURANG') {
                        // Gaya Merah/Orange (Rose)
                        flowInd.classList.add('bg-rose-100', 'text-rose-600');
                        flowIcon.innerHTML = "▼"; // Panah Bawah
                        flowText.innerText = "Berkurang (" + Math.abs(safeRate).toFixed(1) + "%)";
                        iconBg.className = "p-3 bg-rose-50 rounded-xl text-rose-600 transition-colors duration-500";
                        barLevel.className = "bg-rose-500 h-2.5 rounded-full transition-all duration-1000 relative";

                    } else {
                        // Gaya Stabil (Slate/Cyan default)
                        flowInd.classList.add('bg-slate-100', 'text-slate-500');
                        flowIcon.innerHTML = "▬"; // Strip
                        flowText.innerText = "Stabil";
                        iconBg.className = "p-3 bg-cyan-50 rounded-xl text-cyan-600 transition-colors duration-500";
                        barLevel.className = "bg-cyan-500 h-2.5 rounded-full transition-all duration-1000 relative";
                    }


                    // --- UPDATE KARTU LAIN ---
                    document.getElementById('val-status').innerText = data.status_air;
                    const statusEl = document.getElementById('val-status');
                    if(data.status_air === 'KRITIS') statusEl.className = "text-2xl font-bold text-rose-600 mt-2";
                    else if(data.status_air === 'Waspada') statusEl.className = "text-2xl font-bold text-amber-500 mt-2";
                    else statusEl.className = "text-2xl font-bold text-emerald-600 mt-2";

                    document.getElementById('val-time').innerText = data.waktu_habis;
                    document.getElementById('val-turbidity').innerText = data.status_keruh;
                    document.getElementById('val-turbidity-raw').innerText = 'Raw Turbidity: ' + data.turbidity;

                    // --- UPDATE INDIKATOR KONEKSI ---
                    const pingEl = document.getElementById('conn-ping');
                    const dotEl = document.getElementById('conn-dot');
                    const textEl = document.getElementById('conn-text');
                    const containerEl = document.getElementById('conn-container');

                    const secondsAgo = data.last_seen_seconds;

                    if (secondsAgo < 60) {
                        // ONLINE
                        pingEl.classList.remove('hidden');
                        dotEl.className = "relative inline-flex rounded-full h-3 w-3 bg-emerald-500 transition-colors duration-500";
                        textEl.innerText = "Live Connection";
                        textEl.className = "font-medium text-emerald-600 transition-colors duration-500";
                        containerEl.className = "text-sm flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 border border-emerald-100 transition-colors duration-500";
                    } else {
                        // OFFLINE
                        pingEl.classList.add('hidden');
                        dotEl.className = "relative inline-flex rounded-full h-3 w-3 bg-slate-400 transition-colors duration-500";
                        textEl.innerText = "Offline (" + data.updated_at + ")";
                        textEl.className = "font-medium text-slate-500 transition-colors duration-500";
                        containerEl.className = "text-sm flex items-center gap-2 px-3 py-1 rounded-full bg-slate-100 border border-slate-200 transition-colors duration-500";
                    }

                } catch (error) {
                    console.error('Error fetching stats:', error);
                }
            }

            // 2. Update Grafik
            async function fetchChart() {
                try {
                    const response = await fetch("{{ route('dashboard.chart') }}");
                    const data = await response.json();

                    mainChart.data.labels = data.labels;
                    mainChart.data.datasets[0].data = data.levels;
                    mainChart.data.datasets[1].data = data.rates;
                    mainChart.update();
                } catch (error) {
                    console.error('Error fetching chart:', error);
                }
            }

            // ================= RUN LOOPS =================
            fetchStats();
            fetchChart();

            setInterval(fetchStats, 2000);
            setInterval(fetchChart, 5000);
        });
    </script>

    <!-- Custom Style untuk Animasi Shimmer -->
    <style>
        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        .animate-shimmer {
            animation: shimmer 1.5s infinite;
        }
    </style>
</x-app-layout>
