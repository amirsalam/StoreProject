import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type Paginated } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ShieldCheck, ShieldOff } from 'lucide-react';
import { FormEvent, useState } from 'react';

interface UserRow {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    has_two_factor: boolean;
    created_at: string;
    role: string | null;
}

interface AdminUsersIndexProps {
    users: Paginated<UserRow>;
    filters: { search: string; role: string };
    roles: string[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin/products' },
    { title: 'Users', href: '/admin/users' },
];

export default function AdminUsersIndex({ users, filters, roles }: AdminUsersIndexProps) {
    const { flash, errors } = usePage<{
        flash: { success: string | null; error: string | null };
        errors: Record<string, string>;
    }>().props;
    const [search, setSearch] = useState(filters.search);

    const applyFilter = (next: Partial<{ search: string; role: string }>) => {
        const merged = { ...filters, ...next };
        const params: Record<string, string> = {};
        for (const [k, v] of Object.entries(merged)) if (v) params[k] = String(v);
        router.get(route('admin.users.index'), params, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    };

    const submitSearch = (e: FormEvent) => {
        e.preventDefault();
        applyFilter({ search });
    };

    const updateRole = (userId: number, role: string) => {
        router.patch(
            route('admin.users.role.update', userId),
            { role },
            { preserveScroll: true, preserveState: true },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Users · Admin" />

            <div className="mx-auto w-full max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                {flash?.success && (
                    <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                        {flash.success}
                    </div>
                )}
                {errors.role && (
                    <div className="rounded-md border border-destructive/30 bg-destructive/[0.06] px-4 py-2 text-sm text-destructive">
                        {errors.role}
                    </div>
                )}

                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="font-display text-2xl font-semibold tracking-tight">Users</h1>
                        <p className="text-sm text-muted-foreground">
                            {users.total} {users.total === 1 ? 'user' : 'users'} · {roles.length} roles
                        </p>
                    </div>
                </div>

                <form onSubmit={submitSearch} className="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                    <Input
                        type="search"
                        placeholder="Search by name or email…"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="w-full sm:w-72"
                    />
                    <select
                        value={filters.role}
                        onChange={(e) => applyFilter({ role: e.target.value })}
                        className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm sm:w-auto"
                    >
                        <option value="">All roles</option>
                        {roles.map((r) => (
                            <option key={r} value={r}>{r}</option>
                        ))}
                    </select>
                    <Button type="submit" variant="secondary">Filter</Button>
                </form>

                {/* Desktop / tablet: table */}
                <div className="hidden overflow-hidden rounded-lg border bg-card md:block">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left text-xs uppercase tracking-wider text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">Name</th>
                                <th className="px-4 py-3 font-medium">Email</th>
                                <th className="px-4 py-3 font-medium">Role</th>
                                <th className="px-4 py-3 font-medium">2FA</th>
                                <th className="px-4 py-3 font-medium">Joined</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {users.data.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="px-4 py-12 text-center text-muted-foreground">
                                        No users match these filters.
                                    </td>
                                </tr>
                            ) : (
                                users.data.map((user) => (
                                    <tr key={user.id} className="hover:bg-muted/30">
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-3">
                                                <Avatar name={user.name} />
                                                <span className="font-medium">{user.name}</span>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">{user.email}</td>
                                        <td className="px-4 py-3">
                                            <RolePicker
                                                value={user.role ?? ''}
                                                roles={roles}
                                                onChange={(role) => updateRole(user.id, role)}
                                            />
                                        </td>
                                        <td className="px-4 py-3">
                                            <TwoFactorBadge enabled={user.has_two_factor} />
                                        </td>
                                        <td className="px-4 py-3 text-xs text-muted-foreground tabular-nums">
                                            {new Date(user.created_at).toLocaleDateString()}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Mobile: stacked cards */}
                <div className="space-y-3 md:hidden">
                    {users.data.length === 0 ? (
                        <div className="rounded-lg border border-dashed bg-card p-8 text-center text-sm text-muted-foreground">
                            No users match these filters.
                        </div>
                    ) : (
                        users.data.map((user) => (
                            <div key={user.id} className="rounded-lg border bg-card p-4 shadow-sm">
                                <div className="flex items-start gap-3">
                                    <Avatar name={user.name} />
                                    <div className="min-w-0 flex-1">
                                        <div className="truncate font-medium">{user.name}</div>
                                        <div className="truncate text-xs text-muted-foreground">{user.email}</div>
                                    </div>
                                    <TwoFactorBadge enabled={user.has_two_factor} />
                                </div>
                                <div className="mt-3 border-t pt-3">
                                    <label className="font-mono text-[11px] uppercase tracking-wider text-muted-foreground">
                                        Role
                                    </label>
                                    <div className="mt-1">
                                        <RolePicker
                                            value={user.role ?? ''}
                                            roles={roles}
                                            onChange={(role) => updateRole(user.id, role)}
                                        />
                                    </div>
                                </div>
                            </div>
                        ))
                    )}
                </div>

                {users.last_page > 1 && (
                    <nav className="flex flex-wrap items-center justify-center gap-1">
                        {users.links.map((link, idx) => (
                            <PaginationLink key={idx} link={link} />
                        ))}
                    </nav>
                )}
            </div>
        </AppLayout>
    );
}

function Avatar({ name }: { name: string }) {
    const initials = name
        .split(' ')
        .map((n) => n[0])
        .filter(Boolean)
        .join('')
        .slice(0, 2)
        .toUpperCase();
    return (
        <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-foreground text-[10px] font-semibold text-background">
            {initials}
        </span>
    );
}

function RolePicker({
    value,
    roles,
    onChange,
}: {
    value: string;
    roles: string[];
    onChange: (role: string) => void;
}) {
    return (
        <Select value={value} onValueChange={onChange}>
            <SelectTrigger className="h-8 w-36">
                <SelectValue placeholder="—" />
            </SelectTrigger>
            <SelectContent>
                {roles.map((r) => (
                    <SelectItem key={r} value={r} className="capitalize">
                        {r}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

function TwoFactorBadge({ enabled }: { enabled: boolean }) {
    return enabled ? (
        <Badge variant="default" className="bg-emerald-500/15 text-emerald-700 hover:bg-emerald-500/20 dark:text-emerald-400">
            <ShieldCheck className="size-3" />
            On
        </Badge>
    ) : (
        <Badge variant="secondary" className="text-muted-foreground">
            <ShieldOff className="size-3" />
            Off
        </Badge>
    );
}

function PaginationLink({ link }: { link: { url: string | null; label: string; active: boolean } }) {
    const className = cn(
        'min-w-9 rounded-md border px-3 py-1.5 text-sm transition',
        link.active
            ? 'border-primary bg-primary text-primary-foreground'
            : link.url
                ? 'border-input hover:bg-accent'
                : 'border-transparent text-muted-foreground',
    );

    if (!link.url) {
        return <span className={className} dangerouslySetInnerHTML={{ __html: link.label }} />;
    }
    return <Link href={link.url} preserveScroll preserveState className={className} dangerouslySetInnerHTML={{ __html: link.label }} />;
}
