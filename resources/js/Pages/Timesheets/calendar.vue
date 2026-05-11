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
    <!-- SEO et Notifications -->
    <Head title="Calendrier de Pointage" />
    <Toast />

    <AppLayout>
        <div class="p-6 bg-[#fcfdfe] min-h-screen">
            
            <!-- SECTION EN-TÊTE -->
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-2xl font-black text-slate-800 uppercase tracking-tighter">Gestion des Flux</h1>
                    <p class="text-slate-400 text-sm">Suivi hebdomadaire des présences</p>
                </div>
                <div class="flex gap-3">
                    <!-- Bouton dynamique : n'apparaît que si des lignes sont cochées -->
                    <Button v-if="selectedEmployees.length > 0" label="Saisie Groupée" icon="pi pi-users" class="p-button-help p-button-sm shadow-sm" @click="openBulkEdit" />
                    <Button label="Nouveau Téléconseiller" icon="pi pi-user-plus" class="p-button-primary p-button-sm shadow-sm" @click="router.visit('/employees/create')" />
                </div>
            </div>

            <!-- TABLEAU DE DONNÉES -->
            <DataTable 
                :value="calendar" 
                v-model:selection="selectedEmployees" 
                :paginator="true" 
                :rows="rows" 
                dataKey="id" 
                scrollable 
                class="p-datatable-sm custom-table border-0 rounded-xl overflow-hidden shadow-xl shadow-slate-200/50"
            >
                <!-- Colonne de sélection avec filtre sur le statut -->
                <Column selectionMode="multiple" headerStyle="width: 3rem; background: #f8fafc" frozen :disabledSelection="data => data.status === 'soumis'"></Column>
                
                <!-- Colonne Employé -->
                <Column frozen header="Employés" style="min-width: 250px">
                    <template #body="{ data }">
                        <div class="flex flex-col py-2 px-1">
                            <span class="font-bold text-slate-700 text-sm">{{ data.employee.first_name }} {{ data.employee.last_name }}</span>
                            <div class="flex gap-2 mt-1">
                                <small class="text-[10px] px-1.5 py-0.5 bg-slate-100 text-slate-500 rounded font-medium">ID: {{ data.employee.matricule }}</small>
                                <small class="text-[10px] text-slate-400 self-center">Par : {{ data.validator?.first_name || 'N/A' }}</small>
                            </div>
                        </div>
                    </template>
                </Column>

                <!-- Colonnes jours de la semaine (Boucle sur periodDates) -->
                <Column v-for="date in periodDates" :key="date" :header="formatHeader(date)" class="text-center">
                    <template #body="{ data }">
                        <div @click="openTimeCard(data, date)" :class="getCellClass(data, date)" class="m-1 p-2 border-2 rounded-lg cursor-pointer hover:scale-[1.02] active:scale-95 transition-all flex flex-col items-center justify-center min-h-[65px] group">
                            <span class="text-[8px] font-bold uppercase tracking-widest opacity-60 group-hover:opacity-100">{{ data.status }}</span>
                            <div v-if="!getEntry(data.entries, date).is_empty" class="text-sm font-black mt-0.5">
                                {{ getEntry(data.entries, date).total_hours }}<span class="text-[10px] ml-0.5">h</span>
                            </div>
                            <div v-else class="text-[9px] font-bold opacity-40 italic uppercase tracking-tighter">Absent</div>
                        </div>
                    </template>
                </Column>

                <!-- Colonne Résumé Totaux -->
                <Column header="Total (R/P)" class="text-center" style="min-width: 130px">
                    <template #body="{ data }">
                        <div class="total-container flex items-center justify-center gap-1" :class="getTotalsData(data).worked >= getTotalsData(data).planned ? 'text-emerald-600' : 'text-rose-500'">
                            <span class="real-hours text-xl font-black italic">{{ getTotalsData(data).worked.toFixed(1) }}</span>
                            <span class="separator">/</span>
                            <span class="planned-hours text-[11px] font-bold self-end mb-1 text-slate-400">{{ getTotalsData(data).planned.toFixed(1) }}h</span>
                        </div>
                    </template>
                </Column>

                <!-- Colonne Actions rapides -->
                <Column header="Action" class="text-center" style="min-width: 80px">
                    <template #body="{ data }">
                        <!-- Le bouton de soumission n'est visible que pour les managers -->
                        <button v-if="(role === 'cp' || role === 'admin') && data.status !== 'soumis'" @click="openConfirm(data)" class="p-2 text-blue-500 hover:bg-blue-50 rounded-xl transition-all">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                            </svg>
                        </button>
                        <!-- Icône cadenas si la ligne est verrouillée -->
                        <div v-if="data.status === 'soumis'" class="text-emerald-400 bg-emerald-50 w-8 h-8 flex items-center justify-center rounded-full mx-auto">
                            <i class="pi pi-lock text-sm"></i>
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>

        <!-- MODALS -->
        <Dialog v-model:visible="displayModal" :header="'Pointage'" :modal="true" :style="{ width: '400px' }">
            <TimesCard v-if="displayModal" :data="selectedData" @close="displayModal = false" />
        </Dialog>

        <ConfirmDialog v-model:visible="showConfirmDialog" :timesheetId="selectedForSubmit?.id" :employeeName="selectedForSubmit?.name" />
    </AppLayout>
</template>
