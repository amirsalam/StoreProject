import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { Container } from '@/components/ui/container';
import { type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { CheckCircle2, Clock, MailX, XCircle } from 'lucide-react';

interface InvitationProps {
    invitation: {
        token: string;
        email: string;
        role: string;
        tenant: { name: string; slug: string };
        invited_by: string | null;
        expires_at: string;
        is_open: boolean;
        is_accepted: boolean;
        is_expired: boolean;
    } | null;
}

/**
 * Public landing page for an invitation link.
 *
 * Branches:
 *   - Token doesn't exist          → "not found" message.
 *   - Already accepted             → friendly "already accepted" view.
 *   - Expired                      → "expired" view + "request a new one" hint.
 *   - Open + guest                 → "sign in to accept" button.
 *   - Open + auth (matching email) → "accept" button.
 *   - Open + auth (wrong email)    → "this invitation is for {email}" + sign out.
 */
export default function AcceptInvitation({ invitation }: InvitationProps) {
    const { auth } = usePage<SharedData>().props;
    const user = auth?.user ?? null;

    if (!invitation) {
        return <Shell title="Invitation not found" icon={<MailX />}>
            <p className="text-sm text-muted-foreground">
                We couldn&apos;t find an invitation for that link. It may have been revoked or the URL was mistyped.
            </p>
        </Shell>;
    }

    if (invitation.is_accepted) {
        return <Shell title={`You're already a member of ${invitation.tenant.name}`} icon={<CheckCircle2 />}>
            <p className="text-sm text-muted-foreground">
                This invitation was already accepted. Head into the workspace to continue.
            </p>
            <Button asChild className="mt-6">
                <Link href={route('dashboard')}>Go to dashboard</Link>
            </Button>
        </Shell>;
    }

    if (invitation.is_expired) {
        return <Shell title="This invitation has expired" icon={<Clock />}>
            <p className="text-sm text-muted-foreground">
                Invitations expire 7 days after they&apos;re sent. Ask {invitation.invited_by ?? 'the inviter'} to send a fresh one.
            </p>
        </Shell>;
    }

    const emailMismatch = user && user.email.toLowerCase() !== invitation.email.toLowerCase();

    const accept = () => {
        router.post(route('invitations.accept', invitation.token));
    };

    return (
        <Shell
            title={`Join ${invitation.tenant.name}`}
            icon={<CheckCircle2 className="text-primary" />}
        >
            <div className="space-y-4">
                <p className="text-sm text-muted-foreground">
                    {invitation.invited_by ? <strong>{invitation.invited_by}</strong> : 'A teammate'} has
                    invited <strong>{invitation.email}</strong> to join{' '}
                    <strong>{invitation.tenant.name}</strong> as <span className="font-mono text-foreground">{invitation.role}</span>.
                </p>

                {!user && (
                    <div className="rounded-lg border bg-muted/30 p-4 text-sm">
                        <p className="mb-3 text-muted-foreground">Sign in or create an account to accept.</p>
                        <div className="flex gap-2">
                            <Button asChild size="sm">
                                <Link href={route('login')}>Sign in</Link>
                            </Button>
                            <Button asChild size="sm" variant="outline">
                                <Link href={route('register')}>Create an account</Link>
                            </Button>
                        </div>
                    </div>
                )}

                {user && emailMismatch && (
                    <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-200">
                        <p className="mb-2 inline-flex items-center gap-1.5 font-medium">
                            <XCircle className="size-4" />
                            You&apos;re signed in as a different email.
                        </p>
                        <p className="mb-3">
                            This invitation is for <strong>{invitation.email}</strong> but you&apos;re signed in as{' '}
                            <strong>{user.email}</strong>. Sign out and back in to accept.
                        </p>
                        <Button asChild size="sm" variant="outline">
                            <Link href={route('logout')} method="post" as="button">Sign out</Link>
                        </Button>
                    </div>
                )}

                {user && !emailMismatch && (
                    <Button size="lg" className="w-full" onClick={accept}>
                        Accept &amp; join {invitation.tenant.name}
                    </Button>
                )}

                <p className="text-center text-xs text-muted-foreground">
                    Expires {new Date(invitation.expires_at).toLocaleDateString()}.
                </p>
            </div>
        </Shell>
    );
}

function Shell({
    title,
    icon,
    children,
}: {
    title: string;
    icon: React.ReactNode;
    children: React.ReactNode;
}) {
    return (
        <>
            <Head title={title} />
            <div className="flex min-h-screen items-center justify-center bg-background py-16">
                <Container className="!max-w-md">
                    <div className="space-y-6 rounded-2xl border bg-card p-8 shadow-xl shadow-foreground/[0.06]">
                        <header className="flex flex-col items-center gap-4 text-center">
                            <Link href="/" className="flex items-center gap-2 font-display text-sm font-semibold">
                                <span className="flex size-7 items-center justify-center rounded-md bg-foreground text-background">
                                    <AppLogoIcon className="size-4" />
                                </span>
                                StoreProject
                            </Link>
                            <div className="flex size-12 items-center justify-center rounded-full border border-border bg-muted/40 [&_svg]:size-5">
                                {icon}
                            </div>
                            <h1 className="font-display text-xl font-semibold tracking-tight">{title}</h1>
                        </header>
                        {children}
                    </div>
                </Container>
            </div>
        </>
    );
}
