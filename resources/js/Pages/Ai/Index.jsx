import {useState} from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Modal from '@/Components/Modal.jsx';
import PrimaryButton from '@/Components/PrimaryButton.jsx';
import SecondaryButton from '@/Components/SecondaryButton.jsx';
import {Head, router, usePage} from '@inertiajs/react';

export default function Index({auth, aiKey, baseUrl, createThreshold, keepActiveThreshold}) {
  const [showingCreateModal, setShowingCreateModal] = useState(false);
  const {errors, flash} = usePage().props;

  function createKey(event) {
    event.preventDefault();
    router.post(route('ai.key.store'), {}, {
      onSuccess: () => setShowingCreateModal(false),
    });
  }

  return (
    <AuthenticatedLayout
      user={auth.user}
      header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">AI Key</h2>}
    >
      <Head title="AI Key"/>
      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
          {flash.success && (
            <div className="p-4 sm:p-6 bg-green-600 bg-opacity-10 text-green-600 rounded-lg">
              {flash.success}
            </div>
          )}
          {errors.error && (
            <div className="p-4 sm:p-6 bg-red-600 bg-opacity-10 text-red-600 rounded-lg">
              {errors.error}
            </div>
          )}

          <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div className="p-6 text-gray-900 space-y-6">
              <div>
                <h3 className="text-lg font-semibold text-gray-900">Current AI key</h3>
                <p className="mt-2 text-sm text-gray-600">
                  Your AI key is hosted by Sub2API and billed from your existing YAP balance.
                </p>
                <div className="mt-4 rounded-lg bg-gray-900 px-4 py-3 font-mono text-sm text-green-300 break-all">
                  {aiKey ?? 'No AI key created yet'}
                </div>
              </div>

              <div className="grid gap-4 md:grid-cols-3">
                <div className="rounded-lg bg-gray-50 p-4">
                  <div className="text-xs uppercase tracking-wide text-gray-500">Status</div>
                  <div className="mt-2 text-lg font-semibold text-gray-900">{auth.user.sub2api_key_status ?? 'not created'}</div>
                </div>
                <div className="rounded-lg bg-gray-50 p-4">
                  <div className="text-xs uppercase tracking-wide text-gray-500">Create threshold</div>
                  <div className="mt-2 text-lg font-semibold text-gray-900">Balance &gt; ${createThreshold}</div>
                </div>
                <div className="rounded-lg bg-gray-50 p-4">
                  <div className="text-xs uppercase tracking-wide text-gray-500">Keep active</div>
                  <div className="mt-2 text-lg font-semibold text-gray-900">Balance &gt; ${keepActiveThreshold}</div>
                </div>
              </div>

              <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                Resetting your subscription UUID will also rotate this AI key. Endpoint base URL: <span className="font-mono">{baseUrl}</span>
              </div>

              {!aiKey && (
                <PrimaryButton onClick={() => setShowingCreateModal(true)}>
                  Create AI Key
                </PrimaryButton>
              )}
            </div>
          </div>
        </div>
      </div>

      <Modal show={showingCreateModal} onClose={() => setShowingCreateModal(false)}>
        <form className="p-6" onSubmit={createKey}>
          <h2 className="text-lg font-medium text-gray-900">Create AI key</h2>
          <p className="mt-2 text-sm text-gray-600">
            You can only create an AI key when your balance is above ${createThreshold}.
          </p>
          <div className="mt-6 flex justify-end">
            <SecondaryButton onClick={() => setShowingCreateModal(false)}>Cancel</SecondaryButton>
            <PrimaryButton className="ms-3">Create AI Key</PrimaryButton>
          </div>
        </form>
      </Modal>
    </AuthenticatedLayout>
  );
}
