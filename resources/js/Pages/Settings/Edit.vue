<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({ settings: Object, logoUrl: String, states: Array });

const s = props.settings;
const form = useForm({
    name: s.name ?? '',
    address: s.address ?? '',
    phone: s.phone ?? '',
    email: s.email ?? '',
    gstin: s.gstin ?? '',
    state: s.state ?? 'Maharashtra',
    sac_code: s.sac_code ?? '996601',
    gst_rate: s.gst_rate ?? '5.00',
    min_km_per_day: s.min_km_per_day ?? 0,
    invoice_prefix: s.invoice_prefix ?? 'MTT',
    bank_details: s.bank_details ?? '',
    upi_id: s.upi_id ?? '',
    terms: s.terms ?? '',
    logo: null,
});

const fields = [
    { key: 'name', label: 'Business name', col: 'col-12' },
    { key: 'address', label: 'Address', col: 'col-12', textarea: true },
    { key: 'phone', label: 'Phone', col: 'col-md-6' },
    { key: 'email', label: 'Email', col: 'col-md-6', type: 'email' },
    { key: 'gstin', label: 'GSTIN (leave empty if not registered)', col: 'col-md-6' },
    { key: 'sac_code', label: 'SAC code', col: 'col-md-4' },
    { key: 'gst_rate', label: 'GST rate (%)', col: 'col-md-4', type: 'number' },
    { key: 'min_km_per_day', label: 'Minimum km per day (0 = none)', col: 'col-md-4', type: 'number' },
    { key: 'invoice_prefix', label: 'Invoice prefix', col: 'col-md-4' },
    { key: 'upi_id', label: 'UPI ID (for QR on bills)', col: 'col-md-8' },
    { key: 'bank_details', label: 'Bank details (shown on bill)', col: 'col-12', textarea: true },
    { key: 'terms', label: 'Terms / note on bill', col: 'col-12', textarea: true },
];

const submit = () => form.post('/settings', { forceFormData: true, preserveScroll: true });
</script>

<template>
    <Head title="Settings" />
    <AppLayout title="Settings">
        <form class="card card-body" @submit.prevent="submit">
            <div class="row g-3">
                <div v-for="f in fields" :key="f.key" :class="f.col">
                    <label class="form-label" :for="f.key">{{ f.label }}</label>
                    <textarea v-if="f.textarea" :id="f.key" v-model="form[f.key]" rows="2"
                              class="form-control" :class="{ 'is-invalid': form.errors[f.key] }"></textarea>
                    <input v-else :id="f.key" v-model="form[f.key]" :type="f.type || 'text'" step="any"
                           class="form-control" :class="{ 'is-invalid': form.errors[f.key] }">
                    <div class="invalid-feedback">{{ form.errors[f.key] }}</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="state">State</label>
                    <select id="state" v-model="form.state" class="form-select" :class="{ 'is-invalid': form.errors.state }">
                        <option v-for="st in states" :key="st" :value="st">{{ st }}</option>
                    </select>
                    <div class="invalid-feedback">{{ form.errors.state }}</div>
                </div>
                <div class="col-12">
                    <label class="form-label" for="logo">Logo</label>
                    <div class="d-flex align-items-center gap-3">
                        <img v-if="logoUrl" :src="logoUrl" alt="Current logo" style="height: 48px">
                        <input id="logo" type="file" accept="image/*" class="form-control"
                               :class="{ 'is-invalid': form.errors.logo }"
                               @input="form.logo = $event.target.files[0]">
                    </div>
                    <div class="invalid-feedback d-block">{{ form.errors.logo }}</div>
                </div>
            </div>
            <div class="mt-4 d-flex flex-wrap gap-2 align-items-center">
                <button class="btn btn-brand" type="submit" :disabled="form.processing">Save settings</button>
                <a href="/settings/backup" class="btn btn-outline-secondary">⬇ Download backup</a>
                <span class="small text-muted">Save a backup to your phone or Google Drive once a week.</span>
            </div>
        </form>
    </AppLayout>
</template>
