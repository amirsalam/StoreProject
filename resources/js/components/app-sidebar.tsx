import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { SidebarToggle } from '@/components/sidebar-toggle';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    CreditCard,
    FileText,
    Folder,
    FolderKanban,
    LayoutGrid,
    ListTodo,
    Mail,
    Newspaper,
    Package,
    Palette,
    Store,
    UserCog,
    Users,
    Wallet,
} from 'lucide-react';
import AppLogo from './app-logo';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        url: '/dashboard',
        icon: LayoutGrid,
    },
];

const workspaceNavItems: NavItem[] = [
    {
        title: 'Projects',
        url: '/workspace/projects',
        icon: FolderKanban,
    },
    {
        title: 'Tasks',
        url: '/workspace/tasks',
        icon: ListTodo,
    },
    {
        title: 'Invoices',
        url: '/workspace/invoices',
        icon: FileText,
    },
    {
        title: 'My store',
        url: '/workspace/vendor',
        icon: Store,
    },
    {
        title: 'Team',
        url: '/workspace/team',
        icon: UserCog,
    },
    {
        title: 'Billing',
        url: '/workspace/billing',
        icon: CreditCard,
    },
];

const adminNavItems: NavItem[] = [
    {
        title: 'Products',
        url: '/admin/products',
        icon: Package,
    },
    {
        title: 'Blog',
        url: '/admin/blog-posts',
        icon: Newspaper,
    },
    {
        title: 'Contact',
        url: '/admin/contact',
        icon: Mail,
    },
    {
        title: 'Users',
        url: '/admin/users',
        icon: Users,
    },
    {
        title: 'Branding',
        url: '/admin/branding',
        icon: Palette,
    },
    {
        title: 'Payment Gateways',
        url: '/admin/payment-gateways',
        icon: Wallet,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        url: 'https://github.com/laravel/react-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentation',
        url: 'https://laravel.com/docs/starter-kits',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const { auth, direction } = usePage<SharedData>().props;
    const isAdmin = Boolean(auth?.user?.is_admin);

    return (
        // The sidebar sits on the reading-start edge: the spacer that reserves its
        // width is a flex item and flips with dir="rtl", so the fixed panel must too.
        // Closing hides it fully (offcanvas); SidebarToggle here and in the page header show/hide it.
        <Sidebar side={direction === 'rtl' ? 'right' : 'left'} collapsible="offcanvas" variant="inset">
            <SidebarHeader className="flex-row items-center">
                <SidebarMenu className="min-w-0 flex-1">
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <SidebarToggle />
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
                <NavMain items={workspaceNavItems} label="Workspace" />
                {isAdmin && <NavMain items={adminNavItems} label="Admin" />}
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
