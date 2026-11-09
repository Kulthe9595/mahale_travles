<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const reports = [
    ['/reports/earnings', 'Earnings per car', 'Trips, km and billed amount for each car, month by month.'],
    ['/reports/gst', 'GST summary', 'GST bills with taxable value and CGST/SGST/IGST — download Excel for the CA.'],
    ['/reports/outstanding', 'Outstanding', 'Who still owes money, and since when.'],
];
</script>

<template>
    <Head title="Reports" />
    <AppLayout title="Reports">
        <div class="list-group">
            <Link v-for="[href, title, text] in reports" :key="href" :href="href" class="list-group-item list-group-item-action py-3">
                <div class="fw-semibold">{{ title }}</div>
                <div class="small text-muted">{{ text }}</div>
            </Link>
        </div>
    </AppLayout>
</template>
