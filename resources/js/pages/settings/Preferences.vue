<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import UserPreferencesController from '@/actions/App/Http/Controllers/UserPreferencesController';
import FormFieldSupport from '@/components/FormFieldSupport.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
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
import { edit } from '@/routes/settings/preferences';

type Option = { value: string; label: string };
type SpacingDensity = { value: string; label: string; density: number };

type Preferences = {
    locale: string | null;
    timezone: string | null;
    appearance: string | null;
    sidebar_variant: string | null;
    spacing_density: string | null;
    resolved_locale: string;
    resolved_timezone: string;
    resolved_appearance: string;
    resolved_sidebar_variant: string;
    resolved_spacing_density: string;
    resolved_spacing_density_value: number;
};

type Props = {
    preferences: Preferences;
    appearances: Option[];
    locales: string[];
    timezones: string[];
    sidebarVariants: Option[];
    spacingDensities: SpacingDensity[];
};

const props = defineProps<Props>();

const { settingsBreadcrumbs } = useAppNavigation();
const breadcrumbItems = computed(() =>
    settingsBreadcrumbs('Preferences', edit()),
);

/**
 * Empty string sentinel = "use system default" (field not sent to backend → null).
 */
const SYSTEM_DEFAULT = '';

const selectedLocale = ref<string>(props.preferences.locale ?? SYSTEM_DEFAULT);
const selectedTimezone = ref<string>(
    props.preferences.timezone ?? SYSTEM_DEFAULT,
);
const selectedAppearance = ref<string>(
    props.preferences.appearance ?? SYSTEM_DEFAULT,
);
const selectedSidebarVariant = ref<string>(
    props.preferences.sidebar_variant ?? SYSTEM_DEFAULT,
);
const selectedSpacingDensity = ref<string>(
    props.preferences.spacing_density ?? SYSTEM_DEFAULT,
);

watch(
    () => props.preferences,
    (preferences) => {
        selectedLocale.value = preferences.locale ?? SYSTEM_DEFAULT;
        selectedTimezone.value = preferences.timezone ?? SYSTEM_DEFAULT;
        selectedAppearance.value = preferences.appearance ?? SYSTEM_DEFAULT;
        selectedSidebarVariant.value =
            preferences.sidebar_variant ?? SYSTEM_DEFAULT;
        selectedSpacingDensity.value =
            preferences.spacing_density ?? SYSTEM_DEFAULT;
    },
);

function clearField(
    field:
        | 'locale'
        | 'timezone'
        | 'appearance'
        | 'sidebar_variant'
        | 'spacing_density',
) {
    switch (field) {
        case 'locale':
            selectedLocale.value = SYSTEM_DEFAULT;
            break;
        case 'timezone':
            selectedTimezone.value = SYSTEM_DEFAULT;
            break;
        case 'appearance':
            selectedAppearance.value = SYSTEM_DEFAULT;
            break;
        case 'sidebar_variant':
            selectedSidebarVariant.value = SYSTEM_DEFAULT;
            break;
        case 'spacing_density':
            selectedSpacingDensity.value = SYSTEM_DEFAULT;
            break;
    }
}

