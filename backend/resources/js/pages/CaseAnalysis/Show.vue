<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { AlertCircle, ArrowLeft, BrainCircuit, CheckCircle2, FileText, LoaderCircle, Sparkles } from '@lucide/vue';

const props = defineProps({
    analysis: { type: Object, default: null },
    caseData: { type: Object, default: null },
    latestAnalysis: { type: Object, default: null },
});

const currentAnalysis = computed(() => props.latestAnalysis || props.analysis);
const form = useForm({
    expediente: props.caseData?.EXPEDIENTE || '',
    id_carpeta: props.caseData?.ID_CARPETA || '',
});
const isSaving = ref(false);
const elements = ref([]);
const diligences = ref([]);
let pollInterval = null;

const isProcessing = computed(() => currentAnalysis.value?.status === 'draft');
const hasResults = computed(() => currentAnalysis.value && !isProcessing.value);

const stopPolling = () => {
    if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
    }
};

const startPolling = () => {
    if (pollInterval || !props.caseData) return;

    pollInterval = setInterval(() => {
        router.reload({
            only: ['latestAnalysis'],
            preserveScroll: true,
            onSuccess: () => {
                if (currentAnalysis.value && !isProcessing.value) stopPolling();
            },
        });
    }, 3000);
};

const triggerAnalysis = () => {
    form.post(route('cases.analyze'), { preserveScroll: true, onSuccess: startPolling });
};

const syncReview = () => {
    elements.value = JSON.parse(JSON.stringify(currentAnalysis.value?.elements_status || []));
    diligences.value = (currentAnalysis.value?.suggested_diligences || []).map((item) => ({ ...item, accepted: item.accepted ?? true }));
};

const updateElementStatus = (index, status) => { elements.value[index].status = status; };
const toggleDiligence = (index) => { diligences.value[index].accepted = !diligences.value[index].accepted; };

const saveHumanReview = () => {
    isSaving.value = true;
    router.put(route('case-analysis.update', currentAnalysis.value.id), {
        elements_status: elements.value,
        suggested_diligences: diligences.value,
        status: 'reviewed',
    }, { onFinish: () => { isSaving.value = false; } });
};

const getStatusBadge = (status) => ({
    ACREDITADO: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    FALTANTE: 'bg-amber-50 text-amber-700 border-amber-200',
    CONTRADICTORIO: 'bg-rose-50 text-rose-700 border-rose-200',
}[status] || 'bg-slate-50 text-slate-600 border-slate-200');

onMounted(() => {
    syncReview();
    if (isProcessing.value) startPolling();
});
onUnmounted(stopPolling);
watch(currentAnalysis, syncReview);
</script>

