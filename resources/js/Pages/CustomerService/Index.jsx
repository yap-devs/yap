import {useState} from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {Head, router} from '@inertiajs/react';
import Modal from "@/Components/Modal.jsx";
import SecondaryButton from "@/Components/SecondaryButton.jsx";
import PrimaryButton from "@/Components/PrimaryButton.jsx";
import {trans} from '@/Utils/i18n';

export default function Index({auth, resetSubscriptionPrice, clientDownloads}) {
  const [confirmingResetSubscriptionUrl, setConfirmingResetSubscriptionUrl] = useState(false);
  const closeModal = () => {
    setConfirmingResetSubscriptionUrl(false);
  };

  function handleSubmit(e) {
    e.preventDefault();
    router.post(route('customer.service.resetSubscription'));
    closeModal();
  }

  return (<AuthenticatedLayout
    user={auth.user}
    header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">
      {trans('customer_service.title')}
    </h2>}
  >
    <Head title={trans('customer_service.title')}/>
    <div className="py-12">
      <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div className="p-6 text-gray-900">
            {/* Telegram section */}
            <div className="pl-6 pb-6 pt-8 mb-6 rounded shadow-lg bg-gradient-to-r from-blue-50 via-blue-100 to-blue-200 border border-blue-300">
              <h2 className="text-2xl font-bold text-blue-700 flex items-center">
                <span className="mr-2">📢</span> Telegram
              </h2>
              <p className="text-base text-gray-700 mt-2">
                {trans('customer_service.telegram_body')}
              </p>
              <div className="mt-4 text-lg font-semibold text-blue-600 hover:underline">
                🔗 <a href="https://t.me/yap_devs" target="_blank" rel="noreferrer noopener">@yap_devs</a>
              </div>
            </div>

            {/*Client Download section */}
            <div className="text-gray-900 pl-6 pb-6 pt-8 rounded shadow mb-6 bg-blue-50">
              <h2 className="text-lg font-semibold text-gray-800">{trans('customer_service.client_download')}</h2>
              <p className="text-sm text-gray-700 mt-2">
                {trans('customer_service.client_download_body')}
              </p>
              <div className="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                {clientDownloads.map((download) => (
                  <div
                    key={download.key}
                    className="flex flex-col justify-between rounded-lg border border-blue-100 bg-white p-4 shadow-sm transition hover:border-blue-300 hover:shadow-md"
                  >
                    <div>
                      <h3 className="font-semibold text-gray-900">{download.label}</h3>
                    </div>
                    <div className="mt-4 flex flex-wrap items-center gap-3 text-sm">
                      <a
                        href={download.url}
                        className="rounded-md bg-blue-600 px-3 py-2 font-semibold text-white shadow-sm hover:bg-blue-700"
                        target="_blank"
                        rel="noreferrer noopener"
                      >
                        {trans('customer_service.download_primary')}
                      </a>
                      <a
                        href={download.github_url}
                        className="rounded-md border border-blue-200 px-3 py-2 font-semibold text-blue-700 hover:border-blue-300 hover:bg-blue-50"
                        target="_blank"
                        rel="noreferrer noopener"
                      >
                        {trans('customer_service.download_github')}
                      </a>
                    </div>
                  </div>
                ))}
              </div>
            </div>

            {/*Reset Subscription URL section*/}
            <div className="text-gray-900 pl-6 pb-6 pt-8 rounded shadow mb-6 bg-blue-50">
              <h2 className="text-lg font-semibold text-gray-800">{trans('customer_service.reset_subscription_url')}</h2>
              <p className="text-sm text-gray-700 mt-2">
                {trans('customer_service.reset_body')}
              </p>
              <p className="text-sm text-gray-700 mt-1">
                {trans('customer_service.reset_ai_note')}
              </p>
              <p className="text-sm text-gray-700 font-bold">
                {trans('customer_service.reset_cost_note', {amount: resetSubscriptionPrice})}
              </p>
              <div className="mt-2 space-x-4">
                <PrimaryButton onClick={() => setConfirmingResetSubscriptionUrl(true)}>
                  {trans('customer_service.reset_subscription_url')}
                </PrimaryButton>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <Modal show={confirmingResetSubscriptionUrl} onClose={closeModal}>
      <form className="p-6" onSubmit={handleSubmit}>
        <h2 className="text-lg font-medium text-gray-900">
          {trans('customer_service.reset_confirm_title')}
        </h2>

        <p className="mt-1 text-sm text-gray-600">
          {trans('customer_service.reset_confirm_body', {amount: resetSubscriptionPrice})}
        </p>

        <div className="mt-6 flex justify-end">
          <SecondaryButton onClick={closeModal}>{trans('common.cancel')}</SecondaryButton>

          <PrimaryButton className="ms-3">
            {trans('customer_service.reset_subscription_url')}
          </PrimaryButton>
        </div>
      </form>
    </Modal>
  </AuthenticatedLayout>);
}
