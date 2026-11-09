<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import ShareButtons from '../../Components/ShareButtons.vue';
import PaymentForm from '../../Components/PaymentForm.vue';
import { rupees } from '../../http.js';

const props = defineProps({
    trip: Object, invoice: Object, cancelledInvoices: Array, money: Object, payments: Array,
    shareLogs: Array, share: Object, canGenerate: Boolean, canDeletePayments: Boolean,
    gstRegistered: Boolean, today: String,
});

const generate = () => router.post(`/trips/${props.trip.id}/invoice`, {}, { preserveScroll: true });
const refreshBill = () => router.post(`/invoices/${props.invoice.id}/refresh`, {}, { preserveScroll: true });
const reissue = () => {
    if (confirm(`Cancel ${props.invoice.invoice_no} and issue a new bill number with the current trip details?`)) {
        router.post(`/invoices/${props.invoice.id}/reissue`, {}, { preserveScroll: true });
    }
};
const toggleLink = () => router.post(`/invoices/${props.invoice.id}/link`, { enabled: !props.invoice.share_enabled }, { preserveScroll: true });
const removePayment = (p) => {
    if (confirm(`Remove payment of ${rupees(p.amount)}?`)) router.delete(`/payments/${p.id}`, { preserveScroll: true });
};

const showEmail = ref(false);
const emailForm = useForm({ to: props.share?.email ?? props.trip.customer.email ?? '' });
const sendEmail = () => emailForm.post(`/invoices/${props.invoice.id}/email`, {
    preserveScroll: true,
    onSuccess: () => { showEmail.value = false; },
});
const flashErrors = (errors) => errors?.trip ?? errors?.invoice;
</script>