<template>
    <Head title="Análisis de Carpeta | MP-IA" />
    <main class="min-h-screen bg-[#f4f7f6] text-slate-900">
        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <Link :href="route('cases.index', { expediente: caseData?.EXPEDIENTE })" class="mb-6 inline-flex items-center gap-2 text-sm font-semibold text-slate-500 transition hover:text-emerald-700"><ArrowLeft class="size-4" /> Regresar a resultados</Link>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 bg-slate-900 px-6 py-7 text-white sm:px-8">
                    <div class="flex flex-col justify-between gap-6 md:flex-row md:items-start">
                        <div>
                            <div class="mb-3 flex flex-wrap items-center gap-2 text-xs font-bold uppercase tracking-[0.18em] text-emerald-300"><span class="rounded-full bg-emerald-400/10 px-3 py-1">Carpeta de investigación</span><span v-if="caseData">{{ caseData.TIPO }} #{{ caseData.ID_CARPETA }}</span></div>
                            <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ caseData?.EXPEDIENTE || currentAnalysis?.external_case_id }}</h1>
                            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-300">{{ caseData?.DELITO || 'Análisis jurídico asistido por inteligencia artificial' }}<span v-if="caseData?.MODALIDAD"> · {{ caseData.MODALIDAD }}</span><span v-if="caseData?.MUNICIPIO"> · {{ caseData.MUNICIPIO }}</span></p>
                        </div>
                        <button v-if="caseData && !isProcessing" type="button" :disabled="form.processing" @click="triggerAnalysis" class="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-400 px-5 py-3 text-sm font-bold text-slate-950 shadow-lg shadow-emerald-950/20 transition hover:bg-emerald-300 disabled:cursor-not-allowed disabled:opacity-60"><Sparkles class="size-4" /> {{ currentAnalysis ? 'Reanalizar con IA' : 'Analizar con IA' }}</button>
                    </div>
                </div>
                <div v-if="caseData" class="grid gap-6 p-6 sm:p-8 lg:grid-cols-[1fr_280px]">
                    <div><div class="mb-3 flex items-center gap-2 text-sm font-bold text-slate-800"><FileText class="size-4 text-emerald-600" /> Narrativa de los hechos</div><p class="rounded-xl border border-slate-200 bg-slate-50 p-5 text-sm leading-7 text-slate-600">{{ caseData.DESCRIPCION_HECHOS || 'Sin narrativa registrada.' }}</p></div>
                    <div class="rounded-xl border border-slate-200 p-5"><p class="text-xs font-bold uppercase tracking-wider text-slate-400">Estado del expediente</p><p class="mt-2 text-lg font-bold text-slate-800">{{ caseData.ESTADO }}</p><p class="mt-4 text-xs text-slate-500">Unidad: <span class="font-semibold text-slate-700">{{ caseData.UNIDAD }}</span></p><p class="mt-1 text-xs text-slate-500">Municipio: <span class="font-semibold text-slate-700">{{ caseData.MUNICIPIO }}</span></p></div>
                </div>
            </section>

            <Transition name="fade" mode="out-in">
                <section v-if="isProcessing" key="processing" class="analysis-loader mt-6 overflow-hidden rounded-2xl border border-emerald-200 bg-white p-8 shadow-sm sm:p-12"><div class="mx-auto max-w-xl text-center"><div class="relative mx-auto mb-7 flex size-20 items-center justify-center rounded-full bg-emerald-50 text-emerald-600"><BrainCircuit class="size-9 animate-[pulse_2s_ease-in-out_infinite]" /><span class="absolute inset-0 animate-ping rounded-full border-2 border-emerald-300/50"></span></div><p class="text-xs font-bold uppercase tracking-[0.2em] text-emerald-600">MP-IA Engine en ejecución</p><h2 class="mt-3 text-2xl font-bold text-slate-900">Analizando la carpeta</h2><p class="mx-auto mt-3 max-w-md text-sm leading-6 text-slate-500">La IA está contrastando los hechos con los elementos del tipo penal. Los resultados aparecerán aquí automáticamente.</p><div class="mt-8 h-2 overflow-hidden rounded-full bg-slate-100"><div class="loader-bar h-full rounded-full bg-emerald-500"></div></div><div class="mt-4 flex items-center justify-center gap-2 text-xs font-semibold text-slate-400"><LoaderCircle class="size-4 animate-spin" /> Esto puede tardar unos minutos</div></div></section>
                <section v-else-if="hasResults" key="results" class="mt-6 space-y-6"><div class="flex items-center justify-between"><div><p class="text-xs font-bold uppercase tracking-wider text-emerald-600">Resultado de la IA</p><h2 class="mt-1 text-2xl font-bold text-slate-900">Dictamen e integración legal</h2></div><span class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700"><CheckCircle2 class="size-4" /> {{ currentAnalysis.status }}</span></div><div class="grid gap-6 lg:grid-cols-[1.35fr_1fr]"><div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"><h3 class="mb-5 text-lg font-bold">Elementos del tipo penal</h3><div class="space-y-4"><article v-for="(element, index) in elements" :key="element.element_id || index" class="rounded-xl border p-4" :class="element.status === 'ACREDITADO' ? 'border-slate-200' : 'border-amber-200 bg-amber-50/30'"><div class="flex items-start justify-between gap-3"><span class="text-sm font-bold">Elemento constitutivo #{{ element.element_id }}</span><span class="rounded-md border px-2 py-1 text-[10px] font-bold" :class="getStatusBadge(element.status)">{{ element.status }}</span></div><p v-if="element.evidence_found" class="mt-3 text-xs leading-5 text-slate-600"><b>Evidencia:</b> {{ element.evidence_found }}</p><p v-if="element.missing_reason" class="mt-2 text-xs leading-5 text-amber-700"><b>Observación:</b> {{ element.missing_reason }}</p><div class="mt-3 flex gap-2 border-t border-slate-100 pt-3"><button type="button" class="rounded px-2 py-1 text-xs font-semibold" :class="element.status === 'ACREDITADO' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600'" @click="updateElementStatus(index, 'ACREDITADO')">Acreditar</button><button type="button" class="rounded px-2 py-1 text-xs font-semibold" :class="element.status === 'FALTANTE' ? 'bg-amber-600 text-white' : 'bg-slate-100 text-slate-600'" @click="updateElementStatus(index, 'FALTANTE')">Faltante</button></div></article></div></div><div class="space-y-6"><div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"><h3 class="mb-4 text-lg font-bold">Auditoría de objetividad</h3><div v-if="currentAnalysis.objectivity_audit?.bias_warning" class="mb-4 flex gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs leading-5 text-amber-800"><AlertCircle class="size-4 shrink-0" />{{ currentAnalysis.objectivity_audit.bias_warning }}</div><h4 class="mb-2 text-xs font-bold uppercase tracking-wider text-emerald-700">Elementos de cargo</h4><ul class="space-y-2"><li v-for="(item, index) in currentAnalysis.objectivity_audit?.cargo_elements || []" :key="index" class="rounded border border-emerald-100 bg-emerald-50/60 p-2.5 text-xs text-slate-700">{{ item }}</li></ul><h4 class="mb-2 mt-5 text-xs font-bold uppercase tracking-wider text-sky-700">Elementos de descargo</h4><ul class="space-y-2"><li v-for="(item, index) in currentAnalysis.objectivity_audit?.descargo_elements || []" :key="index" class="rounded border border-sky-100 bg-sky-50/60 p-2.5 text-xs text-slate-700">{{ item }}</li></ul></div><div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"><h3 class="mb-4 text-lg font-bold">Diligencias sugeridas</h3><div class="space-y-3"><div v-for="(diligence, index) in diligences" :key="index" class="rounded-lg border p-3" :class="diligence.accepted ? 'border-emerald-200 bg-emerald-50/40' : 'border-slate-200 opacity-60'"><div class="flex items-start justify-between gap-3"><div><p class="text-[10px] font-bold uppercase text-emerald-700">{{ diligence.legal_basis }}</p><p class="mt-1 text-xs font-bold text-slate-800">{{ diligence.action }}</p><p class="mt-1 text-xs text-slate-500">{{ diligence.purpose }}</p></div><button type="button" class="shrink-0 rounded px-2 py-1 text-[10px] font-bold" :class="diligence.accepted ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600'" @click="toggleDiligence(index)">{{ diligence.accepted ? 'Aprobada' : 'Descartada' }}</button></div></div></div><button type="button" :disabled="isSaving" class="mt-5 w-full rounded-lg bg-slate-900 px-4 py-3 text-sm font-bold text-white transition hover:bg-slate-800 disabled:opacity-50" @click="saveHumanReview">{{ isSaving ? 'Guardando...' : 'Confirmar revisión ministerial' }}</button></div></div></div></section>
                <section v-else key="empty" class="mt-6 rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center"><Sparkles class="mx-auto size-8 text-emerald-500" /><h2 class="mt-3 text-lg font-bold">Listo para analizar</h2><p class="mt-2 text-sm text-slate-500">Presiona “Analizar con IA” para obtener la matriz jurídica y las diligencias recomendadas.</p></section>
            </Transition>
        </div>
    </main>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.35s ease, transform 0.35s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; transform: translateY(8px); }
.loader-bar { width: 45%; animation: loading 2.4s ease-in-out infinite; }
@keyframes loading { 0% { transform: translateX(-110%); } 60%, 100% { transform: translateX(230%); } }
</style>
