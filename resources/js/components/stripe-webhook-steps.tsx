import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useState } from 'react';

/** Where Stripe should send events for this store, and which events. */
export interface StripeWebhookInfo {
    url: string;
    events: string[];
}

/**
 * Stripe needs to call back when a payment succeeds; without the webhook,
 * paid orders stay pending and nothing is delivered.
 */
export function StripeWebhookSteps({ info }: { info: StripeWebhookInfo }) {
    const [copied, setCopied] = useState(false);

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(info.url);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch {
            // Clipboard unavailable (insecure context) — the URL is selectable.
        }
    };

    return (
        <div className="mb-4 space-y-3 rounded-md border border-dashed p-4 text-sm">
            <p className="text-muted-foreground">
                Without a webhook, card payments go through at Stripe but orders stay pending and nothing is delivered. In the Stripe Dashboard →
                Developers → Webhooks, add an endpoint:
            </p>
            <div className="flex gap-2">
                <Input value={info.url} readOnly dir="ltr" className="bg-muted/40 font-mono text-xs" onFocus={(e) => e.target.select()} />
                <Button type="button" variant="outline" size="sm" onClick={copy}>
                    {copied ? 'Copied' : 'Copy'}
                </Button>
            </div>
            <div>
                <p className="text-muted-foreground">Events to send:</p>
                <ul className="mt-1 flex flex-wrap gap-1.5" dir="ltr">
                    {info.events.map((event) => (
                        <li key={event} className="bg-muted rounded px-1.5 py-0.5 font-mono text-xs">
                            {event}
                        </li>
                    ))}
                </ul>
            </div>
            <p className="text-muted-foreground">Then paste the endpoint’s signing secret (whsec_…) below.</p>
        </div>
    );
}
