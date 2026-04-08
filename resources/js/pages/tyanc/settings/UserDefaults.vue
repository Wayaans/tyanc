<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import UserDefaultsSettingsController from '@/actions/App/Http/Controllers/Tyanc/Settings/UserDefaultsSettingsController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import SettingsFormFooter from '@/components/tyanc/settings/SettingsFormFooter.vue';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { useAppNavigation } from '@/composables/useAppNavigation';
import AppLayout from '@/layouts/AppLayout.vue';
import TyancSettingsLayout from '@/layouts/tyanc/settings/Layout.vue';
import { edit } from '@/routes/tyanc/settings/user-defaults';

type Option = { value: string; label: string };

type Settings = {
    locale: string;
    timezone: string;
    appearance: string;
};

type Props = {
    settings: Settings;
    appearances: Option[];
    locales: string[];
    timezones: string[];
};

const props = defineProps<Props>();

const { tyancSettingsBreadcrumbs } = useAppNavigation();
const breadcrumbItems = computed(() =>
    tyancSettingsBreadcrumbs('Defaults for New Users', edit()),
);

const selectedLocale = ref(props.settings.locale);
const selectedTimezone = ref(props.settings.timezone);
const selectedAppearance = ref(props.settings.appearance);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Defaults for New Users" />

        <h1 class="sr-only">Defaults for New Users</h1>

        <TyancSettingsLayout>
            <Form
                v-bind="UserDefaultsSettingsController.update.form()"
                :options="{ preserveScroll: true }"
                class="space-y-6"
                v-slot="{ errors, processing, recentlySuccessful }"
            >
                <!-- Locale & time -->
                <div class="space-y-4">
                    <Heading
                        variant="small"
                        title="Locale & time"
                        description="Starting values applied when new user accounts are created"
                    />

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="locale">Default language</Label>
                            <Select v-model="selectedLocale">
                                <SelectTrigger id="locale" class="w-full">
                                    <SelectValue
                                        placeholder="Select language"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="locale in props.locales"
                                        :key="locale"
                                        :value="locale"
                                    >
                                        {{ locale.toUpperCase() }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <input
                                type="hidden"
                                name="locale"
                                :value="selectedLocale"
                            />
                            <InputError :message="errors.locale" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="timezone">Default timezone</Label>
                            <Select v-model="selectedTimezone">
                                <SelectTrigger id="timezone" class="w-full">
                                    <SelectValue
                                        placeholder="Select timezone"
                                    />
                                </SelectTrigger>
                                <SelectContent class="max-h-60">
                                    <SelectItem
                                        v-for="tz in props.timezones"
                                        :key="tz"
                                        :value="tz"
                                    >
                                        {{ tz }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <input
                                type="hidden"
                                name="timezone"
                                :value="selectedTimezone"
                            />
                            <InputError :message="errors.timezone" />
                        </div>
                    </div>
                </div>

                <Separator />

                <!-- Appearance -->
                <div class="space-y-4">
                    <Heading
                        variant="small"
                        title="Appearance"
                        description="Starting theme preference for newly created user accounts"
                    />

                    <div class="grid max-w-xs gap-2">
                        <Label for="appearance">Default theme</Label>
                        <Select v-model="selectedAppearance">
                            <SelectTrigger id="appearance" class="w-full">
                                <SelectValue placeholder="Select theme" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="opt in props.appearances"
                                    :key="opt.value"
                                    :value="opt.value"
                                >
                                    {{ opt.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <input
                            type="hidden"
                            name="appearance"
                            :value="selectedAppearance"
                        />
                        <InputError :message="errors.appearance" />
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
