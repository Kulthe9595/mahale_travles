<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import { rupees } from '../../http.js';

const props = defineProps({ month: String, rows: Array, totals: Object });
const month = ref(props.month);
const load = () => router.get('/reports/earnings', { month: month.value }, { preserveState: true });
</script>

<template>
    <Head title="Earnings per car" />
    <AppLayout title="Earnings per car">
        <div class="d-flex gap-2 mb-3" style="max-width: 320px">
            <input v-model="month" type="month" class="form-control" @change="load">
        </div>
        <div class="table-responsive card">
            <table class="table mb-0">
                <thead><tr><th>Car</th><th class="text-end">Trips</th><th class="text-end">Km</th><th class="text-end">Billed</th></tr></thead>
                <tbody>
                    <tr v-for="r in rows" :key="r.car">
                        <td>{{ r.car }}</td>
                        <td class="text-end">{{ r.trips }}</td>
                        <td class="text-end">{{ r.km.toLocaleString('en-IN') }}</td>
                        <td class="text-end money">{{ rupees(r.revenue) }}</td>
                    </tr>
                    <tr v-if="!rows.length"><td colspan="4" class="text-muted">No cars yet.</td></tr>
                </tbody>
                <tfoot class="fw-bold">
                    <tr><td>Total</td><td class="text-end">{{ totals.trips }}</td><td class="text-end">{{ totals.km.toLocaleString('en-IN') }}</td><td class="text-end money">{{ rupees(totals.revenue) }}</td></tr>
                </tfoot>
            </table>
        </div>
        <p class="small text-muted mt-2">Counts bills dated in the month; cancelled bills are left out.</p>
    </AppLayout>
</template>
