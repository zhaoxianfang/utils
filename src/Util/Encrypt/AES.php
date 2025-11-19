<?php

namespace zxf\Util\Encrypt;

use InvalidArgumentException;
use Random\RandomException;
use RuntimeException;

/**
 * AES 对称加密解密类
 * 使用高级加密标准 (Advanced Encryption Standard) 进行数据加密和解密
 * 完全兼容 PHP 8.2+，提供工业级的加密安全解决方案
 *
 * 使用场景：
 * - 数据库敏感字段加密存储
 * - 文件加密和安全传输
 * - HTTPS通信中的业务数据加密
 * - 用户会话数据保护
 * - 移动应用本地数据加密
 * - 支付信息加密处理
 *
 * 安全特性：
 * - 支持 AES-128、AES-192、AES-256 三种密钥长度
 * - 支持 CBC、CTR、GCM 等多种加密模式
 * - 自动生成安全的随机初始化向量 (IV)
 * - 提供完整性保护（GCM模式）
 * - 抵抗常见的加密攻击
 */

class AES
{
    /**
     * @var string 加密密钥 - 用于加密和解密的核心秘密数据
     *              长度要求：根据选择的加密算法确定
     *              生成方法：使用 AES::generateKey() 方法生成安全随机密钥
     *              安全要求：必须妥善保管，建议存储在安全的环境变量或密钥管理系统中
     */
    private string $key;

    /**
     * @var string 加密算法和方法 - 指定具体的AES算法和工作模式
     *              格式：'aes-{密钥长度}-{模式}'（小写）
     *              示例：'aes-256-gcm', 'aes-128-cbc', 'aes-192-ctr'
     *              默认值：'aes-256-gcm'（推荐，提供认证加密）
     */
    private string $cipher;

    /**
     * 支持的加密算法和方法枚举
     * 列出了PHP 8.2+支持的所有AES相关加密方法及其参数配置
     *
     * 结构说明：
     * - key_len: 密钥长度（字节）
     * - iv_len: 初始化向量长度（字节）
     */
    private const SUPPORTED_CIPHERS = [
        // CBC模式 (Cipher Block Chaining) - 密码块链模式
        // 特点：需要填充，提供保密性，适合文件加密
        'aes-128-cbc' => ['key_len' => 16, 'iv_len' => 16],  // AES-128，CBC模式
        'aes-192-cbc' => ['key_len' => 24, 'iv_len' => 16],  // AES-192，CBC模式  
        'aes-256-cbc' => ['key_len' => 32, 'iv_len' => 16],  // AES-256，CBC模式

        // CTR模式 (Counter) - 计数器模式
        // 特点：流加密，不需要填充，适合实时数据流
        'aes-128-ctr' => ['key_len' => 16, 'iv_len' => 16],  // AES-128，CTR模式
        'aes-192-ctr' => ['key_len' => 24, 'iv_len' => 16],  // AES-192，CTR模式
        'aes-256-ctr' => ['key_len' => 32, 'iv_len' => 16],  // AES-256，CTR模式

        // GCM模式 (Galois/Counter Mode) - 伽罗瓦/计数器模式
        // 特点：认证加密，提供保密性和完整性，推荐使用
        'aes-128-gcm' => ['key_len' => 16, 'iv_len' => 12],  // AES-128，GCM模式
        'aes-192-gcm' => ['key_len' => 24, 'iv_len' => 12],  // AES-192，GCM模式
        'aes-256-gcm' => ['key_len' => 32, 'iv_len' => 12],  // AES-256，GCM模式（最安全）
    ];

