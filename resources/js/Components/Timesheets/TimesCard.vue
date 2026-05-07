<script setup>
import { reactive } from "vue";

import InputText from "primevue/inputtext";
import InputNumber from "primevue/inputnumber";
import Textarea from "primevue/textarea";
import Button from "primevue/button";
import Select from "primevue/select";

const props = defineProps({
    data: Object,
});

const form = reactive({
    timesheet_id: null,

    date: props.data?.date || null,

    check_in: null,

    check_out: null,

    break_duration: 0,

    total_hours: 0,

    planned_hours: 0,

    overtime_hours: 0,

    absence_type: null,

    comment: "",

    status: "brouillon",
});
</script>

<template>
    <div class="flex flex-col gap-4">

        <!-- CHECK IN -->

        <div>
            <label class="block mb-2 font-semibold"> Heure d'arrivée </label>

            <InputText v-model="form.check_in" placeholder="08:00" />
        </div>

        <!-- CHECK OUT -->

        <div>
            <label class="block mb-2 font-semibold"> Heure de départ </label>

            <InputText v-model="form.check_out" placeholder="17:00" />
        </div>

        <!-- BREAK -->

        <div>
            <label class="block mb-2 font-semibold"> Pause (minutes) </label>

            <InputNumber v-model="form.break_duration" />
        </div>

        <!-- TOTAL HOURS -->

        <div>
            <label class="block mb-2 font-semibold"> Total heures </label>

            <InputNumber v-model="form.total_hours" />
        </div>

        <!-- PLANNED HOURS -->

        <div>
            <label class="block mb-2 font-semibold"> Heures prévues </label>

            <InputNumber v-model="form.planned_hours" />
        </div>

        <!-- OVERTIME -->

        <div>
            <label class="block mb-2 font-semibold">
                Heures supplémentaires
            </label>

            <InputNumber v-model="form.overtime_hours" />
        </div>

        <!-- ABSENCE -->

        <div>
            <label class="block mb-2 font-semibold"> Type d'absence </label>

            <Select
                v-model="form.absence_type"
                :options="['Aucune', 'Congé', 'Maladie', 'Repos']"
                placeholder="Choisir"
            />
        </div>

        <!-- COMMENT -->

        <div>
            <label class="block mb-2 font-semibold"> Commentaire </label>

            <Textarea v-model="form.comment" rows="4" />
        </div>

        <!-- SAVE -->

        <Button label="Enregistrer" icon="pi pi-save" />
    </div>
</template>

<style scoped></style>
