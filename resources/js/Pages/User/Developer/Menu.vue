<template>
    <div class="relative overflow-hidden rounded-[28px] border border-slate-200/80 bg-white/80 p-4 shadow-[0_24px_80px_-48px_rgba(15,23,42,0.45)] backdrop-blur-xl">
        <div class="pointer-events-none absolute inset-y-0 start-0 w-40 bg-[radial-gradient(circle_at_center,rgba(14,165,233,0.16),transparent_70%)]" />
        <div class="pointer-events-none absolute -top-10 end-0 h-24 w-24 rounded-full bg-emerald-200/30 blur-2xl" />

        <div class="relative flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl border border-sky-200/80 bg-sky-50 text-sky-700 shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none">
                        <path d="M7 7h10v10H7z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" />
                        <path d="M4 12h3m10 0h3M12 4v3m0 10v3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                    </svg>
                </div>

                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400">
                        {{ $t('Developer Tools') }}
                    </p>
                    <h2 class="text-lg font-semibold text-slate-900">
                        {{ $t('Integration workspace') }}
                    </h2>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <Link
                    rel="noopener noreferrer"
                    href="/developer-tools/access-tokens"
                    class="inline-flex items-center gap-2 rounded-2xl border px-4 py-2.5 text-sm font-medium transition"
                    :class="$page.url === '/developer-tools/access-tokens'
                        ? 'border-slate-900 bg-slate-900 text-white shadow-lg shadow-slate-900/15'
                        : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50'"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none">
                        <path d="M8 10a4 4 0 0 1 7-2.646l1.293 1.293a4 4 0 1 1-5.657 5.657l-.543-.543" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                        <path d="M16 14a4 4 0 0 1-7 2.646l-1.293-1.293A4 4 0 0 1 13.364 9.7l.543.543" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <span>{{ $t('Access token') }}</span>
                </Link>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
</script>
