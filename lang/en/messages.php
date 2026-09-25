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
        'sold_by' => 'Sold by',
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

    'blog' => [
        'meta_title' => 'Blog',
        'title' => 'From the workshop',
        'subtitle' => 'Product updates, engineering notes, and guides for selling digital products.',
        'search_placeholder' => 'Search posts…',
        'search_submit' => 'Search',
        'tags_label' => 'Filter by tag',
        'all_tags' => 'All',
        'empty' => 'No posts yet. Check back soon.',
        'read_more' => 'Read more',
        'back_to_index' => 'All posts',
        'reading_time' => ':minutes min read',
        'related' => 'Related posts',
    ],

    'about' => [
        'meta_title' => 'About us',
        'eyebrow' => 'About us',
        'title' => 'A marketplace built by makers, for makers.',
        'lead' => 'StoreProject is the one-stop marketplace for Laravel scripts, APIs, templates, and SaaS — where independent developers sell their work and buyers get it instantly.',
        'mission' => [
            'title' => 'Why we exist',
            'body_1' => 'Selling software should be about the software. Yet every maker ends up rebuilding the same plumbing: payments, license keys, secure downloads, subscriptions, and invoices.',
            'body_2' => 'We built that plumbing once, properly, so creators can focus on shipping great products and buyers can trust what they get — from checkout to delivery.',
        ],
        'values' => [
            'title' => 'What we stand for',
            'description' => 'A few principles guide every decision we make.',
            'items' => [
                'makers' => [
                    'title' => 'Makers first',
                    'body' => 'Tools designed around how independent developers actually build, price, and ship their products.',
                ],
                'commerce' => [
                    'title' => 'Commerce done right',
                    'body' => 'Secure payments, instant license and download delivery after checkout, and a clear record of every order.',
                ],
                'vendors' => [
                    'title' => 'Your store, your brand',
                    'body' => 'Every vendor gets their own storefront with a profile, logo, and product catalog.',
                ],
                'global' => [
                    'title' => 'Open to everyone',
                    'body' => 'Available in English, French, Spanish, and Arabic — with full right-to-left support.',
                ],
            ],
        ],
        'cta' => [
            'title' => 'Ready to get started?',
            'body' => 'Browse the catalog, or open your own store and start selling today.',
            'browse' => 'Browse products',
            'sell' => 'Start selling',
        ],
    ],

    'legal' => [
        'draft_banner' => [
            'title' => 'Unreviewed draft — not binding',
            'body' => 'This page is an outline of the sections these terms need. It is not legal advice and has not been reviewed by a lawyer. Replace every section with your own wording before launch.',
        ],
        'status_label' => 'Draft · not yet published',
        'toc' => 'On this page',
        'privacy' => [
            'meta_title' => 'Privacy policy',
            'title' => 'Privacy policy',
            'lead' => 'What personal data this marketplace holds, why, and what you can ask us to do with it.',
            'sections' => [
                [
                    'id' => 'controller',
                    'title' => 'Who is responsible',
                    'body' => 'Drafting note: name the legal entity that decides how this data is used, its address, and a privacy contact — plus a data protection officer or EU/UK representative if you need one.',
                ],
                [
                    'id' => 'account_data',
                    'title' => 'Account data you give us',
                    'body' => 'What the code stores today: your name, email address and a hashed password. Confirm this list against your final build before publishing.',
                ],
                [
                    'id' => 'billing_data',
                    'title' => 'Order and billing data',
                    'body' => 'What the code stores today: order totals and currency, billing name, billing email, billing country and billing address, plus the payment method label.',
                ],
                [
                    'id' => 'payments',
                    'title' => 'Payments',
                    'body' => 'Card details are entered in the payment provider’s own element and are not stored by this application; what is stored is the gateway name, its payment and customer identifiers, and the gateway’s response. Drafting note: name your provider and link its privacy notice.',
                ],
                [
                    'id' => 'messages',
                    'title' => 'Messages you send us',
                    'body' => 'The contact form stores the name, email address, subject and message you submit, linked to your account when you are signed in.',
                ],
                [
                    'id' => 'technical_data',
                    'title' => 'Data collected automatically',
                    'body' => 'What the code stores today: an IP address and browser user-agent against each login session and each security event in the activity log, so you can review and revoke your own sessions.',
                ],
                [
                    'id' => 'social_login',
                    'title' => 'Signing in with another provider',
                    'body' => 'If you sign in through an external provider, the code stores the provider name, its account identifier, the email address and avatar it returns, and the raw profile payload. Drafting note: list the providers you actually enable.',
                ],
                [
                    'id' => 'cookies',
                    'title' => 'Cookies',
                    'body' => 'What the code sets today: a session cookie to keep you signed in, and a language cookie remembering your locale. Drafting note: if you add analytics or advertising cookies, list them here and add a consent banner.',
                ],
                [
                    'id' => 'purposes',
                    'title' => 'Why we use it, and on what basis',
                    'body' => 'Drafting note: map each category above to a purpose (running your account, fulfilling orders, fraud prevention, support) and, where GDPR or similar law applies, to a lawful basis.',
                ],
                [
                    'id' => 'sharing',
                    'title' => 'Who we share it with',
                    'body' => 'Drafting note: list the processors you actually use — payment provider, email delivery, hosting, error tracking — and say what vendors on the marketplace can see about buyers of their products.',
                ],
                [
                    'id' => 'retention',
                    'title' => 'How long we keep it',
                    'body' => 'Drafting note: set a retention period per category. Note that orders and invoices usually have a statutory minimum, while sessions, activity logs and contact messages should have a defined maximum.',
                ],
                [
                    'id' => 'rights',
                    'title' => 'Your rights',
                    'body' => 'Drafting note: describe access, correction, deletion, portability, objection and complaint rights for your jurisdiction, and how someone exercises them — the contact form is the route today.',
                ],
                [
                    'id' => 'transfers',
                    'title' => 'International transfers',
                    'body' => 'Drafting note: say where the data is hosted and which safeguards cover transfers out of your users’ region.',
                ],
                [
                    'id' => 'security',
                    'title' => 'How it is protected',
                    'body' => 'What the code does today: passwords are hashed, payment gateway credentials are stored encrypted, and each tenant’s records are scoped so one tenant cannot read another’s. Drafting note: add your organisational measures.',
                ],
                [
                    'id' => 'children',
                    'title' => 'Children',
                    'body' => 'Drafting note: state the minimum age for an account and what you do if you learn a child has registered.',
                ],
                [
                    'id' => 'changes',
                    'title' => 'Changes to this notice',
                    'body' => 'Drafting note: say how changes are announced and keep a visible last-updated date once this is a real notice.',
                ],
            ],
            'contact' => [
                'title' => 'Questions about your data?',
                'body' => 'Send a message and we will get back to you by email.',
                'action' => 'Contact us',
            ],
        ],
        'license' => [
            'meta_title' => 'License terms',
            'title' => 'License terms',
            'lead' => 'What you may do with the products you buy here, and what stays with their authors.',
            'sections' => [
                [
                    'id' => 'grant',
                    'title' => 'What a purchase gives you',
                    'body' => 'Drafting note: state that buying a product grants a licence to use it, not ownership, and that the vendor keeps the copyright.',
                ],
                [
                    'id' => 'license_types',
                    'title' => 'License types',
                    'body' => 'What the code does today: each product can carry a license-type label that its vendor chooses, shown on the product page. Drafting note: either define exactly what each label permits, or replace free-text labels with a fixed set you define here.',
                ],
                [
                    'id' => 'license_keys',
                    'title' => 'License keys',
                    'body' => 'What the code does today: buying a license or API-access product issues a unique key, an activation limit set per product, and a status of active, expired or revoked.',
                ],
                [
                    'id' => 'activations',
                    'title' => 'Activations',
                    'body' => 'Drafting note: define what counts as one activation (a domain, an installation, a machine) and how a buyer frees one up. Confirm activation limits are actually enforced before promising it here.',
                ],
                [
                    'id' => 'downloads',
                    'title' => 'Downloads',
                    'body' => 'What the code does today: buying a downloadable product creates a download grant, which can carry a maximum number of downloads set per product. Drafting note: say how long downloads stay available.',
                ],
                [
                    'id' => 'permitted_use',
                    'title' => 'What you may do',
                    'body' => 'Drafting note: list permitted uses for each license type — personal or commercial projects, client work, modification, number of end products.',
                ],
                [
                    'id' => 'restrictions',
                    'title' => 'What you may not do',
                    'body' => 'Drafting note: cover reselling or redistributing the product, sharing license keys, publishing the source, and using it in a competing marketplace item.',
                ],
                [
                    'id' => 'updates_support',
                    'title' => 'Updates and support',
                    'body' => 'Drafting note: say whether updates and support are included, for how long, and who provides them — the marketplace or the vendor.',
                ],
                [
                    'id' => 'revocation',
                    'title' => 'Revocation and expiry',
                    'body' => 'What the code does today: a license can be revoked, with the time recorded, or expire on a set date. Drafting note: list the grounds for revocation (refund, chargeback, breach) and what happens to copies already deployed.',
                ],
                [
                    'id' => 'vendor_terms',
                    'title' => 'Vendor-specific terms',
                    'body' => 'Drafting note: say whether vendors may attach their own licence to a product and which terms win if they conflict with these.',
                ],
                [
                    'id' => 'third_party',
                    'title' => 'Third-party components',
                    'body' => 'Drafting note: state that bundled open-source or third-party components keep their own licences, and require vendors to disclose them.',
                ],
                [
                    'id' => 'changes',
                    'title' => 'Changes to these terms',
                    'body' => 'Drafting note: say whether changes apply to purchases already made, and keep a visible last-updated date once this is real.',
                ],
            ],
            'contact' => [
                'title' => 'Questions about licensing?',
                'body' => 'Send a message and we will get back to you by email.',
                'action' => 'Contact us',
            ],
        ],
        'refunds' => [
            'meta_title' => 'Refund policy',
            'title' => 'Refund policy',
            'lead' => 'When you can get your money back for a purchase, and what happens to your access when you do.',
            'sections' => [
                [
                    'id' => 'eligibility',
                    'title' => 'Who can get a refund',
                    'body' => 'Drafting note: say which purchases can be refunded and within how many days. Decide whether digital products that were already downloaded or activated are excluded, and check the statutory withdrawal rights that apply where your buyers live.',
                ],
                [
                    'id' => 'how_to_request',
                    'title' => 'How to ask for a refund',
                    'body' => 'What the code does today: there is no refund button or request form — a refund is issued by the marketplace through the payment provider (Stripe). Drafting note: tell buyers how to ask (for example through the contact form, with their order number) and how quickly you answer.',
                ],
                [
                    'id' => 'processing',
                    'title' => 'How a refund is processed',
                    'body' => 'What the code does today: once the payment provider confirms a refund, the payment and its order are marked as refunded and a matching entry is recorded in the account ledger. Drafting note: say how long the money takes to reach the original payment method.',
                ],
                [
                    'id' => 'partial_refunds',
                    'title' => 'Partial refunds',
                    'body' => 'What the code does today: any refund, even for part of the amount, marks the whole order as refunded. Drafting note: decide whether you offer partial refunds, and make the order record reflect them before promising it here.',
                ],
                [
                    'id' => 'access_after_refund',
                    'title' => 'Licenses and downloads after a refund',
                    'body' => 'What the code does today: a refund does not revoke the order’s license keys or download access — they stay active. Drafting note: decide whether a refund ends access; if it does, add automatic revocation before stating it here.',
                ],
                [
                    'id' => 'subscriptions',
                    'title' => 'Subscriptions',
                    'body' => 'Drafting note: say whether cancelling a subscription is refunded for the unused time or simply runs to the end of the paid period.',
                ],
                [
                    'id' => 'vendor_products',
                    'title' => 'Products sold by vendors',
                    'body' => 'Drafting note: say who decides refunds on products sold by other vendors — the marketplace or the vendor — and whether the vendor’s share is taken back.',
                ],
                [
                    'id' => 'chargebacks',
                    'title' => 'Chargebacks and abuse',
                    'body' => 'Drafting note: explain what happens after a card chargeback, and whether repeated refund requests can lead to restrictions on an account.',
                ],
                [
                    'id' => 'changes',
                    'title' => 'Changes to this policy',
                    'body' => 'Drafting note: say whether changes apply to purchases already made, and keep a visible last-updated date once this is real.',
                ],
            ],
            'contact' => [
                'title' => 'Need a refund?',
                'body' => 'Send a message with your order number and we will get back to you by email.',
                'action' => 'Contact us',
            ],
        ],
        'terms' => [
            'meta_title' => 'Terms of service',
            'title' => 'Terms of service',
            'lead' => 'The agreement between the marketplace and the people who buy and sell on it.',
            'sections' => [
                [
                    'id' => 'acceptance',
                    'title' => 'Acceptance of the terms',
                    'body' => 'Drafting note: state that using the marketplace means accepting these terms, who they apply to, and the minimum age to hold an account.',
                ],
                [
                    'id' => 'operator',
                    'title' => 'Who operates the marketplace',
                    'body' => 'Drafting note: name the legal entity behind the marketplace, its registered address, its company number, and how to reach it.',
                ],
                [
                    'id' => 'accounts',
                    'title' => 'Accounts',
                    'body' => 'Drafting note: cover registration, accurate details, keeping credentials safe, responsibility for activity on the account, and when an account may be suspended.',
                ],
                [
                    'id' => 'purchases',
                    'title' => 'Orders and payments',
                    'body' => 'Drafting note: cover how an order is formed, currency and taxes, the payment providers used, failed payments, and what a receipt or invoice represents.',
                ],
                [
                    'id' => 'licenses',
                    'title' => 'Licenses and downloads',
                    'body' => 'Drafting note: describe what a buyer receives for each product type — license keys, downloads, API access, subscriptions — including activation limits, download limits and renewal.',
                ],
                [
                    'id' => 'refunds',
                    'title' => 'Refunds and cancellations',
                    'body' => 'Drafting note: state the refund window and conditions for digital goods, how to request one, and any statutory withdrawal rights that apply to your buyers.',
                ],
                [
                    'id' => 'vendors',
                    'title' => 'Selling on the marketplace',
                    'body' => 'Drafting note: cover what vendors may list, the warranties they give about their own work, commission and payout terms, and grounds for removing a listing or a store.',
                ],
                [
                    'id' => 'acceptable_use',
                    'title' => 'Acceptable use',
                    'body' => 'Drafting note: prohibit malware, infringing or illegal content, scraping, abuse of the API, and attempts to bypass licensing or payment.',
                ],
                [
                    'id' => 'intellectual_property',
                    'title' => 'Intellectual property',
                    'body' => 'Drafting note: separate the marketplace’s own brand and software from the content vendors upload, and state the licence each party grants the other.',
                ],
                [
                    'id' => 'liability',
                    'title' => 'Warranties and liability',
                    'body' => 'Drafting note: this section carries the most legal weight — have a lawyer write the disclaimers, liability cap and indemnity to suit your jurisdiction.',
                ],
                [
                    'id' => 'termination',
                    'title' => 'Suspension and termination',
                    'body' => 'Drafting note: explain how either side ends the relationship, what happens to purchased licenses and downloads afterwards, and which clauses survive.',
                ],
                [
                    'id' => 'law',
                    'title' => 'Governing law and disputes',
                    'body' => 'Drafting note: name the governing law and the courts or arbitration process that settles disputes.',
                ],
                [
                    'id' => 'changes',
                    'title' => 'Changes to these terms',
                    'body' => 'Drafting note: say how changes are announced, how much notice is given, and what continued use means after a change.',
                ],
            ],
            'contact' => [
                'title' => 'Questions about these terms?',
                'body' => 'Send a message and we will get back to you by email.',
                'action' => 'Contact us',
            ],
        ],
    ],

    'contact' => [
        'meta_title' => 'Contact us',
        'eyebrow' => 'Contact us',
        'title' => 'Tell us what you need.',
        'lead' => 'Questions about selling, billing, or a product you bought — send a message and we will get back to you by email.',
        'form' => [
            'name' => 'Your name',
            'email' => 'Email address',
            'subject' => 'Subject',
            'message' => 'Message',
            'submit' => 'Send message',
            'privacy_note' => 'We only use your address to reply.',
            'sent' => 'Thanks — your message has been sent. We will reply by email.',
        ],
    ],

    'customers' => [
        'meta_title' => 'Customers',
        'eyebrow' => 'Customers',
        'title' => 'Built for the people shipping the work.',
        'lead' => 'Independent developers and small teams use StoreProject to sell scripts, APIs, templates, and SaaS — without rebuilding checkout, licensing, and delivery first.',
        'use_cases' => [
            'title' => 'What people sell here',
            'description' => 'The same storefront handles a one-off download, a metered API, and a monthly plan.',
            'items' => [
                'scripts' => [
                    'title' => 'Scripts and templates',
                    'body' => 'Sell a codebase as a one-time download, with secure delivery and a record of every order.',
                ],
                'api' => [
                    'title' => 'API products',
                    'body' => 'Issue keys, limit usage by plan, and bill for access without writing the billing layer.',
                ],
                'saas' => [
                    'title' => 'SaaS and subscriptions',
                    'body' => 'Recurring plans with invoices, so renewals and cancellations are handled for you.',
                ],
            ],
        ],
        'cta' => [
            'title' => 'Want your store here?',
            'body' => 'Browse what is already selling, or read how the platform fits together.',
            'browse' => 'Browse products',
            'about' => 'About us',
        ],
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
