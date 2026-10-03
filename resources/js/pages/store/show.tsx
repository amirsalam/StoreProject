import ProductCard from '@/components/product-card';
import { Badge } from '@/components/ui/badge';
import { Container } from '@/components/ui/container';
import StorefrontLayout from '@/layouts/storefront-layout';
import { type Paginated, type Product, type Vendor } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { BadgeCheck, Globe, Star } from 'lucide-react';

interface StoreShowProps {
    vendor: Vendor;
    products: Paginated<Product>;
    averageRating: number;
    reviewsCount: number;
    productsCount: number;
}

function assetUrl(path: string | null | undefined): string | null {
    if (!path) return null;
    return `/storage/${path}`;
}

export default function StoreShow({ vendor, products, averageRating, reviewsCount, productsCount }: StoreShowProps) {
    const profile = vendor.profile ?? null;
    const banner = assetUrl(profile?.banner_path);
    const logo = assetUrl(profile?.logo_path);
    const socials = profile?.social_links ? Object.entries(profile.social_links).filter(([, v]) => v) : [];

    return (
        <StorefrontLayout>
            <Head title={`${vendor.name} — Store`} />

            {/* Banner */}
            <div className="from-primary/15 via-muted to-muted/40 relative h-40 w-full bg-gradient-to-br sm:h-56">
                {banner && <img src={banner} alt="" className="size-full object-cover" />}
            </div>

            <Container className="pb-12">
                {/* Profile header */}
                <div className="-mt-10 flex flex-col gap-4 sm:-mt-12 sm:flex-row sm:items-end">
                    <div className="border-background bg-muted flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-2xl border-4 text-2xl font-semibold shadow-sm sm:size-28">
                        {logo ? (
                            <img src={logo} alt={vendor.name} className="size-full object-cover" />
                        ) : (
                            <span className="text-muted-foreground">{vendor.name.slice(0, 2).toUpperCase()}</span>
                        )}
                    </div>
                    <div className="flex-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="font-display text-2xl font-semibold tracking-tight sm:text-3xl">{vendor.name}</h1>
                            {vendor.is_verified && (
                                <Badge variant="secondary" className="gap-1">
                                    <BadgeCheck className="text-primary size-3.5" />
                                    Verified
                                </Badge>
                            )}
                        </div>
                        {profile?.company_name && profile.company_name !== vendor.name && (
                            <p className="text-muted-foreground text-sm">{profile.company_name}</p>
                        )}
                        <div className="text-muted-foreground mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
                            <span>
                                {productsCount} {productsCount === 1 ? 'product' : 'products'}
                            </span>
                            {reviewsCount > 0 && (
                                <span className="inline-flex items-center gap-1">
                                    <Star className="size-3.5 fill-amber-400 text-amber-400" />
                                    {averageRating.toFixed(1)} ({reviewsCount})
                                </span>
                            )}
                            {profile?.country && <span>{profile.country}</span>}
                            {profile?.founded_year && <span>Since {profile.founded_year}</span>}
                        </div>
                    </div>
                </div>

                {(profile?.bio || profile?.website || socials.length > 0) && (
                    <div className="mt-6 max-w-3xl space-y-3">
                        {profile?.bio && <p className="text-muted-foreground text-sm leading-relaxed">{profile.bio}</p>}
                        <div className="flex flex-wrap items-center gap-3 text-sm">
                            {profile?.website && (
                                <a
                                    href={profile.website}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-primary inline-flex items-center gap-1.5 hover:underline"
                                >
                                    <Globe className="size-4" />
                                    Website
                                </a>
                            )}
                            {socials.map(([key, url]) => (
                                <a
                                    key={key}
                                    href={url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-muted-foreground hover:text-foreground capitalize hover:underline"
                                >
                                    {key}
                                </a>
                            ))}
                        </div>
                    </div>
                )}

                {/* Catalog */}
                <h2 className="font-display mt-10 mb-4 text-lg font-semibold tracking-tight">Products</h2>
                {products.data.length === 0 ? (
                    <div className="text-muted-foreground rounded-lg border border-dashed p-12 text-center">
                        This store hasn’t published any products yet.
                    </div>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                        {products.data.map((product) => (
                            <ProductCard key={product.id} product={product} />
                        ))}
                    </div>
                )}

                {products.last_page > 1 && (
                    <nav className="mt-8 flex flex-wrap items-center justify-center gap-1">
                        {products.links.map((link, idx) =>
                            link.url ? (
                                <Link
                                    key={idx}
                                    href={link.url}
                                    preserveScroll
                                    className={`min-w-9 rounded-md border px-3 py-1.5 text-sm transition ${
                                        link.active ? 'border-primary bg-primary text-primary-foreground' : 'border-input hover:bg-accent'
                                    }`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ) : (
                                <span
                                    key={idx}
                                    className="text-muted-foreground min-w-9 rounded-md border border-transparent px-3 py-1.5 text-sm"
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ),
                        )}
                    </nav>
                )}
            </Container>
        </StorefrontLayout>
    );
}
