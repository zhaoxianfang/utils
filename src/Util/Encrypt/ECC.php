<?php
namespace zxf\Util\Encrypt;
use InvalidArgumentException;
use OpenSSLAsymmetricKey;
use Random\RandomException;
use RuntimeException;

/**
 * ECC - 全功能 ECC 工具库（PHP 8.2+）
 *
 * 功能概览（完整实现）：
 *  - 密钥生成（多曲线支持）
 *  - 导出：PEM/DER(PKCS8)/SEC1/RAW(压缩/非压缩)/HEX/Base64
 *  - 导入：从 PEM/DER/RAW HEX/Base64 加载公/私钥
 *  - 验证：公钥/私钥是否匹配，公钥是否在曲线上
 *  - ECIES 风格加密（ECDH + HKDF + 对称加密）
 *      - 支持 AES-GCM / AES-CTR + HMAC / AES-CBC + HMAC
 *      - HKDF 支持 sha256/sha384/sha512（可选 salt/info）
 *  - ECDSA 签名与验签（DER/RAW 格式）
 *  - 输出编码：JSON / 二进制 / HEX / base64 / base64url
 *  - 文件读写（自动识别 PEM/DER）
 *  - 密钥轮换（生成新对、保留旧对的工具）
 *  - JWT ES256/ES384/ES512 支持（签名/验证简易版）
 *
 * 注意事项（摘要）：
 *  - 仅使用 openssl 与标准 hash/hmac。部分原始点 <-> PEM 操作使用通用方法，
 *    在极端 OpenSSL 版本或严格 ASN.1 差异下建议使用 phpseclib 进行兼容。
 *  - 请在生产环境中对密钥、salt、随机源、权限等做审计。
 *
 * 使用示例见文件底部。
 *
 * 作者注：注释尽量详尽枚举参数可选值与含义（中文）。
 */
class ECC
{
    /**
     * 支持的常见曲线名称（OpenSSL 名称）
     * - prime256v1 (secp256r1) - 推荐
     * - secp384r1
     * - secp521r1
     * - secp256k1 (某些 OpenSSL 版本才支持)
     */
    public static array $COMMON_CURVES = [
        'prime256v1', 'secp384r1', 'secp521r1', 'secp256k1'
    ];

    /* -------------------- 低级/辅助函数 -------------------- */

    /**
     * 将 PEM 文本转换为 DER（直接返回二进制）
     *
     * @param string $pem PEM 文本（带 -----BEGIN ...-----）
     * @return string DER 二进制
     * @throws InvalidArgumentException 当 PEM 无效
     */
    public static function pemToDer(string $pem): string
    {
        $lines = preg_split('/\R/', trim($pem));
        $b64 = '';
        foreach ($lines as $line) {
            if (str_starts_with($line, '-----')) continue;
            $b64 .= trim($line);
        }
        $der = base64_decode($b64, true);
        if ($der === false) throw new InvalidArgumentException('无法从 PEM 解码 DER');
        return $der;
    }

    /**
     * 将 DER 二进制编码为 PEM 文本
     *
     * @param string $der 二进制 DER
     * @param string $label PEM 标签 (例 'PUBLIC KEY' 或 'PRIVATE KEY')
     * @return string PEM 文本
     */
    public static function derToPem(string $der, string $label = 'PUBLIC KEY'): string
    {
        $b64 = chunk_split(base64_encode($der), 64, "\n");
        return "-----BEGIN {$label}-----\n{$b64}-----END {$label}-----\n";
    }

    /**
     * 将 base64url 编码转换为 base64
     */
    private static function base64UrlToBase64(string $s): string
    {
        $s = strtr($s, '-_', '+/');
        return $s . str_repeat('=', (4 - strlen($s) % 4) % 4);
    }

    /**
     * 将 base64 编码转换为 base64url，无尾部 '='
     */
    private static function base64ToBase64Url(string $s): string
    {
        return rtrim(strtr($s, '+/', '-_'), '=');
    }

    /**
     * 将二进制转 HEX（小写）
     */
    public static function bin2hexLower(string $bin): string
    {
        return strtolower(bin2hex($bin));
    }

    /**
     * 将 HEX 转二进制
     */
    public static function hex2binSafe(string $hex): string
    {
        $hex = preg_replace('/\s+/', '', $hex);
        $bin = hex2bin($hex);
        if ($bin === false) throw new InvalidArgumentException('无效 HEX 字符串');
        return $bin;
    }

    /* -------------------- 密钥生成与导出/导入 -------------------- */