<template>
    <Head :title="invoice ? invoice.invoice_no : 'Trip'" />
    <AppLayout>
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
            <div>
                <h1 class="h4 mb-1">{{ trip.customer.name }}</h1>
                <div class="text-muted small">
                    {{ trip.car.registration_no }} · {{ trip.pickup || '—' }} → {{ trip.drop || '—' }} · {{ trip.start_at }}
                </div>
                <div class="mt-1 d-flex gap-1">
                    <StatusBadge :status="trip.status" />
                    <StatusBadge v-if="money.status" :status="money.status" />
                </div>
            </div>
            <Link :href="`/trips/${trip.id}/edit`" class="btn btn-outline-primary btn-sm">Edit trip</Link>
        </div>

        <div v-if="flashErrors($page.props.errors)" class="alert alert-danger py-2">{{ flashErrors($page.props.errors) }}</div>

        <div class="row g-3">
            <div class="col-lg-7">
                <!-- Bill -->
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span class="fw-semibold">{{ invoice ? invoice.invoice_no : 'Bill' }}</span>
                        <span v-if="invoice" class="small text-muted">
                            {{ invoice.invoice_date }} · {{ invoice.gst_enabled ? 'GST' : 'Non-GST' }}
                            <span v-if="invoice.locked" class="badge text-bg-light border ms-1">Locked</span>
                        </span>
                    </div>
                    <div v-if="!invoice" class="card-body">
                        <p v-if="canGenerate" class="mb-2">Trip details are complete. Create the bill to share it.</p>
                        <p v-else class="text-muted mb-2">Add end time and start/end km (Edit trip) to create the bill.</p>
                        <button class="btn btn-brand" :disabled="!canGenerate" @click="generate">Create bill</button>
                    </div>
                    <template v-else>
                        <table class="table table-sm mb-0">
                            <tbody>
                                <tr v-for="item in invoice.items" :key="item.id">
                                    <td>{{ item.description }} <span class="text-muted small" v-if="Number(item.qty) !== 1">× {{ Number(item.qty) }}</span></td>
                                    <td class="text-end money">{{ rupees(item.amount) }}</td>
                                </tr>
                                <template v-if="invoice.tax_type === 'cgst_sgst'">
                                    <tr><td>CGST @ {{ Number(invoice.gst_rate) / 2 }}%</td><td class="text-end money">{{ rupees(invoice.cgst) }}</td></tr>
                                    <tr><td>SGST @ {{ Number(invoice.gst_rate) / 2 }}%</td><td class="text-end money">{{ rupees(invoice.sgst) }}</td></tr>
                                </template>
                                <tr v-if="invoice.tax_type === 'igst'"><td>IGST @ {{ Number(invoice.gst_rate) }}%</td><td class="text-end money">{{ rupees(invoice.igst) }}</td></tr>
                                <tr v-if="Number(invoice.round_off) !== 0"><td>Round off</td><td class="text-end money">{{ rupees(invoice.round_off) }}</td></tr>
                                <tr class="fw-bold"><td>Total</td><td class="text-end money">{{ rupees(invoice.total) }}</td></tr>
                            </tbody>
                        </table>
                        <div class="card-body border-top">
                            <ShareButtons :pdf-url="`/invoices/${invoice.id}/pdf`" :filename="share.filename"
                                          :log-url="`/invoices/${invoice.id}/share`" :whatsapp-url="share.whatsapp_url"
                                          :public-url="invoice.share_enabled ? share.public_url : null"
                                          :title="invoice.invoice_no" />
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                <button class="btn btn-sm btn-outline-secondary" @click="showEmail = !showEmail">Email</button>
                                <button v-if="!invoice.locked" class="btn btn-sm btn-outline-secondary" @click="refreshBill">Update bill from trip</button>
                                <button v-else class="btn btn-sm btn-outline-danger" @click="reissue">Cancel &amp; reissue</button>
                                <button class="btn btn-sm btn-link text-decoration-none" @click="toggleLink">
                                    {{ invoice.share_enabled ? 'Turn off bill link' : 'Turn on bill link' }}
                                </button>
                            </div>
                            <form v-if="showEmail" class="d-flex gap-2 mt-2" @submit.prevent="sendEmail">
                                <input v-model="emailForm.to" type="email" class="form-control form-control-sm"
                                       :class="{ 'is-invalid': emailForm.errors.to }" placeholder="customer@email.com">
                                <button class="btn btn-sm btn-brand text-nowrap" :disabled="emailForm.processing">Send</button>
                            </form>
                            <div v-if="emailForm.errors.to" class="small text-danger">{{ emailForm.errors.to }}</div>
                        </div>
                    </template>
                </div>

                <div v-if="cancelledInvoices.length" class="small text-muted mb-3">
                    Cancelled:
                    <a v-for="c in cancelledInvoices" :key="c.id" :href="`/invoices/${c.id}/pdf`" target="_blank" class="me-2">
                        {{ c.invoice_no }}
                    </a>
                </div>
            </div>

            <div class="col-lg-5">
                <!-- Money -->
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between"><span>Bill total</span><span class="money">{{ rupees(money.total) }}</span></div>
                        <div class="d-flex justify-content-between"><span>Received</span><span class="money">{{ rupees(money.paid) }}</span></div>
                        <div v-if="Number(money.excess) > 0" class="d-flex justify-content-between fw-bold text-success">
                            <span>Excess received</span><span class="money">{{ rupees(money.excess) }}</span>
                        </div>
                        <div v-else-if="money.balance !== null" class="d-flex justify-content-between fw-bold"
                             :class="Number(money.balance) > 0 ? 'text-danger' : 'text-success'">
                            <span>Balance due</span><span class="money">{{ rupees(money.balance) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Payments -->
                <div class="card mb-3">
                    <div class="card-header fw-semibold">Payments</div>
                    <div class="card-body">
                        <PaymentForm :trip-id="trip.id" :balance="money.balance" :today="today" />
                    </div>
                    <ul class="list-group list-group-flush">
                        <li v-for="p in payments" :key="p.id" class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <span><strong class="money">{{ rupees(p.amount) }}</strong> · {{ p.mode }} · {{ p.paid_on }}</span>
                                <span class="small text-muted">{{ p.receipt_no }}</span>
                            </div>
                            <div v-if="p.reference" class="small text-muted">Ref {{ p.reference }}</div>
                            <div class="mt-1 d-flex flex-wrap gap-1 align-items-center">
                                <ShareButtons :pdf-url="`/payments/${p.id}/receipt`" :filename="p.receipt_filename"
                                              :log-url="`/payments/${p.id}/share`" :title="p.receipt_no" class="small" />
                                <button v-if="canDeletePayments" class="btn btn-sm btn-link text-danger" @click="removePayment(p)">Remove</button>
                            </div>
                        </li>
                        <li v-if="!payments.length" class="list-group-item text-muted small">No payments yet.</li>
                    </ul>
                </div>

                <!-- Share log -->
                <div v-if="shareLogs.length" class="card">
                    <div class="card-header fw-semibold">Sent</div>
                    <ul class="list-group list-group-flush small">
                        <li v-for="l in shareLogs" :key="l.id" class="list-group-item">
                            {{ l.what }} · {{ l.channel }}<span v-if="l.recipient"> · {{ l.recipient }}</span>
                            <div class="text-muted">{{ l.shared_at }}</div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
