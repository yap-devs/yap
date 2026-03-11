import {Head, Link} from '@inertiajs/react';

export default function CommercialDisclosure() {
  const hostname = typeof window !== 'undefined' ? window.location.hostname : 'example.com';
  const origin = typeof window !== 'undefined' ? window.location.origin : 'https://example.com';
  const parts = hostname.split('.');
  const rootDomain = parts.length > 2 ? parts.slice(-2).join('.') : hostname;
  const contactEmail = `contact@${rootDomain}`;

  return (
    <>
      <Head title="特定商取引法に基づく表記 - Commercial Disclosure"/>
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
              特定商取引法に基づく表記
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
                        販売業者<br/>
                        <span className="text-xs text-gray-500 font-normal">Seller</span>
                      </th>
                      <td className="px-6 py-4">
                        請求があった場合は遅滞なく開示します。
                        <br/>
                        <span className="text-xs text-gray-500">
                          Will be disclosed without delay upon request.
                        </span>
                      </td>
                    </tr>
                    <tr>
                      <th className="bg-white/[0.02] px-6 py-4 text-sm font-medium text-gray-200 whitespace-nowrap w-1/3 align-top">
                        運営責任者<br/>
                        <span className="text-xs text-gray-500 font-normal">Head of Operations</span>
                      </th>
                      <td className="px-6 py-4">
                        請求があった場合は遅滞なく開示します。
                        <br/>
                        <span className="text-xs text-gray-500">
                          Will be disclosed without delay upon request.
                        </span>
                      </td>
                    </tr>
                    <tr>
                      <th className="bg-white/[0.02] px-6 py-4 text-sm font-medium text-gray-200 whitespace-nowrap w-1/3 align-top">
                        所在地<br/>
                        <span className="text-xs text-gray-500 font-normal">Address</span>
                      </th>
                      <td className="px-6 py-4">
                        請求があった場合は遅滞なく開示します。
                        <br/>
                        <span className="text-xs text-gray-500">
                          Will be disclosed without delay upon request.
                        </span>
                      </td>
                    </tr>
                    <tr>
                      <th className="bg-white/[0.02] px-6 py-4 text-sm font-medium text-gray-200 whitespace-nowrap w-1/3 align-top">
                        電話番号<br/>
                        <span className="text-xs text-gray-500 font-normal">Phone Number</span>
                      </th>
                      <td className="px-6 py-4">
                        請求があった場合は遅滞なく開示します。
                        <br/>
                        <span className="text-xs text-gray-500">
                          Will be disclosed without delay upon request.
                        </span>
                      </td>
                    </tr>
                    <tr>
                      <th className="bg-white/[0.02] px-6 py-4 text-sm font-medium text-gray-200 whitespace-nowrap w-1/3 align-top">
                        メールアドレス<br/>
                        <span className="text-xs text-gray-500 font-normal">Email Address</span>
                      </th>
                      <td className="px-6 py-4">
                        <a href={`mailto:${contactEmail}`}
                           className="text-indigo-400 hover:text-indigo-300 underline">
                          {contactEmail}
                        </a>
                      </td>
                    </tr>
                    <tr>
                      <th className="bg-white/[0.02] px-6 py-4 text-sm font-medium text-gray-200 whitespace-nowrap w-1/3 align-top">
                        ウェブサイト<br/>
                        <span className="text-xs text-gray-500 font-normal">Website URL</span>
                      </th>
                      <td className="px-6 py-4">
                        <Link href="/" className="text-indigo-400 hover:text-indigo-300 underline">
                          {origin}
                        </Link>
                      </td>
                    </tr>
                    <tr>
                      <th className="bg-white/[0.02] px-6 py-4 text-sm font-medium text-gray-200 whitespace-nowrap w-1/3 align-top">
                        販売価格<br/>
                        <span className="text-xs text-gray-500 font-normal">Pricing</span>
                      </th>
                      <td className="px-6 py-4">
                        各商品ページに記載の金額（税込）
                        <br/>
                        <span className="text-xs text-gray-500">
                          As displayed on each product/service page (tax included).
                        </span>
                      </td>
                    </tr>
                    <tr>
                      <th className="bg-white/[0.02] px-6 py-4 text-sm font-medium text-gray-200 whitespace-nowrap w-1/3 align-top">
                        商品代金以外の必要料金<br/>
                        <span className="text-xs text-gray-500 font-normal">Additional Fees</span>
                      </th>
                      <td className="px-6 py-4">
                        なし。表示価格以外の追加料金はかかりません。
                        <br/>
                        <span className="text-xs text-gray-500">
                          None. No additional charges beyond the displayed price.
                        </span>
                      </td>
                    </tr>
                    <tr>
                      <th className="bg-white/[0.02] px-6 py-4 text-sm font-medium text-gray-200 whitespace-nowrap w-1/3 align-top">
                        支払方法<br/>
                        <span className="text-xs text-gray-500 font-normal">Payment Methods</span>
                      </th>
                      <td className="px-6 py-4">
                        <ul className="list-disc list-inside space-y-1">
                          <li>Alipay</li>
                          <li>暗号資産（USDT / BEPUSDT）</li>
                          <li>GitHub Sponsors</li>
                        </ul>
                      </td>
                    </tr>
                    <tr>
                      <th className="bg-white/[0.02] px-6 py-4 text-sm font-medium text-gray-200 whitespace-nowrap w-1/3 align-top">
                        支払時期<br/>
                        <span className="text-xs text-gray-500 font-normal">Payment Period</span>
                      </th>
                      <td className="px-6 py-4">
                        ご注文時にお支払いいただきます。各決済手段はただちに処理されます。
                        <br/>
                        <span className="text-xs text-gray-500">
                          Payment is due at the time of purchase. All payment methods are processed immediately.
                        </span>
                      </td>
                    </tr>
                    <tr>
                      <th className="bg-white/[0.02] px-6 py-4 text-sm font-medium text-gray-200 whitespace-nowrap w-1/3 align-top">
                        引渡時期<br/>
                        <span className="text-xs text-gray-500 font-normal">Delivery Timing</span>
                      </th>
                      <td className="px-6 py-4">
                        お支払い確認後、ただちにサービスをご利用いただけます。トラフィック残高はお支払い完了後、即座にアカウントに反映されます。
                        <br/>
                        <span className="text-xs text-gray-500">
                          Service is available immediately after payment confirmation.
                          Traffic balance is credited to your account instantly upon successful payment processing.
                        </span>
                      </td>
                    </tr>
                    <tr>
                      <th className="bg-white/[0.02] px-6 py-4 text-sm font-medium text-gray-200 whitespace-nowrap w-1/3 align-top">
                        返品・交換について<br/>
                        <span className="text-xs text-gray-500 font-normal">Returns & Exchanges</span>
                      </th>
                      <td className="px-6 py-4">
                        <p className="font-medium text-gray-200 mb-2">
                          お客様都合による返品・キャンセル
                          <br/>
                          <span className="text-xs text-gray-500 font-normal">Customer-initiated returns/cancellations</span>
                        </p>
                        <p className="mb-3">
                          デジタルサービスの性質上、サービス提供後の返品はお受けできません。ただし、未使用の残高については、購入日から7日以内にお問い合わせいただいた場合、返金を検討いたします。
                        </p>
                        <span className="text-xs text-gray-500">
                          Due to the nature of digital services, returns are not accepted once the service has been delivered.
                          However, refunds for unused balance may be considered if requested within 7 days of the original purchase.
                        </span>

                        <hr className="border-white/5 my-4"/>

                        <p className="font-medium text-gray-200 mb-2">
                          サービスの不具合による返品・交換
                          <br/>
                          <span className="text-xs text-gray-500 font-normal">Returns/exchanges due to service defects</span>
                        </p>
                        <p className="mb-1">
                          当社に起因するサービス障害が発生した場合、以下のケースで返金対応いたします：
                        </p>
                        <ul className="list-disc list-inside space-y-1 mb-2">
                          <li>当社の責任による72時間以上の連続したサービス障害が発生した場合。</li>
                          <li>決済処理の不具合による二重請求や誤課金が発生した場合。</li>
                        </ul>
                        <span className="text-xs text-gray-500">
                          In the event of a service failure attributable to us, refunds will be issued in the following cases:
                          service outage lasting more than 72 consecutive hours caused by us;
                          duplicate or erroneous charges caused by payment processing errors.
                        </span>

                        <hr className="border-white/5 my-4"/>

                        <p>
                          返金のご相談はサポート窓口までご連絡ください。返金の可否は個別に判断いたします。承認された返金の処理には、通常5〜10営業日かかります。
                        </p>
                        <span className="text-xs text-gray-500">
                          To request a refund, please contact us through our support channels.
                          Refund requests are evaluated on a case-by-case basis.
                          Processing time for approved refunds is typically 5-10 business days.
                        </span>
                      </td>
                    </tr>
                    <tr>
                      <th className="bg-white/[0.02] px-6 py-4 text-sm font-medium text-gray-200 whitespace-nowrap w-1/3 align-top">
                        動作環境<br/>
                        <span className="text-xs text-gray-500 font-normal">Operating Environment</span>
                      </th>
                      <td className="px-6 py-4">
                        Windows、macOS、iOS、Android、Linux上のClash、Shadowrocket、Stashクライアントに対応しています。サービスのご利用にはインターネット接続が必要です。
                        <br/>
                        <span className="text-xs text-gray-500">
                          Compatible with Clash, Shadowrocket, and Stash clients on Windows, macOS, iOS, Android, and Linux.
                          A stable internet connection is required to use the service.
                        </span>
                      </td>
                    </tr>
                    <tr>
                      <th className="bg-white/[0.02] px-6 py-4 text-sm font-medium text-gray-200 whitespace-nowrap w-1/3 align-top">
                        お問い合わせ先<br/>
                        <span className="text-xs text-gray-500 font-normal">Contact</span>
                      </th>
                      <td className="px-6 py-4">
                        <a href={`mailto:${contactEmail}`}
                           className="text-indigo-400 hover:text-indigo-300 underline">
                          {contactEmail}
                        </a>
                        <br/>
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
