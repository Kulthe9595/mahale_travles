<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import FormField from '../../Components/FormField.vue';

const props = defineProps({ car: Object });
const c = props.car ?? {};
const form = useForm({
    registration_no: c.registration_no ?? '',
    model: c.model ?? '',
    type: c.type ?? '',
    seats: c.seats ?? '',
    rate_per_km: c.rate_per_km ?? '',
    rate_per_day: c.rate_per_day ?? '',
    is_active: c.is_active ?? true,
});
const submit = () => (props.car ? form.put(`/cars/${props.car.id}`) : form.post('/cars'));
</script>

<template>
    <Head :title="car ? 'Edit car' : 'Add car'" />
    <AppLayout :title="car ? 'Edit car' : 'Add car'">
        <form class="card card-body" @submit.prevent="submit">
            <div class="row g-3">
                <FormField id="registration_no" v-model="form.registration_no" class="col-md-6"
                           label="Registration no." :error="form.errors.registration_no" help="e.g. MH 17 AB 1234" />
                <FormField id="model" v-model="form.model" class="col-md-6" label="Model" :error="form.errors.model" />
                <FormField id="type" v-model="form.type" class="col-md-4" label="Type (Sedan, SUV…)" :error="form.errors.type" />
                <FormField id="seats" v-model="form.seats" class="col-md-2" type="number" label="Seats" :error="form.errors.seats" />
                <FormField id="rate_per_km" v-model="form.rate_per_km" class="col-md-3" type="number"
                           label="Default ₹ per km" :error="form.errors.rate_per_km" />
                <FormField id="rate_per_day" v-model="form.rate_per_day" class="col-md-3" type="number"
                           label="Default ₹ per day" :error="form.errors.rate_per_day" />
                <div class="col-12 form-check ms-2">
                    <input id="is_active" v-model="form.is_active" class="form-check-input" type="checkbox">
                    <label class="form-check-label" for="is_active">Active (shown when creating bills)</label>
                </div>
            </div>
            <div class="mt-4 d-flex gap-2">
                <button class="btn btn-brand" type="submit" :disabled="form.processing">Save</button>
                <Link href="/cars" class="btn btn-outline-secondary">Cancel</Link>
            </div>
        </form>
    </AppLayout>
</template>
