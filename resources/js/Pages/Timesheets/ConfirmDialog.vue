<script setup>
import { router } from "@inertiajs/vue3";
import Dialog from "primevue/dialog";
import Button from "primevue/button";

const props = defineProps({
    visible: Boolean,
    timesheetId: [Number, String],
    employeeName: String
});

const emit = defineEmits(["update:visible"]);

// Fonction pour fermer la modal
const close = () => {
    emit("update:visible", false);
};

// Action de validation finale par le Chef de Plateau
const handleConfirm = () => {
    // Appel de la méthode submit du TimesheetController
    router.post(`/timesheets/${props.timesheetId}/submit`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            close();
        },
    });
};
</script>

<template>
    <Dialog 
        :visible="visible" 
        @update:visible="close" 
        header="Validation Hiérarchique" 
        :modal="true" 
        :style="{ width: '400px' }"
        :closable="false"
        class="custom-confirm-dialog"
    >
        <div class="flex flex-col items-center p-2 text-center">
            <!-- Icône Bouclier / Succès -->
            <div class="w-16 h-16 bg-emerald-50 text-emerald-600 rounded-full flex items-center justify-center mb-4 border border-emerald-100 shadow-inner">
                <svg xmlns="http://w3.org" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
            </div>
            
            <h3 class="text-lg font-black text-gray-900 mb-1 uppercase tracking-tight">Clôturer la semaine ?</h3>
            
            <p class="text-sm text-gray-500 mb-6 px-4 leading-relaxed">
                En tant que <span class="font-bold text-emerald-700 underline decoration-2">Chef de Plateau</span>, vous allez soumettre les heures de <span class="font-bold text-gray-800">{{ employeeName }}</span>. 
                <br>
                <span class="text-red-500 font-bold italic text-[11px] mt-2 block bg-red-50 p-1 rounded">
                    <i class="pi pi-exclamation-circle mr-1"></i>
                    ACTION IRRÉVERSIBLE : Verrouillage total pour le superviseur.
                </span>
            </p>

            <div class="flex gap-3 w-full">
                <!-- Bouton Annuler -->
                <Button 
                    label="Annuler" 
                    class="p-button-text p-button-secondary flex-1 font-bold" 
                    @click="close" 
                />
                <!-- Bouton Confirmer -->
                <Button 
                    label="Confirmer" 
                    class="p-button-success flex-1 font-black shadow-lg uppercase text-xs" 
                    @click="handleConfirm" 
                />
            </div>
        </div>
    </Dialog>
</template>

<style scoped>
/* Style pour s'assurer que la modal PrimeVue respecte le design épuré */
:deep(.p-dialog-header) {
    padding: 1rem;
    font-size: 0.9rem;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}
:deep(.p-dialog-content) {
    padding: 1.5rem 1rem;
}
</style>
