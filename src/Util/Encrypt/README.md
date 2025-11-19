# Encrypt 常见的加密解密类


## AES 使用示例和测试类
```php
// AES 使用示例和演示类
class AESExample
{
    /**
     * 演示AES加密解密的基本用法
     * 展示AES类的基本功能和使用方法
     */
    public static function demonstrateBasicUsage(): void
    {
        echo "=== AES加密解密基础演示 ===\n";

        try {
            // 1. 生成安全的随机密钥
            $key = AES::generateKey('aes-256-gcm'); // 生成AES-256密钥
            echo "1. ✅ 已生成AES-256安全随机密钥（32字节）\n";

            // 2. 创建AES加密实例（使用GCM模式）
            $aes = new AES($key, 'aes-256-gcm');
            echo "2. ✅ 已创建AES-256-GCM加密实例\n";

            // 3. 准备要加密的数据
            $sensitiveData = '这是需要加密的敏感数据，包含密码、个人信息等。';
            echo "3. 📝 原始数据: " . $sensitiveData . "\n";

            // 4. 执行加密操作（包含附加认证数据）
            $aad = '用户ID:12345;时间戳:' . time(); // 附加认证数据
            $encryptedResult = $aes->encrypt($sensitiveData, $aad);
            echo "4. 🔒 数据加密完成\n";
            echo "   - 密文长度: " . strlen($encryptedResult['ciphertext']) . " 字符\n";
            echo "   - IV长度: " . strlen($encryptedResult['iv']) . " 字符\n";
            echo "   - 标签长度: " . ($encryptedResult['tag'] ? strlen($encryptedResult['tag']) : 0) . " 字符\n";

            // 5. 执行解密操作
            $decryptedData = $aes->decrypt(
                $encryptedResult['ciphertext'],
                $encryptedResult['iv'],
                $encryptedResult['tag'] ?? null,
                $encryptedResult['aad'] ?? null
            );
            echo "5. 🔓 数据解密完成\n";

            // 6. 验证加解密结果
            $verification = $sensitiveData === $decryptedData ? '✅ 成功' : '❌ 失败';
            echo "6. " . $verification . " 加解密验证\n";
            echo "7. 📝 解密结果: " . $decryptedData . "\n";

            // 显示加密算法信息
            $cipherInfo = $aes->getCipherInfo();
            echo "\n🔧 加密算法信息:\n";
            echo "- 算法: " . $cipherInfo['cipher'] . "\n";
            echo "- 密钥长度: " . $cipherInfo['key_length'] . " 位\n";
            echo "- 密钥大小: " . $cipherInfo['key_size'] . " 字节\n";
            echo "- IV长度: " . $cipherInfo['iv_length'] . " 字节\n";
            echo "- 加密模式: " . $cipherInfo['mode'] . "\n";
            echo "- 认证加密: " . ($cipherInfo['has_auth'] ? '是' : '否') . "\n";

        } catch (Exception $e) {
            echo "❌ AES操作失败: " . $e->getMessage() . "\n";
        }
    }

    /**
     * 演示不同加密模式的用法
     * 比较不同AES加密模式的特点和表现
     */
    public static function demonstrateCipherModes(): void
    {
        echo "\n=== 不同加密模式演示 ===\n";

        $testData = '测试加密模式的不同表现和特性';
        $key = AES::generateKey('aes-256-gcm');

        // 测试不同的加密模式
        $modes = [
            'aes-256-gcm' => 'GCM模式（认证加密）',
            'aes-256-cbc' => 'CBC模式（块加密）',
            'aes-256-ctr' => 'CTR模式（流加密）'
        ];

        foreach ($modes as $mode => $description) {
            try {
                $aes = new AES($key, $mode);
                $encrypted = $aes->encrypt($testData);
                $decrypted = $aes->decrypt(
                    $encrypted['ciphertext'],
                    $encrypted['iv'],
                    $encrypted['tag'] ?? null
                );

                $status = $testData === $decrypted ? '✅' : '❌';
                echo "{$status} {$description}: 加解密验证" . ($status === '✅' ? '成功' : '失败') . "\n";

            } catch (Exception $e) {
                echo "❌ {$description}: 失败 - " . $e->getMessage() . "\n";
            }
        }
    }

    /**
     * 演示错误处理和安全验证
     * 展示AES类的错误处理能力和安全特性
     */
    public static function demonstrateErrorHandling(): void
    {
        echo "\n=== 错误处理演示 ===\n";

        try {
            $key = AES::generateKey('aes-256-gcm');
            $aes = new AES($key, 'aes-256-gcm');

            // 正常加密
            $encrypted = $aes->encrypt('测试数据');

            // 测试1：错误的密钥
            try {
                $wrongKey = AES::generateKey('aes-256-gcm'); // 生成不同的密钥
                $wrongAes = new AES($wrongKey, 'aes-256-gcm');
                $wrongAes->decrypt(
                    $encrypted['ciphertext'],
                    $encrypted['iv'],
                    $encrypted['tag']
                );
                echo "❌ 错误密钥测试: 应该失败但通过了\n";
            } catch (Exception $e) {
                echo "✅ 错误密钥测试: 正确捕获错误 - " . $e->getMessage() . "\n";
            }

            // 测试2：GCM模式缺少tag
            try {
                $aes->decrypt($encrypted['ciphertext'], $encrypted['iv']);
                echo "❌ 缺少tag测试: 应该失败但通过了\n";
            } catch (Exception $e) {
                echo "✅ 缺少tag测试: 正确捕获错误 - " . $e->getMessage() . "\n";
            }

            // 测试3：篡改密文
            try {
                $tamperedCiphertext = $encrypted['ciphertext'];
                // 修改密文的一个字节（模拟传输错误或恶意篡改）
                $tamperedCiphertext[10] = chr(ord($tamperedCiphertext[10]) ^ 0x01);
                $aes->decrypt($tamperedCiphertext, $encrypted['iv'], $encrypted['tag']);
                echo "❌ 密文篡改测试: 应该失败但通过了\n";
            } catch (Exception $e) {
                echo "✅ 密文篡改测试: 正确检测到数据篡改\n";
            }

        } catch (Exception $e) {
            echo "❌ 错误处理演示失败: " . $e->getMessage() . "\n";
        }
    }

    /**
     * 演示文件加密功能
     * 展示如何使用AES加密文件数据
     */
    public static function demonstrateFileEncryption(): void
    {
        echo "\n=== 文件加密演示 ===\n";

        try {
            $key = AES::generateKey('aes-256-gcm');
            $aes = new AES($key, 'aes-256-gcm');

            // 模拟文件内容
            $fileContent = "这是文件内容\n包含多行数据\n和特殊字符: !@#$%^&*()";
            echo "1. 📄 原始文件内容:\n" . $fileContent . "\n";

            // 加密文件内容
            $encrypted = $aes->encrypt($fileContent, '文件加密示例');
            echo "2. 🔒 文件加密完成\n";

            // 解密文件内容
            $decrypted = $aes->decrypt(
                $encrypted['ciphertext'],
                $encrypted['iv'],
                $encrypted['tag'],
                $encrypted['aad']
            );
            echo "3. 🔓 文件解密完成\n";

            // 验证
            if ($fileContent === $decrypted) {
                echo "4. ✅ 文件加解密验证成功\n";
                echo "5. 📄 解密后的文件内容:\n" . $decrypted . "\n";
            } else {
                echo "4. ❌ 文件加解密验证失败\n";
            }

        } catch (Exception $e) {
            echo "❌ 文件加密演示失败: " . $e->getMessage() . "\n";
        }
    }
}

// 执行演示（仅在命令行环境下运行）
if (php_sapi_name() === 'cli' && basename($_SERVER['argv'][0]) === basename(__FILE__)) {
    echo "AES加密类库演示 (PHP " . PHP_VERSION . ")\n";
    echo "========================================\n";

    AESExample::demonstrateBasicUsage();
    AESExample::demonstrateCipherModes();
    AESExample::demonstrateErrorHandling();
    AESExample::demonstrateFileEncryption();

    echo "\n========================================\n";
    echo "AES演示完成\n";
}

```

