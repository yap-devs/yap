import {Head, Link} from '@inertiajs/react';
import LanguageSelector from '@/Components/LanguageSelector';
import {trans} from '@/Utils/i18n';

export default function Welcome({auth, canLogin, canRegister}) {
  const heroPoints = trans('welcome.hero_points', {}, []);
  const heroPanelSteps = trans('welcome.hero_panel_steps', {}, []);
  const trustItems = trans('welcome.trust', {}, []);
  const useCases = trans('welcome.use_cases', {}, []);
  const privacyPoints = trans('welcome.privacy_points', {}, []);
  const features = trans('welcome.features', {}, []);
  const steps = trans('welcome.steps', {}, []);

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
      <div className="min-h-screen bg-stone-50 text-stone-950 antialiased">
        <header className="border-b border-stone-200 bg-white/95">
          <div className="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6 lg:px-8">
            <Link href="/" className="flex items-center gap-3">
              <span className="flex h-8 w-8 items-center justify-center rounded-md border border-stone-300 bg-white text-sm font-bold text-stone-900">Y</span>
              <span className="text-base font-semibold tracking-tight text-stone-950">YAP</span>
            </Link>
            <div className="flex items-center gap-3 sm:gap-5">
              {auth.user ? (
                <Link href={route('dashboard')} className="text-sm font-medium text-stone-700 hover:text-stone-950">
                  {trans('nav.dashboard')}
                </Link>
              ) : (
                <>
                  {canLogin && (
                    <Link href={route('login')} className="text-sm font-medium text-stone-700 hover:text-stone-950">
                      {trans('auth.login')}
                    </Link>
                  )}
                  {canRegister && (
                    <Link
                      href={route('register')}
                      className="hidden rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700 sm:inline-flex"
                    >
                      {trans('welcome.get_started')}
                    </Link>
                  )}
                </>
              )}
              <LanguageSelector />
            </div>
          </div>
        </header>

        <main>
          <section className="border-b border-stone-200 bg-white">
            <div className="mx-auto grid max-w-6xl gap-10 px-4 py-14 sm:px-6 sm:py-20 lg:grid-cols-[1.15fr_0.85fr] lg:px-8">
              <div>
                <p className="text-sm font-semibold text-stone-600">{trans('welcome.hero_eyebrow')}</p>
                <h1 className="mt-4 max-w-3xl text-4xl font-semibold tracking-tight text-stone-950 sm:text-5xl">
                  {trans('welcome.hero_title')}
                </h1>
                <p className="mt-6 max-w-2xl text-base leading-8 text-stone-700 sm:text-lg">
                  {trans('welcome.hero_body')}
                </p>
                <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                  <Link
                    href={primaryHref}
                    className="inline-flex items-center justify-center rounded-md bg-stone-900 px-5 py-3 text-sm font-semibold text-white hover:bg-stone-700"
                  >
                    {primaryLabel}
                  </Link>
                  <a
                    href="#privacy"
                    className="inline-flex items-center justify-center rounded-md border border-stone-300 bg-white px-5 py-3 text-sm font-semibold text-stone-800 hover:border-stone-400 hover:bg-stone-50"
                  >
                    {trans('welcome.privacy_cta')}
                  </a>
                </div>
                <p className="mt-4 text-sm text-stone-500">{trans('welcome.hero_note')}</p>
              </div>

              <aside className="rounded-xl border border-stone-200 bg-stone-50 p-5">
                <p className="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">{trans('welcome.hero_panel_label')}</p>
                <h2 className="mt-3 text-xl font-semibold text-stone-950">{trans('welcome.hero_panel_title')}</h2>
                <p className="mt-2 text-sm leading-6 text-stone-600">{trans('welcome.hero_panel_body')}</p>
                <div className="mt-5 divide-y divide-stone-200 rounded-lg border border-stone-200 bg-white">
                  {heroPanelSteps.map((item) => (
                    <div key={item.step} className="flex gap-4 p-4">
                      <div className="text-sm font-semibold text-stone-400">{item.step}</div>
                      <div>
                        <h3 className="text-sm font-semibold text-stone-950">{item.title}</h3>
                        <p className="mt-1 text-sm leading-6 text-stone-600">{item.desc}</p>
                      </div>
                    </div>
                  ))}
                </div>
                <p className="mt-4 rounded-lg border border-stone-200 bg-white p-4 text-sm leading-6 text-stone-700">
                  {trans('welcome.hero_panel_footer')}
                </p>
              </aside>
            </div>
          </section>

          <section className="border-b border-stone-200 bg-stone-50">
            <div className="mx-auto max-w-6xl px-4 py-6 sm:px-6 lg:px-8">
              <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                {trustItems.map((item) => (
                  <div key={item.label} className="rounded-lg border border-stone-200 bg-white p-4">
                    <p className="text-xs font-medium text-stone-500">{item.label}</p>
                    <p className="mt-1 text-sm font-semibold text-stone-950">{item.value}</p>
                  </div>
                ))}
              </div>
            </div>
          </section>

          <section className="bg-white px-4 py-14 sm:px-6 sm:py-20 lg:px-8">
            <div className="mx-auto max-w-6xl">
              <div className="max-w-2xl">
                <p className="text-sm font-semibold text-stone-500">{trans('welcome.use_cases_label')}</p>
                <h2 className="mt-3 text-2xl font-semibold tracking-tight text-stone-950 sm:text-3xl">{trans('welcome.use_cases_title')}</h2>
                <p className="mt-4 text-base leading-7 text-stone-600">{trans('welcome.use_cases_body')}</p>
              </div>
              <div className="mt-8 grid gap-4 lg:grid-cols-3">
                {useCases.map((item) => (
                  <article key={item.title} className="rounded-xl border border-stone-200 bg-white p-5">
                    <h3 className="text-base font-semibold text-stone-950">{item.title}</h3>
                    <p className="mt-3 text-sm leading-7 text-stone-600">{item.desc}</p>
                  </article>
                ))}
              </div>
            </div>
          </section>

          <section id="privacy" className="border-y border-stone-200 bg-stone-50 px-4 py-14 sm:px-6 sm:py-20 lg:px-8">
            <div className="mx-auto grid max-w-6xl gap-8 lg:grid-cols-[0.85fr_1.15fr]">
              <div>
                <p className="text-sm font-semibold text-stone-500">{trans('welcome.privacy_label')}</p>
                <h2 className="mt-3 text-2xl font-semibold tracking-tight text-stone-950 sm:text-3xl">{trans('welcome.privacy_title')}</h2>
                <p className="mt-4 text-base leading-8 text-stone-700">{trans('welcome.privacy_body')}</p>
              </div>
              <div className="grid gap-3 sm:grid-cols-2">
                {privacyPoints.map((item) => (
                  <div key={item.title} className="rounded-xl border border-stone-200 bg-white p-5">
                    <h3 className="text-sm font-semibold text-stone-950">{item.title}</h3>
                    <p className="mt-2 text-sm leading-7 text-stone-600">{item.desc}</p>
                  </div>
                ))}
              </div>
            </div>
          </section>

          <section id="features" className="bg-white px-4 py-14 sm:px-6 sm:py-20 lg:px-8">
            <div className="mx-auto max-w-6xl">
              <div className="max-w-2xl">
                <h2 className="text-2xl font-semibold tracking-tight text-stone-950 sm:text-3xl">{trans('welcome.features_title')}</h2>
                <p className="mt-4 text-base leading-7 text-stone-600">{trans('welcome.features_body')}</p>
              </div>
              <div className="mt-8 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                {features.map((feature) => (
                  <article key={feature.title} className="rounded-xl border border-stone-200 bg-white p-5">
                    <h3 className="text-base font-semibold text-stone-950">{feature.title}</h3>
                    <p className="mt-3 text-sm leading-7 text-stone-600">{feature.desc}</p>
                  </article>
                ))}
              </div>
            </div>
          </section>

          <section className="border-y border-stone-200 bg-stone-50 px-4 py-14 sm:px-6 sm:py-20 lg:px-8">
            <div className="mx-auto max-w-6xl">
              <div className="max-w-2xl">
                <h2 className="text-2xl font-semibold tracking-tight text-stone-950 sm:text-3xl">{trans('welcome.how_title')}</h2>
                <p className="mt-4 text-base leading-7 text-stone-600">{trans('welcome.how_body')}</p>
              </div>
              <div className="mt-8 divide-y divide-stone-200 rounded-xl border border-stone-200 bg-white">
                {steps.map((item) => (
                  <div key={item.step} className="grid gap-3 p-5 sm:grid-cols-[5rem_1fr] sm:gap-6">
                    <div className="text-sm font-semibold text-stone-400">Step {item.step}</div>
                    <div>
                      <h3 className="text-base font-semibold text-stone-950">{item.title}</h3>
                      <p className="mt-2 text-sm leading-7 text-stone-600">{item.desc}</p>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </section>

          <section className="bg-white px-4 py-14 sm:px-6 sm:py-20 lg:px-8">
            <div className="mx-auto max-w-6xl rounded-xl border border-stone-200 bg-stone-50 p-6 sm:p-8">
              <div className="grid gap-6 lg:grid-cols-[1fr_auto] lg:items-center">
                <div>
                  <h2 className="text-2xl font-semibold tracking-tight text-stone-950">{trans('welcome.cta_title')}</h2>
                  <p className="mt-3 max-w-2xl text-sm leading-7 text-stone-600">{trans('welcome.cta_body')}</p>
                </div>
                <Link
                  href={primaryHref}
                  className="inline-flex items-center justify-center rounded-md bg-stone-900 px-5 py-3 text-sm font-semibold text-white hover:bg-stone-700"
                >
                  {primaryLabel}
                </Link>
              </div>
            </div>
          </section>
        </main>

        <footer className="border-t border-stone-200 bg-white px-4 sm:px-6 lg:px-8">
          <div className="mx-auto flex max-w-6xl flex-col gap-4 py-8 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <div className="text-sm font-semibold text-stone-950">YAP</div>
              <div className="mt-1 text-xs text-stone-500">{trans('welcome.title')}</div>
            </div>
            <div className="flex flex-wrap items-center gap-4 text-xs font-medium text-stone-500">
              <Link href={route('policy')} className="hover:text-stone-950">{trans('common.privacy_policy')}</Link>
              <Link href={route('tos')} className="hover:text-stone-950">{trans('common.terms_of_service')}</Link>
              <Link href={route('commercial.disclosure')} className="hover:text-stone-950">{trans('common.commercial_disclosure')}</Link>
              <a href="https://t.me/yap_devs" target="_blank" rel="noopener noreferrer" className="hover:text-stone-950">Telegram</a>
              <a href="https://github.com/yap-devs/yap" target="_blank" rel="noopener noreferrer" className="hover:text-stone-950">GitHub</a>
            </div>
          </div>
        </footer>
      </div>
    </>
  );
}
