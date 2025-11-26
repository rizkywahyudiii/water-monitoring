<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">
                {{ __('Water Monitoring System') }}
            </h2>

            <!-- INDICATOR LIVE CONNECTION -->
            <div class="text-sm flex items-center gap-2 px-3 py-1 rounded-full bg-slate-100 border border-slate-200 transition-colors duration-500" id="conn-container">
                <span class="relative flex h-3 w-3">
                  <!-- Ping Animation (Blinking) -->
                  <span id="conn-ping" class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                  <!-- Static Dot -->
                  <span id="conn-dot" class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500 transition-colors duration-500"></span>
                </span>
                <span id="conn-text" class="font-medium text-slate-600 transition-colors duration-500">Connecting...</span>
            </div>
        </div>
    </x-slot>

    <!-- Load Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <div class="py-12 bg-slate-50 min-h-screen">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- ROW 1: STATUS CARDS -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">

                <!-- Card 1: Water Level -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-2xl border-b-4 border-cyan-500 p-6 transition hover:-translate-y-1 duration-300">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Level Air</p>
                            <h3 class="text-3xl font-bold text-slate-800 mt-2">
                                <span id="val-level">0</span><span class="text-lg text-slate-500">%</span>
                            </h3>
                        </div>
                        <div class="p-3 bg-cyan-50 rounded-xl text-cyan-600">
                            <!-- Icon Water -->
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.384-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4 w-full bg-slate-100 rounded-full h-2.5">
                        <div id="bar-level" class="bg-cyan-500 h-2.5 rounded-full transition-all duration-1000" style="width: 0%"></div>
                    </div>
                </div>

                <!-- Card 2: Status -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-2xl border-b-4 border-emerald-500 p-6 transition hover:-translate-y-1 duration-300">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Status Tangki</p>
                            <h3 class="text-2xl font-bold text-emerald-600 mt-2" id="val-status">
                                Memuat...
                            </h3>
                        </div>
                        <div class="p-3 bg-emerald-50 rounded-xl text-emerald-600">
                            <!-- Icon Shield -->
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                    <p class="text-xs text-slate-500 mt-4">Kondisi saat ini</p>
                </div>

                <!-- Card 3: Prediksi Waktu -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-2xl border-b-4 border-violet-500 p-6 transition hover:-translate-y-1 duration-300">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Estimasi Habis</p>
                            <h3 class="text-2xl font-bold text-violet-600 mt-2" id="val-time">
                                -- Jam
                            </h3>
                        </div>
                        <div class="p-3 bg-violet-50 rounded-xl text-violet-600">
                            <!-- Icon Clock -->
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                    <p class="text-xs text-slate-500 mt-4">Prediksi AI (LightGBM)</p>
                </div>

                <!-- Card 4: Kekeruhan -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-2xl border-b-4 border-amber-500 p-6 transition hover:-translate-y-1 duration-300">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Kualitas Air</p>
                            <h3 class="text-2xl font-bold text-amber-600 mt-2" id="val-turbidity">
                                --
                            </h3>
                        </div>
                        <div class="p-3 bg-amber-50 rounded-xl text-amber-600">
                            <!-- Icon Eye -->
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </div>
                    </div>
                    <p class="text-xs text-slate-500 mt-4" id="val-turbidity-raw">Voltage: 0V</p>
                </div>
            </div>

            <!-- ROW 2: CHARTS -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Main Chart -->
                <div class="bg-white p-6 rounded-2xl shadow-sm lg:col-span-2">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-bold text-slate-700">Grafik Level & Prediksi</h3>
                        <span class="text-xs px-2 py-1 bg-slate-100 rounded text-slate-500">Realtime</span>
                    </div>
                    <div class="relative h-72 w-full">
                        <canvas id="mainChart"></canvas>
                    </div>
                </div>

                <!-- AI Performance / ML Stats -->
                <div class="bg-white p-6 rounded-2xl shadow-sm flex flex-col justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-slate-700 mb-1">Performa AI Model</h3>
                        <p class="text-sm text-slate-500 mb-6">Metrik evaluasi LightGBM terbaru.</p>

                        <div class="space-y-4">
                            <!-- Metric 1 -->
                            <div class="flex justify-between items-center border-b border-slate-100 pb-2">
                                <span class="text-slate-500 text-sm">RMSE (Error Rate)</span>
                                <span class="font-mono font-bold text-rose-500" id="ml-rmse">0.000</span>
                            </div>
                            <!-- Metric 2 -->
                            <div class="flex justify-between items-center border-b border-slate-100 pb-2">
                                <span class="text-slate-500 text-sm">R² Score (Akurasi)</span>
                                <span class="font-mono font-bold text-emerald-500" id="ml-r2">0.000</span>
                            </div>
                            <!-- Metric 3 -->
                            <div class="flex justify-between items-center border-b border-slate-100 pb-2">
                                <span class="text-slate-500 text-sm">Training Time</span>
                                <span class="font-mono font-bold text-blue-500" id="ml-time">0s</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <p class="text-xs text-slate-400 mb-1">Terakhir dilatih:</p>
                        <p class="text-sm font-semibold text-slate-700" id="ml-last-update">-</p>
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
                            tension: 0.4, // Buat garis melengkung halus
                            pointRadius: 2
                        },
                        {
                            label: 'Laju Pengurangan (%/jam)',
                            data: [],
                            borderColor: CHART_COLORS.rate,
                            borderWidth: 1,
                            borderDash: [5, 5], // Garis putus-putus
                            fill: false,
                            tension: 0.4,
                            pointRadius: 0,
                            yAxisID: 'y1' // Sumbu Y kedua di kanan
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

                    // Update DOM Elements
                    document.getElementById('val-level').innerText = data.water_level;
                    document.getElementById('bar-level').style.width = data.water_level + '%';

                    document.getElementById('val-status').innerText = data.status_air;
                    // Ubah warna status dinamis
                    const statusEl = document.getElementById('val-status');
                    if(data.status_air === 'KRITIS') statusEl.className = "text-2xl font-bold text-rose-600 mt-2";
                    else if(data.status_air === 'Waspada') statusEl.className = "text-2xl font-bold text-amber-500 mt-2";
                    else statusEl.className = "text-2xl font-bold text-emerald-600 mt-2";

                    document.getElementById('val-time').innerText = data.waktu_habis;
                    document.getElementById('val-turbidity').innerText = data.status_keruh;
                    document.getElementById('val-turbidity-raw').innerText = 'Raw Turbidity: ' + data.turbidity;

                    // --- LOGIKA INDIKATOR KONEKSI BARU ---
                    const pingEl = document.getElementById('conn-ping');
                    const dotEl = document.getElementById('conn-dot');
                    const textEl = document.getElementById('conn-text');
                    const containerEl = document.getElementById('conn-container');

                    // Ambang batas: Jika data terakhir > 60 detik yang lalu, anggap Offline
                    const secondsAgo = data.last_seen_seconds;

                    if (secondsAgo < 60) {
                        // LIVE / ONLINE
                        pingEl.classList.remove('hidden'); // Kedip-kedip nyala
                        dotEl.className = "relative inline-flex rounded-full h-3 w-3 bg-emerald-500 transition-colors duration-500";
                        textEl.innerText = "Live Connection";
                        textEl.className = "font-medium text-emerald-600 transition-colors duration-500";
                        containerEl.className = "text-sm flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 border border-emerald-100 transition-colors duration-500";
                    } else {
                        // OFFLINE
                        pingEl.classList.add('hidden'); // Matikan kedip-kedip
                        dotEl.className = "relative inline-flex rounded-full h-3 w-3 bg-slate-400 transition-colors duration-500"; // Dot abu-abu
                        textEl.innerText = "Offline (" + data.updated_at + ")"; // Tampilkan kapan terakhir on
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

            // 3. Update Metrik ML
            async function fetchMetrics() {
                try {
                    const response = await fetch("{{ route('dashboard.metrics') }}");
                    const data = await response.json();

                    document.getElementById('ml-rmse').innerText = parseFloat(data.rmse).toFixed(4);
                    document.getElementById('ml-r2').innerText = parseFloat(data.r2_score).toFixed(4);
                    document.getElementById('ml-time').innerText = parseFloat(data.training_time).toFixed(2) + 's';
                    document.getElementById('ml-last-update').innerText = data.last_trained;
                } catch (error) {
                    console.error('Error fetching metrics:', error);
                }
            }

            // ================= RUN LOOPS =================
            // Panggil sekali saat load
            fetchStats();
            fetchChart();
            fetchMetrics();

            // Loop update otomatis
            setInterval(fetchStats, 2000);  // Tiap 2 detik untuk status
            setInterval(fetchChart, 5000);  // Tiap 5 detik untuk grafik
            setInterval(fetchMetrics, 10000); // Tiap 10 detik untuk metrik ML
        });
    </script>
</x-app-layout>
