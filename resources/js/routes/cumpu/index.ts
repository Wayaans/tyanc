import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../wayfinder'
import approvals from './approvals'
import approvalRules from './approval-rules'
/**
* @see \App\Http\Controllers\Cumpu\DashboardController::dashboard
* @see app/Http/Controllers/Cumpu/DashboardController.php:24
* @route '/cumpu/dashboard'
*/
export const dashboard = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: dashboard.url(options),
    method: 'get',
})

dashboard.definition = {
    methods: ["get","head"],
    url: '/cumpu/dashboard',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Cumpu\DashboardController::dashboard
* @see app/Http/Controllers/Cumpu/DashboardController.php:24
* @route '/cumpu/dashboard'
*/
dashboard.url = (options?: RouteQueryOptions) => {
    return dashboard.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Cumpu\DashboardController::dashboard
* @see app/Http/Controllers/Cumpu/DashboardController.php:24
* @route '/cumpu/dashboard'
*/
dashboard.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: dashboard.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Cumpu\DashboardController::dashboard
* @see app/Http/Controllers/Cumpu/DashboardController.php:24
* @route '/cumpu/dashboard'
*/
dashboard.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: dashboard.url(options),
    method: 'head',
})

const cumpu = {
    dashboard: Object.assign(dashboard, dashboard),
    approvals: Object.assign(approvals, approvals),
    approvalRules: Object.assign(approvalRules, approvalRules),
}

export default cumpu