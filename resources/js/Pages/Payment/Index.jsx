import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {Head, Link} from '@inertiajs/react';

export default function Index({auth, payments}) {
  const statusCssMap = {
    created: 'bg-yellow-100 text-yellow-800',
    paid: 'bg-green-100 text-green-800',
    cancelled: 'bg-red-100 text-red-800',
    expired: 'bg-red-100 text-red-800',
    refunded: 'bg-red-100 text-red-800',
  }

  return (<AuthenticatedLayout
    user={auth.user}
    header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Payment</h2>}
  >
    <Head title="Payment"/>

    <div className="py-12">
      <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div className="p-6 text-gray-900">
            {
              payments.length === 0 ? (
                <div className="text-center text-gray-600">No payments found.</div>
              ) : (
                <table className="min-w-full divide-y divide-gray-200">
                  <thead>
                  <tr>
                    <th
                      className="px-6 py-3 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                      #
                    </th>
                    <th
                      className="px-6 py-3 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                      Gateway
                    </th>
                    <th
                      className="px-6 py-3 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                      Date
                    </th>
                    <th
                      className="px-6 py-3 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                      Amount
                    </th>
                    <th
                      className="px-6 py-3 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                      Status
                    </th>
                    <th
                      className="px-6 py-3 bg-gray-50 text-left text-xs leading-4 font-medium text-gray-500 uppercase tracking-wider">
                      Action
                    </th>
                  </tr>
                  </thead>
                  <tbody className="bg-white divide-y divide-gray-200">
                  {payments.map((payment) => (
                    <tr key={payment.id}>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="text-sm leading-5 text-gray-900">{payment.remote_id}</div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="text-sm leading-5 text-gray-900">{payment.gateway}</div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="text-sm leading-5 text-gray-900">{payment.created_at}</div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="text-sm leading-5 text-gray-900">${payment.amount}</div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                          <span
                            className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${statusCssMap[payment.status]}`}>
                            {payment.status}
                          </span>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        {
                          (payment.status === 'created') && (payment.gateway === 'alipay') && (
                            <Link href={route('alipay.scan', payment)}
                                  className="text-indigo-600 hover:text-indigo-900">Pay</Link>
                          )
                        }
                      </td>
                    </tr>
                  ))}
                  </tbody>
                </table>
              )
            }
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>);
}
