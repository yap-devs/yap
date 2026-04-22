import {useState} from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Modal from '@/Components/Modal.jsx';
import PrimaryButton from '@/Components/PrimaryButton.jsx';
import SecondaryButton from '@/Components/SecondaryButton.jsx';
import {Head, router} from '@inertiajs/react';

function CopyableField({label, value, copiedField, onCopy, dark = false}) {
  const isCopied = copiedField === value;

  return (
    <div
      className={`flex items-center justify-between gap-2 rounded-lg px-4 py-3 cursor-pointer transition-colors ${
        dark
          ? 'bg-gray-900 hover:bg-gray-800'
          : 'bg-gray-50 hover:bg-gray-100 border border-gray-200'
      }`}
      onClick={() => onCopy(value)}
      title="Click to copy"
    >
      <div className="flex items-center gap-2 min-w-0">
        {label && (
          <span className="shrink-0 rounded bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800">{label}</span>
        )}
        <code className={`font-mono text-sm break-all ${dark ? 'text-green-300' : 'text-gray-900'}`}>
          {value}
        </code>
      </div>
      <span className={`shrink-0 text-xs ${dark ? 'text-gray-400' : 'text-gray-500'}`}>
        {isCopied ? 'Copied!' : 'Click to copy'}
      </span>
    </div>
  );
}

export default function Index({auth, aiKey, baseUrl, createThreshold, keepActiveThreshold}) {
  const [showingCreateModal, setShowingCreateModal] = useState(false);
  const [copiedField, setCopiedField] = useState(null);

  function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
      setCopiedField(text);
      setTimeout(() => setCopiedField(null), 2000);
    });
  }

  function createKey(event) {
    event.preventDefault();
    router.post(route('ai.key.store'));
    setShowingCreateModal(false);
  }

  return (
    <AuthenticatedLayout
      user={auth.user}
      header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">AI Key</h2>}
    >
      <Head title="AI Key"/>
      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
          <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div className="p-6 text-gray-900 space-y-6">
              <div>
                <h3 className="text-lg font-semibold text-gray-900">Current AI key</h3>
                <p className="mt-2 text-sm text-gray-600">
                  Use this key with your existing YAP balance. Keep it private and rotate it if you believe it has been exposed.
                </p>
                {aiKey ? (
                  <div className="mt-4">
                    <CopyableField value={aiKey} copiedField={copiedField} onCopy={copyToClipboard} dark/>
                  </div>
                ) : (
                  <div className="mt-4 rounded-lg bg-gray-900 px-4 py-3 font-mono text-sm text-gray-500">
                    No AI key created yet
                  </div>
                )}
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
                Resetting your subscription UUID will also rotate this AI key.
              </div>

              {aiKey && baseUrl && (
                <div className="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900 space-y-3">
                  <p className="font-medium">API Endpoint</p>
                  <p className="text-xs text-blue-700">Choose the base URL that matches your client:</p>
                  <div className="space-y-2">
                    <CopyableField label="Responses API" value={baseUrl} copiedField={copiedField} onCopy={copyToClipboard}/>
                    <CopyableField label="Chat Completions" value={`${baseUrl}/v1`} copiedField={copiedField} onCopy={copyToClipboard}/>
                  </div>
                  <p className="text-xs text-blue-600">
                    Use <strong>Responses API</strong> for Codex CLI.
                    Use <strong>Chat Completions</strong> for OpenCode, Cursor, and other OpenAI-compatible clients.
                  </p>
                </div>
              )}

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
