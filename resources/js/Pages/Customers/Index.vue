<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({ customers: Object, filters: Object });
const q = ref(props.filters.q ?? '');
let timer;
watch(q, (value) => {
    clearTimeout(timer);
    timer = setTimeout(() => router.get('/customers', { q: value }, { preserveState: true, replace: true }), 300);
});
</script>

<template>
    <Head title="Customers" />
    <AppLayout title="Customers">
        <div class="d-flex gap-2 mb-3">
            <input v-model="q" type="search" class="form-control" placeholder="Search name or phone">
            <Link href="/customers/create" class="btn btn-brand text-nowrap">+ Add</Link>
        </div>
        <div v-if="!customers.data.length" class="text-muted">No customers found.</div>
        <div class="list-group">
            <div v-for="c in customers.data" :key="c.id"
                 class="list-group-item d-flex justify-content-between align-items-center gap-2">
                <div>
                    <div class="fw-semibold">{{ c.name }}</div>
                    <div class="small text-muted">
                        <span v-if="c.phone">{{ c.phone }}</span>
                        <span v-if="c.gstin"> · GSTIN {{ c.gstin }}</span>
                        <span v-if="c.state && c.state !== 'Maharashtra'"> · {{ c.state }}</span>
                    </div>
                </div>
                <Link :href="`/customers/${c.id}`" class="btn btn-sm btn-outline-primary">Ledger</Link>
            </div>
        </div>
        <nav v-if="customers.last_page > 1" class="mt-3 d-flex gap-2">
            <Link v-if="customers.prev_page_url" :href="customers.prev_page_url" class="btn btn-sm btn-outline-secondary">Previous</Link>
            <Link v-if="customers.next_page_url" :href="customers.next_page_url" class="btn btn-sm btn-outline-secondary">Next</Link>
        </nav>
    </AppLayout>
</template>
