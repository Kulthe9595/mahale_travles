<script setup>
import { Link, usePage, router } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({ title: { type: String, default: '' } });

const page = usePage();
const user = computed(() => page.props.auth?.user);
const flash = computed(() => page.props.flash ?? {});
const isOwner = computed(() => user.value?.role === 'owner');

const links = [
    { href: '/', label: 'Dashboard' },
    { href: '/trips', label: 'Bills' },
    { href: '/cars', label: 'Cars' },
    { href: '/drivers', label: 'Drivers' },
    { href: '/customers', label: 'Customers' },
    { href: '/reports', label: 'Reports' },
];

const isActive = (href) =>
    href === '/' ? page.url === '/' : page.url.startsWith(href);

const logout = () => router.post('/logout');
</script>

<template>
    <nav class="navbar navbar-expand-lg navbar-light navbar-mt sticky-top">
        <div class="container-lg">
            <Link class="navbar-brand d-flex align-items-center gap-2" href="/">
                <img src="/brand/logo.png" alt="Mahale's Travels">
                <span>Mahale Tours<small>BILLING</small></span>
            </Link>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                    data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false"
                    aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div id="mainNav" class="collapse navbar-collapse">
                <ul class="navbar-nav me-auto">
                    <li v-for="link in links" :key="link.href" class="nav-item">
                        <Link class="nav-link" :class="{ active: isActive(link.href) }" :href="link.href">
                            {{ link.label }}
                        </Link>
                    </li>
                    <li v-if="isOwner" class="nav-item">
                        <Link class="nav-link" :class="{ active: isActive('/settings') }" href="/settings">Settings</Link>
                    </li>
                </ul>
                <div v-if="user" class="d-flex align-items-center gap-2 text-muted small">
                    <span>{{ user.name }}</span>
                    <button class="btn btn-sm btn-outline-secondary" type="button" @click="logout">Logout</button>
                </div>
            </div>
        </div>
    </nav>

    <main class="container-lg py-3">
        <h1 v-if="title" class="h4 mb-3">{{ title }}</h1>
        <div v-if="flash.success" class="alert alert-success py-2">{{ flash.success }}</div>
        <div v-if="flash.error" class="alert alert-danger py-2">{{ flash.error }}</div>
        <slot />
    </main>
</template>
