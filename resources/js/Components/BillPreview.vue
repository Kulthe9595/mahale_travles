<script setup>
import { rupees } from '../http.js';

defineProps({ preview: Object, error: String, loading: Boolean });
</script>

<template>
    <div class="card">
        <div class="card-header fw-semibold">Bill preview</div>
        <div class="card-body p-0">
            <div v-if="error" class="p-3 text-danger small">{{ error }}</div>
            <div v-else-if="!preview" class="p-3 text-muted small">Enter start/end km and trip end time to see the total.</div>
            <table v-else class="table table-sm mb-0 small">
                <tbody>
                    <tr v-for="(l, i) in preview.lines" :key="i">
                        <td>{{ l.description }} <span class="text-muted" v-if="l.qty != 1">× {{ l.qty }}</span></td>
                        <td class="text-end money">{{ rupees(l.amount) }}</td>
                    </tr>
                    <template v-if="preview.tax_type === 'cgst_sgst'">
                        <tr><td>CGST</td><td class="text-end money">{{ rupees(preview.cgst) }}</td></tr>
                        <tr><td>SGST</td><td class="text-end money">{{ rupees(preview.sgst) }}</td></tr>
                    </template>
                    <tr v-if="preview.tax_type === 'igst'"><td>IGST</td><td class="text-end money">{{ rupees(preview.igst) }}</td></tr>
                    <tr v-if="Number(preview.round_off) !== 0"><td>Round off</td><td class="text-end money">{{ rupees(preview.round_off) }}</td></tr>
                    <tr class="fw-bold"><td>Total</td><td class="text-end money">{{ rupees(preview.total) }}</td></tr>
                    <tr class="text-muted"><td colspan="2">
                        {{ preview.actual_km }} km driven<span v-if="preview.billable_km > preview.actual_km">, billed {{ preview.billable_km }} km (minimum)</span>
                        · {{ preview.days }} day(s) · {{ preview.hours }} hrs
                    </td></tr>
                </tbody>
            </table>
        </div>
        <div v-if="loading" class="card-footer small text-muted py-1">Updating…</div>
    </div>
</template>
