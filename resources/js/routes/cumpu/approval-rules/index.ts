import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Cumpu\ApprovalRuleController::index
* @see app/Http/Controllers/Cumpu/ApprovalRuleController.php:33
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
* @see app/Http/Controllers/Cumpu/ApprovalRuleController.php:33
* @route '/cumpu/approval-rules'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Cumpu\ApprovalRuleController::index
* @see app/Http/Controllers/Cumpu/ApprovalRuleController.php:33
* @route '/cumpu/approval-rules'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Cumpu\ApprovalRuleController::index
* @see app/Http/Controllers/Cumpu/ApprovalRuleController.php:33
* @route '/cumpu/approval-rules'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Cumpu\ApprovalRuleController::index
* @see app/Http/Controllers/Cumpu/ApprovalRuleController.php:33
* @route '/cumpu/approval-rules'
*/
const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Cumpu\ApprovalRuleController::index
* @see app/Http/Controllers/Cumpu/ApprovalRuleController.php:33
* @route '/cumpu/approval-rules'
*/
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Cumpu\ApprovalRuleController::index
* @see app/Http/Controllers/Cumpu/ApprovalRuleController.php:33
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
* @see \App\Http\Controllers\Cumpu\ApprovalRuleController::sync
* @see app/Http/Controllers/Cumpu/ApprovalRuleController.php:62
* @route '/cumpu/approval-rules/sync'
*/
export const sync = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: sync.url(options),
    method: 'post',
})

sync.definition = {
    methods: ["post"],
    url: '/cumpu/approval-rules/sync',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Cumpu\ApprovalRuleController::sync
* @see app/Http/Controllers/Cumpu/ApprovalRuleController.php:62
* @route '/cumpu/approval-rules/sync'
*/
sync.url = (options?: RouteQueryOptions) => {
    return sync.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Cumpu\ApprovalRuleController::sync
* @see app/Http/Controllers/Cumpu/ApprovalRuleController.php:62
* @route '/cumpu/approval-rules/sync'
*/
sync.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: sync.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Cumpu\ApprovalRuleController::sync
* @see app/Http/Controllers/Cumpu/ApprovalRuleController.php:62
* @route '/cumpu/approval-rules/sync'
*/
const syncForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: sync.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Cumpu\ApprovalRuleController::sync
* @see app/Http/Controllers/Cumpu/ApprovalRuleController.php:62
* @route '/cumpu/approval-rules/sync'
*/
syncForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: sync.url(options),
    method: 'post',
})

sync.form = syncForm

/**
* @see \App\Http\Controllers\Cumpu\ApprovalRuleController::update
* @see app/Http/Controllers/Cumpu/ApprovalRuleController.php:79
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
* @see app/Http/Controllers/Cumpu/ApprovalRuleController.php:79
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
* @see app/Http/Controllers/Cumpu/ApprovalRuleController.php:79
* @route '/cumpu/approval-rules/{approvalRule}'
*/
update.patch = (args: { approvalRule: string | { id: string } } | [approvalRule: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Cumpu\ApprovalRuleController::update
* @see app/Http/Controllers/Cumpu/ApprovalRuleController.php:79
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
* @see app/Http/Controllers/Cumpu/ApprovalRuleController.php:79
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
* @see \App\Http\Controllers\Cumpu\ApprovalRuleController::toggle
* @see app/Http/Controllers/Cumpu/ApprovalRuleController.php:99
* @route '/cumpu/approval-rules/{approvalRule}/toggle'
*/
export const toggle = (args: { approvalRule: string | { id: string } } | [approvalRule: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: toggle.url(args, options),
    method: 'patch',
})

toggle.definition = {
    methods: ["patch"],
    url: '/cumpu/approval-rules/{approvalRule}/toggle',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Cumpu\ApprovalRuleController::toggle
* @see app/Http/Controllers/Cumpu/ApprovalRuleController.php:99
* @route '/cumpu/approval-rules/{approvalRule}/toggle'
*/
toggle.url = (args: { approvalRule: string | { id: string } } | [approvalRule: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return toggle.definition.url
            .replace('{approvalRule}', parsedArgs.approvalRule.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Cumpu\ApprovalRuleController::toggle
* @see app/Http/Controllers/Cumpu/ApprovalRuleController.php:99
* @route '/cumpu/approval-rules/{approvalRule}/toggle'
*/
toggle.patch = (args: { approvalRule: string | { id: string } } | [approvalRule: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: toggle.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Cumpu\ApprovalRuleController::toggle
* @see app/Http/Controllers/Cumpu/ApprovalRuleController.php:99
* @route '/cumpu/approval-rules/{approvalRule}/toggle'
*/
const toggleForm = (args: { approvalRule: string | { id: string } } | [approvalRule: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: toggle.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Cumpu\ApprovalRuleController::toggle
* @see app/Http/Controllers/Cumpu/ApprovalRuleController.php:99
* @route '/cumpu/approval-rules/{approvalRule}/toggle'
*/
toggleForm.patch = (args: { approvalRule: string | { id: string } } | [approvalRule: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: toggle.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

toggle.form = toggleForm

const approvalRules = {
    index: Object.assign(index, index),
    sync: Object.assign(sync, sync),
    update: Object.assign(update, update),
    toggle: Object.assign(toggle, toggle),
}

export default approvalRules