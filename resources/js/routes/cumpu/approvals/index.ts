import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
import reports from './reports'
/**
* @see \App\Http\Controllers\Cumpu\ApprovalController::index
* @see app/Http/Controllers/Cumpu/ApprovalController.php:35
* @route '/cumpu/approvals'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/cumpu/approvals',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Cumpu\ApprovalController::index
* @see app/Http/Controllers/Cumpu/ApprovalController.php:35
* @route '/cumpu/approvals'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Cumpu\ApprovalController::index
* @see app/Http/Controllers/Cumpu/ApprovalController.php:35
* @route '/cumpu/approvals'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Cumpu\ApprovalController::index
* @see app/Http/Controllers/Cumpu/ApprovalController.php:35
* @route '/cumpu/approvals'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Cumpu\ApprovalController::index
* @see app/Http/Controllers/Cumpu/ApprovalController.php:35
* @route '/cumpu/approvals'
*/
const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Cumpu\ApprovalController::index
* @see app/Http/Controllers/Cumpu/ApprovalController.php:35
* @route '/cumpu/approvals'
*/
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Cumpu\ApprovalController::index
* @see app/Http/Controllers/Cumpu/ApprovalController.php:35
* @route '/cumpu/approvals'
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
* @see \App\Http\Controllers\Cumpu\ApprovalController::myRequests
* @see app/Http/Controllers/Cumpu/ApprovalController.php:48
* @route '/cumpu/approvals/my-requests'
*/
export const myRequests = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: myRequests.url(options),
    method: 'get',
})

myRequests.definition = {
    methods: ["get","head"],
    url: '/cumpu/approvals/my-requests',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Cumpu\ApprovalController::myRequests
* @see app/Http/Controllers/Cumpu/ApprovalController.php:48
* @route '/cumpu/approvals/my-requests'
*/
myRequests.url = (options?: RouteQueryOptions) => {
    return myRequests.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Cumpu\ApprovalController::myRequests
* @see app/Http/Controllers/Cumpu/ApprovalController.php:48
* @route '/cumpu/approvals/my-requests'
*/
myRequests.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: myRequests.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Cumpu\ApprovalController::myRequests
* @see app/Http/Controllers/Cumpu/ApprovalController.php:48
* @route '/cumpu/approvals/my-requests'
*/
myRequests.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: myRequests.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Cumpu\ApprovalController::myRequests
* @see app/Http/Controllers/Cumpu/ApprovalController.php:48
* @route '/cumpu/approvals/my-requests'
*/
const myRequestsForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: myRequests.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Cumpu\ApprovalController::myRequests
* @see app/Http/Controllers/Cumpu/ApprovalController.php:48
* @route '/cumpu/approvals/my-requests'
*/
myRequestsForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: myRequests.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Cumpu\ApprovalController::myRequests
* @see app/Http/Controllers/Cumpu/ApprovalController.php:48
* @route '/cumpu/approvals/my-requests'
*/
myRequestsForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: myRequests.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

myRequests.form = myRequestsForm

/**
* @see \App\Http\Controllers\Cumpu\ApprovalOverviewController::all
* @see app/Http/Controllers/Cumpu/ApprovalOverviewController.php:17
* @route '/cumpu/approvals/all'
*/
export const all = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: all.url(options),
    method: 'get',
})

