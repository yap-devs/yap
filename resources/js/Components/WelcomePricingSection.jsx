import {Link} from '@inertiajs/react';
import {formatBytes} from '@/Utils/formatBytes.js';
import {formatPrice} from '@/Utils/formatPrice.js';
import {trans} from '@/Utils/i18n';

const paygActionClasses = 'inline-flex items-center justify-center gap-2 rounded-xl bg-white px-4 py-3 text-sm font-semibold text-yap-foreground shadow-sm transition hover:-translate-y-0.5 hover:bg-yap-muted hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-yap-ring focus:ring-offset-2 focus:ring-offset-yap-muted motion-reduce:transition-none motion-reduce:hover:translate-y-0';
const secondaryActionClasses = 'inline-flex items-center justify-center gap-2 rounded-xl border border-yap-border bg-white px-4 py-3 text-sm font-semibold text-yap-foreground shadow-sm transition hover:-translate-y-0.5 hover:border-yap-accent/30 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-yap-ring focus:ring-offset-2 focus:ring-offset-yap-muted motion-reduce:transition-none motion-reduce:hover:translate-y-0';
const pricingLinkClasses = 'inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-yap-accent bg-white px-4 py-2.5 text-sm font-semibold text-yap-accent shadow-sm transition hover:-translate-y-0.5 hover:bg-yap-accent hover:text-white hover:shadow-md focus:outline-none focus:ring-2 focus:ring-yap-ring focus:ring-offset-2 focus:ring-offset-yap-muted motion-reduce:transition-none motion-reduce:hover:translate-y-0';

function ArrowIcon() {
  return (
    <svg className="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
      <path
        fillRule="evenodd"
        d="M3 10a.75.75 0 01.75-.75h10.69l-3.22-3.22a.75.75 0 111.06-1.06l4.5 4.5a.75.75 0 010 1.06l-4.5 4.5a.75.75 0 11-1.06-1.06l3.22-3.22H3.75A.75.75 0 013 10z"
        clipRule="evenodd"
      />
    </svg>
  );
}

function PricingBadge({children}) {
  return (
    <div className="inline-flex items-center gap-3 rounded-full border border-yap-accent/30 bg-yap-accent/5 px-5 py-2">
      <span className="h-2 w-2 rounded-full bg-yap-accent" />
      <span className="font-mono text-xs text-yap-accent">{children}</span>
    </div>
  );
}

function getDiscount(packageItem) {
  const originalPrice = Number(packageItem.original_price);
  const price = Number(packageItem.price);

  if (!originalPrice || originalPrice <= price) {
    return null;
  }

  return Math.round(((originalPrice - price) / originalPrice) * 100);
}

