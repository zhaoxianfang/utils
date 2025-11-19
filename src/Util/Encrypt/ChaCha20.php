<?php

namespace zxf\Util\Encrypt;


use InvalidArgumentException;
use Random\RandomException;
use RuntimeException;

/**
 * ChaCha20 流加密解密类
 * 使用 ChaCha20 流密码算法结合 Poly1305 认证器进行高速加密解密
 * 完全兼容 PHP 8.2+，提供高性能的加密解决方案
 *
 * 使用场景：
 * - 移动设备应用程序加密（性能优于AES）
 * - 实时通信协议加密（如WebRTC、VoIP）
 * - VPN和代理服务加密
 * - TLS 1.3协议中的加密套件
 * - 需要高性能加密的网络应用
 * - 物联网设备安全通信
 *
 * 技术特点：
 * - 在软件实现上比AES有更好的性能表现
 * - 对时序旁路攻击有天然的抵抗性
 * - 内置Poly1305消息认证码提供完整性保护
 * - 简单的算法设计，易于正确实现
 * - 在资源受限环境中表现优异
 * - 提供前向安全性
 */
class ChaCha20
{
    /**
     * @var string 加密密钥 - ChaCha20使用256位（32字节）密钥
     *              长度要求：必须为32字节（256位）
     *              生成方法：使用 ChaCha20::generateKey() 静态方法生成
     *              安全要求：必须使用密码学安全的随机数生成，妥善保管密钥
     */
    private string $key;

    /**
     * @var string 加密算法 - 指定ChaCha20的具体变体
     *              可选值：'chacha20-poly1305', 'xchacha20-poly1305'
     *              推荐值：'chacha20-poly1305'（标准版本，兼容性好）
     *              默认值：'chacha20-poly1305'
     */
    private string $cipher;

    /**
     * 支持的加密算法枚举
     * 列出了PHP 8.2+支持的ChaCha20相关加密方法及其参数配置
     *
     * 结构说明：
     * - key_len: 密钥长度（字节）
     * - iv_len: 随机数长度（字节）
     * - description: 算法描述
     */
    private const SUPPORTED_CIPHERS = [
        // ChaCha20-Poly1305 - 标准ChaCha20流加密 + Poly1305认证
        // 特点：IETF标准，12字节nonce，广泛支持
        'chacha20-poly1305' => [
            'key_len' => 32,      // 32字节密钥（256位）
            'iv_len' => 12,       // 12字节随机数
            'description' => '标准ChaCha20-Poly1305（IETF版本）'
        ],

        // XChaCha20-Poly1305 - 扩展ChaCha20流加密 + Poly1305认证  
        // 特点：24字节nonce，更好的随机数安全性
        'xchacha20-poly1305' => [
            'key_len' => 32,      // 32字节密钥（256位）
            'iv_len' => 24,       // 24字节随机数
            'description' => '扩展ChaCha20-Poly1305（XChaCha20版本）'
        ],
    ];

    /**
     * 构造函数 - 初始化ChaCha20加密实例
     * 创建ChaCha20加密器实例，配置加密算法和密钥
     *
     * @param string $key 加密密钥
     *                    - 类型：string（二进制字符串）
     *                    - 长度要求：必须为32字节（256位）
     *                    - 生成方法：使用 ChaCha20::generateKey() 静态方法生成
     *                    - 安全提示：密钥必须保密，建议从安全存储加载
     *
     * @param string $cipher 加密算法
     *                      - 类型：string
     *                      - 可选值：'chacha20-poly1305', 'xchacha20-poly1305'
     *                      - 推荐值：'chacha20-poly1305'（提供认证加密，广泛支持）
     *                      - 默认值：'chacha20-poly1305'
     *
     * @throws InvalidArgumentException 当密钥长度不正确或算法不支持时抛出
     * @throws RuntimeException 当系统不支持指定加密方法时抛出
     *
     * 安全建议：
     * - 始终使用ChaCha20-Poly1305以获取完整性保护
     * - 确保每个加密操作使用不同的nonce（随机数）
     * - 定期轮换加密密钥（建议每6-12个月）
     * - 在移动设备和资源受限环境中优先选择ChaCha20
     *
     * 示例用法：
     * $key = ChaCha20::generateKey(); // 生成32字节随机密钥
     * $chacha = new ChaCha20($key, 'chacha20-poly1305'); // 创建加密器
     */
    public function __construct(string $key, string $cipher = 'chacha20-poly1305')
    {
        // 统一转换为小写，确保算法名称一致性
        $cipher = strtolower($cipher);

        // 验证请求的加密算法是否在支持列表中
        if (!array_key_exists($cipher, self::SUPPORTED_CIPHERS)) {
            throw new InvalidArgumentException(
                '不支持的ChaCha20加密算法: ' . $cipher .
                '。支持的算法有: ' . implode(', ', array_keys(self::SUPPORTED_CIPHERS))
            );
        }

        // 获取算法要求的密钥长度
        $expectedKeyLen = self::SUPPORTED_CIPHERS[$cipher]['key_len'];

        // 验证提供的密钥长度是否符合算法要求
        if (strlen($key) !== $expectedKeyLen) {
            throw new InvalidArgumentException(
                'ChaCha20密钥长度不正确，算法 ' . $cipher . ' 要求 ' . $expectedKeyLen .
                ' 字节密钥，实际提供 ' . strlen($key) . ' 字节。' .
                '请使用 ChaCha20::generateKey() 方法生成正确长度的密钥。'
            );
        }

        // 检查当前PHP环境的OpenSSL扩展是否支持指定的加密方法
        if (!in_array($cipher, openssl_get_cipher_methods(), true)) {
            throw new RuntimeException(
                '当前PHP环境不支持加密方法: ' . $cipher .
                '。请确保使用PHP 7.2+版本并启用OpenSSL扩展，且支持ChaCha20算法。'
            );
        }

        // 初始化实例变量
        $this->key = $key;                              // 设置加密密钥
        $this->cipher = $cipher;                        // 设置加密算法
    }

