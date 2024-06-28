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

  const renderPayReminder = () => {
    if (auth.user.is_valid) {
      return (<div className="bg-green-100 border-l-4 border-green-500 text-green-700 p-4" role="alert">
        <p className="mt-4">Your Clash URL is:</p>
        <p className="mt-2 text-blue-500">{clashUrl}</p>
        <button
          onClick={() => copyToClipboard(clashUrl)}
          className="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded mt-4"
        >
          {copyButton}
        </button>
      </div>)
    }

    return (<div className="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4" role="alert">
      <p className="font-bold">Your account is limited!</p>
      <p className="mt-4">Please charge your account to continue using the service.</p>
    </div>);
  }

  const formatBytes = (bytes, decimals = 2) => {
    if (!+bytes) return '0 Bytes'

    const k = 1024
    const dm = decimals < 0 ? 0 : decimals
    const sizes = ['Bytes', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB', 'ZiB', 'YiB']

    const i = Math.floor(Math.log(bytes) / Math.log(k))

    return `${parseFloat((bytes / Math.pow(k, i)).toFixed(dm))} ${sizes[i]}`
  }

  const totalTraffic = formatBytes(auth.user.traffic_downlink + auth.user.traffic_uplink);

  return (<AuthenticatedLayout
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
            <p className="mt-4">You have used {totalTraffic} of your &infin; data limit.</p>
            {renderPayReminder()}
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>);
}
