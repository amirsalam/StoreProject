import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

interface SocialAuthButtonsProps {
    /** Label prefix — "Continue with" or "Sign up with" */
    label?: string;
    className?: string;
}

const PROVIDERS = [
    { id: 'google', label: 'Google', Icon: GoogleIcon },
    { id: 'github', label: 'GitHub', Icon: GitHubIcon },
] as const;

export default function SocialAuthButtons({
    label = 'Continue with',
    className,
}: SocialAuthButtonsProps) {
    return (
        <div className={cn('space-y-2', className)}>
            <div className="grid gap-2">
                {PROVIDERS.map(({ id, label: name, Icon }) => (
                    <Button
                        asChild
                        key={id}
                        type="button"
                        variant="outline"
                        size="lg"
                        className="w-full justify-center gap-2.5"
                    >
                        <a href={`/auth/${id}/redirect`}>
                            <Icon className="size-4" />
                            <span>{label} {name}</span>
                        </a>
                    </Button>
                ))}
            </div>
            <div className="relative my-2 flex items-center text-[11px] uppercase tracking-wider text-muted-foreground">
                <div className="h-px flex-1 bg-border" />
                <span className="px-3">or</span>
                <div className="h-px flex-1 bg-border" />
            </div>
        </div>
    );
}

function GoogleIcon({ className }: { className?: string }) {
    return (
        <svg className={className} viewBox="0 0 24 24" aria-hidden="true">
            <path
                d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09Z"
                fill="#4285F4"
            />
            <path
                d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.99.66-2.25 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84A11 11 0 0 0 12 23Z"
                fill="#34A853"
            />
            <path
                d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18A11 11 0 0 0 1 12c0 1.77.42 3.45 1.18 4.93l2.85-2.22.81-.62Z"
                fill="#FBBC05"
            />
            <path
                d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1A11 11 0 0 0 2.18 7.07l3.66 2.84C6.71 7.31 9.14 5.38 12 5.38Z"
                fill="#EA4335"
            />
        </svg>
    );
}

function GitHubIcon({ className }: { className?: string }) {
    return (
        <svg className={className} viewBox="0 0 24 24" aria-hidden="true" fill="currentColor">
            <path d="M12 .5C5.65.5.5 5.65.5 12c0 5.08 3.29 9.39 7.86 10.91.58.1.79-.25.79-.56 0-.27-.01-1-.02-1.96-3.2.69-3.87-1.54-3.87-1.54-.52-1.33-1.28-1.69-1.28-1.69-1.05-.72.08-.7.08-.7 1.16.08 1.78 1.19 1.78 1.19 1.03 1.77 2.71 1.26 3.37.96.1-.75.4-1.26.73-1.55-2.55-.29-5.24-1.28-5.24-5.69 0-1.26.45-2.28 1.18-3.09-.12-.29-.51-1.46.11-3.04 0 0 .97-.31 3.18 1.18a11 11 0 0 1 5.8 0c2.21-1.49 3.18-1.18 3.18-1.18.62 1.58.23 2.75.11 3.04.74.81 1.18 1.83 1.18 3.09 0 4.42-2.69 5.4-5.25 5.68.41.35.78 1.05.78 2.12 0 1.53-.01 2.77-.01 3.14 0 .31.21.67.8.56C20.21 21.38 23.5 17.08 23.5 12 23.5 5.65 18.35.5 12 .5Z" />
        </svg>
    );
}
