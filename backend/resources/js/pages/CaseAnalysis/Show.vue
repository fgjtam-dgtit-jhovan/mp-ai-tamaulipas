<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    AlertCircle,
    ArrowLeft,
    BrainCircuit,
    CheckCircle2,
    CircleAlert,
    FileText,
    Gavel,
    LoaderCircle,
    MapPin,
    RefreshCw,
    Scale,
    Sparkles,
} from '@lucide/vue';

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
const hasFailed = computed(() => currentAnalysis.value?.status === 'rejected');
const hasResults = computed(() => currentAnalysis.value && !isProcessing.value && !hasFailed.value);
const statusLabel = computed(() => ({
    draft: 'Procesando',
    reviewed: 'Análisis completado',
    approved: 'Aprobado',
    rejected: 'Requiere atención',
}[currentAnalysis.value?.status] || 'Pendiente de análisis'));

const syncReview = () => {
    elements.value = JSON.parse(JSON.stringify(currentAnalysis.value?.elements_status || []));
    diligences.value = (currentAnalysis.value?.suggested_diligences || []).map((item) => ({
        ...item,
        accepted: item.accepted ?? true,
    }));
};

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
    form.clearErrors();
    form.post(route('cases.analyze'), {
        preserveScroll: true,
        onSuccess: startPolling,
    });
};

const updateElementStatus = (index, status) => {
    elements.value[index].status = status;
};

const toggleDiligence = (index) => {
    diligences.value[index].accepted = !diligences.value[index].accepted;
};

const saveHumanReview = () => {
    isSaving.value = true;
    router.put(route('case-analysis.update', currentAnalysis.value.id), {
        elements_status: elements.value,
        suggested_diligences: diligences.value,
        status: 'reviewed',
    }, { onFinish: () => { isSaving.value = false; } });
};

const getStatusBadge = (status) => ({
    ACREDITADO: 'status-success',
    FALTANTE: 'status-warning',
    CONTRADICTORIO: 'status-danger',
}[status] || 'status-neutral');

const formatStatus = (status) => ({
    ACREDITADO: 'Acreditado',
    FALTANTE: 'Faltante',
    CONTRADICTORIO: 'Contradictorio',
}[status] || status);

onMounted(() => {
    syncReview();
    if (isProcessing.value) startPolling();
});

onUnmounted(stopPolling);
watch(currentAnalysis, syncReview);
</script>

