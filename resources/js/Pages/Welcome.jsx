import {Head, Link} from '@inertiajs/react';
import LanguageSelector from '@/Components/LanguageSelector';
import {trans} from '@/Utils/i18n';

const primaryActionClasses = 'group inline-flex h-14 items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-yap-accent to-yap-accent-secondary px-6 text-sm font-semibold text-yap-accent-foreground shadow-yap-accent transition duration-200 hover:-translate-y-0.5 hover:shadow-yap-accent-lg hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-yap-ring focus:ring-offset-2 focus:ring-offset-yap-background active:scale-[0.98] motion-reduce:hover:translate-y-0 motion-reduce:transition-none';
const secondaryActionClasses = 'group inline-flex h-14 items-center justify-center gap-2 rounded-xl border border-yap-border bg-white px-6 text-sm font-semibold text-yap-foreground shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-yap-accent/30 hover:bg-yap-muted hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-yap-ring focus:ring-offset-2 focus:ring-offset-yap-background active:scale-[0.98] motion-reduce:hover:translate-y-0 motion-reduce:transition-none';
const navLinkClasses = 'text-sm font-medium text-yap-muted-foreground transition hover:text-yap-foreground focus:outline-none focus:text-yap-foreground';

function ActionLink({href, className, children}) {
  const Component = href.startsWith('#') ? 'a' : Link;

  return (
    <Component href={href} className={className}>
      {children}
    </Component>
  );
}

function ArrowIcon({className = ''}) {
  return (
    <svg className={className} viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
      <path
        fillRule="evenodd"
        d="M3 10a.75.75 0 01.75-.75h10.69l-3.22-3.22a.75.75 0 111.06-1.06l4.5 4.5a.75.75 0 010 1.06l-4.5 4.5a.75.75 0 11-1.06-1.06l3.22-3.22H3.75A.75.75 0 013 10z"
        clipRule="evenodd"
      />
    </svg>
  );
}

function CheckIcon({className = ''}) {
  return (
    <svg className={className} viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
      <path
        fillRule="evenodd"
        d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z"
        clipRule="evenodd"
      />
    </svg>
  );
}

function SectionBadge({children, dark = false}) {
  return (
    <div className={`inline-flex items-center gap-3 rounded-full border px-5 py-2 ${dark ? 'border-white/15 bg-white/10' : 'border-yap-accent/30 bg-yap-accent/5'}`}>
      <span className="h-2 w-2 rounded-full bg-yap-accent motion-safe:animate-yap-pulse" />
      <span className={`font-mono text-xs ${dark ? 'text-white' : 'text-yap-accent'}`}>
        {children}
      </span>
    </div>
  );
}

function getHighlightParts(text) {
  const cleanText = text.trim();
  const punctuationIndex = cleanText.search(/[，、：；]/u);

  if (punctuationIndex > 1 && punctuationIndex < cleanText.length - 2) {
    return {
      lead: cleanText.slice(0, punctuationIndex + 1),
      separator: '',
      highlight: cleanText.slice(punctuationIndex + 1).trimStart(),
    };
  }

  const words = cleanText.split(/\s+/).filter(Boolean);

  if (words.length > 2) {
    return {
      lead: words.slice(0, -2).join(' '),
      separator: ' ',
      highlight: words.slice(-2).join(' '),
    };
  }

  return {
    lead: cleanText,
    separator: '',
    highlight: '',
  };
}

function renderHighlightedText(text) {
  const {lead, separator, highlight} = getHighlightParts(text);

  if (!highlight) {
    return lead;
  }

  return (
    <>
      {lead}{separator}
      <span className="yap-gradient-highlight">{highlight}</span>
    </>
  );
}

