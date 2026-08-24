<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, FolderSearch, Plus, Scale } from '@lucide/vue';
import { dashboard } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});
</script>

<template>
    <Head title="Dashboard" />
    <div class="min-h-full bg-[#f4f7f6] p-4 sm:p-6">
        <div class="mx-auto max-w-7xl space-y-6">
            <section class="overflow-hidden rounded-2xl bg-slate-900 px-6 py-8 text-white shadow-xl shadow-slate-900/10 sm:px-10 sm:py-10">
                <div class="flex flex-col justify-between gap-7 md:flex-row md:items-center">
                    <div><p class="text-xs font-bold uppercase tracking-[0.2em] text-emerald-300">Ministerio Público · MP-IA</p><h1 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">Centro de análisis jurídico</h1><p class="mt-3 max-w-xl text-sm leading-6 text-slate-300">Busca carpetas, consulta tus análisis y toma decisiones con información organizada en un solo espacio.</p></div>
                    <div class="flex size-20 shrink-0 items-center justify-center rounded-2xl bg-emerald-400 text-slate-950"><Scale class="size-10" /></div>
                </div>
            </section>
            <section class="grid gap-5 md:grid-cols-2">
                <Link href="/case-analysis" class="group rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-md"><div class="flex items-start justify-between"><div class="flex size-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700"><FolderSearch class="size-6" /></div><ArrowRight class="size-5 text-slate-300 transition group-hover:translate-x-1 group-hover:text-emerald-600" /></div><h2 class="mt-5 text-xl font-bold">Mis análisis de carpetas</h2><p class="mt-2 text-sm leading-6 text-slate-500">Consulta análisis anteriores, revisa su estado y continúa trabajando sobre sus resultados.</p><span class="mt-5 inline-flex items-center gap-2 text-sm font-bold text-emerald-700">Ver módulo <ArrowRight class="size-4" /></span></Link>
                <Link :href="route('cases.index')" class="group rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:border-slate-400 hover:shadow-md"><div class="flex items-start justify-between"><div class="flex size-11 items-center justify-center rounded-xl bg-slate-100 text-slate-700"><Plus class="size-6" /></div><ArrowRight class="size-5 text-slate-300 transition group-hover:translate-x-1 group-hover:text-slate-700" /></div><h2 class="mt-5 text-xl font-bold">Analizar nueva carpeta</h2><p class="mt-2 text-sm leading-6 text-slate-500">Busca un expediente en Fiscalía Digital y solicita un análisis jurídico asistido por IA.</p><span class="mt-5 inline-flex items-center gap-2 text-sm font-bold text-slate-800">Buscar carpeta <ArrowRight class="size-4" /></span></Link>
            </section>
        </div>
    </div>
</template>