    /**
     * 加密数据 - 使用ChaCha20算法对明文数据进行加密
     * 对输入数据进行ChaCha20流加密，返回包含密文和加密参数的数组
     *
     * @param string $data 待加密的明文数据
     *                    - 类型：string
     *                    - 内容：任意字符串数据
     *                    - 长度：支持任意长度数据，流式加密无填充
     *                    - 编码：建议使用UTF-8编码
     *                    - 性能：特别适合大量数据和实时流加密
     *
     * @param string|null $aad 附加认证数据（Additional Authenticated Data）
     *                        - 类型：string|null
     *                        - 用途：提供额外的完整性保护，不加密但参与认证计算
     *                        - 示例：协议头、序列号、时间戳、会话ID等元数据
     *                        - 要求：解密时必须提供相同的AAD数据
     *                        - 默认值：null（不使用附加认证数据）
     *
     * @return array 加密结果数组，包含加密后的数据和相关参数
     *               - 'ciphertext': string Base64编码的密文数据
     *               - 'iv': string Base64编码的随机数（nonce）
     *               - 'tag': string Base64编码的身份验证标签（Poly1305 MAC）
     *               - 'cipher': string 使用的加密算法标识
     *               - 'aad': string|null Base64编码的附加认证数据（如果提供）
     *
     * @throws RuntimeException|RandomException 当加密操作失败时抛出，包含具体的OpenSSL错误信息
     *
     * 加密过程说明：
     * 1. 生成密码学安全的随机nonce（随机数）- 确保相同明文每次加密结果不同
     * 2. 使用ChaCha20算法进行流加密，生成密钥流并与明文异或
     * 3. 使用Poly1305算法生成认证标签，验证数据完整性
     * 4. 对结果进行Base64编码以便安全存储和传输
     *
     * 安全重要提示：
     * - 每个加密操作必须使用不同的nonce（随机数）
     * - 重复使用nonce和密钥组合会严重破坏安全性
     * - nonce不需要保密，但必须唯一且随机
     * - Poly1305认证标签确保数据完整性和真实性
     *
     * 性能优势：
     * - 在软件实现上比AES更快，特别在移动设备上
     * - 对缓存时序攻击有天然抵抗性
     * - 适合高吞吐量的实时加密场景
     *
     * 示例用法：
     * $encrypted = $chacha->encrypt('实时视频流数据', '协议版本:1.0');
     * // 存储 $encrypted['ciphertext'], $encrypted['iv'], $encrypted['tag'] 等
     */
    public function encrypt(string $data, ?string $aad = null): array
    {
        // 获取当前加密算法需要的nonce长度
        $ivLength = self::SUPPORTED_CIPHERS[$this->cipher]['iv_len'];

        // 生成密码学安全的随机nonce（随机数）
        // nonce不需要保密，但必须唯一且随机，用于确保相同明文的加密结果不同
        $iv = random_bytes($ivLength);

        // 初始化Poly1305认证标签变量
        $tag = '';

        // 执行ChaCha20-Poly1305加密操作
        $encrypted = openssl_encrypt(
            $data,           // 待加密的明文数据
            $this->cipher,   // 加密算法名称
            $this->key,      // 加密密钥
            OPENSSL_RAW_DATA, // 加密选项：使用原始数据格式
            $iv,             // 随机数（nonce）
            $tag,            // 输出的Poly1305认证标签（引用传递）
            $aad ?? '',      // 附加认证数据（Additional Authenticated Data）
            16               // 认证标签长度（16字节 = 128位）
        );

        // 检查加密操作是否成功
        if ($encrypted === false) {
            // 加密失败，抛出异常并包含详细的错误信息
            throw new RuntimeException('ChaCha20加密失败: ' . $this->getOpenSSLError());
        }

        // 构建加密结果数组
        $result = [
            'ciphertext' => base64_encode($encrypted),  // Base64编码的密文，便于存储传输
            'iv' => base64_encode($iv),                 // Base64编码的nonce，必须与密文一起保存
            'tag' => base64_encode($tag),               // Base64编码的Poly1305认证标签
            'cipher' => $this->cipher,                  // 使用的加密算法，解密时需要
        ];

        // 如果提供了附加认证数据，也进行Base64编码保存
        if ($aad !== null) {
            $result['aad'] = base64_encode($aad);  // Base64编码的附加认证数据
        }

        return $result;
    }