function DashboardPreview({rows, actions}) {
  return (
    <aside className="overflow-hidden rounded-2xl border border-yap-border bg-white shadow-xl shadow-slate-900/8" aria-label={trans('welcome.dashboard_preview_title')}>
      <div className="border-b border-yap-border bg-yap-muted/70 px-5 py-4">
        <div className="flex items-center justify-between gap-4">
          <div>
            <p className="text-xs font-medium text-yap-muted-foreground">{trans('nav.dashboard')}</p>
            <h2 className="mt-1 text-lg font-semibold text-yap-foreground">{trans('welcome.dashboard_preview_title')}</h2>
          </div>
          <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-yap-foreground text-sm font-bold text-white">Y</span>
        </div>
      </div>

      <div className="space-y-3 p-5">
        {rows.map((row, index) => (
          <div key={row.label} className={`rounded-xl border p-4 ${index === 0 ? 'border-green-200 bg-green-50' : 'border-gray-200 bg-white'}`}>
            <div className="flex items-start justify-between gap-4">
              <div>
                <p className="text-xs font-medium text-gray-500">{row.label}</p>
                <p className="mt-1 text-lg font-semibold text-gray-900">{row.value}</p>
              </div>
              <span className={`mt-1 h-2.5 w-2.5 rounded-full ${index === 0 ? 'bg-green-500' : 'bg-blue-500'}`} aria-hidden="true" />
            </div>
          </div>
        ))}
      </div>

      <div className="border-t border-yap-border bg-yap-muted/40 p-5">
        <div className="grid gap-2 sm:grid-cols-3">
          {actions.map((action) => (
            <div key={action} className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-center text-xs font-semibold text-gray-700 shadow-sm">
              {action}
            </div>
          ))}
        </div>
        <p className="mt-4 text-sm leading-6 text-yap-muted-foreground">{trans('welcome.dashboard_preview_body')}</p>
      </div>
    </aside>
  );
}

function FeatureIcon({index}) {
  const paths = [
    'M3.75 4.5h12.5a1.25 1.25 0 011.25 1.25v8.5a1.25 1.25 0 01-1.25 1.25H3.75A1.25 1.25 0 012.5 14.25v-8.5A1.25 1.25 0 013.75 4.5zm2 3.25h8.5m-8.5 3h5.5',
    'M5 9.5a5 5 0 1110 0c0 3.5-5 7-5 7s-5-3.5-5-7zm5 1.5a1.5 1.5 0 100-3 1.5 1.5 0 000 3z',
    'M4.75 5.5h10.5A1.25 1.25 0 0116.5 6.75v6.5a1.25 1.25 0 01-1.25 1.25H4.75a1.25 1.25 0 01-1.25-1.25v-6.5A1.25 1.25 0 014.75 5.5zm2.25 11h6m-3-2v2',
    'M10 3.5l5.25 2.25v3.6c0 3.3-2.1 6.2-5.25 7.15-3.15-.95-5.25-3.85-5.25-7.15v-3.6L10 3.5zm2.5 5.25l-3.1 3.1-1.4-1.4',
    'M4.5 5.5h11m-11 4h11m-11 4h7',
    'M5 11.5l3 3 7-9m-8.5 0H5.25A1.25 1.25 0 004 6.75v7.5c0 .69.56 1.25 1.25 1.25h9.5c.69 0 1.25-.56 1.25-1.25v-2.5',
  ];

  return (
    <svg className="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <path d={paths[index % paths.length]} />
    </svg>
  );
}

