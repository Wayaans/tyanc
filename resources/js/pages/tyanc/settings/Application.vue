<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppSettingsController from '@/actions/App/Http/Controllers/Tyanc/Settings/AppSettingsController';
import FormFieldSupport from '@/components/FormFieldSupport.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import ImageUploadField from '@/components/tyanc/settings/ImageUploadField.vue';
import SettingsFormFooter from '@/components/tyanc/settings/SettingsFormFooter.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { useAppNavigation } from '@/composables/useAppNavigation';
import AppLayout from '@/layouts/AppLayout.vue';
import TyancSettingsLayout from '@/layouts/tyanc/settings/Layout.vue';
import { useTranslations } from '@/lib/translations';
import { edit } from '@/routes/tyanc/settings/application';

type Settings = {
    app_name: string;
    company_legal_name: string | null;
    app_logo: string | null;
    app_logo_uuid: string | null;
    favicon: string | null;
    favicon_uuid: string | null;
    login_cover_image: string | null;
    login_cover_image_uuid: string | null;
};

type Props = {
    settings: Settings;
};

const props = defineProps<Props>();

const { tyancSettingsBreadcrumbs } = useAppNavigation();
const breadcrumbItems = computed(() =>
    tyancSettingsBreadcrumbs('Application', edit()),
);
const { __ } = useTranslations();
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="__('Application settings')" />

        <h1 class="sr-only">{{ __('Application settings') }}</h1>

        <TyancSettingsLayout>
            <Form
                v-bind="AppSettingsController.update.form()"
                :options="{ preserveScroll: true }"
                class="space-y-6"
                v-slot="{ errors, processing, recentlySuccessful }"
            >
                <!-- Identity -->
                <div class="space-y-4">
                    <Heading
                        variant="small"
                        :title="__('Identity')"
                        :description="
                            __('Application name and legal information')
                        "
                    />

                    <div class="grid gap-4">
                        <div class="grid gap-2">
                            <Label for="app_name">{{ __('App name') }}</Label>
                            <Input
                                id="app_name"
                                type="text"
                                name="app_name"
                                :default-value="props.settings.app_name"
                                placeholder="My Application"
                                required
                                autocomplete="off"
                            />
                            <InputError :message="errors.app_name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="company_legal_name">
                                Legal name
                                <span
                                    class="ml-1 text-xs text-muted-foreground"
                                >
                                    ({{ __('optional') }})
                                </span>
                            </Label>
                            <Input
                                id="company_legal_name"
                                type="text"
                                name="company_legal_name"
                                :default-value="
                                    props.settings.company_legal_name ??
                                    undefined
                                "
                                placeholder="Acme Corp Pty Ltd"
                                autocomplete="off"
                            />
                            <FormFieldSupport
                                hint="Displayed in footers and legal notices."
                                :error="errors.company_legal_name"
                            />
                        </div>
                    </div>
                </div>

                <Separator />

                <!-- Assets -->
                <div class="space-y-4">
                    <Heading
                        variant="small"
                        :title="__('Assets')"
                        :description="
                            __('Logos and images used across the application')
                        "
                    />

                    <div class="grid gap-6 sm:grid-cols-3">
                        <ImageUploadField
                            name="app_logo"
                            remove-name="remove_app_logo"
                            label="App logo"
                            :current-url="props.settings.app_logo"
                            :current-uuid="props.settings.app_logo_uuid"
                            :error="errors.app_logo"
                        />

                        <ImageUploadField
                            name="favicon"
                            remove-name="remove_favicon"
                            label="Favicon"
                            :current-url="props.settings.favicon"
                            :current-uuid="props.settings.favicon_uuid"
                            :error="errors.favicon"
                            hint="ICO, PNG · 32×32 recommended"
                        />

                        <ImageUploadField
                            name="login_cover_image"
                            remove-name="remove_login_cover_image"
                            label="Login cover"
                            :current-url="props.settings.login_cover_image"
                            :current-uuid="
                                props.settings.login_cover_image_uuid
                            "
                            :error="errors.login_cover_image"
                            hint="PNG, JPG · 1200×800 recommended"
                        />
                    </div>
                </div>

                <Separator />

                <SettingsFormFooter
                    :processing="processing"
                    :recently-successful="recentlySuccessful"
                />
            </Form>
        </TyancSettingsLayout>
    </AppLayout>
</template>