const resolvedLocaleLabel = computed(() =>
    props.preferences.resolved_locale.toUpperCase(),
);
const resolvedTimezoneLabel = computed(
    () => props.preferences.resolved_timezone,
);
const resolvedAppearanceLabel = computed(
    () =>
        props.appearances.find(
            (a) => a.value === props.preferences.resolved_appearance,
        )?.label ?? props.preferences.resolved_appearance,
);
const resolvedSidebarLabel = computed(
    () =>
        props.sidebarVariants.find(
            (v) => v.value === props.preferences.resolved_sidebar_variant,
        )?.label ?? props.preferences.resolved_sidebar_variant,
);
const resolvedSpacingLabel = computed(
    () =>
        props.spacingDensities.find(
            (d) => d.value === props.preferences.resolved_spacing_density,
        )?.label ?? props.preferences.resolved_spacing_density,
);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Preferences" />

        <h1 class="sr-only">Preferences</h1>

        <div class="px-4 py-6">
            <div class="mb-6">
                <Heading
                    title="Preferences"
                    description="Personalise your experience — these settings override application defaults"
                />
            </div>

            <div class="max-w-2xl">
                <Form
                    v-bind="UserPreferencesController.update.form()"
                    :options="{ preserveScroll: true }"
                    class="space-y-6"
                    v-slot="{ errors, processing, recentlySuccessful }"
                >
                    <!-- Locale & time -->
                    <div class="space-y-4">
                        <Heading
                            variant="small"
                            title="Locale & time"
                            description="Your personal language and timezone — overrides system defaults"
                        />

                        <div class="grid gap-4 sm:grid-cols-2">
                            <!-- Locale -->
                            <div class="grid gap-2">
                                <div class="flex items-center justify-between">
                                    <Label for="locale">Language</Label>
                                    <button
                                        v-if="selectedLocale !== SYSTEM_DEFAULT"
                                        type="button"
                                        class="text-xs text-muted-foreground underline-offset-2 hover:underline"
                                        @click="clearField('locale')"
                                    >
                                        Use system default
                                    </button>
                                </div>
                                <Select v-model="selectedLocale">
                                    <SelectTrigger id="locale" class="w-full">
                                        <SelectValue
                                            :placeholder="`System default (${resolvedLocaleLabel})`"
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
                                <!-- Only sent when explicitly set -->
                                <input
                                    v-if="selectedLocale !== SYSTEM_DEFAULT"
                                    type="hidden"
                                    name="locale"
                                    :value="selectedLocale"
                                />
                                <FormFieldSupport
                                    :hint="
                                        selectedLocale === SYSTEM_DEFAULT
                                            ? `Using system default: ${resolvedLocaleLabel}`
                                            : undefined
                                    "
                                    :error="errors.locale"
                                />
                            </div>

                            <!-- Timezone -->
                            <div class="grid gap-2">
                                <div class="flex items-center justify-between">
                                    <Label for="timezone">Timezone</Label>
                                    <button
                                        v-if="
                                            selectedTimezone !== SYSTEM_DEFAULT
                                        "
                                        type="button"
                                        class="text-xs text-muted-foreground underline-offset-2 hover:underline"
                                        @click="clearField('timezone')"
                                    >
                                        Use system default
                                    </button>
                                </div>
                                <Select v-model="selectedTimezone">
                                    <SelectTrigger id="timezone" class="w-full">
                                        <SelectValue
                                            :placeholder="`System default (${resolvedTimezoneLabel})`"
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
                                    v-if="selectedTimezone !== SYSTEM_DEFAULT"
                                    type="hidden"
                                    name="timezone"
                                    :value="selectedTimezone"
                                />
                                <FormFieldSupport
                                    :hint="
                                        selectedTimezone === SYSTEM_DEFAULT
                                            ? `Using system default: ${resolvedTimezoneLabel}`
                                            : undefined
                                    "
                                    :error="errors.timezone"
                                />
                            </div>
                        </div>
                    </div>

                    <Separator />

                    <!-- Display preferences -->
                    <div class="space-y-4">
                        <Heading
                            variant="small"
                            title="Display"
                            description="Theme, sidebar style, and spacing — overrides system defaults"
                        />

                        <div class="grid gap-4 sm:grid-cols-2">
                            <!-- Appearance -->
                            <div class="grid gap-2">
                                <div class="flex items-center justify-between">
                                    <Label for="appearance">Theme</Label>
                                    <button
                                        v-if="
                                            selectedAppearance !==
                                            SYSTEM_DEFAULT
                                        "
                                        type="button"
                                        class="text-xs text-muted-foreground underline-offset-2 hover:underline"
                                        @click="clearField('appearance')"
                                    >
                                        Use system default
                                    </button>
                                </div>
                                <Select v-model="selectedAppearance">
                                    <SelectTrigger
                                        id="appearance"
                                        class="w-full"
                                    >
                                        <SelectValue
                                            :placeholder="`System default (${resolvedAppearanceLabel})`"
                                        />
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
                                    v-if="selectedAppearance !== SYSTEM_DEFAULT"
                                    type="hidden"
                                    name="appearance"
                                    :value="selectedAppearance"
                                />
                                <FormFieldSupport
                                    :hint="
                                        selectedAppearance === SYSTEM_DEFAULT
                                            ? `Using system default: ${resolvedAppearanceLabel}`
                                            : undefined
                                    "
                                    :error="errors.appearance"
                                />
                            </div>

                            <!-- Sidebar variant -->
                            <div class="grid gap-2">
                                <div class="flex items-center justify-between">
                                    <Label for="sidebar_variant"
                                        >Sidebar style</Label
                                    >
                                    <button
                                        v-if="
                                            selectedSidebarVariant !==
                                            SYSTEM_DEFAULT
                                        "
                                        type="button"
                                        class="text-xs text-muted-foreground underline-offset-2 hover:underline"
                                        @click="clearField('sidebar_variant')"
                                    >
                                        Use system default
                                    </button>
                                </div>
                                <Select v-model="selectedSidebarVariant">
                                    <SelectTrigger
                                        id="sidebar_variant"
                                        class="w-full"
                                    >
                                        <SelectValue
                                            :placeholder="`System default (${resolvedSidebarLabel})`"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="variant in props.sidebarVariants"
                                            :key="variant.value"
                                            :value="variant.value"
                                        >
                                            {{ variant.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <input
                                    v-if="
                                        selectedSidebarVariant !==
                                        SYSTEM_DEFAULT
                                    "
                                    type="hidden"
                                    name="sidebar_variant"
                                    :value="selectedSidebarVariant"
                                />
                                <FormFieldSupport
                                    :hint="
                                        selectedSidebarVariant ===
                                        SYSTEM_DEFAULT
                                            ? `Using system default: ${resolvedSidebarLabel}`
                                            : undefined
                                    "
                                    :error="errors.sidebar_variant"
                                />
                            </div>

                            <!-- Spacing density -->
                            <div class="grid gap-2">
                                <div class="flex items-center justify-between">
                                    <Label for="spacing_density"
                                        >Spacing density</Label
                                    >
                                    <button
                                        v-if="
                                            selectedSpacingDensity !==
                                            SYSTEM_DEFAULT
                                        "
                                        type="button"
                                        class="text-xs text-muted-foreground underline-offset-2 hover:underline"
                                        @click="clearField('spacing_density')"
                                    >
                                        Use system default
                                    </button>
                                </div>
                                <Select v-model="selectedSpacingDensity">
                                    <SelectTrigger
                                        id="spacing_density"
                                        class="w-full"
                                    >
                                        <SelectValue
                                            :placeholder="`System default (${resolvedSpacingLabel})`"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="density in props.spacingDensities"
                                            :key="density.value"
                                            :value="density.value"
                                        >
                                            {{ density.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <input
                                    v-if="
                                        selectedSpacingDensity !==
                                        SYSTEM_DEFAULT
                                    "
                                    type="hidden"
                                    name="spacing_density"
                                    :value="selectedSpacingDensity"
                                />
                                <FormFieldSupport
                                    :hint="
                                        selectedSpacingDensity ===
                                        SYSTEM_DEFAULT
                                            ? `Using system default: ${resolvedSpacingLabel} (×${props.preferences.resolved_spacing_density_value})`
                                            : undefined
                                    "
                                    :error="errors.spacing_density"
                                />
                            </div>
                        </div>
                    </div>

                    <Separator />

                    <div class="flex items-center gap-4">
                        <Button type="submit" :disabled="processing">
                            {{ processing ? 'Saving…' : 'Save preferences' }}
                        </Button>

                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p
                                v-show="recentlySuccessful"
                                class="text-sm text-neutral-600"
                            >
                                Saved.
                            </p>
                        </Transition>
                    </div>
                </Form>
            </div>
        </div>
    </AppLayout>
</template>