export default function Welcome({auth, canLogin, canRegister}) {
  const heroPoints = trans('welcome.hero_points', {}, []);
  const heroPanelSteps = trans('welcome.hero_panel_steps', {}, []);
  const trustItems = trans('welcome.trust', {}, []);
  const useCases = trans('welcome.use_cases', {}, []);
  const privacyPoints = trans('welcome.privacy_points', {}, []);
  const features = trans('welcome.features', {}, []);
  const steps = trans('welcome.steps', {}, []);
  const dashboardPreviewRows = trans('welcome.dashboard_preview_rows', {}, []);
  const dashboardPreviewActions = trans('welcome.dashboard_preview_actions', {}, []);

  const primaryHref = auth.user
    ? route('dashboard')
    : canRegister
      ? route('register')
      : canLogin
        ? route('login')
        : '#features';
  const primaryLabel = auth.user ? trans('nav.dashboard') : trans('welcome.create_free_account');

  return (
    <>
      <Head title={trans('welcome.title')} />
      <div className="min-h-screen overflow-hidden bg-yap-background font-sans text-yap-foreground antialiased">
        <div className="pointer-events-none fixed inset-x-0 top-0 -z-10 h-[680px] bg-[radial-gradient(circle_at_20%_20%,rgb(0_82_255/0.12),transparent_34%),radial-gradient(circle_at_82%_10%,rgb(77_124_255/0.1),transparent_28%)]" />

        <header className="sticky top-0 z-40 border-b border-yap-border/80 bg-yap-background/86 backdrop-blur-xl">
          <div className="mx-auto flex h-20 max-w-6xl items-center justify-between px-4 sm:px-6 lg:px-8">
            <Link href="/" className="group flex items-center gap-3 focus:outline-none">
              <span className="flex h-10 w-10 items-center justify-center rounded-2xl bg-gradient-to-br from-yap-accent to-yap-accent-secondary text-sm font-bold text-white shadow-yap-accent transition group-hover:-translate-y-0.5 group-hover:shadow-yap-accent-lg motion-reduce:transition-none motion-reduce:group-hover:translate-y-0">Y</span>
              <span className="text-base font-semibold tracking-tight text-yap-foreground">YAP</span>
            </Link>

            <nav className="hidden items-center gap-8 md:flex" aria-label="Primary navigation">
              <a href="#features" className={navLinkClasses}>{trans('welcome.learn_more')}</a>
              <a href="#privacy" className={navLinkClasses}>{trans('welcome.privacy_label')}</a>
              <a href="#how" className={navLinkClasses}>{trans('welcome.hero_panel_label')}</a>
            </nav>

            <div className="flex items-center gap-3 sm:gap-4">
              <LanguageSelector />
              {auth.user ? (
                <Link href={route('dashboard')} className="hidden rounded-xl border border-yap-border bg-white px-4 py-2 text-sm font-semibold text-yap-foreground shadow-sm transition hover:border-yap-accent/30 hover:shadow-md sm:inline-flex">
                  {trans('nav.dashboard')}
                </Link>
              ) : (
                <>
                  {canLogin && (
                    <Link href={route('login')} className="hidden text-sm font-semibold text-yap-muted-foreground transition hover:text-yap-foreground sm:inline-flex">
                      {trans('auth.login')}
                    </Link>
                  )}
                  {canRegister && (
                    <Link href={route('register')} className="hidden rounded-xl bg-yap-foreground px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg sm:inline-flex motion-reduce:hover:translate-y-0">
                      {trans('welcome.get_started')}
                    </Link>
                  )}
                </>
              )}
            </div>
          </div>
        </header>

        <main>
          <section className="relative px-4 py-20 sm:px-6 sm:py-28 lg:px-8 lg:py-32">
            <div className="absolute left-1/2 top-16 -z-10 h-80 w-80 -translate-x-1/2 rounded-full bg-yap-accent/6 blur-[120px]" />
            <div className="mx-auto grid max-w-6xl gap-14 lg:grid-cols-[1.1fr_0.9fr] lg:items-center">
              <div>
                <SectionBadge>{trans('welcome.hero_eyebrow')}</SectionBadge>
                <h1 className="mt-8 max-w-4xl text-balance font-display text-[clamp(3rem,8vw,5.25rem)] leading-[1.04] tracking-[-0.02em] text-yap-foreground">
                  {renderHighlightedText(trans('welcome.hero_title'))}
                </h1>
                <p className="mt-7 max-w-2xl text-lg leading-8 text-yap-muted-foreground sm:text-xl sm:leading-9">
                  {trans('welcome.hero_body')}
                </p>

                <div className="mt-9 flex flex-col gap-3 sm:flex-row">
                  <ActionLink href={primaryHref} className={primaryActionClasses}>
                    {primaryLabel}
                    <ArrowIcon className="h-4 w-4 transition group-hover:translate-x-1 motion-reduce:transition-none motion-reduce:group-hover:translate-x-0" />
                  </ActionLink>
                  <a href="#how" className={secondaryActionClasses}>
                    {trans('welcome.dashboard_preview_cta')}
                    <ArrowIcon className="h-4 w-4 transition group-hover:translate-x-1 motion-reduce:transition-none motion-reduce:group-hover:translate-x-0" />
                  </a>
                </div>

                <p className="mt-5 max-w-xl text-sm leading-6 text-yap-muted-foreground">{trans('welcome.hero_note')}</p>

                <div className="mt-10 grid gap-3 sm:grid-cols-2" role="list">
                  {heroPoints.map((point) => (
                    <div key={point} className="flex items-center gap-3 rounded-2xl border border-yap-border bg-white/80 p-3 text-sm font-medium text-yap-foreground shadow-sm" role="listitem">
                      <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-yap-accent/8 text-yap-accent">
                        <CheckIcon className="h-4 w-4" />
                      </span>
                      <span>{point}</span>
                    </div>
                  ))}
                </div>
              </div>

              <DashboardPreview rows={dashboardPreviewRows} actions={dashboardPreviewActions} />
            </div>
          </section>

          <section className="relative overflow-hidden bg-yap-foreground px-4 py-20 text-white sm:px-6 sm:py-24 lg:px-8">
            <div className="yap-dot-grid absolute inset-0 opacity-[0.06]" aria-hidden="true" />
            <div className="absolute -left-24 top-12 h-72 w-72 rounded-full bg-yap-accent/12 blur-[130px]" aria-hidden="true" />
            <div className="absolute -right-24 bottom-0 h-80 w-80 rounded-full bg-yap-accent-secondary/10 blur-[140px]" aria-hidden="true" />
            <div className="relative mx-auto grid max-w-6xl gap-10 lg:grid-cols-[0.95fr_1.05fr] lg:items-end">
              <div>
                <SectionBadge dark>{trans('welcome.hero_panel_label')}</SectionBadge>
                <h2 className="mt-6 max-w-xl font-display text-4xl leading-tight text-white sm:text-5xl">
                  {trans('welcome.hero_panel_title')}
                </h2>
                <p className="mt-5 max-w-2xl text-base leading-8 text-white/70">
                  {trans('welcome.hero_panel_body')}
                </p>
              </div>

              <div className="grid gap-4 sm:grid-cols-2">
                {trustItems.map((item) => (
                  <div key={item.label} className="rounded-2xl border border-white/10 bg-white/8 p-5 backdrop-blur transition duration-300 hover:-translate-y-1 hover:bg-white/12 motion-reduce:transition-none motion-reduce:hover:translate-y-0">
                    <p className="font-mono text-[0.68rem] uppercase tracking-[0.15em] text-white/45">{item.label}</p>
                    <p className="mt-3 text-lg font-semibold leading-7 text-white">{item.value}</p>
                  </div>
                ))}
              </div>
            </div>
          </section>

          <section className="px-4 py-24 sm:px-6 sm:py-32 lg:px-8">
            <div className="mx-auto max-w-6xl">
              <div className="max-w-2xl">
                <SectionBadge>{trans('welcome.use_cases_label')}</SectionBadge>
                <h2 className="mt-6 font-display text-4xl leading-tight text-yap-foreground sm:text-5xl">
                  {renderHighlightedText(trans('welcome.use_cases_title'))}
                </h2>
                <p className="mt-5 text-base leading-8 text-yap-muted-foreground">{trans('welcome.use_cases_body')}</p>
              </div>

              <div className="mt-12 grid gap-5 lg:grid-cols-3">
                {useCases.map((item, index) => (
                  <article key={item.title} className={`group relative overflow-hidden rounded-[1.5rem] border border-yap-border bg-yap-card p-7 shadow-lg shadow-slate-900/5 transition duration-300 hover:-translate-y-1 hover:shadow-xl motion-reduce:transition-none motion-reduce:hover:translate-y-0 ${index === 1 ? 'lg:translate-y-8 motion-reduce:lg:translate-y-0' : ''}`}>
                    <div className="absolute inset-0 bg-gradient-to-br from-yap-accent/[0.04] to-transparent opacity-0 transition group-hover:opacity-100" aria-hidden="true" />
                    <div className="relative flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-yap-accent to-yap-accent-secondary text-white shadow-yap-accent transition group-hover:scale-110 motion-reduce:transition-none motion-reduce:group-hover:scale-100">
                      <FeatureIcon index={index} />
                    </div>
                    <h3 className="relative mt-6 text-xl font-semibold tracking-tight text-yap-foreground">{item.title}</h3>
                    <p className="relative mt-4 text-sm leading-7 text-yap-muted-foreground">{item.desc}</p>
                  </article>
                ))}
              </div>
            </div>
          </section>

          <section id="privacy" className="relative overflow-hidden border-y border-yap-border bg-yap-muted/70 px-4 py-24 sm:px-6 sm:py-32 lg:px-8">
            <div className="absolute right-0 top-0 h-80 w-80 rounded-full bg-yap-accent/8 blur-[130px]" aria-hidden="true" />
            <div className="mx-auto grid max-w-6xl gap-10 lg:grid-cols-[0.82fr_1.18fr] lg:items-start">
              <div className="relative rounded-tl-[4rem] rounded-br-[4rem] border border-yap-border bg-white p-8 shadow-xl shadow-slate-900/6">
                <SectionBadge>{trans('welcome.privacy_label')}</SectionBadge>
                <h2 className="mt-6 font-display text-4xl leading-tight text-yap-foreground sm:text-5xl">
                  {renderHighlightedText(trans('welcome.privacy_title'))}
                </h2>
                <p className="mt-6 text-base leading-8 text-yap-muted-foreground">{trans('welcome.privacy_body')}</p>
              </div>

              <div className="grid gap-4 sm:grid-cols-2">
                {privacyPoints.map((item, index) => (
                  <article key={item.title} className="rounded-[1.35rem] border border-yap-border bg-white p-6 shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-xl motion-reduce:transition-none motion-reduce:hover:translate-y-0">
                    <div className="flex items-center gap-4">
                      <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-yap-foreground font-mono text-xs text-white">
                        0{index + 1}
                      </span>
                      <h3 className="text-base font-semibold tracking-tight text-yap-foreground">{item.title}</h3>
                    </div>
                    <p className="mt-4 text-sm leading-7 text-yap-muted-foreground">{item.desc}</p>
                  </article>
                ))}
              </div>
            </div>
          </section>

          <section id="features" className="px-4 py-24 sm:px-6 sm:py-32 lg:px-8">
            <div className="mx-auto max-w-6xl">
              <div className="grid gap-8 lg:grid-cols-[0.85fr_1.15fr] lg:items-end">
                <div>
                  <SectionBadge>{trans('welcome.learn_more')}</SectionBadge>
                  <h2 className="mt-6 font-display text-4xl leading-tight text-yap-foreground sm:text-5xl">
                    {renderHighlightedText(trans('welcome.features_title'))}
                  </h2>
                </div>
                <p className="text-base leading-8 text-yap-muted-foreground lg:max-w-xl">{trans('welcome.features_body')}</p>
              </div>

              <div className="mt-12 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                {features.map((feature, index) => {
                  const featured = index === 0;

                  return (
                    <article key={feature.title} className={featured ? 'rounded-[1.6rem] bg-gradient-to-br from-yap-accent via-yap-accent-secondary to-yap-accent p-[2px] shadow-yap-accent-lg md:col-span-2 lg:col-span-1' : 'rounded-[1.6rem] border border-yap-border bg-white shadow-lg shadow-slate-900/5'}>
                      <div className={`h-full rounded-[1.45rem] p-6 ${featured ? 'bg-white' : ''}`}>
                        <div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-yap-accent to-yap-accent-secondary text-white shadow-yap-accent">
                          <FeatureIcon index={index} />
                        </div>
                        <h3 className="mt-6 text-lg font-semibold tracking-tight text-yap-foreground">{feature.title}</h3>
                        <p className="mt-3 text-sm leading-7 text-yap-muted-foreground">{feature.desc}</p>
                      </div>
                    </article>
                  );
                })}
              </div>
            </div>
          </section>

          <section id="how" className="relative overflow-hidden border-y border-yap-border bg-white px-4 py-24 sm:px-6 sm:py-32 lg:px-8">
            <div className="yap-light-dot-grid absolute inset-y-0 left-0 w-1/2 opacity-45" aria-hidden="true" />
            <div className="relative mx-auto max-w-6xl">
              <div className="max-w-2xl">
                <SectionBadge>{trans('welcome.hero_panel_label')}</SectionBadge>
                <h2 className="mt-6 font-display text-4xl leading-tight text-yap-foreground sm:text-5xl">
                  {renderHighlightedText(trans('welcome.how_title'))}
                </h2>
                <p className="mt-5 text-base leading-8 text-yap-muted-foreground">{trans('welcome.how_body')}</p>
              </div>

              <div className="mt-12 grid gap-5 md:grid-cols-3">
                {steps.map((item, index) => (
                  <article key={item.step} className="relative rounded-[1.5rem] border border-yap-border bg-white p-7 shadow-xl shadow-slate-900/6">
                    {index < steps.length - 1 && (
                      <div className="absolute left-[calc(100%-1rem)] top-12 z-10 hidden h-8 w-8 items-center justify-center rounded-full bg-yap-accent text-white shadow-yap-accent md:flex" aria-hidden="true">
                        <ArrowIcon className="h-4 w-4" />
                      </div>
                    )}
                    <p className="font-mono text-4xl text-yap-accent/20">0{item.step}</p>
                    <h3 className="mt-5 text-xl font-semibold tracking-tight text-yap-foreground">{item.title}</h3>
                    <p className="mt-4 text-sm leading-7 text-yap-muted-foreground">{item.desc}</p>
                  </article>
                ))}
              </div>
            </div>
          </section>

          <section className="px-4 py-24 sm:px-6 sm:py-32 lg:px-8">
            <div className="relative mx-auto max-w-6xl overflow-hidden rounded-[2rem] bg-yap-foreground p-8 text-white shadow-2xl shadow-slate-900/25 sm:p-12 lg:p-14">
              <div className="yap-dot-grid absolute inset-0 opacity-[0.06]" aria-hidden="true" />
              <div className="absolute -right-24 -top-24 h-80 w-80 rounded-full bg-yap-accent/18 blur-[120px]" aria-hidden="true" />
              <div className="relative grid gap-8 lg:grid-cols-[1fr_auto] lg:items-center">
                <div>
                  <SectionBadge dark>{trans('welcome.get_started')}</SectionBadge>
                  <h2 className="mt-6 max-w-3xl font-display text-4xl leading-tight text-white sm:text-5xl">
                    {trans('welcome.cta_title')}
                  </h2>
                  <p className="mt-5 max-w-2xl text-base leading-8 text-white/72">{trans('welcome.cta_body')}</p>
                </div>
                <ActionLink href={primaryHref} className={`${primaryActionClasses} bg-white from-white to-white text-yap-accent hover:brightness-100`}>
                  {primaryLabel}
                  <ArrowIcon className="h-4 w-4 transition group-hover:translate-x-1 motion-reduce:transition-none motion-reduce:group-hover:translate-x-0" />
                </ActionLink>
              </div>
            </div>
          </section>
        </main>

        <footer className="border-t border-yap-border bg-white px-4 sm:px-6 lg:px-8">
          <div className="mx-auto flex max-w-6xl flex-col gap-6 py-9 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <div className="flex items-center gap-3">
                <span className="flex h-9 w-9 items-center justify-center rounded-2xl bg-yap-foreground text-sm font-bold text-white">Y</span>
                <span className="text-sm font-semibold text-yap-foreground">YAP</span>
              </div>
              <p className="mt-2 max-w-sm text-xs leading-5 text-yap-muted-foreground">{trans('welcome.title')}</p>
            </div>
            <div className="flex flex-wrap items-center gap-4 text-xs font-medium text-yap-muted-foreground">
              <Link href={route('policy')} className="transition hover:text-yap-foreground">{trans('common.privacy_policy')}</Link>
              <Link href={route('tos')} className="transition hover:text-yap-foreground">{trans('common.terms_of_service')}</Link>
              <Link href={route('commercial.disclosure')} className="transition hover:text-yap-foreground">{trans('common.commercial_disclosure')}</Link>
              <a href="https://t.me/yap_devs" target="_blank" rel="noopener noreferrer" className="transition hover:text-yap-foreground">Telegram</a>
              <a href="https://github.com/yap-devs/yap" target="_blank" rel="noopener noreferrer" className="transition hover:text-yap-foreground">GitHub</a>
            </div>
          </div>
        </footer>
      </div>
    </>
  );
}