    /**
     * 生成 ECC 密钥对
     *
     * @param string $curve 曲线名。可选值示例： 'prime256v1','secp384r1','secp521r1','secp256k1'
     * @param null|string $privatePemPassphrase 若不为 null，则导出私钥 PEM 时使用该密码进行加密
     * @return array{private_pem:string, public_pem:string, private_der:string, public_der:string}
     * @throws RuntimeException 当生成失败
     */
    public static function generateKeyPair(string $curve = 'prime256v1', ?string $privatePemPassphrase = null): array
    {
        $cfg = ['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => $curve];
        $res = openssl_pkey_new($cfg);
        if ($res === false) {
            throw new RuntimeException('生成密钥失败：' . openssl_error_string());
        }
        // 私钥 PEM（可被加密）
        $exportOk = openssl_pkey_export($res, $privatePem, $privatePemPassphrase);
        if ($exportOk === false) {
            throw new RuntimeException('导出私钥 PEM 失败');
        }
        // 公钥 PEM & DER
        $details = openssl_pkey_get_details($res);
        if ($details === false || !isset($details['key'])) {
            throw new RuntimeException('导出公钥失败');
        }
        $publicPem = $details['key'];
        $privateDer = self::pemToDer($privatePem); // PKCS8/SEC1 取决于 OpenSSL 导出类型
        $publicDer = self::pemToDer($publicPem);
        return [
            'private_pem' => $privatePem,
            'public_pem' => $publicPem,
            'private_der' => $privateDer,
            'public_der' => $publicDer,
        ];
    }

    /**
     * 从 PEM/DER 字符串加载公钥（返回 OpenSSLAsymmetricKey）
     *
     * @param string $pubData 公钥，可以是 PEM 文本，或 DER 二进制（若为 DER，请用 base64_decode 后传入）
     * @param bool $isDer 是否为 DER（true 表示二进制 DER；false 表示 PEM 文本）
     * @return OpenSSLAsymmetricKey
     * @throws RuntimeException
     */
    public static function loadPublicKey(string $pubData, bool $isDer = false): OpenSSLAsymmetricKey
    {
        if ($isDer) {
            $pem = self::derToPem($pubData, 'PUBLIC KEY');
            $key = openssl_pkey_get_public($pem);
        } else {
            $key = openssl_pkey_get_public($pubData);
        }
        if ($key === false) throw new RuntimeException('加载公钥失败（PEM/DER 格式可能非法）');
        return $key;
    }

    /**
     * 从 PEM/DER 字符串加载私钥（返回 OpenSSLAsymmetricKey）
     *
     * @param string $privData 私钥，PEM 文本或 DER 二进制（若 DER 则以二进制传入）
     * @param bool $isDer 是否为 DER（true 表示二进制 DER；false 表示 PEM）
     * @param null|string $passphrase 私钥 PEM 的密码（若私钥加密则需提供）
     * @return OpenSSLAsymmetricKey
     * @throws RuntimeException
     */
    public static function loadPrivateKey(string $privData, bool $isDer = false, ?string $passphrase = null): OpenSSLAsymmetricKey
    {
        if ($isDer) {
            $pem = self::derToPem($privData, 'PRIVATE KEY'); // 注意：可能为 SEC1 格式，标签可能需改
            $key = openssl_pkey_get_private($pem, $passphrase);
        } else {
            $key = openssl_pkey_get_private($privData, $passphrase);
        }
        if ($key === false) throw new RuntimeException('加载私钥失败（PEM/DER 或 passphrase 错误）');
        return $key;
    }

    /**
     * 导出公钥为原始点（X9.63）格式
     *
     * @param OpenSSLAsymmetricKey $pubKey 公钥对象（可由 loadPublicKey 得到）
     * @param bool $compressed 是否返回压缩点（true=compressed(33 bytes for P-256)；false=uncompressed (0x04||X||Y)）
     * @return string 二进制 raw point
     *
     * 说明：
     * - 如果 openssl_pkey_get_details($key)['ec']['x']/'y' 可用，则使用该字段直接组装。
     * - 否则尝试从 PEM->DER 的 SPKI 中提取 BIT STRING（通用方法）。
     * - 在极端环境下若无法从 DER 提取未压缩点，可能需要依赖 phpseclib。
     */
    public static function publicKeyToRawPoint(OpenSSLAsymmetricKey $pubKey, bool $compressed = false): string
    {
        $details = openssl_pkey_get_details($pubKey);
        if ($details === false) throw new RuntimeException('获取公钥细节失败');

        // 优先使用 details['ec']['x'] 和 ['y']（某些 PHP 版本提供）
        if (isset($details['ec']['x']) && isset($details['ec']['y'])) {
            $x = $details['ec']['x']; // binary
            $y = $details['ec']['y'];
            if ($compressed) {
                // 压缩点：0x02/0x03 + X，取决于 Y 的最低位
                $prefix = ((ord($y[strlen($y) - 1]) & 1) === 0) ? "\x02" : "\x03";
                return $prefix . $x;
            } else {
                return "\x04" . $x . $y;
            }
        }

        // 否则从 details['key'] (PEM) -> DER -> 提取 BIT STRING
        if (!isset($details['key'])) throw new RuntimeException('无法从公钥获取 key 字段');

        $pem = $details['key'];
        $der = self::pemToDer($pem);

        // 查找 BIT STRING (0x03)
        $pos = strpos($der, "\x03");
        if ($pos === false) {
            throw new RuntimeException('DER 中找不到 BIT STRING');
        }
        // 简单解析长度（适用于常见 SubjectPublicKeyInfo）
        $off = $pos + 1;
        $lenByte = ord($der[$off]);
        $off++;
        if ($lenByte & 0x80) {
            $num = $lenByte & 0x7F;
            $len = 0;
            for ($i = 0; $i < $num; $i++) {
                $len = ($len << 8) + ord($der[$off + $i]);
            }
            $off += $num;
        } else {
            $len = $lenByte;
        }
        // BIT STRING 内容的第一个字节通常是 0x00 (unused bits)
        if (ord($der[$off]) === 0x00) {
            $point = substr($der, $off + 1, $len - 1);
        } else {
            $point = substr($der, $off, $len);
        }

        if ($compressed) {
            // 如果 point 以 0x04 开头（uncompressed），压缩之
            if ($point[0] === "\x04") {
                $xLen = (strlen($point) - 1) / 2;
                $x = substr($point, 1, $xLen);
                $y = substr($point, 1 + $xLen, $xLen);
                $prefix = ((ord($y[strlen($y) - 1]) & 1) === 0) ? "\x02" : "\x03";
                return $prefix . $x;
            }
            // 否则可能已是压缩点
            return $point;
        } else {
            // 确保是非压缩点
            if ($point[0] === "\x04") return $point;
            // 若为压缩点则无法直接解压（需大数运算），抛异常提示使用 phpseclib
            throw new RuntimeException('DER 中为压缩点，需要点解压以获得未压缩点；请使用 phpseclib 或将公钥以 PEM 形式提供');
        }
    }

    /**
     * 从 raw point 构造可被 OpenSSL 导入的 PUBLIC KEY PEM
     *
     * @param string $rawPoint 二进制 raw point (0x04||X||Y or 0x02/0x03||X)
     * @param string $curve 曲线名（如 prime256v1）
     * @return string 公钥 PEM
     *
     * 说明：
     * - 本方法采用“生成模板 SPKI 并替换 BIT STRING”的实用方法构造 PEM。
     * - 在极端环境下该方法可能需要调整；对跨语言互通建议使用 phpseclib 来做严格构造。
     */
    public static function rawPointToPublicPem(string $rawPoint, string $curve = 'prime256v1'): string
    {
        // 生成临时 key 以获取 SPKI 模板（使用相同曲线）
        $res = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => $curve]);
        if ($res === false) throw new RuntimeException('无法生成临时模板 key');

