import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {Head} from '@inertiajs/react';

export default function Index({auth}) {
  return (<AuthenticatedLayout
    user={auth.user}
    header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">
      Customer Service
    </h2>}
  >
    <Head title="Customer Service"/>
    <div className="py-12">
      <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div className="p-6 text-gray-900">
            <div className="flex items-center justify-between">
              <h2 className="text-lg font-semibold text-gray-800">Customer Service</h2>
            </div>
            <div className="mt-6">
              <p className="text-sm text-gray-700">
                If you have any questions or concerns, please feel free to contact us.
              </p>
              <p className="text-sm text-gray-700">
                We will respond to your inquiries as soon as possible.
              </p>
            </div>
            <div className="mt-6">
              <h3 className="text-lg font-semibold text-gray-800">Contact Information</h3>
              <p className="text-sm text-gray-700">
                Telegram: <a href="https://t.me/yap_devs" target="_blank" rel="noreferrer noopener">@yap_devs</a>
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>);
}
