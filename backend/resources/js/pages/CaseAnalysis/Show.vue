<script setup lang="js">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    AlertCircle,
    ArrowLeft,
    CheckCircle2,
    FileText,
    Gauge,
    Gavel,
    History,
    LoaderCircle,
    ListChecks,
    Scale,
} from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    analysis: { type: Object, default: null },
    caseData: { type: Object, default: null },
    latestAnalysis: { type: Object, default: null },
});

const currentAnalysis = computed(() => props.latestAnalysis || props.analysis);
const hypothesis = computed(() => currentAnalysis.value?.hypotheses?.[0] || null);
const auditEntries = computed(() => currentAnalysis.value?.audits || []);
const form = useForm({
    expediente: props.caseData?.EXPEDIENTE || '',
    id_carpeta: props.caseData?.ID_CARPETA || '',
});
const isSaving = ref(false);
const elements = ref([]);
const diligences = ref([]);
const evidence = ref([]);
const facts = ref([]);
const reviewNote = ref('');
const requestedMotor = ref(null);
let pollInterval = null;

const motorStatus = computed(() => currentAnalysis.value?.motor_status || {});
const hasProcessingMotors = computed(() => Object.values(motorStatus.value).some((motor) => motor?.status === 'draft'));
const isAnalysisProcessing = computed(() => currentAnalysis.value?.status === 'draft' && Object.keys(motorStatus.value).length === 0);
const hasServerProcessing = computed(() => isAnalysisProcessing.value || hasProcessingMotors.value);
const isProcessing = computed(() => hasServerProcessing.value || requestedMotor.value !== null);
const statusLabel = computed(() => isProcessing.value ? 'Motor en ejecución' : 'Motores disponibles');
const hasObjectiveResults = computed(() => Boolean(currentAnalysis.value?.objectivity_audit) || diligences.value.length > 0);
const hasLegalFoundation = computed(() => facts.value.length > 0 && elements.value.length > 0);
const legalSummary = computed(() => ({
    facts: facts.value.length,
    elements: elements.value.length,
    accredited: elements.value.filter((element) => element.status === 'ACREDITADO').length,
    pending: elements.value.filter((element) => element.status !== 'ACREDITADO').length,
}));

const moduleStatus = (motor, hasResults = false) => {
    if (isMotorProcessing(motor) || (motor === 'matriz' && isAnalysisProcessing.value)) {
        return 'working';
    }

    return hasResults ? 'ready' : 'pending';
};

const moduleStatusLabel = (motor, hasResults = false) => ({
    working: 'En ejecución',
    ready: 'Disponible',
    pending: motor === 'hipotesis' ? 'Pendiente de matriz' : 'Pendiente',
}[moduleStatus(motor, hasResults)]);

const syncReview = () => {
    elements.value = JSON.parse(JSON.stringify(currentAnalysis.value?.elements_status || []));
    diligences.value = (currentAnalysis.value?.suggested_diligences || []).map((item) => ({
        ...item,
        accepted: item.accepted ?? true,
    }));
    evidence.value = JSON.parse(JSON.stringify(currentAnalysis.value?.evidence || []));
    facts.value = JSON.parse(JSON.stringify(currentAnalysis.value?.facts || []));
};

const stopPolling = () => {
    if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
    }
};

const startPolling = () => {
    if (pollInterval || !props.caseData) {
        return;
    }

    pollInterval = setInterval(() => {
        router.reload({
            only: ['latestAnalysis'],
            preserveScroll: true,
            onSuccess: () => {
                if (!hasServerProcessing.value) {
                    requestedMotor.value = null;
                    stopPolling();
                }
            },
        });
    }, 3000);
};

const runMotor = (motor) => {
    if (isProcessing.value) {
        return;
    }

    requestedMotor.value = motor;
    form.clearErrors();
    form.transform(() => ({
        expediente: props.caseData?.EXPEDIENTE || '',
        id_carpeta: props.caseData?.ID_CARPETA || '',
        motor,
    }));
    form.post(route('cases.motor'), {
        preserveScroll: true,
        onSuccess: startPolling,
        onError: () => {
            requestedMotor.value = null;
        },
    });
};

const isMotorProcessing = (motor) => motorStatus.value[motor]?.status === 'draft' || requestedMotor.value === motor;
const isAnotherMotorProcessing = (motor) => isProcessing.value && !isMotorProcessing(motor);

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
        evidence: evidence.value,
        status: 'reviewed',
        review_note: reviewNote.value || null,
    }, {
        onFinish: () => {
            isSaving.value = false;
        },
    });
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

const formatAuditDate = (value) => new Intl.DateTimeFormat('es-MX', {
    dateStyle: 'medium',
    timeStyle: 'short',
}).format(new Date(value));

const formatFactDate = (value) => {
    if (!value) {
        return 'No disponible';
    }

    const date = String(value).slice(0, 10);

    return new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium' }).format(new Date(`${date}T12:00:00`));
};

const hypothesisStatusLabel = computed(() => ({
    completa: 'Hipótesis completa',
    incompleta: 'Hipótesis incompleta',
    con_contradicciones: 'Con contradicciones',
    insuficiente: 'Configuración insuficiente',
}[hypothesis.value?.status] || 'Sin evaluación'));

const hypothesisStatusClass = computed(() => ({
    completa: 'hypothesis-status--complete',
    incompleta: 'hypothesis-status--incomplete',
    con_contradicciones: 'hypothesis-status--danger',
    insuficiente: 'hypothesis-status--neutral',
}[hypothesis.value?.status] || 'hypothesis-status--neutral'));

