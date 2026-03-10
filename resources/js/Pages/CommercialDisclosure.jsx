import {Head, Link} from '@inertiajs/react';

export default function CommercialDisclosure() {
  return (
    <>
      <Head title="Commercial Disclosure"/>
      <div className="bg-gray-950 text-gray-100 min-h-screen flex flex-col antialiased">

        {/* Navigation */}
        <nav className="bg-gray-950/80 backdrop-blur-md border-b border-white/5">
          <div className="max-w-4xl mx-auto px-6 h-16 flex items-center justify-between">
            <Link href="/" className="text-xl font-bold tracking-tight text-white">YAP</Link>
          </div>
        </nav>

        <main className="flex-grow py-16 sm:py-24">
          <div className="max-w-4xl mx-auto px-6">
            <h1 className="text-3xl sm:text-4xl font-bold tracking-tight text-white mb-2">
              Commercial Disclosure
            </h1>
            <p className="text-base text-gray-400 mb-12">
              Notation based on the Act on Specified Commercial Transactions
            </p>

            <div className="space-y-8 text-gray-300 text-sm leading-relaxed">

              <div className="overflow-hidden rounded-xl border border-white/5">
                <table className="w-full text-left">
                  <tbody className="divide-y divide-white/5">
                    <tr>
                      <th className="bg-white/[0.02] px-6 py-4 text-sm font-medium text-gray-200 whitespace-nowrap w-1/3 align-top">
                        Seller
                      </th>
                      <td className="px-6 py-4">
                        Will be disclosed without delay upon request.
                      </td>
                    </tr>
                    <tr>
                      <th className="bg-white/[0.02] px-6 py-4 text-sm font-medium text-gray-200 whitespace-nowrap w-1/3 align-top">
                        Head of Operations
                      </th>
                      <td className="px-6 py-4">
                        Will be disclosed without delay upon request.
                      </td>
                    </tr>
                    <tr>
                      <th className="bg-white/[0.02] px-6 py-4 text-sm font-medium text-gray-200 whitespace-nowrap w-1/3 align-top">
                        Address
                      </th>
                      <td className="px-6 py-4">
                        Will be disclosed without delay upon request.
                      </td>
                    </tr>
                    <tr>
                      <th className="bg-white/[0.02] px-6 py-4 text-sm font-medium text-gray-200 whitespace-nowrap w-1/3 align-top">
                        Phone Number
                      </th>
                      <td className="px-6 py-4">
                        Will be disclosed without delay upon request.
                      </td>
                    </tr>
                    <tr>
                      <th className="bg-white/[0.02] px-6 py-4 text-sm font-medium text-gray-200 whitespace-nowrap w-1/3 align-top">
                        Email Address
                      </th>
                      <td className="px-6 py-4">
                        Will be disclosed without delay upon request.
                      </td>
                    </tr>
                    <tr>
                      <th className="bg-white/[0.02] px-6 py-4 text-sm font-medium text-gray-200 whitespace-nowrap w-1/3 align-top">
                        Website URL
                      </th>
                      <td className="px-6 py-4">
                        <Link href="/" className="text-indigo-400 hover:text-indigo-300 underline">
                          {typeof window !== 'undefined' ? window.location.origin : 'https://yap.example.com'}
                        </Link>
                      </td>
                    </tr>
                    <tr>
                      <th className="bg-white/[0.02] px-6 py-4 text-sm font-medium text-gray-200 whitespace-nowrap w-1/3 align-top">
                        Pricing
                      </th>
                      <td className="px-6 py-4">
                        As displayed on each product/service page (tax included).
                      </td>
                    </tr>
                    <tr>
                      <th className="bg-white/[0.02] px-6 py-4 text-sm font-medium text-gray-200 whitespace-nowrap w-1/3 align-top">
                        Additional Fees
                      </th>
                      <td className="px-6 py-4">
                        None. No additional charges beyond the displayed price.
                      </td>
                    </tr>
                    <tr>
                      <th className="bg-white/[0.02] px-6 py-4 text-sm font-medium text-gray-200 whitespace-nowrap w-1/3 align-top">
                        Payment Methods
                      </th>
                      <td className="px-6 py-4">
                        <ul className="list-disc list-inside space-y-1">
                          <li>Alipay</li>
                          <li>Cryptocurrency (USDT via BEPUSDT)</li>
                          <li>GitHub Sponsors</li>
                        </ul>
                      </td>
                    </tr>
                    <tr>
                      <th className="bg-white/[0.02] px-6 py-4 text-sm font-medium text-gray-200 whitespace-nowrap w-1/3 align-top">
                        Payment Period
                      </th>
                      <td className="px-6 py-4">
                        Payment is due at the time of purchase.
                      </td>
                    </tr>
                    <tr>
                      <th className="bg-white/[0.02] px-6 py-4 text-sm font-medium text-gray-200 whitespace-nowrap w-1/3 align-top">
                        Delivery Timing
                      </th>
                      <td className="px-6 py-4">
                        Service is available immediately after payment confirmation.
                        Traffic balance is credited to your account instantly upon successful payment processing.
                      </td>
                    </tr>
                    <tr>
                      <th className="bg-white/[0.02] px-6 py-4 text-sm font-medium text-gray-200 whitespace-nowrap w-1/3 align-top">
                        Returns & Cancellations
                      </th>
                      <td className="px-6 py-4">
                        <p className="mb-2">
                          Due to the nature of digital services, returns are generally not accepted once the service has been delivered.
                        </p>
                        <p className="mb-2">Refunds may be considered in the following cases:</p>
                        <ul className="list-disc list-inside space-y-1">
                          <li>Service outage lasting more than 72 consecutive hours due to issues on our end.</li>
                          <li>Duplicate or erroneous charges caused by payment processing errors.</li>
                          <li>Unused account balance requested within 7 days of the original purchase.</li>
                        </ul>
                        <p className="mt-2">
                          To request a refund, please contact us through our support channels.
                          Refund requests are evaluated on a case-by-case basis.
                          Processing time for approved refunds is typically 5-10 business days.
                        </p>
                      </td>
                    </tr>
                    <tr>
                      <th className="bg-white/[0.02] px-6 py-4 text-sm font-medium text-gray-200 whitespace-nowrap w-1/3 align-top">
                        Operating Environment
                      </th>
                      <td className="px-6 py-4">
                        Compatible with Clash, Shadowrocket, and Stash clients on Windows, macOS, iOS, Android, and Linux.
                        A stable internet connection is required to use the service.
                      </td>
                    </tr>
                    <tr>
                      <th className="bg-white/[0.02] px-6 py-4 text-sm font-medium text-gray-200 whitespace-nowrap w-1/3 align-top">
                        Contact
                      </th>
                      <td className="px-6 py-4">
                        <a href="https://t.me/yap_devs" target="_blank" rel="noopener noreferrer"
                           className="text-indigo-400 hover:text-indigo-300 underline">
                          Telegram community
                        </a>
                        {' / '}
                        <a href="https://github.com/yap-devs/yap" target="_blank" rel="noopener noreferrer"
                           className="text-indigo-400 hover:text-indigo-300 underline">
                          GitHub repository
                        </a>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

            </div>
          </div>
        </main>

        <footer className="border-t border-white/5 bg-gray-950">
          <div className="max-w-4xl mx-auto px-6 py-8">
            <div className="flex flex-col sm:flex-row items-center justify-between gap-4">
              <span className="text-sm font-semibold text-white">YAP</span>
              <div className="flex items-center gap-6 text-xs text-gray-500">
                <Link href={route('policy')} className="hover:text-gray-300 transition-colors">Privacy Policy</Link>
                <Link href={route('tos')} className="hover:text-gray-300 transition-colors">Terms of Service</Link>
                <Link href={route('commercial.disclosure')} className="hover:text-gray-300 transition-colors">Commercial Disclosure</Link>
              </div>
            </div>
          </div>
        </footer>
      </div>
    </>
  );
}
