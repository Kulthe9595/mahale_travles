<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { rupees } from '../../http.js';

defineProps({ rows: Array, total: String });
</script>

<template>
    <Head title="Outstanding" />
    <AppLayout title="Outstanding">
        <div class="card card-body mb-3">
            <div class="small text-muted">Total pending</div>
            <div class="fs-4 fw-bold text-danger money">{{ rupees(total) }}</div>
        </div>
        <div class="list-group">
            <Link v-for="r in rows" :key="r.trip_id" :href="`/trips/${r.trip_id}`" class="list-group-item list-group-item-action">
                <div class="d-flex justify-content-between">
                    <span class="fw-semibold">{{ r.customer }}</span>
                    <span class="fw-bold text-danger money">{{ rupees(r.balance) }}</span>
                </div>
                <div class="small text-muted">
                    {{ r.invoice_no }} · {{ r.invoice_date }} · {{ r.age_days }} day(s) ·
                    paid {{ rupees(r.paid) }} of {{ rupees(r.total) }}
                </div>
            </Link>
            <div v-if="!rows.length" class="list-group-item text-muted">Nothing pending. 🎉</div>
        </div>
    </AppLayout>
</template>