export default function WelcomePricingSection({
  auth,
  canLogin,
  canRegister,
  packages = [],
  unitPrice,
}) {
  const purchaseHref = auth?.user
    ? route('package')
    : canRegister
      ? route('register')
      : canLogin
        ? route('login')
        : '#pricing';
  const purchaseLabel = auth?.user
    ? trans('welcome.pricing_view_packages')
    : trans('welcome.create_free_account');

  return (
    <section id="pricing" className="border-y border-yap-border bg-yap-muted/60 px-4 py-24 sm:px-6 sm:py-28 lg:px-8">
      <div className="mx-auto max-w-6xl">
        <div className="grid gap-8 lg:grid-cols-[0.8fr_1.2fr] lg:items-end">
          <div>
            <PricingBadge>{trans('welcome.pricing_label')}</PricingBadge>
            <h2 className="mt-6 font-display text-4xl leading-tight text-yap-foreground sm:text-5xl">
              {trans('welcome.pricing_title')}
            </h2>
          </div>
          <p className="text-base leading-8 text-yap-muted-foreground lg:max-w-xl">
            {trans('welcome.pricing_body')}
          </p>
        </div>

        <div className="mt-12 grid gap-5 lg:grid-cols-[0.72fr_1.28fr] lg:items-start">
          <article className="rounded-[1.5rem] border border-yap-foreground bg-yap-foreground p-7 text-white shadow-xl shadow-slate-900/15">
            <p className="font-mono text-xs uppercase tracking-[0.14em] text-white/55">
              {trans('welcome.pricing_payg_label')}
            </p>
            <p className="mt-5 font-display text-4xl leading-none sm:text-5xl">
              {formatPrice(unitPrice)}
              <span className="ml-2 font-sans text-base font-semibold text-white/65">/ GB</span>
            </p>
            <h3 className="mt-6 text-xl font-semibold">{trans('welcome.pricing_payg_title')}</h3>
            <p className="mt-3 text-sm leading-7 text-white/70">{trans('welcome.pricing_payg_body')}</p>
            <div className="mt-7 border-t border-white/15 pt-5">
              <p className="text-xs leading-6 text-white/60">{trans('welcome.pricing_payg_note')}</p>
              <Link href={purchaseHref} className={`${paygActionClasses} mt-6`}>
                {purchaseLabel}
                <ArrowIcon />
              </Link>
            </div>
          </article>

          <div>
            <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
              <div>
                <p className="font-mono text-xs uppercase tracking-[0.14em] text-yap-accent">
                  {trans('welcome.pricing_packages_label')}
                </p>
                <h3 className="mt-2 text-2xl font-semibold tracking-tight text-yap-foreground">
                  {trans('welcome.pricing_packages_title')}
                </h3>
              </div>
              <Link href={purchaseHref} className={pricingLinkClasses}>
                {trans('welcome.pricing_view_packages')}
                <ArrowIcon />
              </Link>
            </div>

            {packages.length === 0 ? (
              <div className="mt-5 rounded-[1.5rem] border border-dashed border-yap-border bg-white p-7 text-sm leading-7 text-yap-muted-foreground">
                {trans('welcome.pricing_no_packages')}
              </div>
            ) : (
              <div className="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                {packages.map((packageItem) => {
                  const discount = getDiscount(packageItem);

                  return (
                    <article key={packageItem.id} className="flex h-full flex-col rounded-[1.5rem] border border-yap-border bg-white p-6 shadow-lg shadow-slate-900/5 transition duration-300 hover:-translate-y-1 hover:border-yap-accent/30 hover:shadow-xl motion-reduce:transition-none motion-reduce:hover:translate-y-0">
                      <div className="flex items-start justify-between gap-3">
                        <div>
                          <p className="text-xs font-semibold uppercase tracking-[0.12em] text-yap-muted-foreground">
                            {formatBytes(packageItem.traffic_limit)}
                          </p>
                          <h4 className="mt-3 text-xl font-semibold tracking-tight text-yap-foreground">{packageItem.name}</h4>
                        </div>
                        {discount !== null && (
                          <span className="shrink-0 rounded-full bg-red-50 px-2.5 py-1 text-xs font-bold text-red-600">
                            {trans('welcome.pricing_discount', {percent: discount})}
                          </span>
                        )}
                      </div>

                      <p className="mt-4 min-h-14 text-sm leading-6 text-yap-muted-foreground">
                        {packageItem.description || trans('welcome.pricing_package_default_body')}
                      </p>

                      <div className="mt-6 flex items-end gap-2">
                        <span className="font-display text-3xl leading-none text-yap-accent">{formatPrice(packageItem.price)}</span>
                        {discount !== null && (
                          <span className="pb-0.5 text-sm text-yap-muted-foreground line-through">
                            {formatPrice(packageItem.original_price)}
                          </span>
                        )}
                      </div>

                      <p className="mt-3 text-xs font-medium text-yap-muted-foreground">
                        {packageItem.duration_days > 0
                          ? trans('welcome.pricing_package_days', {days: packageItem.duration_days})
                          : trans('welcome.pricing_package_unlimited')}
                      </p>

                      <Link href={purchaseHref} className={`${secondaryActionClasses} mt-6 w-full`}>
                        {purchaseLabel}
                        <ArrowIcon />
                      </Link>
                    </article>
                  );
                })}
              </div>
            )}
          </div>
        </div>
      </div>
    </section>
  );
}
