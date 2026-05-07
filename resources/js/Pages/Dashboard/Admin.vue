<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

// ─── props transmises par le controller ─────────────────────────────────────
const props = defineProps({
    kpis:               { type: Object,  default: () => ({}) },
    campaignStats:      { type: Array,   default: () => [] },
    employeeStats:      { type: Array,   default: () => [] },
    planningVsRealised: { type: Array,   default: () => [] },
    monthlyTrend:       { type: Array,   default: () => [] },
    period:             { type: Object,  default: () => ({}) },
});

// ─── état local ─────────────────────────────────────────────────────────────
const activeTab     = ref('overview');   // overview | campaigns | employees | planning
const periodStart   = ref(props.period.start ?? '');
const periodEnd     = ref(props.period.end   ?? '');
const searchQuery   = ref('');
const isExporting   = ref(false);
const exportMessage = ref('');

// ─── filtrage employés ───────────────────────────────────────────────────────
const filteredEmployees = computed(() => {
    const q = searchQuery.value.toLowerCase();
    if (!q) return props.employeeStats;
    return props.employeeStats.filter(e =>
        e.name.toLowerCase().includes(q) ||
        e.matricule.toLowerCase().includes(q) ||
        (e.campaign ?? '').toLowerCase().includes(q)
    );
});

// ─── appliquer filtre période ────────────────────────────────────────────────
function applyPeriod() {
    router.get(route('reporting.index'), {
        period_start: periodStart.value,
        period_end:   periodEnd.value,
    }, { preserveState: true, replace: true });
}

// ─── export ─────────────────────────────────────────────────────────────────
async function exportExcel(type) {
    isExporting.value = true;
    exportMessage.value = '';
    try {
        const res = await fetch(
            route('reporting.export') +
            `?type=${type}&period_start=${periodStart.value}&period_end=${periodEnd.value}`
        );
        const json = await res.json();
        downloadAsCSV(json.data, type);
        exportMessage.value = '✔ Export CSV téléchargé';
    } catch {
        exportMessage.value = '✗ Erreur lors de l\'export';
    } finally {
        isExporting.value = false;
        setTimeout(() => exportMessage.value = '', 3000);
    }
}

function downloadAsCSV(data, filename) {
    if (!data.length) return;
    const headers = Object.keys(data[0]);
    const rows    = data.map(row => headers.map(h => `"${row[h] ?? ''}"`).join(','));
    const csv     = [headers.join(','), ...rows].join('\n');
    const blob    = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url     = URL.createObjectURL(blob);
    const link    = document.createElement('a');
    link.href     = url;
    link.download = `${filename}_${periodStart.value}_${periodEnd.value}.csv`;
    link.click();
    URL.revokeObjectURL(url);
}

async function exportPDF(type) {
    isExporting.value = true;
    exportMessage.value = 'Génération PDF…';
    // Utilise l'impression navigateur avec un style optimisé
    window.print();
    isExporting.value = false;
    exportMessage.value = '';
}

// ─── helpers ─────────────────────────────────────────────────────────────────
function gapColor(val) {
    if (val > 5)  return 'text-green-600';
    if (val < -5) return 'text-red-600';
    return 'text-yellow-600';
}
function barWidth(val, max) {
    return max > 0 ? Math.min(100, (val / max) * 100) : 0;
}
const maxCampaignHours = computed(() =>
    Math.max(...props.campaignStats.map(c => c.hours_realised), 1)
);
const maxEmployeeHours = computed(() =>
    Math.max(...props.employeeStats.map(e => e.hours_realised), 1)
);
</script>