    /**
     * 解密数据 - 使用ChaCha20算法对密文数据进行解密和验证
     * 对加密数据进行ChaCha20流解密，同时验证Poly1305认证标签
     *
     * @param string $ciphertext Base64编码的密文数据
     *                          - 类型：string
     *                          - 格式：必须是encrypt方法返回的ciphertext字段
     *                          - 编码：Base64编码的二进制数据
     *                          - 来源：从存储或传输中获取的加密数据
     *
     * @param string $iv Base64编码的随机数（nonce）
     *                  - 类型：string
     *                  - 来源：必须是encrypt方法返回的iv字段
     *                  - 用途：确保解密使用与加密相同的nonce
     *                  - 重要性：必须与加密时使用的nonce完全一致
     *
     * @param string $tag Base64编码的身份验证标签（Poly1305 MAC）
     *                   - 类型：string
     *                   - 要求：必须提供，用于验证数据完整性
     *                   - 用途：验证数据完整性和真实性
     *                   - 重要性：认证失败会阻止解密操作
     *
     * @param string|null $aad Base64编码的附加认证数据
     *                        - 类型：string|null
     *                        - 要求：必须与加密时使用的aad一致
     *                        - 用途：参与完整性验证计算
     *                        - 默认值：null（加密时未使用AAD）
     *
     * @return string 解密后的原始明文数据
     *
     * @throws InvalidArgumentException 当参数格式不正确时抛出
     * @throws RuntimeException 当解密或验证失败时抛出
     *
     * 解密过程说明：
     * 1. 对Base64编码的参数进行解码，还原为原始二进制数据
     * 2. 使用ChaCha20算法进行流解密，生成相同的密钥流与密文异或
     * 3. 使用Poly1305验证认证标签，确保数据完整性
     * 4. 如果验证失败，返回错误而不暴露解密数据
     * 5. 返回解密后的明文数据
     *
     * 安全特性：
     * - 认证失败时不会返回部分解密的数据，防止Oracle攻击
     * - 能够检测数据篡改、伪造和重放攻击
     * - 确保数据的机密性、完整性和真实性
     * - 常数时间操作，抵抗时序攻击
     *
     * 示例用法：
     * $decrypted = $chacha->decrypt(
     *     $encrypted['ciphertext'],
     *     $encrypted['iv'],
     *     $encrypted['tag'],
     *     $encrypted['aad'] ?? null
     * );
     */
    public function decrypt(
        string  $ciphertext,
        string  $iv,
        string  $tag,
        ?string $aad = null
    ): string
    {
        // 解码Base64编码的密文数据
        $encryptedRaw = base64_decode($ciphertext, true);
        // 解码Base64编码的随机数（nonce）
        $ivRaw = base64_decode($iv, true);
        // 解码Base64编码的认证标签
        $tagRaw = base64_decode($tag, true);
        // 解码可选的附加认证数据
        $aadRaw = $aad ? base64_decode($aad, true) : '';

        // 验证Base64解码是否成功
        if ($encryptedRaw === false || $ivRaw === false || $tagRaw === false) {
            throw new InvalidArgumentException('Base64解码失败：密文、IV或标签参数格式不正确');
        }

        // 验证nonce长度是否符合当前算法要求
        $expectedIvLen = self::SUPPORTED_CIPHERS[$this->cipher]['iv_len'];
        if (strlen($ivRaw) !== $expectedIvLen) {
            throw new InvalidArgumentException(
                'Nonce长度不正确，算法 ' . $this->cipher . ' 要求 ' . $expectedIvLen .
                ' 字节nonce，实际 ' . strlen($ivRaw) . ' 字节'
            );
        }

        // 执行ChaCha20-Poly1305解密操作
        $decrypted = openssl_decrypt(
            $encryptedRaw,  // 待解密的密文数据（二进制）
            $this->cipher,  // 加密算法名称
            $this->key,     // 解密密钥
            OPENSSL_RAW_DATA, // 解密选项：使用原始数据格式
            $ivRaw,         // 随机数（二进制）
            $tagRaw,        // 认证标签（二进制）
            $aadRaw         // 附加认证数据（二进制）
        );

        // 检查解密操作是否成功
        if ($decrypted === false) {
            throw new RuntimeException('ChaCha20解密失败: ' . $this->getOpenSSLError());
        }

        return $decrypted;
    }

