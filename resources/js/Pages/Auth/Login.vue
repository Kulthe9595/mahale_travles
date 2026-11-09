<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({ email: '', password: '', remember: true });
const submit = () => form.post('/login', { onFinish: () => form.reset('password') });
</script>

<template>
    <Head title="Login" />
    <div class="min-vh-100 d-flex align-items-center login-bg">
        <div class="container" style="max-width: 420px">
            <div class="card shadow login-card">
                <div class="card-body p-4">
                    <div class="text-center mb-3">
                        <img src="/brand/logo.png" alt="Mahale's Travels" style="height: 96px; width: auto">
                    </div>
                    <h1 class="h5 text-center mb-1">Mahale Tours and Travels</h1>
                    <p class="text-center small mb-4" style="color: var(--mt-orange); font-weight: 600">Billing</p>
                    <form @submit.prevent="submit" novalidate>
                        <div class="mb-3">
                            <label class="form-label" for="email">Email</label>
                            <input id="email" v-model="form.email" type="email" autocomplete="username"
                                   class="form-control" :class="{ 'is-invalid': form.errors.email }" required autofocus>
                            <div class="invalid-feedback">{{ form.errors.email }}</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="password">Password</label>
                            <input id="password" v-model="form.password" type="password" autocomplete="current-password"
                                   class="form-control" :class="{ 'is-invalid': form.errors.password }" required>
                            <div class="invalid-feedback">{{ form.errors.password }}</div>
                        </div>
                        <div class="form-check mb-3">
                            <input id="remember" v-model="form.remember" class="form-check-input" type="checkbox">
                            <label class="form-check-label" for="remember">Keep me logged in</label>
                        </div>
                        <button class="btn btn-brand w-100" type="submit" :disabled="form.processing">Login</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>
