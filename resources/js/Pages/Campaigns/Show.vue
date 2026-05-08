<script setup>
// Importation des composants PrimeVue
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import Tabs from 'primevue/tabs';
import TabList from 'primevue/tablist';
import Tab from 'primevue/tab';
import TabPanels from 'primevue/tabpanels';
import TabPanel from 'primevue/tabpanel';
import Avatar from 'primevue/avatar';
import Dialog from 'primevue/dialog';
import Dropdown from 'primevue/dropdown';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import { useToast } from 'primevue/usetoast';
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

// Props reçues du contrôleur
const props = defineProps({
    campaign: Object,      // Détails de la campagne
    assignments: Array,   // Affectations actives
    history: Array,       // Historique des affectations
    employees: Array,     // Liste des employés avec leur position_id
    positions: Array      // Liste des positions (CP, SUP, TC, etc.)
});

const toast = useToast();
const activeTab = ref(0);

// --- LOGIQUE DE CAMPAGNE ---

/**
 * Clôturer la campagne (Status -> terminee via route DELETE)
 */
const closeDialog = ref(false);
const closeCampaign = () => {
    router.delete(`/campaigns/${props.campaign.id}`, {
        onSuccess: () => {
            toast.add({ severity: 'success', summary: 'Succès', detail: 'Campagne clôturée avec succès', life: 3000 });
            closeDialog.value = false;
        }
    });
};


/**
 * Désactiver la campagne (Status -> inactive)
 */
const deactivateCampaign = () => {
    router.patch(`/campaigns/${props.campaign.id}/status`, { status: 'inactive' }, {
        onSuccess: () => toast.add({ severity: 'success', summary: 'Succès', detail: 'Campagne désactivée', life: 3000 })
    });
};

/**
 * Activer la campagne (Status -> active)
 */
const activateCampaign = () => {
    router.patch(`/campaigns/${props.campaign.id}/status`, { status: 'active' }, {
        onSuccess: () => toast.add({ severity: 'success', summary: 'Succès', detail: 'Campagne activée', life: 3000 })
    });
};

// --- LOGIQUE D'ÉDITION DE CAMPAGNE ---
const campaignDialog = ref(false);
const editForm = ref({});
const submitted = ref(false);

const openEdit = () => {
    // Formatage des dates pour l'input date
    const formatted = { ...props.campaign };
    if (props.campaign.start_date) {
        const p = props.campaign.start_date.split('/');
        if (p.length === 3) formatted.start_date = `${p[2]}-${p[1]}-${p[0]}`;
    }
    if (props.campaign.end_date) {
        const p = props.campaign.end_date.split('/');
        if (p.length === 3) formatted.end_date = `${p[2]}-${p[1]}-${p[0]}`;
    }
    editForm.value = formatted;
    campaignDialog.value = true;
};

const saveCampaign = () => {
    submitted.value = true;
    if (!editForm.value.name) return;

    router.put(`/campaigns/${editForm.value.id}`, editForm.value, {
        onSuccess: () => {
            toast.add({ severity: 'success', summary: 'Succès', detail: 'Campagne mise à jour', life: 3000 });
            campaignDialog.value = false;
        }
    });
};

// --- LOGIQUE HIÉRARCHIQUE (Image 1) ---

const hoveredId = ref(null); // ID de l'affectation survolée

// Organisation des données pour l'affichage en arbre
const hierarchicalView = computed(() => {
    // 1. Trouver les CP (Chefs de Plateau)
    const cps = props.assignments.filter(a => a.position.code === 'CP');
    
    return cps.map(cp => {
        // 2. Trouver les SUP rattachés à ce CP
        const supervisors = props.assignments.filter(a => 
            a.position.code === 'SUP' && a.manager_id === cp.employee_id
        );

        return {
            ...cp,
            subordinates: supervisors.map(sup => {
                // 3. Trouver les TC rattachés à ce SUP
                const teleconseillers = props.assignments.filter(a => 
                    a.position.code === 'TC' && a.manager_id === sup.employee_id
                );
                return { ...sup, subordinates: teleconseillers };
            })
        };
    });
});

// --- GESTION DE L'AFFECTATION (Image 2) ---

const assignmentDialog = ref(false);
const step = ref(1); // Étape du formulaire (1 à 4)
const newAssignment = ref({
    type: null, // 'CP', 'SUP', 'TC'
    employee_id: null,
    manager_id: null,
    campaign_id: props.campaign.id,
    position_id: null,
    start_date: new Date().toISOString().substr(0, 10)
});

// Filtrer les employés par poste correspondant au type choisi (Image 2)
const filteredEmployeesForType = computed(() => {
    if (!newAssignment.value.type) return [];
    return props.employees.filter(emp => {
        const pos = props.positions.find(p => p.id === emp.position_id);
        return pos && pos.code === newAssignment.value.type;
    });
});

