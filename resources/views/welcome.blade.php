<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Water Monitor') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            tailwind.config = {
                theme: {
                    extend: {
                        fontFamily: {
                            sans: ['Instrument Sans', 'sans-serif'],
                        }
                    }
                }
            }
        </script>
    @endif
</head>
<body class="antialiased bg-slate-50 text-slate-800 selection:bg-cyan-500 selection:text-white">

    <div class="fixed top-0 left-0 w-full h-full overflow-hidden pointer-events-none -z-10">
        <div class="absolute top-0 right-0 w-[500px] h-[500px] bg-cyan-200/20 rounded-full blur-3xl -translate-y-1/2 translate-x-1/2"></div>
        <div class="absolute bottom-0 left-0 w-[500px] h-[500px] bg-violet-200/20 rounded-full blur-3xl translate-y-1/2 -translate-x-1/2"></div>
    </div>

    <div class="relative flex items-center justify-center min-h-screen px-4 py-10 sm:px-6 lg:px-8">

        <div class="flex flex-col w-full max-w-5xl overflow-hidden bg-white border shadow-2xl rounded-3xl border-slate-100 lg:flex-row">

            <div class="relative flex flex-col justify-between order-1 w-full p-8 text-white lg:w-5/12 bg-gradient-to-br from-cyan-500 to-blue-600 md:p-12 lg:order-1">
                <div class="absolute inset-0 opacity-10 pattern-dots"></div>

                <div class="relative z-10">
                    <div class="inline-flex items-center gap-2 px-3 py-1 mb-6 text-xs font-bold tracking-widest text-white uppercase border rounded-full bg-white/20 backdrop-blur-sm border-white/20">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        IoT Project 2025
                    </div>
                    <h1 class="text-3xl font-bold leading-tight md:text-4xl lg:text-5xl">
                        Smart Water <br/> Monitoring
                    </h1>
                    <p class="mt-4 text-sm leading-relaxed text-cyan-50 text-opacity-90 md:text-base">
                        Sistem pemantauan level, kualitas, dan penggunaan air secara <i>real-time</i> berbasis Internet of Things dan Machine Learning.
                    </p>

                    <div class="flex flex-wrap gap-4 mt-8">
                        @if (Route::has('login'))
                            @auth
                                <a href="{{ url('/dashboard') }}" class="inline-flex items-center justify-center w-full px-6 py-3 text-sm font-bold transition-all transform bg-white rounded-full shadow-lg text-cyan-700 hover:bg-cyan-50 hover:-translate-y-1 hover:shadow-xl sm:w-auto">
                                    Buka Dashboard &rarr;
                                </a>
                            @else
                                <a href="{{ route('login') }}" class="inline-flex items-center justify-center w-full px-6 py-3 text-sm font-bold transition-all transform bg-white rounded-full shadow-lg text-cyan-700 hover:bg-cyan-50 hover:-translate-y-1 hover:shadow-xl sm:w-auto">
                                    Log in
                                </a>

                                @if (Route::has('register'))
                                    <a href="{{ route('register') }}" class="inline-flex items-center justify-center w-full px-6 py-3 text-sm font-semibold text-white transition-all border rounded-full border-white/30 bg-white/10 backdrop-blur-md hover:bg-white/20 sm:w-auto">
                                        Register
                                    </a>
                                @endif
                            @endauth
                        @endif
                    </div>
                </div>

                <div class="relative z-10 hidden mt-12 lg:block">
                    <div class="inline-block p-4 border shadow-lg bg-white/10 rounded-2xl backdrop-blur-md border-white/20">
                       <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                           <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                       </svg>
                    </div>
                    <p class="mt-4 text-xs font-medium text-cyan-100 opacity-70">
                        &copy; {{ date('Y') }} Kelompok 6 IoT - UNIMED. All rights reserved.
                    </p>
                </div>
            </div>

            <div class="flex flex-col justify-center order-2 w-full p-8 bg-white lg:w-7/12 md:p-12 lg:order-2">

                <div class="pb-6 mb-8 border-b border-slate-100">
                    <h2 class="mb-2 text-xs font-bold tracking-wider uppercase text-slate-400">Tentang Project</h2>
                    <p class="text-sm leading-relaxed text-slate-600">
                        Project ini merupakan tugas akhir dari matakuliah <span class="font-semibold text-slate-800">Internet of Things (IoT)</span> yang diampu oleh:
                    </p>
                    <div class="flex items-center gap-3 p-3 mt-3 border bg-slate-50 rounded-xl border-slate-100">
                        <div class="flex items-center justify-center w-10 h-10 text-lg font-bold rounded-full shrink-0 bg-cyan-100 text-cyan-600">
                            DK
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-slate-800">Pak Dedi Kiswanto, S.Kom., M.Kom.</h3>
                            <p class="text-xs text-slate-500">Dosen Pengampu</p>
                        </div>
                    </div>
                </div>

                <div>
                    <h2 class="mb-4 text-xs font-bold tracking-wider uppercase text-slate-400">Anggota Kelompok</h2>

                    <div class="grid gap-3">
                        <div class="flex items-center justify-between p-3 transition-all bg-white border group border-slate-100 rounded-xl hover:border-cyan-200 hover:shadow-md hover:bg-cyan-50/30">
                            <div class="flex items-center gap-3">
                                <div class="flex items-center justify-center w-10 h-10 font-bold transition-colors rounded-full text-slate-500 bg-slate-100 group-hover:bg-cyan-500 group-hover:text-white shrink-0">
                                    RW
                                </div>
                                <div>
                                    <h4 class="text-sm font-bold md:text-base text-slate-700 group-hover:text-cyan-700">Rizky Wahyudi</h4>
                                    <p class="text-xs text-slate-400 group-hover:text-cyan-600/70">NIM: 4233250024</p>
                                </div>
                            </div>
                            <div class="text-slate-300 group-hover:text-cyan-400">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor">
                                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </div>

                        <div class="flex items-center justify-between p-3 transition-all bg-white border group border-slate-100 rounded-xl hover:border-violet-200 hover:shadow-md hover:bg-violet-50/30">
                            <div class="flex items-center gap-3">
                                <div class="flex items-center justify-center w-10 h-10 font-bold transition-colors rounded-full text-slate-500 bg-slate-100 group-hover:bg-violet-500 group-hover:text-white shrink-0">
                                    WA
                                </div>
                                <div>
                                    <h4 class="text-sm font-bold md:text-base text-slate-700 group-hover:text-violet-700">Windy Aulia</h4>
                                    <p class="text-xs text-slate-400 group-hover:text-violet-600/70">NIM: 4233250021</p>
                                </div>
                            </div>
                            <div class="text-slate-300 group-hover:text-violet-400">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor">
                                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </div>

                        <div class="flex items-center justify-between p-3 transition-all bg-white border group border-slate-100 rounded-xl hover:border-pink-200 hover:shadow-md hover:bg-pink-50/30">
                            <div class="flex items-center gap-3">
                                <div class="flex items-center justify-center w-10 h-10 font-bold transition-colors rounded-full text-slate-500 bg-slate-100 group-hover:bg-pink-500 group-hover:text-white shrink-0">
                                    SA
                                </div>
                                <div>
                                    <h4 class="text-sm font-bold md:text-base text-slate-700 group-hover:text-pink-700">Selfi Audy Priscilia</h4>
                                    <p class="text-xs text-slate-400 group-hover:text-pink-600/70">NIM: 4233250001</p>
                                </div>
                            </div>
                            <div class="text-slate-300 group-hover:text-pink-400">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor">
                                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </div>

                    </div>

                    <div class="block pt-6 mt-8 text-center border-t border-slate-100 lg:hidden">
                         <p class="text-xs font-medium text-slate-400">
                            &copy; {{ date('Y') }} Kelompok 6 IoT - UNIMED. All rights reserved.
                        </p>
                    </div>
                </div>

            </div>
        </div>
    </div>
</body>
</html>