    /**
     * 构造函数 - 初始化AES加密实例
     * 创建AES加密器实例，配置加密算法和密钥
     *
     * @param string $key 加密密钥
     *                    - 类型：string（二进制字符串）
     *                    - 长度要求：必须与所选算法的密钥长度要求一致
     *                    - 生成方法：使用 AES::generateKey() 静态方法生成
     *                    - 安全提示：密钥必须保密，不要硬编码在源代码中
     *
     * @param string $cipher 加密算法和方法
     *                      - 类型：string
     *                      - 可选值：
     *                        'aes-128-cbc', 'aes-192-cbc', 'aes-256-cbc'
     *                        'aes-128-ctr', 'aes-192-ctr', 'aes-256-ctr'
     *                        'aes-128-gcm', 'aes-192-gcm', 'aes-256-gcm'
     *                      - 推荐值：
     *                        'aes-256-gcm' - 最高安全性，提供完整性保护
     *                        'aes-256-cbc' - 高安全性，兼容性好
     *                        'aes-128-gcm' - 平衡安全性和性能
     *                      - 默认值：'aes-256-gcm'
     *
     * @throws InvalidArgumentException 当密钥长度不合法或加密算法不支持时抛出
     * @throws RuntimeException 当系统不支持指定的加密方法时抛出
     *
     * 示例用法：
     * $key = AES::generateKey('aes-256-gcm'); // 生成256位随机密钥
     * $aes = new AES($key, 'aes-256-gcm');    // 创建AES-256-GCM加密器
     */
    public function __construct(string $key, string $cipher = 'aes-256-gcm')
    {
        // 统一转换为小写，确保算法名称一致性
        $cipher = strtolower($cipher);

        // 验证请求的加密算法是否在支持列表中
        if (!array_key_exists($cipher, self::SUPPORTED_CIPHERS)) {
            throw new InvalidArgumentException(
                '不支持的AES加密算法: ' . $cipher .
                '。支持的算法有: ' . implode(', ', array_keys(self::SUPPORTED_CIPHERS))
            );
        }

        // 获取算法要求的密钥长度
        $expectedKeyLen = self::SUPPORTED_CIPHERS[$cipher]['key_len'];

        // 验证提供的密钥长度是否符合算法要求
        if (strlen($key) !== $expectedKeyLen) {
            throw new InvalidArgumentException(
                'AES密钥长度不正确，算法 ' . $cipher . ' 要求 ' . $expectedKeyLen .
                ' 字节密钥，实际提供 ' . strlen($key) . ' 字节。' .
                '请使用 AES::generateKey() 方法生成正确长度的密钥。'
            );
        }

        // 检查当前PHP环境的OpenSSL扩展是否支持指定的加密方法
        if (!in_array($cipher, openssl_get_cipher_methods(), true)) {
            throw new RuntimeException(
                '当前PHP环境不支持加密方法: ' . $cipher .
                '。请确保OpenSSL扩展已安装并启用，且支持该加密算法。'
            );
        }

        // 初始化实例变量
        $this->key = $key;          // 设置加密密钥
        $this->cipher = $cipher;    // 设置加密算法
    }

