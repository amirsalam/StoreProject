<?php

return [
    /*
    |---------------------------------------------------------------------
    | Central domain
    |---------------------------------------------------------------------
    |
    | Requests landing on this host (no subdomain, or the app/www/api
    | reserved labels) are treated as the central marketing/auth surface
    | — no tenant context is set. Everything else is parsed as
    | <slug>.<central-domain> and resolved to a Tenant.
    |
    | Set TENANCY_CENTRAL_DOMAIN in .env. Defaults to localhost so dev
    | works out of the box; tenants in dev can be reached via
    |   acme.localhost:8000
    | on most systems (no /etc/hosts entry needed for *.localhost).
    */
    'central_domain' => env('TENANCY_CENTRAL_DOMAIN', 'localhost'),

    /*
    |---------------------------------------------------------------------
    | Reserved subdomain labels
    |---------------------------------------------------------------------
    |
    | Hosts beginning with one of these labels are treated as the central
    | application, NOT a tenant. Useful so a customer can't register a
    | tenant with slug "www" and hijack the marketing site.
    */
    'reserved_subdomains' => [
        'www', 'app', 'api', 'admin', 'mail', 'smtp', 'ftp', 'cdn',
        'static', 'assets', 'help', 'support', 'docs', 'blog',
    ],

    /*
    |---------------------------------------------------------------------
    | Fallback tenant on the central domain
    |---------------------------------------------------------------------
    |
    | If set, requests landing on the central domain (no subdomain) will
    | resolve to this tenant's slug. Useful in local development where
    | the whole app runs on localhost without a per-tenant subdomain.
    |
    | In production this should usually be empty: the central domain
    | hosts marketing/auth/super-admin and should NOT silently leak into
    | a specific tenant's data.
    */
    'central_fallback_tenant' => env('TENANCY_CENTRAL_FALLBACK_TENANT'),
];
