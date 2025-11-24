# Crypto 常见的加密解密类

测试文件
```php


/**
 * 加密类全面测试
 */
class CryptoTest
{
    private array $testResults = [];
    private string $testDataDir;

    public function __construct()
    {
        $this->testDataDir = __DIR__ . '/test_data';
        $this->createTestDirectory();
    }

    /**
     * 创建测试目录
     */
    private function createTestDirectory(): void
    {
        if (!is_dir($this->testDataDir)) {
            mkdir($this->testDataDir, 0755, true);
        }
    }

    /**
     * 记录测试结果
     */
    private function logResult(string $testName, bool $passed, string $message = ''): void
    {
        $this->testResults[] = [
            'test' => $testName,
            'passed' => $passed,
            'message' => $message
        ];

        $status = $passed ? '✓ 通过' : '✗ 失败';
        echo "{$status}: {$testName}\n<br/>";
        if (!empty($message)) {
            echo "   信息: {$message}\n<br/>";
        }
        echo "\n<br/>";
    }

    /**
     * 运行所有测试
     */
    public function runAllTests(): void
    {
        echo "开始加密类全面测试...\n<br/>";
        echo "================================\n<br/>\n<br/>";

        $this->testECC();
        $this->testAES();
        $this->testRSA();

        $this->printSummary();
    }

    /**
     * ECC 测试
     */
    private function testECC(): void
    {
        echo "ECC 椭圆曲线加密测试\n<br/>";
        echo "--------------------\n<br/>";

        try {
            // 测试1: 密钥对生成
            $ecc = new ECCCrypto(null, null, 'prime256v1');
            $this->logResult('ECC 密钥对生成', true, '曲线: prime256v1');

            // 测试2: 密钥导出
            $privateKey = $ecc->exportPrivateKey();
            $publicKey = $ecc->exportPublicKey();
            $this->logResult('ECC 密钥导出', !empty($privateKey) && !empty($publicKey));

            // 测试3: 从现有密钥创建实例
            $ecc2 = ECCCrypto::createFromKey($privateKey, $publicKey);
            $this->logResult('ECC 从密钥创建实例', $ecc2->hasPrivateKey() && $ecc2->hasPublicKey());

            // 测试4: 签名和验证
            $testData = "ECC测试数据 " . date('Y-m-d H:i:s');
            $signature = $ecc->sign($testData);
            $verifyResult = $ecc->verify($testData, $signature);
            $this->logResult('ECC 签名验证', $verifyResult);

            // 测试5: 带时间戳的签名
            $timestampSignature = $ecc->signWithTimestamp($testData);
            $timestampVerify = $ecc->verifyWithTimestamp(
                $testData,
                $timestampSignature['signature'],
                $timestampSignature['timestamp']
            );
            $this->logResult('ECC 带时间戳签名', $timestampVerify);

            // 测试6: 文件签名
            $testFile = $this->testDataDir . '/ecc_test.txt';
            file_put_contents($testFile, $testData);
            $fileSignature = $ecc->signFile($testFile);
            $fileVerify = $ecc->verifyFile($testFile, $fileSignature);
            $this->logResult('ECC 文件签名', $fileVerify);

            // 测试7: 共享密钥计算
            $ecc3 = new ECCCrypto(null, null, 'prime256v1');
            $sharedSecret1 = $ecc->computeSharedSecret($ecc3->exportPublicKey());
            $sharedSecret2 = $ecc3->computeSharedSecret($ecc->exportPublicKey());
            $this->logResult('ECC 共享密钥', $sharedSecret1 === $sharedSecret2);

            // 测试8: 密钥对验证
            $keyPairValid = $ecc->verifyKeyPair();
            $this->logResult('ECC 密钥对验证', $keyPairValid);

            // 测试9: 密钥指纹
            $fingerprint = $ecc->getKeyFingerprint();
            $this->logResult('ECC 密钥指纹', !empty($fingerprint) && strlen($fingerprint) === 64);

            // 测试10: 加密信息
            $cipherInfo = $ecc->getCipherInfo();
            $this->logResult('ECC 加密信息', !empty($cipherInfo));

            // 测试11: 静态方法创建密钥对
            $keyPair = ECCCrypto::createKeyPair('secp384r1', 'test_password');
            $this->logResult('ECC 静态密钥对生成', !empty($keyPair['private_key']) && !empty($keyPair['public_key']));

            // 测试12: 不同曲线测试
            $curves = ['prime256v1', 'secp384r1', 'secp521r1'];
            foreach ($curves as $curve) {
                try {
                    $curveEcc = new ECCCrypto(null, null, $curve);
                    $curveData = "曲线测试 {$curve}";
                    $curveSig = $curveEcc->sign($curveData);
                    $curveVerify = $curveEcc->verify($curveData, $curveSig);
                    $this->logResult("ECC 曲线 {$curve} 测试", $curveVerify);
                } catch (Exception $e) {
                    $this->logResult("ECC 曲线 {$curve} 测试", false, $e->getMessage());
                }
            }

            // 测试13: 不同签名算法测试
            $algorithms = ['sha256', 'sha384', 'sha512'];
            foreach ($algorithms as $algorithm) {
                try {
                    $algData = "算法测试 {$algorithm}";
                    $algSig = $ecc->sign($algData, $algorithm);
                    $algVerify = $ecc->verify($algData, $algSig, $algorithm);
                    $this->logResult("ECC 算法 {$algorithm} 测试", $algVerify);
                } catch (Exception $e) {
                    $this->logResult("ECC 算法 {$algorithm} 测试", false, $e->getMessage());
                }
            }

            // 测试14: 异常情况测试
            try {
                $invalidEcc = new ECCCrypto(null, null, 'invalid_curve');
                $this->logResult('ECC 无效曲线测试', false, '应该抛出异常');
            } catch (Exception $e) {
                $this->logResult('ECC 无效曲线测试', true, '正确抛出异常: ' . $e->getMessage());
            }

        } catch (Exception $e) {
            $this->logResult('ECC 基础测试', false, '异常: ' . $e->getMessage());
        }

        echo "\n<br/>";
    }

    /**
     * AES 测试
     */
    private function testAES(): void
    {
        echo "AES 对称加密测试\n<br/>";
        echo "----------------\n<br/>";

        try {
            // 测试1: 不同模式测试
            $modes = ['aes-256-cbc', 'aes-256-gcm', 'aes-256-ctr'];
            
            foreach ($modes as $mode) {
                try {
                    $key = AESCrypto::generateKey($mode);
                    $aes = new AESCrypto($key, $mode);
                    
                    // 测试数据
                    $testData = "AES测试数据 {$mode} " . date('Y-m-d H:i:s');
                    
                    // 加密解密测试
                    $encrypted = $aes->encrypt($testData);
                    $decrypted = $aes->decrypt($encrypted);
                    $encryptResult = $testData === $decrypted;
                    
                    $this->logResult("AES 模式 {$mode} 加密解密", $encryptResult);
                    
                    // 测试2: 文件加密解密
                    $testFile = $this->testDataDir . "/aes_test_{$mode}.txt";
                    $encryptedFile = $this->testDataDir . "/aes_test_{$mode}.enc";
                    $decryptedFile = $this->testDataDir . "/aes_test_{$mode}.dec";
                    
                    file_put_contents($testFile, $testData);
                    
                    $fileEncrypt = $aes->encryptFile($testFile, $encryptedFile);
                    $fileDecrypt = $aes->decryptFile($encryptedFile, $decryptedFile);
                    
                    $fileResult = $fileEncrypt && $fileDecrypt && file_get_contents($testFile) === file_get_contents($decryptedFile);
                    $this->logResult("AES 模式 {$mode} 文件加密", $fileResult);
                    
                } catch (Exception $e) {
                    $this->logResult("AES 模式 {$mode} 测试", false, $e->getMessage());
                }
            }

            // 测试3: HMAC 签名
            $aes = new AESCrypto(AESCrypto::generateKey(), 'aes-256-cbc');
            $hmacData = "HMAC测试数据";
            $hmacSignature = $aes->calculateHMAC($hmacData);
            $hmacVerify = $aes->verifyHMAC($hmacData, $hmacSignature);
            $this->logResult('AES HMAC 签名验证', $hmacVerify);

            // 测试4: 加密并签名
            $encryptSignData = "加密签名测试数据";
            $encryptedPackage = $aes->encryptAndSign($encryptSignData);
            $decryptedData = $aes->decryptAndVerify($encryptedPackage);
            $this->logResult('AES 加密并签名', $encryptSignData === $decryptedData);

            // 测试5: 密码派生密钥
            $password = 'test_password';
            $salt = random_bytes(16);
            $derivedKey = AESCrypto::generateKeyFromPassword($password, $salt, 'aes-256-cbc');
            $this->logResult('AES 密码派生密钥', !empty($derivedKey) && strlen($derivedKey) === 32);

            // 测试6: 加密信息
            $cipherInfo = $aes->getCipherInfo();
            $this->logResult('AES 加密信息', !empty($cipherInfo));

            // 测试7: 大文件测试
            // $largeFile = $this->testDataDir . '/aes_large_test.dat';
            // $largeEncrypted = $this->testDataDir . '/aes_large_test.enc';
            // $largeDecrypted = $this->testDataDir . '/aes_large_test.dec';
            
            // // 生成1MB测试文件
            // $largeData = random_bytes(1024 * 1024);
            // file_put_contents($largeFile, $largeData);
            
            // $largeResult = $aes->encryptFile($largeFile, $largeEncrypted, 8192) &&
            //               $aes->decryptFile($largeEncrypted, $largeDecrypted, 8192) &&
            //               file_get_contents($largeFile) === file_get_contents($largeDecrypted);
            
            // $this->logResult('AES 大文件加密', $largeResult);

            // 测试8: 异常情况测试
            try {
                $invalidAes = new AESCrypto('short_key', 'aes-256-cbc');
                $this->logResult('AES 无效密钥测试', false, '应该抛出异常');
            } catch (Exception $e) {
                $this->logResult('AES 无效密钥测试', true, '正确抛出异常: ' . $e->getMessage());
            }

        } catch (Exception $e) {
            $this->logResult('AES 基础测试', false, '异常: ' . $e->getMessage());
        }

        echo "\n<br/>";
    }

    /**
     * RSA 测试
     */
    private function testRSA(): void
    {
        echo "RSA 非对称加密测试\n<br/>";
        echo "------------------\n<br/>";

        try {
            // 测试1: 不同密钥长度测试
            $keySizes = [2048, 3072, 4096];
            
            foreach ($keySizes as $keySize) {
                try {
                    $rsa = new RSACrypto(null, null, $keySize);
                    $this->logResult("RSA {$keySize}位密钥生成", true);
                    
                    // 测试2: 加密解密
                    $testData = "RSA测试数据 {$keySize}位 " . date('Y-m-d H:i:s');
                    
                    // 测试不同填充方式
                    $paddings = [OPENSSL_PKCS1_PADDING, OPENSSL_PKCS1_OAEP_PADDING];
                    
                    foreach ($paddings as $padding) {
                        try {
                            $encrypted = $rsa->encrypt($testData, $padding);
                            $decrypted = $rsa->decrypt($encrypted, $padding);
                            $paddingName = $padding === OPENSSL_PKCS1_PADDING ? 'PKCS1' : 'OAEP';
                            $this->logResult("RSA {$keySize}位 {$paddingName}填充", $testData === $decrypted);
                        } catch (Exception $e) {
                            $this->logResult("RSA {$keySize}位 {$paddingName}填充", false, $e->getMessage());
                        }
                    }
                    
                    // 测试3: 长数据分块加密
                    $longData = str_repeat("RSA长数据测试 ", 100);
                    $encryptedLong = $rsa->encrypt($longData);
                    $decryptedLong = $rsa->decrypt($encryptedLong);
                    $this->logResult("RSA {$keySize}位 长数据加密", $longData === $decryptedLong);
                    
                } catch (Exception $e) {
                    $this->logResult("RSA {$keySize}位测试", false, $e->getMessage());
                }
            }

            // 使用2048位密钥进行后续测试
            $rsa = new RSACrypto(null, null, 2048);
            $rsa2 = new RSACrypto(null, null, 2048);

            // 测试4: 签名验证
            $signData = "RSA签名测试数据";
            $signature = $rsa->sign($signData);
            $verifyResult = $rsa->verify($signData, $signature);
            $this->logResult('RSA 签名验证', $verifyResult);

            // 测试5: 文件签名
            $testFile = $this->testDataDir . '/rsa_test.txt';
            file_put_contents($testFile, $signData);
            $fileSignature = $rsa->signFile($testFile);
            $fileVerify = $rsa->verifyFile($testFile, $fileSignature);
            $this->logResult('RSA 文件签名', $fileVerify);

            // 测试6: 文件加密解密
            // $rsaFile = $this->testDataDir . '/rsa_file_test.txt';
            // $rsaEncrypted = $this->testDataDir . '/rsa_file_test.enc';
            // $rsaDecrypted = $this->testDataDir . '/rsa_file_test.dec';
            
            // file_put_contents($rsaFile, "RSA文件加密测试数据");
            
            // $fileEncrypt = $rsa2->encryptFile($rsaFile, $rsaEncrypted);
            // $fileDecrypt = $rsa2->decryptFile($rsaEncrypted, $rsaDecrypted);
            // $fileResult = $fileEncrypt && $fileDecrypt && file_get_contents($rsaFile) === file_get_contents($rsaDecrypted);
            // $this->logResult('RSA 文件加密', $fileResult);

            // 测试7: 加密并签名
            $secureData = "安全数据传输测试";
            $encryptedPackage = $rsa->encryptAndSign($secureData, $rsa2);
            $decryptedSecureData = $rsa2->decryptAndVerify($encryptedPackage, $rsa);
            $this->logResult('RSA 加密并签名', $secureData === $decryptedSecureData);

            // 测试8: 密钥导出导入
            // $privateKey = $rsa->exportPrivateKey('test_password');
            // $publicKey = $rsa->exportPublicKey();
            
            // $rsaFromKey = RSACrypto::createFromKey($privateKey, $publicKey, 'sha256');
            // $keyImportResult = $rsaFromKey->verifyKeyPair();
            // $this->logResult('RSA 密钥导出导入', $keyImportResult);

            // 测试9: 密钥指纹
            $fingerprint = $rsa->getKeyFingerprint();
            $this->logResult('RSA 密钥指纹', !empty($fingerprint));

            // 测试10: 加密信息
            $cipherInfo = $rsa->getCipherInfo();
            $this->logResult('RSA 加密信息', !empty($cipherInfo));

            // 测试11: 静态方法创建密钥对
            $keyPair = RSACrypto::createKeyPair(2048, 'test_password');
            $this->logResult('RSA 静态密钥对生成', !empty($keyPair['private_key']) && !empty($keyPair['public_key']));

            // 测试12: 不同哈希算法测试
            $hashAlgorithms = ['sha256', 'sha384', 'sha512'];
            foreach ($hashAlgorithms as $algorithm) {
                try {
                    $hashRsa = new RSACrypto(null, null, 2048, $algorithm);
                    $hashData = "哈希算法测试 {$algorithm}";
                    $hashSig = $hashRsa->sign($hashData);
                    $hashVerify = $hashRsa->verify($hashData, $hashSig);
                    $this->logResult("RSA 哈希算法 {$algorithm}", $hashVerify);
                } catch (Exception $e) {
                    $this->logResult("RSA 哈希算法 {$algorithm}", false, $e->getMessage());
                }
            }

            // 测试13: 异常情况测试
            try {
                $invalidRsa = new RSACrypto(null, null, 1024);
                $this->logResult('RSA 无效密钥长度测试', false, '应该抛出异常');
            } catch (Exception $e) {
                $this->logResult('RSA 无效密钥长度测试', true, '正确抛出异常: ' . $e->getMessage());
            }

        } catch (Exception $e) {
            $this->logResult('RSA 基础测试', false, '异常: ' . $e->getMessage());
        }

        echo "\n<br/>";
    }

    /**
     * 打印测试摘要
     */
    private function printSummary(): void
    {
        echo "================================\n<br/>";
        echo "测试完成摘要\n<br/>";
        echo "================================\n<br/>";

        $passed = 0;
        $failed = 0;

        foreach ($this->testResults as $result) {
            if ($result['passed']) {
                $passed++;
            } else {
                $failed++;
            }
        }

        $total = $passed + $failed;
        $successRate = $total > 0 ? round(($passed / $total) * 100, 2) : 0;

        echo "总测试数: {$total}\n<br/>";
        echo "通过: {$passed}\n<br/>";
        echo "失败: {$failed}\n<br/>";
        echo "成功率: {$successRate}%\n<br/>\n<br/>";

        // 显示失败的测试
        if ($failed > 0) {
            echo "失败的测试:\n<br/>";
            foreach ($this->testResults as $result) {
                if (!$result['passed']) {
                    echo "  - {$result['test']}: {$result['message']}\n<br/>";
                }
            }
        }

        // 清理测试文件
        $this->cleanupTestFiles();
    }

    /**
     * 清理测试文件
     */
    private function cleanupTestFiles(): void
    {
        if (is_dir($this->testDataDir)) {
            $files = glob($this->testDataDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->testDataDir);
        }
    }
}

// 运行测试
try {
    $test = new CryptoTest();
    $test->runAllTests();
} catch (Exception $e) {
    echo "测试运行错误: " . $e->getMessage() . "\n<br/>";
    echo "堆栈跟踪: " . $e->getTraceAsString() . "\n<br/>";
}

echo "=== 所有测试执行完毕 ===\n<br/>";


```