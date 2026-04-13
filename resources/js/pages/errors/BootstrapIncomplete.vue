<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader } from '@/components/ui/card';

const props = defineProps<{
    message: string;
    missing: string[];
    warnings?: string[];
    command: string;
    superAdminCommand?: string | null;
}>();

const copied = ref<string | null>(null);

function copyToClipboard(text: string): void {
    navigator.clipboard.writeText(text).then(() => {
        copied.value = text;
        setTimeout(() => (copied.value = null), 2000);
    });
}
</script>

<template>
    <Head title="Service Unavailable" />

    <div
        class="flex min-h-svh flex-col items-center justify-center bg-background p-6 md:p-10"
    >
        <div class="w-full max-w-lg space-y-6">
            <div class="flex flex-col items-center gap-3">
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-xl border border-border bg-sidebar"
                >
                    <AppLogoIcon class="size-6 fill-current text-foreground" />
                </div>
                <Badge variant="destructive" class="font-mono text-xs">
                    503
                </Badge>
            </div>

            <Card>
                <CardHeader class="pb-0">
                    <h1 class="text-lg font-semibold tracking-tight">
                        Bootstrap Incomplete
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{ props.message }}
                    </p>
                </CardHeader>

                <CardContent class="space-y-5 pt-4">
                    <div class="space-y-2">
                        <p
                            class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                        >
                            Missing
                        </p>
                        <ul class="space-y-1.5">
                            <li
                                v-for="item in props.missing"
                                :key="item"
                                class="flex items-center gap-2 text-sm text-destructive"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="size-3.5 shrink-0"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                    aria-hidden="true"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                                <span>{{ item }}</span>
                            </li>
                        </ul>
                    </div>

                    <Alert
                        v-if="props.warnings?.length"
                        variant="default"
                        class="border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-800/40 dark:bg-amber-950/20 dark:text-amber-400 [&>svg]:text-amber-500"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="size-4"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            aria-hidden="true"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z"
                                clip-rule="evenodd"
                            />
                        </svg>
                        <AlertTitle>Warnings</AlertTitle>
                        <AlertDescription>
                            <ul class="space-y-0.5">
                                <li
                                    v-for="warning in props.warnings"
                                    :key="warning"
                                >
                                    {{ warning }}
                                </li>
                            </ul>
                        </AlertDescription>
                    </Alert>

                    <div class="space-y-3">
                        <p
                            class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                        >
                            Run to fix
                        </p>

                        <div class="space-y-1.5">
                            <p class="text-xs text-muted-foreground">
                                Bootstrap the application:
                            </p>
                            <div class="group relative">
                                <pre
                                    class="overflow-x-auto rounded-md border bg-muted px-4 py-3 pr-20 font-mono text-sm text-foreground"
                                    >{{ props.command }}</pre
                                >
                                <button
                                    type="button"
                                    class="absolute top-1/2 right-2 -translate-y-1/2 rounded px-2 py-1 text-xs text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                                    @click="copyToClipboard(props.command)"
                                >
                                    {{
                                        copied === props.command
                                            ? 'Copied!'
                                            : 'Copy'
                                    }}
                                </button>
                            </div>
                        </div>

                        <div v-if="props.superAdminCommand" class="space-y-1.5">
                            <p class="text-xs text-muted-foreground">
                                Create a super admin user:
                            </p>
                            <div class="group relative">
                                <pre
                                    class="overflow-x-auto rounded-md border bg-muted px-4 py-3 pr-20 font-mono text-sm text-foreground"
                                    >{{ props.superAdminCommand }}</pre
                                >
                                <button
                                    type="button"
                                    class="absolute top-1/2 right-2 -translate-y-1/2 rounded px-2 py-1 text-xs text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                                    @click="
                                        copyToClipboard(
                                            props.superAdminCommand!,
                                        )
                                    "
                                >
                                    {{
                                        copied === props.superAdminCommand
                                            ? 'Copied!'
                                            : 'Copy'
                                    }}
                                </button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <p class="text-center text-xs text-muted-foreground">
                This page is only visible to server operators. Reload after
                running the commands.
            </p>
        </div>
    </div>
</template>