<template>
    <Head title="Análisis de carpeta | MP-IA" />

    <main class="analysis-page">
        <div class="analysis-shell">
            <Link
                :href="route('cases.index', { expediente: caseData?.EXPEDIENTE })"
                class="back-link"
            >
                <ArrowLeft class="size-4" />
                Volver a carpetas
            </Link>

            <header class="case-header">
                <div class="case-header__content">
                    <div class="eyebrow"><Scale class="size-4" /> Expediente ministerial</div>
                    <h1>{{ caseData?.EXPEDIENTE || currentAnalysis?.external_case_id || 'Carpeta sin expediente' }}</h1>
                    <div class="case-header__meta">
                        <span class="case-chip">{{ caseData?.TIPO || 'Carpeta' }} #{{ caseData?.ID_CARPETA || 'N/D' }}</span>
                        <span v-if="caseData?.DELITO">{{ caseData.DELITO }}</span>
                        <span v-if="caseData?.MODALIDAD">{{ caseData.MODALIDAD }}</span>
                    </div>
                </div>
                <div class="case-header__action">
                    <span class="header-status" :class="{ 'header-status--danger': hasFailed, 'header-status--working': isProcessing }">
                        <LoaderCircle v-if="isProcessing" class="size-4 animate-spin" />
                        <CircleAlert v-else-if="hasFailed" class="size-4" />
                        <CheckCircle2 v-else class="size-4" />
                        {{ statusLabel }}
                    </span>
                    <button
                        v-if="caseData && !isProcessing"
                        type="button"
                        class="primary-button"
                        :disabled="form.processing"
                        @click="triggerAnalysis"
                    >
                        <RefreshCw class="size-4" />
                        {{ currentAnalysis ? 'Reanalizar' : 'Analizar carpeta' }}
                    </button>
                </div>
            </header>

            <div v-if="form.errors.analysis || form.errors.expediente" class="request-error">
                <AlertCircle class="size-5 shrink-0" />
                <div>
                    <strong>No se pudo iniciar el análisis</strong>
                    <p>{{ form.errors.analysis || form.errors.expediente }}</p>
                </div>
            </div>

            <section class="overview-grid">
                <article class="overview-card overview-card--narrative">
                    <div class="section-heading"><span class="icon-box icon-box--green"><FileText class="size-5" /></span><div><p>Información de la carpeta</p><h2>Narrativa de los hechos</h2></div></div>
                    <p class="narrative-text">{{ caseData?.DESCRIPCION_HECHOS || currentAnalysis?.facts_breakdown?.narrative || 'No hay narrativa registrada.' }}</p>
                </article>
                <article class="overview-card overview-card--facts">
                    <div class="section-heading"><span class="icon-box icon-box--slate"><MapPin class="size-5" /></span><div><p>Referencia operativa</p><h2>Datos de ubicación</h2></div></div>
                    <dl class="fact-list">
                        <div><dt>Estado</dt><dd>{{ caseData?.ESTADO || 'No disponible' }}</dd></div>
                        <div><dt>Unidad</dt><dd>{{ caseData?.UNIDAD || 'No disponible' }}</dd></div>
                        <div><dt>Municipio</dt><dd>{{ caseData?.MUNICIPIO || 'No disponible' }}</dd></div>
                    </dl>
                </article>
            </section>

            <Transition name="fade" mode="out-in">
                <section v-if="isProcessing" key="processing" class="state-panel state-panel--processing">
                    <div class="processing-icon"><BrainCircuit class="size-9" /><span></span></div>
                    <p class="state-kicker">MP-IA ENGINE · PROCESANDO</p>
                    <h2>Analizando los hechos de la carpeta</h2>
                    <p>Estamos contrastando la narrativa con los elementos jurídicos configurados para <strong>{{ caseData?.DELITO }}</strong>. Esta pantalla se actualizará automáticamente.</p>
                    <div class="progress-track"><div class="progress-bar"></div></div>
                    <div class="processing-note"><LoaderCircle class="size-4 animate-spin" /> El análisis puede tardar unos minutos</div>
                </section>

                <section v-else-if="hasFailed" key="failed" class="state-panel state-panel--failed">
                    <div class="failed-icon"><CircleAlert class="size-7" /></div>
                    <div class="failed-copy"><p class="state-kicker">ANÁLISIS INTERRUMPIDO</p><h2>No se pudo completar el análisis</h2><p>{{ currentAnalysis?.error_message || 'La IA no pudo procesar esta carpeta.' }}</p><button type="button" class="danger-button" @click="triggerAnalysis"><Sparkles class="size-4" /> Intentar nuevamente</button></div>
                </section>

                <section v-else-if="hasResults" key="results" class="results-section">
                    <div class="results-heading"><div><p class="state-kicker">RESULTADO DE INTELIGENCIA ARTIFICIAL</p><h2>Evaluación jurídica de la carpeta</h2></div><span class="result-confirmed"><CheckCircle2 class="size-4" /> Resultado disponible</span></div>

                    <div class="results-layout">
                        <article class="result-card result-card--elements">
                            <div class="result-card__heading"><div><p class="card-kicker">01 · Matriz jurídica</p><h3>Elementos del tipo penal</h3></div><Gavel class="size-6 text-emerald-600" /></div>
                            <div v-if="elements.length" class="element-list">
                                <div v-for="(element, index) in elements" :key="element.element_id || index" class="element-item" :class="{ 'element-item--alert': element.status !== 'ACREDITADO' }">
                                    <div class="element-topline"><div><span class="element-number">{{ String(index + 1).padStart(2, '0') }}</span><strong>Elemento constitutivo #{{ element.element_id }}</strong></div><span class="status-badge" :class="getStatusBadge(element.status)">{{ formatStatus(element.status) }}</span></div>
                                    <p v-if="element.evidence_found" class="evidence"><b>Evidencia:</b> {{ element.evidence_found }}</p>
                                    <p v-if="element.missing_reason" class="missing"><b>Qué falta:</b> {{ element.missing_reason }}</p>
                                    <div class="element-actions"><span>Dictamen ministerial</span><button type="button" :class="{ active: element.status === 'ACREDITADO' }" @click="updateElementStatus(index, 'ACREDITADO')">Acreditar</button><button type="button" :class="{ active: element.status === 'FALTANTE' }" @click="updateElementStatus(index, 'FALTANTE')">Faltante</button></div>
                                </div>
                            </div>
                            <div v-else class="empty-result">La IA no devolvió elementos para evaluar.</div>
                        </article>

                        <div class="results-sidebar">
                            <article class="result-card">
                                <div class="result-card__heading"><div><p class="card-kicker">02 · Imparcialidad</p><h3>Auditoría de objetividad</h3></div><Scale class="size-6 text-sky-600" /></div>
                                <div v-if="currentAnalysis?.objectivity_audit?.bias_warning" class="bias-alert"><AlertCircle class="size-4 shrink-0" />{{ currentAnalysis.objectivity_audit.bias_warning }}</div>
                                <div class="audit-block audit-block--charge"><h4>Elementos de cargo</h4><ul><li v-for="(item, index) in currentAnalysis?.objectivity_audit?.cargo_elements || []" :key="index">{{ item }}</li><li v-if="!currentAnalysis?.objectivity_audit?.cargo_elements?.length" class="muted-item">No identificados</li></ul></div>
                                <div class="audit-block audit-block--defense"><h4>Elementos de descargo</h4><ul><li v-for="(item, index) in currentAnalysis?.objectivity_audit?.descargo_elements || []" :key="index">{{ item }}</li><li v-if="!currentAnalysis?.objectivity_audit?.descargo_elements?.length" class="muted-item">No identificados</li></ul></div>
                            </article>

                            <article class="result-card">
                                <div class="result-card__heading"><div><p class="card-kicker">03 · Plan de investigación</p><h3>Diligencias sugeridas</h3></div><FileText class="size-6 text-amber-600" /></div>
                                <div class="diligence-list"><div v-for="(diligence, index) in diligences" :key="index" class="diligence-item" :class="{ 'diligence-item--off': !diligence.accepted }"><div><span>{{ diligence.legal_basis }}</span><strong>{{ diligence.action }}</strong><small>{{ diligence.purpose }}</small></div><button type="button" @click="toggleDiligence(index)">{{ diligence.accepted ? 'Incluida' : 'Omitida' }}</button></div><p v-if="!diligences.length" class="empty-result">No hay diligencias sugeridas.</p></div>
                                <button type="button" class="save-button" :disabled="isSaving" @click="saveHumanReview">{{ isSaving ? 'Guardando revisión...' : 'Guardar revisión ministerial' }}</button>
                            </article>
                        </div>
                    </div>
                </section>

                <section v-else key="empty" class="state-panel state-panel--empty"><div class="empty-icon"><Sparkles class="size-7" /></div><p class="state-kicker">LISTO PARA COMENZAR</p><h2>Analiza esta carpeta con MP-IA</h2><p>La evaluación utilizará los elementos jurídicos y artículos vigentes asociados al delito.</p></section>
            </Transition>
        </div>
    </main>
