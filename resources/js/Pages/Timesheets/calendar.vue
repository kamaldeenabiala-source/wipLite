<script setup>
import { computed, ref } from "vue";
import { Head, usePage, router } from "@inertiajs/vue3";
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import Dialog from "primevue/dialog";
import Button from 'primevue/button';
import { useToast } from "primevue/usetoast";
import Toast from "primevue/toast";
import TimesCard from "./TimesCard.vue";
import ConfirmDialog from "./ConfirmDialog.vue";
import AppLayout from "@/Layouts/AppLayout.vue";

const toast = useToast();
const props = defineProps({ calendar: Array });

// --- ÉTATS RÉACTIFS ---
 // Stocke les employés cochés dans le tableau
const selectedEmployees = ref([]);
      // Nombre de lignes affichées par page
const rows = ref(5);
   // Contrôle l'ouverture du formulaire de saisie
const displayModal = ref(false)
 // Contrôle l'ouverture du dialogue de clôture
const showConfirmDialog = ref(false);
// Données envoyées au composant TimesCard
const selectedData = ref(null);    
// Données envoyées au composant ConfirmDialog
const selectedForSubmit = ref(null); 

// --- GESTION DES UTILISATEURS ---
const user = computed(() => usePage().props.auth.user);
// Normalise le rôle en minuscules (ex: 'ADMIN' -> 'admin') pour faciliter les comparaisons
const role = computed(() => user.value?.role?.name?.toLowerCase() || 'tc');

/**
 * GÉNÉRATION DE LA PÉRIODE HEBDOMADAIRE
 * Calcule dynamiquement chaque jour entre la date de début et de fin du calendrier.
 */
const periodDates = computed(() => {
    if (!props.calendar?.length) return [];
    const start = new Date(props.calendar[0].period_start);
    const end = new Date(props.calendar[0].period_end);
    const dates = [];
    let current = new Date(start);

    while (current <= end) {
        // Ajoute la date formatée YYYY-MM-DD au tableau
        dates.push(new Date(current).toISOString().split("T")[0]);
        // Passe au jour suivant
        current.setDate(current.getDate() + 1);
    }
    return dates;
});

/**
 * FORMATAGE DES EN-TÊTES
 * Transforme une date technique en format lisible (ex: "lun. 20").
 */
const formatHeader = (d) => new Intl.DateTimeFormat("fr-FR", { weekday: "short", day: "numeric" }).format(new Date(d));

/**
 * RÉCUPÉRATION D'UNE ENTRÉE DE POINTAGE
 * Vérifie si un employé possède des heures enregistrées pour une date précise.
 */
const getEntry = (entries, date) => {
    // On compare le début de la string date pour éviter les problèmes de format ISO
    const found = entries?.find(e => e.date.startsWith(date));
    return found ? { ...found, is_empty: false } : { is_empty: true };
};

/**
 * LOGIQUE VISUELLE DES CELLULES
 * Définit la couleur de fond de la case en fonction du statut et du remplissage.
 */
const getCellClass = (timesheet, date) => {
    const entry = getEntry(timesheet.entries, date);
    
    // 1. Si aucune donnée : Rouge (Absent)
    if (entry.is_empty) return 'bg-red-50 border-red-200 text-red-700'; 
    // 2. Si heures saisies < heures prévues : Orange (Incomplet)
    if (parseFloat(entry.total_hours) < parseFloat(entry.planned_hours)) {
        return 'bg-orange-50 border-orange-200 text-orange-700';
    }
    // 3. Si la semaine est validée : Émeraude (Verrouillé)
    if (timesheet.status === 'soumis') return 'bg-emerald-100 border-emerald-300 text-emerald-800 font-bold';
    
    // 4. Par défaut : Vert (Conforme)
    return 'bg-green-50 border-green-200 text-green-800';
};

/**
 * OUVERTURE DU FORMULAIRE INDIVIDUEL
 * Prépare les données de l'employé et du jour sélectionné avant d'ouvrir le modal.
 */
const openTimeCard = (timesheet, date) => {
    selectedData.value = {
        timesheet_id: timesheet.id,
        status: timesheet.status,
        role: role.value,
        employee_name: `${timesheet.employee.first_name} ${timesheet.employee.last_name}`,
        date: date,
        // On passe l'entrée existante si elle existe, sinon null
        entry: timesheet.entries.find(e => e.date.startsWith(date)) || null
    };
    displayModal.value = true;
};

