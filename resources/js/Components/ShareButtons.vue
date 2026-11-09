<script setup>
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { postJson } from '../http.js';

const props = defineProps({
    pdfUrl: { type: String, required: true },
    filename: { type: String, required: true },
    logUrl: { type: String, required: true },
    whatsappUrl: String,
    publicUrl: String,
    title: { type: String, default: 'Bill' },
});

const busy = ref(false);
const note = ref('');
const canShareFiles = computed(() => {
    try {
        const probe = new File([new Blob(['x'])], 'x.pdf', { type: 'application/pdf' });
        return !!navigator.canShare && navigator.canShare({ files: [probe] });
    } catch {
        return false;
    }
});

const logShare = async (channel) => {
    await postJson(props.logUrl, { channel });
    router.reload({ preserveScroll: true });
};

const shareFile = async () => {
    busy.value = true;
    note.value = '';
    try {
        const res = await fetch(props.pdfUrl, { credentials: 'same-origin' });
        const blob = await res.blob();
        const file = new File([blob], props.filename, { type: 'application/pdf' });
        await navigator.share({ files: [file], title: props.title });
        await logShare('share_sheet');
    } catch (e) {
        if (e?.name !== 'AbortError') note.value = 'Could not open the share menu. Use the WhatsApp button instead.';
    } finally {
        busy.value = false;
    }
};

const copyLink = async () => {
    try {
        await navigator.clipboard.writeText(props.publicUrl);
        note.value = 'Link copied.';
        await logShare('copy_link');
    } catch {
        note.value = props.publicUrl;
    }
};
</script>

<template>
    <div>
        <div class="d-flex flex-wrap gap-2">
            <button v-if="canShareFiles" type="button" class="btn btn-success" :disabled="busy" @click="shareFile">
                Share PDF
            </button>
            <a v-if="whatsappUrl" :href="whatsappUrl" target="_blank" rel="noopener" class="btn btn-outline-success"
               @click="logShare('whatsapp_link')">
                WhatsApp link
            </a>
            <button v-if="publicUrl" type="button" class="btn btn-outline-secondary" @click="copyLink">Copy link</button>
            <a :href="pdfUrl" target="_blank" rel="noopener" class="btn btn-outline-primary">View PDF</a>
        </div>
        <div v-if="!whatsappUrl && publicUrl" class="form-text">Add a valid mobile number to the customer to use the WhatsApp link.</div>
        <div v-if="note" class="small mt-1 text-muted text-break">{{ note }}</div>
    </div>
</template>
