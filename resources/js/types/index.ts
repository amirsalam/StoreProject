import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}

export type ProductType = 'digital_download' | 'subscription' | 'api_access' | 'license';

export interface Category {
    id: number;
    name: string;
    slug: string;
    parent_id: number | null;
}

export interface Product {
    id: number;
    category_id: number | null;
    title: string;
    slug: string;
    short_description: string | null;
    description: string | null;
    type: ProductType;
    price: string;
    sale_price: string | null;
    currency: string;
    thumbnail: string | null;
    version: string | null;
    license_type: string | null;
    is_featured: boolean;
    sales_count: number;
    category?: Pick<Category, 'id' | 'name' | 'slug'> | null;
    created_at: string;
}

export interface Review {
    id: number;
    user_id: number;
    product_id: number;
    rating: number;
    title: string | null;
    comment: string | null;
    is_verified_purchase: boolean;
    created_at: string;
    user?: Pick<User, 'id' | 'name'>;
}

export interface PaginatedLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginatedLink[];
}
