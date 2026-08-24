<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { ArrowRight, CheckCircle2, Clock3, FolderSearch, Plus, Search, XCircle } from '@lucide/vue';

const props = defineProps({
    analyses: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    stats: { type: Object, default: () => ({ total: 0, processing: 0, completed: 0, failed: 0 }) },
});

const form = useForm({
    search: props.filters.search || '',
    status: props.filters.status || '',
});

const applyFilters = () => form.get(route('case-analysis.index'), { preserveState: true, preserveScroll: true });
const clearFilters = () => {
    form.reset();
    applyFilters();
};

const statusLabel = (status) => ({ draft: 'Procesando', reviewed: 'Completado', approved: 'Aprobado', rejected: 'Fallido' }[status] || status);
const statusClass = (status) => ({ draft: 'bg-amber-50 text-amber-700 border-amber-200', reviewed: 'bg-emerald-50 text-emerald-700 border-emerald-200', approved: 'bg-sky-50 text-sky-700 border-sky-200', rejected: 'bg-rose-50 text-rose-700 border-rose-200' }[status] || 'bg-slate-50 text-slate-600 border-slate-200');
const formatDate = (date) => date ? new Date(date).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' }) : 'Sin fecha';
</script>

<template>
    <main class="min-h-screen bg-[#f4f7f6] text-slate-900">
        <div class="mx-auto max-w-7xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">
            <header class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
                <div><p class="text-xs font-bold uppercase tracking-[0.2em] text-emerald-600">Centro de trabajo</p><h1 class="mt-2 text-3xl font-bold tracking-tight">Mis análisis de carpetas</h1><p class="mt-2 max-w-xl text-sm leading-6 text-slate-500">Consulta resultados, continúa revisiones y vuelve a analizar expedientes desde un solo lugar.</p></div>
                <Link :href="route('cases.index')" class="inline-flex items-center justify-center gap-2 rounded-lg bg-slate-900 px-4 py-3 text-sm font-bold text-white transition hover:bg-emerald-700"><Plus class="size-4" /> Nuevo análisis</Link>
            </header>

            <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"><div class="flex items-center justify-between"><p class="text-xs font-bold uppercase tracking-wider text-slate-400">Total</p><FolderSearch class="size-5 text-slate-400" /></div><p class="mt-4 text-3xl font-bold">{{ stats.total }}</p><p class="mt-1 text-xs text-slate-500">Carpetas analizadas</p></div>
                <div class="rounded-xl border border-amber-200 bg-amber-50/60 p-5 shadow-sm"><div class="flex items-center justify-between"><p class="text-xs font-bold uppercase tracking-wider text-amber-700">En proceso</p><Clock3 class="size-5 text-amber-600" /></div><p class="mt-4 text-3xl font-bold text-amber-900">{{ stats.processing }}</p><p class="mt-1 text-xs text-amber-800">Esperando respuesta de IA</p></div>
                <div class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-5 shadow-sm"><div class="flex items-center justify-between"><p class="text-xs font-bold uppercase tracking-wider text-emerald-700">Completados</p><CheckCircle2 class="size-5 text-emerald-600" /></div><p class="mt-4 text-3xl font-bold text-emerald-900">{{ stats.completed }}</p><p class="mt-1 text-xs text-emerald-800">Listos para revisión</p></div>
                <div class="rounded-xl border border-rose-200 bg-rose-50/60 p-5 shadow-sm"><div class="flex items-center justify-between"><p class="text-xs font-bold uppercase tracking-wider text-rose-700">Requieren atención</p><XCircle class="size-5 text-rose-600" /></div><p class="mt-4 text-3xl font-bold text-rose-900">{{ stats.failed }}</p><p class="mt-1 text-xs text-rose-800">Análisis para reintentar</p></div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <form @submit.prevent="applyFilters" class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50 p-4 sm:flex-row"><div class="relative flex-1"><Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" /><input v-model="form.search" type="search" placeholder="Buscar por expediente..." class="w-full rounded-lg border border-slate-200 bg-white py-2.5 pl-10 pr-3 text-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10" /></div><select v-model="form.status" class="rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-emerald-500"><option value="">Todos los estados</option><option value="draft">En proceso</option><option value="reviewed">Completados</option><option value="approved">Aprobados</option><option value="rejected">Fallidos</option></select><button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-700">Filtrar</button><button v-if="form.search || form.status" type="button" class="rounded-lg px-3 py-2.5 text-sm font-semibold text-slate-500 hover:bg-slate-200" @click="clearFilters">Limpiar</button></form>
                <div v-if="analyses.length" class="divide-y divide-slate-100"><Link v-for="analysis in analyses" :key="analysis.id" :href="route('case-analysis.show', analysis.id)" class="group flex flex-col gap-4 p-5 transition hover:bg-emerald-50/30 sm:flex-row sm:items-center sm:justify-between"><div class="flex min-w-0 items-start gap-4"><div class="mt-0.5 flex size-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500 group-hover:bg-emerald-100 group-hover:text-emerald-700"><FolderSearch class="size-5" /></div><div class="min-w-0"><p class="truncate font-bold text-slate-800">{{ analysis.external_case_id }}</p><p class="mt-1 text-xs text-slate-500">Delito #{{ analysis.external_offense_id }} · Creado {{ formatDate(analysis.created_at) }}</p><p v-if="analysis.status === 'rejected' && analysis.error_message" class="mt-2 line-clamp-1 text-xs text-rose-700">{{ analysis.error_message }}</p></div></div><div class="flex items-center justify-between gap-4 sm:justify-end"><span class="rounded-full border px-2.5 py-1 text-xs font-bold" :class="statusClass(analysis.status)">{{ statusLabel(analysis.status) }}</span><span class="inline-flex items-center gap-1 text-xs font-bold text-slate-500 group-hover:text-emerald-700">Abrir <ArrowRight class="size-4" /></span></div></Link></div><div v-else class="px-6 py-16 text-center"><FolderSearch class="mx-auto size-10 text-slate-300" /><h2 class="mt-4 text-lg font-bold">Aún no tienes análisis</h2><p class="mx-auto mt-2 max-w-md text-sm text-slate-500">Busca una carpeta para comenzar tu primer análisis jurídico asistido por IA.</p><Link :href="route('cases.index')" class="mt-5 inline-flex items-center gap-2 rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-700"><Plus class="size-4" /> Buscar carpeta</Link></div>
            </section>
        </div>
    </main>
</template>
