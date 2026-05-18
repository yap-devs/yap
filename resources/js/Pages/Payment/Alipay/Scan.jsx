import {Head, router} from '@inertiajs/react';
import {QRCodeCanvas} from "qrcode.react";
import axios from "axios";
import {useEffect, useState} from 'react';
import {trans} from '@/Utils/i18n';

export default function Scan({_auth, QRInfo, amount, paymentId}) {
  const [tradeStatus, setTradeStatus] = useState('');
  const tradeStatusMessage = {
    '': {text: trans('alipay.scan'), color: 'bg-gray-500 text-white'},
    'WAIT_BUYER_PAY': {text: trans('alipay.awaiting'), color: 'bg-blue-500 text-white'},
    'TRADE_SUCCESS': {text: trans('alipay.paid'), color: 'bg-green-500 text-white'},
    'TRADE_CLOSED': {text: trans('alipay.closed'), color: 'bg-red-500 text-white'}
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
    <>
      <Head title={trans('alipay.title')}/>
      <div className="flex flex-col items-center justify-center min-h-screen bg-gray-800">
        <div className="p-4 bg-white rounded shadow-lg">
          <div className="items-center">
            <div
              className={`px-4 py-2 rounded-md text-2xl font-semibold text-center shadow-md ${tradeStatusMessage[tradeStatus].color}`}
            >
              {tradeStatusMessage[tradeStatus].text}
            </div>
            <div className="mt-4">
              <div className="px-4 py-2 bg-gray-800 text-white rounded-md text-lg">{trans('alipay.payment_information')}</div>
              <div className="px-4 py-2 mt-2 bg-gray-200 text-black rounded-md">{trans('alipay.amount', {amount})}</div>
              <div className="px-4 py-2 mt-2 bg-gray-200 text-black rounded-md">{trans('alipay.order_id', {id: QRInfo['out_trade_no']})}</div>
            </div>
            <div className="mt-4">
              <QRCodeCanvas value={QRInfo['qr_code']} size={256} className="m-auto"/>
            </div>
          </div>
        </div>
      </div>
    </>
  );
}
