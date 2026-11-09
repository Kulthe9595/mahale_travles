<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import { rupees } from '../../http.js';

const props = defineProps({ trips: Object, filters: Object });

const q = ref(props.filters.q ?? '');
const status = ref(props.filters.status ?? '');
const tabs = [
    ['', 'All'], ['unbilled', 'No bill'], ['unpaid', 'Unpaid'], ['partly_paid', 'Partly paid'], ['paid', 'Paid'],
];

const go = () => router.get('/trips', { q: q.value || undefined, status: status.value || undefined },
    { preserveState: true, replace: true });

let timer;
watch(q, () => { clearTimeout(timer); timer = setTimeout(go, 300); });
const setStatus = (s) => { status.value = s; go(); };
</script>

<template>
    <Head title="Bills" />
    <AppLayout title="Bills">
        <div class="d-flex gap-2 mb-2">
            <input v-model="q" type="search" class="form-control" placeholder="Search customer, phone, car no., bill no.">
            <Link href="/trips/create" class="btn btn-brand text-nowrap">+ New bill</Link>
        </div>
        <div class="d-flex flex-wrap gap-1 mb-3">
            <button v-for="[value, label] in tabs" :key="value" type="button" class="btn btn-sm"
                    :class="status === value ? 'btn-primary' : 'btn-outline-primary'" @click="setStatus(value)">
                {{ label }}
            </button>
        </div>

        <div v-if="!trips.data.length" class="text-muted">Nothing found.</div>
        <div class="list-group">
            <Link v-for="t in trips.data" :key="t.id" :href="`/trips/${t.id}`"
                  class="list-group-item list-group-item-action">
                <div class="d-flex justify-content-between gap-2">
                    <div class="fw-semibold text-truncate">{{ t.customer?.name }}</div>
                    <div class="money fw-semibold text-nowrap">{{ rupees(t.total) }}</div>
                </div>
                <div class="d-flex justify-content-between gap-2 small text-muted">
                    <div class="text-truncate">
                        {{ t.start_at }} · {{ t.car?.registration_no }} · {{ t.pickup || '—' }} → {{ t.drop || '—' }}
                    </div>
                    <div class="text-nowrap" v-if="t.balance !== null && Number(t.balance) > 0">Due {{ rupees(t.balance) }}</div>
                </div>
                <div class="mt-1 d-flex gap-1 align-items-center">
                    <span v-if="t.invoice_no" class="small text-muted me-1">{{ t.invoice_no }}</span>
                    <StatusBadge :status="t.payment_status ?? (t.status === 'booked' ? 'booked' : null)" />
                </div>
            </Link>
        </div>

        <nav v-if="trips.last_page > 1" class="mt-3 d-flex gap-2">
            <Link v-if="trips.prev_page_url" :href="trips.prev_page_url" class="btn btn-sm btn-outline-secondary">Previous</Link>
            <Link v-if="trips.next_page_url" :href="trips.next_page_url" class="btn btn-sm btn-outline-secondary">Next</Link>
        </nav>
    </AppLayout>
</template>
