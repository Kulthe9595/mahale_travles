<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import FormField from '../../Components/FormField.vue';

const props = defineProps({ customer: Object, states: Array });
const c = props.customer ?? {};
const form = useForm({
    name: c.name ?? '',
    phone: c.phone ?? '',
    email: c.email ?? '',
    address: c.address ?? '',
    state: c.state ?? 'Maharashtra',
    gstin: c.gstin ?? '',
});
const submit = () => (props.customer ? form.put(`/customers/${props.customer.id}`) : form.post('/customers'));
</script>

<template>
    <Head :title="customer ? 'Edit customer' : 'Add customer'" />
    <AppLayout :title="customer ? 'Edit customer' : 'Add customer'">
        <form class="card card-body" @submit.prevent="submit">
            <div class="row g-3">
                <FormField id="name" v-model="form.name" class="col-md-6" label="Name / company" :error="form.errors.name" />
                <FormField id="phone" v-model="form.phone" class="col-md-6" type="tel" label="Phone (WhatsApp)"
                           :error="form.errors.phone" />
                <FormField id="email" v-model="form.email" class="col-md-6" type="email" label="Email" :error="form.errors.email" />
                <div class="col-md-6">
                    <label class="form-label" for="state">State</label>
                    <select id="state" v-model="form.state" class="form-select" :class="{ 'is-invalid': form.errors.state }">
                        <option v-for="st in states" :key="st" :value="st">{{ st }}</option>
                    </select>
                    <div class="form-text">Decides CGST+SGST (same state) or IGST (other states)</div>
                    <div class="invalid-feedback">{{ form.errors.state }}</div>
                </div>
                <FormField id="address" v-model="form.address" class="col-12" textarea label="Address" :error="form.errors.address" />
                <FormField id="gstin" v-model="form.gstin" class="col-md-6" label="GSTIN (companies only)" :error="form.errors.gstin" />
            </div>
            <div class="mt-4 d-flex gap-2">
                <button class="btn btn-brand" type="submit" :disabled="form.processing">Save</button>
                <Link href="/customers" class="btn btn-outline-secondary">Cancel</Link>
            </div>
        </form>
    </AppLayout>
</template>
