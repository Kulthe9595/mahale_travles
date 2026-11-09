<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';
import StatusBadge from '../Components/StatusBadge.vue';
import { rupees } from '../http.js';

defineProps({ stats: Object, todayTrips: Array, upcomingTrips: Array });
</script>

<template>
    <Head title="Dashboard" />
    <AppLayout>
        <Link href="/trips/create" class="btn btn-brand btn-lg w-100 mb-3">+ New Bill</Link>

        <div class="row g-2 mb-3">
            <div class="col-6">
                <Link href="/reports/outstanding" class="card card-body text-decoration-none h-100">
                    <div class="small text-muted">Pending from customers</div>
                    <div class="fs-5 fw-bold text-danger money">{{ rupees(stats.pending) }}</div>
                </Link>
            </div>
            <div class="col-6">
                <Link href="/reports/earnings" class="card card-body text-decoration-none h-100">
                    <div class="small text-muted">Billed this month</div>
                    <div class="fs-5 fw-bold text-success money">{{ rupees(stats.month_revenue) }}</div>
                </Link>
            </div>
        </div>

        <div v-for="[title, list, empty] in [['Today', todayTrips, 'No trips today.'], ['Next 7 days', upcomingTrips, 'No upcoming bookings.']]"
             :key="title" class="card mb-3">
            <div class="card-header fw-semibold">{{ title }}</div>
            <div class="list-group list-group-flush">
                <Link v-for="t in list" :key="t.id" :href="`/trips/${t.id}`" class="list-group-item list-group-item-action">
                    <div class="d-flex justify-content-between">
                        <span class="fw-semibold">{{ t.customer }}</span>
                        <StatusBadge :status="t.status" />
                    </div>
                    <div class="small text-muted">{{ t.time }} · {{ t.car }} · {{ t.route }}</div>
                </Link>
                <div v-if="!list.length" class="list-group-item text-muted small">{{ empty }}</div>
            </div>
        </div>
    </AppLayout>
</template>
