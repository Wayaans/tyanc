import type { RouteDefinition, RouteFormDefinition } from '@/wayfinder';

type GetRouteHelper = (() => RouteDefinition<'get'>) & {
    url: () => string;
    get: () => RouteDefinition<'get'>;
    head: () => RouteDefinition<'head'>;
};

type PostRouteHelper = (() => RouteDefinition<'post'>) & {
    url: () => string;
    post: () => RouteDefinition<'post'>;
    form: () => RouteFormDefinition<'post'>;
};

type DeleteRouteHelper = (() => RouteDefinition<'delete'>) & {
    url: () => string;
    delete: () => RouteDefinition<'delete'>;
    form: (() => RouteFormDefinition<'post'>) & {
        delete: () => RouteFormDefinition<'post'>;
    };
};

const createGetRoute = (url: string): GetRouteHelper => {
    const route = (() => ({ url, method: 'get' as const })) as GetRouteHelper;

    route.url = () => url;
    route.get = () => ({ url, method: 'get' as const });
    route.head = () => ({ url, method: 'head' as const });

    return route;
};

const createPostRoute = (url: string): PostRouteHelper => {
    const route = (() => ({ url, method: 'post' as const })) as PostRouteHelper;

    route.url = () => url;
    route.post = () => ({ url, method: 'post' as const });
    route.form = () => ({ action: url, method: 'post' as const });

    return route;
};

const createDeleteRoute = (url: string): DeleteRouteHelper => {
    const route = (() => ({
        url,
        method: 'delete' as const,
    })) as DeleteRouteHelper;

    route.url = () => url;
    route.delete = () => ({ url, method: 'delete' as const });

    const form = (() => ({
        action: url,
        method: 'post' as const,
    })) as DeleteRouteHelper['form'];
    form.delete = () => ({
        action: `${url}?_method=DELETE`,
        method: 'post' as const,
    });

    route.form = form;

    return route;
};

export const enable = createPostRoute('/user/two-factor-authentication');
export const disable = createDeleteRoute('/user/two-factor-authentication');
export const confirm = createPostRoute(
    '/user/confirmed-two-factor-authentication',
);
export const qrCode = createGetRoute('/user/two-factor-qr-code');
export const recoveryCodes = createGetRoute('/user/two-factor-recovery-codes');
export const regenerateRecoveryCodes = createPostRoute(
    '/user/two-factor-recovery-codes',
);
export const secretKey = createGetRoute('/user/two-factor-secret-key');