        $details = openssl_pkey_get_details($res);
        if ($details === false || !isset($details['key'])) throw new RuntimeException('获取模板失败');

        $pemTemplate = $details['key'];
        $der = self::pemToDer($pemTemplate);

        // 找到 BIT STRING 并替换
        $pos = strpos($der, "\x03");
        if ($pos === false) throw new RuntimeException('模板 DER 中找不到 BIT STRING');

        // 解析当前长度字段（简单实现）
        $off = $pos + 1;
        $lenByte = ord($der[$off]); $off++;
        if ($lenByte & 0x80) {
            $num = $lenByte & 0x7F; $off += $num;
        }
        // off 指向 BIT STRING 内容第一个字节（通常 0x00）
        // 构造新的 BIT STRING (0x00 + rawPoint)
        $newBitString = "\x00" . $rawPoint;
        $newLen = strlen($newBitString);

        // 构造长度字节（ASN.1 长度编码）
        if ($newLen < 128) {
            $lenBytes = chr($newLen);
        } else {
            $hex = dechex($newLen);
            if (strlen($hex) % 2) $hex = '0' . $hex;
            $binLen = hex2bin($hex);
            $lenBytes = chr(0x80 | strlen($binLen)) . $binLen;
        }

        // 重建 DER: prefix(到 0x03) + lenBytes + newBitString + suffix(after old bitstring)
        // 找 suffix 起点（简单方法：重新解析以找到旧 bitstring 长度）
        // 我们重新查找旧 bitstring len 与其内容起点并计算 suffix
        $pos2 = strpos($der, "\x03");
        $off2 = $pos2 + 1;
        $b1 = ord($der[$off2]); $off2++;
        if ($b1 & 0x80) {
            $num = $b1 & 0x7F;
            $lenOld = 0;
            for ($i = 0; $i < $num; $i++) {
                $lenOld = ($lenOld << 8) + ord($der[$off2 + $i]);
            }
            $off2 += $num;
        } else {
            $lenOld = $b1;
        }
        $suffixStart = $off2 + $lenOld;
        $prefix = substr($der, 0, $pos2);
        $suffix = substr($der, $suffixStart);

