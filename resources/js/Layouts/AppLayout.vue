<script setup>
import { ref, computed } from "vue";
import { Link, usePage } from "@inertiajs/vue3";
import ApplicationLogo from "@/Components/ApplicationLogo.vue";
import Dropdown from "@/Components/Dropdown.vue";
import DropdownLink from "@/Components/DropdownLink.vue";
import {
    LayoutDashboard,
    Users,
    Megaphone,
    Calendar,
    Clock,
    BarChart3,
    UserPlus,
    ChevronDown,
} from "lucide-vue-next";

const page = usePage();
const activeMainMenu = ref("dashboard");
const isHoveringSidebar = ref(false);

const menuConfig = {
    admin: {
        main: [
            { id: "dashboard", label: "Tableau de bord", icon: LayoutDashboard },
            { id: "users", label: "Utilisateurs", icon: Users },
            { id: "employees", label: "Employés", icon: UserPlus },
            { id: "campaigns", label: "Campagnes", icon: Megaphone },
            { id: "planning", label: "Planning", icon: Calendar },
            { id: "timesheets", label: "Feuilles de temps", icon: Clock },
            { id: "reports", label: "Rapports", icon: BarChart3 },
        ],
        sub: {
            dashboard: [{ label: "Vue générale", href: "/dashboard/admin" }],
            users: [
                { label: "Liste des utilisateurs", href: "/users" },
                { label: "Ajouter un utilisateur", href: "/users/create" },
            ],
            employees: [
                { label: "Liste des employés", href: "/employees" },
                { label: "Ajouter un employé", href: "/employees/create" },
            ],
            campaigns: [
                { label: "Liste des campagnes", href: "/campaigns" },
                { label: "Ajouter une campagne", href: "/campaigns/create" },
            ],
            planning: [
                { label: "Modèles de planning", href: "/planning/models" },
                { label: "Affectations", href: "/planning/assignments" },
                { label: "Historique", href: "/planning/history" },
            ],
            timesheets: [
                { label: "Validation", href: "/timesheets/validate" },
                { label: "Historique", href: "/timesheets/history" },
            ],
            reports: [
                { label: "Présence", href: "/reports/attendance" },
                { label: "Productivité", href: "/reports/productivity" },
            ],
        },
    },
    cp: {
        main: [
            { id: "dashboard", label: "Tableau de bord", icon: LayoutDashboard },
            { id: "employees", label: "Équipe", icon: Users },
            { id: "campaigns", label: "Campagnes", icon: Megaphone },
            { id: "planning", label: "Planning", icon: Calendar },
            { id: "timesheets", label: "Feuilles de temps", icon: Clock },
        ],
        sub: {
            dashboard: [{ label: "Vue générale", href: "/dashboard/cp" }],
            employees: [{ label: "Mon équipe", href: "/employees" }],
            campaigns: [{ label: "Campagnes actives", href: "/campaigns" }],
            planning: [{ label: "Gérer le planning", href: "/planning" }],
            timesheets: [{ label: "Valider les feuilles", href: "/timesheets/validate" }],
        },
    },
    sup: {
        main: [
            { id: "dashboard", label: "Tableau de bord", icon: LayoutDashboard },
            { id: "team", label: "Mon équipe", icon: Users },
            { id: "timesheets", label: "Feuilles de temps", icon: Clock },
        ],
        sub: {
            dashboard: [{ label: "Vue générale", href: "/dashboard/sup" }],
            team: [{ label: "Liste des agents", href: "/employees" }],
            timesheets: [{ label: "Valider", href: "/timesheets/validate" }],
        },
    },
    tc: {
        main: [
            { id: "dashboard", label: "Tableau de bord", icon: LayoutDashboard },
            { id: "timesheets", label: "Ma feuille de temps", icon: Clock },
            { id: "planning", label: "Mon planning", icon: Calendar },
        ],
        sub: {
            dashboard: [{ label: "Vue générale", href: "/dashboard/tc" }],
            timesheets: [
                { label: "Saisir mes heures", href: "/timesheets/create" },
                { label: "Historique", href: "/timesheets" },
            ],
            planning: [{ label: "Voir mon planning", href: "/planning" }],
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

const hasSubMenuVisible = computed(() => {
    return currentSubMenu.value.length > 1;
});

const sidebarWidth = computed(() => {
    return isHoveringSidebar.value ? "w-64" : "w-20";
});

const handleMainMenuClick = (itemId) => {
    activeMainMenu.value = itemId;
};
</script>

<template>
    <div class="flex h-screen bg-slate-50 font-sans text-slate-900 overflow-hidden">

        <aside
            :class="[
                'bg-white border-r border-slate-200 flex flex-col transition-all duration-300 z-[70] shrink-0 shadow-lg',
                sidebarWidth,
            ]"
            @mouseenter="isHoveringSidebar = true"
            @mouseleave="isHoveringSidebar = false"
        >
            <div class="p-4 border-b border-slate-100 flex items-center bg-blue-600 h-20 shrink-0">
                <Link :href="route('dashboard')" class="flex items-center gap-3">
                    <ApplicationLogo class="h-8 w-auto fill-current text-white shrink-0" />
                    <span v-show="isHoveringSidebar" class="text-xl font-black text-white whitespace-nowrap uppercase tracking-tight">WipLite</span>
                </Link>
            </div>

            <nav class="flex-1 p-3 space-y-2 overflow-y-auto scrollbar-hide">
                <button
                    v-for="item in currentMenu.main"
                    :key="item.id"
                    @click="handleMainMenuClick(item.id)"
                    :class="[
                        'w-full flex items-center gap-4 px-4 py-3.5 rounded-2xl transition-all duration-200 group relative',
                        activeMainMenu === item.id
                            ? 'bg-blue-600 text-white shadow-md'
                            : 'text-slate-500 hover:bg-slate-50 hover:text-blue-600',
                    ]"
                >
                    <component :is="item.icon" class="w-6 h-6 shrink-0" />
                    <span v-show="isHoveringSidebar" class="font-bold text-sm whitespace-nowrap">{{ item.label }}</span>

                    <div v-show="!isHoveringSidebar" class="absolute left-full ml-4 px-3 py-2 bg-slate-800 text-white text-[10px] font-black uppercase rounded-lg opacity-0 group-hover:opacity-100 pointer-events-none whitespace-nowrap z-[100]">
                        {{ item.label }}
                    </div>
                </button>
            </nav>
        </aside>

        <aside
            v-if="hasSubMenuVisible"
            class="bg-white border-r border-slate-200 flex flex-col transition-all duration-300 shadow-sm z-40 w-72 shrink-0 overflow-hidden"
        >
            <div class="p-6 border-b border-slate-100 h-20 flex items-center bg-slate-50/50 text-slate-800 font-black uppercase text-[11px] tracking-[0.2em]">
                {{ currentMenu.main.find((m) => m.id === activeMainMenu)?.label }}
            </div>

            <nav class="flex-1 p-4 space-y-2 overflow-y-auto">
                <Link
                    v-for="item in currentSubMenu"
                    :key="item.href"
                    :href="item.href"
                    :class="[
                        'block px-6 py-4 rounded-2xl transition-all duration-200 font-black text-base',
                        page.url.startsWith(item.href)
                            ? 'bg-blue-600 text-white shadow-blue-200 shadow-lg scale-[1.02]'
                            : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900'
                    ]"
                >
                    {{ item.label }}
                </Link>
            </nav>
        </aside>

        <main class="flex-1 flex flex-col overflow-hidden bg-white">

            <header class="bg-white border-b border-slate-200 px-8 h-20 flex items-center justify-between shrink-0 z-30">
                <div class="flex-1">
                    <slot name="header" />
                </div>

                <div class="flex items-center gap-4 ml-4">
                    <Dropdown align="right" width="48">
                        <template #trigger>
                            <button class="flex items-center gap-3 p-1.5 pl-4 rounded-2xl hover:bg-slate-50 border border-transparent hover:border-slate-100 transition-all outline-none group">
                                <div class="text-right hidden sm:block">
                                    <p class="text-xs font-black text-slate-800 leading-none truncate max-w-[150px]">
                                        {{ page.props.auth.user?.name }}
                                    </p>
                                    <p class="text-[10px] text-blue-600 font-bold uppercase tracking-widest mt-1">
                                        {{ page.props.auth.role }}
                                    </p>
                                </div>

                                <div class="relative">
                                    <div class="w-10 h-10 rounded-xl bg-slate-900 flex items-center justify-center text-white font-black shadow-sm group-hover:scale-105 transition-transform">
                                        {{ page.props.auth.user?.name?.charAt(0).toUpperCase() }}
                                    </div>
                                    <div class="absolute -bottom-1 -right-1 w-4 h-4 bg-green-500 border-2 border-white rounded-full"></div>
                                </div>
                                <ChevronDown class="w-4 h-4 text-slate-400 group-hover:text-blue-600 transition-colors" />
                            </button>
                        </template>

                        <template #content>
                            <div class="p-2 space-y-1">
                                <div class="px-3 py-2 sm:hidden border-b border-slate-50 mb-1">
                                    <p class="text-xs font-black text-slate-800 truncate">{{ page.props.auth.user?.name }}</p>
                                    <p class="text-[9px] text-blue-600 font-bold uppercase">{{ page.props.auth.role }}</p>
                                </div>
                                <DropdownLink :href="route('profile.edit')" class="!rounded-xl font-bold">
                                    Mon Profil
                                </DropdownLink>
                                <hr class="border-slate-50 my-1">
                                <DropdownLink :href="route('logout')" method="post" as="button" class="!rounded-xl font-bold text-red-600 w-full text-left">
                                    Déconnexion
                                </DropdownLink>
                            </div>
                        </template>
                    </Dropdown>
                </div>
            </header>

            <div class="flex-1 overflow-auto p-8 bg-slate-50/30">
                <slot />
            </div>
        </main>
    </div>
</template>

<style>
.scrollbar-hide::-webkit-scrollbar {
    display: none;
}
.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
