import { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
    badge?: string | number;
    action?: () => void;
    children?: NavItem[];
}

export type AppPageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    sidebarOpen: boolean;
    asterisk: {
        host: string;
        port: string;
        domain: string;
    };
};

export interface Wallet {
    id: number;
    user_id: number;
    balance: string | number;
    currency: string;
}

export interface User {
    id: number;
    name: string;
    email: string;
    phone: string;
    status: string;
    avatar?: string;
    avatar_url: string;
    roles: string[];
    email_verified_at: string | null;
    sip_extension?: string;
    sip_password?: string;
    wallet?: Wallet;
    created_at: string;
    updated_at: string;
}

export interface OrderItem {
    id: number;
    order_id: number;
    product_id: number;
    quantity: number;
    unit_price: string | number;
    subtotal: string | number;
    product: {
        id: number;
        name: string;
        price: string | number;
    };
}

export interface Order {
    id: number;
    order_number: string;
    user_id: number;
    status: 'pending' | 'confirmed' | 'in_production' | 'ready' | 'delivered' | 'cancelled';
    statusLabel?: string;
    scheduled_delivery_at: string;
    actual_delivery_at?: string;
    total_amount: string | number;
    paid_amount: string | number;
    payment_status: string;
    notes?: string;
    delivery_address?: string | null;
    courier_id?: number | null;
    courier?: User | null;
    client?: {
        id: number;
        name: string;
        email: string;
        phone: string | null;
        user_profile?: {
            company_name: string | null;
            region: string | null;
        } | null;
    };
    creator?: {
        id: number;
        name: string;
    } | null;
    items?: OrderItem[];
    created_at_human?: string;
    created_at_formatted?: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;
