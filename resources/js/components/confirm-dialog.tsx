import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { cn } from '@/lib/utils';
import { AlertTriangle, CircleHelp } from 'lucide-react';
import { type ReactNode, useCallback, useState } from 'react';

interface ConfirmDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description: ReactNode;
    confirmLabel: string;
    cancelLabel?: string;
    /** Red confirm button + warning icon, for irreversible actions. */
    destructive?: boolean;
    processing?: boolean;
    onConfirm: () => void;
}

/**
 * In-app "are you sure?" popup, replacing the browser's window.confirm().
 * Controlled: the caller owns `open` and runs the action in `onConfirm`.
 */
export default function ConfirmDialog({
    open,
    onOpenChange,
    title,
    description,
    confirmLabel,
    cancelLabel = 'Cancel',
    destructive = false,
    processing = false,
    onConfirm,
}: ConfirmDialogProps) {
    const Icon = destructive ? AlertTriangle : CircleHelp;

    return (
        <Dialog open={open} onOpenChange={(next) => !processing && onOpenChange(next)}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader className="items-center gap-2 sm:flex-row sm:items-start sm:gap-4">
                    <span
                        className={cn(
                            'flex size-10 shrink-0 items-center justify-center rounded-full',
                            destructive ? 'bg-destructive/10 text-destructive' : 'bg-primary/10 text-primary',
                        )}
                    >
                        <Icon className="size-5" />
                    </span>
                    <div className="space-y-1.5">
                        <DialogTitle>{title}</DialogTitle>
                        <DialogDescription>{description}</DialogDescription>
                    </div>
                </DialogHeader>
                <DialogFooter>
                    <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={processing}>
                        {cancelLabel}
                    </Button>
                    <Button type="button" variant={destructive ? 'destructive' : 'default'} onClick={onConfirm} disabled={processing}>
                        {processing ? 'Working…' : confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export interface ConfirmOptions {
    title: string;
    description: ReactNode;
    confirmLabel: string;
    destructive?: boolean;
    /** Runs on confirm; call `finish` when the request completes (e.g. Inertia's onFinish). */
    action: (finish: () => void) => void;
}

/**
 * Hook form of ConfirmDialog for pages with several confirmable actions:
 *
 *   const { ask, confirmDialog } = useConfirmDialog();
 *   ask({ title, description, confirmLabel, destructive: true,
 *         action: (finish) => router.delete(url, { onFinish: finish }) });
 *   …
 *   {confirmDialog}
 *
 * The popup shows "Working…" until `finish` is called, then closes.
 */
export function useConfirmDialog() {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);
    // Kept after closing so the text doesn't vanish during the close animation.
    const [options, setOptions] = useState<ConfirmOptions | null>(null);

    const ask = useCallback((next: ConfirmOptions) => {
        setOptions(next);
        setProcessing(false);
        setOpen(true);
    }, []);

    const finish = useCallback(() => {
        setProcessing(false);
        setOpen(false);
    }, []);

    const confirmDialog = (
        <ConfirmDialog
            open={open}
            onOpenChange={setOpen}
            title={options?.title ?? ''}
            description={options?.description ?? ''}
            confirmLabel={options?.confirmLabel ?? 'Confirm'}
            destructive={options?.destructive}
            processing={processing}
            onConfirm={() => {
                if (!options) return;
                setProcessing(true);
                options.action(finish);
            }}
        />
    );

    return { ask, confirmDialog };
}
