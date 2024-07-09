import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {Head, router} from '@inertiajs/react';
import {QRCodeCanvas} from "qrcode.react";
import axios from "axios";
import {useEffect, useState} from 'react';

export default function Stat({auth, QRInfo, amount, paymentId}) {
  const [tradeStatus, setTradeStatus] = useState('');

  const renderTradeStatus = () => {
    if (tradeStatus === '') return;
    if (tradeStatus === 'WAIT_BUYER_PAY') {
      return (
        <div className="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4">
          <p className="font-bold">Waiting for payment...</p>
        </div>
      );
    }
    if (tradeStatus === 'TRADE_SUCCESS') {
      return (
        <div className="bg-green-100 border-l-4 border-green-500 text-green-700 p-4">
          <p className="font-bold">Payment success! Redirecting...</p>
        </div>
      );
    }
    if (tradeStatus === 'TRADE_CLOSED') {
      return (
        <div className="bg-red-100 border-l-4 border-red-500 text-red-700 p-4">
          <p className="font-bold">Payment closed!</p>
        </div>
      );
    }
  };

  useEffect(() => {
    const query = () => {
      axios.get(route('alipay.query', {payment: paymentId}))
        .then(response => {
          if (!response.data.trade_status) return;

          setTradeStatus(response.data.trade_status);

          if (response.data.trade_status === 'TRADE_SUCCESS') {
            setTimeout(() => {
              router.get(route('profile.edit'));
            }, 2000);
          }
        });
    };

    const interval = setInterval(query, 2000);

    return () => clearInterval(interval);
  }, []);

  return (
    <AuthenticatedLayout
      user={auth.user}
      header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Scan to Pay</h2>}
    >
      <Head title="Scan to Pay"/>

      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div className="p-6 text-gray-900">
              <div className="text-2xl font-semibold">Scan to Pay</div>
              <div className="mt-4">
                <div className="text-lg">Amount: ${amount}</div>
                <div className="text-lg">Order ID: {QRInfo['out_trade_no']}</div>
                <div className="mt-4">
                  <QRCodeCanvas value={QRInfo['qr_code']} size={256}/>
                </div>
              </div>
              <div className="mt-4">
                {renderTradeStatus()}
              </div>
            </div>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
