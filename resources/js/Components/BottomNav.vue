<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const page = usePage();
const isOwner = computed(() => page.props.auth?.user?.role === 'owner');
const sheetOpen = ref(false);
const keyboardOpen = ref(false);

const tabs = [
    { key: 'home', label: 'Home', href: '/' },
    { key: 'bills', label: 'Bills', href: '/trips' },
    { key: 'new', label: 'New', href: '/trips/create' },
    { key: 'reports', label: 'Reports', href: '/reports' },
    { key: 'settings', label: 'Settings', href: null },
];

const active = computed(() => {
    const url = page.url.split('?')[0];
    if (url === '/trips/create') return 'new';
    if (url.startsWith('/trips')) return 'bills';
    if (url.startsWith('/reports')) return 'reports';
    if (['/cars', '/drivers', '/customers', '/settings'].some((p) => url.startsWith(p))) return 'settings';
    return 'home';
});
const activeIndex = computed(() => tabs.findIndex((t) => t.key === active.value));

const tap = (tab) => {
    if (tab.key === 'settings') { sheetOpen.value = !sheetOpen.value; return; }
    sheetOpen.value = false;
};
const go = (href) => { sheetOpen.value = false; router.visit(href); };
const logout = () => { sheetOpen.value = false; router.post('/logout'); };

// Hide the bar while the on-screen keyboard is up.
const onViewport = () => {
    const vv = window.visualViewport;
    keyboardOpen.value = !!vv && window.innerHeight - vv.height > 150;
};
onMounted(() => window.visualViewport?.addEventListener('resize', onViewport));
onBeforeUnmount(() => window.visualViewport?.removeEventListener('resize', onViewport));
</script>

<template>
    <Transition name="mt-fade">
        <div v-if="sheetOpen" class="mt-sheet-backdrop d-lg-none" @click="sheetOpen = false"></div>
    </Transition>
    <Transition name="mt-sheet">
        <div v-if="sheetOpen" class="mt-sheet d-lg-none" role="dialog" aria-label="Settings">
            <div class="mt-sheet-handle"></div>
            <button v-if="isOwner" type="button" class="mt-sheet-item" @click="go('/settings')">
                <span class="mt-sheet-icon">🏢</span>Business settings
            </button>
            <button type="button" class="mt-sheet-item" @click="go('/cars')"><span class="mt-sheet-icon">🚗</span>Cars</button>
            <button type="button" class="mt-sheet-item" @click="go('/drivers')"><span class="mt-sheet-icon">🧑‍✈️</span>Drivers</button>
            <button type="button" class="mt-sheet-item" @click="go('/customers')"><span class="mt-sheet-icon">👥</span>Customers</button>
            <button type="button" class="mt-sheet-item text-danger" @click="logout"><span class="mt-sheet-icon">⎋</span>Logout</button>
        </div>
    </Transition>

    <nav class="mt-tabbar d-lg-none" :class="{ 'mt-tabbar-hidden': keyboardOpen }"
         :style="{ '--mt-tab': activeIndex }" aria-label="Main">
        <div class="mt-tabbar-bg"></div>
        <div class="mt-tab-bubble" aria-hidden="true"></div>
        <template v-for="(tab, i) in tabs" :key="tab.key">
            <component :is="tab.href ? Link : 'button'" :href="tab.href ?? undefined" :type="tab.href ? undefined : 'button'"
                       class="mt-tab" :class="{ active: i === activeIndex }"
                       :aria-current="i === activeIndex ? 'page' : undefined" @click="tap(tab)">
                <svg class="mt-tab-icon" viewBox="0 0 24 24" aria-hidden="true">
                    <path v-if="tab.key === 'home'" d="M3 11.5 12 4l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z" />
                    <path v-else-if="tab.key === 'bills'" d="M6 2h12a1 1 0 0 1 1 1v18l-3-2-2 2-2-2-2 2-2-2-3 2V3a1 1 0 0 1 1-1zm2 5v2h8V7zm0 4v2h8v-2zm0 4v2h5v-2z" />
                    <path v-else-if="tab.key === 'new'" d="M11 4h2v7h7v2h-7v7h-2v-7H4v-2h7z" />
                    <path v-else-if="tab.key === 'reports'" d="M4 20V10h3v10zm6.5 0V4h3v16zM17 20v-7h3v7z" />
                    <path v-else d="M19.4 13a7.5 7.5 0 0 0 0-2l2.1-1.6-2-3.5-2.5 1a7 7 0 0 0-1.7-1L15 3h-4l-.4 2.9a7 7 0 0 0-1.7 1l-2.5-1-2 3.5L6.6 11a7.5 7.5 0 0 0 0 2l-2.1 1.6 2 3.5 2.5-1a7 7 0 0 0 1.7 1L11 21h4l.4-2.9a7 7 0 0 0 1.7-1l2.5 1 2-3.5zM13 15.5A3.5 3.5 0 1 1 13 8.5a3.5 3.5 0 0 1 0 7z" />
                </svg>
                <span class="mt-tab-label">{{ tab.label }}</span>
            </component>
        </template>
    </nav>
</template>