    /**
     * 加密数据 - 使用AES算法对明文数据进行加密
     * 对输入数据进行AES加密，返回包含密文和加密参数的数组
     *
     * @param string $data 待加密的明文数据
     *                    - 类型：string
     *                    - 内容：任意字符串数据
     *                    - 长度：支持任意长度数据，会自动处理分组
     *                    - 编码：建议使用UTF-8编码
     *                    - 安全：确保数据不包含敏感信息的日志或调试信息
     *
     * @param string|null $aad 附加认证数据（仅GCM模式有效）
     *                        - 类型：string|null
     *                        - 用途：提供额外的完整性保护，不加密但参与认证计算
     *                        - 示例：协议头、时间戳、版本号、用户ID等元数据
     *                        - 要求：解密时必须提供相同的AAD数据
     *                        - 默认值：null（不使用附加认证数据）
     *
     * @return array 加密结果数组，包含加密后的数据和相关参数
     *               - 'ciphertext': string Base64编码的密文数据
     *               - 'iv': string Base64编码的初始化向量（随机生成）
     *               - 'tag': string|null Base64编码的身份验证标签（GCM模式）
     *               - 'cipher': string 使用的加密算法标识
     *               - 'aad': string|null Base64编码的附加认证数据（如果提供）
     *
     * @throws RuntimeException|RandomException 当加密操作失败时抛出，包含具体的OpenSSL错误信息
     *
     * 加密过程说明：
     * 1. 生成密码学安全的随机初始化向量 (IV) - 确保相同明文每次加密结果不同
     * 2. 根据加密模式选择相应的加密方式（GCM或其他模式）
     * 3. 执行实际的加密操作，使用OpenSSL扩展
     * 4. 对结果进行Base64编码以便安全存储和传输
     *
     * 安全重要提示：
     * - 每次加密都会生成不同的IV，确保语义安全
     * - GCM模式提供完整性保护，能够检测数据篡改
     * - 密文必须与IV和tag（GCM模式）一起存储
     *
     * 示例用法：
     * $encrypted = $aes->encrypt('敏感数据', '用户123');
     * // 存储 $encrypted['ciphertext'], $encrypted['iv'], $encrypted['tag'] 等
     */
    public function encrypt(string $data, ?string $aad = null): array
    {
        // 获取当前加密算法需要的IV长度
        $ivLength = self::SUPPORTED_CIPHERS[$this->cipher]['iv_len'];

        // 生成密码学安全的随机初始化向量
        // IV不需要保密，但必须唯一且随机，用于确保相同明文的加密结果不同
        $iv = random_bytes($ivLength);

        // 设置OpenSSL加密选项 - 使用原始数据输出，不进行额外编码
        $options = OPENSSL_RAW_DATA;

        // 初始化变量
        $tag = '';       // GCM模式的身份验证标签
        $encrypted = ''; // 加密后的数据

        // 根据加密模式选择不同的加密方式
        if (str_contains($this->cipher, 'gcm')) {
            // GCM模式加密 - 提供认证加密（保密性 + 完整性）
            $encrypted = openssl_encrypt(
                $data,           // 待加密的明文数据
                $this->cipher,   // 加密算法名称
                $this->key,      // 加密密钥
                $options,        // 加密选项（原始数据输出）
                $iv,             // 初始化向量
                $tag,            // 输出的身份验证标签（引用传递）
                $aad ?? '',      // 附加认证数据（Additional Authenticated Data）
                16               // 认证标签长度（16字节 = 128位）
            );
        } else {
            // 其他模式加密（CBC、CTR等）- 仅提供保密性
            $encrypted = openssl_encrypt(
                $data,         // 待加密的明文数据
                $this->cipher, // 加密算法名称
                $this->key,    // 加密密钥
                $options,      // 加密选项（原始数据输出）
                $iv            // 初始化向量
            );
        }

        // 检查加密操作是否成功
        if ($encrypted === false) {
            // 加密失败，抛出异常并包含详细的错误信息
            throw new RuntimeException('AES加密失败: ' . $this->getOpenSSLError());
        }

        // 构建加密结果数组
        $result = [
            'ciphertext' => base64_encode($encrypted),  // Base64编码的密文，便于存储传输
            'iv' => base64_encode($iv),                 // Base64编码的IV，必须与密文一起保存
            'cipher' => $this->cipher,                  // 使用的加密算法，解密时需要
        ];

        // 如果是GCM模式，添加身份验证标签
        if (!empty($tag)) {
            $result['tag'] = base64_encode($tag);  // Base64编码的认证标签
        }

        // 如果提供了附加认证数据，也进行Base64编码保存
        if ($aad !== null) {
            $result['aad'] = base64_encode($aad);  // Base64编码的附加认证数据
        }

        return $result;
    }

