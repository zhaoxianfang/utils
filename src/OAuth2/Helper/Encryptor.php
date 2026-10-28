<?php
declare(strict_types=1);

/**
 * Encryptor
 */
namespace zxf\Utils\OAuth2\Helper;

use zxf\Utils\OAuth2\Exception\OAuthException;

/**
 * 第三方平台加密数据解密器。
 *
 * 目前用于解密微信小程序下发的敏感数据（如用户手机号），
 * 这些数据由微信使用登录时下发的 sessionKey 以 AES-128-CBC 加密。
 *
 * @package zxf\Utils\OAuth2\Helper
 * @version 1.0.0
 * @license MIT
 */
class Encryptor{

    /**
     * 解密数据（微信小程序手机号）
     *
     * 入参均为 base64 编码，内部先解码再用 AES 解密，最后解析为 JSON 数组。
     *
     * @param  string  $sessionKey  会话密钥（base64 编码，登录时由微信下发）
     * @param  string  $iv          初始化向量（base64 编码）
     * @param  string  $encrypted   加密数据（base64 编码，小程序端 encryptedData）
     * @return array 解密后的数据，典型结构如下：
     *               ```php
     *               [
     *                   'phoneNumber'     => '1314666****',
     *                   'purePhoneNumber' => '1314666****',
     *                   'countryCode'     => '86',
     *                   'watermark'       => [
     *                       'timestamp' => 1732589884,
     *                       'appid'     => 'wxb771b4b7fb****',
     *                   ],
     *               ]
     *               ```
     *
     * @throws OAuthException 当解密结果不是合法的 JSON 对象（payload 无效）时抛出
     *
     * @example
     * ```php
     * $data = Encryptor::decryptData($sessionKey, $iv, $encryptedData);
     * echo $data['phoneNumber']; // 1314666****
     * ```
     */
    static public function decryptData(string $sessionKey, string $iv, string $encrypted): array
    {
        $decrypted = AES::decrypt(
            base64_decode($encrypted, false),
            base64_decode($sessionKey, false),
            base64_decode($iv, false)
        );

        $decrypted = json_decode($decrypted, true);

        // 必须校验为数组：若解密结果是可被 json_decode 解析的标量（如 5、"x"），
        // 原实现仅判断真假就会把标量直接 return，从而违反返回类型 array 触发 TypeError。
        if (! is_array($decrypted)) {
            throw new OAuthException("The given payload is invalid.");
        }

        return $decrypted;
    }
}