    /**
     * 静态方法：生成密码学安全的随机ChaCha20密钥
     * 生成符合ChaCha20算法要求的32字节随机密钥
     *
     * @param string $cipher 加密算法（用于验证兼容性）
     *                      - 类型：string
     *                      - 可选值：'chacha20-poly1305', 'xchacha20-poly1305'
     *                      - 默认值：'chacha20-poly1305'
     *
     * @return string 生成的32字节随机密钥（二进制字符串）
     *
     * @throws InvalidArgumentException|RandomException 当加密算法不支持时抛出
     *
     * 安全要求：
     * - 必须使用密码学安全的随机数生成器（random_bytes）
     * - 密钥应该存储在安全的地方（环境变量、密钥管理系统）
     * - 建议定期轮换密钥（生产环境每6-12个月）
     * - 不同应用和服务应使用不同的密钥
     *
     * 技术说明：
     * - ChaCha20固定使用32字节（256位）密钥
     * - 密钥强度相当于AES-256
     * - 随机性质量直接影响加密安全性
     *
     * 示例用法：
     * $key = ChaCha20::generateKey(); // 生成ChaCha20密钥
     * $chacha = new ChaCha20($key);   // 创建加密器实例
     */
    public static function generateKey(string $cipher = 'chacha20-poly1305'): string
    {
        // 统一转换为小写，确保算法名称一致性
        $cipher = strtolower($cipher);

        // 验证加密算法是否支持
        if (!array_key_exists($cipher, self::SUPPORTED_CIPHERS)) {
            throw new InvalidArgumentException(
                '不支持的ChaCha20加密算法: ' . $cipher .
                '。支持的算法有: ' . implode(', ', array_keys(self::SUPPORTED_CIPHERS))
            );
        }

        // ChaCha20固定使用32字节密钥
        return random_bytes(32);
    }

    /**
     * 获取当前实例使用的加密算法信息
     * 返回当前配置的加密算法的详细信息
     *
     * @return array 加密算法详细信息
     *               - 'cipher': string 加密算法名称
     *               - 'key_length': int 密钥长度（位）
     *               - 'key_size': int 密钥大小（字节）
     *               - 'iv_length': int nonce长度（字节）
     *               - 'description': string 算法描述
     *               - 'has_auth': bool 是否提供认证加密
     *
     * 用途：
     * - 调试和日志记录
     * - 配置验证
     * - 系统状态监控
     * - 兼容性检查
     */
    public function getCipherInfo(): array
    {
        $cipherInfo = self::SUPPORTED_CIPHERS[$this->cipher];

        return [
            'cipher' => $this->cipher,           // 完整算法名称
            'key_length' => 256,                 // 密钥长度（位）- ChaCha20固定256位
            'key_size' => 32,                    // 密钥大小（字节）- ChaCha20固定32字节
            'iv_length' => $cipherInfo['iv_len'], // nonce长度（字节）
            'description' => $cipherInfo['description'], // 算法描述
            'has_auth' => true                   // ChaCha20-Poly1305始终提供认证加密
        ];
    }

    /**
     * 获取当前PHP环境支持的所有ChaCha20加密方法
     * 查询系统实际可用的ChaCha20加密算法列表
     *
     * @return array 可用的ChaCha20加密方法列表
     *               - 类型：string[]
     *               - 内容：系统支持的ChaCha20算法名称数组
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

        // 过滤出ChaCha20相关的加密方法
        return array_values(array_filter(
            $availableCiphers,
            fn($cipher) => str_contains(strtolower($cipher), 'chacha')
        ));
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
     * - 返回完整的错误链，便于问题诊断
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
