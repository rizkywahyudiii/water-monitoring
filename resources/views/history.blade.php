<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-slate-800">
            {{ __('Riwayat Data Sensor') }}
        </h2>
    </x-slot>

    <div class="min-h-screen py-12 bg-slate-50">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">

            <!-- Card Container -->
            <div class="overflow-hidden bg-white border shadow-sm sm:rounded-2xl border-slate-200">
                <div class="flex items-center justify-between p-6 bg-white border-b border-slate-200">
                    <div>
                        <h3 class="text-lg font-bold text-slate-700">Log Data Lengkap</h3>
                        <p class="text-sm text-slate-500">Menampilkan 10 data per halaman.</p>
                    </div>
                    <!-- Tombol Export CSV -->
                    <a
                        href="{{ route('history.export') }}"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white transition rounded-lg shadow-sm bg-emerald-500 hover:bg-emerald-600"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        Download CSV
                    </a>
                </div>

                <!-- TABLE WRAPPER -->
                <!-- max-h-[500px] & overflow-y-auto membuat tabel bisa di-scroll vertikal -->
                <div class="overflow-x-auto max-h-[500px] overflow-y-auto relative">
                    <table class="w-full text-sm text-left text-slate-500">
                        <!-- STICKY HEADER -->
                        <thead class="sticky top-0 z-10 text-xs uppercase shadow-sm text-slate-700 bg-slate-100">
                            <tr>
                                <th scope="col" class="px-6 py-4 font-bold tracking-wider">Waktu (WIB)</th>
                                <th scope="col" class="px-6 py-4 font-bold tracking-wider">Level Air</th>
                                <th scope="col" class="px-6 py-4 font-bold tracking-wider">Jarak Sensor</th>
                                <th scope="col" class="px-6 py-4 font-bold tracking-wider">Kekeruhan</th>
                                <th scope="col" class="px-6 py-4 font-bold tracking-wider">Rate</th>
                                <th scope="col" class="px-6 py-4 font-bold tracking-wider">Prediksi AI</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($data as $row)
                                <tr class="transition duration-150 bg-white hover:bg-cyan-50">
                                    <!-- Timestamp -->
                                    <td class="px-6 py-4 font-medium whitespace-nowrap text-slate-900">
                                        {{ $row->created_at->format('d M Y') }}
                                        <span class="mx-1 text-slate-400">|</span>
                                        {{ $row->created_at->format('H:i:s') }}
                                    </td>

                                    <!-- Level Air -->
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-2">
                                            <div class="w-full bg-slate-200 rounded-full h-2.5 w-16">
                                                <div class="bg-cyan-500 h-2.5 rounded-full" style="width: {{ $row->water_level }}%"></div>
                                            </div>
                                            <span class="font-bold text-cyan-700">{{ $row->water_level }}%</span>
                                        </div>
                                    </td>

                                    <!-- Jarak -->
                                    <td class="px-6 py-4">
                                        {{ number_format($row->distance, 1) }} cm
                                    </td>

                                    <!-- Kekeruhan -->
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col">
                                            <span class="font-bold {{ $row->turbidity_status == 'JERNIH' ? 'text-emerald-600' : ($row->turbidity_status == 'KERUH' ? 'text-rose-600' : 'text-amber-600') }}">
                                                {{ $row->turbidity_status ?? 'UNKNOWN' }}
                                            </span>
                                            <span class="text-xs text-slate-400">{{ number_format($row->turbidity, 2) }} V</span>
                                        </div>
                                    </td>

                                    <!-- Rate -->
                                    <td class="px-6 py-4">
                                        @if($row->depletion_rate > 0)
                                            <span class="font-mono text-rose-500">↓ {{ number_format($row->depletion_rate, 2) }}%/h</span>
                                        @else
                                            <span class="font-mono text-slate-400">-</span>
                                        @endif
                                    </td>

                                    <!-- Prediksi -->
                                    <td class="px-6 py-4">
                                        @if($row->prediction)
                                            <div class="flex flex-col">
                                                <span class="font-bold text-violet-600">
                                                    {{ $row->prediction->time_remaining ?? number_format($row->prediction->predicted_hours, 1).' jam' }}
                                                </span>
                                                <span class="text-[10px] text-slate-400 uppercase tracking-wide">
                                                    {{ $row->prediction->predicted_method }}
                                                </span>
                                            </div>
                                        @else
                                            <span class="italic text-slate-300">No Data</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-10 text-center text-slate-400">
                                        Belum ada data sensor yang terekam.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- PAGINATION FOOTER -->
                <div class="p-4 border-t border-slate-200 bg-slate-50">
                    {{ $data->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
