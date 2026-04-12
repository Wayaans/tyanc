import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Cumpu\ApprovalRuleController::index
* @see app/Http/Controllers/Cumpu/ApprovalRuleController.php:29
* @route '/cumpu/approval-rules'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/cumpu/approval-rules',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Cumpu\ApprovalRuleController::index
* @see app/Http/Controllers/Cumpu/ApprovalRuleController.php:29
* @route '/cumpu/approval-rules'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Cumpu\ApprovalRuleController::index
* @see app/Http/Controllers/Cumpu/ApprovalRuleController.php:29
* @route '/cumpu/approval-rules'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Cumpu\ApprovalRuleController::index
* @see app/Http/Controllers/Cumpu/ApprovalRuleController.php:29
* @route '/cumpu/approval-rules'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Cumpu\ApprovalRuleController::index
* @see app/Http/Controllers/Cumpu/ApprovalRuleController.php:29
* @route '/cumpu/approval-rules'
*/
const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Cumpu\ApprovalRuleController::index
* @see app/Http/Controllers/Cumpu/ApprovalRuleController.php:29
* @route '/cumpu/approval-rules'
*/
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Cumpu\ApprovalRuleController::index
* @see app/Http/Controllers/Cumpu/ApprovalRuleController.php:29
* @route '/cumpu/approval-rules'
*/
indexForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

index.form = indexForm

/**
* @see \App\Http\Controllers\Cumpu\ApprovalRuleController::store
* @see app/Http/Controllers/Cumpu/ApprovalRuleController.php:53
* @route '/cumpu/approval-rules'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/cumpu/approval-rules',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Cumpu\ApprovalRuleController::store
* @see app/Http/Controllers/Cumpu/ApprovalRuleController.php:53
* @route '/cumpu/approval-rules'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Cumpu\ApprovalRuleController::store
* @see app/Http/Controllers/Cumpu/ApprovalRuleController.php:53
* @route '/cumpu/approval-rules'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Cumpu\ApprovalRuleController::store
* @see app/Http/Controllers/Cumpu/ApprovalRuleController.php:53
* @route '/cumpu/approval-rules'
*/
const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Cumpu\ApprovalRuleController::store
* @see app/Http/Controllers/Cumpu/ApprovalRuleController.php:53
* @route '/cumpu/approval-rules'
*/
storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

store.form = storeForm

/**
* @see \App\Http\Controllers\Cumpu\ApprovalRuleController::update
* @see app/Http/Controllers/Cumpu/ApprovalRuleController.php:68
* @route '/cumpu/approval-rules/{approvalRule}'
*/
export const update = (args: { approvalRule: string | { id: string } } | [approvalRule: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/cumpu/approval-rules/{approvalRule}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Cumpu\ApprovalRuleController::update
* @see app/Http/Controllers/Cumpu/ApprovalRuleController.php:68
* @route '/cumpu/approval-rules/{approvalRule}'
*/
update.url = (args: { approvalRule: string | { id: string } } | [approvalRule: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { approvalRule: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { approvalRule: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            approvalRule: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        approvalRule: typeof args.approvalRule === 'object'
        ? args.approvalRule.id
        : args.approvalRule,
    }

    return update.definition.url
            .replace('{approvalRule}', parsedArgs.approvalRule.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Cumpu\ApprovalRuleController::update
* @see app/Http/Controllers/Cumpu/ApprovalRuleController.php:68
* @route '/cumpu/approval-rules/{approvalRule}'
*/
update.patch = (args: { approvalRule: string | { id: string } } | [approvalRule: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Cumpu\ApprovalRuleController::update
* @see app/Http/Controllers/Cumpu/ApprovalRuleController.php:68
* @route '/cumpu/approval-rules/{approvalRule}'
*/
const updateForm = (args: { approvalRule: string | { id: string } } | [approvalRule: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Cumpu\ApprovalRuleController::update
* @see app/Http/Controllers/Cumpu/ApprovalRuleController.php:68
* @route '/cumpu/approval-rules/{approvalRule}'
*/
updateForm.patch = (args: { approvalRule: string | { id: string } } | [approvalRule: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

update.form = updateForm

/**
* @see \App\Http\Controllers\Cumpu\ApprovalRuleController::destroy
* @see app/Http/Controllers/Cumpu/ApprovalRuleController.php:87
* @route '/cumpu/approval-rules/{approvalRule}'
*/
export const destroy = (args: { approvalRule: string | { id: string } } | [approvalRule: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/cumpu/approval-rules/{approvalRule}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Cumpu\ApprovalRuleController::destroy
* @see app/Http/Controllers/Cumpu/ApprovalRuleController.php:87
* @route '/cumpu/approval-rules/{approvalRule}'
*/
destroy.url = (args: { approvalRule: string | { id: string } } | [approvalRule: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { approvalRule: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { approvalRule: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            approvalRule: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        approvalRule: typeof args.approvalRule === 'object'
        ? args.approvalRule.id
        : args.approvalRule,
    }

    return destroy.definition.url
            .replace('{approvalRule}', parsedArgs.approvalRule.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Cumpu\ApprovalRuleController::destroy
* @see app/Http/Controllers/Cumpu/ApprovalRuleController.php:87
* @route '/cumpu/approval-rules/{approvalRule}'
*/
destroy.delete = (args: { approvalRule: string | { id: string } } | [approvalRule: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Cumpu\ApprovalRuleController::destroy
* @see app/Http/Controllers/Cumpu/ApprovalRuleController.php:87
* @route '/cumpu/approval-rules/{approvalRule}'
*/
const destroyForm = (args: { approvalRule: string | { id: string } } | [approvalRule: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Cumpu\ApprovalRuleController::destroy
* @see app/Http/Controllers/Cumpu/ApprovalRuleController.php:87
* @route '/cumpu/approval-rules/{approvalRule}'
*/
destroyForm.delete = (args: { approvalRule: string | { id: string } } | [approvalRule: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

destroy.form = destroyForm

const approvalRules = {
    index: Object.assign(index, index),
    store: Object.assign(store, store),
    update: Object.assign(update, update),
    destroy: Object.assign(destroy, destroy),
}

export default approvalRules