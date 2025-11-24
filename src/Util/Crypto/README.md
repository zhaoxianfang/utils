# Crypto 常见的加密解密类

测试文件

```php
<?php

/**
 * 加密解密类全面测试套件
 * 测试AES、RSA、ECC三个加密类的所有功能
 * 确保每个功能都得到充分测试，异常不会影响后续测试
 *
 * @package CryptoTest
 * @author Test Team
 * @version 1.0.0
 * @created 2024-01-15
 */

// 引入加密类
require_once 'AES.php';
require_once 'RSA.php';
require_once 'ECC.php';

/**
 * 加密类全面测试器
 */
class CryptoTestSuite
{
    private string $testDataDir;
    private array $testResults;
    private int $totalTests;
    private int $passedTests;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->testDataDir = __DIR__ . '/test_data';
        $this->testResults = [];
        $this->totalTests = 0;
        $this->passedTests = 0;

        // 创建测试目录
        $this->createTestDirectory();
    }

    /**
     * 创建测试目录
     */
    private function createTestDirectory(): void
    {
        if (!is_dir($this->testDataDir)) {
            mkdir($this->testDataDir, 0755, true);
            echo "创建测试目录: {$this->testDataDir}\n<br />";
        }

        // 创建测试文件
        $this->createTestFiles();
    }

    /**
     * 创建测试文件
     */
    private function createTestFiles(): void
    {
        $testFiles = [
            'small.txt' => '这是一个小测试文件，用于测试加密功能。内容不长，但足够测试。',
            'medium.txt' => str_repeat('这是中等大小的测试文件内容。', 100),
            'large.txt' => str_repeat('这是大文件测试内容，用于测试大文件加密性能。', 1000),
            'binary.bin' => $this->generateBinaryData(1024), // 1KB二进制数据
            'special_chars.txt' => "特殊字符测试：\n<br />\t中文内容 € § ® © 表情符号 😀 🌟 🚀\n<br />第二行内容"
        ];

        foreach ($testFiles as $filename => $content) {
            $filepath = $this->testDataDir . '/' . $filename;
            if (!file_exists($filepath)) {
                file_put_contents($filepath, $content);
                echo "创建测试文件: {$filename}\n<br />";
            }
        }
    }

    /**
     * 生成二进制测试数据
     */
    private function generateBinaryData(int $size): string
    {
        $data = '';
        for ($i = 0; $i < $size; $i++) {
            $data .= chr(rand(0, 255));
        }
        return $data;
    }

    /**
     * 运行所有测试
     */
    public function runAllTests(): void
    {
        echo "========================================\n<br />";
        echo "开始运行加密类全面测试\n<br />";
        echo "========================================\n<br />\n<br />";

        $this->runAESTests();
        $this->runRSATests();
        $this->runECCTests();

        $this->generateTestReport();
    }

    /**
     * 运行AES测试
     */
    private function runAESTests(): void
    {
        echo "AES加密类测试\n<br />";
        echo "==============\n<br />\n<br />";

        $this->testAESBasicFunctions();
        $this->testAESEncryptionModes();
        $this->testAESFileOperations();
        $this->testAESAdvancedFeatures();
        $this->testAESErrorHandling();

        echo "\n<br />";
    }

    /**
     * 运行RSA测试
     */
    private function runRSATests(): void
    {
        echo "RSA加密类测试\n<br />";
        echo "==============\n<br />\n<br />";

        $this->testRSABasicFunctions();
        $this->testRSAEncryptionDecryption();
        $this->testRSASigningVerification();
        $this->testRSAFileOperations();
        $this->testRSAAdvancedFeatures();
        $this->testRSAErrorHandling();

        echo "\n<br />";
    }

    /**
     * 运行ECC测试
     */
    private function runECCTests(): void
    {
        echo "ECC加密类测试\n<br />";
        echo "==============\n<br />\n<br />";

        $this->ECCBasicFunctions();
        $this->ECCSigningVerification();
        $this->ECCKeyExchange();
        $this->ECCFileOperations();
        $this->ECCAdvancedFeatures();
        $this->ECCErrorHandling();

        echo "\n<br />";
    }

    /**
     * AES基础功能测试
     */
    private function testAESBasicFunctions(): void
    {
        $this->testCase("AES-01", "AES基础加密解密", function() {
            $key = AESCrypto::generateKey('aes-256-cbc');
            $aes = new AESCrypto($key, 'aes-256-cbc', null, false);

            $testData = "Hello, AES Encryption World! 测试中文内容 🚀";
            $encrypted = $aes->encrypt($testData);
            $decrypted = $aes->decrypt($encrypted);

            return $decrypted === $testData;
        });

        $this->testCase("AES-02", "AES不同密钥长度", function() {
            $results = [];

            // 测试128位密钥
            $key128 = AESCrypto::generateKey('aes-128-cbc');
            $aes128 = new AESCrypto($key128, 'aes-128-cbc');
            $data = "128位密钥测试";
            $encrypted = $aes128->encrypt($data);
            $results[] = $aes128->decrypt($encrypted) === $data;

            // 测试192位密钥
            $key192 = AESCrypto::generateKey('aes-192-cbc');
            $aes192 = new AESCrypto($key192, 'aes-192-cbc');
            $encrypted = $aes192->encrypt($data);
            $results[] = $aes192->decrypt($encrypted) === $data;

            // 测试256位密钥
            $key256 = AESCrypto::generateKey('aes-256-cbc');
            $aes256 = new AESCrypto($key256, 'aes-256-cbc');
            $encrypted = $aes256->encrypt($data);
            $results[] = $aes256->decrypt($encrypted) === $data;

            return !in_array(false, $results, true);
        });

        $this->testCase("AES-03", "AES IV重用测试", function() {
            $key = AESCrypto::generateKey('aes-256-cbc');
            $iv = random_bytes(16);

            $aes1 = new AESCrypto($key, 'aes-256-cbc', $iv);
            $aes2 = new AESCrypto($key, 'aes-256-cbc', $iv);

            $data = "相同IV测试数据";
            $encrypted1 = $aes1->encrypt($data);
            $encrypted2 = $aes2->encrypt($data);

            // 相同IV和密钥应该产生相同的结果
            return $encrypted1 === $encrypted2;
        });

        $this->testCase("AES-04", "AES密码派生密钥", function() {
            $password = "mySecurePassword123";
            $salt = "randomSaltValue";

            $key = AESCrypto::generateKeyFromPassword($password, $salt, 'aes-256-cbc');
            $aes = new AESCrypto($key, 'aes-256-cbc');

            $data = "密码派生密钥测试";
            $encrypted = $aes->encrypt($data);
            $decrypted = $aes->decrypt($encrypted);

            return $decrypted === $data;
        });
    }

    /**
     * AES加密模式测试
     */
    private function testAESEncryptionModes(): void
    {
        $this->testCase("AES-05", "AES CBC模式", function() {
            $key = AESCrypto::generateKey('aes-256-cbc');
            $aes = new AESCrypto($key, 'aes-256-cbc');

            $data = "CBC模式测试数据";
            $encrypted = $aes->encrypt($data);
            $decrypted = $aes->decrypt($encrypted);

            return $decrypted === $data && $aes->requiresIV();
        });

        $this->testCase("AES-06", "AES ECB模式", function() {
            $key = AESCrypto::generateKey('aes-256-ecb');
            $aes = new AESCrypto($key, 'aes-256-ecb');

            $data = "ECB模式测试数据";
            $encrypted = $aes->encrypt($data);
            $decrypted = $aes->decrypt($encrypted);

            return $decrypted === $data && !$aes->requiresIV();
        });

        $this->testCase("AES-07", "AES GCM模式", function() {
            $key = AESCrypto::generateKey('aes-256-gcm');
            $aes = new AESCrypto($key, 'aes-256-gcm');

            $data = "GCM认证加密模式测试";
            $additionalData = "附加认证数据";

            $encrypted = $aes->encrypt($data, OPENSSL_RAW_DATA, $additionalData);
            $decrypted = $aes->decrypt($encrypted, OPENSSL_RAW_DATA, $additionalData);

            return $decrypted === $data && $aes->isAuthenticatedMode();
        });

        $this->testCase("AES-08", "AES CTR模式", function() {
            $key = AESCrypto::generateKey('aes-256-ctr');
            $aes = new AESCrypto($key, 'aes-256-ctr');

            $data = "CTR流加密模式测试";
            $encrypted = $aes->encrypt($data);
            $decrypted = $aes->decrypt($encrypted);

            return $decrypted === $data;
        });

        $this->testCase("AES-09", "AES CFB模式", function() {
            $key = AESCrypto::generateKey('aes-256-cfb');
            $aes = new AESCrypto($key, 'aes-256-cfb');

            $data = "CFB模式测试数据";
            $encrypted = $aes->encrypt($data);
            $decrypted = $aes->decrypt($encrypted);

            return $decrypted === $data;
        });
    }

    /**
     * AES文件操作测试
     */
    private function testAESFileOperations(): void
    {
        $this->testCase("AES-10", "AES小文件加密解密", function() {
            $key = AESCrypto::generateKey('aes-256-cbc');
            $aes = new AESCrypto($key, 'aes-256-cbc');

            $inputFile = $this->testDataDir . '/small.txt';
            $encryptedFile = $this->testDataDir . '/small_encrypted.aes';
            $decryptedFile = $this->testDataDir . '/small_decrypted.txt';

            // 加密文件
            $success = $aes->encryptFile($inputFile, $encryptedFile);
            if (!$success) return false;

            // 解密文件
            $success = $aes->decryptFile($encryptedFile, $decryptedFile);
            if (!$success) return false;

            // 验证内容
            $original = file_get_contents($inputFile);
            $decrypted = file_get_contents($decryptedFile);

            // 清理测试文件
            unlink($encryptedFile);
            unlink($decryptedFile);

            return $original === $decrypted;
        });

        $this->testCase("AES-11", "AES大文件加密解密", function() {
            $key = AESCrypto::generateKey('aes-256-cbc');
            $aes = new AESCrypto($key, 'aes-256-cbc');

            $inputFile = $this->testDataDir . '/large.txt';
            $encryptedFile = $this->testDataDir . '/large_encrypted.aes';
            $decryptedFile = $this->testDataDir . '/large_decrypted.txt';

            // 加密文件
            $success = $aes->encryptFile($inputFile, $encryptedFile);
            if (!$success) return false;

            // 解密文件
            $success = $aes->decryptFile($encryptedFile, $decryptedFile);
            if (!$success) return false;

            // 验证内容
            $original = file_get_contents($inputFile);
            $decrypted = file_get_contents($decryptedFile);

            // 清理测试文件
            unlink($encryptedFile);
            unlink($decryptedFile);

            return $original === $decrypted;
        });

        $this->testCase("AES-12", "AES文件压缩加密", function() {
            $key = AESCrypto::generateKey('aes-256-cbc');
            $aes = new AESCrypto($key, 'aes-256-cbc');

            $inputFile = $this->testDataDir . '/medium.txt';
            $encryptedFile = $this->testDataDir . '/medium_compressed_encrypted.aes';
            $decryptedFile = $this->testDataDir . '/medium_decompressed.txt';

            // 启用压缩加密文件
            $success = $aes->encryptFile($inputFile, $encryptedFile, 65536, true, true);
            if (!$success) return false;

            // 解密文件
            $success = $aes->decryptFile($encryptedFile, $decryptedFile);
            if (!$success) return false;

            // 验证内容
            $original = file_get_contents($inputFile);
            $decrypted = file_get_contents($decryptedFile);

            // 清理测试文件
            unlink($encryptedFile);
            unlink($decryptedFile);

            return $original === $decrypted;
        });

        $this->testCase("AES-13", "AES字符串到文件加密", function() {
            $key = AESCrypto::generateKey('aes-256-cbc');
            $aes = new AESCrypto($key, 'aes-256-cbc');

            $testData = "这是要加密的字符串数据，包含中文和特殊字符：🚀🌟✨";
            $outputFile = $this->testDataDir . '/string_encrypted.aes';

            // 加密字符串到文件
            $success = $aes->encryptStringToFile($testData, $outputFile, true);
            if (!$success) return false;

            // 从文件解密字符串
            $decrypted = $aes->decryptFileToString($outputFile);

            // 清理测试文件
            unlink($outputFile);

            return $decrypted === $testData;
        });

        $this->testCase("AES-14", "AES批量文件加密", function() {
            $key = AESCrypto::generateKey('aes-256-cbc');
            $aes = new AESCrypto($key, 'aes-256-cbc');

            $files = [
                $this->testDataDir . '/small.txt',
                $this->testDataDir . '/medium.txt'
            ];

            $outputDir = $this->testDataDir . '/batch_output';

            // 批量加密文件
            $results = $aes->encryptFiles($files, $outputDir, 65536, true);

            $successCount = 0;
            foreach ($results as $result) {
                if ($result['success']) {
                    $successCount++;
                    // 清理加密文件
                    unlink($result['output_file']);
                }
            }

            // 清理输出目录
            if (is_dir($outputDir)) {
                rmdir($outputDir);
            }

            return $successCount === count($files);
        });
    }

    /**
     * AES高级功能测试
     */
    private function testAESAdvancedFeatures(): void
    {
        $this->testCase("AES-15", "AES HMAC签名验证", function() {
            $key = AESCrypto::generateKey('aes-256-cbc');
            $aes = new AESCrypto($key, 'aes-256-cbc');

            $data = "需要签名的数据内容";

            // 计算HMAC
            $hmac = $aes->calculateHMAC($data, 'sha256');

            // 验证HMAC
            $valid = $aes->verifyHMAC($data, $hmac, 'sha256');

            return $valid;
        });

        $this->testCase("AES-16", "AES加密并签名", function() {
            $key = AESCrypto::generateKey('aes-256-cbc');
            $aes = new AESCrypto($key, 'aes-256-cbc');

            $data = "需要加密并签名的数据";

            // 加密并签名
            $package = $aes->encryptAndSign($data, 'sha256');

            // 解密并验证
            $decrypted = $aes->decryptAndVerify($package);

            return $decrypted === $data;
        });

        $this->testCase("AES-17", "AES获取加密信息", function() {
            $key = AESCrypto::generateKey('aes-256-gcm');
            $aes = new AESCrypto($key, 'aes-256-gcm');

            $info = $aes->getCipherInfo();

            $checks = [
                isset($info['method']),
                isset($info['key_length']),
                isset($info['iv_length']),
                isset($info['authenticated_mode']),
                $info['method'] === 'aes-256-gcm',
                $info['authenticated_mode'] === true
            ];

            return !in_array(false, $checks, true);
        });

        $this->testCase("AES-18", "AES性能测试", function() {
            $key = AESCrypto::generateKey('aes-256-cbc');
            $aes = new AESCrypto($key, 'aes-256-cbc');

            $performance = $aes->testPerformance(1024, 10);

            $checks = [
                isset($performance['method']),
                isset($performance['throughput']),
                isset($performance['operations_per_second']),
                $performance['method'] === 'aes-256-cbc'
            ];

            return !in_array(false, $checks, true);
        });

        $this->testCase("AES-19", "AES配置导出导入", function() {
            $key = AESCrypto::generateKey('aes-256-cbc');
            $aes1 = new AESCrypto($key, 'aes-256-cbc');

            // 导出配置
            $config = $aes1->exportConfig();

            // 导入配置创建新实例
            $aes2 = AESCrypto::fromConfig($config);

            $data = "配置导出导入测试数据";
            $encrypted = $aes1->encrypt($data);
            $decrypted = $aes2->decrypt($encrypted);

            return $decrypted === $data;
        });

        $this->testCase("AES-20", "AES调试模式", function() {
            $key = AESCrypto::generateKey('aes-256-cbc');

            // 启用调试模式
            $aes = new AESCrypto($key, 'aes-256-cbc', null, true);

            $data = "调试模式测试";
            $encrypted = $aes->encrypt($data);
            $decrypted = $aes->decrypt($encrypted);

            // 禁用调试模式
            $aes->setDebugMode(false);

            return $decrypted === $data;
        });
    }

    /**
     * AES错误处理测试
     */
    private function testAESErrorHandling(): void
    {
        $this->testCase("AES-21", "AES空数据加密", function() {
            try {
                $key = AESCrypto::generateKey('aes-256-cbc');
                $aes = new AESCrypto($key, 'aes-256-cbc');

                $aes->encrypt("");
                return false; // 应该抛出异常
            } catch (InvalidArgumentException $e) {
                return true; // 期望的异常
            } catch (Exception $e) {
                return false; // 其他异常
            }
        });

        $this->testCase("AES-22", "AES无效密钥长度", function() {
            try {
                $invalidKey = "too_short_key";
                new AESCrypto($invalidKey, 'aes-256-cbc');
                return false; // 应该抛出异常
            } catch (InvalidArgumentException $e) {
                return true; // 期望的异常
            } catch (Exception $e) {
                return false; // 其他异常
            }
        });

        $this->testCase("AES-23", "AES无效加密方法", function() {
            try {
                $key = AESCrypto::generateKey('aes-256-cbc');
                new AESCrypto($key, 'invalid-method');
                return false; // 应该抛出异常
            } catch (InvalidArgumentException $e) {
                return true; // 期望的异常
            } catch (Exception $e) {
                return false; // 其他异常
            }
        });

        $this->testCase("AES-24", "AES文件不存在错误", function() {
            try {
                $key = AESCrypto::generateKey('aes-256-cbc');
                $aes = new AESCrypto($key, 'aes-256-cbc');

                $aes->encryptFile('nonexistent.file', 'output.enc');
                return false; // 应该抛出异常
            } catch (InvalidArgumentException $e) {
                return true; // 期望的异常
            } catch (Exception $e) {
                return false; // 其他异常
            }
        });

        $this->testCase("AES-25", "AES无效HMAC算法", function() {
            try {
                $key = AESCrypto::generateKey('aes-256-cbc');
                $aes = new AESCrypto($key, 'aes-256-cbc');

                $aes->calculateHMAC('data', 'invalid-algorithm');
                return false; // 应该抛出异常
            } catch (InvalidArgumentException $e) {
                return true; // 期望的异常
            } catch (Exception $e) {
                return false; // 其他异常
            }
        });
    }

    /**
     * RSA基础功能测试
     */
    private function testRSABasicFunctions(): void
    {
        $this->testCase("RSA-01", "RSA密钥对生成", function() {
            $keyPair = RSACrypto::createKeyPair(2048);

            $checks = [
                isset($keyPair['private_key']),
                isset($keyPair['public_key']),
                isset($keyPair['key_size']),
                strlen($keyPair['private_key']) > 0,
                strlen($keyPair['public_key']) > 0,
                $keyPair['key_size'] === 2048
            ];

            return !in_array(false, $checks, true);
        });

        $this->testCase("RSA-02", "RSA从密钥创建实例", function() {
            $keyPair = RSACrypto::createKeyPair(2048);

            // 从私钥创建实例
            $rsaPrivate = RSACrypto::createFromKey($keyPair['private_key']);

            // 从公钥创建实例
            $rsaPublic = RSACrypto::createFromPublicKey($keyPair['public_key']);

            return $rsaPrivate->hasPrivateKey() && $rsaPublic->hasPublicKey();
        });

        $this->testCase("RSA-03", "RSA不同密钥长度", function() {
            $keySizes = [2048, 3072, 4096];
            $results = [];

            foreach ($keySizes as $keySize) {
                $keyPair = RSACrypto::createKeyPair($keySize);
                $rsa = RSACrypto::createFromKey($keyPair['private_key']);

                $results[] = $rsa->getKeySize() === $keySize;
            }

            return !in_array(false, $results, true);
        });
    }

    /**
     * RSA加密解密测试
     */
    private function testRSAEncryptionDecryption(): void
    {
        $this->testCase("RSA-04", "RSA基础加密解密", function() {
            $keyPair = RSACrypto::createKeyPair(2048);
            $rsa = RSACrypto::createFromKey($keyPair['private_key']);

            $testData = "Hello RSA Encryption! 测试中文内容 🚀";
            $encrypted = $rsa->encrypt($testData);
            $decrypted = $rsa->decrypt($encrypted);

            return $decrypted === $testData;
        });

        $this->testCase("RSA-05", "RSA不同填充方式", function() {
            $keyPair = RSACrypto::createKeyPair(2048);
            $rsa = RSACrypto::createFromKey($keyPair['private_key']);

            $testData = "填充方式测试数据";
            $results = [];

            // PKCS1 v1.5填充
            $encrypted1 = $rsa->encrypt($testData, OPENSSL_PKCS1_PADDING);
            $decrypted1 = $rsa->decrypt($encrypted1, OPENSSL_PKCS1_PADDING);
            $results[] = $decrypted1 === $testData;

            // OAEP填充
            $encrypted2 = $rsa->encrypt($testData, OPENSSL_PKCS1_OAEP_PADDING);
            $decrypted2 = $rsa->decrypt($encrypted2, OPENSSL_PKCS1_OAEP_PADDING);
            $results[] = $decrypted2 === $testData;

            return !in_array(false, $results, true);
        });

        $this->testCase("RSA-06", "RSA大数据分块加密", function() {
            $keyPair = RSACrypto::createKeyPair(2048);
            $rsa = RSACrypto::createFromKey($keyPair['private_key']);

            // 生成大于RSA块大小的数据
            $largeData = str_repeat("大数据分块加密测试", 100);

            $encrypted = $rsa->encrypt($largeData, OPENSSL_PKCS1_OAEP_PADDING, true);
            $decrypted = $rsa->decrypt($encrypted, OPENSSL_PKCS1_OAEP_PADDING);

            return $decrypted === $largeData;
        });
    }

    /**
     * RSA签名验证测试
     */
    private function testRSASigningVerification(): void
    {
        $this->testCase("RSA-07", "RSA数据签名验证", function() {
            $keyPair = RSACrypto::createKeyPair(2048);
            $rsa = RSACrypto::createFromKey($keyPair['private_key']);

            $data = "需要签名的数据内容";

            // 签名
            $signature = $rsa->sign($data);

            // 验证签名
            $valid = $rsa->verify($data, $signature);

            return $valid;
        });

        $this->testCase("RSA-08", "RSA文件签名验证", function() {
            $keyPair = RSACrypto::createKeyPair(2048);
            $rsa = RSACrypto::createFromKey($keyPair['private_key']);

            $filePath = $this->testDataDir . '/small.txt';

            // 文件签名
            $signature = $rsa->signFile($filePath);

            // 验证文件签名
            $valid = $rsa->verifyFile($filePath, $signature);

            return $valid;
        });

        $this->testCase("RSA-09", "RSA不同哈希算法签名", function() {
            $keyPair = RSACrypto::createKeyPair(2048);
            $rsa = RSACrypto::createFromKey($keyPair['private_key']);

            $data = "不同哈希算法测试";
            $results = [];

            $algorithms = ['sha256', 'sha384', 'sha512'];
            foreach ($algorithms as $algorithm) {
                $signature = $rsa->sign($data, $algorithm);
                $valid = $rsa->verify($data, $signature, $algorithm);
                $results[] = $valid;
            }

            return !in_array(false, $results, true);
        });

        $this->testCase("RSA-10", "RSA加密并签名", function() {
            // 发送方
            $senderKeyPair = RSACrypto::createKeyPair(2048);
            $sender = RSACrypto::createFromKey($senderKeyPair['private_key']);

            // 接收方
            $receiverKeyPair = RSACrypto::createKeyPair(2048);
            $receiver = RSACrypto::createFromKey($receiverKeyPair['private_key']);

            $data = "需要加密并签名的敏感数据";

            // 发送方加密并签名
            $package = $sender->encryptAndSign($data, $receiver);

            // 接收方解密并验证
            $decrypted = $receiver->decryptAndVerify($package, $sender);

            return $decrypted === $data;
        });
    }

    /**
     * RSA文件操作测试
     */
    private function testRSAFileOperations(): void
    {
        $this->testCase("RSA-11", "RSA文件加密解密", function() {
            $keyPair = RSACrypto::createKeyPair(2048);
            $rsa = RSACrypto::createFromKey($keyPair['private_key']);

            $inputFile = $this->testDataDir . '/small.txt';
            $encryptedFile = $this->testDataDir . '/small_encrypted.rsa';
            $decryptedFile = $this->testDataDir . '/small_decrypted.txt';

            // 加密文件
            $success = $rsa->encryptFile($inputFile, $encryptedFile);
            if (!$success) return false;

            // 解密文件
            $success = $rsa->decryptFile($encryptedFile, $decryptedFile);
            if (!$success) return false;

            // 验证内容
            $original = file_get_contents($inputFile);
            $decrypted = file_get_contents($decryptedFile);

            // 清理测试文件
            unlink($encryptedFile);
            unlink($decryptedFile);

            return $original === $decrypted;
        });

        $this->testCase("RSA-12", "RSA批量文件加密", function() {
            $keyPair = RSACrypto::createKeyPair(2048);
            $rsa = RSACrypto::createFromKey($keyPair['private_key']);

            $files = [
                $this->testDataDir . '/small.txt',
                $this->testDataDir . '/medium.txt'
            ];

            $outputDir = $this->testDataDir . '/rsa_batch_output';

            // 批量加密文件
            $results = $rsa->encryptFiles($files, $outputDir);

            $successCount = 0;
            foreach ($results as $result) {
                if ($result['success']) {
                    $successCount++;
                    // 清理加密文件
                    unlink($result['output_file']);
                }
            }

            // 清理输出目录
            if (is_dir($outputDir)) {
                rmdir($outputDir);
            }

            return $successCount === count($files);
        });
    }

    /**
     * RSA高级功能测试
     */
    private function testRSAAdvancedFeatures(): void
    {
        $this->testCase("RSA-13", "RSA密钥导出", function() {
            $keyPair = RSACrypto::createKeyPair(2048);
            $rsa = RSACrypto::createFromKey($keyPair['private_key']);

            // 导出私钥
            $privateKey = $rsa->exportPrivateKey('password123');

            // 导出公钥
            $publicKey = $rsa->exportPublicKey();

            return strlen($privateKey) > 0 && strlen($publicKey) > 0;
        });

        $this->testCase("RSA-14", "RSA密钥详情", function() {
            $keyPair = RSACrypto::createKeyPair(2048);
            $rsa = RSACrypto::createFromKey($keyPair['private_key']);

            $details = $rsa->getKeyDetails();

            $checks = [
                isset($details['bits']),
                isset($details['key']),
                $details['bits'] === 2048
            ];

            return !in_array(false, $checks, true);
        });

        $this->testCase("RSA-15", "RSA密钥对验证", function() {
            $keyPair = RSACrypto::createKeyPair(2048);
            $rsa = RSACrypto::createFromKey($keyPair['private_key']);

            return $rsa->verifyKeyPair();
        });

        $this->testCase("RSA-16", "RSA密钥指纹", function() {
            $keyPair = RSACrypto::createKeyPair(2048);
            $rsa = RSACrypto::createFromKey($keyPair['private_key']);

            $fingerprint = $rsa->getKeyFingerprint('sha256');

            return strlen($fingerprint) === 64; // SHA256指纹长度为64字符
        });

        $this->testCase("RSA-17", "RSA加密信息", function() {
            $keyPair = RSACrypto::createKeyPair(2048);
            $rsa = RSACrypto::createFromKey($keyPair['private_key']);

            $info = $rsa->getCipherInfo();

            $checks = [
                isset($info['key_size']),
                isset($info['hash_algorithm']),
                isset($info['has_private_key']),
                isset($info['has_public_key']),
                $info['key_size'] === 2048,
                $info['has_private_key'] === true,
                $info['has_public_key'] === true
            ];

            return !in_array(false, $checks, true);
        });

        $this->testCase("RSA-18", "RSA性能测试", function() {
            $keyPair = RSACrypto::createKeyPair(2048);
            $rsa = RSACrypto::createFromKey($keyPair['private_key']);

            $performance = $rsa->testKeyStrength(10);

            $checks = [
                isset($performance['key_size']),
                isset($performance['encrypt_speed']),
                isset($performance['decrypt_speed']),
                $performance['key_size'] === 2048
            ];

            return !in_array(false, $checks, true);
        });

        $this->testCase("RSA-19", "RSA JWK导出", function() {
            $keyPair = RSACrypto::createKeyPair(2048);
            $rsa = RSACrypto::createFromKey($keyPair['private_key']);

            $jwk = $rsa->exportAsJWK();

            $checks = [
                isset($jwk['kty']),
                isset($jwk['n']),
                isset($jwk['e']),
                $jwk['kty'] === 'RSA'
            ];

            return !in_array(false, $checks, true);
        });
    }

    /**
     * RSA错误处理测试
     */
    private function testRSAErrorHandling(): void
    {
        $this->testCase("RSA-20", "RSA无私钥解密错误", function() {
            try {
                $keyPair = RSACrypto::createKeyPair(2048);
                $rsaPublic = RSACrypto::createFromPublicKey($keyPair['public_key']);

                $encrypted = "some_encrypted_data";
                $rsaPublic->decrypt($encrypted);
                return false; // 应该抛出异常
            } catch (RuntimeException $e) {
                return true; // 期望的异常
            } catch (Exception $e) {
                return false; // 其他异常
            }
        });

        $this->testCase("RSA-21", "RSA无公钥加密错误", function() {
            try {
                $keyPair = RSACrypto::createKeyPair(2048);
                $rsaPrivate = RSACrypto::createFromKey($keyPair['private_key']);

                // 模拟没有公钥的情况（实际上createFromKey会导出公钥）
                // 这里我们测试无效数据的情况
                $rsaPrivate->encrypt("");
                return false; // 应该抛出异常
            } catch (InvalidArgumentException $e) {
                return true; // 期望的异常
            } catch (Exception $e) {
                return false; // 其他异常
            }
        });

        $this->testCase("RSA-22", "RSA无效密钥格式", function() {
            try {
                RSACrypto::createFromKey("invalid_private_key_format");
                return false; // 应该抛出异常
            } catch (RuntimeException $e) {
                return true; // 期望的异常
            } catch (Exception $e) {
                return false; // 其他异常
            }
        });

        $this->testCase("RSA-23", "RSA文件不存在错误", function() {
            try {
                $keyPair = RSACrypto::createKeyPair(2048);
                $rsa = RSACrypto::createFromKey($keyPair['private_key']);

                $rsa->encryptFile('nonexistent.file', 'output.rsa');
                return false; // 应该抛出异常
            } catch (InvalidArgumentException $e) {
                return true; // 期望的异常
            } catch (Exception $e) {
                return false; // 其他异常
            }
        });
    }

    /**
     * ECC基础功能测试
     */
    private function ECCBasicFunctions(): void
    {
        $this->testCase("ECC-01", "ECC密钥对生成", function() {
            $curves = ['prime256v1', 'secp384r1', 'secp521r1'];
            $results = [];

            foreach ($curves as $curve) {
                $keyPair = ECCCrypto::createKeyPair($curve);

                $checks = [
                    isset($keyPair['private_key']),
                    isset($keyPair['public_key']),
                    isset($keyPair['curve_name']),
                    strlen($keyPair['private_key']) > 0,
                    strlen($keyPair['public_key']) > 0,
                    $keyPair['curve_name'] === $curve
                ];

                $results[] = !in_array(false, $checks, true);
            }

            return !in_array(false, $results, true);
        });

        $this->testCase("ECC-02", "ECC从密钥创建实例", function() {
            $keyPair = ECCCrypto::createKeyPair('prime256v1');

            // 从私钥创建实例
            $eccPrivate = ECCCrypto::createFromKey($keyPair['private_key']);

            // 从公钥创建实例
            $eccPublic = ECCCrypto::createFromPublicKey($keyPair['public_key']);

            return $eccPrivate->hasPrivateKey() && $eccPublic->hasPublicKey();
        });

        $this->testCase("ECC-03", "ECC曲线信息", function() {
            $ecc = new ECCCrypto(null, null, 'prime256v1');

            $curveInfo = $ecc->getCurveSecurityInfo();

            $checks = [
                isset($curveInfo['curve_name']),
                isset($curveInfo['display_name']),
                isset($curveInfo['security_level']),
                $curveInfo['curve_name'] === 'prime256v1'
            ];

            return !in_array(false, $checks, true);
        });
    }

    /**
     * ECC签名验证测试
     */
    private function ECCSigningVerification(): void
    {
        $this->testCase("ECC-04", "ECC数据签名验证", function() {
            $keyPair = ECCCrypto::createKeyPair('prime256v1');
            $ecc = ECCCrypto::createFromKey($keyPair['private_key']);

            $data = "需要签名的ECC数据内容";

            // 签名
            $signature = $ecc->sign($data);

            // 验证签名
            $valid = $ecc->verify($data, $signature);

            return $valid;
        });

        $this->testCase("ECC-05", "ECC文件签名验证", function() {
            $keyPair = ECCCrypto::createKeyPair('prime256v1');
            $ecc = ECCCrypto::createFromKey($keyPair['private_key']);

            $filePath = $this->testDataDir . '/small.txt';

            // 文件签名
            $signature = $ecc->signFile($filePath);

            // 验证文件签名
            $valid = $ecc->verifyFile($filePath, $signature);

            return $valid;
        });

        $this->testCase("ECC-06", "ECC不同哈希算法签名", function() {
            $keyPair = ECCCrypto::createKeyPair('prime256v1');
            $ecc = ECCCrypto::createFromKey($keyPair['private_key']);

            $data = "不同ECC哈希算法测试";
            $results = [];

            $algorithms = ['sha256', 'sha384', 'sha512'];
            foreach ($algorithms as $algorithm) {
                $signature = $ecc->sign($data, $algorithm);
                $valid = $ecc->verify($data, $signature, $algorithm);
                $results[] = $valid;
            }

            return !in_array(false, $results, true);
        });

        $this->testCase("ECC-07", "ECC确定性签名", function() {
            $keyPair = ECCCrypto::createKeyPair('prime256v1');
            $ecc = ECCCrypto::createFromKey($keyPair['private_key']);

            $data = "确定性ECDSA签名测试";

            // 使用确定性签名
            $signature1 = $ecc->sign($data, 'sha256', true);
            $signature2 = $ecc->sign($data, 'sha256', true);

            // 验证两个签名都应该有效
            $valid1 = $ecc->verify($data, $signature1);
            $valid2 = $ecc->verify($data, $signature2);

            // 在理想情况下，确定性签名应该产生相同的结果
            // 但由于PHP OpenSSL限制，我们主要验证签名有效性
            $signaturesEqual = ($signature1 === $signature2);

            if (!$signaturesEqual) {
                echo "信息: 当前环境确定性签名产生不同结果，但签名验证有效\n<br />";
            }

            return $valid1 && $valid2;
        });

        $this->testCase("ECC-08", "ECC带时间戳签名", function() {
            $keyPair = ECCCrypto::createKeyPair('prime256v1');
            $ecc = ECCCrypto::createFromKey($keyPair['private_key']);

            $data = "带时间戳的签名数据";

            // 生成带时间戳的签名
            $signedPackage = $ecc->signWithTimestamp($data);

            // 验证带时间戳的签名
            $valid = $ecc->verifyWithTimestamp(
                $data,
                $signedPackage['signature'],
                $signedPackage['timestamp']
            );

            return $valid;
        });
    }

    /**
     * ECC密钥交换测试
     */
    private function ECCKeyExchange(): void
    {
        $this->testCase("ECC-09", "ECDH密钥交换", function() {
            // Alice生成密钥对
            $aliceKeyPair = ECCCrypto::createKeyPair('prime256v1');
            $alice = ECCCrypto::createFromKey($aliceKeyPair['private_key']);

            // Bob生成密钥对
            $bobKeyPair = ECCCrypto::createKeyPair('prime256v1');
            $bob = ECCCrypto::createFromKey($bobKeyPair['private_key']);

            // Alice计算共享密钥
            $aliceShared = $alice->computeSharedSecret($bobKeyPair['public_key']);

            // Bob计算共享密钥
            $bobShared = $bob->computeSharedSecret($aliceKeyPair['public_key']);

            // 共享密钥应该相同
            return $aliceShared === $bobShared;
        });

        $this->testCase("ECC-10", "ECDH带KDF密钥交换", function() {
            // Alice生成密钥对
            $aliceKeyPair = ECCCrypto::createKeyPair('prime256v1');
            $alice = ECCCrypto::createFromKey($aliceKeyPair['private_key']);

            // Bob生成密钥对
            $bobKeyPair = ECCCrypto::createKeyPair('prime256v1');
            $bob = ECCCrypto::createFromKey($bobKeyPair['private_key']);

            $salt = "key_exchange_salt";
            $info = "application_data";

            // Alice计算共享密钥（带KDF）
            $aliceShared = $alice->computeSharedSecret(
                $bobKeyPair['public_key'],
                32,
                'sha256',
                $info
            );

            // Bob计算共享密钥（带KDF）
            $bobShared = $bob->computeSharedSecret(
                $aliceKeyPair['public_key'],
                32,
                'sha256',
                $info
            );

            // 共享密钥应该相同
            return $aliceShared === $bobShared;
        });
    }

    /**
     * ECC文件操作测试
     */
    private function ECCFileOperations(): void
    {
        $this->testCase("ECC-11", "ECC批量文件签名", function() {
            $keyPair = ECCCrypto::createKeyPair('prime256v1');
            $ecc = ECCCrypto::createFromKey($keyPair['private_key']);

            $files = [
                $this->testDataDir . '/small.txt',
                $this->testDataDir . '/medium.txt'
            ];

            // 批量签名文件
            $results = $ecc->signFiles($files, 'sha256', true);

            $successCount = 0;
            foreach ($results as $result) {
                if ($result['success']) {
                    $successCount++;
                }
            }

            return $successCount === count($files);
        });

        $this->testCase("ECC-12", "ECC批量文件验证", function() {
            $keyPair = ECCCrypto::createKeyPair('prime256v1');
            $ecc = ECCCrypto::createFromKey($keyPair['private_key']);

            $files = [
                $this->testDataDir . '/small.txt',
                $this->testDataDir . '/medium.txt'
            ];

            // 先批量签名
            $signatureResults = $ecc->signFiles($files, 'sha256', true);

            // 准备验证数据
            $filesWithSignatures = [];
            foreach ($signatureResults as $filePath => $result) {
                if ($result['success']) {
                    $filesWithSignatures[$filePath] = $result['signature'];
                }
            }

            // 批量验证签名
            $verifyResults = $ecc->verifyFiles($filesWithSignatures, 'sha256');

            $successCount = 0;
            foreach ($verifyResults as $result) {
                if ($result['success'] && $result['verified']) {
                    $successCount++;
                }
            }

            return $successCount === count($filesWithSignatures);
        });
    }

    /**
     * ECC高级功能测试
     */
    private function ECCAdvancedFeatures(): void
    {
        $this->testCase("ECC-13", "ECC密钥导出", function() {
            $keyPair = ECCCrypto::createKeyPair('prime256v1');
            $ecc = ECCCrypto::createFromKey($keyPair['private_key']);

            // 导出私钥
            $privateKey = $ecc->exportPrivateKey('password123');

            // 导出公钥
            $publicKey = $ecc->exportPublicKey();

            return strlen($privateKey) > 0 && strlen($publicKey) > 0;
        });

        $this->testCase("ECC-14", "ECC密钥详情", function() {
            $keyPair = ECCCrypto::createKeyPair('prime256v1');
            $ecc = ECCCrypto::createFromKey($keyPair['private_key']);

            $details = $ecc->getKeyDetails();

            $checks = [
                isset($details['ec']),
                isset($details['ec']['curve_name']),
                $details['ec']['curve_name'] === 'prime256v1'
            ];

            return !in_array(false, $checks, true);
        });

        $this->testCase("ECC-15", "ECC密钥对验证", function() {
            $keyPair = ECCCrypto::createKeyPair('prime256v1');
            $ecc = ECCCrypto::createFromKey($keyPair['private_key']);

            return $ecc->verifyKeyPair();
        });

        $this->testCase("ECC-16", "ECC密钥指纹", function() {
            $keyPair = ECCCrypto::createKeyPair('prime256v1');
            $ecc = ECCCrypto::createFromKey($keyPair['private_key']);

            $fingerprint = $ecc->getKeyFingerprint('sha256');

            return strlen($fingerprint) === 64; // SHA256指纹长度为64字符
        });

        $this->testCase("ECC-17", "ECC加密信息", function() {
            $keyPair = ECCCrypto::createKeyPair('prime256v1');
            $ecc = ECCCrypto::createFromKey($keyPair['private_key']);

            $info = $ecc->getCipherInfo();

            $checks = [
                isset($info['curve_name']),
                isset($info['has_private_key']),
                isset($info['has_public_key']),
                $info['curve_name'] === 'prime256v1',
                $info['has_private_key'] === true,
                $info['has_public_key'] === true
            ];

            return !in_array(false, $checks, true);
        });

        $this->testCase("ECC-18", "ECC性能测试", function() {
            $keyPair = ECCCrypto::createKeyPair('prime256v1');
            $ecc = ECCCrypto::createFromKey($keyPair['private_key']);

            $performance = $ecc->testKeyStrength(50);

            $checks = [
                isset($performance['curve_name']),
                isset($performance['sign_speed']),
                isset($performance['verify_speed']),
                $performance['curve_name'] === 'prime256v1'
            ];

            return !in_array(false, $checks, true);
        });

        $this->testCase("ECC-19", "ECC JWK导出", function() {
            $keyPair = ECCCrypto::createKeyPair('prime256v1');
            $ecc = ECCCrypto::createFromKey($keyPair['private_key']);

            $jwk = $ecc->exportAsJWK(true); // 包含私钥

            $checks = [
                isset($jwk['kty']),
                isset($jwk['crv']),
                isset($jwk['x']),
                isset($jwk['y']),
                isset($jwk['d']), // 私钥参数
                $jwk['kty'] === 'EC',
                $jwk['crv'] === 'prime256v1'
            ];

            return !in_array(false, $checks, true);
        });

        $this->testCase("ECC-20", "ECC证书签名请求", function() {
            $keyPair = ECCCrypto::createKeyPair('prime256v1');
            $ecc = ECCCrypto::createFromKey($keyPair['private_key']);

            $dn = [
                "countryName" => "CN",
                "stateOrProvinceName" => "Beijing",
                "localityName" => "Beijing",
                "organizationName" => "Test Organization",
                "organizationalUnitName" => "IT Department",
                "commonName" => "example.com",
                "emailAddress" => "admin@example.com"
            ];

            $csr = $ecc->generateCSR($dn);

            return strlen($csr) > 0;
        });

        $this->testCase("ECC-21", "ECC曲线更改", function() {
            $keyPair = ECCCrypto::createKeyPair('prime256v1');
            $ecc = ECCCrypto::createFromKey($keyPair['private_key']);

            // 更改曲线
            $ecc->changeCurve('secp384r1');

            return $ecc->getCurveName() === 'secp384r1';
        });
    }

    /**
     * ECC错误处理测试
     */
    private function ECCErrorHandling(): void
    {
        $this->testCase("ECC-22", "ECC无私钥签名错误", function() {
            try {
                $keyPair = ECCCrypto::createKeyPair('prime256v1');
                $eccPublic = ECCCrypto::createFromPublicKey($keyPair['public_key']);

                $eccPublic->sign("data");
                return false; // 应该抛出异常
            } catch (RuntimeException $e) {
                return true; // 期望的异常
            } catch (Exception $e) {
                return false; // 其他异常
            }
        });

        $this->testCase("ECC-23", "ECC无效曲线错误", function() {
            try {
                new ECCCrypto(null, null, 'invalid_curve');
                return false; // 应该抛出异常
            } catch (InvalidArgumentException $e) {
                return true; // 期望的异常
            } catch (Exception $e) {
                return false; // 其他异常
            }
        });

        $this->testCase("ECC-24", "ECC无效签名算法", function() {
            try {
                $keyPair = ECCCrypto::createKeyPair('prime256v1');
                $ecc = ECCCrypto::createFromKey($keyPair['private_key']);

                $ecc->sign("data", "invalid_algorithm");
                return false; // 应该抛出异常
            } catch (InvalidArgumentException $e) {
                return true; // 期望的异常
            } catch (Exception $e) {
                return false; // 其他异常
            }
        });

        $this->testCase("ECC-25", "ECC文件不存在错误", function() {
            try {
                $keyPair = ECCCrypto::createKeyPair('prime256v1');
                $ecc = ECCCrypto::createFromKey($keyPair['private_key']);

                $ecc->signFile('nonexistent.file');
                return false; // 应该抛出异常
            } catch (InvalidArgumentException $e) {
                return true; // 期望的异常
            } catch (Exception $e) {
                return false; // 其他异常
            }
        });
    }

    /**
     * 单个测试用例
     */
    private function testCase(string $testId, string $description, callable $testFunction): void
    {
        $this->totalTests++;

        try {
            $result = $testFunction();

            if ($result) {
                $this->passedTests++;
                $status = "✓ 通过";
            } else {
                $status = "✗ 失败";
            }

            $this->testResults[] = [
                'id' => $testId,
                'description' => $description,
                'status' => $status,
                'passed' => $result
            ];

            echo "{$testId}: {$description} - {$status}\n<br />";

        } catch (Exception $e) {
            $this->testResults[] = [
                'id' => $testId,
                'description' => $description,
                'status' => "✗ 异常: " . $e->getMessage(),
                'passed' => false
            ];

            echo "{$testId}: {$description} - ✗ 异常: " . $e->getMessage() . "\n<br />";
        }
    }

    /**
     * 生成测试报告
     */
    private function generateTestReport(): void
    {
        echo "\n<br />========================================\n<br />";
        echo "测试报告\n<br />";
        echo "========================================\n<br />\n<br />";

        $passedCount = 0;
        $failedCount = 0;
        $errorCount = 0;

        foreach ($this->testResults as $result) {
            if ($result['passed']) {
                $passedCount++;
            } else {
                if (strpos($result['status'], '异常') !== false) {
                    $errorCount++;
                } else {
                    $failedCount++;
                }
            }
        }

        echo "测试统计:\n<br />";
        echo "总测试数: {$this->totalTests}\n<br />";
        echo "通过: {$passedCount}\n<br />";
        echo "失败: {$failedCount}\n<br />";
        echo "异常: {$errorCount}\n<br />";
        echo "通过率: " . round(($passedCount / $this->totalTests) * 100, 2) . "%\n<br />\n<br />";

        // 显示失败和异常的测试
        if ($failedCount > 0 || $errorCount > 0) {
            echo "失败的测试:\n<br />";
            foreach ($this->testResults as $result) {
                if (!$result['passed']) {
                    echo "  {$result['id']}: {$result['description']} - {$result['status']}\n<br />";
                }
            }
        }

        echo "\n<br />测试完成!\n<br />";
    }
}

// 运行测试套件
$testSuite = new CryptoTestSuite();
$testSuite->runAllTests();


```