import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {Head, router} from '@inertiajs/react';
import {useState} from "react";
import {formatBytes} from "@/Utils/formatBytes";

export default function Dashboard({auth, clashUrl, unitPrice, servers}) {
  const [copyButton, setCopyButton] = useState('Copy URL');
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

  const renderClashUrl = () => {
    const clashUrlBlock = (
      <div className="mt-4">
        <div className="flex flex-col md:flex-row md:space-x-4">
          <button
            onClick={() => copyToClipboard(clashUrl)}
            className="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded shadow-md transition duration-300 ease-in-out transform hover:scale-105"
          >
            {copyButton}
          </button>
          <button
            onClick={() => window.location.href = 'clash://install-config?url=' + encodeURIComponent(clashUrl)}
            className="bg-[#1E90FF] hover:bg-[#4682B4] text-white font-bold py-2 px-4 rounded shadow-md transition duration-300 ease-in-out transform hover:scale-105 mt-4 md:mt-0"
          >
            Import to Clash
          </button>
          <button
            onClick={() => window.location.href = 'shadowrocket://add/sub://' + btoa(clashUrl)}
            className="bg-[#8A2BE2] hover:bg-[#9370DB] text-white font-bold py-2 px-4 rounded shadow-md transition duration-300 ease-in-out transform hover:scale-105 mt-4 md:mt-0"
          >
            Import to Shadowrocket
          </button>
          <button
            onClick={() => window.location.href = 'stash://install-config?url=' + encodeURIComponent(clashUrl)}
            className="bg-[#FF4500] hover:bg-[#FF6347] text-white font-bold py-2 px-4 rounded shadow-md transition duration-300 ease-in-out transform hover:scale-105 mt-4 md:mt-0"
          >
            Import to Stash
          </button>
        </div>
      </div>
    );

    if (auth.user.is_valid) {
      if (auth.user.is_low_priority) {
        return (
          <div className="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4" role="alert">
            <p className="font-bold">Your account is low priority!</p>
            <p className="mt-4">
              Please consider
              <button onClick={() => window.location.href = route('profile.edit')}
                      className="text-blue-500 hover:underline px-1" style={{cursor: 'pointer'}}>upgrading</button>
              your account to high priority to get the best experience.
            </p>
            {clashUrlBlock}
          </div>
        )
      }

      return clashUrlBlock;
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
  const renderTrafficUnpaid = () => {
    const sharedElements = (
      <>⚠️ Pending traffic: <strong>{formatBytes(auth.user.traffic_unpaid)}</strong>.</>
    );

    if (auth.user.traffic_unpaid === 0) {
      return (
        <>
          {sharedElements} &nbsp;
          No pending traffic, how dare you.
        </>
      );
    }

    return (
      <>
        {sharedElements} &nbsp;
        It will be settled soon.
      </>
    );
  };

  const renderServers = () => {
    if (servers.length === 0) {
      return (
        <div className="p-4 bg-gray-100 text-red-600 rounded-lg">
          No servers found.
        </div>
      );
    }

    const isUnavailable = (user, server) => (user.is_low_priority && !server.for_low_priority) || !user.is_valid;
    return (
      <ul className="divide-y divide-gray-200">
        {servers.map((server, index) => (
          <li
            key={server.id}
            className={`pl-3 pr-4 py-3 flex items-center justify-between text-sm
            ${isUnavailable(auth.user, server) ? "opacity-50 cursor-not-allowed" : ""}`}
          >
            <div className="w-0 flex-1 flex items-center">
            <span className="flex-1 w-0 ml-2">
              Server {index}: <strong>{server.name}</strong>
            </span>
            </div>
            <div className="ml-4 flex-shrink-0">
              Rate: <strong>{server.rate}x</strong>
            </div>
          </li>
        ))}
      </ul>
    );
  }

  return (<AuthenticatedLayout
    user={auth.user}
    header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Dashboard</h2>}
  >
    <Head title="Dashboard"/>

    <div className="py-12">
      <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div className="p-6 bg-white text-black rounded-lg shadow-md">
            <h1 className="text-2xl font-bold">Welcome Back, {auth.user.name}!</h1>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
              <div className="p-4 bg-blue-50 rounded-lg">
                <h2 className="text-xl font-semibold">Your details</h2>
                <p className="mt-2"><strong>Total Data used:</strong> {totalTraffic}</p>
                <p className="mt-2">{renderTrafficUnpaid()}</p>
                <p className="mt-2"><strong>Rate:</strong> ${unitPrice} per GB by default.</p>
                <p className="mt-2">
                  <strong>Please consider purchasing traffic packages to get better rates.</strong>
                </p>
                <button
                  onClick={() => router.get(route('profile.edit'))}
                  className="mt-4 bg-gradient-to-r from-blue-500 to-purple-600 hover:from-purple-600 hover:to-blue-500 text-white font-bold py-2 px-4 rounded cursor-pointer transition duration-300 ease-in-out transform hover:scale-105"
                >
                  Go to Charge
                </button>
                <button
                  onClick={() => router.get(route('package'))}
                  className="ml-6 mt-4 bg-gradient-to-r from-red-500 to-yellow-500 hover:from-yellow-500 hover:to-red-500 text-white font-bold py-2 px-4 rounded-full shadow-lg transition duration-300 ease-in-out transform hover:scale-110"
                >
                  🚀 Limited Time Offer! Buy More Traffic Now!
                </button>
              </div>
              <div className="p-4 bg-yellow-50 rounded-lg">
                <h2 className="text-xl font-semibold">Subscription Information</h2>
                {renderClashUrl()}
              </div>
            </div>
            <div className="mt-4">
              <h2 className="text-xl font-semibold">Servers</h2>
              {renderServers()}
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>);
}
