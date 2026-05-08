<script setup>
import { computed } from "vue";
import { useForm } from "@inertiajs/vue3";
import Button from "primevue/button";
import InputText from "primevue/inputtext";
import Calendar from "primevue/calendar";

const props = defineProps({
    data: Object, // Contient employee_id, employee_ids (bulk), employee_name, date, entry, status, role, isBulk
});

const emit = defineEmits(["close"]);

// --- LOGIQUE DE VERROUILLAGE SELON TA MATRICE ---
const isLocked = computed(() => {
    // 1. Le Téléconseiller (TC) est TOUJOURS en lecture seule
    if (props.data.role === 'TC') return true;
    // 2. Si le statut est 'soumis', personne ne modifie (même le SUP ou CP)
    if (props.data.status === 'soumis') return true;
    // 3. Sinon (Brouillon/Valide), le SUP et le CP peuvent modifier
    return false;
});

// --- INITIALISATION DU FORMULAIRE ---
const form = useForm({
    // Gestion hybride : simple ou groupé
    employee_id: props.data.isBulk ? null : props.data.employee_id,
    employee_ids: props.data.isBulk ? props.data.employee_ids : null,
    
    timesheet_id: props.data.entry?.timesheet_id || props.data.timesheet_id,
    date: props.data.isBulk ? props.data.date[0] : props.data.date, // Prend le premier jour si Bulk
    
    check_in: props.data.entry?.check_in 
        ? new Date(`2000-01-01 ${props.data.entry.check_in}`) 
        : null,
    check_out: props.data.entry?.check_out 
        ? new Date(`2000-01-01 ${props.data.entry.check_out}`) 
        : null,
    break_duration: props.data.entry?.break_duration || 0,
    comment: props.data.entry?.comment || "",
});

// Transformation des objets Date en format HH:mm pour Laravel
const formatToHHmm = (date) => {
    if (!date) return null;
    const d = new Date(date);
    return d.getHours().toString().padStart(2, "0") + ":" + 
           d.getMinutes().toString().padStart(2, "0");
};

const submit = () => {
    if (isLocked.value) return;

    form.transform((data) => ({
        ...data,
        check_in: formatToHHmm(data.check_in),
        check_out: formatToHHmm(data.check_out),
    })).post("/timesheet-entries", {
        preserveScroll: true,
        onSuccess: () => emit("close"),
    });
};
</script>

<template>
    <div class="p-fluid grid gap-4">
        <!-- Header Info -->
        <div class="mb-1 p-3 rounded bg-gray-50 border flex justify-between items-center shadow-sm">
            <div>
                <div class="font-black text-gray-900 text-sm uppercase tracking-tight">
                    {{ data.employee_name }}
                </div>
                <div class="text-[10px] text-gray-500 font-bold uppercase">
                    {{ data.isBulk ? 'Saisie multi-employés' : 'Journée du ' + data.date }}
                </div>
            </div>
            <div :class="isLocked ? 'text-red-600 bg-red-50 border-red-200' : 'text-blue-600 bg-blue-50 border-blue-200'" 
                 class="px-2 py-1 rounded text-[9px] font-black border uppercase italic">
                {{ isLocked ? 'Consultation' : 'Édition' }}
            </div>
        </div>

        <!-- Alerte si verrouillé -->
        <div v-if="isLocked" class="p-2 bg-amber-50 border border-amber-200 rounded text-[10px] text-amber-700 font-bold flex items-center gap-2">
            <i class="pi pi-lock"></i>
            Cette période est verrouillée ou votre profil ne permet pas la modification.
        </div>

        <!-- Champs de saisie -->
        <div class="field">
            <label class="font-black text-[10px] uppercase text-gray-500 mb-1 block">Heure d'Arrivée</label>
            <Calendar 
                v-model="form.check_in" 
                timeOnly 
                hourFormat="24" 
                :disabled="isLocked" 
                placeholder="--:--" 
                class="custom-calendar"
            />
        </div>

        <div class="field">
            <label class="font-black text-[10px] uppercase text-gray-500 mb-1 block">Heure de Départ</label>
            <Calendar 
                v-model="form.check_out" 
                timeOnly 
                hourFormat="24" 
                :disabled="isLocked" 
                placeholder="--:--" 
            />
        </div>

        <div class="field">
            <label class="font-black text-[10px] uppercase text-gray-500 mb-1 block">Durée Pause (Minutes)</label>
            <InputText 
                v-model="form.break_duration" 
                type="number" 
                :disabled="isLocked" 
                placeholder="Ex: 60"
            />
        </div>

        <div class="field">
            <label class="font-black text-[10px] uppercase text-gray-500 mb-1 block">Commentaire / Justification</label>
            <InputText 
                v-model="form.comment" 
                placeholder="Optionnel..." 
                :disabled="isLocked" 
            />
        </div>

        <!-- Actions -->
        <div class="flex justify-end gap-2 mt-4 pt-3 border-t">
            <Button 
                label="Fermer" 
                class="p-button-text p-button-secondary text-xs font-bold" 
                @click="$emit('close')" 
            />
            <Button 
                v-if="!isLocked" 
                :label="data.isBulk ? 'Appliquer à la sélection' : 'Enregistrer'" 
                icon="pi pi-check" 
                class="p-button-sm font-bold shadow-md" 
                :loading="form.processing" 
                @click="submit" 
            />
        </div>
    </div>
</template>

<style scoped>
/* Pour uniformiser avec le reste du calendrier */
:deep(.p-inputtext) {
    font-size: 0.85rem;
    font-weight: 600;
}
:deep(.p-disabled) {
    background-color: #fcfcfc !important;
    opacity: 0.7;
}
</style>
