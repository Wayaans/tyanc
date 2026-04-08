<script setup lang="ts">
defineProps<{
    primaryColor: string;
    secondaryColor: string;
    borderRadius: string;
    fontFamilyStack: string;
    fontFamilyLabel: string;
    spacingDensityLabel: string;
    sidebarVariantLabel: string;
}>();
</script>

<template>
    <div
        class="overflow-hidden rounded-lg border bg-background shadow-sm"
        :style="{
            fontFamily: fontFamilyStack || undefined,
            '--preview-primary': primaryColor,
            '--preview-secondary': secondaryColor,
            '--preview-radius': borderRadius || '0.375rem',
        }"
    >
        <!-- Mini sidebar + content chrome -->
        <div class="flex h-28 text-[10px]">
            <!-- Sidebar strip -->
            <div class="flex w-12 flex-col gap-1 border-r bg-muted/40 p-1.5">
                <div
                    class="h-2 w-full rounded-[var(--preview-radius)] bg-muted"
                />
                <div
                    class="h-1.5 w-5/6 rounded-[var(--preview-radius)] opacity-80"
                    :style="{ background: primaryColor }"
                />
                <div
                    class="h-1.5 w-4/6 rounded-[var(--preview-radius)] bg-muted"
                />
                <div
                    class="h-1.5 w-3/4 rounded-[var(--preview-radius)] bg-muted"
                />
                <div class="mt-auto">
                    <div
                        class="h-2 w-full rounded-[var(--preview-radius)] bg-muted"
                    />
                </div>
            </div>

            <!-- Content area -->
            <div class="flex-1 space-y-1.5 p-2">
                <div
                    class="h-2 w-1/3 rounded-[var(--preview-radius)] bg-foreground/20"
                />
                <div
                    class="h-1.5 w-2/3 rounded-[var(--preview-radius)] bg-muted"
                />
                <div
                    class="h-1.5 w-1/2 rounded-[var(--preview-radius)] bg-muted"
                />
                <!-- Mini button -->
                <div
                    class="mt-1 h-4 w-14 rounded-[var(--preview-radius)]"
                    :style="{ background: primaryColor }"
                />
            </div>
        </div>

        <!-- Info bar -->
        <div
            class="flex flex-wrap gap-x-3 gap-y-0.5 border-t bg-muted/20 px-2 py-1.5 text-[9px] text-muted-foreground"
        >
            <span class="flex items-center gap-1">
                <span
                    class="inline-block size-2 rounded-[2px] border"
                    :style="{ background: primaryColor }"
                />
                Primary
            </span>
            <span class="flex items-center gap-1">
                <span
                    class="inline-block size-2 rounded-[2px] border"
                    :style="{ background: secondaryColor }"
                />
                Secondary
            </span>
            <span>{{ fontFamilyLabel }}</span>
            <span>{{ spacingDensityLabel }}</span>
            <span>{{ sidebarVariantLabel }}</span>
        </div>
    </div>
</template>
