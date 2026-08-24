<script setup>
import CaseAnalysisShow from '../CaseAnalysis/Show.vue';

defineProps({
    caseData: { type: Object, default: null },
    latestAnalysis: { type: Object, default: null },
});
</script>

<template>
    <CaseAnalysisShow
        :case-data="caseData"
        :latest-analysis="latestAnalysis"
    />
</template>
<script setup>
import { onMounted, onUnmounted } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';

const props = defineProps({
    caseData: Object,
    latestAnalysis: Object
});

const form = useForm({
    expediente: props.caseData.EXPEDIENTE,
    id_carpeta: props.caseData.ID_CARPETA
});

const triggerAnalysis = () => {
    form.post(route('cases.analyze'), {
        preserveScroll: true,
        onSuccess: () => startPolling()
    });
};

let pollInterval = null;

const startPolling = () => {
    if (pollInterval) return;
    pollInterval = setInterval(() => {
        router.reload({
            only: ['latestAnalysis'],
            onSuccess: () => {
                if (props.latestAnalysis && props.latestAnalysis.status !== 'draft') {
                    clearInterval(pollInterval);
                    pollInterval = null;
                }
            }
        });
    }, 3000);
};

onMounted(() => {
    if (props.latestAnalysis && props.latestAnalysis.status === 'draft') {
        startPolling();
    }
});

onUnmounted(() => {
    if (pollInterval) clearInterval(pollInterval);
});
</script>

<template>
    <div class="p-6 max-w-7xl mx-auto space-y-6">
        <!-- Botón Regresar -->
        <Link :href="route('cases.index', { expediente: caseData.EXPEDIENTE })"
            class="inline-flex items-center text-sm text-indigo-600 hover:text-indigo-800 font-medium gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Regresar a resultados
        </Link>

        <!-- Encabezado de la carpeta seleccionada -->
        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 flex justify-between items-start">
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <span class="px-2.5 py-0.5 rounded text-xs font-semibold bg-indigo-100 text-indigo-800">
                        {{ caseData.TIPO }} #{{ caseData.ID_CARPETA }}
                    </span>
                    <span class="px-2.5 py-0.5 rounded text-xs font-semibold bg-gray-100 text-gray-700">
                        {{ caseData.ESTADO }}
                    </span>
                </div>
                <h1 class="text-2xl font-bold text-gray-900">Expediente: {{ caseData.EXPEDIENTE }}</h1>
                <p class="text-sm text-gray-600 mt-1">
                    <span class="font-semibold">Delito:</span> {{ caseData.DELITO }} ({{ caseData.MODALIDAD }}) |
                    <span class="font-semibold">Unidad:</span> {{ caseData.UNIDAD }} - {{ caseData.MUNICIPIO }}
                </p>
            </div>

            <button v-if="!latestAnalysis || latestAnalysis.status !== 'draft'" @click="triggerAnalysis"
                :disabled="form.processing"
                class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-4 py-2 rounded-lg shadow transition flex items-center gap-2">
                <span>{{ latestAnalysis ? 'Reanalizar con IA' : 'Analizar Hechos con IA' }}</span>
            </button>
        </div>

        <!-- Descripción de los hechos -->
        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
            <h2 class="text-lg font-bold text-gray-900 mb-3">Descripción de los Hechos (Denuncia)</h2>
            <p class="text-gray-700 leading-relaxed bg-gray-50 p-4 rounded-lg border text-sm whitespace-pre-line">
                {{ caseData.DESCRIPCION_HECHOS }}
            </p>
        </div>

        <!-- Resultados del Análisis de la IA -->
        <div v-if="latestAnalysis" class="bg-white p-6 rounded-xl shadow-sm border-t-4 border-indigo-600 space-y-6">
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-bold text-gray-900">Dictamen e Integración Legal</h2>
                <span class="px-3 py-1 rounded-full text-xs font-semibold"
                    :class="latestAnalysis.status === 'draft' ? 'bg-yellow-100 text-yellow-800 animate-pulse' : 'bg-green-100 text-green-800'">
                    {{ latestAnalysis.status === 'draft' ? 'Procesando en FastAPI...' : 'Análisis Completado' }}
                </span>
            </div>

            <div v-if="latestAnalysis.status === 'draft'" class="p-8 text-center text-gray-500">
                Analizando hechos con el microservicio de Python...
            </div>

            <div v-else class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-gray-50 p-4 rounded-lg border">
                    <h3 class="font-semibold text-gray-900 mb-2">Elementos del Delito</h3>
                    <pre class="text-xs text-gray-700 whitespace-pre-wrap">{{ latestAnalysis.elements_status }}</pre>
                </div>

                <div class="bg-gray-50 p-4 rounded-lg border">
                    <h3 class="font-semibold text-gray-900 mb-2">Auditoría de Objetividad</h3>
                    <pre class="text-xs text-gray-700 whitespace-pre-wrap">{{ latestAnalysis.objectivity_audit }}</pre>
                </div>

                <div class="bg-gray-50 p-4 rounded-lg border">
                    <h3 class="font-semibold text-gray-900 mb-2">Diligencias Sugeridas</h3>
                    <pre
                        class="text-xs text-gray-700 whitespace-pre-wrap">{{ latestAnalysis.suggested_diligences }}</pre>
                </div>
            </div>
        </div>
    </div>
</template>
