import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {Head} from '@inertiajs/react';
import {QRCodeCanvas} from "qrcode.react";

export default function Stat({auth, QRInfo, amount}) {
  return (<AuthenticatedLayout
    user={auth.user}
    header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">
      Scan to Pay
    </h2>}
  >
    <Head title="Scan to Pay"/>

    <div className="py-12">
      <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div className="p-6 text-gray-900">
            <div className="text-2xl font-semibold">Scan to Pay</div>
            <div className="mt-4">
              <div className="text-lg">Amount: {amount}</div>
              <div className="text-lg">Order ID: {QRInfo['out_trade_no']}</div>
              <div className="mt-4">
                <QRCodeCanvas value={QRInfo['qr_code']} size={256}/>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>);
}
