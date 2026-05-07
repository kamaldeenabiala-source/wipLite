<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    employeeHistory:Array
})

</script>


<template>

<AppLayout>
    <pre>{{ employeeHistory }}</pre>
</AppLayout>

</template>


<style scoped>


</style>