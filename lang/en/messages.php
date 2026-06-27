<?php

return [
    'common' => [
        'log_in' => 'Log in',
        'sign_up' => 'Sign up',
        'get_started' => 'Get started',
        'dashboard' => 'Dashboard',
        'open_dashboard' => 'Open dashboard',
        'go_to_dashboard' => 'Go to dashboard',
        'cancel' => 'Cancel',
        'save' => 'Save',
        'remove' => 'Remove',
        'loading' => 'Loading…',
        'theme' => [
            'toggle' => 'Toggle theme',
            'light' => 'Light',
            'dark' => 'Dark',
            'system' => 'System',
        ],
        'language' => [
            'select' => 'Select language',
        ],
    ],

    'nav' => [
        'products' => 'Products',
        'pricing' => 'Pricing',
        'customers' => 'Customers',
        'docs' => 'Docs',
        'open_menu' => 'Open menu',
        'close_menu' => 'Close menu',
    ],

    'cart' => [
        'aria_label' => 'Open cart (:count items)',
        'aria_label_one' => 'Open cart (1 item)',
        'title' => 'Your cart',
        'empty' => 'Your cart is empty.',
        'items_summary' => ':count items ready for checkout.',
        'item_summary' => '1 item ready for checkout.',
        'clear' => 'Clear cart',
        'clear_confirm' => 'Remove all items from your cart?',
        'remove' => 'Remove',
        'on_sale' => 'On sale',
        'each' => 'ea',
        'qty_locked' => 'Qty 1',
        'order_summary' => 'Order summary',
        'subtotal' => 'Subtotal',
        'tax' => 'Tax',
        'tax_value' => 'Calculated at checkout',
        'total' => 'Total',
        'continue_to_checkout' => 'Continue to checkout',
        'stripe_coming_soon' => 'Stripe checkout — coming soon',
        'support_help' => 'Need help? Email :email — we reply within an hour during business days.',
        'empty_state_title' => 'Your cart is empty',
        'empty_state_body' => 'Add a script, template, or license to get started. You can mix subscriptions and one-time products in the same order.',
        'browse_products' => 'Browse products',
        'increase_qty' => 'Increase quantity',
        'decrease_qty' => 'Decrease quantity',
        'secure_checkout' => 'Secure checkout — powered by Stripe',
    ],

    'checkout' => [
        'title' => 'Checkout',
        'subtitle' => 'Review your order and complete your purchase.',
        'billing_details' => 'Billing details',
        'full_name' => 'Full name',
        'email' => 'Email',
        'country' => 'Country (2-letter code)',
        'coupon' => 'Coupon code',
        'coupon_placeholder' => 'Have a code?',
        'order_summary' => 'Order summary',
        'qty' => 'Qty',
        'subtotal' => 'Subtotal',
        'discount' => 'Discount',
        'total' => 'Total',
        'place_order' => 'Place order',
        'placing' => 'Placing order…',
        'continue_to_payment' => 'Continue to payment',
        'payment_details' => 'Payment details',
        'pay_now' => 'Pay :amount',
        'processing' => 'Processing…',
        'payment_error' => 'We could not process your payment. Please try again.',
        'payments_unavailable' => 'Payments are not available right now. Please try again later.',
        'secure_note' => 'Payments are processed securely by Stripe.',
        'back_to_cart' => 'Back to cart',
        'confirm_title' => 'Order confirmed',
        'confirm_thank_you' => 'Thank you! Your order has been placed.',
        'order_number' => 'Order number',
        'status' => 'Status',
        'pending_note' => 'We’re awaiting payment confirmation. Your licenses and downloads will appear in your dashboard once payment clears.',
        'paid_note' => 'Payment received. Your purchases are ready in your dashboard.',
        'view_dashboard' => 'Go to dashboard',
        'continue_shopping' => 'Continue shopping',
    ],

    'product' => [
        'add_to_cart' => 'Add to cart',
        'adding' => 'Adding…',
        'added' => 'Added to cart',
        'secure_checkout' => 'Secure checkout via Stripe',
        'reviews' => 'Reviews',
        'no_reviews' => 'No reviews yet.',
        'verified_purchase' => 'Verified purchase',
        'related' => 'Related products',
        'type' => 'Type',
        'version' => 'Version',
        'sales' => 'Sales',
        'license_label' => 'License: :type',
        'types' => [
            'digital_download' => 'Digital download',
            'subscription' => 'Subscription',
            'api_access' => 'API access',
            'license' => 'License',
        ],
    ],

    'hero' => [
        'eyebrow_beta' => 'Now in public beta',
        'eyebrow_release' => 'v1.0 ships today',
        'title_lead' => 'The marketplace built for',
        'title_highlight' => 'makers who ship.',
        'subtitle' => 'Sell Laravel scripts, SaaS products, APIs, templates, and licenses from one polished storefront. Secure delivery, license keys, subscriptions, and payouts — built in.',
        'cta_primary' => 'Start selling free',
        'cta_secondary' => 'Browse marketplace',
        'trust_no_card' => 'No credit card required',
        'trust_payments' => 'Stripe & Paddle ready',
        'trust_uptime' => '99.99% uptime',
        'preview_revenue' => 'Revenue this month',
    ],

    'logo_cloud' => [
        'tagline' => 'Trusted by makers shipping on',
    ],

    'features' => [
        'eyebrow' => 'Everything you need',
        'title' => 'A complete storefront — without the spreadsheet',
        'description' => 'Skip the cobbled-together SaaS. Manage products, licenses, subscriptions, payouts, and customers from one polished admin.',
        'items' => [
            'licenses' => [
                'title' => 'License management',
                'body' => 'Generate, activate, and revoke license keys with per-product activation limits, domain binding, and expiration windows.',
            ],
            'downloads' => [
                'title' => 'Secure downloads',
                'body' => 'Signed URLs, download caps, and S3-compatible storage with per-customer audit trail. No leaked files.',
            ],
            'billing' => [
                'title' => 'Subscriptions & one-time',
                'body' => 'Stripe and Paddle ready. Mix lifetime, subscription, and API-credit billing on the same storefront.',
            ],
            'analytics' => [
                'title' => 'Real-time analytics',
                'body' => 'Revenue, churn, MRR, refunds, top products. Filters down to the cohort. Exports to CSV.',
            ],
            'security' => [
                'title' => 'Built-in security',
                'body' => 'Rate limiting, signed URLs, 2FA for admins, and CSRF — Laravel best practices, on by default.',
            ],
            'seo' => [
                'title' => 'SEO out of the box',
                'body' => 'Per-product meta tags, sitemaps, Open Graph, structured data. Rank without plugins.',
            ],
        ],
    ],

    'product_types' => [
        'eyebrow' => 'Sell anything digital',
        'title' => 'One platform. Every product type.',
        'description' => 'Mix and match. Sell a script as a one-time download, the API as a subscription, and the source code as a developer license — all from the same dashboard.',
        'items' => [
            'downloads' => [
                'label' => 'Digital downloads',
                'desc' => 'ZIPs, PDFs, source code. Signed URLs, version control, customer download history.',
            ],
            'subscriptions' => [
                'label' => 'Subscriptions',
                'desc' => 'Monthly and annual plans with trials, dunning, proration, and Stripe-billed invoices.',
            ],
            'api' => [
                'label' => 'API access',
                'desc' => 'Issue API keys, rate-limit by plan, and bill credits or usage. Webhook events out of the box.',
            ],
            'licenses' => [
                'label' => 'License keys',
                'desc' => 'Single-site, unlimited, or developer licenses. Activate from your script with one request.',
            ],
        ],
    ],

    'pricing' => [
        'eyebrow' => 'Simple pricing',
        'title' => 'Pay for growth, not for software.',
        'description' => 'Start free, then upgrade when you hit your first dollar. No per-product or per-seat surprises.',
        'most_popular' => 'Most popular',
        'footnote' => 'Plus Stripe/Paddle fees. No platform commission on Pro and Scale.',
        'plans' => [
            'starter' => [
                'name' => 'Starter',
                'price' => '$0',
                'cadence' => '/forever',
                'description' => 'Get your storefront live this weekend.',
                'cta' => 'Start free',
                'features' => [
                    'Up to 10 products',
                    'Stripe checkout',
                    '1 GB secure storage',
                    'Email support',
                ],
            ],
            'pro' => [
                'name' => 'Pro',
                'price' => '$29',
                'cadence' => '/month',
                'description' => 'Everything you need to scale to your first 1,000 customers.',
                'cta' => 'Start 14-day trial',
                'features' => [
                    'Unlimited products',
                    'License keys & subscriptions',
                    '100 GB secure storage',
                    'Custom domain',
                    'Analytics & exports',
                    'Priority support',
                ],
            ],
            'scale' => [
                'name' => 'Scale',
                'price' => 'Custom',
                'cadence' => '',
                'description' => 'For teams shipping at volume with SLAs.',
                'cta' => 'Talk to sales',
                'features' => [
                    'Everything in Pro',
                    'Dedicated S3 region',
                    'SAML SSO + audit logs',
                    'Custom invoicing',
                    '99.99% SLA',
                ],
            ],
        ],
    ],

    'testimonials' => [
        'eyebrow' => 'Loved by builders',
        'title' => 'Stories from the shipping room',
        'description' => 'Teams using StoreProject to sell scripts, APIs, and SaaS products to customers around the world.',
        'items' => [
            [
                'quote' => 'We migrated three Gumroad products to StoreProject in a weekend. License activation that used to be a Google Sheet is now a single API call.',
                'name' => 'Maya Rodriguez',
                'role' => 'Founder, Stripeline',
            ],
            [
                'quote' => 'The admin is exactly what I’d build if I had three more months. We replaced a Notion + Zapier setup and finally have real analytics.',
                'name' => 'Jonas Vetter',
                'role' => 'CTO, Sailwave',
            ],
            [
                'quote' => 'Subscriptions, downloads, and API credits in one place. The hairline-perfect UI doesn’t hurt either.',
                'name' => 'Aiko Tanaka',
                'role' => 'Indie dev, ToolBelt',
            ],
        ],
    ],

    'faq' => [
        'eyebrow' => 'Frequently asked',
        'title' => 'Answers, no marketing fluff.',
        'description' => 'If you don’t find what you need, the team replies in under an hour during business days.',
        'items' => [
            [
                'q' => 'Can I migrate from Gumroad / Lemonsqueezy?',
                'a' => 'Yes. Import your products and customer list via CSV; license keys can be backfilled with the migration command. We also keep your old slugs alive with permanent redirects.',
            ],
            [
                'q' => 'Do I own my customer data?',
                'a' => 'Always. Every customer record, license, and download log lives in your database. Export at any time as CSV or JSON.',
            ],
            [
                'q' => 'Which payment providers do you support?',
                'a' => 'Stripe and Paddle are first-class. Add additional gateways via the Payments service layer — it ships as a clean interface.',
            ],
            [
                'q' => 'How does license validation work?',
                'a' => 'POST your key to the /licenses/validate endpoint. We return activation state, expiration, and bound domain. Plug it into your script in under five minutes.',
            ],
            [
                'q' => 'Is there a self-hosted version?',
                'a' => 'StoreProject is open-core. Self-host the full stack on any VPS, or use our managed hosting on the Pro and Scale plans.',
            ],
        ],
    ],

    'cta_strip' => [
        'title' => 'Ship your storefront this weekend.',
        'body' => 'Spin up a fully-featured marketplace in minutes. The hard parts — licenses, downloads, billing — are already done.',
        'primary' => 'Start selling free',
        'secondary' => 'See live storefront',
    ],

    'footer' => [
        'tagline' => 'The single-vendor marketplace for Laravel scripts, APIs, templates, and SaaS — built for makers who ship.',
        'status_ok' => 'All systems operational',
        'copy' => '© :year StoreProject. All rights reserved.',
        'columns' => [
            'product' => [
                'title' => 'Product',
                'browse' => 'Browse',
                'pricing' => 'Pricing',
                'changelog' => 'Changelog',
                'roadmap' => 'Roadmap',
            ],
            'resources' => [
                'title' => 'Resources',
                'docs' => 'Docs',
                'guides' => 'Guides',
                'api' => 'API',
                'status' => 'Status',
            ],
            'company' => [
                'title' => 'Company',
                'about' => 'About',
                'blog' => 'Blog',
                'customers' => 'Customers',
                'contact' => 'Contact',
            ],
            'legal' => [
                'title' => 'Legal',
                'terms' => 'Terms',
                'privacy' => 'Privacy',
                'license' => 'License',
                'refunds' => 'Refunds',
            ],
        ],
    ],
];
