<template>
    <Head :title="t('platform.mail.meta_title', 'Mail delivery')" />

    <PlatformLayout :title="t('platform.mail.title', 'Mail delivery')">
        <Card class="rounded-none shadow-none">
            <CardHeader class="gap-0 border-b border-slate-200 p-0">
                <div
                    class="flex flex-wrap items-start justify-between gap-3 px-4 py-4 sm:px-5"
                >
                    <div class="flex min-w-0 items-start gap-3">
                        <div
                            class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-blue-50 text-blue-700 ring-1 ring-blue-100"
                            aria-hidden="true"
                        >
                            <span class="mdi mdi-email-fast text-2xl" />
                        </div>
                        <div class="min-w-0">
                            <CardTitle class="text-lg">
                                {{ t('platform.mail.title', 'Mail delivery') }}
                            </CardTitle>
                            <CardDescription class="mt-1">
                                {{
                                    t(
                                        'platform.mail.description',
                                        'Configure the global mail transport used by all CMS emails.',
                                    )
                                }}
                            </CardDescription>
                        </div>
                    </div>

                    <div class="flex flex-wrap justify-end gap-2">
                        <AdminFormBackButton
                            :href="route('platform.dashboard')"
                            :dirty="form.isDirty"
                            :processing="form.processing"
                            @save="saveTransport"
                        />
                        <Button
                            type="button"
                            variant="outline"
                            class="gap-2 border-blue-200 text-blue-700 shadow-none hover:bg-blue-50 hover:text-blue-800"
                            :disabled="form.processing"
                            @click="testTransport"
                        >
                            <span
                                class="mdi mdi-email-fast text-base"
                                aria-hidden="true"
                            />
                            {{
                                t(
                                    'platform.mail.actions.test',
                                    'Send test mail',
                                )
                            }}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            class="gap-2 border-blue-200 text-blue-700 shadow-none hover:bg-blue-50 hover:text-blue-800"
                            :disabled="activateForm.processing || !canActivate"
                            @click="activateTransport"
                        >
                            <span
                                class="mdi mdi-check-circle text-base"
                                aria-hidden="true"
                            />
                            {{
                                t(
                                    'platform.mail.actions.activate',
                                    'Activate transport',
                                )
                            }}
                        </Button>
                        <AdminFormSaveButton
                            type="button"
                            :dirty="form.isDirty || hasBlockingClientIssues"
                            :processing="form.processing"
                            @click="saveTransport"
                        />
                    </div>
                </div>
            </CardHeader>

            <div
                class="grid gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3 sm:px-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]"
            >
                <div :class="statusCardClass">
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <div>
                            <div class="text-sm font-semibold">
                                {{ statusLabel }}
                            </div>
                            <div class="mt-1 text-xs">{{ statusHelp }}</div>
                        </div>
                        <span
                            class="rounded-full bg-white/70 px-2 py-0.5 text-xs font-semibold"
                        >
                            {{
                                transport?.is_active
                                    ? t('platform.mail.status.active', 'Active')
                                    : t(
                                          'platform.mail.status.inactive',
                                          'Inactive',
                                      )
                            }}
                        </span>
                    </div>
                </div>

                <dl class="grid gap-2 text-sm sm:grid-cols-3">
                    <div>
                        <dt class="font-medium text-slate-600">
                            {{
                                t(
                                    'platform.mail.status.last_test',
                                    'Last test',
                                )
                            }}:
                        </dt>
                        <dd class="font-bold text-slate-950">
                            {{ transport?.last_tested_at || '-' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="font-medium text-slate-600">
                            {{ t('platform.mail.status.secret', 'Secret') }}:
                        </dt>
                        <dd class="font-bold text-slate-950">
                            {{ transport?.has_secret ? '••••••••' : '-' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="font-medium text-slate-600">
                            {{
                                t('platform.mail.fields.provider', 'Provider')
                            }}:
                        </dt>
                        <dd class="font-bold text-slate-950">
                            {{ transport?.provider || 'smtp' }}
                        </dd>
                    </div>
                </dl>

                <div
                    v-if="transport?.last_test_error"
                    class="rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700 lg:col-span-2"
                >
                    {{ transport.last_test_error }}
                </div>
            </div>

            <div
                v-if="hasClientErrors || hasServerErrors"
                class="border-b border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 sm:px-5"
            >
                <div class="font-semibold">
                    {{
                        t(
                            'platform.mail.validation.summary_title',
                            'Saving is blocked',
                        )
                    }}
                </div>
                <div class="mt-1">
                    {{
                        t(
                            'platform.mail.validation.summary_description',
                            'Resolve the fields below and try again.',
                        )
                    }}
                </div>
            </div>

            <CardContent class="p-4 sm:p-5">
                <form
                    id="platform-mail-transport-form"
                    class="grid gap-5"
                    @submit.prevent="saveTransport"
                >
                    <section class="grid gap-4">
                        <div>
                            <h2 class="text-base font-semibold text-slate-900">
                                {{
                                    t(
                                        'platform.mail.sections.general',
                                        'General',
                                    )
                                }}
                            </h2>
                            <p class="mt-1 text-sm text-slate-600">
                                {{
                                    t(
                                        'platform.mail.sections.general_help',
                                        'Name this transport and choose the provider.',
                                    )
                                }}
                            </p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <RequiredLabel
                                    for-id="name"
                                    :label="
                                        t('platform.mail.fields.name', 'Name')
                                    "
                                />
                                <Input
                                    id="name"
                                    v-model="form.name"
                                    autocomplete="off"
                                    class="bg-yellow-50"
                                    @blur="touchField('name')"
                                />
                                <FieldError :message="fieldError('name')" />
                            </div>

                            <div class="grid gap-2">
                                <RequiredLabel
                                    for-id="provider"
                                    :label="
                                        t(
                                            'platform.mail.fields.provider',
                                            'Provider',
                                        )
                                    "
                                />
                                <select
                                    id="provider"
                                    v-model="form.provider"
                                    class="h-10 rounded-md border border-slate-300 bg-yellow-50 px-3 text-sm text-slate-900"
                                    @blur="touchField('provider')"
                                >
                                    <option
                                        v-for="providerOption in providerOptions"
                                        :key="providerOption.value"
                                        :value="providerOption.value"
                                    >
                                        {{ providerOption.label }}
                                    </option>
                                </select>
                                <FieldError :message="fieldError('provider')" />
                            </div>
                        </div>
                    </section>

                    <section
                        v-if="isSmtpProvider"
                        class="grid gap-4 border-t border-slate-200 pt-5"
                    >
                        <div>
                            <h2 class="text-base font-semibold text-slate-900">
                                {{
                                    t('platform.mail.sections.sender', 'Sender')
                                }}
                            </h2>
                            <p class="mt-1 text-sm text-slate-600">
                                {{
                                    t(
                                        'platform.mail.sections.sender_help',
                                        'These values are used when an individual email has no sender override.',
                                    )
                                }}
                            </p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <RequiredLabel
                                    for-id="from_name"
                                    :label="
                                        t(
                                            'platform.mail.fields.from_name',
                                            'From name',
                                        )
                                    "
                                />
                                <Input
                                    id="from_name"
                                    v-model="form.from_name"
                                    autocomplete="off"
                                    class="bg-yellow-50"
                                    @blur="touchField('from_name')"
                                />
                                <FieldError
                                    :message="fieldError('from_name')"
                                />
                            </div>

                            <div class="grid gap-2">
                                <RequiredLabel
                                    for-id="from_email"
                                    :label="
                                        t(
                                            'platform.mail.fields.from_email',
                                            'From email',
                                        )
                                    "
                                />
                                <Input
                                    id="from_email"
                                    v-model="form.from_email"
                                    autocomplete="off"
                                    class="bg-yellow-50"
                                    type="email"
                                    @blur="touchField('from_email')"
                                />
                                <FieldError
                                    :message="fieldError('from_email')"
                                />
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <Label for="reply_to_email">
                                {{
                                    t(
                                        'platform.mail.fields.reply_to_email',
                                        'Reply-to email',
                                    )
                                }}
                            </Label>
                            <Input
                                id="reply_to_email"
                                v-model="form.reply_to_email"
                                autocomplete="off"
                                type="email"
                                @blur="touchField('reply_to_email')"
                            />
                            <FieldError
                                :message="fieldError('reply_to_email')"
                            />
                        </div>
                    </section>

                    <section
                        v-if="!isSmtpProvider"
                        class="grid gap-4 border-t border-slate-200 pt-5"
                    >
                        <div>
                            <h2 class="text-base font-semibold text-slate-900">
                                {{ providerSectionTitle }}
                            </h2>
                            <p class="mt-1 text-sm text-slate-600">
                                {{ providerSectionHelp }}
                            </p>
                        </div>

                        <div
                            v-if="form.provider === 'mailgun'"
                            class="grid gap-4 sm:grid-cols-2"
                        >
                            <div class="grid gap-2">
                                <RequiredLabel
                                    for-id="mailgun_domain"
                                    :label="
                                        t(
                                            'platform.mail.fields.mailgun_domain',
                                            'Mailgun domain',
                                        )
                                    "
                                />
                                <Input
                                    id="mailgun_domain"
                                    v-model="form.provider_config.domain"
                                    class="bg-yellow-50"
                                    placeholder="mg.example.com"
                                    @blur="touchField('provider_config.domain')"
                                />
                                <FieldError
                                    :message="
                                        fieldError('provider_config.domain')
                                    "
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label for="mailgun_endpoint">
                                    {{
                                        t(
                                            'platform.mail.fields.mailgun_endpoint',
                                            'Mailgun endpoint',
                                        )
                                    }}
                                </Label>
                                <Input
                                    id="mailgun_endpoint"
                                    v-model="form.provider_config.endpoint"
                                    placeholder="api.mailgun.net"
                                />
                            </div>
                        </div>

                        <div
                            v-if="form.provider === 'postmark'"
                            class="grid gap-2"
                        >
                            <Label for="postmark_stream">
                                {{
                                    t(
                                        'platform.mail.fields.postmark_stream',
                                        'Message stream ID',
                                    )
                                }}
                            </Label>
                            <Input
                                id="postmark_stream"
                                v-model="form.provider_config.message_stream_id"
                                placeholder="outbound"
                            />
                        </div>

                        <div
                            v-if="form.provider === 'ses'"
                            class="grid gap-4 sm:grid-cols-2"
                        >
                            <div class="grid gap-2">
                                <RequiredLabel
                                    for-id="ses_access_key"
                                    :label="
                                        t(
                                            'platform.mail.fields.ses_access_key',
                                            'AWS access key ID',
                                        )
                                    "
                                />
                                <Input
                                    id="ses_access_key"
                                    v-model="form.username"
                                    class="bg-yellow-50"
                                    autocomplete="off"
                                    @blur="touchField('username')"
                                />
                                <FieldError :message="fieldError('username')" />
                            </div>

                            <div class="grid gap-2">
                                <RequiredLabel
                                    for-id="ses_region"
                                    :label="
                                        t(
                                            'platform.mail.fields.ses_region',
                                            'AWS region',
                                        )
                                    "
                                />
                                <Input
                                    id="ses_region"
                                    v-model="form.provider_config.region"
                                    class="bg-yellow-50"
                                    placeholder="us-east-1"
                                    @blur="touchField('provider_config.region')"
                                />
                                <FieldError
                                    :message="
                                        fieldError('provider_config.region')
                                    "
                                />
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <RequiredLabel
                                for-id="api_secret"
                                :label="providerSecretLabel"
                            />
                            <Input
                                id="api_secret"
                                v-model="form.secret"
                                type="password"
                                autocomplete="new-password"
                                class="bg-yellow-50"
                                :placeholder="secretPlaceholder"
                                @blur="touchField('secret')"
                            />
                            <p class="text-xs text-slate-500">
                                {{
                                    t(
                                        'platform.mail.fields.secret_help',
                                        'Leave empty to keep the current secret.',
                                    )
                                }}
                            </p>
                            <FieldError :message="fieldError('secret')" />
                        </div>
                    </section>

                    <section
                        v-if="isSmtpProvider"
                        class="grid gap-4 border-t border-slate-200 pt-5"
                    >
                        <div>
                            <h2 class="text-base font-semibold text-slate-900">
                                {{
                                    t(
                                        'platform.mail.sections.smtp',
                                        'SMTP server',
                                    )
                                }}
                            </h2>
                            <p class="mt-1 text-sm text-slate-600">
                                {{
                                    t(
                                        'platform.mail.sections.smtp_help',
                                        'Use the SMTP settings provided by your mail provider.',
                                    )
                                }}
                            </p>
                        </div>

                        <div
                            class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_140px_160px]"
                        >
                            <div class="grid gap-2">
                                <RequiredLabel
                                    for-id="host"
                                    :label="
                                        t('platform.mail.fields.host', 'Host')
                                    "
                                />
                                <Input
                                    id="host"
                                    v-model="form.host"
                                    autocomplete="off"
                                    class="bg-yellow-50"
                                    :placeholder="
                                        t(
                                            'platform.mail.fields.host_placeholder',
                                            'smtp.example.com',
                                        )
                                    "
                                    @blur="touchField('host')"
                                />
                                <FieldError :message="fieldError('host')" />
                            </div>

                            <div class="grid gap-2">
                                <RequiredLabel
                                    for-id="port"
                                    :label="
                                        t('platform.mail.fields.port', 'Port')
                                    "
                                />
                                <Input
                                    id="port"
                                    v-model="form.port"
                                    autocomplete="off"
                                    class="bg-yellow-50"
                                    type="number"
                                    min="1"
                                    max="65535"
                                    @blur="touchField('port')"
                                />
                                <FieldError :message="fieldError('port')" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="encryption">
                                    {{
                                        t(
                                            'platform.mail.fields.encryption',
                                            'Encryption',
                                        )
                                    }}
                                </Label>
                                <select
                                    id="encryption"
                                    v-model="form.encryption"
                                    class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                                >
                                    <option value="">
                                        {{
                                            t(
                                                'platform.mail.encryption.none',
                                                'None',
                                            )
                                        }}
                                    </option>
                                    <option value="tls">
                                        {{
                                            t(
                                                'platform.mail.encryption.tls',
                                                'TLS',
                                            )
                                        }}
                                    </option>
                                    <option value="ssl">
                                        {{
                                            t(
                                                'platform.mail.encryption.ssl',
                                                'SSL',
                                            )
                                        }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="username">
                                    {{
                                        t(
                                            'platform.mail.fields.username',
                                            'Username',
                                        )
                                    }}
                                </Label>
                                <Input
                                    id="username"
                                    v-model="form.username"
                                    autocomplete="off"
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label for="secret">
                                    {{
                                        t(
                                            'platform.mail.fields.secret',
                                            'Password or API key',
                                        )
                                    }}
                                </Label>
                                <Input
                                    id="secret"
                                    v-model="form.secret"
                                    type="password"
                                    autocomplete="new-password"
                                    :placeholder="secretPlaceholder"
                                />
                                <p class="text-xs text-slate-500">
                                    {{
                                        t(
                                            'platform.mail.fields.secret_help',
                                            'Leave empty to keep the current secret.',
                                        )
                                    }}
                                </p>
                            </div>
                        </div>
                    </section>

                    <section class="grid gap-3 border-t border-slate-200 pt-5">
                        <h2 class="text-base font-semibold text-slate-900">
                            {{
                                t(
                                    'platform.mail.checklist.title',
                                    'Deliverability checklist',
                                )
                            }}
                        </h2>
                        <ul
                            class="grid gap-2 text-sm text-slate-700 sm:grid-cols-2"
                        >
                            <li>
                                {{
                                    t(
                                        'platform.mail.checklist.spf',
                                        'Add SPF records for your mail provider.',
                                    )
                                }}
                            </li>
                            <li>
                                {{
                                    t(
                                        'platform.mail.checklist.dkim',
                                        'Enable DKIM signing in your provider.',
                                    )
                                }}
                            </li>
                            <li>
                                {{
                                    t(
                                        'platform.mail.checklist.dmarc',
                                        'Add a DMARC policy for your domain.',
                                    )
                                }}
                            </li>
                            <li>
                                {{
                                    t(
                                        'platform.mail.checklist.from_domain',
                                        'Use a from address on a verified domain.',
                                    )
                                }}
                            </li>
                        </ul>
                    </section>
                </form>
            </CardContent>
        </Card>
    </PlatformLayout>
</template>

<script setup>
import AdminFormBackButton from '@/Components/Admin/Form/AdminFormBackButton.vue';
import AdminFormSaveButton from '@/Components/Admin/Form/AdminFormSaveButton.vue';
import PlatformLayout from '@/Layouts/PlatformLayout.vue';
import { useAdminTranslations } from '@/composables/useAdminTranslations';
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
import { computed, defineComponent, h, ref, watch } from 'vue';

const props = defineProps({
    transport: { type: Object, default: null },
});

const { t } = useAdminTranslations('admin_common_ui');

const providerOptions = computed(() => [
    { value: 'smtp', label: t('platform.mail.providers.smtp', 'SMTP') },
    {
        value: 'mailgun',
        label: t('platform.mail.providers.mailgun', 'Mailgun'),
    },
    {
        value: 'postmark',
        label: t('platform.mail.providers.postmark', 'Postmark'),
    },
    { value: 'ses', label: t('platform.mail.providers.ses', 'Amazon SES') },
    { value: 'resend', label: t('platform.mail.providers.resend', 'Resend') },
]);

const form = useForm({
    name:
        props.transport?.name ||
        t('platform.mail.default_name', 'Default SMTP'),
    provider: props.transport?.provider || 'smtp',
    from_name: props.transport?.from_name || '',
    from_email: props.transport?.from_email || '',
    reply_to_email: props.transport?.reply_to_email || '',
    host: props.transport?.host || '',
    port: props.transport?.port || 587,
    encryption: props.transport?.encryption || 'tls',
    username: props.transport?.username || '',
    secret: '',
    transport_has_secret: Boolean(props.transport?.has_secret),
    current_provider: props.transport?.provider || 'smtp',
    provider_config: {
        domain: props.transport?.provider_config?.domain || '',
        endpoint:
            props.transport?.provider_config?.endpoint || 'api.mailgun.net',
        region: props.transport?.provider_config?.region || 'us-east-1',
        message_stream_id:
            props.transport?.provider_config?.message_stream_id || '',
    },
});

const activateForm = useForm({});
const clientErrors = ref({});
const touchedFields = ref({});

const emailFields = ['from_email', 'reply_to_email'];

const transport = computed(() => props.transport);
const isSmtpProvider = computed(() => form.provider === 'smtp');
const currentProviderChanged = computed(
    () => String(form.current_provider || 'smtp') !== String(form.provider),
);
const requiredFields = computed(() => {
    const fields = ['name', 'provider', 'from_name', 'from_email'];

    if (form.provider === 'smtp') {
        fields.push('host', 'port');
    }

    if (form.provider === 'mailgun') {
        fields.push('provider_config.domain');
    }

    if (form.provider === 'ses') {
        fields.push('username', 'provider_config.region');
    }

    if (
        form.provider !== 'smtp' &&
        (!form.transport_has_secret || currentProviderChanged.value)
    ) {
        fields.push('secret');
    }

    return fields;
});
const hasClientErrors = computed(
    () => Object.keys(clientErrors.value).length > 0,
);
const hasServerErrors = computed(
    () => Object.keys(form.errors ?? {}).length > 0,
);
const hasBlockingClientIssues = computed(() => clientIssueFields().length > 0);
const canActivate = computed(
    () =>
        transport.value?.status === 'ready' &&
        transport.value?.last_test_status === 'success' &&
        !transport.value?.is_active,
);
const secretPlaceholder = computed(() =>
    transport.value?.has_secret && !currentProviderChanged.value
        ? '••••••••'
        : t(
              'platform.mail.fields.secret_placeholder',
              'Enter password or API key',
          ),
);
const providerSectionTitle = computed(() => {
    return t(
        `platform.mail.provider_sections.${form.provider}.title`,
        'Provider credentials',
    );
});
const providerSectionHelp = computed(() => {
    return t(
        `platform.mail.provider_sections.${form.provider}.help`,
        'Use the API credentials from your mail provider.',
    );
});
const providerSecretLabel = computed(() => {
    if (form.provider === 'ses') {
        return t('platform.mail.fields.ses_secret', 'AWS secret access key');
    }

    return t('platform.mail.fields.api_key', 'API key');
});

const statusLabel = computed(() => {
    const status = transport.value?.status || 'not_configured';

    return t(`platform.mail.statuses.${status}`, status);
});

const statusHelp = computed(() => {
    const status = transport.value?.status || 'not_configured';

    return t(
        `platform.mail.status_help.${status}`,
        'Save and test the mail transport.',
    );
});

const statusCardClass = computed(() => {
    const status = transport.value?.status || 'not_configured';

    if (transport.value?.is_active) {
        return 'rounded-lg border border-green-200 bg-green-50 p-3 text-green-800';
    }

    if (status === 'failed') {
        return 'rounded-lg border border-red-200 bg-red-50 p-3 text-red-800';
    }

    if (status === 'ready') {
        return 'rounded-lg border border-blue-200 bg-blue-50 p-3 text-blue-800';
    }

    return 'rounded-lg border border-orange-200 bg-orange-50 p-3 text-orange-800';
});

watch(
    () => ({ ...form.data() }),
    () => validateTouchedFields(),
    { deep: true },
);

function saveTransport() {
    if (!validateBeforeSubmit()) {
        return;
    }

    form.post(route('platform.mail-transport.store'), {
        preserveScroll: true,
    });
}

function testTransport() {
    if (!validateBeforeSubmit()) {
        return;
    }

    form.post(route('platform.mail-transport.test'), {
        preserveScroll: true,
    });
}

function activateTransport() {
    activateForm.post(route('platform.mail-transport.activate'), {
        preserveScroll: true,
    });
}

function validateBeforeSubmit() {
    touchedFields.value = Object.fromEntries(
        [...requiredFields.value, ...emailFields].map((field) => [field, true]),
    );
    validateTouchedFields();

    return Object.keys(clientErrors.value).length === 0;
}

function touchField(field) {
    touchedFields.value = { ...touchedFields.value, [field]: true };
    validateTouchedFields();
}

function validateTouchedFields() {
    const errors = {};

    requiredFields.value.forEach((field) => {
        if (!touchedFields.value[field]) {
            return;
        }

        if (String(fieldValue(field) ?? '').trim() === '') {
            errors[field] = t(
                'platform.mail.validation.required',
                'This field is required.',
            );
        }
    });

    if (form.provider === 'smtp' && touchedFields.value.port) {
        const port = Number(form.port);

        if (!Number.isInteger(port) || port < 1 || port > 65535) {
            errors.port = t(
                'platform.mail.validation.port',
                'Enter a valid port between 1 and 65535.',
            );
        }
    }

    emailFields.forEach((field) => {
        if (!touchedFields.value[field] || errors[field]) {
            return;
        }

        const value = String(form[field] ?? '').trim();

        if (value !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
            errors[field] = t(
                'platform.mail.validation.email',
                'Enter a valid email address.',
            );
        }
    });

    clientErrors.value = errors;
}

function clientIssueFields() {
    const fields = [];

    requiredFields.value.forEach((field) => {
        if (String(fieldValue(field) ?? '').trim() === '') {
            fields.push(field);
        }
    });

    const port = Number(form.port);

    if (
        form.provider === 'smtp' &&
        (!Number.isInteger(port) || port < 1 || port > 65535)
    ) {
        fields.push('port');
    }

    emailFields.forEach((field) => {
        const value = String(form[field] ?? '').trim();

        if (value !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
            fields.push(field);
        }
    });

    return [...new Set(fields)];
}

function fieldError(field) {
    return clientErrors.value[field] || form.errors[field] || '';
}

function fieldValue(field) {
    return field.split('.').reduce((value, key) => value?.[key], form);
}

const RequiredLabel = defineComponent({
    props: {
        forId: { type: String, required: true },
        label: { type: String, required: true },
    },
    setup(componentProps) {
        return () =>
            h(
                Label,
                { for: componentProps.forId, class: 'flex items-center gap-1' },
                () => [
                    h(
                        'span',
                        { class: 'text-red-600', 'aria-hidden': 'true' },
                        '*',
                    ),
                    h('span', componentProps.label),
                ],
            );
    },
});

const FieldError = defineComponent({
    props: {
        message: { type: String, default: '' },
    },
    setup(componentProps) {
        return () =>
            componentProps.message
                ? h(
                      'p',
                      { class: 'text-sm text-red-600' },
                      componentProps.message,
                  )
                : null;
    },
});
</script>
