import DraftDocument from '@/components/draft-document';

/**
 * The legal documents are unreviewed outlines rendered by the shared
 * DraftDocument shell (draft banner + robots: noindex).
 */
export default function LegalDocument({ docKey }: { docKey: 'terms' | 'privacy' | 'license' | 'refunds' }) {
    return <DraftDocument ns="legal" docKey={docKey} />;
}
