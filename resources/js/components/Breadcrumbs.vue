<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

type Props = {
    breadcrumbs: BreadcrumbItemType[];
};

defineProps<Props>();
</script>

<template>
    <Breadcrumb>
        <BreadcrumbList class="min-w-0 flex-nowrap sm:flex-wrap">
            <template
                v-for="(item, index) in breadcrumbs"
                :key="`${item.title}-${index}`"
            >
                <BreadcrumbItem
                    :class="
                        index < breadcrumbs.length - 1
                            ? 'hidden sm:inline-flex'
                            : 'max-w-full min-w-0'
                    "
                >
                    <template v-if="index === breadcrumbs.length - 1">
                        <BreadcrumbPage
                            class="block max-w-[12rem] truncate sm:max-w-none"
                        >
                            {{ item.title }}
                        </BreadcrumbPage>
                    </template>
                    <template v-else>
                        <BreadcrumbLink
                            as-child
                            class="max-w-[10rem] truncate sm:max-w-none"
                        >
                            <Link :href="item.href">{{ item.title }}</Link>
                        </BreadcrumbLink>
                    </template>
                </BreadcrumbItem>
                <BreadcrumbSeparator
                    v-if="index !== breadcrumbs.length - 1"
                    class="hidden sm:block"
                />
            </template>
        </BreadcrumbList>
    </Breadcrumb>
</template>
