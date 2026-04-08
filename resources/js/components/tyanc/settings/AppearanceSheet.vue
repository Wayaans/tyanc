<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppearanceSettingsController from '@/actions/App/Http/Controllers/Tyanc/Settings/AppearanceSettingsController';
import InputError from '@/components/InputError.vue';
import AppearancePreview from '@/components/tyanc/settings/AppearancePreview.vue';
import SettingsFormFooter from '@/components/tyanc/settings/SettingsFormFooter.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';

type Option = { value: string; label: string };
type FontFamily = { value: string; label: string; stack: string };
type SpacingDensity = { value: string; label: string; density: number };

type Settings = {
    primary_color: string;
    secondary_color: string;
    border_radius: string;
    spacing_density: string;
    spacing_density_value: number;
    font_family: string;
    font_family_stack: string;
    sidebar_variant: string;
};

const props = defineProps<{
    settings: Settings;
    fontFamilies: FontFamily[];
    sidebarVariants: Option[];
    spacingDensities: SpacingDensity[];
}>();

const BORDER_RADIUS_OPTIONS = [
    { value: '0rem', label: 'None' },
    { value: '0.125rem', label: 'XS — 2px' },
    { value: '0.25rem', label: 'SM — 4px' },
    { value: '0.375rem', label: 'MD — 6px' },
    { value: '0.5rem', label: 'LG — 8px' },
    { value: '0.75rem', label: 'XL — 12px' },
    { value: '1rem', label: '2XL — 16px' },
];

/** Local reactive state for the live preview */
const primaryColor = ref(props.settings.primary_color);
const secondaryColor = ref(props.settings.secondary_color);
const borderRadius = ref(props.settings.border_radius);
const spacingDensity = ref(props.settings.spacing_density);
const fontFamily = ref(props.settings.font_family);
const sidebarVariant = ref(props.settings.sidebar_variant);

const borderRadiusOptions = computed(() => {
    const found = BORDER_RADIUS_OPTIONS.find(
        (o) => o.value === borderRadius.value,
    );
    if (found) {
        return BORDER_RADIUS_OPTIONS;
    }
    return [
        { value: borderRadius.value, label: borderRadius.value },
        ...BORDER_RADIUS_OPTIONS,
    ];
});

const previewFontStack = computed(
    () =>
        props.fontFamilies.find((f) => f.value === fontFamily.value)?.stack ??
        props.settings.font_family_stack,
);

const previewFontLabel = computed(
    () =>
        props.fontFamilies.find((f) => f.value === fontFamily.value)?.label ??
        fontFamily.value,
);

const previewSpacingLabel = computed(
    () =>
        props.spacingDensities.find((d) => d.value === spacingDensity.value)
            ?.label ?? spacingDensity.value,
);

const previewSidebarLabel = computed(
    () =>
        props.sidebarVariants.find((v) => v.value === sidebarVariant.value)
            ?.label ?? sidebarVariant.value,
);
</script>

<template>
    <Sheet>
        <SheetTrigger as-child>
            <Button variant="outline" size="sm">Edit appearance</Button>
        </SheetTrigger>

        <SheetContent
            side="right"
            class="flex flex-col overflow-y-auto sm:max-w-md"
        >
            <SheetHeader class="px-6 pt-6">
                <SheetTitle>Edit appearance</SheetTitle>
                <SheetDescription>
                    Changes apply globally. Users can override via personal
                    preferences.
                </SheetDescription>
            </SheetHeader>

            <!-- Live preview -->
            <div class="px-6">
                <AppearancePreview
                    :primary-color="primaryColor"
                    :secondary-color="secondaryColor"
                    :border-radius="borderRadius"
                    :font-family-stack="previewFontStack"
                    :font-family-label="previewFontLabel"
                    :spacing-density-label="previewSpacingLabel"
                    :sidebar-variant-label="previewSidebarLabel"
                />
            </div>

            <Form
                v-bind="AppearanceSettingsController.update.form()"
                :options="{ preserveScroll: true }"
                class="flex-1 space-y-5 overflow-y-auto px-6 pb-6"
                v-slot="{ errors, processing, recentlySuccessful }"
            >
                <!-- Colors -->
                <fieldset class="space-y-3">
                    <legend class="text-sm font-medium">Colors</legend>
                    <div class="grid grid-cols-1 gap-4">
                        <div class="grid gap-2">
                            <Label for="primary_color">Primary</Label>
                            <div class="flex items-center gap-2">
                                <span
                                    class="inline-flex size-9 shrink-0 rounded-md border"
                                    :style="{ background: primaryColor }"
                                />
                                <Input
                                    id="primary_color"
                                    v-model="primaryColor"
                                    type="text"
                                    name="primary_color"
                                    placeholder="oklch(0.5 0.17 200) or #0f766e"
                                    class="font-mono"
                                />
                            </div>
                            <InputError :message="errors.primary_color" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="secondary_color">Secondary</Label>
                            <div class="flex items-center gap-2">
                                <span
                                    class="inline-flex size-9 shrink-0 rounded-md border"
                                    :style="{ background: secondaryColor }"
                                />
                                <Input
                                    id="secondary_color"
                                    v-model="secondaryColor"
                                    type="text"
                                    name="secondary_color"
                                    placeholder="oklch(0.96 0 0) or #f5f5f5"
                                    class="font-mono"
                                />
                            </div>
                            <InputError :message="errors.secondary_color" />
                        </div>
                    </div>
                </fieldset>

                <!-- Border radius -->
                <div class="grid gap-2">
                    <Label for="border_radius">Border radius</Label>
                    <Select v-model="borderRadius">
                        <SelectTrigger id="border_radius" class="w-full">
                            <SelectValue placeholder="Select radius" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="opt in borderRadiusOptions"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ opt.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <input
                        type="hidden"
                        name="border_radius"
                        :value="borderRadius"
                    />
                    <InputError :message="errors.border_radius" />
                </div>

                <!-- Font family -->
                <div class="grid gap-2">
                    <Label for="font_family">Font family</Label>
                    <Select v-model="fontFamily">
                        <SelectTrigger id="font_family" class="w-full">
                            <SelectValue placeholder="Select font" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="font in props.fontFamilies"
                                :key="font.value"
                                :value="font.value"
                            >
                                {{ font.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <input
                        type="hidden"
                        name="font_family"
                        :value="fontFamily"
                    />
                    <InputError :message="errors.font_family" />
                </div>

                <!-- Spacing density -->
                <div class="grid gap-2">
                    <Label for="spacing_density">Spacing density</Label>
                    <Select v-model="spacingDensity">
                        <SelectTrigger id="spacing_density" class="w-full">
                            <SelectValue placeholder="Select density" />
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
                        type="hidden"
                        name="spacing_density"
                        :value="spacingDensity"
                    />
                    <InputError :message="errors.spacing_density" />
                </div>

                <!-- Sidebar variant -->
                <div class="grid gap-2">
                    <Label for="sidebar_variant">Sidebar style</Label>
                    <Select v-model="sidebarVariant">
                        <SelectTrigger id="sidebar_variant" class="w-full">
                            <SelectValue placeholder="Select style" />
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
                        type="hidden"
                        name="sidebar_variant"
                        :value="sidebarVariant"
                    />
                    <InputError :message="errors.sidebar_variant" />
                </div>

                <SettingsFormFooter
                    :processing="processing"
                    :recently-successful="recentlySuccessful"
                />
            </Form>
        </SheetContent>
    </Sheet>
</template>