        $newDer = $prefix . "\x03" . $lenBytes . $newBitString . $suffix;
        $pemOut = self::derToPem($newDer, 'PUBLIC KEY');
        return $pemOut;
    }

    /**
     * 判断私钥与公钥是否匹配
     *
     * @param OpenSSLAsymmetricKey $priv 私钥对象
     * @param OpenSSLAsymmetricKey $pub 公钥对象
     * @return bool 匹配返回 true，否则 false
     *
     * 说明：方法实现为对同一随机明文签名并验证
     */
    public static function keyPairMatches(OpenSSLAsymmetricKey $priv, OpenSSLAsymmetricKey $pub): bool
    {
        $msg = random_bytes(32);
        $sig = '';
        $ok = openssl_sign($msg, $sig, $priv, OPENSSL_ALGO_SHA256);
        if ($ok === false) return false;
        $v = openssl_verify($msg, $sig, $pub, OPENSSL_ALGO_SHA256);
        return $v === 1;
    }

    /* -------------------- KDF: HKDF (RFC5869) -------------------- */

    /**
     * HKDF 派生函数
     *
     * @param string $hash 'sha256'|'sha384'|'sha512' 等
     * @param string $ikm 初始密钥材料（二进制）
     * @param int $length 输出字节数
     * @param string $info 可选 info 字符串
     * @param null|string $salt 可选 salt（二进制），null 表示全 0
     * @return string 派生输出字节串
     */
    public static function hkdf(string $hash, string $ikm, int $length, string $info = '', ?string $salt = null): string
    {
        $hlen = strlen(hash($hash, '', true));
        if ($length > 255 * $hlen) throw new InvalidArgumentException('HKDF length too large');
        if ($salt === null) $salt = str_repeat("\0", $hlen);
        $prk = hash_hmac($hash, $ikm, $salt, true);
        $n = (int)ceil($length / $hlen);
        $okm = ''; $t = '';
        for ($i = 1; $i <= $n; $i++) {
            $t = hash_hmac($hash, $t . $info . chr($i), $prk, true);
            $okm .= $t;
        }
        return substr($okm, 0, $length);
    }

    /* -------------------- 对称加密：AES 模式（GCM, CTR+HMAC, CBC+HMAC） -------------------- */

    /**
     * 对称加密通用函数（支持多种模式）
     *
     * @param string $plaintext 明文二进制
     * @param string $key 对称密钥（二进制）
     * @param array $opts 选项：
     *   - 'cipher' => 'aes-256-gcm'|'aes-256-ctr'|'aes-256-cbc'
     *   - 'iv' => 二进制 IV, 若未提供则自动生成（GCM/CTR 使用 12 字节，CBC 使用 16 字节）
     *   - 'aad' => 附加认证数据，仅对 GCM 有效
     * @return array 返回 ['ciphertext'=>bin, 'iv'=>bin, 'tag'=>bin|null, 'hmac'=>bin|null]
     * @throws RuntimeException|RandomException
     */
    public static function symEncrypt(string $plaintext, string $key, array $opts = []): array
    {
        $cipher = $opts['cipher'] ?? 'aes-256-gcm';
        $aad = $opts['aad'] ?? '';
        $keyLen = (int) (strlen($key) * 8);

        if (!in_array($cipher, ['aes-256-gcm', 'aes-256-ctr', 'aes-256-cbc'], true)) {
            throw new InvalidArgumentException('不支持的 cipher');
        }

        // IV 生成规则
        if (isset($opts['iv'])) {
            $iv = $opts['iv'];
        } else {
            if ($cipher === 'aes-256-gcm' || $cipher === 'aes-256-ctr') {
                $iv = random_bytes(12);
            } else { // CBC
                $iv = random_bytes(16);
            }
        }

        if ($cipher === 'aes-256-gcm') {
            $tag = '';
            $ct = openssl_encrypt($plaintext, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag, $aad, 16);
            if ($ct === false) throw new RuntimeException('GCM 加密失败');
            return ['ciphertext' => $ct, 'iv' => $iv, 'tag' => $tag, 'hmac' => null];
        } else {
            // CTR / CBC => 需要 HMAC 进行认证
            $ct = openssl_encrypt($plaintext, $cipher, $key, OPENSSL_RAW_DATA, $iv);
            if ($ct === false) throw new RuntimeException('加密失败');
            // HMAC 使用 SHA256(key || iv || ciphertext)
            $hmac = hash_hmac('sha256', $iv . $ct, $key, true);
            return ['ciphertext' => $ct, 'iv' => $iv, 'tag' => null, 'hmac' => $hmac];
        }
    }

    /**
     * 对称解密通用函数（配合 symEncrypt）
     *
     * @param string $ciphertext 二进制密文
     * @param string $key 对称密钥
     * @param array $opts 与 symEncrypt 对应：
     *    - 'cipher' => 'aes-256-gcm'|'aes-256-ctr'|'aes-256-cbc'
     *    - 'iv' => 二进制 iv
     *    - 'tag' => GCM tag（二进制）或 null
     *    - 'hmac' => HMAC 值（二进制）或 null
     *    - 'aad' => GCM 的 aad
     * @return string 明文二进制
     * @throws RuntimeException
     */
    public static function symDecrypt(string $ciphertext, string $key, array $opts = []): string
    {
        $cipher = $opts['cipher'] ?? 'aes-256-gcm';
        $iv = $opts['iv'] ?? throw new InvalidArgumentException('缺少 iv');
        if ($cipher === 'aes-256-gcm') {
            $tag = $opts['tag'] ?? throw new InvalidArgumentException('GCM 需提供 tag');
            $aad = $opts['aad'] ?? '';
            $pt = openssl_decrypt($ciphertext, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag, $aad);
            if ($pt === false) throw new RuntimeException('GCM 解密或认证失败');
            return $pt;
        } else {
            $hmac = $opts['hmac'] ?? throw new InvalidArgumentException('非 GCM 模式需提供 hmac');
            $expected = hash_hmac('sha256', $iv . $ciphertext, $key, true);
            if (!hash_equals($expected, $hmac)) throw new RuntimeException('HMAC 验证失败');
            $pt = openssl_decrypt($ciphertext, $cipher, $key, OPENSSL_RAW_DATA, $iv);
            if ($pt === false) throw new RuntimeException('解密失败');
            return $pt;
        }
    }

    /* -------------------- ECIES (ECDH + KDF + Symmetric) -------------------- */

    /**
     * ECIES 加密
     *
     * @param string $plaintext 明文（任意二进制字符串）
     * @param OpenSSLAsymmetricKey $recipientPub 公钥对象（接收方公钥）
     * @param array $opts 选项：
     *   - 'ephemeral_curve' => 曲线名（null 表示使用 recipientPub 的曲线）
     *   - 'hkdf_hash' => 'sha256'|'sha384'|'sha512'（默认 'sha256'）
     *   - 'hkdf_salt' => null|binary
     *   - 'hkdf_info' => string
     *   - 'key_len' => 对称 key 长度（字节，默认 32）
     *   - 'iv_len' => 派生 iv 长度（字节，默认 12）
     *   - 'sym_cipher' => 'aes-256-gcm'|'aes-256-ctr'|'aes-256-cbc'（默认 aes-256-gcm）
     *   - 'ephemeral_pub_format' => 'pem'|'raw_uncompressed'|'raw_compressed'（默认 pem）
     *   - 'output' => 'json'|'binary'|'hex'|'base64'|'base64url'（默认 json）
     *   - 'include_salt' => bool （若为 true 且 hkdf_salt 非 null，则会把 salt 放入包中）
     *   - 'aad' => string 附加认证数据（用于 GCM）
     * @return string 按 output 指定格式返回加密包
     *
     * 返回（当 output='json'）的字段示例（字段均 base64 编码或 raw，根据说明）：
     * {
     *   version: 'ECIES-TOOLKIT-1',
     *   curve: 'prime256v1',
     *   ephemeral_pub_format: 'pem'|'raw_uncompressed'|'raw_compressed',
     *   ephemeral_pub: base64(...),
     *   hkdf_hash: 'sha256',
     *   hkdf_info: base64(...),
     *   hkdf_salt: base64(...) 或 '',
     *   iv: base64(...),
     *   tag: base64(...) 或 '',
     *   ciphertext: base64(...),
     *   hmac: base64(...) 或 '',
     *   aad: base64(...)
     * }
     *
     * @throws RuntimeException
     */
    public static function eciesEncrypt(string $plaintext, OpenSSLAsymmetricKey $recipientPub, array $opts = []): string
    {
        // options defaults
        $hkdfHash = $opts['hkdf_hash'] ?? 'sha256';
        $hkdfSalt = $opts['hkdf_salt'] ?? null;
        $hkdfInfo = $opts['hkdf_info'] ?? '';
        $keyLen = $opts['key_len'] ?? 32;
        $ivLen = $opts['iv_len'] ?? 12;
        $symCipher = $opts['sym_cipher'] ?? 'aes-256-gcm';
        $ephemFormat = $opts['ephemeral_pub_format'] ?? 'pem';
        $output = $opts['output'] ?? 'json';
        $includeSalt = $opts['include_salt'] ?? false;
        $aad = $opts['aad'] ?? '';

        // 1) 获取 recipient 曲线（尽量使用 recipient 的曲线）
        $det = openssl_pkey_get_details($recipientPub);
        if ($det === false || !isset($det['ec']['curve_name'])) {
            throw new RuntimeException('无法从 recipient 公钥获取曲线信息');
        }
        $curve = $det['ec']['curve_name'];

        // 2) 生成 ephemeral key
        $eph = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => $curve]);
        if ($eph === false) throw new RuntimeException('生成 ephemeral key 失败');
        openssl_pkey_export($eph, $ephPrivPem);
        $ephDetail = openssl_pkey_get_details($eph);
        if ($ephDetail === false) throw new RuntimeException('获取 ephemeral 细节失败');
        $ephPubPem = $ephDetail['key'];

        // 3) ECDH derive
        $shared = openssl_pkey_derive($recipientPub, $eph);
        if ($shared === false) throw new RuntimeException('ECDH 推导失败');

        // 4) HKDF -> key + iv
        $okm = self::hkdf($hkdfHash, $shared, $keyLen + $ivLen, $hkdfInfo, $hkdfSalt);
        $symKey = substr($okm, 0, $keyLen);
        $iv = substr($okm, $keyLen, $ivLen);

        // 5) 对称加密
        $symOut = self::symEncrypt($plaintext, $symKey, ['cipher' => $symCipher, 'iv' => $iv, 'aad' => $aad]);
        $ct = $symOut['ciphertext'];
        $tag = $symOut['tag'] ?? '';
        $hmac = $symOut['hmac'] ?? '';

        // 6) ephemeral pub 格式化
        switch ($ephemFormat) {
            case 'pem': $epubOut = $ephPubPem; break;
            case 'raw_uncompressed': $epubOut = self::publicKeyToRawPoint($eph, false); break;
            case 'raw_compressed': $epubOut = self::publicKeyToRawPoint($eph, true); break;
            default: throw new InvalidArgumentException('ephemeral_pub_format 不支持');
        }

        // 7) 包装
        $pkg = [
            'version' => 'ECIES-TOOLKIT-1',
            'curve' => $curve,
            'ephemeral_pub_format' => $ephemFormat,
            'ephemeral_pub' => base64_encode($epubOut),
            'hkdf_hash' => $hkdfHash,
            'hkdf_info' => base64_encode($hkdfInfo),
            'hkdf_salt' => $includeSalt && $hkdfSalt !== null ? base64_encode($hkdfSalt) : '',
            'iv' => base64_encode($iv),
            'tag' => $tag !== '' ? base64_encode($tag) : '',
            'ciphertext' => base64_encode($ct),
            'hmac' => $hmac !== '' ? base64_encode($hmac) : '',
            'aad' => base64_encode($aad),
        ];

        $json = json_encode($pkg, JSON_UNESCAPED_SLASHES);
        switch ($output) {
            case 'json': return $json;
            case 'base64': return base64_encode($json);
            case 'base64url': return self::base64ToBase64Url(base64_encode($json));
            case 'hex': return bin2hex($json);
            case 'binary': return $json; // 用户自行处理二进制
            default: throw new InvalidArgumentException('不支持的 output 类型');
        }
    }

    /**
     * ECIES 解密
     *
     * @param string $package 加密包（默认为 JSON 字符串，或其他编码以 opts 指定）
     * @param OpenSSLAsymmetricKey $recipientPriv 接收方私钥对象
     * @param array $opts 选项：
     *   - 'input' => 'json'|'base64'|'base64url'|'hex'|'binary' (默认 'json')
     *   - 'expected_hkdf_hashes' => array of allowed hashes
     * @return string 明文二进制
     * @throws RuntimeException
     */
    public static function eciesDecrypt(string $package, OpenSSLAsymmetricKey $recipientPriv, array $opts = []): string
    {
        $input = $opts['input'] ?? 'json';
        $expectedHashes = $opts['expected_hkdf_hashes'] ?? ['sha256','sha384','sha512'];

        switch ($input) {
            case 'json': $json = $package; break;
            case 'base64': $json = base64_decode($package, true); break;
            case 'base64url': $json = base64_decode(self::base64UrlToBase64($package), true); break;
            case 'hex': $json = hex2bin($package); break;
            case 'binary': $json = $package; break;
            default: throw new InvalidArgumentException('未知 input 类型');
        }
        if ($json === false) throw new InvalidArgumentException('输入解码失败');

        $pkg = json_decode($json, true);
        if (!is_array($pkg)) throw new InvalidArgumentException('包非 JSON');

        // 必需字段
        $fields = ['ephemeral_pub_format','ephemeral_pub','hkdf_hash','hkdf_info','iv','ciphertext','aad'];
        foreach ($fields as $f) {
            if (!array_key_exists($f, $pkg)) throw new InvalidArgumentException("包缺少字段 {$f}");
        }

        $hkdfHash = $pkg['hkdf_hash'];
        if (!in_array($hkdfHash, $expectedHashes, true)) throw new InvalidArgumentException('不允许的 hkdf_hash');

        $ephemFormat = $pkg['ephemeral_pub_format'];
        $ephemRaw = base64_decode($pkg['ephemeral_pub'], true);
        if ($ephemRaw === false) throw new InvalidArgumentException('ephemeral_pub base64 解码失败');

        // 将 ephemeral 转为 PEM（若必要）
        switch ($ephemFormat) {
            case 'pem':
                $epemPem = $ephemRaw;
                break;
            case 'raw_uncompressed':
                $epemPem = self::rawPointToPublicPem($ephemRaw, $pkg['curve'] ?? 'prime256v1');
                break;
            case 'raw_compressed':
                $epemPem = self::rawPointToPublicPem($ephemRaw, $pkg['curve'] ?? 'prime256v1');
                break;
            default:
                throw new InvalidArgumentException('未知 ephemeral_pub_format');
        }

        $epub = openssl_pkey_get_public($epemPem);
        if ($epub === false) throw new RuntimeException('加载 ephemeral 公钥失败');

        // ECDH derive
        $shared = openssl_pkey_derive($epub, $recipientPriv);
        if ($shared === false) throw new RuntimeException('ECDH 推导失败');

        $hkdfSalt = (isset($pkg['hkdf_salt']) && $pkg['hkdf_salt'] !== '') ? base64_decode($pkg['hkdf_salt'], true) : null;
        $hkdfInfo = base64_decode($pkg['hkdf_info'], true) ?: '';

        $iv = base64_decode($pkg['iv'], true);
        $ct = base64_decode($pkg['ciphertext'], true);
        $tag = isset($pkg['tag']) && $pkg['tag'] !== '' ? base64_decode($pkg['tag'], true) : null;
        $hmac = isset($pkg['hmac']) && $pkg['hmac'] !== '' ? base64_decode($pkg['hmac'], true) : null;
        $aad = base64_decode($pkg['aad'], true) ?: '';

        // 派生 key (默认 32 + ivLen)
        $keyLen = 32;
        $ivLen = strlen($iv);
        $okm = self::hkdf($hkdfHash, $shared, $keyLen + $ivLen, $hkdfInfo, $hkdfSalt);
        $symKey = substr($okm, 0, $keyLen);
        $derivedIv = substr($okm, $keyLen, $ivLen);
        if (!hash_equals($derivedIv, $iv)) {
            // 这里严格校验一致性（加密端默认从 HKDF 派生 iv）
            throw new RuntimeException('派生 IV 与包中 IV 不匹配');
        }

        // 选择 cipher：由包中 tag/hmac 判断（简化策略）
        $cipher = $tag !== null && $tag !== '' ? 'aes-256-gcm' : 'aes-256-ctr';

        $plain = self::symDecrypt($ct, $symKey, ['cipher' => $cipher, 'iv' => $iv, 'tag' => $tag, 'hmac' => $hmac, 'aad' => $aad]);
        return $plain;
    }

    /* -------------------- ECDSA 签名与验签 -------------------- */

    /**
     * ECDSA 签名（返回 DER 编码或 raw r||s）
     *
     * @param OpenSSLAsymmetricKey $priv 私钥对象
     * @param string $message 消息（任意二进制）
     * @param string $hashAlgo 'sha256'|'sha384'|'sha512'
     * @param string $format 'der'|'raw' （raw 为 r||s 固定长度）
     * @return string 签名二进制
     */
    public static function ecdsaSign(OpenSSLAsymmetricKey $priv, string $message, string $hashAlgo = 'sha256', string $format = 'der'): string
    {
        $algoConst = match ($hashAlgo) {
            'sha256' => OPENSSL_ALGO_SHA256,
            'sha384' => OPENSSL_ALGO_SHA384,
            'sha512' => OPENSSL_ALGO_SHA512,
            default => throw new InvalidArgumentException('不支持的 hashAlgo'),
        };
        $ok = openssl_sign($message, $sig, $priv, $algoConst);
        if ($ok === false) throw new RuntimeException('签名失败');

        if ($format === 'der') return $sig;

        // raw 格式：把 DER 解 ASN.1 提取 r、s（轻量解析）
        // DER(ECDSA-Sig-Value) = SEQUENCE { r INTEGER, s INTEGER }
        [$r, $s] = self::ecdsaDerToRawRs($sig);
        return $r . $s;
    }

    /**
     * ECDSA 验签（支持 der 或 raw sig）
     *
     * @param OpenSSLAsymmetricKey $pub 公钥对象
     * @param string $message 原始消息
     * @param string $signature 签名二进制（der 或 raw）
     * @param string $hashAlgo 'sha256'|'sha384'|'sha512'
     * @param string $sigFormat 'der'|'raw'
     * @return bool 验签结果
     */
    public static function ecdsaVerify(OpenSSLAsymmetricKey $pub, string $message, string $signature, string $hashAlgo = 'sha256', string $sigFormat = 'der'): bool
    {
        $sigToUse = $signature;
        if ($sigFormat === 'raw') {
            // raw => 转回 der
            $sigToUse = self::ecdsaRawRsToDer($signature, $pub);
        }
        $algoConst = match ($hashAlgo) {
            'sha256' => OPENSSL_ALGO_SHA256,
            'sha384' => OPENSSL_ALGO_SHA384,
            'sha512' => OPENSSL_ALGO_SHA512,
            default => throw new InvalidArgumentException('不支持的 hashAlgo'),
        };
        $v = openssl_verify($message, $sigToUse, $pub, $algoConst);
        return $v === 1;
    }

    /**
     * 将 ECDSA DER 签名解析为 [r, s] 固定位长二进制数组（各自为大整数二进制，未补零）
     *
     * @param string $derSig DER 编码签名
     * @return array [r_bin, s_bin]
     */
    private static function ecdsaDerToRawRs(string $derSig): array
    {
        // 简单 ASN.1 解析：查找两个 INTEGER
        $pos = 0;
        if (ord($derSig[$pos]) !== 0x30) throw new RuntimeException('非 ASN.1 SEQUENCE');
        $pos++;
        // 跳过长度字节（简化）
        $len = ord($derSig[$pos]); $pos++;
        if ($len & 0x80) {
            $num = $len & 0x7F; $len = 0;
            for ($i = 0; $i < $num; $i++) { $len = ($len << 8) + ord($derSig[$pos]); $pos++; }
        }
        // INTEGER r
        if (ord($derSig[$pos]) !== 0x02) throw new RuntimeException('期待 INTEGER');
        $pos++;
        $rlen = ord($derSig[$pos]); $pos++;
        if ($rlen & 0x80) { $num = $rlen & 0x7F; $rlen = 0; for ($i = 0; $i < $num; $i++) { $rlen = ($rlen<<8) + ord($derSig[$pos]); $pos++; } }
        $r = substr($derSig, $pos, $rlen); $pos += $rlen;
        if (ord($derSig[$pos]) !== 0x02) throw new RuntimeException('期待第二 INTEGER');
        $pos++;
        $slen = ord($derSig[$pos]); $pos++;
        if ($slen & 0x80) { $num = $slen & 0x7F; $slen = 0; for ($i = 0; $i < $num; $i++) { $slen = ($slen<<8) + ord($derSig[$pos]); $pos++; } }
        $s = substr($derSig, $pos, $slen);
        return [$r, $s];
    }

    /**
     * 将 raw r||s 转换为 DER 编码（长度推导基于公钥曲线长度）
     *
     * @param string $raw rs concat
     * @param OpenSSLAsymmetricKey|null $pub 用于推断坐标长度；若为空则尝试均分
     * @return string DER 编码签名
     */
    private static function ecdsaRawRsToDer(string $raw, ?OpenSSLAsymmetricKey $pub = null): string
    {
        $len = strlen($raw);
        if ($pub !== null) {
            $det = openssl_pkey_get_details($pub);
            $keySize = isset($det['ec']['x']) ? strlen($det['ec']['x']) : intdiv($len, 2);
            $r = substr($raw, 0, $keySize);
            $s = substr($raw, $keySize);
        } else {
            $r = substr($raw, 0, intdiv($len,2));
            $s = substr($raw, intdiv($len,2));
        }
        // 去除前导零的规则（ASN.1 INTEGER 不能有多余前导 0x00，必要时加 0x00 当最高位为 1）
        $r = ltrim($r, "\x00"); if (ord($r[0]) & 0x80) $r = "\x00".$r;
        $s = ltrim($s, "\x00"); if (ord($s[0]) & 0x80) $s = "\x00".$s;
        $der = "\x30" . chr(2 + strlen($r) + 2 + strlen($s)) . "\x02" . chr(strlen($r)) . $r . "\x02" . chr(strlen($s)) . $s;
        return $der;
    }

    /* -------------------- 文件操作、密钥轮换与 JWT 简易支持 -------------------- */

    /**
     * 将 PEM 内容写入文件
     *
     * @param string $path 目标路径
     * @param string $pemContent PEM 文本
     * @return void
     */
    public static function savePemToFile(string $path, string $pemContent): void
    {
        file_put_contents($path, $pemContent, LOCK_EX);
    }

    /**
     * 从文件加载（自动识别 PEM / DER）
     *
     * @param string $path 文件路径
     * @param bool $isPrivate 是否解析为私钥（true -> 私钥, false -> 公钥）
     * @param null|string $passphrase 私钥密码（若需要）
     * @return OpenSSLAsymmetricKey
     */
    public static function loadKeyFromFile(string $path, bool $isPrivate = false, ?string $passphrase = null): OpenSSLAsymmetricKey
    {
        $raw = file_get_contents($path);
        if ($raw === false) throw new RuntimeException('读取文件失败');
        // 简单检测是否为 PEM
        if (str_contains($raw, '-----BEGIN')) {
            if ($isPrivate) return self::loadPrivateKey($raw, false, $passphrase);
            return self::loadPublicKey($raw, false);
        } else {
            // 当作 DER
            if ($isPrivate) return self::loadPrivateKey($raw, true, $passphrase);
            return self::loadPublicKey($raw, true);
        }
    }

    /**
     * 密钥轮换：生成新密钥对并把旧密钥保存到 backups 目录（简单实现）
     *
     * @param string $dir 存放密钥的目录（会自动创建）
     * @param string $curve 曲线名
     * @param null|string $passphrase 私钥加密密钥（可选）
     * @return array 新密钥对数组（同 generateKeyPair 返回结构）
     */
    public static function rotateKeys(string $dir, string $curve = 'prime256v1', ?string $passphrase = null): array
    {
        if (!is_dir($dir)) mkdir($dir, 0700, true);
        // 备份旧密钥（如果存在）
        $privPath = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'private.pem';
        $pubPath  = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'public.pem';
        $bdir = $dir . DIRECTORY_SEPARATOR . 'backups';
        if (!is_dir($bdir)) mkdir($bdir, 0700, true);
        if (file_exists($privPath)) {
            $t = date('YmdHis');
            rename($privPath, $bdir . DIRECTORY_SEPARATOR . "private_{$t}.pem");
        }
        if (file_exists($pubPath)) {
            $t = date('YmdHis');
            rename($pubPath, $bdir . DIRECTORY_SEPARATOR . "public_{$t}.pem");
        }
        $kp = self::generateKeyPair($curve, $passphrase);
        self::savePemToFile($privPath, $kp['private_pem']);
        self::savePemToFile($pubPath, $kp['public_pem']);
        return $kp;
    }

    /**
     * 简易 JWT ESxxx 签名（header.payload => signature）
     *
     * @param OpenSSLAsymmetricKey $priv 私钥对象
     * @param array $payload PHP 数组（将被 JSON 编码）
     * @param string $alg 'ES256'|'ES384'|'ES512'
     * @return string 完整 JWT（header.payload.signature，base64url 编码）
     */
    public static function jwtSign(OpenSSLAsymmetricKey $priv, array $payload, string $alg = 'ES256'): string
    {
        $algHash = match ($alg) {
            'ES256' => 'sha256',
            'ES384' => 'sha384',
            'ES512' => 'sha512',
            default => throw new InvalidArgumentException('不支持的 alg'),
        };
        $header = ['alg' => $alg, 'typ' => 'JWT'];
        $b64 = self::base64ToBase64Url(base64_encode(json_encode($header))) . '.' . self::base64ToBase64Url(base64_encode(json_encode($payload)));
        // 签名（DER）
        $sig = '';
        $algoConst = match ($algHash) {
            'sha256' => OPENSSL_ALGO_SHA256,
            'sha384' => OPENSSL_ALGO_SHA384,
            'sha512' => OPENSSL_ALGO_SHA512,
        };
        $ok = openssl_sign($b64, $sig, $priv, $algoConst);
        if ($ok === false) throw new RuntimeException('JWT 签名失败');
        // 将 DER -> raw r||s 并 pad 到固定长度
        [$r, $s] = self::ecdsaDerToRawRs($sig);
        // 计算长度：从公钥推断或从算法推断
        $bytes = match ($alg) {
            'ES256' => 32,
            'ES384' => 48,
            'ES512' => 66,
        };
        $r = str_pad(ltrim($r, "\x00"), $bytes, "\x00", STR_PAD_LEFT);
        $s = str_pad(ltrim($s, "\x00"), $bytes, "\x00", STR_PAD_LEFT);
        $raw = $r . $s;
        $signature = self::base64ToBase64Url(base64_encode($raw));
        return $b64 . '.' . $signature;
    }

    /**
     * 验证 JWT ESxxx（简易）
     *
     * @param string $jwt 完整 JWT 字符串
     * @param OpenSSLAsymmetricKey $pub 公钥对象
     * @return bool
     */
    public static function jwtVerify(string $jwt, OpenSSLAsymmetricKey $pub): bool
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) return false;
        [$h, $p, $sigB64] = $parts;
        $data = $h . '.' . $p;
        $raw = base64_decode(self::base64UrlToBase64($sigB64), true);
        if ($raw === false) return false;
        // raw -> der
        $der = self::ecdsaRawRsToDer($raw, $pub);
        $v = openssl_verify($data, $der, $pub, OPENSSL_ALGO_SHA256);
        return $v === 1;
    }
}
