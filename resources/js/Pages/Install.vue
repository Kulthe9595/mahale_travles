<script setup>
import { Head, useForm } from '@inertiajs/vue3';

defineProps({ databaseOk: Boolean });
const form = useForm({ install_code: '', name: 'Mahale', email: 'mahale.veeru1@gmail.com', password: '', password_confirmation: '' });
const submit = () => form.post('/install');
</script>

<template>
    <Head title="Setup" />
    <div class="min-vh-100 d-flex align-items-center login-bg py-4">
        <div class="container" style="max-width: 460px">
            <div class="card shadow login-card">
                <div class="card-body p-4">
                    <div class="text-center mb-3">
                        <img src="/brand/logo.png" alt="Mahale's Travels" style="height: 80px; width: auto">
                    </div>
                    <h1 class="h5 text-center mb-1">First-time setup</h1>
                    <p class="text-center small text-muted mb-3">Creates the database tables and the owner login. Runs only once.</p>

                    <div v-if="databaseOk" class="alert alert-success py-2 small">✓ Database connected</div>
                    <div v-else class="alert alert-danger py-2 small">
                        Can't reach the database. In File Manager, open <code>.env</code> and check the
                        <code>DB_HOST</code>, <code>DB_DATABASE</code>, <code>DB_USERNAME</code> and <code>DB_PASSWORD</code> lines.
                    </div>

                    <form class="d-grid gap-3" @submit.prevent="submit" novalidate>
                        <div>
                            <label class="form-label" for="install_code">Setup code (from README.txt)</label>
                            <input id="install_code" v-model="form.install_code" class="form-control" :class="{ 'is-invalid': form.errors.install_code }" autocomplete="off">
                            <div class="invalid-feedback">{{ form.errors.install_code }}</div>
                        </div>
                        <div>
                            <label class="form-label" for="name">Owner name</label>
                            <input id="name" v-model="form.name" class="form-control" :class="{ 'is-invalid': form.errors.name }">
                            <div class="invalid-feedback">{{ form.errors.name }}</div>
                        </div>
                        <div>
                            <label class="form-label" for="email">Login email</label>
                            <input id="email" v-model="form.email" type="email" class="form-control" :class="{ 'is-invalid': form.errors.email }">
                            <div class="invalid-feedback">{{ form.errors.email }}</div>
                        </div>
                        <div>
                            <label class="form-label" for="password">Password (min 8 characters)</label>
                            <input id="password" v-model="form.password" type="password" autocomplete="new-password" class="form-control" :class="{ 'is-invalid': form.errors.password }">
                            <div class="invalid-feedback">{{ form.errors.password }}</div>
                        </div>
                        <div>
                            <label class="form-label" for="password_confirmation">Repeat password</label>
                            <input id="password_confirmation" v-model="form.password_confirmation" type="password" autocomplete="new-password" class="form-control">
                        </div>
                        <button class="btn btn-brand btn-lg" type="submit" :disabled="form.processing || !databaseOk">
                            {{ form.processing ? 'Setting up…' : 'Finish setup' }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>
