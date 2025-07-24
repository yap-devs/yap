import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {Head, router, usePage} from '@inertiajs/react';
import SecondaryButton from "@/Components/SecondaryButton.jsx";
import {formatBytes} from "@/Utils/formatBytes.js";
import {formatPrice} from "@/Utils/formatPrice.js";
import Modal from "@/Components/Modal.jsx";
import PrimaryButton from "@/Components/PrimaryButton.jsx";
import {useState} from 'react';


export default function Index({auth, packages, userPackages}) {
  const [confirmingBuy, setConfirmingBuy] = useState(false);

  const {errors} = usePage().props;

  function handleSubmit(e) {
    e.preventDefault();
    router.visit(route('package.buy', confirmingBuy), {
      method: 'post',
      preserveScroll: true,
    });
    setConfirmingBuy(false);
  }

  const calcPercentageOff = (rawPrice, price) => {
    return `${(((rawPrice - price) / rawPrice) * 100).toFixed(0)}% OFF`;
  }

  return (
    <AuthenticatedLayout
      user={auth.user}
      header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Package</h2>}
    >
      <Head title="Package"/>
      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          {errors.error && (
            <div className="p-4 sm:p-8 bg-red-600 bg-opacity-10 text-red-600 rounded-lg">
              <div className="flex items-center">
                <svg className="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                     xmlns="http://www.w3.org/2000/svg">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
                <span className="ml-2">{errors.error}</span>
              </div>
            </div>
          )}
          {errors.success && (
            <div className="p-4 sm:p-8 bg-green-600 bg-opacity-10 text-green-600 rounded-lg">
              <div className="flex items-center">
                <svg className="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                     xmlns="http://www.w3.org/2000/svg">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7"/>
                </svg>
                <span className="ml-2">{errors.success}</span>
              </div>
            </div>
          )}

          {/* Recharge Button Section */}
          <div className="mb-6 flex justify-center">
            <button
              onClick={() => router.get(route('profile.edit'))}
              className="inline-flex items-center bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-white font-semibold py-3 px-6 rounded-lg shadow-lg transition duration-300 ease-in-out transform hover:scale-105 hover:shadow-xl"
            >
              <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              💰 Recharge Account
            </button>
          </div>

          <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div className="p-6 text-gray-900" style={{overflowX: 'auto'}}>
              <table className="min-w-full divide-y divide-gray-200">
                <thead>
                <tr>
                  <th
                    className="px-6 py-3 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">Name
                  </th>
                  <th
                    className="px-6 py-3 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">Description
                  </th>
                  <th
                    className="px-6 py-3 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">Traffic
                  </th>
                  <th
                    className="px-6 py-3 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">Price
                  </th>
                  <th
                    className="px-6 py-3 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">Validity
                    Period (days)
                  </th>
                  <th className="px-6 py-3 bg-gray-50"></th>
                </tr>
                </thead>
                <tbody className="bg-white divide-y divide-gray-200">
                {packages.map((_package) => (
                  <tr key={_package.id} className="hover:bg-yellow-50 transition duration-300 ease-in-out">
                    <td className="px-6 py-4 whitespace-no-wrap">
                      <div className="text-sm leading-5 text-gray-900">{_package.name}</div>
                    </td>
                    <td className="px-6 py-4 whitespace-no-wrap">
                      <div className="text-sm leading-5 text-gray-900">{_package.description}</div>
                    </td>
                    <td className="px-6 py-4 whitespace-no-wrap">
                      <div className="text-sm leading-5 text-gray-900">{formatBytes(_package.traffic_limit)}</div>
                    </td>
                    <td className="px-6 py-4 whitespace-no-wrap">
                      <div className="text-sm leading-5 text-gray-900">
                        <span className="line-through text-gray-500 mr-1">{formatPrice(_package.original_price)}</span>
                        <span className="text-green-600 font-bold text-lg">{formatPrice(_package.price)}</span>
                        <span className="relative inline-block">
                          <span
                            className="absolute top-0 right-0 transform -rotate-12 bg-red-500 text-white font-bold py-0.5 px-1.5 rounded shadow-lg shake text-xs"
                            style={{whiteSpace: 'nowrap'}}
                          >
                            {calcPercentageOff(_package.original_price, _package.price)}
                          </span>
                        </span>
                      </div>
                    </td>
                    <td className="px-6 py-4 whitespace-no-wrap">
                      <div className="text-sm leading-5 text-gray-900">{_package.duration_days}</div>
                    </td>
                    <td className="px-6 py-4 whitespace-no-wrap text-right text-sm leading-5 font-medium">
                      <div className="text-sm leading-5 text-gray-900">
                        <SecondaryButton
                          onClick={() => setConfirmingBuy(_package.id)}
                          className="mt-4 bg-gradient-to-r from-red-500 to-yellow-500 hover:from-yellow-500 hover:to-red-500 text-white font-bold py-2 px-4 rounded-full shadow-lg transition duration-300 ease-in-out transform hover:scale-110"
                        >
                          🚀 Buy Now!
                        </SecondaryButton>
                      </div>
                    </td>
                  </tr>
                ))}
                </tbody>
              </table>
            </div>
          </div>
          <div className="mt-6">
            <h2 className="text-lg font-medium text-gray-900">Your Packages</h2>
            <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
              <div className="p-6 text-gray-900" style={{overflowX: 'auto'}}>
                <table className="min-w-full divide-y divide-gray-200">
                  <thead>
                  <tr>
                    <th
                      className="px-6 py-3 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">Name
                    </th>
                    <th
                      className="px-6 py-3 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">Traffic
                      (Remain/Total)
                    </th>
                    <th
                      className="px-6 py-3 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">Status
                    </th>
                    <th
                      className="px-6 py-3 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">Expired
                      At
                    </th>
                  </tr>
                  </thead>
                  <tbody className="bg-white divide-y divide-gray-200">
                  {userPackages.map((userPackage) => (
                    <tr
                      key={userPackage.id}
                      className={
                        userPackage.status !== "active" ? "text-gray-500 line-through" : "text-gray-900"
                      }
                    >
                      <td className="px-6 py-4 whitespace-no-wrap">
                        <div>{userPackage.package.name}</div>
                      </td>
                      <td className="px-6 py-4 whitespace-no-wrap">
                        <div>
                          {formatBytes(userPackage.remaining_traffic)}/{formatBytes(userPackage.package.traffic_limit)}
                        </div>
                      </td>
                      <td className="px-6 py-4 whitespace-no-wrap">
                        <div>{userPackage.status}</div>
                      </td>
                      <td className="px-6 py-4 whitespace-no-wrap">
                        <div>{userPackage.ended_at}</div>
                      </td>
                    </tr>
                  ))}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

      <Modal show={confirmingBuy} onClose={() => setConfirmingBuy(false)}>
        <form className="p-6" onSubmit={handleSubmit}>
          <h2 className="text-lg font-medium text-gray-900">Are you sure you want to buy this package?</h2>
          <p className="mt-1 text-sm text-gray-600">
            Buying this package will directly deduct ${packages.find(p => p.id === confirmingBuy)?.price} from your balance.
          </p>
          <div className="mt-6 flex justify-end">
            <SecondaryButton onClick={() => setConfirmingBuy(false)}>Cancel</SecondaryButton>
            <PrimaryButton className="ms-3">Buy</PrimaryButton>
          </div>
        </form>
      </Modal>
    </AuthenticatedLayout>
  );
}
