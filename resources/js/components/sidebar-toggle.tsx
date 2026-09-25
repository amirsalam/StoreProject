import { Button } from '@/components/ui/button';
import { useSidebar } from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { PanelLeftClose, PanelLeftOpen, PanelRightClose, PanelRightOpen } from 'lucide-react';

/**
 * Show/hide button for the app sidebar. The icon reflects the current
 * state and is mirrored in RTL, where the sidebar sits on the right.
 * The open state persists via AppShell (localStorage); Ctrl/⌘+B also toggles.
 */
export function SidebarToggle({ className }: { className?: string }) {
    const { open, openMobile, isMobile, toggleSidebar } = useSidebar();
    const { direction } = usePage<SharedData>().props;

    const isOpen = isMobile ? openMobile : open;
    const rtl = direction === 'rtl';
    const Icon = isOpen ? (rtl ? PanelRightClose : PanelLeftClose) : rtl ? PanelRightOpen : PanelLeftOpen;
    const label = isOpen ? 'Hide sidebar' : 'Show sidebar';

    return (
        <Button
            variant="ghost"
            size="icon"
            className={cn('h-7 w-7 shrink-0', className)}
            onClick={toggleSidebar}
            aria-label={label}
            aria-expanded={isOpen}
            title={label}
        >
            <Icon />
        </Button>
    );
}