<template>
  <Head title="Reporting & Analytics" />
  <AppLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="text-xl font-semibold text-gray-800">📊 Reporting &amp; Analytics</h2>
        <div v-if="exportMessage" class="text-sm font-medium px-3 py-1 rounded-full bg-indigo-50 text-indigo-700">
          {{ exportMessage }}
        </div>
      </div>
    </template>

    <div class="py-8 px-4 max-w-7xl mx-auto space-y-6">

      <!-- ── FILTRES PÉRIODE ──────────────────────────────────────────────── -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 flex flex-wrap gap-4 items-end">
        <div>
          <label class="block text-xs font-semibold text-gray-500 mb-1">Période début</label>
          <input type="date" v-model="periodStart"
            class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 focus:outline-none" />
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-500 mb-1">Période fin</label>
          <input type="date" v-model="periodEnd"
            class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 focus:outline-none" />
        </div>
        <button @click="applyPeriod"
          class="px-5 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition">
          Appliquer
        </button>
        <div class="ml-auto flex gap-2">
          <button @click="exportExcel(activeTab)" :disabled="isExporting"
            class="flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white text-sm font-medium rounded-lg hover:bg-emerald-700 transition disabled:opacity-50">
            <span>⬇ CSV</span>
          </button>
          <button @click="exportPDF(activeTab)" :disabled="isExporting"
            class="flex items-center gap-2 px-4 py-2 bg-rose-600 text-white text-sm font-medium rounded-lg hover:bg-rose-700 transition disabled:opacity-50">
            <span>🖨 PDF</span>
          </button>
        </div>
      </div>

      <!-- ── ONGLETS ──────────────────────────────────────────────────────── -->
      <div class="flex gap-1 bg-gray-100 rounded-xl p-1 w-fit">
        <button v-for="tab in [{id:'overview',label:'Vue globale'},{id:'campaigns',label:'Campagnes'},{id:'employees',label:'Employés'},{id:'planning',label:'Planning vs Réalisé'}]"
          :key="tab.id"
          @click="activeTab = tab.id"
          :class="[activeTab === tab.id
            ? 'bg-white text-indigo-700 shadow-sm font-semibold'
            : 'text-gray-500 hover:text-gray-700',
            'px-4 py-2 text-sm rounded-lg transition-all']">
          {{ tab.label }}
        </button>
      </div>

      <!-- ══════════════════════════════════════════════════════════════════ -->
      <!-- TAB : VUE GLOBALE                                                  -->
      <!-- ══════════════════════════════════════════════════════════════════ -->
      <div v-if="activeTab === 'overview'" class="space-y-6">

        <!-- KPI Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-1">
            <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Employés actifs</p>
            <p class="text-3xl font-bold text-gray-900">{{ kpis.active_employees }}</p>
            <p class="text-xs text-gray-400">/ {{ kpis.total_employees }} total</p>
          </div>
          <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-1">
            <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Taux d'occupation</p>
            <p class="text-3xl font-bold text-indigo-600">{{ kpis.occupancy_rate }}%</p>
            <div class="w-full bg-gray-100 rounded-full h-1.5 mt-2">
              <div class="bg-indigo-500 h-1.5 rounded-full" :style="`width:${kpis.occupancy_rate}%`"></div>
            </div>
          </div>
          <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-1">
            <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Campagnes actives</p>
            <p class="text-3xl font-bold text-emerald-600">{{ kpis.active_campaigns }}</p>
            <p class="text-xs text-gray-400">{{ kpis.assigned_employees }} ressources affectées</p>
          </div>
          <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-1">
            <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Heures ce mois</p>
            <p class="text-3xl font-bold text-gray-900">{{ kpis.current_month_hours }}h</p>
            <p class="text-xs text-amber-500">dont {{ kpis.overtime_hours }}h supp.</p>
          </div>
        </div>

        <!-- Tendance mensuelle (graphe HTML/CSS pur) -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
          <h3 class="text-sm font-bold text-gray-700 mb-4">Tendance des heures — 12 derniers mois</h3>
          <div class="flex items-end gap-2 h-40">
            <template v-for="(m, i) in monthlyTrend" :key="i">
              <div class="flex-1 flex flex-col items-center gap-1 group">
                <span class="text-xs text-gray-400 opacity-0 group-hover:opacity-100 transition font-medium">
                  {{ m.hours }}h
                </span>
                <div class="w-full rounded-t-md bg-indigo-500 hover:bg-indigo-600 transition cursor-pointer"
                  :style="`height:${barWidth(m.hours, Math.max(...monthlyTrend.map(x=>x.hours), 1))}%`"
                  :title="`${m.month} : ${m.hours}h`">
                </div>
                <span class="text-[10px] text-gray-400 truncate w-full text-center">{{ m.month.split(' ')[0] }}</span>
              </div>
            </template>
            <div v-if="!monthlyTrend.length" class="w-full text-center text-gray-300 text-sm py-8">
              Aucune donnée disponible
            </div>
          </div>
        </div>

        <!-- Résumé non affectés -->
        <div class="bg-amber-50 border border-amber-100 rounded-2xl p-5 flex items-center gap-4">
          <div class="w-12 h-12 rounded-full bg-amber-100 flex items-center justify-center text-2xl">⚠️</div>
          <div>
            <p class="font-semibold text-amber-800">{{ kpis.unassigned_employees }} employé(s) non affecté(s)</p>
            <p class="text-sm text-amber-600">Ces ressources actives ne sont rattachées à aucune campagne.</p>
          </div>
          <div class="ml-auto">
            <span v-if="kpis.pending_plannings > 0"
              class="px-3 py-1 bg-amber-200 text-amber-800 text-xs font-bold rounded-full">
              {{ kpis.pending_plannings }} planning(s) en attente
            </span>
          </div>
        </div>
      </div>

      <!-- ══════════════════════════════════════════════════════════════════ -->
      <!-- TAB : CAMPAGNES                                                     -->
      <!-- ══════════════════════════════════════════════════════════════════ -->
      <div v-if="activeTab === 'campaigns'" class="space-y-4">
        <div v-if="!campaignStats.length" class="text-center text-gray-400 py-12">
          Aucune donnée de campagne sur cette période.
        </div>
        <div v-for="camp in campaignStats" :key="camp.id"
          class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4">
          <div class="flex items-start justify-between">
            <div>
              <h3 class="font-bold text-gray-900 text-lg">{{ camp.name }}</h3>
              <div class="flex gap-2 mt-1">
                <span :class="[
                  camp.status === 'active'   ? 'bg-emerald-100 text-emerald-700' :
                  camp.status === 'inactive' ? 'bg-yellow-100 text-yellow-700' :
                                               'bg-gray-100 text-gray-500',
                  'text-xs font-semibold px-2 py-0.5 rounded-full']">
                  {{ camp.status }}
                </span>
                <span class="text-xs text-gray-400">{{ camp.start_date }} → {{ camp.end_date ?? '…' }}</span>
              </div>
            </div>
            <div class="text-right">
              <p class="text-2xl font-bold text-gray-900">{{ camp.total_assigned }}</p>
              <p class="text-xs text-gray-400">ressources affectées</p>
            </div>
          </div>

          <!-- Breakdown hiérarchique -->
          <div class="flex gap-3 flex-wrap">
            <div v-for="(count, pos) in camp.breakdown" :key="pos"
              class="flex items-center gap-2 bg-slate-50 rounded-lg px-3 py-2">
              <span class="text-xs text-gray-500 uppercase font-semibold">{{ pos }}</span>
              <span class="text-sm font-bold text-gray-900">{{ count }}</span>
            </div>
          </div>

          <!-- Barre heures planifiées vs réalisées -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <div class="flex justify-between text-xs text-gray-500 mb-1">
                <span>Heures planifiées</span><span>{{ camp.hours_planned }}h</span>
              </div>
              <div class="w-full bg-gray-100 rounded-full h-2">
                <div class="bg-blue-400 h-2 rounded-full"
                  :style="`width:${barWidth(camp.hours_planned, Math.max(camp.hours_planned, camp.hours_realised, 1))}%`">
                </div>
              </div>
            </div>
            <div>
              <div class="flex justify-between text-xs text-gray-500 mb-1">
                <span>Heures réalisées</span>
                <span :class="gapColor(camp.variance_pct)">
                  {{ camp.hours_realised }}h ({{ camp.variance_pct > 0 ? '+' : '' }}{{ camp.variance_pct }}%)
                </span>
              </div>
              <div class="w-full bg-gray-100 rounded-full h-2">
                <div :class="[camp.variance_pct >= 0 ? 'bg-emerald-400' : 'bg-rose-400', 'h-2 rounded-full transition-all']"
                  :style="`width:${barWidth(camp.hours_realised, Math.max(camp.hours_planned, camp.hours_realised, 1))}%`">
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ══════════════════════════════════════════════════════════════════ -->
      <!-- TAB : EMPLOYÉS                                                      -->
      <!-- ══════════════════════════════════════════════════════════════════ -->
      <div v-if="activeTab === 'employees'" class="space-y-4">
        <div class="flex gap-3">
          <input v-model="searchQuery" type="text" placeholder="Rechercher par nom, matricule, campagne…"
            class="flex-1 border border-gray-200 rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-indigo-400 focus:outline-none" />
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
          <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
              <tr>
                <th class="px-4 py-3 text-left">Employé</th>
                <th class="px-4 py-3 text-left">Poste</th>
                <th class="px-4 py-3 text-left">Campagne</th>
                <th class="px-4 py-3 text-right">H. planifiées</th>
                <th class="px-4 py-3 text-right">H. réalisées</th>
                <th class="px-4 py-3 text-right">Heures supp.</th>
                <th class="px-4 py-3 text-right">Absences</th>
                <th class="px-4 py-3 text-right">Écart</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-for="emp in filteredEmployees" :key="emp.id"
                class="hover:bg-slate-50 transition">
                <td class="px-4 py-3">
                  <div class="font-semibold text-gray-900">{{ emp.name }}</div>
                  <div class="text-xs text-gray-400">{{ emp.matricule }}</div>
                </td>
                <td class="px-4 py-3 text-gray-600">{{ emp.position }}</td>
                <td class="px-4 py-3 text-gray-600">{{ emp.campaign }}</td>
                <td class="px-4 py-3 text-right text-gray-700">{{ emp.hours_planned }}h</td>
                <td class="px-4 py-3 text-right">
                  <div class="font-semibold text-gray-900">{{ emp.hours_realised }}h</div>
                  <div class="w-full bg-gray-100 rounded-full h-1 mt-1">
                    <div class="bg-indigo-400 h-1 rounded-full"
                      :style="`width:${barWidth(emp.hours_realised, maxEmployeeHours)}%`">
                    </div>
                  </div>
                </td>
                <td class="px-4 py-3 text-right" :class="emp.overtime_hours > 0 ? 'text-amber-600 font-semibold' : 'text-gray-400'">
                  {{ emp.overtime_hours > 0 ? emp.overtime_hours + 'h' : '—' }}
                </td>
                <td class="px-4 py-3 text-right" :class="emp.absence_days > 0 ? 'text-rose-600 font-semibold' : 'text-gray-400'">
                  {{ emp.absence_days > 0 ? emp.absence_days + 'j' : '—' }}
                </td>
                <td class="px-4 py-3 text-right font-bold" :class="gapColor(emp.gap_pct)">
                  {{ emp.gap > 0 ? '+' : '' }}{{ emp.gap }}h
                  <div class="text-xs font-normal">{{ emp.gap_pct > 0 ? '+' : '' }}{{ emp.gap_pct }}%</div>
                </td>
              </tr>
              <tr v-if="!filteredEmployees.length">
                <td colspan="8" class="px-4 py-10 text-center text-gray-300">Aucun employé trouvé</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ══════════════════════════════════════════════════════════════════ -->
      <!-- TAB : PLANNING VS RÉALISÉ                                          -->
      <!-- ══════════════════════════════════════════════════════════════════ -->
      <div v-if="activeTab === 'planning'" class="space-y-6">
        <div v-if="!planningVsRealised.length" class="text-center text-gray-400 py-12">
          Aucune donnée de planning sur cette période.
        </div>

        <!-- Tableau récapitulatif -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
          <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
              <tr>
                <th class="px-4 py-3 text-left">Semaine</th>
                <th class="px-4 py-3 text-right">H. planifiées</th>
                <th class="px-4 py-3 text-right">H. réalisées</th>
                <th class="px-4 py-3 text-right">H. supp.</th>
                <th class="px-4 py-3 text-right">Absences</th>
                <th class="px-4 py-3 text-right">Écart</th>
                <th class="px-4 py-3 text-left w-40">Visualisation</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-for="row in planningVsRealised" :key="row.week" class="hover:bg-slate-50 transition">
                <td class="px-4 py-3 font-semibold text-gray-700">Sem. {{ row.week }}</td>
                <td class="px-4 py-3 text-right text-gray-600">{{ row.planned }}h</td>
                <td class="px-4 py-3 text-right font-semibold text-gray-900">{{ row.realised }}h</td>
                <td class="px-4 py-3 text-right" :class="row.overtime > 0 ? 'text-amber-600 font-semibold' : 'text-gray-400'">
                  {{ row.overtime > 0 ? row.overtime + 'h' : '—' }}
                </td>
                <td class="px-4 py-3 text-right" :class="row.absences > 0 ? 'text-rose-600 font-semibold' : 'text-gray-400'">
                  {{ row.absences > 0 ? row.absences : '—' }}
                </td>
                <td class="px-4 py-3 text-right font-bold" :class="gapColor(row.gap)">
                  {{ row.gap > 0 ? '+' : '' }}{{ row.gap }}h
                </td>
                <td class="px-4 py-3">
                  <!-- Mini dual bar -->
                  <div class="flex flex-col gap-0.5">
                    <div class="flex items-center gap-1">
                      <span class="text-[9px] text-gray-400 w-4">P</span>
                      <div class="flex-1 bg-gray-100 rounded-full h-1.5">
                        <div class="bg-blue-400 h-1.5 rounded-full"
                          :style="`width:${barWidth(row.planned, Math.max(row.planned, row.realised, 1))}%`">
                        </div>
                      </div>
                    </div>
                    <div class="flex items-center gap-1">
                      <span class="text-[9px] text-gray-400 w-4">R</span>
                      <div class="flex-1 bg-gray-100 rounded-full h-1.5">
                        <div :class="[row.gap >= 0 ? 'bg-emerald-400' : 'bg-rose-400', 'h-1.5 rounded-full']"
                          :style="`width:${barWidth(row.realised, Math.max(row.planned, row.realised, 1))}%`">
                        </div>
                      </div>
                    </div>
                  </div>
                </td>
              </tr>
            </tbody>
            <tfoot class="bg-gray-50 font-semibold text-sm">
              <tr>
                <td class="px-4 py-3 text-gray-700">Totaux</td>
                <td class="px-4 py-3 text-right text-gray-700">
                  {{ planningVsRealised.reduce((s,r) => s + r.planned,  0).toFixed(1) }}h
                </td>
                <td class="px-4 py-3 text-right text-gray-900">
                  {{ planningVsRealised.reduce((s,r) => s + r.realised, 0).toFixed(1) }}h
                </td>
                <td class="px-4 py-3 text-right text-amber-600">
                  {{ planningVsRealised.reduce((s,r) => s + r.overtime,  0).toFixed(1) }}h
                </td>
                <td class="px-4 py-3 text-right text-rose-600">
                  {{ planningVsRealised.reduce((s,r) => s + r.absences, 0) }}
                </td>
                <td class="px-4 py-3 text-right"
                  :class="gapColor(planningVsRealised.reduce((s,r) => s + r.gap, 0))">
                  {{ planningVsRealised.reduce((s,r) => s + r.gap, 0).toFixed(1) }}h
                </td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        </div>

        <!-- Graphe visuel planning vs réalisé -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
          <h3 class="text-sm font-bold text-gray-700 mb-4">Comparaison planifié / réalisé par semaine</h3>
          <div class="flex items-end gap-3 h-48">
            <template v-for="(row, i) in planningVsRealised" :key="i">
              <div class="flex-1 flex flex-col items-center gap-0.5">
                <div class="w-full flex gap-0.5 items-end" style="height:160px">
                  <div class="flex-1 rounded-t bg-blue-200 hover:bg-blue-300 transition cursor-default"
                    :style="`height:${barWidth(row.planned, Math.max(...planningVsRealised.map(r=>Math.max(r.planned,r.realised)),1))}%`"
                    :title="`Planifié: ${row.planned}h`">
                  </div>
                  <div :class="[row.gap >= 0 ? 'bg-emerald-400 hover:bg-emerald-500' : 'bg-rose-400 hover:bg-rose-500',
                    'flex-1 rounded-t transition cursor-default']"
                    :style="`height:${barWidth(row.realised, Math.max(...planningVsRealised.map(r=>Math.max(r.planned,r.realised)),1))}%`"
                    :title="`Réalisé: ${row.realised}h`">
                  </div>
                </div>
                <span class="text-[10px] text-gray-400">{{ row.week }}</span>
              </div>
            </template>
          </div>
          <div class="flex gap-4 mt-3">
            <div class="flex items-center gap-2"><div class="w-3 h-3 rounded bg-blue-200"></div><span class="text-xs text-gray-500">Planifié</span></div>
            <div class="flex items-center gap-2"><div class="w-3 h-3 rounded bg-emerald-400"></div><span class="text-xs text-gray-500">Réalisé (surplus)</span></div>
            <div class="flex items-center gap-2"><div class="w-3 h-3 rounded bg-rose-400"></div><span class="text-xs text-gray-500">Réalisé (déficit)</span></div>
          </div>
        </div>
      </div>

    </div>
  </AppLayout>
</template>

<style>
@media print {
  nav, aside, header button, .no-print { display: none !important; }
  body { background: white; }
  table { font-size: 11px; }
}
</style>
