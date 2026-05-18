import {Head, Link} from '@inertiajs/react';
import LanguageSelector from '@/Components/LanguageSelector';
import {trans} from '@/Utils/i18n';

export default function Tos() {
  return (
    <>
      <Head title={trans('tos.title')}/>
      <div className="bg-gray-950 text-gray-100 min-h-screen flex flex-col antialiased">
        <nav className="bg-gray-950/80 backdrop-blur-md border-b border-white/5">
          <div className="max-w-4xl mx-auto px-6 h-16 flex items-center justify-between">
            <Link href="/" className="text-xl font-bold tracking-tight text-white">YAP</Link>
            <LanguageSelector className="text-gray-300" />
          </div>
        </nav>

        <main className="flex-grow py-16 sm:py-24">
          <div className="max-w-4xl mx-auto px-6">
            <h1 className="text-3xl sm:text-4xl font-bold tracking-tight text-white mb-2">{trans('tos.title')}</h1>
            <p className="text-sm text-gray-500 mb-12">{trans('common.last_updated')}</p>

            <div className="space-y-10 text-gray-300 text-sm leading-relaxed">
              {trans('tos.sections').map((section) => (
                <section key={section.title}>
                  <h2 className="text-lg font-semibold text-white mb-3">{section.title}</h2>
                  {section.body.map((paragraph) => (
                    <p key={paragraph} className="mb-2">{paragraph}</p>
                  ))}
                </section>
              ))}
            </div>
          </div>
        </main>

        <footer className="border-t border-white/5 bg-gray-950">
          <div className="max-w-4xl mx-auto px-6 py-8">
            <div className="flex flex-col sm:flex-row items-center justify-between gap-4">
              <span className="text-sm font-semibold text-white">YAP</span>
              <div className="flex items-center gap-6 text-xs text-gray-500">
                <Link href={route('policy')} className="hover:text-gray-300 transition-colors">{trans('common.privacy_policy')}</Link>
                <Link href={route('tos')} className="hover:text-gray-300 transition-colors">{trans('common.terms_of_service')}</Link>
                <Link href={route('commercial.disclosure')} className="hover:text-gray-300 transition-colors">{trans('common.commercial_disclosure')}</Link>
              </div>
            </div>
          </div>
        </footer>
      </div>
    </>
  );
}
