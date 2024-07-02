import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {Head} from '@inertiajs/react';
import {useState} from "react";
import {formatBytes} from "@/Utils/formatBytes";

export default function Dashboard({auth, clashUrl, unitPrice}) {
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
      <p className="mt-4">
        Please
        <button onClick={() => window.location.href = route('profile.edit')}
                className="text-blue-500 hover:underline px-1" style={{cursor: 'pointer'}}>charge</button>
        your account to continue using the service.
      </p>
    </div>);
  }

  const totalTraffic = formatBytes(auth.user.traffic_downlink + auth.user.traffic_uplink);
  const trafficUnpaid = formatBytes(auth.user.traffic_unpaid);

  return (<AuthenticatedLayout
    user={auth.user}
    header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Dashboard</h2>}
  >
    <Head title="Dashboard"/>

    <div className="py-12">
      <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div className="p-6 text-gray-900">
            <div className="text-lg">Hello, {auth.user.name}! It's good to see you again.</div>
            <div className="mt-4">You're currently logged in with the email: <strong>{auth.user.email}</strong></div>
            <div className="mt-4">So far, you have used <strong>{totalTraffic}</strong> of your data.</div>
            <div className="mt-4">You have <strong>{trafficUnpaid}</strong> of traffic that hasn't been settled yet.
              Don't worry, it will be charged when it reaches 1GB at the next scheduled check.
            </div>
            <div className="my-4">Just a heads up, the current rate is <span
              className="text-indigo-700">${unitPrice}</span> per GB.
            </div>
            {renderPayReminder()}
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>);
}
