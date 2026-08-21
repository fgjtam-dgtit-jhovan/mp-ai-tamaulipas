<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';

const props = defineProps({
  analysis: {
    type: Object,
    required: true
  }
});

// Reactividad para decisiones del usuario (Human-in-the-Loop)
const elements = ref(JSON.parse(JSON.stringify(props.analysis.elements_status || [])));
const diligences = ref(
  (props.analysis.suggested_diligences || []).map(d => ({ ...d, accepted: d.accepted ?? true }))
);
const isSaving = ref(false);

const updateElementStatus = (index, newStatus) => {
  elements.value[index].status = newStatus;
};

const toggleDiligence = (index) => {
  diligences.value[index].accepted = !diligences.value[index].accepted;
};

const saveHumanReview = () => {
  isSaving.value = true;
  router.put(`/case-analysis/${props.analysis.id}`, {
    elements_status: elements.value,
    suggested_diligences: diligences.value,
    status: 'reviewed'
  }, {
    onFinish: () => { isSaving.value = false; }
  });
};

const getStatusBadge = (status) => {
  switch (status) {
    case 'ACREDITADO':
      return 'bg-emerald-100 text-emerald-800 border-emerald-300';
    case 'FALTANTE':
      return 'bg-amber-100 text-amber-800 border-amber-300';
    case 'CONTRADICTORIO':
      return 'bg-rose-100 text-rose-800 border-rose-300';
    default:
      return 'bg-slate-100 text-slate-800 border-slate-300';
  }
};
</script>

