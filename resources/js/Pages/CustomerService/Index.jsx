import {useState} from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {Head, router, usePage} from '@inertiajs/react';
import Modal from "@/Components/Modal.jsx";
import SecondaryButton from "@/Components/SecondaryButton.jsx";
import PrimaryButton from "@/Components/PrimaryButton.jsx";

export default function Index({auth, resetSubscriptionPrice}) {
  const [confirmingResetSubscriptionUrl, setConfirmingResetSubscriptionUrl] = useState(false);
  const closeModal = () => {
    setConfirmingResetSubscriptionUrl(false);
  };

  const {errors} = usePage().props;

  function handleSubmit(e) {
    e.preventDefault();
    router.post(route('customer.service.resetSubscription'));
    closeModal();
  }

  return (<AuthenticatedLayout
    user={auth.user}
    header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">
      Customer Service
    </h2>}
  >
    <Head title="Customer Service"/>
    <div className="py-12">
      <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
        {
          errors.success && (
            <div className="p-4 sm:p-8 bg-green-600 bg-opacity-10 text-green-600 rounded-lg">
              <div className="flex items-center">
                <span className="ml-2">{errors.success}</span>
              </div>
            </div>
          )
        }
        {
          errors.error && (
            <div className="p-4 sm:p-8 bg-red-600 bg-opacity-10 text-red-600 rounded-lg">
              <div className="flex items-center">
                <span className="ml-2">{errors.error}</span>
              </div>
            </div>
          )
        }

        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div className="p-6 text-gray-900">
            {/* Telegram section */}
            <div className="pl-6 pb-6 pt-8 mb-6 rounded shadow-lg bg-gradient-to-r from-blue-50 via-blue-100 to-blue-200 border border-blue-300">
              <h2 className="text-2xl font-bold text-blue-700 flex items-center">
                <span className="mr-2">📢</span> Telegram
              </h2>
              <p className="text-base text-gray-700 mt-2">
                Join our Telegram channel to get the latest updates and support!
              </p>
              <div className="mt-4 text-lg font-semibold text-blue-600 hover:underline">
                🔗 <a href="https://t.me/yap_devs" target="_blank" rel="noreferrer noopener">@yap_devs</a>
              </div>
            </div>

            {/*Client Download section */}
            <div className="text-gray-900 pl-6 pb-6 pt-8 rounded shadow mb-6 bg-blue-50">
              <h2 className="text-lg font-semibold text-gray-800">Client Download</h2>
              <p className="text-sm text-gray-700 mt-2">
                Download the latest version of the client software.
              </p>
              <div className="underline mt-2 space-x-4">
                <a href="https://github.com/clash-verge-rev/clash-verge-rev/releases/latest" target="_blank"
                   rel="noreferrer noopener">
                  For Windows && MacOS && Linux
                </a>
                <a href="https://github.com/MetaCubeX/ClashMetaForAndroid/releases/latest" target="_blank"
                   rel="noreferrer noopener">
                  For Android
                </a>
              </div>
            </div>

            {/*Reset Subscription URL section*/}
            <div className="text-gray-900 pl-6 pb-6 pt-8 rounded shadow mb-6 bg-blue-50">
              <h2 className="text-lg font-semibold text-gray-800">Reset Subscription URL</h2>
              <p className="text-sm text-gray-700 mt-2">
                If you need to reset your subscription URL, please click the button below.
              </p>
              <p className="text-sm text-gray-700 font-bold">
                Note: This will cost you ${resetSubscriptionPrice} per reset.
              </p>
              <div className="mt-2 space-x-4">
                <PrimaryButton onClick={() => setConfirmingResetSubscriptionUrl(true)}>
                  Reset Subscription URL
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
          Are you sure you want to reset your subscription URL?
        </h2>

        <p className="mt-1 text-sm text-gray-600">
          Resetting your subscription URL will cost you ${resetSubscriptionPrice}.
        </p>

        <div className="mt-6 flex justify-end">
          <SecondaryButton onClick={closeModal}>Cancel</SecondaryButton>

          <PrimaryButton className="ms-3">
            Reset Subscription URL
          </PrimaryButton>
        </div>
      </form>
    </Modal>
  </AuthenticatedLayout>);
}