## ChaCha20 使用示例和测试类
```php

// ChaCha20 使用示例和演示类
class ChaCha20Example
{
    /**
     * 演示ChaCha20加密解密的基本用法
     * 展示ChaCha20类的基本功能和使用方法
     */
    public static function demonstrateBasicUsage(): void
    {
        echo "=== ChaCha20加密解密基础演示 ===\n";

        try {
            // 1. 生成安全的随机密钥
            $key = ChaCha20::generateKey('chacha20-poly1305');
            echo "1. ✅ 已生成ChaCha20安全随机密钥（32字节）\n";

            // 2. 创建ChaCha20加密实例（使用标准模式）
            $chacha = new ChaCha20($key, 'chacha20-poly1305');
            echo "2. ✅ 已创建ChaCha20-Poly1305加密实例\n";

            // 3. 准备要加密的实时通信数据
            $message = '这是需要加密的实时视频流数据，要求高性能和低延迟。';
            echo "3. 📝 原始数据: " . $message . "\n";

            // 4. 执行加密操作（包含协议头作为附加认证数据）
            $aad = 'Protocol: WebRTC; Session: ' . bin2hex(random_bytes(8)) . '; Timestamp: ' . time();
            $encryptedResult = $chacha->encrypt($message, $aad);
            echo "4. 🔒 数据加密完成\n";
            echo "   - 密文长度: " . strlen($encryptedResult['ciphertext']) . " 字符\n";
            echo "   - Nonce长度: " . strlen($encryptedResult['iv']) . " 字符\n";
            echo "   - 标签长度: " . strlen($encryptedResult['tag']) . " 字符\n";

            // 5. 执行解密和验证操作
            $decryptedData = $chacha->decrypt(
                $encryptedResult['ciphertext'],
                $encryptedResult['iv'],
                $encryptedResult['tag'],
                $encryptedResult['aad'] ?? null
            );
            echo "5. 🔓 数据解密和验证完成\n";

            // 6. 验证加解密结果
            $verification = $message === $decryptedData ? '✅ 成功' : '❌ 失败';
            echo "6. " . $verification . " 加解密验证\n";
            echo "7. 📝 解密结果: " . $decryptedData . "\n";

            // 显示加密算法信息
            $cipherInfo = $chacha->getCipherInfo();
            echo "\n🔧 加密算法信息:\n";
            echo "- 算法: " . $cipherInfo['cipher'] . "\n";
            echo "- 描述: " . $cipherInfo['description'] . "\n";
            echo "- 密钥长度: " . $cipherInfo['key_length'] . " 位\n";
            echo "- 密钥大小: " . $cipherInfo['key_size'] . " 字节\n";
            echo "- Nonce长度: " . $cipherInfo['iv_length'] . " 字节\n";
            echo "- 认证加密: " . ($cipherInfo['has_auth'] ? '是' : '否') . "\n";

        } catch (Exception $e) {
            echo "❌ ChaCha20操作失败: " . $e->getMessage() . "\n";
        }
    }

    /**
     * 演示性能测试（与AES对比）
     * 展示ChaCha20在性能方面的优势
     */
    public static function demonstratePerformance(): void
    {
        echo "\n=== 性能测试演示 ===\n";

        // 生成测试数据（模拟实时视频流数据）
        $testData = str_repeat('性能测试数据块', 1000);  // 生成约15KB测试数据
        $iterations = 100;  // 测试迭代次数

        // ChaCha20测试
        $chachaKey = ChaCha20::generateKey();
        $chacha = new ChaCha20($chachaKey, 'chacha20-poly1305');

        $startTime = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $encrypted = $chacha->encrypt($testData);
            $chacha->decrypt($encrypted['ciphertext'], $encrypted['iv'], $encrypted['tag']);
        }
        $chachaTime = microtime(true) - $startTime;

        echo "ChaCha20-Poly1305 性能统计:\n";
        echo "  - 总时间: " . number_format($chachaTime, 4) . " 秒\n";
        echo "  - 迭代次数: " . $iterations . " 次\n";
        echo "  - 数据总量: " . number_format(strlen($testData) * $iterations / 1024, 2) . " KB\n";
        echo "  - 平均吞吐量: " . number_format((strlen($testData) * $iterations) / $chachaTime / 1024 / 1024, 2) . " MB/秒\n";
        echo "  - 平均每次操作: " . number_format($chachaTime / $iterations * 1000, 2) . " 毫秒\n";

        // 显示性能优势说明
        echo "\n💡 性能特点说明:\n";
        echo "- ChaCha20在软件实现上通常比AES更快\n";
        echo "- 特别适合移动设备和资源受限环境\n";
        echo "- 对时序攻击有更好的抵抗性\n";
        echo "- 在缺乏AES硬件加速的设备上优势明显\n";
    }

    /**
     * 演示完整性保护特性
     * 展示ChaCha20-Poly1305的完整性验证能力
     */
    public static function demonstrateIntegrityProtection(): void
    {
        echo "\n=== 完整性保护演示 ===\n";

        try {
            $key = ChaCha20::generateKey();
            $chacha = new ChaCha20($key, 'chacha20-poly1305');

            // 正常加密
            $originalData = '重要的实时通信数据包';
            $encrypted = $chacha->encrypt($originalData, '协议版本:1.0');
            echo "1. ✅ 原始数据加密完成: " . $originalData . "\n";

            // 测试1：尝试篡改密文
            echo "2. 🔍 测试篡改检测...\n";
            $tamperedCiphertext = $encrypted['ciphertext'];
            // 修改密文的一个字节（模拟网络传输错误或恶意篡改）
            $tamperedCiphertext[10] = chr(ord($tamperedCiphertext[10]) ^ 0x01);

            try {
                $chacha->decrypt($tamperedCiphertext, $encrypted['iv'], $encrypted['tag'], $encrypted['aad'] ?? null);
                echo "   ❌ 篡改检测测试: 应该失败但通过了\n";
            } catch (Exception $e) {
                echo "   ✅ 篡改检测测试: 成功检测到数据篡改\n";
            }

            // 测试2：尝试使用错误的认证标签
            echo "3. 🔍 测试错误标签检测...\n";
            try {
                $wrongTag = base64_encode(random_bytes(16)); // 生成随机错误标签
                $chacha->decrypt($encrypted['ciphertext'], $encrypted['iv'], $wrongTag, $encrypted['aad'] ?? null);
                echo "   ❌ 错误标签测试: 应该失败但通过了\n";
            } catch (Exception $e) {
                echo "   ✅ 错误标签测试: 成功检测到错误标签\n";
            }

            // 测试3：尝试使用错误的AAD数据
            echo "4. 🔍 测试错误AAD检测...\n";
            try {
                $wrongAad = '错误的协议数据';
                $chacha->decrypt($encrypted['ciphertext'], $encrypted['iv'], $encrypted['tag'], $wrongAad);
                echo "   ❌ 错误AAD测试: 应该失败但通过了\n";
            } catch (Exception $e) {
                echo "   ✅ 错误AAD测试: 成功检测到错误AAD数据\n";
            }

            // 测试4：正常解密验证
            echo "5. 🔍 测试正常解密...\n";
            $decrypted = $chacha->decrypt($encrypted['ciphertext'], $encrypted['iv'], $encrypted['tag'], $encrypted['aad'] ?? null);
            if ($decrypted === $originalData) {
                echo "   ✅ 正常解密测试: 成功恢复原始数据\n";
            } else {
                echo "   ❌ 正常解密测试: 解密数据不匹配\n";
            }

            echo "\n🛡️ 完整性保护总结:\n";
            echo "- Poly1305认证标签确保数据完整性\n";
            echo "- 能够检测篡改、伪造和传输错误\n";
            echo "- 认证失败时不会暴露解密数据\n";
            echo "- 提供端到端的数据真实性保证\n";

        } catch (Exception $e) {
            echo "❌ 完整性保护演示失败: " . $e->getMessage() . "\n";
        }
    }

    /**
     * 演示不同ChaCha20变体的比较
     * 比较标准ChaCha20和XChaCha20的区别
     */
    public static function demonstrateVariantsComparison(): void
    {
        echo "\n=== ChaCha20变体比较 ===\n";

        $testData = '测试不同ChaCha20变体的性能和特性';

        $variants = [
            'chacha20-poly1305' => '标准ChaCha20（12字节nonce）',
            'xchacha20-poly1305' => '扩展XChaCha20（24字节nonce）'
        ];

        foreach ($variants as $variant => $description) {
            try {
                // 检查系统是否支持该变体
                if (!in_array($variant, openssl_get_cipher_methods(), true)) {
                    echo "❌ {$description}: 系统不支持\n";
                    continue;
                }

                $key = ChaCha20::generateKey($variant);
                $chacha = new ChaCha20($key, $variant);

                // 性能测试
                $startTime = microtime(true);
                for ($i = 0; $i < 50; $i++) {
                    $encrypted = $chacha->encrypt($testData);
                    $chacha->decrypt($encrypted['ciphertext'], $encrypted['iv'], $encrypted['tag']);
                }
                $totalTime = microtime(true) - $startTime;

                $cipherInfo = $chacha->getCipherInfo();

                echo "✅ {$description}:\n";
                echo "  - Nonce长度: " . $cipherInfo['iv_length'] . " 字节\n";
                echo "  - 性能: " . number_format($totalTime * 20, 2) . " 毫秒/次\n";
                echo "  - 描述: " . $cipherInfo['description'] . "\n";

            } catch (Exception $e) {
                echo "❌ {$description}: 测试失败 - " . $e->getMessage() . "\n";
            }
        }

        echo "\n📊 变体选择建议:\n";
        echo "- chacha20-poly1305: 兼容性好，广泛支持\n";
        echo "- xchacha20-poly1305: 更大的nonce空间，更好的随机数安全性\n";
    }

    /**
     * 演示实时通信加密场景
     * 模拟WebRTC或VoIP中的实时数据加密
     */
    public static function demonstrateRealTimeCommunication(): void
    {
        echo "\n=== 实时通信加密演示 ===\n";

        try {
            // 模拟通信双方共享密钥（实际中通过密钥交换协议获得）
            $sharedKey = ChaCha20::generateKey();

            // 创建客户端和服务端实例
            $client = new ChaCha20($sharedKey, 'chacha20-poly1305');
            $server = new ChaCha20($sharedKey, 'chacha20-poly1305');

            // 模拟实时数据包序列
            $packets = [
                ['seq' => 1, 'data' => '音频数据包1', 'type' => 'audio'],
                ['seq' => 2, 'data' => '视频数据包1', 'type' => 'video'],
                ['seq' => 3, 'data' => '控制信令包', 'type' => 'control'],
                ['seq' => 4, 'data' => '音频数据包2', 'type' => 'audio'],
            ];

            echo "模拟实时通信数据包加密传输:\n";

            foreach ($packets as $packet) {
                // 客户端加密数据包
                $aad = "SEQ:{$packet['seq']};TYPE:{$packet['type']};TIME:" . microtime(true);
                $encryptedPacket = $client->encrypt($packet['data'], $aad);

                echo "📦 数据包 {$packet['seq']} ({$packet['type']}):\n";
                echo "  - 客户端加密完成\n";

                // 模拟网络传输...

                // 服务端解密数据包
                $decryptedData = $server->decrypt(
                    $encryptedPacket['ciphertext'],
                    $encryptedPacket['iv'],
                    $encryptedPacket['tag'],
                    $encryptedPacket['aad']
                );

                echo "  - 服务端解密完成: " . $decryptedData . "\n";

                // 验证数据完整性
                if ($decryptedData === $packet['data']) {
                    echo "  - ✅ 数据完整性验证成功\n";
                } else {
                    echo "  - ❌ 数据完整性验证失败\n";
                }
            }

            echo "\n🎯 实时通信加密优势:\n";
            echo "- 低延迟：流加密无需填充，处理速度快\n";
            echo "- 高吞吐：适合音频视频流加密\n";
            echo "- 完整性：每个数据包独立认证\n";
            echo "- 前向安全：即使密钥泄露，历史通信仍安全\n";

        } catch (Exception $e) {
            echo "❌ 实时通信演示失败: " . $e->getMessage() . "\n";
        }
    }
}

// 执行演示（仅在命令行环境下运行）
if (php_sapi_name() === 'cli' && basename($_SERVER['argv'][0]) === basename(__FILE__)) {
    echo "ChaCha20加密类库演示 (PHP " . PHP_VERSION . ")\n";
    echo "==============================================\n";

    ChaCha20Example::demonstrateBasicUsage();
    ChaCha20Example::demonstratePerformance();
    ChaCha20Example::demonstrateIntegrityProtection();
    ChaCha20Example::demonstrateVariantsComparison();
    ChaCha20Example::demonstrateRealTimeCommunication();

    echo "\n==============================================\n";
    echo "ChaCha20演示完成\n";
}
```

