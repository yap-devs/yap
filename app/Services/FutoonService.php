<?php

namespace App\Services;


class FutoonService
{
    public function submit($out_trade_no, $name, $money)
    {
        $params = [
            'pid' => config('futoon.pid'),
            'out_trade_no' => $out_trade_no,
            'name' => $name,
            'money' => $money,
            'notify_url' => route('futoon.notify'),
            'return_url' => route('profile.edit')
        ];
        $params['sign'] = $this->sign($params);
        $params['sign_type'] = 'MD5';

        return 'https://futoon.org/submit.php?' . http_build_query($params);
    }

    public function notify($request)
    {
        $params = $request->all();

        if ($params['sign'] !== $this->sign($params)) {
            return 'fail';
        }

        return 'success';
    }

    /**
     * Sign the given parameters
     *
     * @param array $params The parameters to sign
     * @return string The sign value
     */
    private function sign(array $params): string
    {
        // 将发送或接收到的所有参数按照参数名ASCII码从小到大排序（a-z），sign、sign_type、和空值不参与签名！
        ksort($params);
        // 将排序后的参数拼接成URL键值对的格式，例如 a=b&c=d&e=f，参数值不要进行url编码。
        $str = '';
        foreach ($params as $key => $value) {
            if ($key === 'sign' || $key === 'sign_type' || $value === '') {
                continue;
            }

            $str .= $key . '=' . $value . '&';
        }
        $str = rtrim($str, '&');
        // 再将拼接好的字符串与商户密钥KEY进行MD5加密得出sign签名参数，sign = md5 ( a=b&c=d&e=f + KEY ) （注意：+ 为各语言的拼接符，不是字符！），md5结果为小写。
        return md5($str . config('futoon.key'));
    }
}
