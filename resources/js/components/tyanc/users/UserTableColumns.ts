import {
    createColumnHelper,
    type ColumnDef,
    type Table as TanStackTable,
} from '@tanstack/vue-table';
import { h } from 'vue';
import UserActionsDropdown from '@/components/tyanc/users/UserActionsDropdown.vue';
import UserStatusBadge from '@/components/tyanc/users/UserStatusBadge.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { getInitials } from '@/composables/useInitials';
import { __ } from '@/lib/translations';
import type { UserRow } from '@/types';

const columnHelper = createColumnHelper<UserRow>();

export function createUserTableColumns(
    dateFormatter: Intl.DateTimeFormat,
): ColumnDef<UserRow>[] {
    return [
        columnHelper.display({
            id: 'select',
            enableSorting: false,
            enableHiding: false,
            header: ({ table }: { table: TanStackTable<UserRow> }) =>
                h(Checkbox, {
                    modelValue: table.getIsAllPageRowsSelected()
                        ? true
                        : table.getIsSomePageRowsSelected()
                          ? 'indeterminate'
                          : false,
                    'onUpdate:modelValue': (value: boolean | 'indeterminate') =>
                        table.toggleAllPageRowsSelected(Boolean(value)),
                    'aria-label': __('Select all rows'),
                }),
            cell: ({ row }) =>
                h(Checkbox, {
                    modelValue: row.getIsSelected(),
                    'onUpdate:modelValue': (value: boolean | 'indeterminate') =>
                        row.toggleSelected(Boolean(value)),
                    'aria-label': __('Select row'),
                }),
            meta: { label: 'Selection' },
        }),

        columnHelper.accessor('name', {
            header: __('User'),
            enableSorting: true,
            cell: ({ row }) =>
                h('div', { class: 'flex items-center gap-3 min-w-48' }, [
                    h(
                        Avatar,
                        { class: 'size-8 shrink-0' },
                        {
                            default: () => [
                                h(AvatarImage, {
                                    src: row.original.avatar ?? '',
                                    alt: row.original.name,
                                }),
                                h(
                                    AvatarFallback,
                                    { class: 'text-xs' },
                                    {
                                        default: () =>
                                            getInitials(row.original.name),
                                    },
                                ),
                            ],
                        },
                    ),
                    h('div', { class: 'space-y-0.5' }, [
                        h(
                            'p',
                            {
                                class: 'font-medium text-foreground leading-none',
                            },
                            row.original.name,
                        ),
                        h(
                            'p',
                            { class: 'text-xs text-muted-foreground' },
                            row.original.email,
                        ),
                    ]),
                ]),
            meta: { label: 'User' },
        }),

        columnHelper.accessor('username', {
            header: __('Username'),
            enableSorting: true,
            cell: ({ getValue }) => {
                const val = getValue();

                return h(
                    'span',
                    { class: 'text-muted-foreground font-mono text-xs' },
                    val ? `@${val}` : '—',
                );
            },
            meta: { label: 'Username' },
        }),

        columnHelper.accessor('status', {
            header: __('Status'),
            enableSorting: true,
            cell: ({ getValue }) =>
                h(UserStatusBadge, { status: String(getValue()) }),
            meta: { label: 'Status' },
        }),

        columnHelper.accessor('roles', {
            header: __('Roles'),
            enableSorting: false,
            cell: ({ getValue }) => {
                const roles = getValue() as string[];

                if (!roles || roles.length === 0) {
                    return h(
                        'span',
                        { class: 'text-muted-foreground text-xs' },
                        '—',
                    );
                }

                return h(
                    'div',
                    { class: 'flex flex-wrap gap-1' },
                    roles
                        .slice(0, 2)
                        .map((role) =>
                            h(
                                Badge,
                                {
                                    key: role,
                                    variant: 'secondary',
                                    class: 'rounded-full text-xs',
                                },
                                { default: () => role },
                            ),
                        )
                        .concat(
                            roles.length > 2
                                ? [
                                      h(
                                          Badge,
                                          {
                                              variant: 'outline',
                                              class: 'rounded-full text-xs',
                                          },
                                          {
                                              default: () =>
                                                  `+${roles.length - 2}`,
                                          },
                                      ),
                                  ]
                                : [],
                        ),
                );
            },
            meta: { label: 'Roles' },
        }),

        columnHelper.accessor('locale', {
            header: __('Locale'),
            enableSorting: true,
            cell: ({ getValue }) =>
                h(
                    'span',
                    { class: 'text-muted-foreground text-xs uppercase' },
                    String(getValue()),
                ),
            meta: { label: 'Locale' },
        }),

        columnHelper.accessor('last_login_at', {
            header: __('Last login'),
            enableSorting: true,
            cell: ({ getValue }) => {
                const val = getValue();

                return h(
                    'span',
                    {
                        class: 'whitespace-nowrap text-muted-foreground text-xs',
                    },
                    val ? dateFormatter.format(new Date(String(val))) : '—',
                );
            },
            meta: { label: 'Last login' },
        }),

        columnHelper.accessor('created_at', {
            header: __('Joined'),
            enableSorting: true,
            cell: ({ getValue }) =>
                h(
                    'span',
                    {
                        class: 'whitespace-nowrap text-muted-foreground text-xs',
                    },
                    dateFormatter.format(new Date(String(getValue()))),
                ),
            meta: { label: 'Joined' },
        }),

        columnHelper.display({
            id: 'actions',
            enableSorting: false,
            enableHiding: false,
            header: '',
            cell: ({ row }) =>
                h(
                    'div',
                    { class: 'flex justify-end' },
                    h(UserActionsDropdown, {
                        userId: row.original.id,
                        userName: row.original.name,
                    }),
                ),
            meta: { label: 'Actions' },
        }),
    ] as ColumnDef<UserRow>[];
}
