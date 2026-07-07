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
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const recovery = ref(false);

const form = useForm({
    code: '',
    recovery_code: '',
});

const toggleRecovery = () => {
    recovery.value = !recovery.value;
    form.code = '';
    form.recovery_code = '';
};

const submit = () => {
    form.post('/two-factor-challenge');
};
</script>

<template>
    <Head title="2FA verificatie" />

    <GuestLayout>
        <Card class="border-none shadow-none">
            <CardHeader class="px-0 pt-0">
                <CardTitle>Two-factor verificatie</CardTitle>
                <CardDescription>
                    Bevestig je login met een code uit je authenticator-app.
                </CardDescription>
            </CardHeader>
            <CardContent class="px-0 pb-0">
                <form class="grid gap-4" @submit.prevent="submit">
                    <div v-if="!recovery" class="grid gap-2">
                        <Label for="code">Authenticatiecode</Label>
                        <Input
                            id="code"
                            v-model="form.code"
                            autocomplete="one-time-code"
                            autofocus
                        />
                        <p v-if="form.errors.code" class="text-sm text-red-600">
                            {{ form.errors.code }}
                        </p>
                    </div>

                    <div v-else class="grid gap-2">
                        <Label for="recovery_code">Recovery code</Label>
                        <Input
                            id="recovery_code"
                            v-model="form.recovery_code"
                            autocomplete="one-time-code"
                            autofocus
                        />
                        <p
                            v-if="form.errors.recovery_code"
                            class="text-sm text-red-600"
                        >
                            {{ form.errors.recovery_code }}
                        </p>
                    </div>

                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <Button
                            type="button"
                            variant="ghost"
                            @click="toggleRecovery"
                        >
                            {{
                                recovery
                                    ? 'Gebruik authenticator code'
                                    : 'Gebruik recovery code'
                            }}
                        </Button>
                        <Button type="submit" :disabled="form.processing">
                            Bevestigen
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </GuestLayout>
</template>
