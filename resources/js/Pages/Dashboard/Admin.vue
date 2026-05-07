<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
<<<<<<< HEAD
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
  <Head title="Tableau de bord - Administrateur" />
  <AppLayout>
    <template #header>
      <h2 class="text-xl font-semibold leading-tight text-gray-800">Tableau de bord - Administrateur</h2>
    </template>
    <div class="py-12">
      <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
          <div class="p-6 text-gray-900">
            Bienvenue sur le tableau de bord Administrateur !
        </div>
        </div>
      </div>
    </div>
  </AppLayout>
=======
defineProps({ stats: Object, campaignStats: Array, planningGaps: Object });
</script>

<template>
<AppLayout>

<template #header>
<h2 class="text-3xl font-black text-slate-800">
Dashboard Admin
</h2>
</template>

<div class="grid grid-cols-1 md:grid-cols-4 gap-6">

<div class="bg-white p-6 rounded-3xl shadow-sm">
<p>Employés</p>
<h1 class="text-4xl font-black">{{ stats.employees }}</h1>
</div>

<div class="bg-white p-6 rounded-3xl shadow-sm">
<p>Campagnes</p>
<h1 class="text-4xl font-black">{{ stats.campaigns }}</h1>
</div>

<div class="bg-white p-6 rounded-3xl shadow-sm">
<p>Affectations</p>
<h1 class="text-4xl font-black">{{ stats.assignments }}</h1>
</div>

<div class="bg-white p-6 rounded-3xl shadow-sm">
<p>Heures</p>
<h1 class="text-4xl font-black">{{ stats.workedHours }}</h1>
</div>

</div>

</AppLayout>
>>>>>>> 34f8aa01a52cef7da66c986867cb99e60d5f6441
</template>
