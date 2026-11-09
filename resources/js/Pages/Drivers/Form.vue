<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import FormField from '../../Components/FormField.vue';

const props = defineProps({ driver: Object });
const d = props.driver ?? {};
const form = useForm({
    name: d.name ?? '',
    phone: d.phone ?? '',
    licence_no: d.licence_no ?? '',
    is_active: d.is_active ?? true,
});
const submit = () => (props.driver ? form.put(`/drivers/${props.driver.id}`) : form.post('/drivers'));
</script>

<template>
    <Head :title="driver ? 'Edit driver' : 'Add driver'" />
    <AppLayout :title="driver ? 'Edit driver' : 'Add driver'">
        <form class="card card-body" @submit.prevent="submit">
            <div class="row g-3">
                <FormField id="name" v-model="form.name" class="col-md-6" label="Name" :error="form.errors.name" />
                <FormField id="phone" v-model="form.phone" class="col-md-6" type="tel" label="Phone" :error="form.errors.phone" />
                <FormField id="licence_no" v-model="form.licence_no" class="col-md-6" label="Licence no."
                           :error="form.errors.licence_no" />
                <div class="col-12 form-check ms-2">
                    <input id="is_active" v-model="form.is_active" class="form-check-input" type="checkbox">
                    <label class="form-check-label" for="is_active">Active</label>
                </div>
            </div>
            <div class="mt-4 d-flex gap-2">
                <button class="btn btn-brand" type="submit" :disabled="form.processing">Save</button>
                <Link href="/drivers" class="btn btn-outline-secondary">Cancel</Link>
            </div>
        </form>
    </AppLayout>
</template>