/**
 * OUVERTURE DU DIALOGUE DE CLÔTURE
 * Prépare l'ID de la feuille de temps pour la soumission finale.
 */
const openConfirm = (timesheet) => {
    selectedForSubmit.value = { 
        id: timesheet.id, 
        name: `${timesheet.employee.first_name} ${timesheet.employee.last_name}` 
    };
    showConfirmDialog.value = true;
};

/**
 * GESTION DE LA SAISIE GROUPÉE (BULK)
 * Vérifie l'éligibilité de la sélection avant d'ouvrir le mode multiple.
 */
const openBulkEdit = () => {
    if (selectedEmployees.value.length === 0) return;

    // Sécurité : Vérifie si un employé de la sélection est déjà verrouillé (soumis)
    const hasSubmitted = selectedEmployees.value.some(emp => emp.status === 'soumis');
    
    if (hasSubmitted) {
        toast.add({ 
            severity: 'error', 
            summary: 'Action impossible', 
            detail: 'Certains employés sélectionnés sont déjà en statut soumis.', 
            life: 5000 
        });
        return; 
    }

    // Préparation des données pour plusieurs IDs simultanés
    selectedData.value = {
        isBulk: true,
        timesheet_ids: selectedEmployees.value.map(item => item.id),
        employee_name: `${selectedEmployees.value.length} employés sélectionnés`,
        all_dates: periodDates.value,
        role: role.value,
        status: 'brouillon',
        timesheet_id: selectedEmployees.value[0].id // ID pivot pour le formulaire
    };
    displayModal.value = true;
};

/**
 * CALCUL DES TOTALS HEBDOMADAIRES
 * Additionne toutes les heures réelles et prévues de la semaine pour un employé.
 */
const getTotalsData = (timesheet) => {
    if (!timesheet?.entries?.length) return { worked: 0, planned: 0 };
    
    return timesheet.entries.reduce((acc, entry) => {
        const worked = parseFloat(entry.total_hours) || 0;
        const planned = parseFloat(entry.planned_hours) || 0;
        return { worked: acc.worked + worked, planned: acc.planned + planned };
    }, { worked: 0, planned: 0 });
};
</script>

