import {Head, Link} from '@inertiajs/react';

export default function Tos() {
  return (
    <>
      <Head title="Terms of Service"/>
      <div className="bg-gray-950 text-gray-100 min-h-screen flex flex-col antialiased">

        {/* Navigation */}
        <nav className="bg-gray-950/80 backdrop-blur-md border-b border-white/5">
          <div className="max-w-4xl mx-auto px-6 h-16 flex items-center justify-between">
            <Link href="/" className="text-xl font-bold tracking-tight text-white">YAP</Link>
          </div>
        </nav>

        <main className="flex-grow py-16 sm:py-24">
          <div className="max-w-4xl mx-auto px-6">
            <h1 className="text-3xl sm:text-4xl font-bold tracking-tight text-white mb-2">Terms of Service</h1>
            <p className="text-sm text-gray-500 mb-12">Last updated: March 5, 2026</p>

            <div className="space-y-10 text-gray-300 text-sm leading-relaxed">

              <section>
                <h2 className="text-lg font-semibold text-white mb-3">1. Acceptance of Terms</h2>
                <p>
                  By creating an account or using the YAP network acceleration service (the "Service") operated
                  by YAP ("we," "us," or "our"), you agree to be bound by these Terms of Service ("Terms").
                  If you do not agree to these Terms, you must not access or use the Service. We reserve the
                  right to modify these Terms at any time, and your continued use of the Service constitutes
                  acceptance of any modifications.
                </p>
              </section>

              <section>
                <h2 className="text-lg font-semibold text-white mb-3">2. Eligibility</h2>
                <p>
                  You must be at least 18 years of age to use the Service. By using the Service, you represent
                  and warrant that you meet this age requirement and have the legal capacity to enter into a
                  binding agreement. If you are using the Service on behalf of an organization, you represent
                  that you have the authority to bind that organization to these Terms.
                </p>
              </section>

              <section>
                <h2 className="text-lg font-semibold text-white mb-3">3. Account Registration</h2>
                <ul className="list-disc list-inside space-y-1 ml-2">
                  <li>You must provide accurate, current, and complete information during registration.</li>
                  <li>You are responsible for maintaining the confidentiality of your account credentials.</li>
                  <li>You are responsible for all activities that occur under your account.</li>
                  <li>You must notify us immediately of any unauthorized use of your account.</li>
                  <li>We reserve the right to suspend or terminate accounts that violate these Terms.</li>
                </ul>
              </section>

              <section>
                <h2 className="text-lg font-semibold text-white mb-3">4. Service Description</h2>
                <p>
                  YAP provides a network acceleration service that routes your internet traffic through our
                  proxy infrastructure. The Service includes account management, subscription management,
                  traffic package purchases, and access to proxy nodes. Service availability, speed, and
                  node locations may vary and are not guaranteed.
                </p>
              </section>

              <section>
                <h2 className="text-lg font-semibold text-white mb-3">5. Payments and Billing</h2>
                <ul className="list-disc list-inside space-y-1 ml-2">
                  <li>The Service operates on a pay-per-traffic model. You purchase traffic packages or top up your account balance.</li>
                  <li>All payments are processed through third-party payment providers (including Alipay, BEPUSDT, and Stripe).</li>
                  <li>Prices are displayed at the time of purchase and are subject to change with notice.</li>
                  <li>All purchases are final. Refunds are issued at our sole discretion and only in cases of Service failure attributable to us.</li>
                  <li>Unused balance remains in your account and does not expire while your account is active.</li>
                  <li>We reserve the right to modify pricing with reasonable advance notice.</li>
                </ul>
              </section>

              <section>
                <h2 className="text-lg font-semibold text-white mb-3">6. Refund Policy</h2>
                <p className="mb-2">
                  We strive to provide a reliable service. Refunds may be considered under the following circumstances:
                </p>
                <ul className="list-disc list-inside space-y-1 ml-2">
                  <li>Service outage lasting more than 72 consecutive hours due to issues on our end.</li>
                  <li>Duplicate or erroneous charges caused by payment processing errors.</li>
                  <li>Account balance that has not been used, requested within 7 days of the original purchase.</li>
                </ul>
                <p className="mt-2">
                  To request a refund, please contact us through our support channels. Refund requests are
                  evaluated on a case-by-case basis. Refunds for consumed traffic or partially used packages
                  are generally not available. Processing time for approved refunds is typically 5–10 business days.
                </p>
              </section>

              <section>
                <h2 className="text-lg font-semibold text-white mb-3">7. Acceptable Use</h2>
                <p className="mb-2">You agree NOT to use the Service to:</p>
                <ul className="list-disc list-inside space-y-1 ml-2">
                  <li>Violate any applicable local, national, or international law or regulation.</li>
                  <li>Transmit any material that is unlawful, harmful, threatening, abusive, defamatory, or otherwise objectionable.</li>
                  <li>Distribute malware, viruses, or any other malicious code.</li>
                  <li>Engage in unauthorized access to computer systems or networks.</li>
                  <li>Send unsolicited bulk communications (spam).</li>
                  <li>Infringe upon the intellectual property rights of others.</li>
                  <li>Engage in any activity that disrupts or interferes with the Service or its infrastructure.</li>
                  <li>Resell, redistribute, or share your account or subscription with unauthorized third parties.</li>
                  <li>Attempt to circumvent any usage limits or security measures of the Service.</li>
                </ul>
              </section>

              <section>
                <h2 className="text-lg font-semibold text-white mb-3">8. Service Availability and Modifications</h2>
                <ul className="list-disc list-inside space-y-1 ml-2">
                  <li>We strive to maintain high availability but do not guarantee uninterrupted or error-free service.</li>
                  <li>We may modify, suspend, or discontinue any part of the Service at any time with reasonable notice.</li>
                  <li>Scheduled maintenance will be announced in advance when possible.</li>
                  <li>We are not liable for any loss or damage resulting from Service interruptions beyond our reasonable control.</li>
                </ul>
              </section>

              <section>
                <h2 className="text-lg font-semibold text-white mb-3">9. Intellectual Property</h2>
                <p>
                  The Service, including its software, design, logos, and content, is protected by copyright,
                  trademark, and other intellectual property laws. YAP is open-source software licensed under
                  the MIT License. Third-party components are subject to their respective licenses. You may not
                  use our trademarks or branding without prior written consent.
                </p>
              </section>

              <section>
                <h2 className="text-lg font-semibold text-white mb-3">10. Termination</h2>
                <ul className="list-disc list-inside space-y-1 ml-2">
                  <li>You may terminate your account at any time by deleting it through your profile settings.</li>
                  <li>We may suspend or terminate your account immediately if you violate these Terms.</li>
                  <li>Upon termination, your right to use the Service ceases immediately.</li>
                  <li>Any remaining account balance at the time of termination due to Terms violation is forfeited.</li>
                  <li>Provisions that by their nature should survive termination (including limitation of liability, indemnification, and dispute resolution) shall survive.</li>
                </ul>
              </section>

              <section>
                <h2 className="text-lg font-semibold text-white mb-3">11. Disclaimer of Warranties</h2>
                <p>
                  THE SERVICE IS PROVIDED "AS IS" AND "AS AVAILABLE" WITHOUT WARRANTIES OF ANY KIND, EITHER
                  EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO IMPLIED WARRANTIES OF MERCHANTABILITY,
                  FITNESS FOR A PARTICULAR PURPOSE, AND NON-INFRINGEMENT. WE DO NOT WARRANT THAT THE SERVICE
                  WILL BE UNINTERRUPTED, SECURE, OR ERROR-FREE, OR THAT ANY DEFECTS WILL BE CORRECTED.
                </p>
              </section>

              <section>
                <h2 className="text-lg font-semibold text-white mb-3">12. Limitation of Liability</h2>
                <p>
                  TO THE MAXIMUM EXTENT PERMITTED BY APPLICABLE LAW, IN NO EVENT SHALL YAP, ITS OFFICERS,
                  DIRECTORS, EMPLOYEES, OR AGENTS BE LIABLE FOR ANY INDIRECT, INCIDENTAL, SPECIAL,
                  CONSEQUENTIAL, OR PUNITIVE DAMAGES, INCLUDING BUT NOT LIMITED TO LOSS OF PROFITS, DATA,
                  USE, OR GOODWILL, ARISING OUT OF OR IN CONNECTION WITH YOUR USE OF THE SERVICE, WHETHER
                  BASED ON WARRANTY, CONTRACT, TORT (INCLUDING NEGLIGENCE), OR ANY OTHER LEGAL THEORY.
                  OUR TOTAL LIABILITY SHALL NOT EXCEED THE AMOUNT YOU HAVE PAID TO US IN THE TWELVE (12)
                  MONTHS PRECEDING THE EVENT GIVING RISE TO THE CLAIM.
                </p>
              </section>

              <section>
                <h2 className="text-lg font-semibold text-white mb-3">13. Indemnification</h2>
                <p>
                  You agree to indemnify, defend, and hold harmless YAP and its affiliates, officers, directors,
                  employees, and agents from and against any and all claims, damages, losses, liabilities, costs,
                  and expenses (including reasonable attorneys' fees) arising out of or related to your use of the
                  Service, your violation of these Terms, or your violation of any rights of a third party.
                </p>
              </section>

              <section>
                <h2 className="text-lg font-semibold text-white mb-3">14. Governing Law and Dispute Resolution</h2>
                <p>
                  These Terms shall be governed by and construed in accordance with applicable laws, without
                  regard to conflict of law principles. Any disputes arising from these Terms or the Service
                  shall first be attempted to be resolved through good-faith negotiation. If negotiation fails,
                  disputes shall be resolved through binding arbitration in accordance with applicable arbitration
                  rules, unless prohibited by local law.
                </p>
              </section>

              <section>
                <h2 className="text-lg font-semibold text-white mb-3">15. Severability</h2>
                <p>
                  If any provision of these Terms is found to be unenforceable or invalid, that provision shall
                  be limited or eliminated to the minimum extent necessary so that the remaining provisions
                  shall remain in full force and effect.
                </p>
              </section>

              <section>
                <h2 className="text-lg font-semibold text-white mb-3">16. Entire Agreement</h2>
                <p>
                  These Terms, together with our Privacy Policy, constitute the entire agreement between you
                  and YAP regarding the use of the Service and supersede all prior agreements, understandings,
                  and communications, whether written or oral.
                </p>
              </section>

              <section>
                <h2 className="text-lg font-semibold text-white mb-3">17. Contact Us</h2>
                <p>
                  If you have any questions about these Terms, please contact us via our{' '}
                  <a href="https://t.me/yap_devs" target="_blank" rel="noopener noreferrer"
                     className="text-indigo-400 hover:text-indigo-300 underline">
                    Telegram community
                  </a>{' '}
                  or open an issue on our{' '}
                  <a href="https://github.com/yap-devs/yap" target="_blank" rel="noopener noreferrer"
                     className="text-indigo-400 hover:text-indigo-300 underline">
                    GitHub repository
                  </a>.
                </p>
              </section>

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
              </div>
            </div>
          </div>
        </footer>
      </div>
    </>
  );
}