## RSA 使用示例和测试类
```php

/**
 * RSA 使用示例和演示类
 */
class RSAExample
{
    /**
     * 演示RSA加密解密的基本用法
     */
    public static function demonstrateBasicUsage(): void
    {
        echo "=== RSA加密解密基础演示 ===\n";

        try {
            // 1. 生成RSA密钥对
            echo "1. 🔑 正在生成RSA-2048密钥对...\n";
            $keyPair = RSA::generateKeyPair(2048);
            echo "   ✅ 密钥对生成完成\n";
            echo "   - 私钥长度: " . strlen($keyPair['private_key']) . " 字符\n";
            echo "   - 公钥长度: " . strlen($keyPair['public_key']) . " 字符\n";
            echo "   - 密钥大小: " . $keyPair['key_size'] . " 位\n";
            echo "   - 安全级别: " . $keyPair['security_level'] . "\n";
            echo "   - 公钥指纹: " . substr($keyPair['fingerprint'], 0, 16) . "...\n";

            // 2. 创建RSA加密实例
            $rsa = new RSA(OPENSSL_PKCS1_OAEP_PADDING);
            $rsa->loadFromString($keyPair['private_key'], $keyPair['public_key']);
            echo "2. 🔧 已创建RSA加密实例（OAEP填充）\n";

            // 3. 准备要加密的数据
            $symmetricKey = random_bytes(32);
            echo "3. 📝 原始对称密钥: " . bin2hex($symmetricKey) . "\n";

            // 4. 使用公钥加密对称密钥
            $encryptedKey = $rsa->encrypt($symmetricKey);
            echo "4. 🔒 RSA加密完成\n";
            echo "   - 加密后长度: " . strlen($encryptedKey) . " 字符\n";

            // 5. 使用私钥解密对称密钥
            $decryptedKey = $rsa->decrypt($encryptedKey);
            echo "5. 🔓 RSA解密完成\n";

            // 6. 验证加解密结果
            $verification = $symmetricKey === $decryptedKey ? '✅ 成功' : '❌ 失败';
            echo "6. " . $verification . " 加解密验证\n";
            echo "7. 📝 解密后的密钥: " . bin2hex($decryptedKey) . "\n";

        } catch (Exception $e) {
            echo "❌ RSA操作失败: " . $e->getMessage() . "\n";
        }
    }

    /**
     * 演示数字签名和验证
     */
    public static function demonstrateSigning(): void
    {
        echo "\n=== 数字签名和验证演示 ===\n";

        try {
            // 生成密钥对
            $keyPair = RSA::generateKeyPair(2048);
            $rsa = new RSA();
            $rsa->loadFromString($keyPair['private_key'], $keyPair['public_key']);

            // 准备要签名的文档
            $document = "这是一份重要合同，双方同意以下条款：\n"
                . "1. 甲方支付乙方10000元\n"
                . "2. 乙方在30天内完成工作\n"
                . "3. 双方确认后合同生效\n"
                . "签署时间：" . date('Y-m-d H:i:s');
            echo "1. 📄 原始文档:\n" . $document . "\n";

            // 使用私钥对文档进行签名
            $signature = $rsa->sign($document, 'sha256');
            echo "2. ✍️ 生成数字签名\n";
            echo "   - 签名长度: " . strlen($signature) . " 字符\n";
            echo "   - 签名算法: SHA256\n";

            // 使用公钥验证签名
            $isValid = $rsa->verify($document, $signature, 'sha256');
            echo "3. 🔍 签名验证: " . ($isValid ? '✅ 有效' : '❌ 无效') . "\n";

            // 测试篡改检测
            $tamperedDocument = str_replace('10000元', '50000元', $document);
            $isTamperedValid = $rsa->verify($tamperedDocument, $signature, 'sha256');
            echo "4. 🔍 篡改检测: " . ($isTamperedValid ? '❌ 验证异常' : '✅ 成功检测到篡改') . "\n";

            // 测试错误签名
            $wrongSignature = base64_encode(random_bytes(256));
            $isWrongValid = $rsa->verify($document, $wrongSignature, 'sha256');
            echo "5. 🔍 错误签名检测: " . ($isWrongValid ? '❌ 验证异常' : '✅ 成功检测到错误签名') . "\n";

            // 显示签名详细信息
            $keyInfo = $rsa->getKeyDetails();
            if ($keyInfo && isset($keyInfo['key_size'])) {
                echo "\n🔑 密钥信息:\n";
                echo "- 密钥长度: " . $keyInfo['key_size'] . " 位\n";
                echo "- 包含私钥: " . ($keyInfo['has_private'] ? '是' : '否') . "\n";
                echo "- 包含公钥: " . ($keyInfo['has_public'] ? '是' : '否') . "\n";
            } else {
                echo "\n🔑 密钥信息: 无法获取密钥详情\n";
            }

        } catch (Exception $e) {
            echo "❌ 数字签名演示失败: " . $e->getMessage() . "\n";
        }
    }

    /**
     * 演示不同密钥长度的性能比较
     */
    public static function demonstratePerformance(): void
    {
        echo "\n=== 不同密钥长度性能比较 ===\n";

        $testData = random_bytes(32);
        $iterations = 5;

        $keySizes = [2048, 3072];

        foreach ($keySizes as $keySize) {
            try {
                echo "测试 {$keySize} 位RSA密钥:\n";

                // 生成密钥对
                $startTime = microtime(true);
                $keyPair = RSA::generateKeyPair($keySize);
                $keyGenTime = microtime(true) - $startTime;

                $rsa = new RSA(OPENSSL_PKCS1_OAEP_PADDING);
                $rsa->loadFromString($keyPair['private_key'], $keyPair['public_key']);

                // 加密性能测试
                $startTime = microtime(true);
                for ($i = 0; $i < $iterations; $i++) {
                    $encrypted = $rsa->encrypt($testData);
                }
                $encryptTime = microtime(true) - $startTime;

                // 解密性能测试
                $encryptedData = $rsa->encrypt($testData);
                $startTime = microtime(true);
                for ($i = 0; $i < $iterations; $i++) {
                    $rsa->decrypt($encryptedData);
                }
                $decryptTime = microtime(true) - $startTime;

                // 签名性能测试
                $startTime = microtime(true);
                for ($i = 0; $i < $iterations; $i++) {
                    $signature = $rsa->sign($testData, 'sha256');
                }
                $signTime = microtime(true) - $startTime;

                // 验证性能测试
                $signature = $rsa->sign($testData, 'sha256');
                $startTime = microtime(true);
                for ($i = 0; $i < $iterations; $i++) {
                    $rsa->verify($testData, $signature, 'sha256');
                }
                $verifyTime = microtime(true) - $startTime;

                echo "  - 密钥生成: " . number_format($keyGenTime * 1000, 2) . " 毫秒\n";
                echo "  - 加密: " . number_format($encryptTime / $iterations * 1000, 2) . " 毫秒/次\n";
                echo "  - 解密: " . number_format($decryptTime / $iterations * 1000, 2) . " 毫秒/次\n";
                echo "  - 签名: " . number_format($signTime / $iterations * 1000, 2) . " 毫秒/次\n";
                echo "  - 验证: " . number_format($verifyTime / $iterations * 1000, 2) . " 毫秒/次\n";
                echo "  - 安全级别: " . $keyPair['security_level'] . "\n\n";

            } catch (Exception $e) {
                echo "  - 测试失败: " . $e->getMessage() . "\n\n";
            }
        }

        echo "💡 性能特点说明:\n";
        echo "- 密钥生成: 最耗时，应在系统初始化时完成\n";
        echo "- 加密/解密: 较慢，适合小数据量操作\n";
        echo "- 签名/验证: 相对较快，适合频繁使用\n";
        echo "- 密钥长度: 越长越安全，但性能下降明显\n";
    }

    /**
     * 演示混合加密系统（RSA + AES）
     */
    public static function demonstrateHybridEncryption(): void
    {
        echo "\n=== 混合加密系统演示 ===\n";

        try {
            // 1. 生成RSA密钥对
            $rsaKeyPair = RSA::generateKeyPair(2048);
            $rsa = new RSA();
            $rsa->loadFromString($rsaKeyPair['private_key'], $rsaKeyPair['public_key']);
            echo "1. ✅ RSA密钥对生成完成（2048位）\n";

            // 2. 生成AES对称密钥
            $aesKey = random_bytes(32);
            echo "2. ✅ 生成AES-256对称密钥: " . substr(bin2hex($aesKey), 0, 16) . "...\n";

            // 3. 使用RSA公钥加密AES密钥
            $encryptedAesKey = $rsa->encrypt($aesKey);
            echo "3. 🔒 使用RSA加密AES密钥\n";
            echo "   - 加密后的AES密钥长度: " . strlen($encryptedAesKey) . " 字符\n";


            // 5. 解密过程：先解密AES密钥，再解密数据
            $decryptedAesKey = $rsa->decrypt($encryptedAesKey);
            echo "6. 🔓 使用RSA解密AES密钥\n";
            echo "   - 解密后的AES密钥: " . substr(bin2hex($decryptedAesKey), 0, 16) . "...\n";

        } catch (Exception $e) {
            echo "❌ 混合加密演示失败: " . $e->getMessage() . "\n";
        }
    }

    /**
     * 演示API请求签名验证
     */
    public static function demonstrateAPISigning(): void
    {
        echo "\n=== API请求签名验证演示 ===\n";

        try {
            // 模拟服务端生成密钥对
            $serverKeyPair = RSA::generateKeyPair(2048);
            $serverRSA = new RSA();
            $serverRSA->loadFromString($serverKeyPair['private_key'], $serverKeyPair['public_key']);

            // 模拟客户端只拥有服务端公钥
            $clientRSA = new RSA();
            $clientRSA->loadFromString(null, $serverKeyPair['public_key']);

            // 模拟API请求数据
            $apiRequest = [
                'method' => 'POST',
                'path' => '/api/v1/users',
                'timestamp' => time(),
                'nonce' => bin2hex(random_bytes(16)),
                'data' => [
                    'name' => '张三',
                    'email' => 'zhangsan@example.com',
                    'role' => 'user'
                ]
            ];

            $requestJson = json_encode($apiRequest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            echo "1. 📨 API请求数据:\n" . json_encode($apiRequest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

            // 客户端：生成请求签名
            $signature = $serverRSA->sign($requestJson, 'sha256');
            echo "2. ✍️ 生成请求签名\n";
            echo "   - 签名: " . substr($signature, 0, 32) . "...\n";

            // 模拟请求传输...
            echo "3. 📡 模拟请求传输...\n";

            // 服务端：验证请求签名
            $isValid = $serverRSA->verify($requestJson, $signature, 'sha256');
            echo "4. 🔍 服务端验证签名: " . ($isValid ? '✅ 有效' : '❌ 无效') . "\n";

            // 测试篡改请求
            $tamperedRequest = $apiRequest;
            $tamperedRequest['data']['role'] = 'admin';
            $tamperedJson = json_encode($tamperedRequest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $isTamperedValid = $serverRSA->verify($tamperedJson, $signature, 'sha256');
            echo "5. 🔍 篡改请求验证: " . ($isTamperedValid ? '❌ 验证异常' : '✅ 成功检测到篡改') . "\n";

            echo "\n🛡️ API签名安全机制:\n";
            echo "- 🔐 身份认证: 确保请求来自合法客户端\n";
            echo "- 📝 数据完整性: 防止请求数据被篡改\n";
            echo "- ⏰ 防重放: 结合timestamp和nonce防止重放攻击\n";

        } catch (Exception $e) {
            echo "❌ API签名演示失败: " . $e->getMessage() . "\n";
        }
    }
}

// 执行演示
if (php_sapi_name() === 'cli' && isset($_SERVER['argv'][0]) && basename($_SERVER['argv'][0]) === basename(__FILE__)) {
    echo "RSA加密类库演示 (PHP " . PHP_VERSION . ")\n";
    echo "========================================\n";

    RSAExample::demonstrateBasicUsage();
    RSAExample::demonstrateSigning();
    RSAExample::demonstratePerformance();
    RSAExample::demonstrateHybridEncryption();
    RSAExample::demonstrateAPISigning();

    echo "\n========================================\n";
    echo "RSA演示完成\n";
}
```

