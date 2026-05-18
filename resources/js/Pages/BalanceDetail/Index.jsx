import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {Head} from '@inertiajs/react';
import Pagination from "@/Components/Pagination.jsx";
import {trans} from '@/Utils/i18n';

export default function Index({auth, balanceDetails}) {
  const translateDescription = (description) => {
    const exactMatches = {
      'Stripe payment': trans('balance_descriptions.stripe_payment'),
      'Alipay payment': trans('balance_descriptions.alipay_payment'),
      'Usdt payment': trans('balance_descriptions.usdt_payment'),
      'USDT payment': trans('balance_descriptions.usdt_payment'),
      'GitHub sponsor': trans('balance_descriptions.github_sponsor'),
      'Traffic deduction': trans('balance_descriptions.traffic_deduction'),
      'Daily deduction': trans('balance_descriptions.daily_deduction'),
      'Subscription URL reset': trans('balance_descriptions.subscription_url_reset'),
    };

    if (description?.startsWith('Bought package ')) {
      return trans('balance_descriptions.bought_package', {name: description.replace('Bought package ', '')});
    }

    return exactMatches[description] || description;
  };

  return (<AuthenticatedLayout
    user={auth.user}
    header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">
      {trans('balance_detail.title')}
    </h2>}
  >
    <Head title={trans('balance_detail.title')}/>
    <div className="py-12">
      <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div className="p-6 text-gray-900" style={{overflowX: 'auto'}}>
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  {trans('common.date')}
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  {trans('common.amount')}
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  {trans('common.description')}
                </th>
              </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200">
              {balanceDetails.data.map((balanceDetail) => (
                <tr key={balanceDetail.id}>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="text-sm text-gray-900">{balanceDetail.created_at}</div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className={`text-sm ${balanceDetail.amount < 0 ? 'text-red-500' : 'text-green-500'}`}>
                      ${balanceDetail.amount}
                    </div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="text-sm text-gray-900">{translateDescription(balanceDetail.description)}</div>
                  </td>
                </tr>
              ))}
              {balanceDetails.data.length === 0 && (
                <tr>
                  <td colSpan="3" className="px-6 py-4 whitespace-nowrap">
                    <div className="text-sm text-gray-900 text-center">{trans('common.no_records')}</div>
                  </td>
                </tr>
              )}
              </tbody>
            </table>

            <Pagination links={balanceDetails.links}/>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>);
}