<template>
  <Head title="Análisis de Carpeta - MP-IA" />

  <div class="min-h-screen bg-slate-50 p-6 font-sans">
    <div class="max-w-7xl mx-auto space-y-6">

      <!-- Encabezado del Expediente -->
      <header class="bg-white p-6 rounded-xl shadow-sm border border-slate-200 flex justify-between items-center">
        <div>
          <div class="flex items-center space-x-3">
            <span class="px-3 py-1 bg-indigo-50 text-indigo-700 text-xs font-semibold rounded-full uppercase tracking-wider">
              Carpeta de Investigación
            </span>
            <span class="text-xs text-slate-400">ID Local: #{{ analysis.id }}</span>
          </div>
          <h1 class="text-2xl font-bold text-slate-900 mt-1">
            {{ analysis.external_case_id }}
          </h1>
          <p class="text-sm text-slate-500 mt-0.5">
            Clave de Delito: <span class="font-medium text-slate-700">{{ analysis.external_offense_id }}</span>
          </p>
        </div>

        <div class="flex items-center space-x-3">
          <span
            class="px-3 py-1.5 text-sm font-medium rounded-lg border uppercase"
            :class="analysis.status === 'draft' ? 'bg-slate-100 border-slate-300 text-slate-700' : 'bg-emerald-50 border-emerald-200 text-emerald-700'"
          >
            Estado: {{ analysis.status }}
          </span>
        </div>
      </header>

      <!-- Resumen Narrativo de Hechos -->
      <section class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
        <h2 class="text-lg font-semibold text-slate-800 mb-2">Narrativa de los Hechos</h2>
        <p class="text-sm text-slate-600 leading-relaxed bg-slate-50 p-4 rounded-lg border border-slate-100">
          {{ analysis.facts_breakdown?.narrative || 'Sin narrativa registrada.' }}
        </p>
      </section>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Columna Izquierda (2 Cols): Matriz de Tipicidad & Diligencias -->
        <div class="lg:col-span-2 space-y-6">

          <!-- Matriz de Tipicidad (Semáforos e Interacción) -->
          <section class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
            <div class="flex justify-between items-center mb-4">
              <h2 class="text-lg font-semibold text-slate-800">
                Matriz de Elementos del Tipo Penal
              </h2>
              <span class="text-xs text-slate-500">Evaluación del MP</span>
            </div>

            <div class="space-y-4">
              <div
                v-for="(elem, idx) in elements"
                :key="elem.element_id"
                class="p-4 rounded-lg border bg-white space-y-3"
                :class="elem.status === 'ACREDITADO' ? 'border-slate-200' : 'border-amber-200 bg-amber-50/20'"
              >
                <div class="flex justify-between items-start">
                  <span class="text-sm font-bold text-slate-800">
                    Elemento Constitutivo #{{ elem.element_id }}
                  </span>
                  <span class="px-2.5 py-0.5 text-xs font-bold rounded-md border uppercase" :class="getStatusBadge(elem.status)">
                    {{ elem.status }}
                  </span>
                </div>

                <p v-if="elem.evidence_found" class="text-xs text-slate-600">
                  <strong class="text-slate-700">Evidencia:</strong> {{ elem.evidence_found }}
                </p>

                <p v-if="elem.missing_reason" class="text-xs text-amber-700">
                  <strong>Observación / Faltante:</strong> {{ elem.missing_reason }}
                </p>

                <!-- Botonera de Ajuste Ministerial -->
                <div class="flex items-center space-x-2 pt-2 border-t border-slate-100">
                  <span class="text-xs text-slate-500 font-medium">Dictamen MP:</span>
                  <button
                    @click="updateElementStatus(idx, 'ACREDITADO')"
                    :class="elem.status === 'ACREDITADO' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                    class="px-2 py-1 text-xs rounded transition font-medium"
                  >
                    Acreditar
                  </button>
                  <button
                    @click="updateElementStatus(idx, 'FALTANTE')"
                    :class="elem.status === 'FALTANTE' ? 'bg-amber-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                    class="px-2 py-1 text-xs rounded transition font-medium"
                  >
                    Faltante
                  </button>
                </div>
              </div>
            </div>
          </section>

          <!-- Diligencias Sugeridas e Selección -->
          <section class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
            <h2 class="text-lg font-semibold text-slate-800 mb-4">
              Diligencias e Investigaciones Recomendadas
            </h2>

            <div class="space-y-3">
              <div
                v-for="(dil, idx) in diligences"
                :key="idx"
                class="p-4 bg-indigo-50/50 rounded-lg border flex justify-between items-center transition"
                :class="dil.accepted ? 'border-indigo-200' : 'border-slate-200 opacity-60'"
              >
                <div class="space-y-1">
                  <span class="text-xs font-semibold text-indigo-600 uppercase">
                    Fundamento: {{ dil.legal_basis }}
                  </span>
                  <h3 class="text-sm font-bold text-slate-800">{{ dil.action }}</h3>
                  <p class="text-xs text-slate-600">Finalidad: {{ dil.purpose }}</p>
                </div>

                <button
                  @click="toggleDiligence(idx)"
                  :class="dil.accepted ? 'bg-indigo-600 text-white hover:bg-indigo-700' : 'bg-slate-200 text-slate-600 hover:bg-slate-300'"
                  class="px-3 py-1.5 text-xs font-semibold rounded-lg transition"
                >
                  {{ dil.accepted ? 'Aprobada' : 'Descartada' }}
                </button>
              </div>
            </div>

            <!-- Botón Principal para Guardar la Revisión del MP -->
            <div class="mt-6 flex justify-end">
              <button
                @click="saveHumanReview"
                :disabled="isSaving"
                class="px-5 py-2.5 bg-slate-900 text-white text-sm font-semibold rounded-lg shadow hover:bg-slate-800 transition disabled:opacity-50"
              >
                {{ isSaving ? 'Guardando...' : 'Confirmar y Guardar Revisión' }}
              </button>
            </div>
          </section>

        </div>

        <!-- Columna Derecha (1 Col): Auditoría de Objetividad (Cargo vs Descargo) -->
        <div class="space-y-6">
          <section class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
            <h2 class="text-lg font-semibold text-slate-800 mb-4">
              Auditoría de Objetividad
            </h2>

            <!-- Alerta de Sesgo -->
            <div v-if="analysis.objectivity_audit?.bias_warning" class="mb-4 p-3.5 bg-amber-50 border border-amber-200 rounded-lg">
              <p class="text-xs text-amber-800 leading-relaxed font-medium">
                ⚠️ {{ analysis.objectivity_audit.bias_warning }}
              </p>
            </div>

            <!-- Datos de Cargo -->
            <div class="mb-4">
              <h3 class="text-xs font-bold text-emerald-700 uppercase tracking-wider mb-2">
                Elementos de Cargo (Incriminatorios)
              </h3>
              <ul class="space-y-1.5">
                <li
                  v-for="(item, idx) in analysis.objectivity_audit?.cargo_elements"
                  :key="idx"
                  class="text-xs text-slate-700 bg-emerald-50/60 p-2.5 rounded border border-emerald-100"
                >
                  • {{ item }}
                </li>
              </ul>
            </div>

            <!-- Datos de Descargo -->
            <div>
              <h3 class="text-xs font-bold text-sky-700 uppercase tracking-wider mb-2">
                Elementos de Descargo (Defensa / Eximentes)
              </h3>
              <ul class="space-y-1.5">
                <li
                  v-for="(item, idx) in analysis.objectivity_audit?.descargo_elements"
                  :key="idx"
                  class="text-xs text-slate-700 bg-sky-50/60 p-2.5 rounded border border-sky-100"
                >
                  • {{ item }}
                </li>
              </ul>
            </div>
          </section>
        </div>

      </div>

    </div>
  </div>
</template>
