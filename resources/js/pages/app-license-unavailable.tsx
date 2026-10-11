import { Button } from '@/components/ui/button';
import { Container, Section } from '@/components/ui/container';
import { useTranslate } from '@/hooks/use-translate';
import StorefrontLayout from '@/layouts/storefront-layout';
import { Head, Link } from '@inertiajs/react';
import { Clock } from 'lucide-react';

/**
 * Shown (HTTP 503) to non-admin users on licensed areas while this
 * installation is not licensed. Deliberately says nothing about licensing
 * internals — visitors only need to know it is temporary.
 */
export default function AppLicenseUnavailable() {
    const { __ } = useTranslate();

    return (
        <StorefrontLayout>
            <Head title={__('Temporarily unavailable')}>
                <meta name="robots" content="noindex" />
            </Head>

            <Section className="pt-16 pb-24 sm:pt-20 sm:pb-32">
                <Container>
                    <div className="mx-auto flex max-w-xl flex-col items-center gap-4 text-center">
                        <span className="bg-muted flex size-12 items-center justify-center rounded-full">
                            <Clock className="size-6" />
                        </span>
                        <h1 className="font-display text-3xl font-semibold tracking-tight">{__('Temporarily unavailable')}</h1>
                        <p className="text-muted-foreground text-pretty">
                            {__('This part of the site is temporarily unavailable. Please try again later.')}
                        </p>
                        <Button asChild variant="outline">
                            <Link href="/">{__('Back to the store')}</Link>
                        </Button>
                    </div>
                </Container>
            </Section>
        </StorefrontLayout>
    );
}
