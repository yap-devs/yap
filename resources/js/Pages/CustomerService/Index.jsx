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
            {/*Client Download section */}
            <div className="text-gray-900 pl-6 pb-6 pt-8 rounded shadow mb-6 bg-blue-50">
              <h2 className="text-lg font-semibold text-gray-800">Client Download</h2>
              <p className="text-sm text-gray-700 mt-2">
                Download the latest version of the client software.
              </p>
              <div className="underline mt-2 space-x-4">
                <a href="https://github.com/clash-verge-rev/clash-verge-rev/releases/latest" target="_blank"
                   rel="noreferrer noopener">
                  For Windows && MacOS && Linux
                </a>
                <a href="https://github.com/MetaCubeX/ClashMetaForAndroid/releases/latest" target="_blank"
                   rel="noreferrer noopener">
                  For Android
                </a>
              </div>
            </div>

            {/*Customer Service section */}
            <div className="text-gray-900 pl-6 pb-6 pt-8 rounded shadow mb-6 bg-blue-50">
              <h2 className="text-lg font-semibold text-gray-800">Customer Service</h2>
              <p className="text-sm text-gray-700 mt-2">
                If you have any questions or concerns, please feel free to contact us.
              </p>
              <p className="text-sm text-gray-700">
                We will respond to your inquiries as soon as possible.
              </p>
              <div className="mt-2 space-x-4">
                Telegram: <a href="https://t.me/yap_devs" target="_blank" rel="noreferrer noopener">@yap_devs</a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>);
}
