<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineProps({ cars: Array });

const remove = (car) => {
    if (confirm(`Delete ${car.registration_no}?`)) router.delete(`/cars/${car.id}`);
};
</script>

<template>
    <Head title="Cars" />
    <AppLayout title="Cars">
        <div class="mb-3"><Link href="/cars/create" class="btn btn-brand">+ Add car</Link></div>
        <div v-if="!cars.length" class="text-muted">No cars yet.</div>
        <div class="list-group">
            <div v-for="car in cars" :key="car.id"
                 class="list-group-item d-flex justify-content-between align-items-center gap-2">
                <div>
                    <div class="fw-semibold">
                        {{ car.registration_no }}
                        <span v-if="!car.is_active" class="badge text-bg-secondary ms-1">Inactive</span>
                    </div>
                    <div class="small text-muted">
                        {{ car.model }}<span v-if="car.type"> · {{ car.type }}</span><span v-if="car.seats"> · {{ car.seats }} seats</span>
                        · ₹{{ car.rate_per_km }}/km · ₹{{ car.rate_per_day }}/day
                    </div>
                </div>
                <div class="d-flex gap-1">
                    <Link :href="`/cars/${car.id}/edit`" class="btn btn-sm btn-outline-primary">Edit</Link>
                    <button class="btn btn-sm btn-outline-danger" type="button" @click="remove(car)">Delete</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