<template>
    <Head title="Calendrier de Pointage" />
    <Toast />

    <AppLayout>
        <!-- Fond très clair pour faire ressortir le tableau blanc -->
        <div class="p-8 bg-slate-50 min-h-screen">
            
            <!-- SECTION EN-TÊTE : Design épuré -->
            <div class="flex justify-between items-end mb-8">
                <div>
                    <h1 class="text-3xl font-black text-slate-900 uppercase tracking-tighter leading-none">
                        Gestion des Flux
                    </h1>
                    <p class="text-slate-500 text-sm mt-2 font-medium">
                        Suivi hebdomadaire des présences et pointages
                    </p>
                </div>
                <div class="flex gap-3">
                    <Button v-if="selectedEmployees.length > 0" 
                            label="Saisie Groupée" 
                            icon="pi pi-users" 
                            class="p-button-raised p-button-help p-button-sm font-bold border-0 shadow-lg shadow-purple-200" 
                            @click="openBulkEdit" />
                    
                    <Button label="Nouveau Téléconseiller" 
                            icon="pi pi-user-plus" 
                            class="p-button-raised p-button-primary p-button-sm font-bold border-0 shadow-lg shadow-blue-200" 
                            @click="router.visit('/employees/create')" />
                </div>
            </div>

            <!-- TABLEAU : Blanc pur avec ombres douces -->
            <DataTable 
                :value="calendar" 
                v-model:selection="selectedEmployees" 
                :paginator="true" 
                :rows="rows" 
                dataKey="id" 
                scrollable 
                class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl shadow-slate-200/60"
            >
                <!-- Colonne de sélection -->
                <Column selectionMode="multiple" 
                        class="bg-slate-50/50 border-b border-slate-100" 
                        headerStyle="background: #f8fafc; width: 3rem;" 
                        frozen 
                        :disabledSelection="data => data.status === 'soumis'">
                </Column>
                
                <!-- Colonne Employé -->
                <Column frozen header="Employés" class="border-b border-slate-100 bg-white" style="min-width: 280px">
                    <template #body="{ data }">
                        <div class="flex flex-col py-3 px-2">
                            <span class="font-extrabold text-slate-800 text-sm tracking-tight">
                                {{ data.employee.first_name }} {{ data.employee.last_name }}
                            </span>
                            <div class="flex items-center gap-2 mt-1.5">
                                <span class="px-2 py-0.5 bg-blue-50 text-blue-600 text-[9px] font-black rounded-full uppercase tracking-wider border border-blue-100">
                                    ID: {{ data.employee.matricule }}
                                </span>
                                <span class="text-[10px] text-slate-400 italic">
                                    Validé par: {{ data.validator?.first_name || 'En attente' }}
                                </span>
                            </div>
                        </div>
                    </template>
                </Column>

                <!-- Colonnes jours (Cellules harmonieuses) -->
                <Column v-for="date in periodDates" :key="date" :header="formatHeader(date)" class="text-center border-b border-slate-100">
                    <template #body="{ data }">
                        <div @click="openTimeCard(data, date)" 
                             :class="[
                                getCellClass(data, date),
                                'm-1.5 p-3 rounded-xl border-2 cursor-pointer transition-all duration-200 hover:shadow-md hover:-translate-y-0.5 active:scale-90 flex flex-col items-center justify-center min-h-[70px] min-w-[70px]'
                             ]">
                            <span class="text-[7px] font-black uppercase tracking-widest mb-1 opacity-70">{{ data.status }}</span>
                            
                            <div v-if="!getEntry(data.entries, date).is_empty" class="flex items-baseline">
                                <span class="text-sm font-black">{{ getEntry(data.entries, date).total_hours }}</span>
                                <span class="text-[9px] ml-0.5 font-bold italic">h</span>
                            </div>
                            
                            <div v-else class="text-[9px] font-bold opacity-40 italic uppercase tracking-tighter">Absent</div>
                        </div>
                    </template>
                </Column>

                <!-- Résumé Totaux -->
                <Column header="Total (R/P)" class="text-center border-b border-slate-100 bg-slate-50/30" style="min-width: 140px">
                    <template #body="{ data }">
                        <div class="flex items-center justify-center py-2 px-3 bg-white rounded-lg border border-slate-100 shadow-sm mx-2">
                            <span :class="[
                                getTotalsData(data).worked >= getTotalsData(data).planned ? 'text-emerald-600' : 'text-rose-500',
                                'text-lg font-black italic tracking-tighter'
                            ]">
                                {{ getTotalsData(data).worked.toFixed(1) }}
                            </span>
                            <span class="mx-1.5 text-slate-300 font-light text-xl">/</span>
                            <span class="text-[10px] font-extrabold text-slate-400 self-end mb-1">
                                {{ getTotalsData(data).planned.toFixed(1) }}h
                            </span>
                        </div>
                    </template>
                </Column>

                <!-- Actions -->
                <Column header="Action" class="text-center border-b border-slate-100" style="min-width: 90px">
                    <template #body="{ data }">
                        <button v-if="(role === 'cp' || role === 'admin') && data.status !== 'soumis'" 
                                @click="openConfirm(data)" 
                                class="p-2.5 text-blue-500 bg-blue-50 hover:bg-blue-600 hover:text-white rounded-xl transition-all duration-300 shadow-sm border border-blue-100">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                            </svg>
                        </button>
                        
                        <div v-if="data.status === 'soumis'" 
                             class="w-10 h-10 bg-emerald-50 text-emerald-500 flex items-center justify-center rounded-full border border-emerald-100 mx-auto shadow-inner">
                            <i class="pi pi-lock text-sm"></i>
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>

        <!-- MODALS : Unifiés -->
        <Dialog v-model:visible="displayModal" :header="'Saisie de Pointage'" :modal="true" :style="{ width: '400px' }" class="p-fluid">
            <TimesCard v-if="displayModal" :data="selectedData" @close="displayModal = false" />
        </Dialog>

        <ConfirmDialog v-model:visible="showConfirmDialog" :timesheetId="selectedForSubmit?.id" :employeeName="selectedForSubmit?.name" />
    </AppLayout>
</template>

