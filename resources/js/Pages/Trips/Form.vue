<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import CustomerPicker from '../../Components/CustomerPicker.vue';
import BillPreview from '../../Components/BillPreview.vue';
import { postJson, rupees } from '../../http.js';

const props = defineProps({ trip: Object, cars: Array, drivers: Array, settings: Object, states: Array });

const t = props.trip ?? {};
const b = t.billing_inputs ?? {};
const nowLocal = () => {
    const d = new Date();
    d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
    return d.toISOString().slice(0, 16);
};

const form = useForm({
    customer_id: t.customer_id ?? null,
    car_id: t.car_id ?? '',
    driver_id: t.driver_id ?? '',
    pickup: t.pickup ?? '',
    drop: t.drop ?? '',
    start_at: t.start_at ?? nowLocal(),
    end_at: t.end_at ?? '',
    trip_type: t.trip_type ?? 'per_km',
    start_km: t.start_km ?? '',
    end_km: t.end_km ?? '',
    status: t.status ?? 'completed',
    notes: t.notes ?? '',
    generate_invoice: false,
    billing: {
        rate_per_km: b.rate_per_km ?? '',
        package_mode: b.package_mode ?? 'fixed',
        package_amount: b.package_amount ?? '',
        package_rate_per_day: b.package_rate_per_day ?? '',
        included_km: b.included_km ?? '',
        included_hours: b.included_hours ?? '',
        extra_km_rate: b.extra_km_rate ?? '',
        extra_hour_rate: b.extra_hour_rate ?? '',
        driver_allowance_per_day: b.driver_allowance_per_day ?? '',
        night_halt_per_night: b.night_halt_per_night ?? '',
        toll: b.toll ?? '',
        parking: b.parking ?? '',
        state_permit: b.state_permit ?? '',
        other_taxable: b.other_taxable ?? '',
        other_taxable_label: b.other_taxable_label ?? '',
        discount: b.discount ?? '',
        gst_enabled: b.gst_enabled ?? false,
    },
});

const err = (key) => form.errors[key];
const selectedCar = computed(() => props.cars.find((c) => c.id === Number(form.car_id)));

watch(() => form.car_id, () => {
    const car = selectedCar.value;
    if (!car) return;
    if (!form.billing.rate_per_km) form.billing.rate_per_km = car.rate_per_km;
    if (!form.billing.package_rate_per_day) form.billing.package_rate_per_day = car.rate_per_day;
});

const onCustomer = (c) => { form.customer_id = c?.id ?? null; };

// Live preview — same calculator the saved bill uses.
const preview = ref(null);
const previewError = ref('');
const previewLoading = ref(false);
const canPreview = computed(() =>
    form.customer_id && form.car_id && form.start_at && form.end_at && form.start_km !== '' && form.end_km !== '');

let timer;
watch(() => JSON.stringify(form.data()), () => {
    clearTimeout(timer);
    if (!canPreview.value) { preview.value = null; previewError.value = ''; return; }
    timer = setTimeout(async () => {
        previewLoading.value = true;
        const { ok, data } = await postJson('/bill-preview', { ...form.data(), status: 'completed' });
        previewLoading.value = false;
        if (ok) { preview.value = data; previewError.value = ''; }
        else { preview.value = null; previewError.value = data.message ?? 'Check the trip details.'; }
    }, 400);
}, { immediate: true });

const submit = (withBill) => {
    form.generate_invoice = withBill;
    const opts = { preserveScroll: true };
    props.trip ? form.put(`/trips/${props.trip.id}`, opts) : form.post('/trips', opts);
};

const money = [
    ['driver_allowance_per_day', 'Driver allowance / day'],
    ['night_halt_per_night', 'Night halt / night'],
    ['toll', 'Toll (actual)'],
    ['parking', 'Parking (actual)'],
    ['state_permit', 'State permit (actual)'],
    ['discount', 'Discount'],
];
</script>