    /**
     * 解密数据 - 使用AES算法对密文数据进行解密
     * 对加密数据进行AES解密，返回原始明文数据
     *
     * @param string $ciphertext Base64编码的密文数据
     *                          - 类型：string
     *                          - 格式：必须是encrypt方法返回的ciphertext字段
     *                          - 编码：Base64编码的二进制数据
     *                          - 来源：从存储或传输中获取的加密数据
     *
     * @param string $iv Base64编码的初始化向量
     *                  - 类型：string
     *                  - 来源：必须是encrypt方法返回的iv字段
     *                  - 用途：确保解密使用与加密相同的IV
     *                  - 重要性：必须与加密时使用的IV完全一致
     *
     * @param string|null $tag Base64编码的身份验证标签（GCM模式必需）
     *                        - 类型：string|null
     *                        - 要求：GCM模式必须提供，其他模式为null
     *                        - 用途：验证数据完整性和真实性
     *                        - 重要性：GCM模式解密必须提供正确的tag
     *
     * @param string|null $aad Base64编码的附加认证数据
     *                        - 类型：string|null
     *                        - 要求：必须与加密时使用的aad一致
     *                        - 用途：参与完整性验证计算
     *                        - 默认值：null（加密时未使用AAD）
     *
     * @return string 解密后的原始明文数据
     *
     * @throws InvalidArgumentException 当参数格式不正确或GCM模式缺少tag时抛出
     * @throws RuntimeException 当解密操作失败时抛出，可能原因包括：
     *                         - 密钥不正确
     *                         - IV被篡改
     *                         - 认证标签验证失败（GCM模式）
     *                         - 数据被损坏或篡改
     *
     * 解密过程说明：
     * 1. 对Base64编码的参数进行解码，还原为原始二进制数据
     * 2. 根据加密模式选择相应的解密方式
     * 3. 执行实际的解密操作，使用OpenSSL扩展
     * 4. 验证解密结果的完整性（GCM模式）
     * 5. 返回解密后的明文数据
     *
     * 安全特性：
     * - GCM模式会在解密时自动验证数据完整性
     * - 如果认证失败，OpenSSL会返回false，不会暴露部分解密的数据
     * - 能够检测数据篡改、伪造和重放攻击
     *
     * 示例用法：
     * $decrypted = $aes->decrypt($encrypted['ciphertext'], $encrypted['iv'], $encrypted['tag']);
     */
    public function decrypt(
        string $ciphertext,
        string $iv,
        ?string $tag = null,
        ?string $aad = null
    ): string {
        // 解码Base64编码的密文数据
        $encryptedRaw = base64_decode($ciphertext, true);
        // 解码Base64编码的初始化向量
        $ivRaw = base64_decode($iv, true);

        // 验证Base64解码是否成功
        if ($encryptedRaw === false || $ivRaw === false) {
            throw new InvalidArgumentException('Base64解码失败：密文或IV参数格式不正确');
        }

        // 解码可选的认证标签和附加认证数据
        $tagRaw = $tag ? base64_decode($tag, true) : '';    // GCM模式的认证标签
        $aadRaw = $aad ? base64_decode($aad, true) : '';    // 附加认证数据

        // GCM模式必须提供身份验证标签
        if (str_contains($this->cipher, 'gcm') && empty($tag)) {
            throw new InvalidArgumentException(
                'GCM加密模式必须提供身份验证标签(tag)参数以验证数据完整性。' .
                '请确保从加密结果中获取并传递tag参数。'
            );
        }

        // 设置OpenSSL解密选项 - 使用原始数据输入
        $options = OPENSSL_RAW_DATA;
        $decrypted = '';  // 解密后的数据

        // 根据加密模式选择不同的解密方式
        if (str_contains($this->cipher, 'gcm')) {
            // GCM模式解密 - 同时验证完整性
            $decrypted = openssl_decrypt(
                $encryptedRaw,  // 待解密的密文数据（二进制）
                $this->cipher,  // 加密算法名称
                $this->key,     // 解密密钥
                $options,       // 解密选项（原始数据输入）
                $ivRaw,         // 初始化向量（二进制）
                $tagRaw,        // 身份验证标签（二进制）
                $aadRaw         // 附加认证数据（二进制）
            );
        } else {
            // 其他模式解密（CBC、CTR等）- 仅解密数据
            $decrypted = openssl_decrypt(
                $encryptedRaw,  // 待解密的密文数据（二进制）
                $this->cipher,  // 加密算法名称
                $this->key,     // 解密密钥
                $options,       // 解密选项（原始数据输入）
                $ivRaw          // 初始化向量（二进制）
            );
        }

        // 检查解密操作是否成功
        if ($decrypted === false) {
            throw new RuntimeException('AES解密失败: ' . $this->getOpenSSLError());
        }

        return $decrypted;
    }

