import {Head, Link} from '@inertiajs/react';

export default function Policy() {
  return (
    <>
      <Head title="Privacy Policy"/>
      <div className="bg-gray-950 text-gray-100 min-h-screen flex flex-col antialiased">

        {/* Navigation */}
        <nav className="bg-gray-950/80 backdrop-blur-md border-b border-white/5">
          <div className="max-w-4xl mx-auto px-6 h-16 flex items-center justify-between">
            <Link href="/" className="text-xl font-bold tracking-tight text-white">YAP</Link>
          </div>
        </nav>

        <main className="flex-grow py-16 sm:py-24">
          <div className="max-w-4xl mx-auto px-6">
            <h1 className="text-3xl sm:text-4xl font-bold tracking-tight text-white mb-2">Privacy Policy</h1>
            <p className="text-sm text-gray-500 mb-12">Last updated: March 5, 2026</p>

            <div className="space-y-10 text-gray-300 text-sm leading-relaxed">

              <section>
                <h2 className="text-lg font-semibold text-white mb-3">1. Introduction</h2>
                <p>
                  YAP ("we," "us," or "our") operates the YAP network acceleration service (the "Service").
                  This Privacy Policy explains how we collect, use, disclose, and safeguard your personal
                  information when you visit our website or use our Service. By accessing or using the Service,
                  you agree to the terms of this Privacy Policy. If you do not agree, please do not use the Service.
                </p>
              </section>

              <section>
                <h2 className="text-lg font-semibold text-white mb-3">2. Information We Collect</h2>
                <h3 className="text-base font-medium text-gray-200 mb-2">2.1 Information You Provide</h3>
                <ul className="list-disc list-inside space-y-1 ml-2">
                  <li>Account registration data: email address, username, and password.</li>
                  <li>Payment information: transaction identifiers processed through third-party payment providers (Alipay, BEPUSDT, Stripe). We do not store full payment card numbers.</li>
                  <li>Communications: any messages you send to our support channels.</li>
                  <li>GitHub account information if you choose to link your GitHub account via OAuth.</li>
                </ul>

                <h3 className="text-base font-medium text-gray-200 mt-4 mb-2">2.2 Information Collected Automatically</h3>
                <ul className="list-disc list-inside space-y-1 ml-2">
                  <li>Usage data: bandwidth consumption, connection timestamps, and subscription status.</li>
                  <li>Device and browser information: IP address, browser type, operating system, and device identifiers.</li>
                  <li>Cookies and similar technologies for session management and analytics.</li>
                </ul>

                <h3 className="text-base font-medium text-gray-200 mt-4 mb-2">2.3 Information We Do NOT Collect</h3>
                <ul className="list-disc list-inside space-y-1 ml-2">
                  <li>We do not log, monitor, or store the content of your network traffic.</li>
                  <li>We do not record the websites you visit or the applications you use while connected to our Service.</li>
                </ul>
              </section>

              <section>
                <h2 className="text-lg font-semibold text-white mb-3">3. How We Use Your Information</h2>
                <p className="mb-2">We use the information we collect for the following purposes:</p>
                <ul className="list-disc list-inside space-y-1 ml-2">
                  <li>To create and manage your account and provide the Service.</li>
                  <li>To process payments and maintain billing records.</li>
                  <li>To communicate with you regarding your account, transactions, and Service updates.</li>
                  <li>To detect, prevent, and address fraud, abuse, and technical issues.</li>
                  <li>To comply with applicable legal obligations.</li>
                  <li>To improve and optimize the Service.</li>
                </ul>
              </section>

              <section>
                <h2 className="text-lg font-semibold text-white mb-3">4. Legal Basis for Processing (GDPR)</h2>
                <p className="mb-2">If you are located in the European Economic Area (EEA), our legal bases for processing your personal data include:</p>
                <ul className="list-disc list-inside space-y-1 ml-2">
                  <li><strong className="text-gray-200">Contract performance:</strong> processing necessary to provide the Service you requested.</li>
                  <li><strong className="text-gray-200">Legitimate interests:</strong> fraud prevention, security, and Service improvement.</li>
                  <li><strong className="text-gray-200">Legal obligation:</strong> compliance with applicable laws and regulations.</li>
                  <li><strong className="text-gray-200">Consent:</strong> where you have given explicit consent for specific processing activities.</li>
                </ul>
              </section>

              <section>
                <h2 className="text-lg font-semibold text-white mb-3">5. Data Sharing and Disclosure</h2>
                <p className="mb-2">We do not sell your personal information. We may share your data with:</p>
                <ul className="list-disc list-inside space-y-1 ml-2">
                  <li><strong className="text-gray-200">Payment processors:</strong> Alipay, BEPUSDT, Stripe, and other payment providers to process transactions.</li>
                  <li><strong className="text-gray-200">Service providers:</strong> hosting, analytics, and error-tracking services (e.g., Sentry) that assist in operating the Service.</li>
                  <li><strong className="text-gray-200">Law enforcement:</strong> when required by law, court order, or governmental regulation.</li>
                  <li><strong className="text-gray-200">Business transfers:</strong> in connection with a merger, acquisition, or sale of assets.</li>
                </ul>
              </section>

              <section>
                <h2 className="text-lg font-semibold text-white mb-3">6. Data Retention</h2>
                <p>
                  We retain your personal information for as long as your account is active or as needed to provide
                  the Service. Upon account deletion, we will remove or anonymize your personal data within 30 days,
                  except where retention is required by law or for legitimate business purposes (e.g., fraud prevention,
                  financial record-keeping).
                </p>
              </section>

              <section>
                <h2 className="text-lg font-semibold text-white mb-3">7. Data Security</h2>
                <p>
                  We implement industry-standard technical and organizational measures to protect your personal
                  information, including encryption in transit (TLS/SSL), secure password hashing, and access
                  controls. However, no method of transmission over the Internet or electronic storage is 100%
                  secure, and we cannot guarantee absolute security.
                </p>
              </section>

              <section>
                <h2 className="text-lg font-semibold text-white mb-3">8. Your Rights</h2>
                <p className="mb-2">Depending on your jurisdiction, you may have the following rights:</p>
                <ul className="list-disc list-inside space-y-1 ml-2">
                  <li><strong className="text-gray-200">Access:</strong> request a copy of the personal data we hold about you.</li>
                  <li><strong className="text-gray-200">Rectification:</strong> request correction of inaccurate or incomplete data.</li>
                  <li><strong className="text-gray-200">Erasure:</strong> request deletion of your personal data.</li>
                  <li><strong className="text-gray-200">Restriction:</strong> request restriction of processing in certain circumstances.</li>
                  <li><strong className="text-gray-200">Portability:</strong> request transfer of your data in a machine-readable format.</li>
                  <li><strong className="text-gray-200">Objection:</strong> object to processing based on legitimate interests.</li>
                </ul>
                <p className="mt-2">
                  To exercise any of these rights, please contact us using the information provided below.
                </p>
              </section>

              <section>
                <h2 className="text-lg font-semibold text-white mb-3">9. Cookies</h2>
                <p>
                  We use essential cookies to maintain your session and authenticate your account. We may also
                  use analytics cookies to understand how the Service is used. You can control cookie preferences
                  through your browser settings. Disabling essential cookies may impair Service functionality.
                </p>
              </section>

              <section>
                <h2 className="text-lg font-semibold text-white mb-3">10. Third-Party Links</h2>
                <p>
                  The Service may contain links to third-party websites or services. We are not responsible for
                  the privacy practices of those third parties. We encourage you to review their privacy policies
                  before providing any personal information.
                </p>
              </section>

              <section>
                <h2 className="text-lg font-semibold text-white mb-3">11. Children's Privacy</h2>
                <p>
                  The Service is not intended for individuals under the age of 18. We do not knowingly collect
                  personal information from children. If we become aware that we have collected data from a child,
                  we will take steps to delete it promptly.
                </p>
              </section>

              <section>
                <h2 className="text-lg font-semibold text-white mb-3">12. Changes to This Policy</h2>
                <p>
                  We may update this Privacy Policy from time to time. We will notify you of material changes by
                  posting the updated policy on this page with a revised "Last updated" date. Your continued use
                  of the Service after any changes constitutes acceptance of the updated policy.
                </p>
              </section>

              <section>
                <h2 className="text-lg font-semibold text-white mb-3">13. Contact Us</h2>
                <p>
                  If you have any questions or concerns about this Privacy Policy or our data practices, please
                  contact us via our{' '}
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