<template>
    <Head :title="trip ? 'Edit trip' : 'New bill'" />
    <AppLayout :title="trip ? 'Edit trip' : 'New bill'">
        <div v-if="trip?.locked" class="alert alert-warning py-2 small">
            This trip's bill was already shared and is locked. Changes here will not change that bill,
            and the customer cannot be changed — use “Cancel &amp; reissue” on the bill page if the bill itself must change.
        </div>
        <form class="has-sticky-bar" @submit.prevent="submit(false)">
            <div class="row g-3">
                <div class="col-lg-8">
                    <!-- Customer -->
                    <div class="card mb-3"><div class="card-body">
                        <h2 class="h6">Customer</h2>
                        <CustomerPicker :initial="trip?.customer" :states="states" :error="err('customer_id')" @selected="onCustomer" />
                    </div></div>

                    <!-- Car & driver -->
                    <div class="card mb-3"><div class="card-body">
                        <h2 class="h6">Car &amp; driver</h2>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <select v-model="form.car_id" class="form-select" :class="{ 'is-invalid': err('car_id') }">
                                    <option value="" disabled>Select car</option>
                                    <option v-for="c in cars" :key="c.id" :value="c.id">{{ c.registration_no }} · {{ c.model }}</option>
                                </select>
                                <div class="invalid-feedback">{{ err('car_id') }}</div>
                            </div>
                            <div class="col-md-6">
                                <select v-model="form.driver_id" class="form-select">
                                    <option value="">Driver (optional)</option>
                                    <option v-for="d in drivers" :key="d.id" :value="d.id">{{ d.name }}</option>
                                </select>
                            </div>
                        </div>
                    </div></div>

                    <!-- Trip -->
                    <div class="card mb-3"><div class="card-body">
                        <h2 class="h6">Trip</h2>
                        <div class="row g-2">
                            <div class="col-6"><input v-model="form.pickup" class="form-control" placeholder="Pickup"></div>
                            <div class="col-6"><input v-model="form.drop" class="form-control" placeholder="Drop"></div>
                            <div class="col-md-6">
                                <label class="form-label small mb-0">Start</label>
                                <input v-model="form.start_at" type="datetime-local" class="form-control" :class="{ 'is-invalid': err('start_at') }">
                                <div class="invalid-feedback">{{ err('start_at') }}</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small mb-0">End</label>
                                <input v-model="form.end_at" type="datetime-local" class="form-control" :class="{ 'is-invalid': err('end_at') }">
                                <div class="invalid-feedback">{{ err('end_at') }}</div>
                            </div>
                            <div class="col-6">
                                <label class="form-label small mb-0">Start km</label>
                                <input v-model="form.start_km" type="number" inputmode="numeric" class="form-control" :class="{ 'is-invalid': err('start_km') }">
                                <div class="invalid-feedback">{{ err('start_km') }}</div>
                            </div>
                            <div class="col-6">
                                <label class="form-label small mb-0">End km</label>
                                <input v-model="form.end_km" type="number" inputmode="numeric" class="form-control" :class="{ 'is-invalid': err('end_km') }">
                                <div class="invalid-feedback">{{ err('end_km') }}</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small mb-0">Status</label>
                                <select v-model="form.status" class="form-select" :class="{ 'is-invalid': err('status') }">
                                    <option value="booked">Booked (advance only)</option>
                                    <option value="ongoing">Ongoing</option>
                                    <option value="completed">Completed</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                                <div class="invalid-feedback">{{ err('status') }}</div>
                            </div>
                        </div>
                    </div></div>

                    <!-- Charges -->
                    <div class="card mb-3"><div class="card-body">
                        <h2 class="h6">Charges</h2>
                        <div class="btn-group w-100 mb-3" role="group" aria-label="Trip type">
                            <input id="tt-km" v-model="form.trip_type" type="radio" class="btn-check" value="per_km">
                            <label class="btn btn-outline-primary" for="tt-km">Per km</label>
                            <input id="tt-pkg" v-model="form.trip_type" type="radio" class="btn-check" value="package">
                            <label class="btn btn-outline-primary" for="tt-pkg">Package / per day</label>
                        </div>

                        <div v-if="form.trip_type === 'per_km'" class="row g-2">
                            <div class="col-6">
                                <label class="form-label small mb-0">Rate per km (₹)</label>
                                <input v-model="form.billing.rate_per_km" type="number" step="any" inputmode="decimal" class="form-control">
                            </div>
                            <div v-if="settings.min_km_per_day" class="col-6 small text-muted align-self-end">
                                Minimum {{ settings.min_km_per_day }} km/day applies.
                            </div>
                        </div>

                        <div v-else class="row g-2">
                            <div class="col-12">
                                <div class="form-check form-check-inline">
                                    <input id="pm-fixed" v-model="form.billing.package_mode" class="form-check-input" type="radio" value="fixed">
                                    <label class="form-check-label" for="pm-fixed">Fixed package</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input id="pm-day" v-model="form.billing.package_mode" class="form-check-input" type="radio" value="per_day">
                                    <label class="form-check-label" for="pm-day">Rate per day</label>
                                </div>
                            </div>
                            <div class="col-6" v-if="form.billing.package_mode === 'fixed'">
                                <label class="form-label small mb-0">Package amount (₹)</label>
                                <input v-model="form.billing.package_amount" type="number" step="any" inputmode="decimal" class="form-control">
                            </div>
                            <div class="col-6" v-else>
                                <label class="form-label small mb-0">Rate per day (₹)</label>
                                <input v-model="form.billing.package_rate_per_day" type="number" step="any" inputmode="decimal" class="form-control">
                            </div>
                            <div class="col-3">
                                <label class="form-label small mb-0">Incl. km</label>
                                <input v-model="form.billing.included_km" type="number" inputmode="numeric" class="form-control">
                            </div>
                            <div class="col-3">
                                <label class="form-label small mb-0">Incl. hrs</label>
                                <input v-model="form.billing.included_hours" type="number" inputmode="numeric" class="form-control">
                            </div>
                            <div class="col-6">
                                <label class="form-label small mb-0">Extra km rate (₹)</label>
                                <input v-model="form.billing.extra_km_rate" type="number" step="any" inputmode="decimal" class="form-control">
                            </div>
                            <div class="col-6">
                                <label class="form-label small mb-0">Extra hour rate (₹)</label>
                                <input v-model="form.billing.extra_hour_rate" type="number" step="any" inputmode="decimal" class="form-control">
                            </div>
                        </div>

                        <hr>
                        <div class="row g-2">
                            <div v-for="[key, label] in money" :key="key" class="col-6 col-md-4">
                                <label class="form-label small mb-0">{{ label }}</label>
                                <input v-model="form.billing[key]" type="number" step="any" inputmode="decimal" class="form-control"
                                       :class="{ 'is-invalid': err('billing.' + key) }">
                                <div class="invalid-feedback">{{ err('billing.' + key) }}</div>
                            </div>
                            <div class="col-6 col-md-4">
                                <label class="form-label small mb-0">Other charge label</label>
                                <input v-model="form.billing.other_taxable_label" class="form-control" placeholder="e.g. Decoration">
                            </div>
                            <div class="col-6 col-md-4">
                                <label class="form-label small mb-0">Other charge (₹)</label>
                                <input v-model="form.billing.other_taxable" type="number" step="any" inputmode="decimal" class="form-control">
                            </div>
                        </div>

                        <hr>
                        <div class="form-check form-switch">
                            <input id="gst" v-model="form.billing.gst_enabled" class="form-check-input" type="checkbox"
                                   :disabled="!settings.gst_registered">
                            <label class="form-check-label" for="gst">GST bill ({{ Number(settings.gst_rate) }}%)</label>
                        </div>
                        <div v-if="!settings.gst_registered" class="form-text">Add the business GSTIN in Settings to issue GST bills.</div>
                    </div></div>

                    <div class="card mb-3"><div class="card-body">
                        <label class="form-label small mb-0" for="notes">Notes (not printed)</label>
                        <textarea id="notes" v-model="form.notes" rows="2" class="form-control"></textarea>
                    </div></div>
                </div>

                <div class="col-lg-4">
                    <div class="position-sticky" style="top: 70px">
                        <BillPreview :preview="preview" :error="previewError" :loading="previewLoading" />
                    </div>
                </div>
            </div>

            <div class="sticky-bar shadow">
                <div class="container-lg d-flex align-items-center gap-2">
                    <div class="me-auto">
                        <div class="small text-muted lh-1">Total</div>
                        <div class="fw-bold fs-5 money">{{ preview ? rupees(preview.total) : '—' }}</div>
                    </div>
                    <Link :href="trip ? `/trips/${trip.id}` : '/trips'" class="btn btn-outline-secondary d-none d-md-inline-block">Cancel</Link>
                    <button type="submit" class="btn btn-outline-primary" :disabled="form.processing">Save</button>
                    <button v-if="form.status === 'completed'" type="button" class="btn btn-brand"
                            :disabled="form.processing || !preview" @click="submit(true)">
                        {{ trip?.locked ? 'Save' : 'Save & bill' }}
                    </button>
                </div>
            </div>
        </form>
    </AppLayout>
</template>
