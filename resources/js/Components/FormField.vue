<script setup>
defineProps({
    label: String,
    id: String,
    error: String,
    type: { type: String, default: 'text' },
    textarea: Boolean,
    help: String,
});
const model = defineModel();
</script>

<template>
    <div>
        <label class="form-label" :for="id">{{ label }}</label>
        <textarea v-if="textarea" :id="id" v-model="model" rows="2" class="form-control"
                  :class="{ 'is-invalid': error }"></textarea>
        <input v-else :id="id" v-model="model" :type="type" :step="type === 'number' ? 'any' : undefined"
               :inputmode="type === 'number' ? 'decimal' : undefined"
               class="form-control" :class="{ 'is-invalid': error }">
        <div v-if="help" class="form-text">{{ help }}</div>
        <div class="invalid-feedback">{{ error }}</div>
    </div>
</template>
