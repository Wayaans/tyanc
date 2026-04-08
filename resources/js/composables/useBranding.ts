import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { BrandProps } from '@/types';

export function useBranding() {
    const page = usePage();

    const brand = computed<BrandProps>(() => page.props.brand);
    const appName = computed<string>(() => brand.value.app_name);
    const appLogo = computed<string | null>(() => brand.value.app_logo);
    const loginCoverImage = computed<string | null>(
        () => brand.value.login_cover_image,
    );

    return { brand, appName, appLogo, loginCoverImage };
}
