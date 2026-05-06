<script setup>
import { ref, computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';

const page = usePage();
const activeMainMenu = ref('dashboard');

const menuConfig = {
  admin: {
    main: [
      { id: 'dashboard', label: 'Tableau de bord', icon: 'dashboard' },
      { id: 'employees', label: 'Employés', icon: 'users' },
      { id: 'campaigns', label: 'Campagnes', icon: 'megaphone' },
      { id: 'planning', label: 'Planning', icon: 'calendar' },
      { id: 'timesheets', label: 'Feuilles de temps', icon: 'clock' },
      { id: 'reports', label: 'Rapports', icon: 'chart-bar' },
    ],
    sub: {
      dashboard: [
        { label: 'Vue générale', href: '/dashboard/admin' },
      ],
      employees: [
        { label: 'Liste des employés', href: '/employees' },
        { label: 'Ajouter un employé', href: '/employees/create' },
      ],
      campaigns: [
        { label: 'Liste des campagnes', href: '/campaigns' },
        { label: 'Ajouter une campagne', href: '/campaigns/create' },
      ],
      planning: [
        { label: 'Modèles de planning', href: '/planning/models' },
        { label: 'Affectations', href: '/planning/assignments' },
      ],
      timesheets: [
        { label: 'Validation', href: '/timesheets/validate' },
        { label: 'Historique', href: '/timesheets/history' },
      ],
      reports: [
        { label: 'Présence', href: '/reports/attendance' },
        { label: 'Productivité', href: '/reports/productivity' },
      ],
    },
  },
  cp: {
    main: [
      { id: 'dashboard', label: 'Tableau de bord', icon: 'dashboard' },
      { id: 'employees', label: 'Équipe', icon: 'users' },
      { id: 'campaigns', label: 'Campagnes', icon: 'megaphone' },
      { id: 'planning', label: 'Planning', icon: 'calendar' },
      { id: 'timesheets', label: 'Feuilles de temps', icon: 'clock' },
    ],
    sub: {
      dashboard: [
        { label: 'Vue générale', href: '/dashboard/cp' },
      ],
      employees: [
        { label: 'Mon équipe', href: '/employees' },
      ],
      campaigns: [
        { label: 'Campagnes actives', href: '/campaigns' },
      ],
      planning: [
        { label: 'Gérer le planning', href: '/planning' },
      ],
      timesheets: [
        { label: 'Valider les feuilles', href: '/timesheets/validate' },
      ],
    },
  },
  sup: {
    main: [
      { id: 'dashboard', label: 'Tableau de bord', icon: 'dashboard' },
      { id: 'team', label: 'Mon équipe', icon: 'users' },
      { id: 'timesheets', label: 'Feuilles de temps', icon: 'clock' },
    ],
    sub: {
      dashboard: [
        { label: 'Vue générale', href: '/dashboard/sup' },
      ],
      team: [
        { label: 'Liste des agents', href: '/employees' },
      ],
      timesheets: [
        { label: 'Valider', href: '/timesheets/validate' },
      ],
    },
  },
  tc: {
    main: [
      { id: 'dashboard', label: 'Tableau de bord', icon: 'dashboard' },
      { id: 'timesheets', label: 'Ma feuille de temps', icon: 'clock' },
      { id: 'planning', label: 'Mon planning', icon: 'calendar' },
    ],
    sub: {
      dashboard: [
        { label: 'Vue générale', href: '/dashboard/tc' },
      ],
      timesheets: [
        { label: 'Saisir mes heures', href: '/timesheets/create' },
        { label: 'Historique', href: '/timesheets' },
      ],
      planning: [
        { label: 'Voir mon planning', href: '/planning' },
      ],
    },
  },
};

const currentMenu = computed(() => {
  const role = page.props.auth.role;
  return menuConfig[role] || menuConfig.tc;
});

const currentSubMenu = computed(() => {
  return currentMenu.value.sub[activeMainMenu.value] || [];
});
</script>

<template>
  <div class="flex h-screen bg-gray-100">
    <!-- Sidebar principale -->
    <aside class="w-64 bg-gray-900 text-white flex flex-col">
      <div class="p-4 border-b border-gray-700">
        <Link :href="route('dashboard')" class="flex items-center gap-2">
          <ApplicationLogo class="h-8 w-auto fill-current text-white" />
          <span class="text-xl font-bold">WipLite</span>
        </Link>
      </div>
      <nav class="flex-1 p-4 space-y-2">
        <button
          v-for="item in currentMenu.main"
          :key="item.id"
          @click="activeMainMenu = item.id"
          :class="[
            'w-full flex items-center gap-3 px-4 py-3 rounded-lg transition-colors',
            activeMainMenu === item.id
              ? 'bg-blue-600 text-white'
              : 'text-gray-300 hover:bg-gray-800 hover:text-white'
          ]"
        >
          <span class="text-lg">{{ item.icon }}</span>
          <span class="font-medium">{{ item.label }}</span>
        </button>
      </nav>
      <div class="p-4 border-t border-gray-700">
        <Dropdown align="left" width="48">
          <template #trigger>
            <button class="w-full flex items-center gap-3 px-4 py-3 rounded-lg text-gray-300 hover:bg-gray-800 hover:text-white transition-colors">
              <div class="w-10 h-10 rounded-full bg-blue-600 flex items-center justify-center">
                {{ page.props.auth.user?.name?.charAt(0)?.toUpperCase() || 'U' }}
              </div>
              <div class="text-left flex-1">
                <div class="font-medium text-sm">{{ page.props.auth.user?.name }}</div>
                <div class="text-xs text-gray-400">{{ page.props.auth.role }}</div>
              </div>
            </button>
          </template>
          <template #content>
            <DropdownLink :href="route('profile.edit')">
              Profile
            </DropdownLink>
            <DropdownLink :href="route('logout')" method="post" as="button">
              Log Out
            </DropdownLink>
          </template>
        </Dropdown>
      </div>
    </aside>

    <!-- Sidebar secondaire -->
    <aside class="w-56 bg-white border-r border-gray-200 flex flex-col" v-if="currentSubMenu.length > 0">
      <div class="p-4 border-b border-gray-200">
        <h3 class="font-semibold text-gray-700">
          {{ currentMenu.main.find(m => m.id === activeMainMenu)?.label }}
        </h3>
      </div>
      <nav class="flex-1 p-4 space-y-1">
        <Link
          v-for="item in currentSubMenu"
          :key="item.href"
          :href="item.href"
          class="block px-4 py-2 rounded-lg text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors"
        >
          {{ item.label }}
        </Link>
      </nav>
    </aside>

    <!-- Contenu principal -->
    <main class="flex-1 flex flex-col overflow-hidden">
      <header class="bg-white border-b border-gray-200 px-6 py-4">
        <slot name="header" />
      </header>
      <div class="flex-1 overflow-auto p-6">
        <slot />
      </div>
    </main>
  </div>
</template>
