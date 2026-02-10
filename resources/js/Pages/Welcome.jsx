import {Head, Link} from '@inertiajs/react';

export default function Welcome({auth, laravelVersion, phpVersion}) {
  const features = [
    {
      title: 'Traffic-based Billing',
      desc: 'Pay for what you use. Purchase traffic packages at bulk rates or use pay-as-you-go pricing.',
    },
    {
      title: 'Multi-client Support',
      desc: 'One subscription URL works with Clash, Shadowrocket, and Stash across all your devices.',
    },
    {
      title: 'Smart Routing',
      desc: 'Automatic split tunneling with direct domestic connections and accelerated international access.',
    },
    {
      title: 'Streaming Unlock',
      desc: 'Dedicated nodes for Netflix, Disney+, HBO, and other geo-restricted streaming services.',
    },
    {
      title: 'Multiple Servers',
      desc: 'Choose from a selection of global nodes with different rate multipliers to fit your needs.',
    },
    {
      title: 'Flexible Payments',
      desc: 'Top up via Alipay, USDT, or earn credits through GitHub Sponsors.',
    },
  ];

  return (
    <>
      <Head title="YAP"/>
      <div className="bg-[#0a0a0a] text-neutral-100 min-h-screen flex flex-col">

        {/* Header */}
        <header className="border-b border-neutral-800/60">
          <div className="max-w-5xl mx-auto px-6 h-14 flex items-center justify-between">
            <span className="text-lg font-semibold tracking-tight">YAP</span>
            <nav className="flex items-center gap-5">
              {auth.user ? (
                <Link
                  href={route('dashboard')}
                  className="text-sm text-neutral-400 hover:text-neutral-100 transition-colors"
                >
                  Dashboard
                </Link>
              ) : (
                <>
                  <Link
                    href={route('login')}
                    className="text-sm text-neutral-400 hover:text-neutral-100 transition-colors"
                  >
                    Log in
                  </Link>
                  <Link
                    href={route('register')}
                    className="text-sm px-3.5 py-1.5 rounded-md bg-neutral-100 text-neutral-900 font-medium hover:bg-white transition-colors"
                  >
                    Sign up
                  </Link>
                </>
              )}
            </nav>
          </div>
        </header>

        <main className="flex-grow">

          {/* Hero */}
          <section className="max-w-5xl mx-auto px-6 pt-24 pb-20 sm:pt-32 sm:pb-28">
            <p className="text-sm font-medium text-neutral-500 mb-4 tracking-wide uppercase">Yet Another Panel</p>
            <h1 className="text-3xl sm:text-4xl md:text-5xl font-bold leading-tight tracking-tight max-w-2xl">
              A self-hosted proxy subscription management panel.
            </h1>
            <p className="mt-5 text-base sm:text-lg text-neutral-400 max-w-xl leading-relaxed">
              Manage users, traffic, servers, and payments in one place.
              Built with Laravel and React for operators who want full control.
            </p>
            <div className="mt-8 flex flex-wrap gap-3">
              <Link
                href={route('register')}
                className="inline-flex items-center px-5 py-2.5 rounded-md bg-neutral-100 text-neutral-900 text-sm font-medium hover:bg-white transition-colors"
              >
                Get started
                <svg className="ml-1.5 w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
              </Link>
              <a
                href="https://github.com/yap-devs/yap"
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex items-center px-5 py-2.5 rounded-md border border-neutral-700 text-sm text-neutral-300 font-medium hover:border-neutral-500 hover:text-neutral-100 transition-colors"
              >
                <svg className="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 24 24">
                  <path fillRule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z" clipRule="evenodd"/>
                </svg>
                Source code
              </a>
            </div>
          </section>

          {/* Divider */}
          <div className="max-w-5xl mx-auto px-6">
            <div className="border-t border-neutral-800/60"></div>
          </div>

          {/* Features */}
          <section className="max-w-5xl mx-auto px-6 py-20 sm:py-28">
            <h2 className="text-xl sm:text-2xl font-semibold tracking-tight mb-10">What's included</h2>
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-8 gap-y-10">
              {features.map((f, i) => (
                <div key={i}>
                  <h3 className="text-sm font-semibold text-neutral-100 mb-2">{f.title}</h3>
                  <p className="text-sm text-neutral-500 leading-relaxed">{f.desc}</p>
                </div>
              ))}
            </div>
          </section>

          {/* Divider */}
          <div className="max-w-5xl mx-auto px-6">
            <div className="border-t border-neutral-800/60"></div>
          </div>

          {/* CTA */}
          <section className="max-w-5xl mx-auto px-6 py-20 sm:py-28">
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-6">
              <div>
                <h2 className="text-xl sm:text-2xl font-semibold tracking-tight">Ready to get started?</h2>
                <p className="mt-2 text-sm text-neutral-500">Create an account, top up your balance, and connect.</p>
              </div>
              <Link
                href={route('register')}
                className="inline-flex items-center px-5 py-2.5 rounded-md bg-neutral-100 text-neutral-900 text-sm font-medium hover:bg-white transition-colors shrink-0"
              >
                Create account
              </Link>
            </div>
          </section>
        </main>

        {/* Footer */}
        <footer className="border-t border-neutral-800/60">
          <div className="max-w-5xl mx-auto px-6 py-6 flex flex-col sm:flex-row items-center justify-between gap-4">
            <span className="text-xs text-neutral-600">
              Laravel v{laravelVersion} (PHP v{phpVersion})
            </span>
            <div className="flex items-center gap-5">
              <a
                href="https://t.me/yap_devs"
                className="text-neutral-600 hover:text-neutral-400 transition-colors"
                target="_blank"
                rel="noopener noreferrer"
              >
                <span className="sr-only">Telegram</span>
                <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.346.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.96 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>
                </svg>
              </a>
              <a
                href="https://github.com/yap-devs/yap"
                className="text-neutral-600 hover:text-neutral-400 transition-colors"
                target="_blank"
                rel="noopener noreferrer"
              >
                <span className="sr-only">GitHub</span>
                <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                  <path fillRule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z" clipRule="evenodd"/>
                </svg>
              </a>
            </div>
          </div>
        </footer>
      </div>
    </>
  );
}
