<script setup>
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import { rupees } from '../../http.js';

const props = defineProps({ from: String, to: String, rows: Array, totals: Object });
const from = ref(props.from);
const to = ref(props.to);
const load = () => router.get('/reports/gst', { from: from.value, to: to.value }, { preserveState: true });
const exportUrl = computed(() => `/reports/gst/export?from=${props.from}&to=${props.to}`);
</script>

<template>
    <Head title="GST summary" />
    <AppLayout title="GST summary">
        <div class="row g-2 mb-3 align-items-end">
            <div class="col-6 col-md-3"><label class="form-label small mb-0">From</label><input v-model="from" type="date" class="form-control" @change="load"></div>
            <div class="col-6 col-md-3"><label class="form-label small mb-0">To</label><input v-model="to" type="date" class="form-control" @change="load"></div>
            <div class="col-12 col-md-3 d-grid"><a :href="exportUrl" class="btn btn-success">Download Excel</a></div>
        </div>
        <div class="table-responsive card">
            <table class="table table-sm mb-0 small">
                <thead><tr><th>Invoice</th><th>Date</th><th>Customer</th><th>GSTIN</th><th class="text-end">Taxable</th><th class="text-end">CGST</th><th class="text-end">SGST</th><th class="text-end">IGST</th><th class="text-end">Total</th></tr></thead>
                <tbody>
                    <tr v-for="r in rows" :key="r.invoice_no">
                        <td class="text-nowrap">{{ r.invoice_no }}</td><td class="text-nowrap">{{ r.invoice_date }}</td>
                        <td>{{ r.customer }}</td><td>{{ r.gstin || '—' }}</td>
                        <td class="text-end money">{{ rupees(r.taxable) }}</td><td class="text-end money">{{ rupees(r.cgst) }}</td>
                        <td class="text-end money">{{ rupees(r.sgst) }}</td><td class="text-end money">{{ rupees(r.igst) }}</td>
                        <td class="text-end money">{{ rupees(r.total) }}</td>
                    </tr>
                    <tr v-if="!rows.length"><td colspan="9" class="text-muted">No GST bills in this period.</td></tr>
                </tbody>
                <tfoot class="fw-bold">
                    <tr><td colspan="4">Total</td>
                        <td class="text-end money">{{ rupees(totals.taxable) }}</td><td class="text-end money">{{ rupees(totals.cgst) }}</td>
                        <td class="text-end money">{{ rupees(totals.sgst) }}</td><td class="text-end money">{{ rupees(totals.igst) }}</td>
                        <td class="text-end money">{{ rupees(totals.total) }}</td></tr>
                </tfoot>
            </table>
        </div>
    </AppLayout>
</template>
