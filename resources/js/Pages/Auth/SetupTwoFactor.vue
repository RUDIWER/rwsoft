<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onMounted, ref } from 'vue';

const page = usePage();

const statusMessage = computed(() => page.props.flash?.status ?? null);

const qrCodeSvg = ref('');
const recoveryCodes = ref([]);
const setupKey = ref('');
const confirmationCode = ref('');
const loading = ref(false);
const confirming = ref(false);
const setupEnabled = ref(false);

const hasConfirmedTwoFactor = computed(() => {
    const user = page.props.auth?.user;

    return Boolean(user?.two_factor_confirmed_at);
});

const fetchSetupData = async () => {
    try {
        loading.value = true;

        const [qrCodeResponse, setupKeyResponse, recoveryCodeResponse] =
            await Promise.all([
                axios.get('/user/two-factor-qr-code'),
                axios.get('/user/two-factor-secret-key'),
                axios.get('/user/two-factor-recovery-codes'),
            ]);

        qrCodeSvg.value = qrCodeResponse.data?.svg ?? '';
        setupKey.value = setupKeyResponse.data?.secretKey ?? '';
        recoveryCodes.value = recoveryCodeResponse.data ?? [];
    } finally {
        loading.value = false;
    }
};

const enableTwoFactor = async () => {
    try {
        loading.value = true;
        await axios.post('/user/two-factor-authentication');
        setupEnabled.value = true;
        await fetchSetupData();
    } finally {
        loading.value = false;
    }
};

const confirmTwoFactor = async () => {
    if (!confirmationCode.value) {
        return;
    }

    try {
        confirming.value = true;
        await axios.post('/user/confirmed-two-factor-authentication', {
            code: confirmationCode.value,
        });
        router.visit(route('admin'));
    } finally {
        confirming.value = false;
    }
};

onMounted(() => {
    if (hasConfirmedTwoFactor.value) {
        router.visit(route('admin'));
    }
});
</script>

<template>
    <Head title="2FA instellen" />

    <GuestLayout>
        <Card class="border-none shadow-none">
            <CardHeader class="px-0 pt-0">
                <CardTitle>Two-factor authenticatie verplicht</CardTitle>
                <CardDescription>
                    Voor toegang tot de admin moet je eerst 2FA activeren en
                    bevestigen.
                </CardDescription>
            </CardHeader>
            <CardContent class="grid gap-4 px-0 pb-0">
                <p
                    v-if="statusMessage"
                    class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800"
                >
                    {{ statusMessage }}
                </p>

                <div v-if="!setupEnabled && !loading" class="grid gap-3">
                    <Button @click="enableTwoFactor">2FA activeren</Button>
                    <p class="text-sm text-slate-600">
                        Klik op activeren om je QR-code en setup sleutel op te
                        halen.
                    </p>
                </div>

                <div v-if="loading" class="text-sm text-slate-600">
                    Laden...
                </div>

                <div v-if="setupEnabled && !loading" class="grid gap-4">
                    <div class="rounded-lg border border-slate-200 p-4">
                        <p class="mb-3 text-sm text-slate-700">
                            Scan deze QR-code met je authenticator-app:
                        </p>
                        <div class="overflow-auto" v-html="qrCodeSvg" />
                    </div>

                    <div
                        class="rounded-lg border border-slate-200 p-4 text-sm text-slate-700"
                    >
                        <p class="font-medium">Setup sleutel</p>
                        <p class="mt-1 font-mono">{{ setupKey }}</p>
                    </div>

                    <div
                        class="rounded-lg border border-slate-200 p-4 text-sm text-slate-700"
                    >
                        <p class="font-medium">Recovery codes</p>
                        <ul class="mt-2 grid gap-1 font-mono text-xs">
                            <li v-for="code in recoveryCodes" :key="code">
                                {{ code }}
                            </li>
                        </ul>
                    </div>

                    <div class="grid gap-2">
                        <Label for="confirmation_code"
                            >Bevestigingscode uit app</Label
                        >
                        <Input
                            id="confirmation_code"
                            v-model="confirmationCode"
                        />
                    </div>

                    <Button :disabled="confirming" @click="confirmTwoFactor"
                        >Bevestigen en doorgaan</Button
                    >
                </div>
            </CardContent>
        </Card>
    </GuestLayout>
</template>
