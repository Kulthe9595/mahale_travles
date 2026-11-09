<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineProps({ drivers: Array });

const remove = (d) => {
    if (confirm(`Delete ${d.name}?`)) router.delete(`/drivers/${d.id}`);
};
</script>

<template>
    <Head title="Drivers" />
    <AppLayout title="Drivers">
        <div class="mb-3"><Link href="/drivers/create" class="btn btn-brand">+ Add driver</Link></div>
        <div v-if="!drivers.length" class="text-muted">No drivers yet.</div>
        <div class="list-group">
            <div v-for="d in drivers" :key="d.id"
                 class="list-group-item d-flex justify-content-between align-items-center gap-2">
                <div>
                    <div class="fw-semibold">
                        {{ d.name }}
                        <span v-if="!d.is_active" class="badge text-bg-secondary ms-1">Inactive</span>
                    </div>
                    <div class="small text-muted">
                        <a v-if="d.phone" :href="`tel:${d.phone}`">{{ d.phone }}</a>
                        <span v-if="d.licence_no"> · Licence {{ d.licence_no }}</span>
                    </div>
                </div>
                <div class="d-flex gap-1">
                    <Link :href="`/drivers/${d.id}/edit`" class="btn btn-sm btn-outline-primary">Edit</Link>
                    <button class="btn btn-sm btn-outline-danger" type="button" @click="remove(d)">Delete</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