// Managers disponibles selon la hiérarchie
const availableManagers = computed(() => {
    if (newAssignment.value.type === 'SUP') {
        return props.assignments.filter(a => a.position.code === 'CP');
    }
    if (newAssignment.value.type === 'TC') {
        return props.assignments.filter(a => a.position.code === 'SUP');
    }
    return [];
});

/**
 * Validation des étapes et automatisation (Image 2)
 */
const nextStep = () => {
    if (step.value === 1) {
        if (!newAssignment.value.type) return;
        // Automatisation : si on affecte un SUP et qu'il n'y a qu'un seul CP, on le pré-sélectionne
        if (newAssignment.value.type === 'SUP' && availableManagers.value.length === 1) {
            newAssignment.value.manager_id = availableManagers.value[0].employee_id;
        }
    }
    if (step.value === 2 && !newAssignment.value.employee_id) return;
    if (step.value === 3) {
        if (newAssignment.value.type !== 'CP' && !newAssignment.value.manager_id) return;
    }
    step.value++;
};

const saveAssignment = () => {
    const pos = props.positions.find(p => p.code === newAssignment.value.type);
    newAssignment.value.position_id = pos?.id;

    router.post('/assignments', newAssignment.value, {
        onSuccess: () => {
            toast.add({ severity: 'success', summary: 'Succès', detail: 'Ressource affectée', life: 3000 });
            assignmentDialog.value = false;
            step.value = 1;
        }
    });
};

// --- GESTION UNIQUE DE L'AFFECTATION (Zéro Orphelin) ---

const manageDialog = ref(false);
const manageStep = ref('choice'); // 'choice', 'transfer', 'release'
const manageData = ref({
    mode: null, // 'transfer', 'release'
    new_manager_id: null,
    reason: ''
});

/**
 * Ouvre le modal unique de gestion
 */
const openManage = (assignment) => {
    selectedAssignment.value = assignment;
    manageData.value = {
        mode: null,
        new_manager_id: null,
        reason: ''
    };
    manageStep.value = 'choice';
    manageDialog.value = true;
};

/**
 * Sélectionne le mode (Transfert ou Libération)
 */
const selectManageMode = (mode) => {
    manageData.value.mode = mode;
    if (mode === 'transfer') {
        manageStep.value = 'transfer';
    } else {
        manageStep.value = 'release';
    }
};

/**
 * Exécute l'action de gestion (Libération ou Transfert)
 */
const executeManage = () => {
    const payload = {
        mode: manageData.value.mode === 'transfer' ? 'transfer' : 'cascade',
        new_manager_id: manageData.value.new_manager_id,
        reason: manageData.value.reason
    };

    router.post(`/assignments/${selectedAssignment.value.id}/release`, payload, {
        onSuccess: () => {
            toast.add({ 
                severity: 'success', 
                summary: 'Succès', 
                detail: manageData.value.mode === 'transfer' ? 'Chaîne transférée avec succès' : 'Ressources libérées avec succès', 
                life: 3000 
            });
            manageDialog.value = false;
        }
    });
};

// Managers disponibles pour le transfert (doivent avoir le même rôle que la personne qui part)
const availableTransferManagers = computed(() => {
    if (!selectedAssignment.value) return [];
    
    // On cherche les employés qui ont la même position et qui sont actifs sur la campagne
    // mais qui ne sont pas la personne sélectionnée
    return props.assignments.filter(a => 
        a.position_id === selectedAssignment.value.position_id && 
        a.employee_id !== selectedAssignment.value.employee_id
    );
});

// Utilitaires d'affichage
const getInitials = (first, last) => (first?.[0] || '') + (last?.[0] || '');
const getStatusSeverity = (status) => status === 'active' || status === 'actif' ? 'success' : 'warn';

</script>

