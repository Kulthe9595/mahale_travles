<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { rupees } from '../../http.js';

defineProps({ customer: Object, bills: Array, outstanding: String });
</script>

<template>
    <Head :title="customer.name" />
    <AppLayout>
        <div class="d-flex justify-content-between align-items-start mb-3">
            <div>
                <h1 class="h4 mb-1">{{ customer.name }}</h1>
                <div class="small text-muted">
                    <a v-if="customer.phone" :href="`tel:${customer.phone}`">{{ customer.phone }}</a>
                    <span v-if="customer.gstin"> · GSTIN {{ customer.gstin }}</span>
                    <span v-if="customer.state"> · {{ customer.state }}</span>
                </div>
            </div>
            <Link :href="`/customers/${customer.id}/edit`" class="btn btn-sm btn-outline-primary">Edit</Link>
        </div>
        <div class="card card-body mb-3">
            <div class="small text-muted">Outstanding</div>
            <div class="fs-4 fw-bold money" :class="Number(outstanding) > 0 ? 'text-danger' : 'text-success'">{{ rupees(outstanding) }}</div>
        </div>
        <div class="list-group">
            <Link v-for="b in bills" :key="b.trip_id" :href="`/trips/${b.trip_id}`" class="list-group-item list-group-item-action">
                <div class="d-flex justify-content-between">
                    <span class="fw-semibold">{{ b.invoice_no }}</span>
                    <span class="money">{{ rupees(b.total) }}</span>
                </div>
                <div class="d-flex justify-content-between small text-muted">
                    <span>{{ b.date }} · {{ b.car }} · {{ b.route }}</span>
                    <span v-if="Number(b.balance) > 0" class="text-danger">Due {{ rupees(b.balance) }}</span>
                    <span v-else class="text-success">Paid</span>
                </div>
            </Link>
            <div v-if="!bills.length" class="list-group-item text-muted">No bills yet.</div>
        </div>
    </AppLayout>
</template>
