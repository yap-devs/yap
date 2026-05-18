import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {Head, router} from '@inertiajs/react';
import SecondaryButton from "@/Components/SecondaryButton.jsx";
import {formatBytes} from "@/Utils/formatBytes.js";
import {formatPrice} from "@/Utils/formatPrice.js";
import Modal from "@/Components/Modal.jsx";
import PrimaryButton from "@/Components/PrimaryButton.jsx";
import {useState} from 'react';
import {trans} from '@/Utils/i18n';


export default function Index({auth, packages, userPackages}) {
  const [confirmingBuy, setConfirmingBuy] = useState(false);

  function handleSubmit(e) {
    e.preventDefault();
    router.visit(route('package.buy', confirmingBuy), {
      method: 'post',
      preserveScroll: true,
    });
    setConfirmingBuy(false);
  }

  const calcPercentageOff = (rawPrice, price) => {
    return trans('package.off', {percent: (((rawPrice - price) / rawPrice) * 100).toFixed(0)});
  }

  return (
    <AuthenticatedLayout
      user={auth.user}
      header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">{trans('package.title')}</h2>}
    >
      <Head title={trans('package.title')}/>
      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          {/* Explanation banner */}
          <div className="mb-6 bg-blue-50 rounded-lg p-4 border border-blue-100">
            <p className="text-sm text-blue-800">
              <span className="font-semibold">{trans('package.optional_title')}</span>
              {' '}{trans('package.optional_body')}
            </p>
            <div className="mt-2">
              <button
                onClick={() => router.get(route('recharge'))}
                className="text-sm text-blue-600 hover:text-blue-800 font-medium underline"
              >
                {trans('package.need_funds')}
              </button>
            </div>
          </div>

          <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div className="p-6 text-gray-900" style={{overflowX: 'auto'}}>
              <table className="min-w-full divide-y divide-gray-200">
                <thead>
                <tr>
                  <th
                    className="px-6 py-3 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">{trans('package.name')}
                  </th>
                  <th
                    className="px-6 py-3 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">{trans('package.description')}
                  </th>
                  <th
                    className="px-6 py-3 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">{trans('package.traffic')}
                  </th>
                  <th
                    className="px-6 py-3 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">{trans('package.price')}
                  </th>
                  <th
                    className="px-6 py-3 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">{trans('package.validity_days')}
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
                      <button
                        type="button"
                        onClick={() => setConfirmingBuy(_package.id)}
                        className="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition duration-200"
                      >
                        {trans('common.buy')}
                      </button>
                    </td>
                  </tr>
                ))}
                </tbody>
              </table>
            </div>
          </div>

          {userPackages.length > 0 && (
            <div className="mt-6">
              <h2 className="text-lg font-medium text-gray-900 mb-3">{trans('package.your_packages')}</h2>
              <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div className="p-6 text-gray-900" style={{overflowX: 'auto'}}>
                  <table className="min-w-full divide-y divide-gray-200">
                    <thead>
                    <tr>
                      <th
                        className="px-6 py-3 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">{trans('package.name')}
                      </th>
                      <th
                        className="px-6 py-3 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">{trans('package.traffic_remain_total')}
                      </th>
                      <th
                        className="px-6 py-3 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">{trans('common.status')}
                      </th>
                      <th
                        className="px-6 py-3 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">{trans('package.expired_at')}
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
          )}
        </div>
      </div>

      <Modal show={confirmingBuy} onClose={() => setConfirmingBuy(false)}>
        <form className="p-6" onSubmit={handleSubmit}>
          <h2 className="text-lg font-medium text-gray-900">{trans('package.confirm_title')}</h2>
          <p className="mt-1 text-sm text-gray-600">
            {trans('package.confirm_body', {amount: packages.find(p => p.id === confirmingBuy)?.price})}
          </p>
          <div className="mt-6 flex justify-end">
            <SecondaryButton onClick={() => setConfirmingBuy(false)}>{trans('common.cancel')}</SecondaryButton>
            <PrimaryButton className="ms-3">{trans('common.buy')}</PrimaryButton>
          </div>
        </form>
      </Modal>
    </AuthenticatedLayout>
  );
}