all.definition = {
    methods: ["get","head"],
    url: '/cumpu/approvals/all',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Cumpu\ApprovalOverviewController::all
* @see app/Http/Controllers/Cumpu/ApprovalOverviewController.php:17
* @route '/cumpu/approvals/all'
*/
all.url = (options?: RouteQueryOptions) => {
    return all.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Cumpu\ApprovalOverviewController::all
* @see app/Http/Controllers/Cumpu/ApprovalOverviewController.php:17
* @route '/cumpu/approvals/all'
*/
all.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: all.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Cumpu\ApprovalOverviewController::all
* @see app/Http/Controllers/Cumpu/ApprovalOverviewController.php:17
* @route '/cumpu/approvals/all'
*/
all.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: all.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Cumpu\ApprovalOverviewController::all
* @see app/Http/Controllers/Cumpu/ApprovalOverviewController.php:17
* @route '/cumpu/approvals/all'
*/
const allForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: all.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Cumpu\ApprovalOverviewController::all
* @see app/Http/Controllers/Cumpu/ApprovalOverviewController.php:17
* @route '/cumpu/approvals/all'
*/
allForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: all.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Cumpu\ApprovalOverviewController::all
* @see app/Http/Controllers/Cumpu/ApprovalOverviewController.php:17
* @route '/cumpu/approvals/all'
*/
allForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: all.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

all.form = allForm

/**
* @see \App\Http\Controllers\Cumpu\ApprovalController::show
* @see app/Http/Controllers/Cumpu/ApprovalController.php:61
* @route '/cumpu/approvals/{approvalRequest}'
*/
export const show = (args: { approvalRequest: string | { id: string } } | [approvalRequest: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/cumpu/approvals/{approvalRequest}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Cumpu\ApprovalController::show
* @see app/Http/Controllers/Cumpu/ApprovalController.php:61
* @route '/cumpu/approvals/{approvalRequest}'
*/
show.url = (args: { approvalRequest: string | { id: string } } | [approvalRequest: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { approvalRequest: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { approvalRequest: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            approvalRequest: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        approvalRequest: typeof args.approvalRequest === 'object'
        ? args.approvalRequest.id
        : args.approvalRequest,
    }

    return show.definition.url
            .replace('{approvalRequest}', parsedArgs.approvalRequest.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Cumpu\ApprovalController::show
* @see app/Http/Controllers/Cumpu/ApprovalController.php:61
* @route '/cumpu/approvals/{approvalRequest}'
*/
show.get = (args: { approvalRequest: string | { id: string } } | [approvalRequest: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Cumpu\ApprovalController::show
* @see app/Http/Controllers/Cumpu/ApprovalController.php:61
* @route '/cumpu/approvals/{approvalRequest}'
*/
show.head = (args: { approvalRequest: string | { id: string } } | [approvalRequest: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Cumpu\ApprovalController::show
* @see app/Http/Controllers/Cumpu/ApprovalController.php:61
* @route '/cumpu/approvals/{approvalRequest}'
*/
const showForm = (args: { approvalRequest: string | { id: string } } | [approvalRequest: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Cumpu\ApprovalController::show
* @see app/Http/Controllers/Cumpu/ApprovalController.php:61
* @route '/cumpu/approvals/{approvalRequest}'
*/
showForm.get = (args: { approvalRequest: string | { id: string } } | [approvalRequest: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Cumpu\ApprovalController::show
* @see app/Http/Controllers/Cumpu/ApprovalController.php:61
* @route '/cumpu/approvals/{approvalRequest}'
*/
showForm.head = (args: { approvalRequest: string | { id: string } } | [approvalRequest: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

show.form = showForm

/**
* @see \App\Http\Controllers\Cumpu\ApprovalController::approve
* @see app/Http/Controllers/Cumpu/ApprovalController.php:99
* @route '/cumpu/approvals/{approvalRequest}/approve'
*/
export const approve = (args: { approvalRequest: string | { id: string } } | [approvalRequest: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: approve.url(args, options),
    method: 'patch',
})

approve.definition = {
    methods: ["patch"],
    url: '/cumpu/approvals/{approvalRequest}/approve',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Cumpu\ApprovalController::approve
* @see app/Http/Controllers/Cumpu/ApprovalController.php:99
* @route '/cumpu/approvals/{approvalRequest}/approve'
*/
approve.url = (args: { approvalRequest: string | { id: string } } | [approvalRequest: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { approvalRequest: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { approvalRequest: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            approvalRequest: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        approvalRequest: typeof args.approvalRequest === 'object'
        ? args.approvalRequest.id
        : args.approvalRequest,
    }

    return approve.definition.url
            .replace('{approvalRequest}', parsedArgs.approvalRequest.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Cumpu\ApprovalController::approve
* @see app/Http/Controllers/Cumpu/ApprovalController.php:99
* @route '/cumpu/approvals/{approvalRequest}/approve'
*/
approve.patch = (args: { approvalRequest: string | { id: string } } | [approvalRequest: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: approve.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Cumpu\ApprovalController::approve
* @see app/Http/Controllers/Cumpu/ApprovalController.php:99
* @route '/cumpu/approvals/{approvalRequest}/approve'
*/
const approveForm = (args: { approvalRequest: string | { id: string } } | [approvalRequest: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: approve.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Cumpu\ApprovalController::approve
* @see app/Http/Controllers/Cumpu/ApprovalController.php:99
* @route '/cumpu/approvals/{approvalRequest}/approve'
*/
approveForm.patch = (args: { approvalRequest: string | { id: string } } | [approvalRequest: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: approve.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

approve.form = approveForm

/**
* @see \App\Http\Controllers\Cumpu\ApprovalController::reject
* @see app/Http/Controllers/Cumpu/ApprovalController.php:116
* @route '/cumpu/approvals/{approvalRequest}/reject'
*/
export const reject = (args: { approvalRequest: string | { id: string } } | [approvalRequest: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: reject.url(args, options),
    method: 'patch',
})

reject.definition = {
    methods: ["patch"],
    url: '/cumpu/approvals/{approvalRequest}/reject',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Cumpu\ApprovalController::reject
* @see app/Http/Controllers/Cumpu/ApprovalController.php:116
* @route '/cumpu/approvals/{approvalRequest}/reject'
*/
reject.url = (args: { approvalRequest: string | { id: string } } | [approvalRequest: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { approvalRequest: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { approvalRequest: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            approvalRequest: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        approvalRequest: typeof args.approvalRequest === 'object'
        ? args.approvalRequest.id
        : args.approvalRequest,
    }

    return reject.definition.url
            .replace('{approvalRequest}', parsedArgs.approvalRequest.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Cumpu\ApprovalController::reject
* @see app/Http/Controllers/Cumpu/ApprovalController.php:116
* @route '/cumpu/approvals/{approvalRequest}/reject'
*/
reject.patch = (args: { approvalRequest: string | { id: string } } | [approvalRequest: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: reject.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Cumpu\ApprovalController::reject
* @see app/Http/Controllers/Cumpu/ApprovalController.php:116
* @route '/cumpu/approvals/{approvalRequest}/reject'
*/
const rejectForm = (args: { approvalRequest: string | { id: string } } | [approvalRequest: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: reject.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Cumpu\ApprovalController::reject
* @see app/Http/Controllers/Cumpu/ApprovalController.php:116
* @route '/cumpu/approvals/{approvalRequest}/reject'
*/
rejectForm.patch = (args: { approvalRequest: string | { id: string } } | [approvalRequest: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: reject.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

reject.form = rejectForm

/**
* @see \App\Http\Controllers\Cumpu\ApprovalController::reassign
* @see app/Http/Controllers/Cumpu/ApprovalController.php:133
* @route '/cumpu/approvals/{approvalRequest}/reassign'
*/
export const reassign = (args: { approvalRequest: string | { id: string } } | [approvalRequest: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: reassign.url(args, options),
    method: 'patch',
})

reassign.definition = {
    methods: ["patch"],
    url: '/cumpu/approvals/{approvalRequest}/reassign',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Cumpu\ApprovalController::reassign
* @see app/Http/Controllers/Cumpu/ApprovalController.php:133
* @route '/cumpu/approvals/{approvalRequest}/reassign'
*/
reassign.url = (args: { approvalRequest: string | { id: string } } | [approvalRequest: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { approvalRequest: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { approvalRequest: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            approvalRequest: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        approvalRequest: typeof args.approvalRequest === 'object'
        ? args.approvalRequest.id
        : args.approvalRequest,
    }

    return reassign.definition.url
            .replace('{approvalRequest}', parsedArgs.approvalRequest.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Cumpu\ApprovalController::reassign
* @see app/Http/Controllers/Cumpu/ApprovalController.php:133
* @route '/cumpu/approvals/{approvalRequest}/reassign'
*/
reassign.patch = (args: { approvalRequest: string | { id: string } } | [approvalRequest: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: reassign.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Cumpu\ApprovalController::reassign
* @see app/Http/Controllers/Cumpu/ApprovalController.php:133
* @route '/cumpu/approvals/{approvalRequest}/reassign'
*/
const reassignForm = (args: { approvalRequest: string | { id: string } } | [approvalRequest: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: reassign.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Cumpu\ApprovalController::reassign
* @see app/Http/Controllers/Cumpu/ApprovalController.php:133
* @route '/cumpu/approvals/{approvalRequest}/reassign'
*/
reassignForm.patch = (args: { approvalRequest: string | { id: string } } | [approvalRequest: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: reassign.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

reassign.form = reassignForm

/**
* @see \App\Http\Controllers\Cumpu\ApprovalController::cancel
* @see app/Http/Controllers/Cumpu/ApprovalController.php:164
* @route '/cumpu/approvals/{approvalRequest}/cancel'
*/
export const cancel = (args: { approvalRequest: string | { id: string } } | [approvalRequest: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: cancel.url(args, options),
    method: 'patch',
})

cancel.definition = {
    methods: ["patch"],
    url: '/cumpu/approvals/{approvalRequest}/cancel',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Cumpu\ApprovalController::cancel
* @see app/Http/Controllers/Cumpu/ApprovalController.php:164
* @route '/cumpu/approvals/{approvalRequest}/cancel'
*/
cancel.url = (args: { approvalRequest: string | { id: string } } | [approvalRequest: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { approvalRequest: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { approvalRequest: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            approvalRequest: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        approvalRequest: typeof args.approvalRequest === 'object'
        ? args.approvalRequest.id
        : args.approvalRequest,
    }

    return cancel.definition.url
            .replace('{approvalRequest}', parsedArgs.approvalRequest.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Cumpu\ApprovalController::cancel
* @see app/Http/Controllers/Cumpu/ApprovalController.php:164
* @route '/cumpu/approvals/{approvalRequest}/cancel'
*/
cancel.patch = (args: { approvalRequest: string | { id: string } } | [approvalRequest: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: cancel.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Cumpu\ApprovalController::cancel
* @see app/Http/Controllers/Cumpu/ApprovalController.php:164
* @route '/cumpu/approvals/{approvalRequest}/cancel'
*/
const cancelForm = (args: { approvalRequest: string | { id: string } } | [approvalRequest: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: cancel.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Cumpu\ApprovalController::cancel
* @see app/Http/Controllers/Cumpu/ApprovalController.php:164
* @route '/cumpu/approvals/{approvalRequest}/cancel'
*/
cancelForm.patch = (args: { approvalRequest: string | { id: string } } | [approvalRequest: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: cancel.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

cancel.form = cancelForm

const approvals = {
    index: Object.assign(index, index),
    myRequests: Object.assign(myRequests, myRequests),
    all: Object.assign(all, all),
    reports: Object.assign(reports, reports),
    show: Object.assign(show, show),
    approve: Object.assign(approve, approve),
    reject: Object.assign(reject, reject),
    reassign: Object.assign(reassign, reassign),
    cancel: Object.assign(cancel, cancel),
}

export default approvals