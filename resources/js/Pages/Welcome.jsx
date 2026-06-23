import {Head, Link} from '@inertiajs/react';
import {useEffect, useState} from 'react';
import LanguageSelector from '@/Components/LanguageSelector';
import {trans} from '@/Utils/i18n';

export default function Welcome({auth, canLogin, canRegister}) {
  const [scrolled, setScrolled] = useState(false);

  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 20);
    window.addEventListener('scroll', onScroll);

    return () => window.removeEventListener('scroll', onScroll);
  }, []);

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

  const featureIcons = [
    <path key="route" strokeLinecap="round" strokeLinejoin="round" d="M9 6.75V15m0 0l3-3m-3 3l-3-3m12-3.75V15m0 0l3-3m-3 3l-3-3M3.75 4.5h16.5M3.75 19.5h16.5" />,
    <path key="wallet" strokeLinecap="round" strokeLinejoin="round" d="M21 12.75V9.75A2.25 2.25 0 0018.75 7.5h-13.5A2.25 2.25 0 003 9.75v4.5A2.25 2.25 0 005.25 16.5h13.5A2.25 2.25 0 0021 14.25v-1.5zm-4.5 0h.008v.008H16.5v-.008z" />,
    <path key="devices" strokeLinecap="round" strokeLinejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" />,
    <path key="shield" strokeLinecap="round" strokeLinejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751H20.25c-3.196 0-6.1-1.248-8.25-3.285z" />,
    <path key="chart" strokeLinecap="round" strokeLinejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zm6.75-4.5c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zm6.75-4.5c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />,
    <path key="refresh" strokeLinecap="round" strokeLinejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />,
  ];

  return (
    <>
      <Head title={trans('welcome.title')} />
      <div className="min-h-screen overflow-hidden bg-[#f8f3ea] text-slate-950 antialiased">
        <div className="pointer-events-none fixed inset-0 -z-10">
          <div className="absolute left-1/2 top-0 h-[34rem] w-[34rem] -translate-x-1/2 rounded-full bg-red-500/10 blur-3xl" />
          <div className="absolute right-0 top-40 h-96 w-96 rounded-full bg-sky-400/10 blur-3xl" />
          <div className="absolute bottom-0 left-0 h-96 w-96 rounded-full bg-amber-300/20 blur-3xl" />
        </div>

        <nav className={`fixed inset-x-0 top-0 z-50 transition duration-200 ${scrolled ? 'border-b border-slate-950/10 bg-[#f8f3ea]/90 shadow-sm backdrop-blur-xl' : 'bg-transparent'}`}>
          <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-5 sm:px-6 lg:px-8">
            <Link href="/" className="flex items-center gap-3">
              <span className="flex h-9 w-9 items-center justify-center rounded-full bg-red-600 text-sm font-black text-white shadow-lg shadow-red-600/20">Y</span>
              <span className="text-lg font-black tracking-tight text-slate-950">YAP</span>
            </Link>
            <div className="flex items-center gap-3 sm:gap-4">
              {auth.user ? (
                <Link href={route('dashboard')} className="hidden text-sm font-semibold text-slate-700 transition hover:text-red-700 sm:inline-flex">
                  {trans('nav.dashboard')}
                </Link>
              ) : (
                <>
                  {canLogin && (
                    <Link href={route('login')} className="text-sm font-semibold text-slate-700 transition hover:text-red-700">
                      {trans('auth.login')}
                    </Link>
                  )}
                  {canRegister && (
                    <Link
                      href={route('register')}
                      className="hidden rounded-full bg-slate-950 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700 sm:inline-flex"
                    >
                      {trans('welcome.get_started')}
                    </Link>
                  )}
                </>
              )}
              <LanguageSelector />
            </div>
          </div>
        </nav>

        <main>
          <section className="relative px-5 pb-16 pt-28 sm:px-6 sm:pb-24 sm:pt-36 lg:px-8">
            <div className="mx-auto grid max-w-7xl items-center gap-12 lg:grid-cols-[1.05fr_0.95fr]">
              <div>
                <div className="inline-flex items-center gap-2 rounded-full border border-red-600/20 bg-white/80 px-4 py-2 text-sm font-bold text-red-700 shadow-sm">
                  <span className="h-2 w-2 rounded-full bg-red-600" />
                  {trans('welcome.hero_eyebrow')}
                </div>
                <h1 className="mt-7 max-w-4xl text-4xl font-black tracking-tight text-slate-950 sm:text-6xl lg:text-7xl">
                  {trans('welcome.hero_title')}
                </h1>
                <p className="mt-6 max-w-2xl text-lg leading-8 text-slate-700 sm:text-xl">
                  {trans('welcome.hero_body')}
                </p>
                <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                  <Link
                    href={primaryHref}
                    className="inline-flex items-center justify-center rounded-full bg-red-600 px-7 py-3 text-sm font-bold text-white shadow-xl shadow-red-600/20 transition hover:bg-red-700"
                  >
                    {primaryLabel}
                    <svg className="ml-2 h-4 w-4" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                    </svg>
                  </Link>
                  <a
                    href="#privacy"
                    className="inline-flex items-center justify-center rounded-full border border-slate-300 bg-white/70 px-7 py-3 text-sm font-bold text-slate-800 shadow-sm transition hover:border-red-200 hover:text-red-700"
                  >
                    {trans('welcome.privacy_cta')}
                  </a>
                </div>
                <p className="mt-4 text-sm font-medium text-slate-500">
                  {trans('welcome.hero_note')}
                </p>
                <div className="mt-8 flex flex-wrap gap-3">
                  {heroPoints.map((point) => (
                    <span key={point} className="rounded-full border border-slate-200 bg-white/75 px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm">
                      {point}
                    </span>
                  ))}
                </div>
              </div>

              <div className="relative">
                <div className="absolute -inset-4 rounded-[2rem] bg-linear-to-br from-red-500/10 via-white/40 to-sky-400/10 blur-2xl" />
                <div className="relative overflow-hidden rounded-[2rem] border border-white/80 bg-white/85 p-5 shadow-[0_30px_80px_rgba(15,23,42,0.14)] backdrop-blur-xl sm:p-7">
                  <div className="flex items-start justify-between gap-4 border-b border-slate-200 pb-5">
                    <div>
                      <div className="text-xs font-black uppercase tracking-[0.3em] text-red-600">{trans('welcome.hero_panel_label')}</div>
                      <h2 className="mt-3 text-2xl font-black text-slate-950">{trans('welcome.hero_panel_title')}</h2>
                      <p className="mt-2 text-sm leading-6 text-slate-600">{trans('welcome.hero_panel_body')}</p>
                    </div>
                    <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-red-600 text-2xl font-black text-white shadow-lg shadow-red-600/20">中</div>
                  </div>
                  <div className="mt-6 space-y-4">
                    {heroPanelSteps.map((item) => (
                      <div key={item.title} className="flex gap-4 rounded-2xl border border-slate-200 bg-slate-50/80 p-4">
                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-950 text-sm font-black text-white">
                          {item.step}
                        </div>
                        <div>
                          <h3 className="font-bold text-slate-950">{item.title}</h3>
                          <p className="mt-1 text-sm leading-6 text-slate-600">{item.desc}</p>
                        </div>
                      </div>
                    ))}
                  </div>
                  <div className="mt-5 rounded-2xl bg-red-50 p-4 text-sm font-semibold leading-6 text-red-800 ring-1 ring-red-100">
                    {trans('welcome.hero_panel_footer')}
                  </div>
                </div>
              </div>
            </div>
          </section>

          <section className="border-y border-slate-950/10 bg-white/55 px-5 sm:px-6 lg:px-8">
            <div className="mx-auto grid max-w-7xl grid-cols-2 gap-px py-4 md:grid-cols-4">
              {trustItems.map((item) => (
                <div key={item.label} className="p-4 text-center sm:p-6">
                  <div className="text-xs font-black uppercase tracking-[0.18em] text-slate-500">{item.label}</div>
                  <div className="mt-2 text-sm font-black text-slate-950 sm:text-base">{item.value}</div>
                </div>
              ))}
            </div>
          </section>

          <section className="px-5 py-20 sm:px-6 sm:py-28 lg:px-8">
            <div className="mx-auto max-w-7xl">
              <div className="max-w-2xl">
                <span className="text-sm font-black uppercase tracking-[0.25em] text-red-600">{trans('welcome.use_cases_label')}</span>
                <h2 className="mt-4 text-3xl font-black tracking-tight text-slate-950 sm:text-5xl">{trans('welcome.use_cases_title')}</h2>
                <p className="mt-4 text-lg leading-8 text-slate-600">{trans('welcome.use_cases_body')}</p>
              </div>
              <div className="mt-10 grid gap-5 lg:grid-cols-3">
                {useCases.map((item, index) => (
                  <div key={item.title} className="group rounded-[1.75rem] border border-slate-200 bg-white/75 p-6 shadow-sm transition hover:-translate-y-1 hover:border-red-200 hover:shadow-xl hover:shadow-red-900/5">
                    <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-950 text-sm font-black text-white">
                      0{index + 1}
                    </div>
                    <h3 className="mt-6 text-xl font-black text-slate-950">{item.title}</h3>
                    <p className="mt-3 text-sm leading-7 text-slate-600">{item.desc}</p>
                  </div>
                ))}
              </div>
            </div>
          </section>

          <section id="privacy" className="px-5 pb-20 sm:px-6 sm:pb-28 lg:px-8">
            <div className="mx-auto grid max-w-7xl gap-8 rounded-[2rem] bg-slate-950 p-6 text-white shadow-2xl shadow-slate-950/20 sm:p-8 lg:grid-cols-[0.85fr_1.15fr] lg:p-10">
              <div className="rounded-[1.5rem] bg-white/8 p-6 ring-1 ring-white/10">
                <div className="inline-flex rounded-full bg-red-500 px-3 py-1 text-xs font-black uppercase tracking-[0.18em] text-white">{trans('welcome.privacy_label')}</div>
                <h2 className="mt-6 text-3xl font-black tracking-tight sm:text-4xl">{trans('welcome.privacy_title')}</h2>
                <p className="mt-5 text-base leading-8 text-slate-300">{trans('welcome.privacy_body')}</p>
              </div>
              <div className="grid gap-4 sm:grid-cols-2">
                {privacyPoints.map((item) => (
                  <div key={item.title} className="rounded-[1.5rem] bg-white p-5 text-slate-950 shadow-sm">
                    <div className="flex h-10 w-10 items-center justify-center rounded-full bg-red-50 text-red-700">
                      <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                      </svg>
                    </div>
                    <h3 className="mt-4 font-black text-slate-950">{item.title}</h3>
                    <p className="mt-2 text-sm leading-7 text-slate-600">{item.desc}</p>
                  </div>
                ))}
              </div>
            </div>
          </section>

          <section id="features" className="px-5 py-20 sm:px-6 sm:py-28 lg:px-8">
            <div className="mx-auto max-w-7xl">
              <div className="mx-auto max-w-3xl text-center">
                <h2 className="text-3xl font-black tracking-tight text-slate-950 sm:text-5xl">{trans('welcome.features_title')}</h2>
                <p className="mt-4 text-lg leading-8 text-slate-600">{trans('welcome.features_body')}</p>
              </div>
              <div className="mt-12 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                {features.map((feature, index) => (
                  <div key={feature.title} className="rounded-[1.5rem] border border-slate-200 bg-white/75 p-6 shadow-sm">
                    <div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-red-50 text-red-700">
                      <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" strokeWidth="1.8" stroke="currentColor">
                        {featureIcons[index % featureIcons.length]}
                      </svg>
                    </div>
                    <h3 className="mt-5 text-lg font-black text-slate-950">{feature.title}</h3>
                    <p className="mt-2 text-sm leading-7 text-slate-600">{feature.desc}</p>
                  </div>
                ))}
              </div>
            </div>
          </section>

          <section className="px-5 pb-20 sm:px-6 sm:pb-28 lg:px-8">
            <div className="mx-auto max-w-5xl rounded-[2rem] border border-slate-200 bg-white/75 p-6 shadow-sm sm:p-10">
              <div className="text-center">
                <h2 className="text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">{trans('welcome.how_title')}</h2>
                <p className="mt-4 text-base leading-7 text-slate-600">{trans('welcome.how_body')}</p>
              </div>
              <div className="mt-10 grid gap-4 md:grid-cols-3">
                {steps.map((item) => (
                  <div key={item.step} className="rounded-2xl bg-slate-50 p-5 ring-1 ring-slate-200">
                    <div className="flex h-10 w-10 items-center justify-center rounded-full bg-red-600 text-sm font-black text-white">{item.step}</div>
                    <h3 className="mt-5 font-black text-slate-950">{item.title}</h3>
                    <p className="mt-2 text-sm leading-7 text-slate-600">{item.desc}</p>
                  </div>
                ))}
              </div>
            </div>
          </section>

          <section className="px-5 pb-20 sm:px-6 sm:pb-28 lg:px-8">
            <div className="mx-auto max-w-7xl overflow-hidden rounded-[2rem] bg-linear-to-br from-red-600 via-red-600 to-slate-950 p-8 text-center text-white shadow-2xl shadow-red-900/20 sm:p-14">
              <h2 className="mx-auto max-w-3xl text-3xl font-black tracking-tight sm:text-5xl">{trans('welcome.cta_title')}</h2>
              <p className="mx-auto mt-5 max-w-2xl text-base leading-8 text-red-50">{trans('welcome.cta_body')}</p>
              <div className="mt-8 flex justify-center">
                <Link
                  href={primaryHref}
                  className="inline-flex items-center justify-center rounded-full bg-white px-7 py-3 text-sm font-black text-red-700 shadow-lg transition hover:bg-red-50"
                >
                  {primaryLabel}
                  <svg className="ml-2 h-4 w-4" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                  </svg>
                </Link>
              </div>
            </div>
          </section>
        </main>

        <footer className="border-t border-slate-950/10 bg-white/60 px-5 sm:px-6 lg:px-8">
          <div className="mx-auto flex max-w-7xl flex-col gap-5 py-8 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <div className="text-sm font-black text-slate-950">YAP</div>
              <div className="mt-1 text-xs font-medium text-slate-500">{trans('welcome.title')}</div>
            </div>
            <div className="flex flex-wrap items-center gap-4 text-xs font-semibold text-slate-500">
              <Link href={route('policy')} className="transition hover:text-red-700">{trans('common.privacy_policy')}</Link>
              <Link href={route('tos')} className="transition hover:text-red-700">{trans('common.terms_of_service')}</Link>
              <Link href={route('commercial.disclosure')} className="transition hover:text-red-700">{trans('common.commercial_disclosure')}</Link>
              <a href="https://t.me/yap_devs" target="_blank" rel="noopener noreferrer" className="transition hover:text-red-700">Telegram</a>
              <a href="https://github.com/yap-devs/yap" target="_blank" rel="noopener noreferrer" className="transition hover:text-red-700">GitHub</a>
            </div>
          </div>
        </footer>
      </div>
    </>
  );
}