<template>
    <AppLayout>
        <div class="p-6 bg-slate-50 min-h-screen">
            <!-- BOUTON RETOUR -->
            <Link href="/campaigns" class="flex items-center gap-2 text-slate-500 hover:text-slate-800 mb-6 transition-colors">
                <i class="pi pi-arrow-left text-sm" />
                <span>Retour aux campagnes</span>
            </Link>

            <!-- CARD HEADER CAMPAGNE (Image 1) -->
            <div class="bg-white rounded-3xl p-8 shadow-sm border border-slate-100 mb-8">
                <div class="flex justify-between items-start">
                    <div class="flex-1">
                        <div class="flex items-center gap-3 mb-2">
                            <h1 class="text-3xl font-bold text-slate-900">{{ props.campaign.name }}</h1>
                            <Tag :value="props.campaign.status" :severity="getStatusSeverity(props.campaign.status)" rounded class="px-3" />
                        </div>
                        <p class="text-slate-500 text-lg mb-6">{{ props.campaign.description }}</p>
                        
                        <div class="flex flex-wrap gap-6 text-slate-600">
                            <div class="flex items-center gap-2">
                                <i class="pi pi-calendar text-slate-400" />
                                <span>Du <b>{{ props.campaign.start_date }}</b> au <b>{{ props.campaign.end_date || 'En cours' }}</b></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <i class="pi pi-users text-slate-400" />
                                <span><b>{{ props.assignments.length }}</b> affectations</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-3">
                        <Button label="Modifier" icon="pi pi-pencil" severity="secondary" outlined class="rounded-xl" @click="openEdit" />
                        
                        <!-- Boutons selon le statut -->
                        <template v-if="props.campaign.status === 'active'">
                            <Button label="Désactiver" icon="pi pi-power-off" severity="warn" outlined class="rounded-xl" @click="deactivateCampaign" />
                            <Button label="Clôturer" icon="pi pi-times-circle" severity="danger" class="rounded-xl border-none" @click="closeDialog = true" />
                        </template>

                        <template v-else-if="props.campaign.status === 'inactive'">
                            <Button label="Activer" icon="pi pi-play" severity="success" outlined class="rounded-xl" @click="activateCampaign" />
                            <Button label="Clôturer" icon="pi pi-times-circle" severity="danger" class="rounded-xl border-none" @click="closeDialog = true" />
                        </template>

                        <!-- Si terminée, aucun bouton supplémentaire n'est affiché selon la consigne (seul Modifier reste) -->
                    </div>
                </div>
            </div>

            <!-- TABS (Image 1) -->
            <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
                <Tabs v-model:value="activeTab">
                    <TabList class="px-6 pt-4 border-b border-slate-100">
                        <Tab :value="0" class="flex items-center gap-2 pb-4">
                            <i class="pi pi-users text-sm" />
                            <span>Affectations</span>
                            <Tag :value="props.assignments.length" severity="info" rounded class="ml-1 text-[10px]" />
                        </Tab>
                        <Tab :value="1" class="flex items-center gap-2 pb-4">
                            <i class="pi pi-history text-sm" />
                            <span>Historique</span>
                            <Tag :value="props.history.length" severity="secondary" rounded class="ml-1 text-[10px]" />
                        </Tab>
                    </TabList>

                    <TabPanels class="p-8">
                        <!-- VUE HIÉRARCHIQUE (Image 1) -->
                        <TabPanel :value="0">
                            <!-- Cas Campagne Active -->
                            <template v-if="props.campaign.status === 'active'">
                                <div class="flex justify-between items-center mb-8">
                                    <h3 class="text-slate-400 font-medium">Vue hiérarchique des ressources affectées</h3>
                                    <Button 
                                        label="Affecter une ressource" 
                                        icon="pi pi-user-plus" 
                                        class="rounded-2xl px-6 py-3 bg-blue-600 border-none shadow-lg shadow-blue-200" 
                                        @click="assignmentDialog = true" 
                                    />
                                </div>

                                <div v-if="hierarchicalView.length === 0" class="text-center py-20 bg-slate-50 rounded-3xl border-2 border-dashed border-slate-200">
                                    <i class="pi pi-user-plus text-4xl text-slate-300 mb-4" />
                                    <p class="text-slate-500">Aucun Chef de Plateau n'est encore affecté à cette campagne.</p>
                                </div>

                                <!-- ARBRE HIÉRARCHIQUE -->
                                <div class="flex flex-col gap-6">
                                    <div v-for="cp in hierarchicalView" :key="cp.id" class="relative">
                                        <!-- Ligne CP -->
                                        <div 
                                            class="flex items-center gap-4 p-4 bg-slate-50/50 rounded-2xl border border-slate-100 group transition-all hover:bg-white hover:shadow-md"
                                            @mouseenter="hoveredId = cp.id"
                                            @mouseleave="hoveredId = null"
                                        >
                                            <Avatar :label="getInitials(cp.employee.first_name, cp.employee.last_name)" size="large" shape="circle" class="bg-purple-100 text-purple-600 font-bold" />
                                            <div class="flex-1">
                                                <div class="flex items-center gap-2">
                                                    <span class="font-bold text-slate-900 text-lg">{{ cp.employee.first_name }} {{ cp.employee.last_name }}</span>
                                                    <Tag value="CP" severity="info" class="bg-purple-50 text-purple-500 text-[10px] font-bold" />
                                                </div>
                                                <div class="flex items-center gap-4 text-slate-400 text-sm mt-1">
                                                    <span>{{ cp.employee.matricule }}</span>
                                                    <span class="flex items-center gap-1"><i class="pi pi-circle-fill text-[6px] text-green-500" /> actif</span>
                                                    <span>Depuis le {{ cp.start_date }}</span>
                                                </div>
                                            </div>
                                            <!-- Bouton d'action unique (Zéro Orphelin) -->
                                            <div class="flex gap-2 animate-fade-in">
                                                <Button 
                                                    icon="pi pi-cog" 
                                                    severity="secondary" 
                                                    text rounded size="small" 
                                                    v-tooltip.top="'Gérer l\'affectation'" 
                                                    @click="openManage(cp)"
                                                />
                                            </div>
                                        </div>

                                        <!-- Lignes SUP -->
                                        <div v-if="cp.subordinates.length > 0" class="ml-12 mt-4 flex flex-col gap-4 border-l-2 border-slate-100 pl-8 relative">
                                            <div v-for="sup in cp.subordinates" :key="sup.id" class="relative">
                                                <!-- Connecteur horizontal -->
                                                <div class="absolute -left-8 top-8 w-8 h-0.5 bg-slate-100"></div>
                                                
                                                <div 
                                                    class="flex items-center gap-4 p-4 bg-white rounded-2xl border border-slate-100 shadow-sm group transition-all hover:shadow-md"
                                                    @mouseenter="hoveredId = sup.id"
                                                    @mouseleave="hoveredId = null"
                                                >
                                                    <Avatar :label="getInitials(sup.employee.first_name, sup.employee.last_name)" size="large" shape="circle" class="bg-blue-100 text-blue-600 font-bold" />
                                                    <div class="flex-1">
                                                        <div class="flex items-center gap-2">
                                                            <span class="font-bold text-slate-900">{{ sup.employee.first_name }} {{ sup.employee.last_name }}</span>
                                                            <Tag value="SUP" severity="info" class="bg-blue-50 text-blue-500 text-[10px] font-bold" />
                                                        </div>
                                                        <div class="text-slate-400 text-xs mt-1">{{ sup.employee.matricule }} • Depuis le {{ sup.start_date }}</div>
                                                    </div>
                                                    <!-- Bouton d'action unique -->
                                                    <div class="flex gap-2 animate-fade-in">
                                                        <Button 
                                                            icon="pi pi-cog" 
                                                            severity="secondary" 
                                                            text rounded size="small" 
                                                            v-tooltip.top="'Gérer l\'affectation'" 
                                                            @click="openManage(sup)"
                                                        />
                                                    </div>
                                                </div>

                                                <!-- Lignes TC -->
                                                <div v-if="sup.subordinates.length > 0" class="ml-12 mt-4 flex flex-col gap-3 border-l-2 border-slate-100 pl-8 relative">
                                                    <div v-for="tc in sup.subordinates" :key="tc.id" class="relative">
                                                        <div class="absolute -left-8 top-6 w-8 h-0.5 bg-slate-100"></div>
                                                        <div 
                                                            class="flex items-center gap-3 p-3 bg-white rounded-xl border border-slate-100 shadow-sm group transition-all hover:shadow-md"
                                                            @mouseenter="hoveredId = tc.id"
                                                            @mouseleave="hoveredId = null"
                                                        >
                                                            <Avatar :label="getInitials(tc.employee.first_name, tc.employee.last_name)" size="normal" shape="circle" class="bg-emerald-100 text-emerald-600 font-bold" />
                                                            <div class="flex-1">
                                                                <div class="flex items-center gap-2">
                                                                    <span class="font-semibold text-slate-800 text-sm">{{ tc.employee.first_name }} {{ tc.employee.last_name }}</span>
                                                                    <Tag value="TC" severity="info" class="bg-emerald-50 text-emerald-500 text-[9px] font-bold" />
                                                                </div>
                                                                <div class="text-slate-400 text-[10px]">{{ tc.employee.matricule }} • Depuis le {{ tc.start_date }}</div>
                                                            </div>
                                                            <!-- Bouton d'action unique -->
                                                            <div class="flex gap-2 animate-fade-in">
                                                                <Button 
                                                                    icon="pi pi-cog" 
                                                                    severity="secondary" 
                                                                    text rounded size="small" 
                                                                    v-tooltip.top="'Gérer l\'affectation'" 
                                                                    @click="openManage(tc)"
                                                                />
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <!-- Cas Campagne Inactive -->
                            <template v-else-if="props.campaign.status === 'inactive'">
                                <div class="text-center py-20 bg-amber-50 rounded-3xl border-2 border-dashed border-amber-200">
                                    <i class="pi pi-lock text-4xl text-amber-400 mb-4" />
                                    <p class="text-amber-800 font-bold text-xl mb-2">Les affectations sont désactivées.</p>
                                    <p class="text-amber-600">La campagne doit être active pour gérer les affectations.</p>
                                </div>
                            </template>

                            <!-- Cas Campagne Terminee -->
                            <template v-else>
                                <div class="text-center py-20 bg-slate-50 rounded-3xl border-2 border-dashed border-slate-200">
                                    <i class="pi pi-calendar-times text-4xl text-slate-300 mb-4" />
                                    <p class="text-slate-800 font-bold text-xl mb-2">Campagne clôturée.</p>
                                    <p class="text-slate-500">L'arborescence des affectations n'est plus disponible pour les campagnes terminées. Veuillez consulter l'historique.</p>
                                    <Button label="Voir l'historique" icon="pi pi-history" text class="mt-4" @click="activeTab = 1" />
                                </div>
                            </template>
                        </TabPanel>

                        <!-- HISTORIQUE (Image 1) -->
                        <TabPanel :value="1">
                            <div class="flex flex-col gap-4">
                                <div v-for="h in props.history" :key="h.id" class="flex gap-4 p-4 border-b border-slate-50">
                                    <div class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-400">
                                        <i :class="h.action_type === 'assign' ? 'pi pi-user-plus' : 'pi pi-user-minus'" />
                                    </div>
                                    <div>
                                        <p class="text-slate-900">
                                            <b>{{ h.employee.first_name }} {{ h.employee.last_name }}</b> 
                                            {{ h.action_type === 'assign' ? 'a été affecté' : 'a été libéré' }}
                                        </p>
                                        <p class="text-xs text-slate-400 mt-1">Par {{ h.author.name }} • {{ h.created_at }}</p>
                                        <div v-if="h.reason" class="mt-2 text-sm text-slate-500 bg-slate-50 p-2 rounded-lg italic">"{{ h.reason }}"</div>
                                    </div>
                                </div>
                            </div>
                        </TabPanel>
                    </TabPanels>
                </Tabs>
            </div>

            <!-- MODAL ÉDITION CAMPAGNE -->
            <Dialog v-model:visible="campaignDialog" header="Modifier la campagne" modal class="p-fluid rounded-2xl" :style="{width: '500px'}">
                <div class="flex flex-col gap-4 mt-2">
                    <div>
                        <label class="font-semibold block mb-1">Nom de la campagne *</label>
                        <InputText v-model="editForm.name" placeholder="Ex: Campagne Highfive" :class="{'p-invalid': submitted && !editForm.name}" />
                    </div>
                    <div>
                        <label class="font-semibold block mb-1">Description</label>
                        <Textarea v-model="editForm.description" rows="3" placeholder="Objectifs et détails..." />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="font-semibold block mb-1">Date début *</label>
                            <InputText type="date" v-model="editForm.start_date" />
                        </div>
                        <div>
                            <label class="font-semibold block mb-1">Date fin</label>
                            <InputText type="date" v-model="editForm.end_date" />
                        </div>
                    </div>
                    <div>
                        <label class="font-semibold block mb-2">Statut</label>
                        <div class="flex gap-2">
                            <Button label="Active" :outlined="editForm.status !== 'active'" rounded @click="editForm.status = 'active'" severity="success" size="small" />
                            <Button label="Inactive" :outlined="editForm.status !== 'inactive'" rounded @click="editForm.status = 'inactive'" severity="warn" size="small" />
                            <Button label="Terminée" :outlined="editForm.status !== 'terminee'" rounded @click="editForm.status = 'terminee'" severity="secondary" size="small" />
                        </div>
                    </div>
                </div>
                <template #footer>
                    <Button label="Annuler" severity="secondary" text @click="campaignDialog = false" />
                    <Button label="Enregistrer" icon="pi pi-check" @click="saveCampaign" class="px-4" />
                </template>
            </Dialog>

            <!-- MODAL CONFIRMATION CLÔTURE -->
            <Dialog v-model:visible="closeDialog" header="Confirmation de clôture" modal :style="{width: '400px'}">
                <div class="flex items-center gap-3">
                    <i class="pi pi-exclamation-triangle text-red-500 text-3xl" />
                    <div class="flex flex-col">
                        <span>Voulez-vous clôturer la campagne <b>{{ props.campaign.name }}</b> ?</span>
                        <span class="text-sm text-slate-500 mt-2 italic">Cette action passera le statut à "Terminée".</span>
                    </div>
                </div>
                <template #footer>
                    <Button label="Annuler" severity="secondary" text @click="closeDialog = false" />
                    <Button label="Confirmer la clôture" severity="danger" @click="closeCampaign" />
                </template>
            </Dialog>

            <!-- MODAL D'AFFECTATION PAR ÉTAPES (Image 2) -->
            <Dialog v-model:visible="assignmentDialog" modal class="p-0 overflow-hidden rounded-3xl" :style="{width: '650px'}" :closable="true">
                <template #header>
                    <div class="px-8 pt-8 pb-4">
                        <h2 class="text-2xl font-bold text-slate-900">Affecter une ressource</h2>
                    </div>
                </template>

                <div class="px-12 py-6">
                    <!-- STEPS INDICATOR (Image 2) -->
                    <div class="flex items-center justify-between mb-12 relative">
                        <div class="absolute top-1/2 left-0 w-full h-0.5 bg-slate-100 -translate-y-1/2 z-0"></div>
                        <div v-for="i in 4" :key="i" class="z-10 flex flex-col items-center gap-2">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold transition-all duration-300"
                                 :class="step >= i ? 'bg-blue-600 text-white' : 'bg-white border-2 border-slate-100 text-slate-300'">
                                {{ i }}
                            </div>
                            <span class="text-xs font-bold" :class="step >= i ? 'text-blue-600' : 'text-slate-300'">
                                {{ ['Type', 'Employé', 'Configuration', 'Validation'][i-1] }}
                            </span>
                        </div>
                    </div>

                    <!-- STEP 1: TYPE (Image 2) -->
                    <div v-if="step === 1" class="flex flex-col gap-4">
                        <p class="text-slate-500 mb-2">Sélectionnez le type de ressource à affecter à <b>{{ props.campaign.name }}</b>.</p>
                        
                        <!-- Option CP -->
                        <div @click="newAssignment.type = 'CP'" 
                             class="flex items-center gap-4 p-5 rounded-2xl border-2 transition-all cursor-pointer"
                             :class="newAssignment.type === 'CP' ? 'border-blue-600 bg-blue-50/50' : 'border-slate-100 hover:border-blue-200'">
                            <div class="w-12 h-12 rounded-xl bg-white border border-slate-100 flex items-center justify-center text-slate-400">
                                <i class="pi pi-shield" />
                            </div>
                            <div>
                                <div class="font-bold text-slate-900">Chef de Plateau</div>
                                <div class="text-xs text-slate-500">Responsable de la campagne, pilote les superviseurs</div>
                            </div>
                        </div>

                        <!-- Option SUP -->
                        <div @click="hierarchicalView.length > 0 ? newAssignment.type = 'SUP' : null" 
                             class="flex items-center gap-4 p-5 rounded-2xl border-2 transition-all cursor-pointer"
                             :class="[
                                newAssignment.type === 'SUP' ? 'border-blue-600 bg-blue-50/50' : 'border-slate-100 hover:border-blue-200',
                                hierarchicalView.length === 0 ? 'opacity-50 grayscale' : ''
                             ]">
                            <div class="w-12 h-12 rounded-xl bg-white border border-slate-100 flex items-center justify-center text-slate-400">
                                <i class="pi pi-briefcase" />
                            </div>
                            <div>
                                <div class="font-bold text-slate-900">Superviseur</div>
                                <div class="text-xs text-slate-500">Encadre les téléconseilleurs</div>
                                <div v-if="hierarchicalView.length === 0" class="text-[10px] text-red-500 mt-1 font-bold">⚠ Aucun CP affecté</div>
                            </div>
                        </div>

                        <!-- Option TC -->
                        <div @click="props.assignments.some(a => a.position.code === 'SUP') ? newAssignment.type = 'TC' : null" 
                             class="flex items-center gap-4 p-5 rounded-2xl border-2 transition-all cursor-pointer"
                             :class="[
                                newAssignment.type === 'TC' ? 'border-blue-600 bg-blue-50/50' : 'border-slate-100 hover:border-blue-200',
                                !props.assignments.some(a => a.position.code === 'SUP') ? 'opacity-50 grayscale' : ''
                             ]">
                            <div class="w-12 h-12 rounded-xl bg-white border border-slate-100 flex items-center justify-center text-slate-400">
                                <i class="pi pi-headphones" />
                            </div>
                            <div>
                                <div class="font-bold text-slate-900">Téléconseiller</div>
                                <div class="text-xs text-slate-500">Agent de terrain</div>
                                <div v-if="!props.assignments.some(a => a.position.code === 'SUP')" class="text-[10px] text-red-500 mt-1 font-bold">⚠ Aucun SUP affecté</div>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 2: EMPLOYÉ -->
                    <div v-if="step === 2" class="flex flex-col gap-4">
                        <label class="font-bold text-slate-900">Sélectionnez l'employé à affecter</label>
                        <!-- Filtrage des employés par poste selon le type choisi (Image 2) -->
                        <Dropdown v-model="newAssignment.employee_id" :options="filteredEmployeesForType" optionLabel="email" optionValue="id" filter placeholder="Rechercher parmi les employés qualifiés..." class="rounded-xl border-slate-200" />
                        
                        <div v-if="newAssignment.employee_id" class="p-4 bg-blue-50 rounded-2xl border border-blue-100 text-blue-800 text-sm">
                            <i class="pi pi-info-circle mr-2" /> Cet employé a bien la position <b>{{ newAssignment.type }}</b> dans la base de données.
                        </div>
                    </div>

                    <!-- STEP 3: CONFIGURATION (Manager) -->
                    <div v-if="step === 3" class="flex flex-col gap-4">
                        <label class="font-bold text-slate-900">Rattachement hiérarchique</label>
                        <Dropdown v-if="newAssignment.type !== 'CP'" v-model="newAssignment.manager_id" :options="availableManagers" optionLabel="employee.email" optionValue="employee_id" placeholder="Choisir le responsable direct..." class="rounded-xl border-slate-200" />
                        <div v-else class="p-8 text-center bg-slate-50 rounded-3xl border-2 border-dashed border-slate-200">
                            <p class="text-slate-500">Un Chef de Plateau est au sommet de la hiérarchie de campagne. Il n'a pas de manager direct sur cette campagne.</p>
                        </div>
                        
                        <div class="mt-4">
                            <label class="font-bold text-slate-900">Date de début d'affectation</label>
                            <InputText type="date" v-model="newAssignment.start_date" class="w-full mt-1 rounded-xl border-slate-200" />
                        </div>
                    </div>

                    <!-- STEP 4: VALIDATION -->
                    <div v-if="step === 4" class="text-center py-6">
                        <div class="w-20 h-20 rounded-full bg-green-100 text-green-600 flex items-center justify-center mx-auto mb-6">
                            <i class="pi pi-check text-4xl" />
                        </div>
                        <h3 class="text-xl font-bold text-slate-900 mb-2">Prêt à valider</h3>
                        <p class="text-slate-500">L'employé sélectionné sera affecté à la campagne avec les paramètres définis. Une trace sera ajoutée à l'historique.</p>
                    </div>
                </div>

                <template #footer>
                    <div class="px-8 pb-8 flex justify-between gap-3">
                        <Button label="Précédent" severity="secondary" text @click="step--" v-if="step > 1" class="rounded-xl px-6" />
                        <div v-else></div>
                        
                        <Button v-if="step < 4" label="Suivant" icon="pi pi-arrow-right" iconPos="right" @click="nextStep" class="rounded-xl px-8 bg-blue-600 border-none shadow-lg shadow-blue-200" />
                        <Button v-else label="Confirmer l'affectation" icon="pi pi-check" @click="saveAssignment" class="rounded-xl px-8 bg-green-600 border-none shadow-lg shadow-green-200" />
                    </div>
                </template>
            </Dialog>

            <!-- MODAL UNIQUE DE GESTION (Zéro Orphelin) -->
            <Dialog v-model:visible="manageDialog" modal class="p-0 overflow-hidden rounded-3xl" :style="{width: '550px'}" :closable="true">
                <template #header>
                    <div class="px-8 pt-8 pb-4">
                        <h2 class="text-2xl font-bold text-slate-900">Gestion de l'affectation</h2>
                    </div>
                </template>

                <div class="px-12 py-6">
                    <div v-if="selectedAssignment" class="flex flex-col gap-6">
                        <!-- Résumé de la ressource -->
                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100 flex items-center gap-3">
                            <Avatar :label="getInitials(selectedAssignment.employee.first_name, selectedAssignment.employee.last_name)" shape="circle" class="bg-blue-100 text-blue-600 font-bold" />
                            <div class="flex flex-col">
                                <span class="font-bold text-slate-900">{{ selectedAssignment.employee.first_name }} {{ selectedAssignment.employee.last_name }}</span>
                                <span class="text-xs text-slate-500">{{ selectedAssignment.position.name }}</span>
                            </div>
                        </div>

                        <!-- ÉTAPE 1 : CHOIX DE L'ACTION -->
                        <div v-if="manageStep === 'choice'" class="flex flex-col gap-4">
                            <p class="text-slate-600">Comment souhaitez-vous gérer le départ de ce responsable ?</p>
                            
                            <!-- Option 1: Remplacer (Garder les subordonnés) -->
                            <div v-if="selectedAssignment.position.code !== 'TC'"
                                 @click="availableTransferManagers.length > 0 ? selectManageMode('transfer') : null" 
                                 class="flex items-center gap-4 p-5 rounded-2xl border-2 transition-all cursor-pointer"
                                 :class="[
                                    'border-slate-100 hover:border-blue-200 hover:bg-blue-50/30',
                                    availableTransferManagers.length === 0 ? 'opacity-50 grayscale cursor-not-allowed' : ''
                                 ]">
                                <div class="w-12 h-12 rounded-xl bg-white border border-slate-100 flex items-center justify-center text-blue-500">
                                    <i class="pi pi-user-edit" />
                                </div>
                                <div class="flex-1">
                                    <div class="font-bold text-slate-900">Remplacer et garder la chaîne</div>
                                    <div class="text-xs text-slate-500">Le responsable part, mais un remplaçant prend sa place et récupère tous ses subordonnés.</div>
                                    <div v-if="availableTransferManagers.length === 0" class="text-[10px] text-red-500 mt-1 font-bold">⚠ Aucun autre {{ selectedAssignment.position.code }} disponible pour le remplacement</div>
                                </div>
                            </div>

                            <!-- Option 2: Libérer avec toute la chaîne -->
                            <div @click="selectManageMode('release')" 
                                 class="flex items-center gap-4 p-5 rounded-2xl border-2 border-slate-100 hover:border-red-200 hover:bg-red-50/30 transition-all cursor-pointer">
                                <div class="w-12 h-12 rounded-xl bg-white border border-slate-100 flex items-center justify-center text-red-500">
                                    <i class="pi pi-users" />
                                </div>
                                <div class="flex-1">
                                    <div class="font-bold text-slate-900">Libérer avec toute sa chaîne</div>
                                    <div class="text-xs text-slate-500">
                                        {{ selectedAssignment.position.code === 'TC' ? 'Retirer le téléconseiller de la campagne.' : 'Le responsable et TOUS ses subordonnés sont retirés de la campagne.' }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ÉTAPE 2 : CONFIGURATION TRANSFERT -->
                        <div v-if="manageStep === 'transfer'" class="flex flex-col gap-6">
                            <div>
                                <label class="font-bold text-slate-900 block mb-2">Choisir le remplaçant *</label>
                                <Dropdown 
                                    v-model="manageData.new_manager_id" 
                                    :options="availableTransferManagers" 
                                    optionLabel="employee.email" 
                                    optionValue="employee_id" 
                                    placeholder="Sélectionner le nouveau responsable..." 
                                    class="w-full rounded-xl border-slate-200" 
                                />
                                <p class="text-[10px] text-slate-400 mt-2 italic">Les subordonnés de {{ selectedAssignment.employee.first_name }} lui seront automatiquement rattachés.</p>
                            </div>

                            <div>
                                <label class="font-bold text-slate-900 block mb-1">Raison du transfert</label>
                                <textarea v-model="manageData.reason" rows="3" placeholder="Ex: Remplacement congé, promotion..." class="w-full rounded-xl border border-slate-200 p-3" />
                            </div>
                        </div>

                        <!-- ÉTAPE 2 : CONFIGURATION LIBÉRATION -->
                        <div v-if="manageStep === 'release'" class="flex flex-col gap-6">
                            <div class="p-4 bg-red-50 rounded-2xl border border-red-100">
                                <div class="flex gap-3 text-red-700">
                                    <i class="pi pi-exclamation-triangle text-xl" />
                                    <div>
                                        <p class="font-bold">Attention : Règle Zéro Orphelin</p>
                                        <p class="text-sm mt-1">
                                            {{ selectedAssignment.position.code === 'TC' 
                                                ? 'Cette action retirera définitivement ce téléconseiller de la campagne.' 
                                                : 'La libération d\'un responsable entraînera la libération automatique de tous ses subordonnés pour garantir l\'intégrité de la hiérarchie.' }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="font-bold text-slate-900 block mb-1">Motif de la libération</label>
                                <textarea v-model="manageData.reason" rows="3" placeholder="Précisez la raison..." class="w-full rounded-xl border border-slate-200 p-3" />
                            </div>
                        </div>
                    </div>
                </div>

                <template #footer>
                    <div class="px-8 pb-8 flex justify-between gap-3">
                        <Button v-if="manageStep !== 'choice'" label="Retour" severity="secondary" text @click="manageStep = 'choice'" class="rounded-xl px-6" />
                        <div v-else></div>
                        
                        <div class="flex gap-3">
                            <Button label="Annuler" severity="secondary" text @click="manageDialog = false" class="rounded-xl px-6" />
                            <Button 
                                v-if="manageStep !== 'choice'"
                                :label="manageStep === 'transfer' ? 'Confirmer le transfert' : 'Confirmer la libération'" 
                                :severity="manageStep === 'transfer' ? 'info' : 'danger'"
                                icon="pi pi-check" 
                                @click="executeManage" 
                                class="rounded-xl px-8 border-none shadow-lg"
                                :class="manageStep === 'transfer' ? 'bg-blue-600 shadow-blue-200' : 'bg-red-600 shadow-red-200'"
                            />
                        </div>
                    </div>
                </template>
            </Dialog>
        </div>
    </AppLayout>
</template>

<style scoped>
:deep(.p-tabview-nav) {
    border: none;
}
:deep(.p-tabs-tab) {
    border-bottom: 2px solid transparent;
    transition: all 0.3s;
    color: #64748b;
    font-weight: 600;
}
:deep(.p-tabs-tab-active) {
    color: #2563eb;
    border-color: #2563eb;
}
:deep(.p-tabs-tab:not(.p-tabs-tab-active):hover) {
    color: #1e293b;
    border-color: #e2e8f0;
}
:deep(.p-dialog-header) {
    border: none;
}
</style>
