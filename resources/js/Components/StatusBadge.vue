<script setup>
import { computed } from 'vue';

const props = defineProps({ status: String });
const map = {
    unpaid: ['Unpaid', 'text-bg-danger'],
    partly_paid: ['Partly paid', 'text-bg-warning'],
    paid: ['Paid', 'text-bg-success'],
    booked: ['Booked', 'text-bg-info'],
    ongoing: ['Ongoing', 'text-bg-primary'],
    completed: ['Completed', 'text-bg-secondary'],
    cancelled: ['Cancelled', 'text-bg-dark'],
};
const badge = computed(() => map[props.status] ?? ['No bill', 'text-bg-light border']);
</script>

<template>
    <span class="badge" :class="badge[1]">{{ badge[0] }}</span>
</template>
