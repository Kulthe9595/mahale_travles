<script setup>
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

const props = defineProps({ tripId: Number, balance: String, today: String });
const form = useForm({
    amount: props.balance && Number(props.balance) > 0 ? props.balance : '',
    paid_on: props.today,
    mode: 'upi',
    reference: '',
    note: '',
});
const balanceAmount = () => (props.balance && Number(props.balance) > 0 ? props.balance : '');
watch(() => props.balance, () => { form.amount = balanceAmount(); });

const submit = () => form.post(`/trips/${props.tripId}/payments`, {
    preserveScroll: true,
    onSuccess: () => { form.reference = ''; form.note = ''; form.amount = balanceAmount(); },
});
</script>

<template>
    <form class="row g-2" @submit.prevent="submit">
        <div class="col-6 col-md-3">
            <input v-model="form.amount" type="number" step="any" inputmode="decimal" class="form-control"
                   :class="{ 'is-invalid': form.errors.amount }" placeholder="Amount ₹">
            <div class="invalid-feedback">{{ form.errors.amount }}</div>
        </div>
        <div class="col-6 col-md-3">
            <input v-model="form.paid_on" type="date" class="form-control" :class="{ 'is-invalid': form.errors.paid_on }">
        </div>
        <div class="col-6 col-md-2">
            <select v-model="form.mode" class="form-select">
                <option value="upi">UPI</option>
                <option value="cash">Cash</option>
                <option value="bank">Bank</option>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <input v-model="form.reference" class="form-control" placeholder="Ref / UTR">
        </div>
        <div class="col-12 col-md-2 d-grid">
            <button class="btn btn-brand" type="submit" :disabled="form.processing">Add payment</button>
        </div>
    </form>
</template>
