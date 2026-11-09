<script setup>
import { ref, watch } from 'vue';
import { getJson, postJson } from '../http.js';

const props = defineProps({ initial: Object, error: String });
const emit = defineEmits(['selected']);

const selected = ref(props.initial ?? null);
const query = ref('');
const results = ref([]);
const open = ref(false);
const adding = ref(false);
const newCustomer = ref({ name: '', phone: '', state: 'Maharashtra', gstin: '', email: '' });
const newErrors = ref({});
const saving = ref(false);

let timer;
watch(query, (q) => {
    clearTimeout(timer);
    if (!q.trim()) { results.value = []; return; }
    timer = setTimeout(async () => {
        results.value = await getJson('/customers/search?q=' + encodeURIComponent(q));
        open.value = true;
    }, 250);
});

const choose = (c) => {
    selected.value = c;
    open.value = false;
    query.value = '';
    emit('selected', c);
};

const startAdd = () => {
    adding.value = true;
    newCustomer.value.name = /\d{5,}/.test(query.value) ? '' : query.value;
    newCustomer.value.phone = /\d{5,}/.test(query.value) ? query.value : '';
    open.value = false;
};

const saveNew = async () => {
    saving.value = true;
    const { ok, data } = await postJson('/customers', newCustomer.value);
    saving.value = false;
    if (!ok) {
        newErrors.value = Object.fromEntries(Object.entries(data.errors ?? {}).map(([k, v]) => [k, v[0]]));
        return;
    }
    adding.value = false;
    newErrors.value = {};
    choose(data.customer);
};
</script>

<template>
    <div>
        <div v-if="selected && !adding" class="d-flex justify-content-between align-items-center border rounded p-2 bg-white">
            <div>
                <div class="fw-semibold">{{ selected.name }}</div>
                <div class="small text-muted">
                    {{ selected.phone }}<span v-if="selected.gstin"> · GSTIN {{ selected.gstin }}</span>
                    <span v-if="selected.state && selected.state !== 'Maharashtra'"> · {{ selected.state }}</span>
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary" @click="selected = null">Change</button>
        </div>

        <div v-else-if="!adding" class="position-relative">
            <input v-model="query" type="search" class="form-control" :class="{ 'is-invalid': error }"
                   placeholder="Search customer by name or phone" autocomplete="off" @focus="open = results.length > 0">
            <div class="invalid-feedback">{{ error }}</div>
            <div v-if="open" class="list-group position-absolute w-100 shadow-sm" style="z-index: 1030">
                <button v-for="c in results" :key="c.id" type="button" class="list-group-item list-group-item-action"
                        @click="choose(c)">
                    {{ c.name }} <span class="small text-muted">{{ c.phone }}</span>
                </button>
                <button type="button" class="list-group-item list-group-item-action text-primary" @click="startAdd">
                    + New customer<span v-if="query"> “{{ query }}”</span>
                </button>
            </div>
            <button v-if="!query" type="button" class="btn btn-link px-0 small" @click="startAdd">+ New customer</button>
        </div>

        <div v-else class="border rounded p-3 bg-white">
            <div class="row g-2">
                <div class="col-md-6">
                    <input v-model="newCustomer.name" class="form-control" :class="{ 'is-invalid': newErrors.name }" placeholder="Name / company">
                    <div class="invalid-feedback">{{ newErrors.name }}</div>
                </div>
                <div class="col-md-6">
                    <input v-model="newCustomer.phone" type="tel" class="form-control" placeholder="Phone (WhatsApp)">
                </div>
                <div class="col-md-4">
                    <input v-model="newCustomer.state" class="form-control" placeholder="State">
                </div>
                <div class="col-md-4">
                    <input v-model="newCustomer.gstin" class="form-control" :class="{ 'is-invalid': newErrors.gstin }" placeholder="GSTIN (optional)">
                    <div class="invalid-feedback">{{ newErrors.gstin }}</div>
                </div>
                <div class="col-md-4">
                    <input v-model="newCustomer.email" type="email" class="form-control" :class="{ 'is-invalid': newErrors.email }" placeholder="Email (optional)">
                    <div class="invalid-feedback">{{ newErrors.email }}</div>
                </div>
            </div>
            <div class="mt-2 d-flex gap-2">
                <button type="button" class="btn btn-sm btn-brand" :disabled="saving" @click="saveNew">Add customer</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" @click="adding = false">Cancel</button>
            </div>
        </div>
    </div>
</template>
