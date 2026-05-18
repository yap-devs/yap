import {Head, Link} from '@inertiajs/react';
import LanguageSelector from '@/Components/LanguageSelector';
import {trans} from '@/Utils/i18n';

export default function CommercialDisclosure({seller, address, phone, headOfOperations}) {
  const hostname = typeof window !== 'undefined' ? window.location.hostname : 'example.com';
  const origin = typeof window !== 'undefined' ? window.location.origin : 'https://example.com';
  const parts = hostname.split('.');
  const rootDomain = parts.length > 2 ? parts.slice(-2).join('.') : hostname;
  const contactEmail = `contact@${rootDomain}`;

  const values = {
    seller: seller || trans('commercial.upon_request'),
    address: address || trans('commercial.upon_request'),
    phone: phone || trans('commercial.upon_request'),
    email: (
      <a href={`mailto:${contactEmail}`} className="text-indigo-400 hover:text-indigo-300 underline">
        {contactEmail}
      </a>
    ),
    head: headOfOperations || <span className="text-yellow-500">{trans('commercial.not_configured')}</span>,
    website: <Link href="/" className="text-indigo-400 hover:text-indigo-300 underline">{origin}</Link>,
  };

  return (
    <>
      <Head title={trans('commercial.title')}/>
      <div className="bg-gray-950 text-gray-100 min-h-screen flex flex-col antialiased">
        <nav className="bg-gray-950/80 backdrop-blur-md border-b border-white/5">
          <div className="max-w-4xl mx-auto px-6 h-16 flex items-center justify-between">
            <Link href="/" className="text-xl font-bold tracking-tight text-white">YAP</Link>
            <LanguageSelector className="text-gray-300" />
          </div>
        </nav>

        <main className="flex-grow py-16 sm:py-24">
          <div className="max-w-4xl mx-auto px-6">
            <h1 className="text-3xl sm:text-4xl font-bold tracking-tight text-white mb-2">
              {trans('commercial.title')}
            </h1>
            <p className="text-base text-gray-400 mb-12">{trans('commercial.subtitle')}</p>

            <div className="overflow-hidden rounded-xl border border-white/5 text-gray-300 text-sm leading-relaxed">
              <table className="w-full text-left">
                <tbody className="divide-y divide-white/5">
                  {trans('commercial.rows').map((row) => (
                    <tr key={row.label}>
                      <th className="bg-white/[0.02] px-6 py-4 text-sm font-medium text-gray-200 whitespace-nowrap w-1/3 align-top">
                        {row.label}
                      </th>
                      <td className="px-6 py-4">
                        {row.key ? values[row.key] : row.text}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
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
