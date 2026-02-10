import {Head, Link} from '@inertiajs/react';
import {useEffect, useState} from 'react';

export default function Welcome({auth, laravelVersion, phpVersion}) {
  const [scrolled, setScrolled] = useState(false);

  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 20);
    window.addEventListener('scroll', onScroll);
    return () => window.removeEventListener('scroll', onScroll);
  }, []);

  return (
    <>
      <Head title="YAP - Network Acceleration Service"/>
      <div className="bg-gray-950 text-gray-100 min-h-screen flex flex-col antialiased">

        {/* Navigation */}
        <nav className={`fixed top-0 inset-x-0 z-50 transition-colors duration-200 ${scrolled ? 'bg-gray-950/80 backdrop-blur-md border-b border-white/5' : ''}`}>
          <div className="max-w-6xl mx-auto px-6 h-16 flex items-center justify-between">
            <Link href="/" className="text-xl font-bold tracking-tight text-white">YAP</Link>
            <div className="flex items-center gap-4">
              {auth.user ? (
                <Link href={route('dashboard')} className="text-sm text-gray-300 hover:text-white transition-colors">
                  Dashboard
                </Link>
              ) : (
                <>
                  <Link href={route('login')} className="text-sm text-gray-400 hover:text-white transition-colors">
                    Log in
                  </Link>
                  <Link
                    href={route('register')}
                    className="text-sm font-medium px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white transition-colors"
                  >
                    Get Started
                  </Link>
                </>
              )}
            </div>
          </div>
        </nav>

        <main className="flex-grow">

          {/* Hero */}
          <section className="relative pt-32 pb-24 sm:pt-40 sm:pb-32 overflow-hidden">
            {/* Aurora background */}
            <div className="absolute inset-0 pointer-events-none aurora-bg">
              <div className="aurora-orb-1 absolute -top-32 left-1/4 w-[500px] h-[500px] bg-indigo-600 rounded-full blur-[140px] opacity-15"></div>
              <div className="aurora-orb-2 absolute -top-20 right-1/4 w-[400px] h-[400px] bg-violet-500 rounded-full blur-[120px] opacity-12"></div>
              <div className="aurora-orb-3 absolute top-10 left-1/2 -translate-x-1/2 w-[600px] h-[350px] bg-cyan-500 rounded-full blur-[150px] opacity-10"></div>
            </div>

            <div className="relative max-w-6xl mx-auto px-6 text-center">
              <h1 className="text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight leading-[1.15] text-white">
                Fast, Stable, Global<br className="hidden sm:block"/> Network Acceleration
              </h1>
              <p className="mt-6 text-lg sm:text-xl text-gray-400 max-w-2xl mx-auto leading-relaxed">
                Premium proxy service with smart routing, multi-platform support,
                and streaming media unlock. Pay only for the traffic you use.
              </p>
              <div className="mt-10 flex flex-col sm:flex-row items-center justify-center gap-4">
                <Link
                  href={route('register')}
                  className="w-full sm:w-auto text-center px-8 py-3 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-medium transition-colors text-sm"
                >
                  Create Free Account
                </Link>
                <a
                  href="#features"
                  className="w-full sm:w-auto text-center px-8 py-3 rounded-lg border border-gray-700 hover:border-gray-500 text-gray-300 hover:text-white font-medium transition-colors text-sm"
                >
                  Learn More
                </a>
              </div>
            </div>
          </section>

          {/* Trust bar */}
          <section className="border-y border-white/5 bg-white/[0.02]">
            <div className="max-w-6xl mx-auto px-6 py-8">
              <div className="grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
                {[
                  {label: 'Supported Clients', value: 'Clash / Shadowrocket / Stash'},
                  {label: 'Payment Methods', value: 'Alipay / USDT / GitHub'},
                  {label: 'Billing Model', value: 'Pay-per-traffic'},
                  {label: 'Support', value: 'Telegram Community'},
                ].map((item, i) => (
                  <div key={i}>
                    <div className="text-xs text-gray-500 uppercase tracking-wider mb-1">{item.label}</div>
                    <div className="text-sm font-medium text-gray-200">{item.value}</div>
                  </div>
                ))}
              </div>
            </div>
          </section>

          {/* Features */}
          <section id="features" className="py-24 sm:py-32">
            <div className="max-w-6xl mx-auto px-6">
              <div className="text-center mb-16">
                <h2 className="text-2xl sm:text-3xl font-bold tracking-tight text-white">
                  Everything you need for unrestricted access
                </h2>
                <p className="mt-4 text-gray-400 max-w-xl mx-auto">
                  Built for performance, priced for fairness.
                </p>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                {[
                  {
                    icon: (
                      <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/>
                    ),
                    title: 'High-speed Nodes',
                    desc: 'Optimized global server network with low-latency connections and high-bandwidth backbone infrastructure.',
                  },
                  {
                    icon: (
                      <path strokeLinecap="round" strokeLinejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
                    ),
                    title: 'Privacy First',
                    desc: 'No activity logging, no user tracking. Your connection data is never stored or monitored.',
                  },
                  {
                    icon: (
                      <path strokeLinecap="round" strokeLinejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"/>
                    ),
                    title: 'Smart Routing',
                    desc: 'Automatic split tunneling -- direct connection for domestic sites, accelerated routing for international access.',
                  },
                  {
                    icon: (
                      <path strokeLinecap="round" strokeLinejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z M15.91 11.672a.375.375 0 010 .656l-5.603 3.113a.375.375 0 01-.557-.328V8.887c0-.286.307-.466.557-.327l5.603 3.112z"/>
                    ),
                    title: 'Streaming Unlock',
                    desc: 'Dedicated nodes for Netflix, Disney+, HBO, Hulu and more. Watch global content without restrictions.',
                  },
                  {
                    icon: (
                      <path strokeLinecap="round" strokeLinejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z"/>
                    ),
                    title: 'Flexible Billing',
                    desc: 'Pay-as-you-go pricing or purchase traffic packages at bulk discount rates. No monthly subscriptions forced.',
                  },
                  {
                    icon: (
                      <path strokeLinecap="round" strokeLinejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3"/>
                    ),
                    title: 'Multi-platform',
                    desc: 'One subscription URL works across Clash, Shadowrocket, and Stash on Windows, macOS, iOS, Android, and Linux.',
                  },
                ].map((feature, i) => (
                  <div key={i} className="group p-6 rounded-xl border border-white/5 bg-white/[0.02] hover:bg-white/[0.04] hover:border-white/10 transition-colors">
                    <div className="w-10 h-10 rounded-lg bg-indigo-600/10 flex items-center justify-center mb-4">
                      <svg className="w-5 h-5 text-indigo-400" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor">
                        {feature.icon}
                      </svg>
                    </div>
                    <h3 className="text-base font-semibold text-white mb-2">{feature.title}</h3>
                    <p className="text-sm text-gray-400 leading-relaxed">{feature.desc}</p>
                  </div>
                ))}
              </div>
            </div>
          </section>

          {/* How it works */}
          <section className="py-24 sm:py-32 border-t border-white/5">
            <div className="max-w-6xl mx-auto px-6">
              <div className="text-center mb-16">
                <h2 className="text-2xl sm:text-3xl font-bold tracking-tight text-white">
                  Get connected in minutes
                </h2>
                <p className="mt-4 text-gray-400">Three simple steps to get started.</p>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-4xl mx-auto">
                {[
                  {step: '1', title: 'Create an account', desc: 'Sign up for free with just your email. No credit card required.'},
                  {step: '2', title: 'Top up balance', desc: 'Add funds via Alipay, USDT, or GitHub Sponsors. Start from as low as $2.'},
                  {step: '3', title: 'Import & connect', desc: 'Copy your subscription URL into Clash, Shadowrocket, or Stash and connect instantly.'},
                ].map((item, i) => (
                  <div key={i} className="text-center">
                    <div className="w-10 h-10 rounded-full bg-indigo-600/20 text-indigo-400 text-sm font-bold flex items-center justify-center mx-auto mb-4">
                      {item.step}
                    </div>
                    <h3 className="text-base font-semibold text-white mb-2">{item.title}</h3>
                    <p className="text-sm text-gray-400 leading-relaxed">{item.desc}</p>
                  </div>
                ))}
              </div>
            </div>
          </section>

          {/* CTA */}
          <section className="py-24 sm:py-32 border-t border-white/5">
            <div className="max-w-6xl mx-auto px-6">
              <div className="rounded-2xl bg-gradient-to-b from-indigo-600/10 to-transparent border border-indigo-500/10 p-12 sm:p-16 text-center">
                <h2 className="text-2xl sm:text-3xl font-bold tracking-tight text-white">
                  Start using YAP today
                </h2>
                <p className="mt-4 text-gray-400 max-w-lg mx-auto">
                  Free to register. No monthly commitment. Pay only for the traffic you actually use.
                </p>
                <div className="mt-8">
                  <Link
                    href={route('register')}
                    className="inline-flex items-center px-8 py-3 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-medium transition-colors text-sm"
                  >
                    Create Free Account
                    <svg className="ml-2 w-4 h-4" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/>
                    </svg>
                  </Link>
                </div>
              </div>
            </div>
          </section>
        </main>

        {/* Footer */}
        <footer className="border-t border-white/5 bg-gray-950">
          <div className="max-w-6xl mx-auto px-6 py-8">
            <div className="flex flex-col sm:flex-row items-center justify-between gap-4">
              <div className="flex items-center gap-6">
                <span className="text-sm font-semibold text-white">YAP</span>
                <span className="text-xs text-gray-600">
                  Laravel v{laravelVersion} (PHP v{phpVersion})
                </span>
              </div>
              <div className="flex items-center gap-4">
                <a href="https://t.me/yap_devs" target="_blank" rel="noopener noreferrer"
                   className="text-gray-500 hover:text-gray-300 transition-colors" title="Telegram">
                  <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.346.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.96 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>
                  </svg>
                </a>
                <a href="https://github.com/yap-devs/yap" target="_blank" rel="noopener noreferrer"
                   className="text-gray-500 hover:text-gray-300 transition-colors" title="GitHub">
                  <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                    <path fillRule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z" clipRule="evenodd"/>
                  </svg>
                </a>
              </div>
            </div>
          </div>
        </footer>
      </div>
    </>
  );
}
