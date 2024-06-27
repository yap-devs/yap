import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {Head} from '@inertiajs/react';
import {useState} from "react";

export default function Dashboard({auth, clashUrl}) {
  const [copyButton, setCopyButton] = useState('Copy');
  const copyToClipboard = async text => {
    try {
      await navigator.clipboard.writeText(text);
      setCopyButton('Copied!');
      setTimeout(() => {
        setCopyButton('Copy');
      }, 2000);
    } catch (err) {
      console.error('Failed to copy: ', err);
    }
  }

  return (
    <AuthenticatedLayout
      user={auth.user}
      header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Dashboard</h2>}
    >
      <Head title="Dashboard"/>

      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div className="p-6 text-gray-900">
              <p className="text-lg">Welcome back, {auth.user.name}!</p>
              <p className="mt-4">You're logged in with the email {auth.user.email}</p>
              <p className="mt-4">Your Clash URL is:</p>
              <p className="mt-2 text-blue-500">{clashUrl}</p>
              <button
                onClick={() => copyToClipboard(clashUrl)}
                className="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded mt-4"
              >
                {copyButton}
              </button>
            </div>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