    /**
     * 静态方法：生成密码学安全的随机AES密钥
     * 生成符合指定加密算法要求的随机密钥
     *
     * @param string $cipher 加密算法
     *                      - 类型：string
     *                      - 可选值：self::SUPPORTED_CIPHERS 中的任意键名
     *                      - 推荐值：'aes-256-gcm'
     *                      - 默认值：'aes-256-gcm'
     *
     * @return string 生成的随机密钥（二进制字符串）
     *
     * @throws InvalidArgumentException|RandomException 当加密算法不支持时抛出
     *
     * 安全提示：
     * - 必须使用密码学安全的随机数生成器（random_bytes）
     * - 密钥应该存储在安全的地方（如环境变量、密钥管理系统）
     * - 定期轮换密钥以增强安全性（建议每1-2年）
     * - 不同用途应使用不同的密钥
     *
     * 示例用法：
     * $key256 = AES::generateKey('aes-256-gcm'); // 生成AES-256密钥
     * $key128 = AES::generateKey('aes-128-cbc'); // 生成AES-128密钥
     */
    public static function generateKey(string $cipher = 'aes-256-gcm'): string
    {
        // 统一转换为小写，确保算法名称一致性
        $cipher = strtolower($cipher);

        // 验证加密算法是否支持
        if (!array_key_exists($cipher, self::SUPPORTED_CIPHERS)) {
            throw new InvalidArgumentException(
                '不支持的AES加密算法: ' . $cipher .
                '。支持的算法有: ' . implode(', ', array_keys(self::SUPPORTED_CIPHERS))
            );
        }

        // 获取算法要求的密钥长度
        $keyLength = self::SUPPORTED_CIPHERS[$cipher]['key_len'];

        // 使用密码学安全的随机字节生成器生成密钥
        return random_bytes($keyLength);
    }

    /**
     * 获取当前PHP环境支持的所有AES加密方法
     * 查询系统实际可用的AES加密算法列表
     *
     * @return array 可用的AES加密方法列表
     *               - 类型：string[]
     *               - 内容：系统支持的AES算法名称数组
     *
     * 用途：
     * - 检查系统支持的加密算法
     * - 动态选择可用的加密方法
     * - 兼容性检查和调试
     * - 功能检测和回退策略
     */
    public static function getSupportedCiphers(): array
    {
        // 获取OpenSSL支持的所有加密方法
        $availableCiphers = openssl_get_cipher_methods();

        // 过滤出AES相关的加密方法
        return array_filter($availableCiphers, function($cipher) {
            return str_starts_with($cipher, 'aes-');
        });
    }

    /**
     * 获取当前实例使用的加密算法信息
     * 返回当前配置的加密算法的详细信息
     *
     * @return array 加密算法详细信息
     *               - 'cipher': string 加密算法名称
     *               - 'key_length': int 密钥长度（位）
     *               - 'key_size': int 密钥大小（字节）
     *               - 'iv_length': int IV长度（字节）
     *               - 'mode': string 加密模式（cbc/ctr/gcm）
     *               - 'has_auth': bool 是否提供认证加密
     *
     * 用途：
     * - 调试和日志记录
     * - 配置验证
     * - 系统状态监控
     */
    public function getCipherInfo(): array
    {
        // 从算法名称中提取模式和密钥长度信息
        preg_match('/aes-(\d+)-(\w+)/', $this->cipher, $matches);
        $keyBits = $matches[1] ?? 0;  // 密钥长度（位）
        $mode = $matches[2] ?? 'unknown';  // 加密模式

        return [
            'cipher' => $this->cipher,           // 完整算法名称
            'key_length' => (int)$keyBits,       // 密钥长度（位）
            'key_size' => (int)($keyBits / 8),   // 密钥大小（字节）
            'iv_length' => self::SUPPORTED_CIPHERS[$this->cipher]['iv_len'], // IV长度
            'mode' => $mode,                     // 加密模式
            'has_auth' => $mode === 'gcm'        // 是否认证加密
        ];
    }

    /**
     * 获取 OpenSSL 错误信息
     * 收集并格式化OpenSSL扩展的错误信息
     *
     * @return string 格式化的错误信息字符串
     *
     * 实现说明：
     * - OpenSSL错误以栈的形式存储，需要循环读取
     * - 每次调用openssl_error_string()会弹出最早的错误
     * - 该方法会收集所有可用的错误信息
     */
    private function getOpenSSLError(): string
    {
        $errorMessages = [];  // 存储错误消息的数组

        // 循环读取所有OpenSSL错误
        while ($errorMessage = openssl_error_string()) {
            $errorMessages[] = $errorMessage;  // 收集错误消息
        }

        // 返回格式化的错误信息
        return $errorMessages ? implode('; ', $errorMessages) : '未知OpenSSL错误';
    }
}