## ECC 使用示例和测试类
```php

/* ========================= 使用示例（可复制运行） =========================
   下面示例演示：生成密钥、导出 raw point、压缩/解压、加密/解密、签名/验签、JWT、文件保存、轮换等。
   注：建议在 CLI 中运行示例。
*/

function example_all_features(): void
{
    echo "=== ECC 演示 ===\n";

    // 1) 生成密钥对
    $kp = ECC::generateKeyPair('prime256v1', null);
    $privPem = $kp['private_pem'];
    $pubPem = $kp['public_pem'];
    echo "生成私钥 PEM:\n" . substr($privPem, 0, 80) . "...\n";
    echo "生成公钥 PEM:\n" . substr($pubPem, 0, 80) . "...\n";

    // 2) 加载公/私钥对象
    $priv = ECC::loadPrivateKey($privPem, false, null);
    $pub  = ECC::loadPublicKey($pubPem, false);

    // 3) 导出 raw 未压缩点与压缩点
    $rawUnc = ECC::publicKeyToRawPoint($pub, false);
    $rawC   = ECC::publicKeyToRawPoint($pub, true);
    echo "raw uncompressed len: " . strlen($rawUnc) . "\n";
    echo "raw compressed len: " . strlen($rawC) . "\n";

    // 4) 将 raw point 转回 PEM（演示）
    $pemFromRaw = ECC::rawPointToPublicPem($rawUnc, 'prime256v1');
    echo "从 raw 重建 PEM片段: " . substr($pemFromRaw, 0, 60) . "...\n";

    // 5) 验证私钥与公钥是否匹配
    $matches = ECC::keyPairMatches($priv, $pub) ? '匹配' : '不匹配';
    echo "公私钥配对检测： {$matches}\n";

    // 6) ECIES 加密/解密示例
    $msg = "测试消息 - 时间: " . date('c');
    $pkg = ECC::eciesEncrypt($msg, $pub, [
        'hkdf_hash' => 'sha256',
        'hkdf_salt' => random_bytes(16),
        'hkdf_info' => 'demo',
        'sym_cipher' => 'aes-256-gcm',
        'ephemeral_pub_format' => 'pem',
        'output' => 'json',
        'include_salt' => true,
        'aad' => 'app:demo'
    ]);
    echo "ECIES 包: " . substr($pkg, 0, 120) . "...\n";
    $plain = ECC::eciesDecrypt($pkg, $priv, ['input'=>'json']);
    echo "解密结果: {$plain}\n";

    // 7) ECDSA 签名/验签
    $sigDer = ECC::ecdsaSign($priv, $msg, 'sha256', 'der');
    $ok = ECC::ecdsaVerify($pub, $msg, $sigDer, 'sha256', 'der') ? '通过' : '失败';
    echo "ECDSA 验签: {$ok}\n";

    // 8) JWT ES256 签名示例
    $jwt = ECC::jwtSign($priv, ['sub'=>'user123', 'iat'=>time()], 'ES256');
    echo "JWT: " . $jwt . "\n";
    $v = ECC::jwtVerify($jwt, $pub) ? 'JWT 验证通过' : 'JWT 验证失败';
    echo $v . "\n";

    // 9) 保存公私钥到文件
    ECC::savePemToFile(__DIR__.'/et_priv.pem', $privPem);
    ECC::savePemToFile(__DIR__.'/et_pub.pem', $pubPem);
    echo "已保存 pem 到当前目录\n";

    // 10) 密钥轮换示例（将备份旧秘钥并写入新秘钥）
    $rot = ECC::rotateKeys(__DIR__ . '/keystore_demo', 'prime256v1', null);
    echo "轮换并保存新密钥到 keystore_demo\n";
}

// 如果想运行示例，取消下一行注释
 example_all_features();


```