onMounted(() => {
    syncReview();

    if (isProcessing.value) {
        startPolling();
    }
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
                    <dl class="case-header__details">
                        <div><dt>Estado</dt><dd>{{ caseData?.ESTADO || 'No disponible' }}</dd></div>
                        <div><dt>Unidad</dt><dd>{{ caseData?.UNIDAD || 'No disponible' }}</dd></div>
                        <div><dt>Municipio</dt><dd>{{ caseData?.MUNICIPIO || 'No disponible' }}</dd></div>
                        <div><dt>Fecha del hecho</dt><dd>{{ formatFactDate(currentAnalysis?.fact_date || caseData?.FECHA_HECHO) }}</dd></div>
                    </dl>
                </div>
                <div class="case-header__action">
                    <span class="header-status" :class="{ 'header-status--working': isProcessing }">
                        <LoaderCircle v-if="isProcessing" class="size-4 animate-spin" />
                        <CheckCircle2 v-else class="size-4" />
                        {{ statusLabel }}
                    </span>
                </div>
            </header>

            <p class="decision-disclaimer"><AlertCircle class="size-4 shrink-0" />Este resultado es una herramienta de apoyo y no constituye una determinación ministerial.</p>

            <div v-if="form.errors.analysis || form.errors.expediente || form.errors.motor" class="request-error">
                <AlertCircle class="size-5 shrink-0" />
                <div>
                    <strong>No se pudo ejecutar el motor</strong>
                    <p>{{ form.errors.analysis || form.errors.expediente || form.errors.motor }}</p>
                </div>
            </div>

            <section class="overview-grid">
                <article class="overview-card overview-card--narrative">
                    <div class="section-heading"><span class="icon-box icon-box--green"><FileText class="size-5" /></span><div><p>Información de la carpeta</p><h2>Narrativa de los hechos</h2></div></div>
                    <p class="narrative-text">{{ caseData?.DESCRIPCION_HECHOS || currentAnalysis?.facts_breakdown?.narrative || 'No hay narrativa registrada.' }}</p>
                </article>
            </section>

            <section v-if="caseData" class="institutional-results">
                <header class="institutional-results__header">
                    <div>
                        <p class="state-kicker">REVISIÓN MINISTERIAL</p>
                        <h2>Ruta de análisis de la carpeta</h2>
                        <p>Flujo obligatorio: hechos y matriz, auditoría y plan, hipótesis y registro de evidencia.</p>
                    </div>
                    <span class="result-confirmed"><CheckCircle2 class="size-4" /> {{ isProcessing ? 'Análisis en curso' : 'Vista consolidada' }}</span>
                </header>

                <section class="institutional-module institutional-module--legal" aria-labelledby="module-legal-title">
                    <div class="institutional-module__header">
                        <div class="institutional-module__identity"><span class="institutional-module__number">01</span><div><p class="state-kicker">BASE DEL ANÁLISIS</p><h2 id="module-legal-title">Hechos y matriz jurídica</h2><p>Los hechos son el insumo; la matriz determina qué elementos jurídicos están acreditados.</p></div></div>
                        <button type="button" class="module-action" :disabled="isAnotherMotorProcessing('matriz') || isAnalysisProcessing" @click="runMotor('matriz')"><LoaderCircle v-if="isMotorProcessing('matriz') || isAnalysisProcessing" class="size-4 animate-spin" />{{ isMotorProcessing('matriz') || isAnalysisProcessing ? 'Procesando análisis jurídico' : 'Analizar hechos y matriz' }}</button>
                    </div>
                    <div class="legal-summary-strip"><div><strong>{{ legalSummary.facts }}</strong><span>Hechos identificados</span></div><div><strong>{{ legalSummary.elements }}</strong><span>Elementos evaluados</span></div><div><strong>{{ legalSummary.accredited }}</strong><span>Acreditados</span></div><div><strong>{{ legalSummary.pending }}</strong><span>Pendientes</span></div></div>
                    <div class="legal-foundation-flow">
                        <article class="institutional-panel legal-facts-panel">
                            <div class="institutional-panel__heading"><div><p class="card-kicker">01-A · Insumo de la evaluación</p><h3>Hechos clasificados</h3><small>Fragmentos identificados en la narrativa</small></div><FileText class="size-5 text-sky-600" /></div>
                            <div v-if="moduleStatus('matriz') === 'working'" class="module-loading module-loading--compact"><LoaderCircle class="size-5 animate-spin" /><div><strong>Clasificando la narrativa</strong><span>Identificando manifestaciones, testimonios, evidencia y datos técnicos.</span></div></div>
                            <div v-else-if="facts.length" class="facts-list"><div v-for="(fact, index) in facts" :key="fact.id || index" class="fact-item"><div class="fact-item__heading"><span class="fact-id">{{ fact.id || `F-${String(index + 1).padStart(2, '0')}` }}</span><span class="fact-type">{{ fact.information_type }}</span><span class="fact-relation">{{ fact.procedural_relation }}</span></div><p>{{ fact.content }}</p><small>Origen: {{ fact.source }}</small></div></div>
                            <div v-else class="institutional-empty">Los hechos aparecerán al ejecutar el análisis jurídico.</div>
                        </article>
                        <div class="legal-flow-divider"><span>CONTRASTE JURÍDICO</span><b>↓</b></div>
                        <article class="institutional-panel legal-matrix-panel">
                            <div class="institutional-panel__heading"><div><p class="card-kicker">01-B · Resultado jurídico</p><h3>Matriz de acreditación</h3><small>Cada elemento se relaciona con los hechos disponibles</small></div><Gavel class="size-5 text-emerald-600" /></div>
                            <div v-if="moduleStatus('matriz') === 'working'" class="module-loading"><div class="loading-orbit"><LoaderCircle class="size-6 animate-spin" /></div><div><strong>Construyendo la matriz jurídica</strong><span>Relacionando cada elemento con los hechos disponibles.</span></div><div class="loading-line"><span></span></div></div>
                            <div v-else-if="elements.length" class="element-list"><div v-for="(element, index) in elements" :key="element.element_id || index" class="element-item" :class="{ 'element-item--alert': element.status !== 'ACREDITADO' }"><div class="element-topline"><div><span class="element-number">{{ String(index + 1).padStart(2, '0') }}</span><strong>Elemento #{{ element.element_id }}</strong></div><span class="status-badge" :class="getStatusBadge(element.status)">{{ formatStatus(element.status) }}</span></div><p v-if="element.evidence_found" class="evidence"><b>Hecho relacionado:</b> {{ element.evidence_found }}</p><p v-if="element.supporting_fact_id" class="fact-link">Referencia: {{ element.supporting_fact_id }}</p><p v-if="element.missing_reason" class="missing"><b>Qué falta:</b> {{ element.missing_reason }}</p><div class="element-actions"><span>Dictamen ministerial</span><button type="button" :class="{ active: element.status === 'ACREDITADO' }" @click="updateElementStatus(index, 'ACREDITADO')">Acreditar</button><button type="button" :class="{ active: element.status === 'FALTANTE' }" @click="updateElementStatus(index, 'FALTANTE')">Faltante</button></div></div></div>
                            <div v-else class="institutional-empty">La matriz se habilitará después de analizar los hechos.</div>
                        </article>
                    </div>
                </section>

                <section class="institutional-module institutional-module--objectivity" aria-labelledby="module-objectivity-title">
                    <div class="institutional-module__header">
                        <div class="institutional-module__identity"><span class="institutional-module__number">02</span><div><p class="state-kicker">VALORACIÓN MINISTERIAL</p><h2 id="module-objectivity-title">Auditoría y plan de investigación</h2><p>Una misma revisión identifica sesgos y propone diligencias para los elementos pendientes.</p></div></div>
                        <div class="module-action-wrap"><span v-if="!hasLegalFoundation" class="module-dependency">Requiere hechos y matriz terminados</span><button type="button" class="module-action" :disabled="isAnotherMotorProcessing('objetividad') || isAnalysisProcessing || !hasLegalFoundation" @click="runMotor('objetividad')"><LoaderCircle v-if="isMotorProcessing('objetividad')" class="size-4 animate-spin" />{{ isMotorProcessing('objetividad') ? 'Procesando valoración' : 'Analizar auditoría y plan' }}</button></div>
                    </div>
                    <div class="institutional-module__grid institutional-module__grid--objectivity">
                        <article class="institutional-panel"><div class="institutional-panel__heading"><div><p class="card-kicker">02-A · Auditoría de objetividad</p><h3>Contraste de cargo y descargo</h3></div><Scale class="size-5 text-sky-600" /></div><div v-if="isMotorProcessing('objetividad')" class="module-loading module-loading--compact"><LoaderCircle class="size-5 animate-spin" /><div><strong>Revisando objetividad</strong><span>La auditoría se genera junto con el plan de investigación.</span></div></div><template v-else><div v-if="currentAnalysis?.objectivity_audit?.bias_warning" class="bias-alert"><AlertCircle class="size-4 shrink-0" />{{ currentAnalysis.objectivity_audit.bias_warning }}</div><div class="audit-block audit-block--charge"><h4>Elementos de cargo</h4><ul><li v-for="(item, index) in currentAnalysis?.objectivity_audit?.cargo_elements || []" :key="index">{{ item }}</li><li v-if="!currentAnalysis?.objectivity_audit?.cargo_elements?.length" class="muted-item">No identificados</li></ul></div><div class="audit-block audit-block--defense"><h4>Elementos de descargo</h4><ul><li v-for="(item, index) in currentAnalysis?.objectivity_audit?.descargo_elements || []" :key="index">{{ item }}</li><li v-if="!currentAnalysis?.objectivity_audit?.descargo_elements?.length" class="muted-item">No identificados</li></ul></div></template></article>
                        <article class="institutional-panel"><div class="institutional-panel__heading"><div><p class="card-kicker">02-B · Plan de investigación</p><h3>Diligencias sugeridas</h3></div><FileText class="size-5 text-amber-600" /></div><div v-if="isMotorProcessing('objetividad')" class="module-loading module-loading--compact"><LoaderCircle class="size-5 animate-spin" /><div><strong>Preparando diligencias</strong><span>Seleccionando acciones para los elementos pendientes.</span></div></div><div v-else-if="diligences.length" class="diligence-list"><div v-for="(diligence, index) in diligences" :key="index" class="diligence-item" :class="{ 'diligence-item--off': !diligence.accepted }"><div><span>{{ diligence.legal_basis }}</span><strong>{{ diligence.action }}</strong><small>{{ diligence.purpose }}</small></div><button type="button" @click="toggleDiligence(index)">{{ diligence.accepted ? 'Incluida' : 'Omitida' }}</button></div></div><div v-else class="institutional-empty">Las diligencias aparecerán después de ejecutar la auditoría.</div></article>
                    </div>
                </section>

                <section class="institutional-module institutional-module--hypothesis" aria-labelledby="module-hypothesis-title">
                    <div class="institutional-module__header"><div class="institutional-module__identity"><span class="institutional-module__number">03</span><div><p class="state-kicker">SÍNTESIS JURÍDICA</p><h2 id="module-hypothesis-title">Hipótesis de trabajo</h2><p>Resume la completitud de los elementos requeridos sin realizar otra llamada al modelo.</p></div></div><div class="module-action-wrap"><span v-if="!hasLegalFoundation" class="module-dependency">Requiere hechos y matriz terminados</span><button type="button" class="module-action" :disabled="isAnotherMotorProcessing('hipotesis') || isAnalysisProcessing || !hasLegalFoundation" @click="runMotor('hipotesis')"><LoaderCircle v-if="isMotorProcessing('hipotesis')" class="size-4 animate-spin" />{{ isMotorProcessing('hipotesis') ? 'Calculando hipótesis' : 'Calcular hipótesis' }}</button></div></div>
                    <article class="hypothesis-summary"><div class="hypothesis-summary__heading"><div class="result-card__heading"><span class="icon-box icon-box--green"><Gauge class="size-5" /></span><div><p class="card-kicker">Resultado de completitud</p><h3>¿La carpeta permite concluir?</h3></div></div><span v-if="hypothesis" class="hypothesis-status" :class="hypothesisStatusClass">{{ hypothesisStatusLabel }}</span></div><div v-if="hypothesis" class="hypothesis-summary__body"><div class="hypothesis-score"><strong>{{ Number(hypothesis.completeness_percentage || 0).toFixed(2) }}%</strong><span>elementos requeridos acreditados</span><div class="hypothesis-progress" role="progressbar" :aria-valuenow="hypothesis.completeness_percentage || 0" aria-valuemin="0" aria-valuemax="100"><span :style="{ width: `${Math.min(100, Math.max(0, Number(hypothesis.completeness_percentage || 0)))}%` }"></span></div></div><div class="hypothesis-metrics"><div><strong>{{ hypothesis.accredited_count }}</strong><span>Acreditados</span></div><div><strong>{{ hypothesis.missing_count }}</strong><span>Faltantes</span></div><div><strong>{{ hypothesis.contradictory_count }}</strong><span>Contradictorios</span></div><div><strong>{{ hypothesis.required_elements }}</strong><span>Requeridos</span></div></div><div class="hypothesis-conclusion" :class="{ 'hypothesis-conclusion--warning': !hypothesis.can_conclude }"><ListChecks class="size-5 shrink-0" /><div><strong>{{ hypothesis.can_conclude ? 'Puede pasar a revisión humana' : 'Información insuficiente' }}</strong><p>{{ hypothesis.can_conclude ? 'Los elementos requeridos aparecen acreditados y no hay contradicciones registradas.' : 'Revise los elementos pendientes antes de continuar.' }}</p></div></div></div><div v-else-if="isMotorProcessing('hipotesis')" class="module-loading module-loading--compact"><LoaderCircle class="size-5 animate-spin" /><div><strong>Calculando completitud</strong><span>Comparando los elementos requeridos de la matriz.</span></div></div><div v-else class="institutional-empty">Ejecute la matriz jurídica antes de calcular la hipótesis.</div><div v-if="hypothesis?.missing_required_elements?.length" class="hypothesis-missing"><p><b>Elementos requeridos por revisar</b></p><ul><li v-for="item in hypothesis.missing_required_elements" :key="item.element_id"><span>{{ item.name || `Elemento #${item.element_id}` }}</span><small>{{ item.reason || 'No hay información suficiente.' }}</small></li></ul></div></article>
                </section>

                <section class="institutional-module institutional-module--evidence" aria-labelledby="module-evidence-title">
                    <div class="institutional-module__header"><div class="institutional-module__identity"><span class="institutional-module__number">04</span><div><p class="state-kicker">SOPORTE DOCUMENTAL</p><h2 id="module-evidence-title">Registro de evidencia</h2><p>Se construye con los hechos y elementos ya analizados; no requiere otra llamada al modelo.</p></div></div><span class="deterministic-label">Derivado del análisis jurídico</span></div>
                    <article class="institutional-panel"><div class="institutional-panel__heading"><div><p class="card-kicker">04-A · Registro probatorio</p><h3>Elementos para revisión ministerial</h3></div><FileText class="size-5 text-emerald-600" /></div><div v-if="moduleStatus('matriz') === 'working'" class="module-loading module-loading--compact"><LoaderCircle class="size-5 animate-spin" /><div><strong>Preparando el registro</strong><span>Esperando la relación entre hechos y elementos jurídicos.</span></div></div><div v-else-if="evidence.length" class="evidence-table-wrap"><table class="evidence-table"><thead><tr><th>Tipo y origen</th><th>Fecha</th><th>Hecho relacionado</th><th>Autenticidad</th><th>Valoración</th><th>Relación</th></tr></thead><tbody><tr v-for="item in evidence" :key="item.id"><td><input v-model="item.evidence_type" aria-label="Tipo de evidencia" /><input v-model="item.source" aria-label="Origen de evidencia" /></td><td><input v-model="item.evidence_date" type="date" aria-label="Fecha de evidencia" /></td><td><textarea v-model="item.related_fact" rows="3" aria-label="Hecho relacionado"></textarea></td><td><select v-model="item.authenticity_status" aria-label="Estado de autenticidad"><option value="pendiente">Pendiente</option><option value="autentica">Auténtica</option><option value="no_autentica">No auténtica</option><option value="por_verificar">Por verificar</option></select></td><td><select v-model="item.valuation_status" aria-label="Estado de valoración"><option value="pendiente">Pendiente</option><option value="relevante">Relevante</option><option value="no_relevante">No relevante</option><option value="valorada">Valorada</option></select></td><td><select v-model="item.procedural_relation" aria-label="Relación procesal"><option value="cargo">Cargo</option><option value="descargo">Descargo</option><option value="neutral">Neutral</option></select></td></tr></tbody></table></div><div v-else class="institutional-empty">El registro se generará cuando concluya el análisis jurídico.</div></article>
                </section>

                <section class="final-review institutional-final" aria-labelledby="final-review-title"><div class="final-review__copy"><p class="state-kicker">CIERRE DE LA REVISIÓN</p><h3 id="final-review-title">Consolidar revisión ministerial</h3><p>Verifique los cambios realizados antes de guardar la revisión.</p><label class="review-note"><span>Observación de la revisión (opcional)</span><textarea v-model="reviewNote" rows="2" maxlength="1000" placeholder="Motivo o contexto de los cambios realizados"></textarea></label></div><div class="final-review__action"><span>{{ elements.length }} elementos · {{ evidence.length }} evidencias · {{ diligences.length }} diligencias</span><button type="button" class="save-button" :disabled="isSaving" @click="saveHumanReview"><CheckCircle2 class="size-4" />{{ isSaving ? 'Guardando revisión...' : 'Guardar revisión ministerial' }}</button></div></section>

                <section v-if="auditEntries.length" class="audit-trail" aria-labelledby="audit-trail-title">
                    <div class="audit-trail__heading"><History class="size-5" /><div><p class="state-kicker">TRAZABILIDAD</p><h2 id="audit-trail-title">Bitácora de revisiones</h2></div></div>
                    <ol><li v-for="entry in auditEntries" :key="entry.id"><strong>Revisión ministerial guardada</strong><span>{{ entry.user?.name || 'Usuario institucional' }} · {{ formatAuditDate(entry.created_at) }}</span><small>{{ entry.reason || 'Sin observación adicional.' }}</small></li></ol>
                </section>
            </section>

            <section v-if="caseData" class="results-section legacy-results" aria-hidden="true">
                    <div class="results-heading"><div><p class="state-kicker">REVISIÓN MINISTERIAL</p><h2>Evaluación jurídica de la carpeta</h2></div><span class="result-confirmed"><CheckCircle2 class="size-4" /> Resultado disponible</span></div>

                    <nav class="module-roadmap" aria-label="Flujo de análisis">
                        <div class="roadmap-step roadmap-step--active"><span>01</span><div><strong>Base jurídica</strong><small>Hechos y matriz</small></div></div>
                        <div class="roadmap-connector"></div>
                        <div class="roadmap-step" :class="{ 'roadmap-step--active': hasObjectiveResults, 'roadmap-step--working': isMotorProcessing('objetividad') }"><span>02</span><div><strong>Objetividad</strong><small>Auditoría y diligencias</small></div></div>
                        <div class="roadmap-connector"></div>
                        <div class="roadmap-step" :class="{ 'roadmap-step--active': hypothesis, 'roadmap-step--working': isMotorProcessing('hipotesis') }"><span>03</span><div><strong>Hipótesis</strong><small>Revisión de completitud</small></div></div>
                        <div class="roadmap-connector"></div>
                        <div class="roadmap-step" :class="{ 'roadmap-step--active': evidence.length, 'roadmap-step--working': isAnalysisProcessing }"><span>04</span><div><strong>Registro</strong><small>Evidencia derivada</small></div></div>
                    </nav>

                    <div class="module-heading module-heading--foundation"><div><p class="state-kicker">MÓDULO 01</p><h2>Base jurídica de la carpeta</h2></div><span class="module-heading__note">Una llamada obtiene los hechos y evalúa los elementos</span></div>

                    <section class="module-group module-group--hypothesis">
                        <div class="module-heading"><div><p class="state-kicker">MÓDULO 03</p><h2>Hipótesis de trabajo</h2></div><span class="module-heading__note">Cálculo local sobre la matriz, sin nueva llamada al modelo</span></div>
                        <article v-if="caseData" class="hypothesis-summary" aria-labelledby="hypothesis-summary-title">
                        <div class="hypothesis-summary__heading">
                            <div class="result-card__heading">
                                <span class="icon-box icon-box--green"><Gauge class="size-5" /></span>
                                <div><p class="card-kicker">Motor de hipótesis</p><h3 id="hypothesis-summary-title">Completitud de la hipótesis</h3></div>
                            </div>
                            <div class="motor-heading-actions">
                                <span v-if="isMotorProcessing('hipotesis')" class="motor-running"><LoaderCircle class="size-3 animate-spin" /> Ejecutando</span>
                                <button type="button" class="motor-button" :disabled="isMotorProcessing('hipotesis')" @click="runMotor('hipotesis')">{{ isMotorProcessing('hipotesis') ? 'Procesando...' : 'Analizar' }}</button>
                                <span v-if="hypothesis" class="hypothesis-status" :class="hypothesisStatusClass">{{ hypothesisStatusLabel }}</span>
                            </div>
                        </div>
                        <div v-if="hypothesis" class="hypothesis-summary__body">
                            <div class="hypothesis-score">
                                <strong>{{ Number(hypothesis.completeness_percentage || 0).toFixed(2) }}%</strong>
                                <span>elementos requeridos acreditados</span>
                                <div class="hypothesis-progress" role="progressbar" :aria-valuenow="hypothesis.completeness_percentage || 0" aria-valuemin="0" aria-valuemax="100"><span :style="{ width: `${Math.min(100, Math.max(0, Number(hypothesis.completeness_percentage || 0)))}%` }"></span></div>
                            </div>
                            <div class="hypothesis-metrics">
                                <div><strong>{{ hypothesis.accredited_count }}</strong><span>Acreditados</span></div>
                                <div><strong>{{ hypothesis.missing_count }}</strong><span>Faltantes</span></div>
                                <div><strong>{{ hypothesis.contradictory_count }}</strong><span>Contradictorios</span></div>
                                <div><strong>{{ hypothesis.required_elements }}</strong><span>Requeridos</span></div>
                            </div>
                            <div class="hypothesis-conclusion" :class="{ 'hypothesis-conclusion--warning': !hypothesis.can_conclude }">
                                <ListChecks class="size-5 shrink-0" />
                                <div><strong>{{ hypothesis.can_conclude ? 'La hipótesis puede pasar a revisión humana' : 'No puedo concluir' }}</strong><p>{{ hypothesis.can_conclude ? 'Todos los elementos requeridos aparecen acreditados y no hay contradicciones registradas.' : 'La información disponible no permite una conclusión completa. Revise los elementos pendientes antes de continuar.' }}</p></div>
                            </div>
                        </div>
                        <div v-else-if="moduleStatus('hipotesis') === 'working'" class="module-loading module-loading--compact"><LoaderCircle class="size-5 animate-spin" /><div><strong>Calculando completitud</strong><span>Estamos comparando los elementos requeridos.</span></div></div>
                        <div v-else class="empty-result">Ejecuta la matriz jurídica antes de calcular la hipótesis.</div>
                        <div v-if="hypothesis?.missing_required_elements?.length" class="hypothesis-missing">
                            <p><b>Elementos requeridos por revisar</b></p>
                            <ul><li v-for="item in hypothesis.missing_required_elements" :key="item.element_id"><span>{{ item.name || `Elemento #${item.element_id}` }}</span><small>{{ item.reason || (item.status === 'CONTRADICTORIO' ? 'Presenta información contradictoria.' : 'No hay información suficiente.') }}</small></li></ul>
                        </div>
                        </article>
                    </section>

                    <div class="results-layout">
                        <article class="result-card result-card--elements">
                            <div class="result-card__heading"><div><p class="card-kicker">01 · Análisis jurídico</p><h3>Hechos y elementos del tipo penal</h3></div><div class="motor-heading-actions"><span v-if="isMotorProcessing('matriz') || isAnalysisProcessing" class="motor-running"><LoaderCircle class="size-3 animate-spin" /> {{ moduleStatusLabel('matriz', elements.length) }}</span><button type="button" class="motor-button" :disabled="isMotorProcessing('matriz') || isAnalysisProcessing" @click="runMotor('matriz')">{{ isMotorProcessing('matriz') || isAnalysisProcessing ? 'Procesando hechos y matriz...' : 'Analizar hechos y matriz' }}</button><Gavel class="size-6 text-emerald-600" /></div></div>
                            <div v-if="moduleStatus('matriz') === 'working'" class="module-loading"><div class="loading-orbit"><LoaderCircle class="size-6 animate-spin" /></div><div><strong>Construyendo la matriz jurídica</strong><span>Clasificando hechos y contrastándolos con los elementos del tipo penal.</span></div><div class="loading-line"><span></span></div></div>
                            <div v-else-if="elements.length" class="element-list">
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
                                <div class="result-card__heading"><div><p class="card-kicker">02 · Objetividad e investigación</p><h3>Auditoría y plan de investigación</h3></div><div class="motor-heading-actions"><span v-if="isMotorProcessing('objetividad')" class="motor-running"><LoaderCircle class="size-3 animate-spin" /> {{ moduleStatusLabel('objetividad', hasObjectiveResults) }}</span><button type="button" class="motor-button" :disabled="isMotorProcessing('objetividad')" @click="runMotor('objetividad')">{{ isMotorProcessing('objetividad') ? 'Procesando auditoría y plan...' : 'Analizar auditoría y plan' }}</button><Scale class="size-6 text-sky-600" /></div></div>
                                <div v-if="moduleStatus('objetividad', hasObjectiveResults) === 'working'" class="module-loading module-loading--compact"><LoaderCircle class="size-5 animate-spin" /><div><strong>Revisando objetividad</strong><span>La auditoría y las diligencias se generan en la misma llamada.</span></div></div>
                                <template v-else><div v-if="currentAnalysis?.objectivity_audit?.bias_warning" class="bias-alert"><AlertCircle class="size-4 shrink-0" />{{ currentAnalysis.objectivity_audit.bias_warning }}</div>
                                <div class="audit-block audit-block--charge"><h4>Elementos de cargo</h4><ul><li v-for="(item, index) in currentAnalysis?.objectivity_audit?.cargo_elements || []" :key="index">{{ item }}</li><li v-if="!currentAnalysis?.objectivity_audit?.cargo_elements?.length" class="muted-item">No identificados</li></ul></div>
                                <div class="audit-block audit-block--defense"><h4>Elementos de descargo</h4><ul><li v-for="(item, index) in currentAnalysis?.objectivity_audit?.descargo_elements || []" :key="index">{{ item }}</li><li v-if="!currentAnalysis?.objectivity_audit?.descargo_elements?.length" class="muted-item">No identificados</li></ul></div></template>
                            </article>

                            <article class="result-card">
                                <div class="result-card__heading"><div><p class="card-kicker">Plan de investigación</p><h3>Diligencias sugeridas</h3></div><FileText class="size-6 text-amber-600" /></div>
                                <div v-if="isMotorProcessing('objetividad')" class="module-loading module-loading--compact"><LoaderCircle class="size-5 animate-spin" /><div><strong>Preparando diligencias</strong><span>Seleccionando acciones para los elementos pendientes.</span></div></div><div v-else class="diligence-list"><div v-for="(diligence, index) in diligences" :key="index" class="diligence-item" :class="{ 'diligence-item--off': !diligence.accepted }"><div><span>{{ diligence.legal_basis }}</span><strong>{{ diligence.action }}</strong><small>{{ diligence.purpose }}</small></div><button type="button" @click="toggleDiligence(index)">{{ diligence.accepted ? 'Incluida' : 'Omitida' }}</button></div><p v-if="!diligences.length" class="empty-result">No hay diligencias sugeridas.</p></div>
                            </article>
                        </div>
                    </div>

                    <div class="module-heading module-heading--compact module-heading--facts"><div><p class="state-kicker">Detalle de la base jurídica</p><h2>Hechos utilizados</h2></div><span class="module-heading__note">Los hechos alimentan la matriz y el registro probatorio</span></div>
                    <article class="result-card facts-card">
                        <div class="result-card__heading"><div><p class="card-kicker">Hechos incluidos en el análisis jurídico</p><h3>Información clasificada de la carpeta</h3></div><FileText class="size-6 text-sky-600" /></div>
                        <p class="evidence-intro">La clasificación describe la naturaleza de cada fragmento y no convierte una manifestación en un hecho probado.</p>
                        <div v-if="moduleStatus('matriz') === 'working'" class="module-loading module-loading--compact"><LoaderCircle class="size-5 animate-spin" /><div><strong>Clasificando la narrativa</strong><span>Separando manifestaciones, testimonios, evidencia y datos técnicos.</span></div></div>
                        <div v-else-if="facts.length" class="facts-list">
                            <div v-for="(fact, index) in facts" :key="fact.id || index" class="fact-item">
                                <div class="fact-item__heading"><span class="fact-type">{{ fact.information_type }}</span><span class="fact-relation">{{ fact.procedural_relation }}</span></div>
                                <p>{{ fact.content }}</p>
                                <small>Origen: {{ fact.source }}</small>
                            </div>
                        </div>
                        <div v-else class="empty-result">No hay hechos clasificados en este análisis.</div>
                    </article>

                    <div class="module-heading module-heading--compact module-heading--evidence"><div><p class="state-kicker">MÓDULO 04</p><h2>Registro probatorio</h2></div><span class="module-heading__note">Se construye automáticamente a partir del análisis jurídico</span></div>
                    <article class="result-card evidence-card">
                        <div class="result-card__heading"><div><p class="card-kicker">03 · Registro probatorio</p><h3>Evidencia derivada de los hechos</h3></div><span class="deterministic-label">Se genera con los resultados anteriores</span><FileText class="size-6 text-emerald-600" /></div>
                        <p class="evidence-intro">La evidencia se registra como relevante potencial hasta que exista valoración ministerial.</p>
                        <div v-if="moduleStatus('matriz') === 'working'" class="module-loading module-loading--compact"><LoaderCircle class="size-5 animate-spin" /><div><strong>Preparando el registro probatorio</strong><span>Esperando la relación entre hechos y elementos jurídicos.</span></div></div>
                        <div v-else-if="evidence.length" class="evidence-table-wrap">
                            <table class="evidence-table">
                                <thead><tr><th>Tipo y origen</th><th>Fecha</th><th>Hecho relacionado</th><th>Autenticidad</th><th>Valoración</th><th>Relación</th></tr></thead>
                                <tbody>
                                    <tr v-for="item in evidence" :key="item.id">
                                        <td><input v-model="item.evidence_type" aria-label="Tipo de evidencia" /><input v-model="item.source" aria-label="Origen de evidencia" /></td>
                                        <td><input v-model="item.evidence_date" type="date" aria-label="Fecha de evidencia" /></td>
                                        <td><textarea v-model="item.related_fact" rows="3" aria-label="Hecho relacionado"></textarea></td>
                                        <td><select v-model="item.authenticity_status" aria-label="Estado de autenticidad"><option value="pendiente">Pendiente</option><option value="autentica">Auténtica</option><option value="no_autentica">No auténtica</option><option value="por_verificar">Por verificar</option></select></td>
                                        <td><select v-model="item.valuation_status" aria-label="Estado de valoración"><option value="pendiente">Pendiente</option><option value="relevante">Relevante</option><option value="no_relevante">No relevante</option><option value="valorada">Valorada</option></select></td>
                                        <td><select v-model="item.procedural_relation" aria-label="Relación procesal"><option value="cargo">Cargo</option><option value="descargo">Descargo</option><option value="neutral">Neutral</option></select></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="empty-result">No hay evidencia estructurada registrada en este análisis.</div>
                    </article>

                    <section class="final-review" aria-labelledby="final-review-title">
                        <div class="final-review__copy"><p class="state-kicker">CIERRE DE LA REVISIÓN</p><h3 id="final-review-title">Consolidar revisión ministerial</h3><p>Verifique los cambios realizados en la matriz jurídica, las diligencias y el registro evidencial antes de guardar.</p></div>
                        <div class="final-review__action"><span>{{ elements.length }} elementos · {{ evidence.length }} evidencias · {{ diligences.length }} diligencias</span><button type="button" class="save-button" :disabled="isSaving" @click="saveHumanReview"><CheckCircle2 class="size-4" />{{ isSaving ? 'Guardando revisión...' : 'Guardar revisión ministerial' }}</button></div>
                    </section>
            </section>
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
.eyebrow, .case-header__meta, .section-heading, .result-card__heading, .results-heading, .element-topline, .element-actions, .processing-note, .result-confirmed, .header-status, .primary-button, .danger-button, .save-button { display: flex; align-items: center; }
.eyebrow { gap: 8px; color: #67e0b4; font-size: 11px; font-weight: 800; letter-spacing: .17em; text-transform: uppercase; }
.case-header__meta { flex-wrap: wrap; gap: 9px 16px; color: #b5cbc5; font-size: 13px; }
.case-chip { padding: 5px 10px; border: 1px solid rgba(123, 221, 185, .28); border-radius: 999px; color: #83e8bf; font-weight: 800; }
.case-header__action { display: flex; flex-direction: column; align-items: flex-end; gap: 18px; }
.header-status, .result-confirmed { gap: 7px; padding: 8px 12px; border: 1px solid rgba(103, 224, 180, .3); border-radius: 999px; color: #8ceac6; font-size: 12px; font-weight: 800; }
.header-status--working { color: #f5d27a; border-color: rgba(245, 210, 122, .35); }
.header-status--danger { color: #ffb0a6; border-color: rgba(255, 176, 166, .35); }
.primary-button, .danger-button, .save-button { justify-content: center; gap: 8px; border: 0; border-radius: 9px; padding: 12px 17px; font-size: 13px; font-weight: 800; cursor: pointer; transition: transform .2s, background .2s; }
.save-button svg { flex: 0 0 auto; }
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
.evidence-card { margin-top: 18px; }.evidence-intro { margin: -8px 0 17px; color: #71837d; font-size: 12px; }.evidence-table-wrap { overflow-x: auto; }.evidence-table { width: 100%; min-width: 960px; border-collapse: collapse; }.evidence-table th { padding: 10px 9px; background: #f1f6f3; color: #60736c; font-size: 10px; font-weight: 900; letter-spacing: .08em; text-align: left; text-transform: uppercase; }.evidence-table td { padding: 9px; border-bottom: 1px solid #e8efec; vertical-align: top; }.evidence-table input, .evidence-table textarea, .evidence-table select { width: 100%; border: 1px solid #d5e2dc; border-radius: 5px; background: #fff; color: #29443b; font: inherit; font-size: 11px; }.evidence-table input, .evidence-table select { min-height: 32px; padding: 6px 7px; }.evidence-table textarea { min-width: 210px; padding: 7px; resize: vertical; }.evidence-table td:first-child { display: grid; min-width: 150px; gap: 6px; }.evidence-table input:focus, .evidence-table textarea:focus, .evidence-table select:focus { border-color: #39aa7d; outline: 2px solid #d8f3e7; }
.review-flow { display: flex; align-items: center; gap: 12px; margin: 0 0 18px; padding: 15px 18px; border: 1px solid #dce7e2; border-radius: 12px; background: #fff; }.review-flow__step { display: flex; align-items: center; gap: 9px; min-width: 0; color: #9aa9a4; }.review-flow__step > span { display: grid; width: 28px; height: 28px; flex: 0 0 auto; place-items: center; border: 1px solid #d9e4df; border-radius: 50%; font-size: 10px; font-weight: 900; }.review-flow__step div { display: grid; gap: 2px; }.review-flow__step strong { font-size: 11px; }.review-flow__step small { font-size: 10px; }.review-flow__step--complete { color: #168965; }.review-flow__step--complete > span { border-color: #8eddbb; background: #effbf5; }.review-flow__step--active { color: #263f37; }.review-flow__step--active > span { border-color: #168965; background: #168965; color: #fff; }.review-flow__line { height: 1px; flex: 1; min-width: 18px; background: #dce7e2; }.final-review { display: flex; align-items: center; justify-content: space-between; gap: 24px; margin-top: 18px; padding: 24px 26px; border: 1px solid #9ddfc2; border-radius: 16px; background: #effbf6; }.final-review__copy { max-width: 630px; }.final-review__copy h3 { margin: 0 0 6px; font-size: 20px; }.final-review__copy > p:not(.state-kicker) { margin: 0; color: #607970; font-size: 12px; line-height: 1.6; }.final-review__action { display: grid; min-width: 265px; gap: 10px; }.final-review__action > span { display: flex; align-items: center; justify-content: flex-end; gap: 6px; color: #168965; font-size: 11px; font-weight: 800; }.final-review .save-button { width: 100%; margin-top: 0; background: #0e6d4e; }.final-review .save-button:hover { background: #09573f; }
.final-review { border-color: #c5d4ce; border-radius: 8px; background: #f8faf9; box-shadow: 0 4px 12px rgba(36, 69, 60, .035); }.final-review__copy h3 { color: #1d332c; font-size: 18px; letter-spacing: -.01em; }.final-review__copy > p:not(.state-kicker) { color: #657771; }.final-review__action { min-width: 280px; }.final-review__action > span { justify-content: flex-end; color: #73847e; font-weight: 700; }.final-review .save-button { border-radius: 6px; background: #183f36; }.final-review .save-button:hover { background: #0f3029; }
.review-note { display: grid; gap: 5px; margin-top: 14px; color: #607970; font-size: 11px; font-weight: 700; }.review-note textarea { width: 100%; padding: 8px 9px; border: 1px solid #cfded7; border-radius: 6px; background: #fff; color: #29443b; font: inherit; font-weight: 400; resize: vertical; }.review-note textarea:focus { border-color: #39aa7d; outline: 2px solid #d8f3e7; }
.audit-trail { margin-top: 18px; padding: 22px 25px; border: 1px solid #d7e5df; border-radius: 12px; background: #fff; }.audit-trail__heading { display: flex; align-items: center; gap: 10px; color: #287fa1; }.audit-trail__heading h2 { margin: 1px 0 0; color: #1d332c; font-size: 17px; }.audit-trail__heading .state-kicker { margin: 0; }.audit-trail ol { display: grid; gap: 8px; margin: 16px 0 0; padding: 0; list-style: none; }.audit-trail li { display: grid; gap: 3px; padding: 11px 13px; border-left: 3px solid #8ecdb3; background: #f8fbf9; }.audit-trail strong { color: #29483d; font-size: 12px; }.audit-trail span, .audit-trail small { color: #71837d; font-size: 11px; }
.facts-card { margin-top: 18px; }.facts-list { display: grid; gap: 10px; }.fact-item { padding: 14px; border: 1px solid #dce7e2; border-radius: 9px; background: #fbfcfc; }.fact-item__heading { display: flex; align-items: center; justify-content: space-between; gap: 8px; }.fact-type, .fact-relation { color: #168965; font-size: 10px; font-weight: 900; letter-spacing: .06em; text-transform: uppercase; }.fact-relation { color: #788a84; }.fact-item p { margin: 10px 0 8px; color: #314b42; font-size: 12px; line-height: 1.6; }.fact-item small { color: #91a19c; font-size: 10px; }
.fade-enter-active, .fade-leave-active { transition: opacity .3s ease, transform .3s ease; }.fade-enter-from, .fade-leave-to { opacity: 0; transform: translateY(8px); }
@keyframes loading { 0% { transform: translateX(-120%); } 60%, 100% { transform: translateX(260%); } } @keyframes pulse-ring { 0%, 100% { transform: scale(.94); opacity: .7; } 50% { transform: scale(1.08); opacity: .2; } }
@media (max-width: 900px) { .results-layout { grid-template-columns: 1fr; } }
@media (max-width: 680px) { .analysis-shell { width: min(100% - 28px, 600px); padding-top: 20px; }.case-header { flex-direction: column; padding: 25px 22px; }.case-header__action { width: 100%; align-items: stretch; }.header-status { align-self: flex-start; }.overview-grid { grid-template-columns: 1fr; }.overview-card { padding: 20px; }.results-heading { align-items: flex-start; flex-direction: column; }.result-card { padding: 18px; }.state-panel--failed { flex-direction: column; }.element-topline { align-items: flex-start; flex-direction: column; }.element-actions { flex-wrap: wrap; }.element-actions span { width: 100%; }.element-actions span { margin-right: 0; } }
@media (max-width: 680px) { .review-flow { align-items: stretch; flex-direction: column; gap: 9px; }.review-flow__line { width: 1px; height: 12px; flex: 0 0 auto; margin-left: 14px; }.final-review { align-items: stretch; flex-direction: column; padding: 20px; }.final-review__action { min-width: 0; }.final-review__action > span { justify-content: flex-start; } }
@media (max-width: 680px) { .facts-list { grid-template-columns: 1fr; } }
.hypothesis-summary { margin-bottom: 18px; padding: 22px 24px; border: 1px solid #b9e4d1; border-radius: 16px; background: #f8fdfb; }
.hypothesis-summary__heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 18px; }
.hypothesis-summary__heading .result-card__heading { justify-content: flex-start; margin-bottom: 0; }
.hypothesis-status { padding: 6px 9px; border: 1px solid; border-radius: 6px; font-size: 10px; font-weight: 900; text-transform: uppercase; }
.hypothesis-status--complete { border-color: #a7dfc2; background: #e9f9f0; color: #15764f; }
.hypothesis-status--incomplete { border-color: #f0d59e; background: #fff9e9; color: #a36a0a; }
.hypothesis-status--danger { border-color: #f2c4be; background: #fff2f0; color: #a53b30; }
.hypothesis-status--neutral { border-color: #d9e4e0; background: #f4f7f6; color: #637770; }
.hypothesis-summary__body { display: grid; grid-template-columns: minmax(190px, .9fr) minmax(260px, 1.2fr) minmax(280px, 1.5fr); gap: 20px; align-items: center; margin-top: 22px; }
.hypothesis-score strong { display: block; color: #116d4d; font-size: 32px; letter-spacing: -.04em; }
.hypothesis-score span, .hypothesis-metrics span { display: block; color: #71837d; font-size: 11px; }
.hypothesis-progress { height: 8px; margin-top: 12px; overflow: hidden; border-radius: 99px; background: #dcece5; }
.hypothesis-progress span { display: block; height: 100%; border-radius: inherit; background: #2baa79; transition: width .35s ease; }
.hypothesis-metrics { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
.hypothesis-metrics div { padding: 10px 12px; border: 1px solid #dcebe4; border-radius: 8px; background: #fff; }
.hypothesis-metrics strong { display: block; color: #29483d; font-size: 18px; }
.hypothesis-conclusion { display: flex; gap: 10px; padding: 14px; border: 1px solid #b9e4d1; border-radius: 9px; background: #eaf9f1; color: #16734f; }
.hypothesis-conclusion--warning { border-color: #f0d59e; background: #fff9e9; color: #91600c; }
.hypothesis-conclusion strong { font-size: 12px; }.hypothesis-conclusion p { margin: 4px 0 0; color: #657a72; font-size: 11px; line-height: 1.5; }
.hypothesis-missing { margin-top: 18px; padding-top: 15px; border-top: 1px solid #dcebe4; }.hypothesis-missing p { margin: 0 0 8px; color: #526961; font-size: 11px; }
.hypothesis-missing ul { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; margin: 0; padding: 0; list-style: none; }
.hypothesis-missing li { display: grid; gap: 3px; padding: 9px 11px; border-left: 3px solid #e5b95e; background: #fffdf7; }.hypothesis-missing li span { color: #5a4b2d; font-size: 11px; font-weight: 800; }.hypothesis-missing li small { color: #8c7a55; font-size: 10px; line-height: 1.4; }
@media (max-width: 900px) { .hypothesis-summary__body { grid-template-columns: 1fr 1fr; }.hypothesis-conclusion { grid-column: 1 / -1; } }
@media (max-width: 680px) { .hypothesis-summary { padding: 18px; }.hypothesis-summary__heading { flex-direction: column; }.hypothesis-summary__body { grid-template-columns: 1fr; }.hypothesis-conclusion { grid-column: auto; }.hypothesis-missing ul { grid-template-columns: 1fr; } }
.motor-heading-actions { display: flex; align-items: center; gap: 8px; }
.motor-button { border: 1px solid #b8e8d4; border-radius: 6px; padding: 7px 9px; background: #effbf6; color: #168965; font-size: 10px; font-weight: 800; cursor: pointer; }
.motor-button:hover { background: #d9f5e8; }
.motor-button:disabled { cursor: wait; opacity: .6; }
.motor-running { display: inline-flex; align-items: center; gap: 4px; color: #a36a0a; font-size: 10px; font-weight: 800; }
.deterministic-label { color: #71837d; font-size: 10px; font-weight: 700; }
.module-roadmap { display: flex; align-items: center; gap: 12px; margin-bottom: 28px; padding: 15px 18px; border: 1px solid #dce7e2; border-radius: 14px; background: #fff; }
.roadmap-step { display: flex; align-items: center; gap: 9px; min-width: 0; color: #9aa9a4; }
.roadmap-step > span { display: grid; width: 30px; height: 30px; flex: 0 0 auto; place-items: center; border: 1px solid #d9e4df; border-radius: 50%; font-size: 10px; font-weight: 900; }
.roadmap-step div { display: grid; gap: 2px; }.roadmap-step strong, .roadmap-step small { white-space: nowrap; }.roadmap-step strong { font-size: 11px; }.roadmap-step small { font-size: 10px; }.roadmap-step--active { color: #168965; }.roadmap-step--active > span { border-color: #8eddbb; background: #effbf5; }.roadmap-step--working { color: #a36a0a; }.roadmap-step--working > span { border-color: #f0d59e; background: #fff9e9; }.roadmap-connector { height: 1px; min-width: 18px; flex: 1; background: #dce7e2; }
.module-heading { display: flex; align-items: flex-end; justify-content: space-between; gap: 18px; margin: 28px 0 13px; }.module-heading h2 { margin: 0; color: #1d332c; font-size: 20px; letter-spacing: -.02em; }.module-heading__note { color: #71837d; font-size: 11px; text-align: right; }.module-heading--compact { margin-top: 28px; }
.results-section { display: flex; flex-direction: column; }.results-heading { order: 1; }.module-roadmap { order: 2; }.module-heading--foundation { order: 3; }.results-layout { order: 4; }.module-group--hypothesis { order: 5; }.module-heading--facts { order: 6; }.facts-card { order: 7; }.module-heading--evidence { order: 8; }.evidence-card { order: 9; }.final-review { order: 10; }.module-group { width: 100%; }
.module-loading { display: grid; gap: 13px; min-height: 220px; align-content: center; justify-items: center; padding: 24px; border: 1px dashed #b9dfcf; border-radius: 12px; background: linear-gradient(135deg, #f4fcf8, #fbfdfc); color: #168965; text-align: center; }.module-loading > div:not(.loading-orbit):not(.loading-line) { display: grid; gap: 5px; }.module-loading strong { color: #245448; font-size: 13px; }.module-loading span { color: #71837d; font-size: 11px; line-height: 1.5; }.module-loading--compact { display: flex; min-height: 92px; align-content: initial; justify-items: initial; justify-content: flex-start; padding: 16px; text-align: left; }.loading-orbit { display: grid; width: 48px; height: 48px; place-items: center; border: 1px solid #9edfc3; border-radius: 50%; background: #e8f9f0; }.loading-line { width: min(260px, 90%); height: 5px; overflow: hidden; border-radius: 99px; background: #dcece5; }.loading-line span { display: block; width: 38%; height: 100%; border-radius: inherit; background: #29b984; animation: loading 1.8s ease-in-out infinite; }
@media (max-width: 780px) { .module-roadmap { align-items: stretch; flex-direction: column; gap: 9px; }.roadmap-connector { width: 1px; height: 10px; min-width: 0; margin-left: 14px; }.module-heading { align-items: flex-start; flex-direction: column; gap: 6px; }.module-heading__note { text-align: left; } }
@media (max-width: 680px) { .case-header__details { grid-template-columns: 1fr; gap: 11px; margin-top: 20px; padding-top: 15px; } }
.legacy-results { display: none !important; }
.case-header__details { display: grid; grid-template-columns: repeat(3, minmax(130px, 1fr)); gap: 16px; max-width: 760px; margin: 24px 0 0; padding-top: 18px; border-top: 1px solid rgba(181, 203, 197, .2); }.case-header__details div { display: grid; gap: 5px; }.case-header__details dt { color: #8eaaa1; font-size: 10px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }.case-header__details dd { margin: 0; color: #f1faf6; font-size: 13px; font-weight: 700; }
.overview-grid { grid-template-columns: minmax(0, 1fr); }.overview-card--narrative { width: 100%; }
.institutional-results { display: grid; gap: 22px; margin-top: 28px; }
.institutional-results__header { display: flex; align-items: flex-end; justify-content: space-between; gap: 24px; padding-bottom: 4px; }.institutional-results__header h2 { margin: 0; color: #18372e; font-size: 28px; letter-spacing: -.03em; }.institutional-results__header p:not(.state-kicker) { margin: 7px 0 0; color: #71837d; font-size: 13px; }
.institutional-module { overflow: hidden; border: 1px solid #d7e5df; border-radius: 16px; background: #fff; box-shadow: 0 8px 24px rgba(36, 69, 60, .05); }.institutional-module--legal { border-top: 4px solid #168965; }.institutional-module--objectivity { border-top: 4px solid #287fa1; }.institutional-module--hypothesis { border-top: 4px solid #bc8a28; }.institutional-module--evidence { border-top: 4px solid #4f7d69; }
.institutional-module__header { display: flex; align-items: center; justify-content: space-between; gap: 24px; padding: 25px 27px 22px; background: #fbfdfc; }.institutional-module__identity { display: flex; align-items: flex-start; gap: 15px; }.institutional-module__number { display: grid; width: 42px; height: 42px; flex: 0 0 auto; place-items: center; border-radius: 10px; background: #e8f6ef; color: #147452; font-size: 13px; font-weight: 900; }.institutional-module--objectivity .institutional-module__number { background: #eaf6fa; color: #287fa1; }.institutional-module--hypothesis .institutional-module__number { background: #fff7e7; color: #a4731d; }.institutional-module--evidence .institutional-module__number { background: #edf5f0; color: #4f7d69; }.institutional-module__header h2 { margin: 0; color: #1d332c; font-size: 20px; letter-spacing: -.02em; }.institutional-module__header p:not(.state-kicker) { margin: 5px 0 0; color: #71837d; font-size: 12px; line-height: 1.5; }.module-action { display: inline-flex; align-items: center; justify-content: center; gap: 7px; flex: 0 0 auto; border: 1px solid #168965; border-radius: 7px; padding: 10px 13px; background: #168965; color: #fff; font-size: 11px; font-weight: 800; cursor: pointer; }.module-action:hover { background: #0f704f; }.module-action:disabled { cursor: wait; opacity: .65; }
.institutional-module__grid { display: grid; gap: 1px; padding: 1px; background: #dce7e2; }.institutional-module__grid--legal, .institutional-module__grid--objectivity { grid-template-columns: repeat(2, minmax(0, 1fr)); }.institutional-panel { min-width: 0; padding: 24px; background: #fff; }.institutional-panel__heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 15px; margin-bottom: 17px; }.institutional-panel__heading h3 { margin: 0; color: #1e3930; font-size: 16px; }.institutional-panel__heading .card-kicker { margin-bottom: 5px; }.institutional-empty { display: grid; min-height: 100px; place-items: center; padding: 20px; border: 1px dashed #c9ddd4; border-radius: 9px; background: #f8fbf9; color: #81938c; font-size: 12px; text-align: center; }.institutional-panel .module-loading { min-height: 170px; }
.institutional-module--hypothesis .hypothesis-summary { margin: 0; border: 0; border-radius: 0; background: #fffdf8; }.institutional-module--evidence .institutional-panel { padding-top: 22px; }.institutional-final { margin-top: 0; }
.institutional-module--evidence .module-loading { display: flex; min-height: 100px; border-style: solid; background: #f8fbf9; color: #81938c; }.institutional-module--evidence .module-loading > svg, .institutional-module--evidence .module-loading > div { display: none; }.institutional-module--evidence .module-loading::after { content: 'Pendiente del análisis jurídico'; font-size: 12px; font-weight: 700; }
@media (max-width: 820px) { .institutional-results__header, .institutional-module__header { align-items: flex-start; flex-direction: column; }.module-action { width: 100%; }.institutional-module__grid--legal, .institutional-module__grid--objectivity { grid-template-columns: 1fr; } }
.case-header { align-items: start; padding: 25px 30px; }.case-header__content { display: grid; grid-template-columns: minmax(0, 1fr) auto; column-gap: 24px; }.case-header__content .eyebrow, .case-header__content h1 { grid-column: 1 / -1; }.case-header__content h1 { margin: 8px 0 12px; }.case-header__meta { align-self: center; }.case-header__details { grid-template-columns: repeat(4, minmax(100px, 1fr)); gap: 14px; max-width: none; margin: 0; padding: 0 0 0 20px; border-top: 0; border-left: 1px solid rgba(181, 203, 197, .2); }
@media (max-width: 680px) { .case-header { padding: 23px 22px; }.case-header__content { display: block; width: 100%; }.case-header__details { grid-template-columns: repeat(3, minmax(0, 1fr)); margin-top: 16px; padding: 14px 0 0; border-top: 1px solid rgba(181, 203, 197, .2); border-left: 0; }.case-header__action { width: 100%; margin-top: 18px; align-items: flex-start; } }
@media (max-width: 520px) { .case-header__details { grid-template-columns: 1fr; gap: 9px; } }
.case-header__content { min-width: 0; }.case-header__action { padding-top: 1px; }.case-header__details { padding: 12px 16px; border: 1px solid rgba(123, 221, 185, .18); border-radius: 10px; background: rgba(255, 255, 255, .045); }.case-header__details dd { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.overview-card--narrative { border-top: 3px solid #69d9ae; }.narrative-text { max-height: 220px; overflow-y: auto; scrollbar-color: #b8dacc transparent; }
.institutional-module__header { background: linear-gradient(90deg, #fbfdfc 0%, #f5faf7 100%); }.institutional-module__header h2 { font-size: 21px; }.institutional-module__number { box-shadow: 0 0 0 5px rgba(232, 246, 239, .75); }.module-action { min-height: 38px; box-shadow: 0 4px 10px rgba(22, 137, 101, .16); }.module-action:focus-visible, .motor-button:focus-visible, .save-button:focus-visible { outline: 3px solid rgba(103, 224, 180, .35); outline-offset: 2px; }
.module-action-wrap { display: grid; justify-items: end; gap: 7px; }.module-dependency { color: #a4731d; font-size: 10px; font-weight: 700; text-align: right; }.module-action:disabled { box-shadow: none; }
.analysis-page { background: linear-gradient(180deg, #f7faf8 0%, #edf3f0 100%); font-family: var(--font-sans), 'Instrument Sans', sans-serif; }.analysis-shell { width: min(1320px, calc(100% - 56px)); padding-top: 24px; }.case-header { border: 1px solid rgba(135, 190, 169, .18); border-radius: 16px; background: linear-gradient(118deg, #102a24 0%, #173b31 68%, #1d4a3b 100%); box-shadow: 0 20px 46px rgba(22, 57, 47, .16); }.case-header__content h1 { font-size: clamp(28px, 3.4vw, 40px); letter-spacing: -.02em; }.header-status { background: rgba(255, 255, 255, .06); backdrop-filter: blur(8px); }
.overview-card, .institutional-module, .result-card { border-radius: 12px; box-shadow: 0 10px 28px rgba(29, 61, 51, .055); }.overview-card { padding: 27px 29px; }.narrative-text { border-left-width: 2px; background: #f8fbf9; color: #40584f; font-size: 13px; }
.decision-disclaimer { display: flex; align-items: flex-start; gap: 8px; margin: 16px 0 0; padding: 11px 13px; border: 1px solid #e7d7af; border-radius: 8px; background: #fffaf0; color: #7a5a18; font-size: 12px; line-height: 1.5; }
.institutional-results { gap: 26px; }.institutional-results__header { padding: 3px 2px 7px; }.institutional-results__header h2 { color: #173a30; font-size: 30px; font-weight: 700; }.institutional-results__header p:not(.state-kicker) { color: #6f817a; }.institutional-module { border-radius: 13px; box-shadow: 0 12px 32px rgba(29, 61, 51, .06); }.institutional-module__header { padding: 22px 25px; }.institutional-module__identity { gap: 14px; }.institutional-module__number { width: 38px; height: 38px; border-radius: 9px; box-shadow: none; font-size: 11px; }.institutional-module__header h2 { font-size: 19px; }.institutional-module__header p:not(.state-kicker) { max-width: 650px; }.institutional-panel { padding: 26px; }.institutional-panel__heading { padding-bottom: 13px; border-bottom: 1px solid #edf2ef; }.institutional-panel__heading h3 { font-size: 15px; }.module-action { border-radius: 6px; padding: 9px 13px; font-size: 10px; letter-spacing: .02em; }
.fact-item, .element-item, .diligence-item { border-radius: 7px; }.fact-item { background: #fcfdfc; }.element-item { background: #fff; }.institutional-empty { background: #fbfcfb; }.final-review { border-radius: 12px; box-shadow: 0 10px 26px rgba(29, 61, 51, .045); }
.module-loading { position: relative; overflow: hidden; isolation: isolate; border-color: #c8e4d6; background: linear-gradient(135deg, #f8fcfa 0%, #eef8f3 50%, #f8fcfa 100%); box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .8); }.module-loading::before { position: absolute; z-index: -1; top: 0; bottom: 0; left: -35%; width: 35%; background: linear-gradient(90deg, transparent, rgba(255, 255, 255, .8), transparent); content: ''; animation: loading-sheen 2.4s ease-in-out infinite; }.module-loading--compact { min-height: 108px; gap: 13px; border-style: solid; border-color: #d5e7df; background: #f8fbf9; }.module-loading--compact > svg { width: 34px; height: 34px; flex: 0 0 auto; padding: 8px; border: 1px solid #a9d8c2; border-radius: 50%; background: #e9f7ef; color: #168965; }.module-loading strong { letter-spacing: -.01em; }.module-loading span { max-width: 410px; }.module-loading--compact > div { position: relative; }.module-loading--compact > div::after { display: block; width: 54px; height: 3px; margin-top: 9px; border-radius: 99px; background: #b8dfcc; content: ''; animation: loading-pulse 1.5s ease-in-out infinite; }.loading-orbit { position: relative; width: 58px; height: 58px; border: 1px solid #a9d8c2; background: #e9f7ef; box-shadow: 0 0 0 7px rgba(233, 247, 239, .65); }.loading-orbit::after { position: absolute; inset: -8px; border: 1px solid rgba(41, 185, 132, .25); border-radius: 50%; content: ''; animation: pulse-ring 1.8s ease-in-out infinite; }.loading-line { width: min(300px, 85%); height: 6px; background: #dcece5; box-shadow: inset 0 0 0 1px rgba(185, 223, 207, .45); }.loading-line span { width: 28%; background: linear-gradient(90deg, #168965, #69d9ae); box-shadow: 0 0 9px rgba(41, 185, 132, .35); }
.institutional-module--objectivity .module-loading { border-color: #c8dfe7; background: linear-gradient(135deg, #f8fcfd 0%, #eef8fb 50%, #f8fcfd 100%); }.institutional-module--objectivity .module-loading--compact > svg { border-color: #afd3df; background: #eaf6fa; color: #287fa1; }.institutional-module--hypothesis .module-loading { border-color: #e7d7af; background: linear-gradient(135deg, #fffdf8 0%, #fff8e9 50%, #fffdf8 100%); }.institutional-module--hypothesis .module-loading--compact > svg { border-color: #e5ca8d; background: #fff7e7; color: #a4731d; }
.institutional-module--evidence .module-loading { min-height: 108px; border-style: solid; background: #f8fbf9; }.institutional-module--evidence .module-loading::before { display: none; }.institutional-module--evidence .module-loading::after { display: inline-flex; align-items: center; min-height: 34px; padding: 0 14px; border: 1px solid #d5e7df; border-radius: 7px; background: #fff; color: #71837d; font-size: 11px; font-weight: 800; content: 'Pendiente del análisis jurídico'; }
@keyframes loading-sheen { 0% { transform: translateX(0); } 65%, 100% { transform: translateX(480%); } } @keyframes loading-pulse { 0%, 100% { width: 34px; opacity: .45; } 50% { width: 76px; opacity: 1; } }
.legal-summary-strip { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1px; padding: 1px; background: #dce7e2; }.legal-summary-strip div { display: grid; gap: 3px; padding: 14px 18px; background: #f8fbf9; }.legal-summary-strip strong { color: #173a30; font-size: 22px; line-height: 1; }.legal-summary-strip span { color: #71837d; font-size: 10px; font-weight: 800; text-transform: uppercase; }.legal-foundation-flow { display: grid; grid-template-columns: minmax(0, .9fr) 58px minmax(0, 1.3fr); align-items: stretch; gap: 0; padding: 1px; background: #dce7e2; }.legal-foundation-flow .institutional-panel { min-height: 100%; }.legal-facts-panel { background: #fafdff; }.legal-matrix-panel { background: #fff; }.institutional-panel__heading small { display: block; margin-top: 5px; color: #8a9a94; font-size: 10px; line-height: 1.4; }.legal-flow-divider { display: grid; place-items: center; align-content: center; gap: 9px; background: #f4f8f6; color: #7a9087; }.legal-flow-divider span { writing-mode: vertical-rl; transform: rotate(180deg); font-size: 9px; font-weight: 900; letter-spacing: .13em; }.legal-flow-divider b { display: grid; width: 25px; height: 25px; place-items: center; border: 1px solid #b9d9ca; border-radius: 50%; background: #eaf7ef; color: #168965; font-size: 14px; }.fact-item__heading { flex-wrap: wrap; }.fact-id { color: #168965; font-size: 10px; font-weight: 900; letter-spacing: .06em; }.fact-link { display: inline-block; margin: 9px 0 0; padding: 3px 6px; border-radius: 4px; background: #edf8f2; color: #168965; font-size: 10px; font-weight: 800; }.legal-matrix-panel .element-list { max-height: 620px; padding-right: 4px; }.legal-matrix-panel .element-item { padding: 14px; }.legal-matrix-panel .element-actions { margin-top: 11px; }
@media (max-width: 900px) { .legal-foundation-flow { grid-template-columns: 1fr; }.legal-flow-divider { min-height: 42px; grid-auto-flow: column; gap: 8px; }.legal-flow-divider span { writing-mode: initial; transform: none; }.legal-flow-divider b { transform: rotate(90deg); } }
@media (max-width: 560px) { .legal-summary-strip { grid-template-columns: repeat(2, 1fr); }.legal-summary-strip div { padding: 12px 14px; }.legal-summary-strip strong { font-size: 19px; } }
</style>