</template>

<style scoped>
.analysis-page { min-height: 100vh; background: #f3f7f5; color: #15221f; }
.analysis-shell { width: min(1440px, calc(100% - 48px)); margin: 0 auto; padding: 30px 0 64px; }
.back-link { display: inline-flex; align-items: center; gap: 8px; margin-bottom: 22px; color: #52706a; font-size: 13px; font-weight: 700; transition: color .2s; }
.back-link:hover { color: #087c5a; }
.case-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 24px; padding: 34px 38px; border-radius: 20px; background: #102521; color: white; box-shadow: 0 18px 40px rgba(16, 37, 33, .13); }
.case-header__content h1 { margin: 10px 0 14px; font-size: clamp(26px, 4vw, 42px); line-height: 1.05; letter-spacing: -.03em; }
.eyebrow, .case-header__meta, .section-heading, .result-card__heading, .results-heading, .element-topline, .element-actions, .processing-note, .result-confirmed, .header-status, .primary-button, .danger-button { display: flex; align-items: center; }
.eyebrow { gap: 8px; color: #67e0b4; font-size: 11px; font-weight: 800; letter-spacing: .17em; text-transform: uppercase; }
.case-header__meta { flex-wrap: wrap; gap: 9px 16px; color: #b5cbc5; font-size: 13px; }
.case-chip { padding: 5px 10px; border: 1px solid rgba(123, 221, 185, .28); border-radius: 999px; color: #83e8bf; font-weight: 800; }
.case-header__action { display: flex; flex-direction: column; align-items: flex-end; gap: 18px; }
.header-status, .result-confirmed { gap: 7px; padding: 8px 12px; border: 1px solid rgba(103, 224, 180, .3); border-radius: 999px; color: #8ceac6; font-size: 12px; font-weight: 800; }
.header-status--working { color: #f5d27a; border-color: rgba(245, 210, 122, .35); }
.header-status--danger { color: #ffb0a6; border-color: rgba(255, 176, 166, .35); }
.primary-button, .danger-button, .save-button { justify-content: center; gap: 8px; border: 0; border-radius: 9px; padding: 12px 17px; font-size: 13px; font-weight: 800; cursor: pointer; transition: transform .2s, background .2s; }
.primary-button { background: #65dfb2; color: #102521; }
.primary-button:hover { background: #8beaca; transform: translateY(-1px); }
.primary-button:disabled, .save-button:disabled { cursor: wait; opacity: .6; }
.request-error { display: flex; gap: 12px; margin-top: 18px; padding: 15px 18px; border: 1px solid #f2c2bc; border-radius: 12px; background: #fff2f0; color: #8e3026; }
.request-error strong { font-size: 13px; }.request-error p { margin-top: 3px; font-size: 13px; }
.overview-grid { display: grid; grid-template-columns: minmax(0, 1.55fr) minmax(260px, .75fr); gap: 18px; margin-top: 18px; }
.overview-card, .result-card { border: 1px solid #dce7e2; border-radius: 16px; background: #fff; box-shadow: 0 7px 20px rgba(36, 69, 60, .045); }
.overview-card { padding: 25px; }.section-heading { gap: 12px; }.section-heading p, .card-kicker, .state-kicker { margin: 0 0 4px; color: #168965; font-size: 10px; font-weight: 900; letter-spacing: .16em; text-transform: uppercase; }.section-heading h2, .result-card h3 { margin: 0; font-size: 17px; letter-spacing: -.02em; }.icon-box { display: flex; align-items: center; justify-content: center; width: 42px; height: 42px; border-radius: 11px; }.icon-box--green { background: #e5f8ef; color: #11855d; }.icon-box--slate { background: #edf2f1; color: #526661; }
.narrative-text { margin: 21px 0 0; padding: 16px 18px; border-left: 3px solid #69d9ae; border-radius: 0 9px 9px 0; background: #f7faf9; color: #536761; font-size: 13px; line-height: 1.8; white-space: pre-line; }.fact-list { display: grid; gap: 13px; margin: 22px 0 0; }.fact-list div { display: flex; justify-content: space-between; gap: 12px; padding-bottom: 11px; border-bottom: 1px solid #edf2f0; }.fact-list dt { color: #81948e; font-size: 11px; }.fact-list dd { margin: 0; color: #2d453e; font-size: 12px; font-weight: 800; text-align: right; }
.state-panel { display: flex; flex-direction: column; align-items: center; margin-top: 18px; padding: 58px 24px; border: 1px solid #dce7e2; border-radius: 16px; background: #fff; text-align: center; }.state-panel h2 { margin: 8px 0 8px; font-size: 25px; letter-spacing: -.03em; }.state-panel > p:not(.state-kicker) { max-width: 530px; margin: 0; color: #6b7e78; font-size: 13px; line-height: 1.7; }.state-panel--processing { border-color: #a9e8ce; }.processing-icon { position: relative; display: flex; align-items: center; justify-content: center; width: 76px; height: 76px; margin-bottom: 22px; border-radius: 50%; background: #e5f8ef; color: #138c62; }.processing-icon span { position: absolute; inset: -7px; border: 1px solid #7edeb8; border-radius: 50%; animation: pulse-ring 1.8s infinite; }.progress-track { width: min(430px, 90%); height: 7px; margin-top: 26px; overflow: hidden; border-radius: 99px; background: #e8f0ed; }.progress-bar { width: 42%; height: 100%; border-radius: inherit; background: #29b984; animation: loading 2.4s ease-in-out infinite; }.processing-note { gap: 7px; margin-top: 13px; color: #91a29d; font-size: 11px; font-weight: 700; }.state-panel--failed { flex-direction: row; align-items: flex-start; gap: 17px; padding: 27px; border-color: #f1c4be; background: #fff5f3; text-align: left; }.failed-icon { display: flex; align-items: center; justify-content: center; width: 45px; height: 45px; flex: 0 0 auto; border-radius: 12px; background: #ffe1dc; color: #bc4437; }.failed-copy h2 { margin: 4px 0 6px; color: #6f2922; font-size: 20px; }.failed-copy > p:not(.state-kicker) { margin: 0; color: #984d44; font-size: 13px; line-height: 1.6; }.danger-button { margin-top: 17px; background: #af3f34; color: #fff; }.danger-button:hover { background: #8e3027; }.state-panel--empty { padding: 48px 24px; }.empty-icon { display: flex; align-items: center; justify-content: center; width: 53px; height: 53px; border-radius: 14px; background: #e5f8ef; color: #168965; margin-bottom: 15px; }
.results-section { margin-top: 28px; }.results-heading { justify-content: space-between; gap: 16px; margin-bottom: 17px; }.results-heading h2 { margin: 0; font-size: 25px; letter-spacing: -.03em; }.result-confirmed { border-color: #b8e8d4; background: #effbf6; color: #168965; }.results-layout { display: grid; grid-template-columns: minmax(0, 1.3fr) minmax(320px, .8fr); gap: 18px; }.result-card { padding: 24px; }.result-card__heading { justify-content: space-between; gap: 15px; margin-bottom: 20px; }.card-kicker { color: #83958f; }.element-list, .results-sidebar { display: grid; gap: 12px; }.element-item { padding: 17px; border: 1px solid #dce7e2; border-radius: 12px; }.element-item--alert { border-color: #f1d29a; background: #fffcf5; }.element-topline { justify-content: space-between; gap: 12px; }.element-topline > div { display: flex; align-items: center; gap: 10px; }.element-number { color: #91a49d; font-size: 11px; font-weight: 900; }.element-topline strong { font-size: 13px; }.status-badge { padding: 5px 8px; border: 1px solid; border-radius: 5px; font-size: 10px; font-weight: 900; text-transform: uppercase; }.status-success { border-color: #b7e8d2; background: #effbf5; color: #168965; }.status-warning { border-color: #f0d59e; background: #fff9e9; color: #a36a0a; }.status-danger { border-color: #f2c4be; background: #fff2f0; color: #a53b30; }.status-neutral { border-color: #d9e4e0; background: #f4f7f6; color: #637770; }.evidence, .missing { margin: 13px 0 0; color: #637770; font-size: 12px; line-height: 1.65; }.missing { color: #a36a0a; }.element-actions { gap: 7px; margin-top: 14px; padding-top: 12px; border-top: 1px solid #edf2f0; }.element-actions span { margin-right: auto; color: #9aa9a4; font-size: 10px; font-weight: 700; }.element-actions button, .diligence-item button { border: 0; border-radius: 5px; padding: 6px 9px; background: #edf2f0; color: #64766f; font-size: 10px; font-weight: 800; cursor: pointer; }.element-actions button.active { background: #168965; color: #fff; }.bias-alert { display: flex; gap: 8px; padding: 10px; border: 1px solid #f1d59f; border-radius: 8px; background: #fff9e9; color: #98660e; font-size: 11px; line-height: 1.5; }.audit-block { margin-top: 17px; }.audit-block h4 { margin: 0 0 8px; font-size: 10px; letter-spacing: .1em; text-transform: uppercase; }.audit-block--charge h4 { color: #168965; }.audit-block--defense h4 { color: #13759c; }.audit-block ul { display: grid; gap: 6px; margin: 0; padding: 0; list-style: none; }.audit-block li { padding: 8px 10px; border-radius: 6px; color: #4e625b; font-size: 11px; line-height: 1.5; }.audit-block--charge li { background: #effaf5; }.audit-block--defense li { background: #eef8fc; }.audit-block .muted-item { color: #9aa9a4; background: #f5f7f6; }.diligence-list { display: grid; gap: 9px; }.diligence-item { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; padding: 11px; border: 1px solid #ccebdd; border-radius: 8px; background: #f2fbf7; }.diligence-item--off { border-color: #e0e7e4; background: #f7f9f8; opacity: .62; }.diligence-item span, .diligence-item strong, .diligence-item small { display: block; }.diligence-item span { margin-bottom: 4px; color: #168965; font-size: 9px; font-weight: 900; text-transform: uppercase; }.diligence-item strong { color: #344b43; font-size: 11px; line-height: 1.4; }.diligence-item small { margin-top: 4px; color: #7b8d87; font-size: 10px; line-height: 1.4; }.diligence-item button { flex: 0 0 auto; background: #168965; color: #fff; }.diligence-item--off button { background: #dfe7e3; color: #64766f; }.save-button { width: 100%; margin-top: 18px; background: #102521; color: #fff; }.save-button:hover { background: #1b3d34; }.empty-result { color: #8c9b95; font-size: 12px; }
.fade-enter-active, .fade-leave-active { transition: opacity .3s ease, transform .3s ease; }.fade-enter-from, .fade-leave-to { opacity: 0; transform: translateY(8px); }
@keyframes loading { 0% { transform: translateX(-120%); } 60%, 100% { transform: translateX(260%); } } @keyframes pulse-ring { 0%, 100% { transform: scale(.94); opacity: .7; } 50% { transform: scale(1.08); opacity: .2; } }
@media (max-width: 900px) { .results-layout { grid-template-columns: 1fr; } }
@media (max-width: 680px) { .analysis-shell { width: min(100% - 28px, 600px); padding-top: 20px; }.case-header { flex-direction: column; padding: 25px 22px; }.case-header__action { width: 100%; align-items: stretch; }.header-status { align-self: flex-start; }.overview-grid { grid-template-columns: 1fr; }.overview-card { padding: 20px; }.results-heading { align-items: flex-start; flex-direction: column; }.result-card { padding: 18px; }.state-panel--failed { flex-direction: column; }.element-topline { align-items: flex-start; flex-direction: column; }.element-actions { flex-wrap: wrap; }.element-actions span { width: 100%; }.element-actions span { margin-right: 0; } }
</style>
