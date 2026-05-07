<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import { ref } from "vue";
import { router } from "@inertiajs/vue3";
import { useToast } from "primevue/usetoast";
import { useConfirm } from "primevue/useconfirm";

import DataTable from "primevue/datatable";
import Column from "primevue/column";
import Button from "primevue/button";
import Toolbar from "primevue/toolbar";
import IconField from "primevue/iconfield";
import InputIcon from "primevue/inputicon";
import InputText from "primevue/inputtext";
import Tag from "primevue/tag";

// ---------------------------------------------------------
// PROPS — données injectées par le controller via Inertia
// ---------------------------------------------------------
const props = defineProps({
    employees: Object, // paginated (meta, data, links)
    positions: Array,
    filters: Object,
});

// ---------------------------------------------------------
// INSTANCES
// ---------------------------------------------------------
const toast = useToast();
const confirm = useConfirm();
const dt = ref();

// ---------------------------------------------------------
// FILTRES DataTable (recherche globale côté client)
// ---------------------------------------------------------
const search = ref(props.filters?.search ?? '');

const onSearch = () => {
    router.get(route('employees.index'), {
        search: search.value,
        page: 1,
    }, { preserveState: true, replace: true });
}
// ---------------------------------------------------------
// STATUT — badge coloré
// ---------------------------------------------------------
const getStatusSeverity = (status) => {
    switch (status) {
        case "actif":
            return "success";
        case "suspendu":
            return "warn";
        case "inactif":
            return "danger";
        default:
            return null;
    }
};

// ---------------------------------------------------------
// EXPORT CSV
// ---------------------------------------------------------
const exportCSV = () => dt.value.exportCSV();

// ---------------------------------------------------------
// NAVIGATION — vers le formulaire de création
// ---------------------------------------------------------
const openCreate = () => router.visit(route("employees.create"));

// ---------------------------------------------------------
// NAVIGATION — vers la fiche employé (edit)
// ---------------------------------------------------------
const openEdit = (employee) =>
    router.visit(route("employees.edit", employee.id));

// ---------------------------------------------------------
// SUPPRESSION — confirmation puis appel Inertia DELETE
// ---------------------------------------------------------
const confirmDelete = (employee) => {
    confirm.require({
        message: `Voulez-vous archiver ${employee.first_name} ${employee.last_name} ?`,
        header: "Confirmation",
        icon: "pi pi-exclamation-triangle",
        rejectProps: {
            label: "Annuler",
            severity: "secondary",
            variant: "text",
        },
        acceptProps: { label: "Archiver", severity: "danger" },
        accept: () => {
            router.delete(route("employees.destroy", employee.id), {
                onSuccess: () =>
                    toast.add({
                        severity: "success",
                        summary: "Archivé",
                        detail: `${employee.first_name} ${employee.last_name} a été archivé.`,
                        life: 3000,
                    }),
            });
        },
    });
};

// ---------------------------------------------------------
// QUAND LA PAGE CHANGE
// ---------------------------------------------------------
const onPageChange = (event) => {
    router.get(route('employees.index'), {
        page: event.page + 1,
        search: search.value, 
    }, { preserveState: true });
};
</script>

<template>
    <AppLayout>
        <div class="card">
            <!-- TOOLBAR -->
            <Toolbar class="mb-6">
                <template #start>
                    <Button
                        label="Nouvel employé"
                        icon="pi pi-plus"
                        class="mr-2"
                        @click="openCreate"
                    />
                </template>
                <template #end>
                    <Button
                        label="Exporter"
                        icon="pi pi-upload"
                        severity="secondary"
                        @click="exportCSV"
                    />
                </template>
            </Toolbar>

            <!-- DATATABLE -->
            <DataTable
                :value="employees.data"
                :lazy="true"
                :paginator="true"
                :rows="employees.per_page"
                :totalRecords="employees.total"
                @page="onPageChange"
            >
                <!-- HEADER — recherche globale -->
                <template #header>
                    <div
                        class="flex flex-wrap gap-2 items-center justify-between"
                    >
                        <h4 class="m-0">Liste des employés</h4>
                        <IconField>
                            <InputIcon>
                                <i class="pi pi-search" />
                            </InputIcon>
                            <InputText v-model="search" placeholder="Rechercher..." @keyup.enter="onSearch" />
                        </IconField>
                    </div>
                </template>

                <!-- COLONNES -->
                <Column
                    field="matricule"
                    header="Matricule"
                    sortable
                    style="min-width: 10rem"
                />
                <Column header="Nom complet" sortable style="min-width: 14rem">
                    <template #body="{ data }">
                        {{ data.first_name }} {{ data.last_name }}
                    </template>
                </Column>
                <Column header="Poste" sortable style="min-width: 10rem">
                    <template #body="{ data }">
                        {{ data.position?.name ?? "—" }}
                    </template>
                </Column>
                <Column
                    field="email"
                    header="Email"
                    sortable
                    style="min-width: 14rem"
                />
                <Column
                    field="phone"
                    header="Téléphone"
                    style="min-width: 12rem"
                />
                <Column
                    field="status"
                    header="Statut"
                    sortable
                    style="min-width: 10rem"
                >
                    <template #body="{ data }">
                        <Tag
                            :value="data.status"
                            :severity="getStatusSeverity(data.status)"
                        />
                    </template>
                </Column>

                <!-- ACTIONS -->
                <Column :exportable="false" style="min-width: 10rem">
                    <template #body="{ data }">
                        <Button
                            icon="pi pi-pencil"
                            variant="outlined"
                            rounded
                            class="mr-2"
                            @click="openEdit(data)"
                        />
                        <Button
                            icon="pi pi-trash"
                            variant="outlined"
                            rounded
                            severity="danger"
                            @click="confirmDelete(data)"
                        />
                    </template>
                </Column>
            </DataTable>
        </div>

        <!-- CONFIRMATION DIALOG (géré par useConfirm) -->
        <ConfirmDialog />
    </AppLayout>
</template>
