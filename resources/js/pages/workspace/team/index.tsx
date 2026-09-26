import { useConfirmDialog } from '@/components/confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Mail, Trash2, UserPlus, Users } from 'lucide-react';
import { FormEvent } from 'react';

interface Member {
    id: number;
    name: string;
    email: string;
    role: string;
    joined_at: string | null;
}

interface PendingInvitation {
    id: number;
    email: string;
    role: string;
    expires_at: string;
    created_at: string;
}

interface RoleOption {
    value: string;
    label: string;
}

interface TeamIndexProps {
    members: Member[];
    invitations: PendingInvitation[];
    roles: RoleOption[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Workspace', href: '/workspace/projects' },
    { title: 'Team', href: '/workspace/team' },
];

export default function WorkspaceTeamIndex({ members, invitations, roles }: TeamIndexProps) {
    const { flash } = usePage<{ flash: { success: string | null; error: string | null } }>().props;

    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        role: 'member',
    });

    const submitInvite = (e: FormEvent) => {
        e.preventDefault();
        post(route('workspace.team.invitations.store'), {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    const { ask, confirmDialog } = useConfirmDialog();

    const revoke = (invitation: PendingInvitation) => {
        ask({
            title: 'Revoke this invitation?',
            description: `The invitation link sent to ${invitation.email} stops working.`,
            confirmLabel: 'Revoke invitation',
            destructive: true,
            action: (finish) =>
                router.delete(route('workspace.team.invitations.revoke', invitation.id), { preserveScroll: true, onFinish: finish }),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Workspace · Team" />

            <div className="mx-auto w-full max-w-5xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                {flash?.success && (
                    <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                        {flash.success}
                    </div>
                )}

                <div>
                    <h1 className="font-display text-2xl font-semibold tracking-tight">Team</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Invite people to your workspace. They&apos;ll get a sign-in link by email.
                    </p>
                </div>

                {/* INVITE FORM */}
                <section className="rounded-xl border bg-card p-5 shadow-sm sm:p-6">
                    <h2 className="mb-4 inline-flex items-center gap-2 font-display text-base font-semibold tracking-tight">
                        <UserPlus className="size-4" /> Invite a teammate
                    </h2>
                    <form onSubmit={submitInvite} className="flex flex-col gap-3 sm:flex-row">
                        <Input
                            type="email"
                            required
                            placeholder="teammate@example.com"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            className="flex-1"
                        />
                        <select
                            value={data.role}
                            onChange={(e) => setData('role', e.target.value)}
                            className="rounded-md border border-input bg-background px-3 py-2 text-sm sm:w-40"
                        >
                            {roles.map((r) => (
                                <option key={r.value} value={r.value}>{r.label}</option>
                            ))}
                        </select>
                        <Button type="submit" disabled={processing}>Send invite</Button>
                    </form>
                    {(errors.email || errors.role) && (
                        <p className="mt-2 text-xs text-destructive">{errors.email ?? errors.role}</p>
                    )}
                </section>

                {/* PENDING INVITES */}
                {invitations.length > 0 && (
                    <section className="rounded-xl border bg-card shadow-sm">
                        <header className="flex items-center justify-between border-b px-5 py-3 sm:px-6">
                            <h2 className="inline-flex items-center gap-2 font-display text-sm font-semibold tracking-tight">
                                <Mail className="size-4" /> Pending invitations
                                <Badge variant="secondary" className="ms-1 font-mono text-[10px]">
                                    {invitations.length}
                                </Badge>
                            </h2>
                        </header>
                        <ul className="divide-y">
                            {invitations.map((inv) => (
                                <li key={inv.id} className="flex items-center justify-between gap-3 px-5 py-3 sm:px-6">
                                    <div className="min-w-0">
                                        <div className="truncate text-sm font-medium">{inv.email}</div>
                                        <div className="text-xs text-muted-foreground">
                                            {inv.role} · expires {new Date(inv.expires_at).toLocaleDateString()}
                                        </div>
                                    </div>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        onClick={() => revoke(inv)}
                                        className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                    >
                                        <Trash2 className="size-3.5" /> Revoke
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                {/* MEMBERS */}
                <section className="rounded-xl border bg-card shadow-sm">
                    <header className="flex items-center justify-between border-b px-5 py-3 sm:px-6">
                        <h2 className="inline-flex items-center gap-2 font-display text-sm font-semibold tracking-tight">
                            <Users className="size-4" /> Members
                            <Badge variant="secondary" className="ms-1 font-mono text-[10px]">
                                {members.length}
                            </Badge>
                        </h2>
                    </header>
                    <ul className="divide-y">
                        {members.map((m) => (
                            <li key={m.id} className="flex items-center gap-3 px-5 py-3 sm:px-6">
                                <div className="flex size-9 shrink-0 items-center justify-center rounded-full bg-foreground text-xs font-semibold text-background">
                                    {m.name.split(' ').map((p) => p[0]).join('').slice(0, 2).toUpperCase()}
                                </div>
                                <div className="min-w-0 flex-1">
                                    <div className="truncate text-sm font-medium">{m.name}</div>
                                    <div className="truncate text-xs text-muted-foreground">{m.email}</div>
                                </div>
                                <Badge variant="outline" className="capitalize">{m.role}</Badge>
                            </li>
                        ))}
                    </ul>
                </section>
            </div>

            {confirmDialog}
        </AppLayout>
    );
}
