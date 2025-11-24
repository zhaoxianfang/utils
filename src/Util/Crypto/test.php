<?php

/**
 * 加密类全面测试套件
 * 覆盖AES、ECC、RSA所有功能测试
 */

require_once 'AES.php';
require_once 'ECC.php';
require_once 'RSA.php';

class CryptoTestSuite
{
    private string $testDataDir;
    private array $testResults = [];

    public function __construct()
    {
        $this->testDataDir = __DIR__ . '/test_data';
        $this->setupTestEnvironment();
    }

    /**
     * 设置测试环境
     */
    private function setupTestEnvironment(): void
    {
        echo "=== 设置测试环境 ===\n<br/>";

        if (!is_dir($this->testDataDir)) {
            mkdir($this->testDataDir, 0755, true);
            echo "创建测试目录: {$this->testDataDir}\n<br/>";
        }

        // 创建测试文件
        $this->createTestFiles();
        echo "测试环境设置完成\n<br/>\n<br/>";
    }

    /**
     * 创建测试文件
     */
    private function createTestFiles(): void
    {
        $testFiles = [
            'small.txt' => "这是一个小型测试文件内容。\n<br/>Hello World!",
            'medium.txt' => str_repeat("这是中等大小测试文件内容。", 100),
            'large.txt' => str_repeat("这是大型测试文件内容，用于测试大文件处理能力。", 1000),
            'binary.bin' => $this->generateBinaryData(1024)
        ];

        foreach ($testFiles as $filename => $content) {
            $filepath = $this->testDataDir . '/' . $filename;
            file_put_contents($filepath, $content);
            echo "创建测试文件: {$filename} (" . strlen($content) . " 字节)\n<br/>";
        }
    }

    /**
     * 生成二进制数据
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
        echo "=== 开始全面加密测试 ===\n<br/>\n<br/>";

        $this->testAESCrypto();
        $this->testECCCrypto();
        $this->testRSACrypto();

        $this->printTestSummary();
    }

    /**
     * AES加密测试
     */
    private function testAESCrypto(): void
    {
        echo "=== AES加密测试 ===\n<br/>";

        // 测试1: 基本加密解密
        $this->runTest('AES-基本加密解密', function() {
            $key = AESCrypto::generateKey('aes-256-cbc');
            $aes = new AESCrypto($key, 'aes-256-cbc', null, true);

            $originalData = "Hello, AES Encryption! 这是测试数据。";
            $encrypted = $aes->encrypt($originalData);
            $decrypted = $aes->decrypt($encrypted);

            echo "原始数据: {$originalData}\n<br/>";
            echo "加密数据: " . substr($encrypted, 0, 50) . "...\n<br/>";
            echo "解密数据: {$decrypted}\n<br/>";
            echo "测试结果: " . ($originalData === $decrypted ? "成功" : "失败") . "\n<br/>";

            return $originalData === $decrypted;
        });

        // 测试2: 不同加密模式
        $this->runTest('AES-不同加密模式', function() {
            $modes = ['aes-256-cbc', 'aes-256-ecb', 'aes-256-cfb'];
            $testData = "测试不同加密模式的数据";

            foreach ($modes as $mode) {
                $key = AESCrypto::generateKey($mode);
                $aes = new AESCrypto($key, $mode, null, false);

                $encrypted = $aes->encrypt($testData);
                $decrypted = $aes->decrypt($encrypted);

                if ($testData !== $decrypted) {
                    echo "模式 {$mode} 测试失败\n<br/>";
                    return false;
                }
                echo "模式 {$mode} 测试成功\n<br/>";
            }
            return true;
        });

        // 测试3: 文件加密解密
        $this->runTest('AES-文件加密解密', function() {
            $key = AESCrypto::generateKey('aes-256-cbc');
            $aes = new AESCrypto($key, 'aes-256-cbc', null, true);

            $inputFile = $this->testDataDir . '/small.txt';
            $encryptedFile = $this->testDataDir . '/small_encrypted.aes';
            $decryptedFile = $this->testDataDir . '/small_decrypted.txt';

            // 加密文件
            $success = $aes->encryptFile($inputFile, $encryptedFile);
            if (!$success) {
                echo "文件加密失败\n<br/>";
                return false;
            }
            echo "文件加密成功: " . filesize($encryptedFile) . " 字节\n<br/>";

            // 解密文件
            $success = $aes->decryptFile($encryptedFile, $decryptedFile);
            if (!$success) {
                echo "文件解密失败\n<br/>";
                return false;
            }
            echo "文件解密成功: " . filesize($decryptedFile) . " 字节\n<br/>";

            // 验证内容
            $original = file_get_contents($inputFile);
            $decrypted = file_get_contents($decryptedFile);

            $result = $original === $decrypted;
            echo "文件内容验证: " . ($result ? "成功" : "失败") . "\n<br/>";

            // 清理
            unlink($encryptedFile);
            unlink($decryptedFile);

            return $result;
        });

        // 测试4: 字符串到文件加密
        $this->runTest('AES-字符串到文件加密', function() {
            $key = AESCrypto::generateKey('aes-256-cbc');
            $aes = new AESCrypto($key, 'aes-256-cbc', null, true);

            $testData = "这是要加密到文件的字符串数据";
            $outputFile = $this->testDataDir . '/string_encrypted.aes';

            $success = $aes->encryptStringToFile($testData, $outputFile);
            if (!$success) {
                echo "字符串到文件加密失败\n<br/>";
                return false;
            }
            echo "字符串到文件加密成功\n<br/>";

            $decrypted = $aes->decryptFileToString($outputFile);
            echo "文件到字符串解密成功\n<br/>";

            $result = $testData === $decrypted;
            echo "字符串内容验证: " . ($result ? "成功" : "失败") . "\n<br/>";

            unlink($outputFile);
            return $result;
        });

        // 测试5: 批量文件加密
        $this->runTest('AES-批量文件加密', function() {
            $key = AESCrypto::generateKey('aes-256-cbc');
            $aes = new AESCrypto($key, 'aes-256-cbc', null, true);

            $files = [
                $this->testDataDir . '/small.txt',
                $this->testDataDir . '/medium.txt'
            ];

            $outputDir = $this->testDataDir . '/batch_encrypted';
            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            $results = $aes->encryptFiles($files, $outputDir);

            $successCount = 0;
            foreach ($results as $file => $result) {
                echo "文件 {$file}: " . ($result['success'] ? "成功" : "失败") . "\n<br/>";
                if ($result['success']) {
                    $successCount++;
                }
            }

            // 清理
            $this->deleteDirectory($outputDir);

            return $successCount === count($files);
        });

        // 测试6: HMAC签名
        $this->runTest('AES-HMAC签名验证', function() {
            $key = AESCrypto::generateKey('aes-256-cbc');
            $aes = new AESCrypto($key, 'aes-256-cbc', null, true);

            $data = "需要签名的数据";
            $hmac = $aes->calculateHMAC($data);

            echo "HMAC签名: " . substr($hmac, 0, 20) . "...\n<br/>";

            $verified = $aes->verifyHMAC($data, $hmac);
            echo "HMAC验证: " . ($verified ? "成功" : "失败") . "\n<br/>";

            return $verified;
        });

        // 测试7: 加密并签名
        $this->runTest('AES-加密并签名', function() {
            $key = AESCrypto::generateKey('aes-256-cbc');
            $aes = new AESCrypto($key, 'aes-256-cbc', null, true);

            $data = "需要加密并签名的数据";
            $package = $aes->encryptAndSign($data);

            echo "加密数据包生成成功\n<br/>";
            echo "包含字段: " . implode(', ', array_keys($package)) . "\n<br/>";

            $decrypted = $aes->decryptAndVerify($package);
            echo "解密并验证: " . ($data === $decrypted ? "成功" : "失败") . "\n<br/>";

            return $data === $decrypted;
        });

        // 测试8: 性能测试
        $this->runTest('AES-性能测试', function() {
            $key = AESCrypto::generateKey('aes-256-cbc');
            $aes = new AESCrypto($key, 'aes-256-cbc', null, false);

            $performance = $aes->testPerformance(1024, 100);

            echo "加密方法: {$performance['method']}\n<br/>";
            echo "吞吐量: {$performance['throughput']}\n<br/>";
            echo "操作次数/秒: {$performance['operations_per_second']}\n<br/>";
            echo "总时间: {$performance['total_time']}\n<br/>";

            return $performance['throughput'] > 0;
        });

        // 测试9: 密钥管理
        $this->runTest('AES-密钥管理', function() {
            $key = AESCrypto::generateKey('aes-256-cbc');
            $aes = new AESCrypto($key, 'aes-256-cbc', null, true);

            $originalMethod = $aes->getMethod();
            $originalIV = $aes->getIV();

            echo "原始方法: {$originalMethod}\n<br/>";
            echo "原始IV: " . bin2hex($originalIV) . "\n<br/>";

            // 更改密钥
            $newKey = AESCrypto::generateKey('aes-256-cbc');
            $aes->changeKey($newKey);
            echo "密钥更改成功\n<br/>";

            // 更改方法
            $aes->changeMethod('aes-128-cbc');
            $newMethod = $aes->getMethod();
            echo "方法更改: {$originalMethod} -> {$newMethod}\n<br/>";

            return $newMethod === 'aes-128-cbc';
        });

        // 测试10: 从密码生成密钥
        $this->runTest('AES-从密码生成密钥', function() {
            $password = "my_secure_password";
            $salt = "random_salt_value";

            $key = AESCrypto::generateKeyFromPassword($password, $salt, 'aes-256-cbc');

            echo "从密码生成的密钥长度: " . strlen($key) . " 字节\n<br/>";

            $aes = new AESCrypto($key, 'aes-256-cbc', null, true);
            $testData = "测试密码派生密钥";

            $encrypted = $aes->encrypt($testData);
            $decrypted = $aes->decrypt($encrypted);

            return $testData === $decrypted;
        });

        echo "\n<br/>";
    }

    /**
     * ECC加密测试
     */
    private function testECCCrypto(): void
    {
        echo "=== ECC加密测试 ===\n<br/>";

        // 测试1: 基本密钥生成和签名
        $this->runTest('ECC-基本密钥生成和签名', function() {
            $ecc = new ECCCrypto(null, null, 'prime256v1', '', true);

            $data = "Hello, ECC Signature!";
            $signature = $ecc->sign($data);

            echo "数据签名成功\n<br/>";
            echo "签名: " . substr($signature, 0, 30) . "...\n<br/>";

            $verified = $ecc->verify($data, $signature);
            echo "签名验证: " . ($verified ? "成功" : "失败") . "\n<br/>";

            return $verified;
        });

        // 测试2: 不同曲线测试
        $this->runTest('ECC-不同曲线测试', function() {
            $curves = ['prime256v1', 'secp384r1'];
            $testData = "测试不同ECC曲线";

            foreach ($curves as $curve) {
                $ecc = new ECCCrypto(null, null, $curve, '', false);

                $signature = $ecc->sign($testData);
                $verified = $ecc->verify($testData, $signature);

                if (!$verified) {
                    echo "曲线 {$curve} 测试失败\n<br/>";
                    return false;
                }
                echo "曲线 {$curve} 测试成功\n<br/>";
            }
            return true;
        });

        // 测试3: 文件签名验证
        $this->runTest('ECC-文件签名验证', function() {
            $ecc = new ECCCrypto(null, null, 'prime256v1', '', true);

            $filePath = $this->testDataDir . '/medium.txt';
            $signature = $ecc->signFile($filePath);

            echo "文件签名成功\n<br/>";
            echo "签名: " . substr($signature, 0, 30) . "...\n<br/>";

            $verified = $ecc->verifyFile($filePath, $signature);
            echo "文件签名验证: " . ($verified ? "成功" : "失败") . "\n<br/>";

            return $verified;
        });

        // 测试4: ECDH共享密钥
        $this->runTest('ECC-ECDH共享密钥', function() {
            // 生成两个密钥对
            $alice = new ECCCrypto(null, null, 'prime256v1', '', false);
            $bob = new ECCCrypto(null, null, 'prime256v1', '', false);

            // 交换公钥并计算共享密钥
            $alicePublic = $alice->exportPublicKey();
            $bobPublic = $bob->exportPublicKey();

            $aliceShared = $alice->computeSharedSecret($bobPublic);
            $bobShared = $bob->computeSharedSecret($alicePublic);

            echo "Alice共享密钥: " . bin2hex(substr($aliceShared, 0, 16)) . "...\n<br/>";
            echo "Bob共享密钥: " . bin2hex(substr($bobShared, 0, 16)) . "...\n<br/>";

            $result = $aliceShared === $bobShared;
            echo "共享密钥匹配: " . ($result ? "成功" : "失败") . "\n<br/>";

            return $result;
        });

        // 测试5: 密钥导出导入
        $this->runTest('ECC-密钥导出导入', function() {
            $original = new ECCCrypto(null, null, 'prime256v1', '', true);

            $privateKey = $original->exportPrivateKey();
            $publicKey = $original->exportPublicKey();

            echo "私钥导出成功: " . strlen($privateKey) . " 字节\n<br/>";
            echo "公钥导出成功: " . strlen($publicKey) . " 字节\n<br/>";

            // 从导出的密钥创建新实例
            $restored = new ECCCrypto($privateKey, $publicKey, 'prime256v1', '', true);

            $testData = "测试密钥导出导入";
            $signature = $original->sign($testData);
            $verified = $restored->verify($testData, $signature);

            echo "密钥恢复验证: " . ($verified ? "成功" : "失败") . "\n<br/>";

            return $verified;
        });

        // 测试6: 带时间戳的签名
        $this->runTest('ECC-带时间戳签名', function() {
            $ecc = new ECCCrypto(null, null, 'prime256v1', '', true);

            $data = "带时间戳的数据";
            $signedPackage = $ecc->signWithTimestamp($data);

            echo "带时间戳签名生成成功\n<br/>";
            echo "时间戳: {$signedPackage['timestamp']}\n<br/>";

            $verified = $ecc->verifyWithTimestamp(
                $data,
                $signedPackage['signature'],
                $signedPackage['timestamp']
            );

            echo "带时间戳验证: " . ($verified ? "成功" : "失败") . "\n<br/>";

            return $verified;
        });

        // 测试7: JWK格式导出
        $this->runTest('ECC-JWK格式导出', function() {
            $ecc = new ECCCrypto(null, null, 'prime256v1', '', true);

            $jwkPrivate = $ecc->exportAsJWK(true);
            $jwkPublic = $ecc->exportAsJWK(false);

            echo "JWK私钥包含字段: " . implode(', ', array_keys($jwkPrivate)) . "\n<br/>";
            echo "JWK公钥包含字段: " . implode(', ', array_keys($jwkPublic)) . "\n<br/>";
            echo "曲线: {$jwkPrivate['crv']}\n<br/>";

            return isset($jwkPrivate['d']) && !isset($jwkPublic['d']);
        });

        // 测试8: 批量文件签名
        $this->runTest('ECC-批量文件签名', function() {
            $ecc = new ECCCrypto(null, null, 'prime256v1', '', true);

            $files = [
                $this->testDataDir . '/small.txt',
                $this->testDataDir . '/medium.txt'
            ];

            $results = $ecc->signFiles($files);

            $successCount = 0;
            foreach ($results as $file => $result) {
                echo "文件 {$file}: " . ($result['success'] ? "成功" : "失败") . "\n<br/>";
                if ($result['success']) {
                    $successCount++;
                }
            }

            return $successCount === count($files);
        });

        // 测试9: 证书操作
        $this->runTest('ECC-证书操作', function() {
            $ecc = new ECCCrypto(null, null, 'prime256v1', '', true);

            // 生成CSR
            $dn = [
                'countryName' => 'CN',
                'stateOrProvinceName' => 'Beijing',
                'localityName' => 'Beijing',
                'organizationName' => 'Test Company',
                'commonName' => 'test.example.com'
            ];

            $csr = $ecc->generateCSR($dn);
            echo "CSR生成成功: " . strlen($csr) . " 字节\n<br/>";

            // 这里通常需要CA签名生成证书，我们只测试CSR生成
            return !empty($csr);
        });

        // 测试10: 性能测试
        $this->runTest('ECC-性能测试', function() {
            $ecc = new ECCCrypto(null, null, 'prime256v1', '', false);

            $performance = $ecc->testKeyStrength();

            echo "曲线: {$performance['curve_name']}\n<br/>";
            echo "签名速度: {$performance['sign_speed']}\n<br/>";
            echo "验证速度: {$performance['verify_speed']}\n<br/>";
            echo "性能评级: {$performance['performance_rating']}\n<br/>";

            return !empty($performance['performance_rating']);
        });

        echo "\n<br/>";
    }

    /**
     * RSA加密测试
     */
    private function testRSACrypto(): void
    {
        echo "=== RSA加密测试 ===\n<br/>";

        // 测试1: 基本加密解密
        $this->runTest('RSA-基本加密解密', function() {
            $rsa = new RSACrypto(null, null, 2048, 'sha256', true);

            $originalData = "Hello, RSA Encryption! 这是RSA测试数据。";
            $encrypted = $rsa->encrypt($originalData);
            $decrypted = $rsa->decrypt($encrypted);

            echo "原始数据: {$originalData}\n<br/>";
            echo "加密数据: " . substr($encrypted, 0, 50) . "...\n<br/>";
            echo "解密数据: {$decrypted}\n<br/>";
            echo "测试结果: " . ($originalData === $decrypted ? "成功" : "失败") . "\n<br/>";

            return $originalData === $decrypted;
        });

        // 测试2: 不同密钥长度
        $this->runTest('RSA-不同密钥长度', function() {
            $keySizes = [2048, 3072];
            $testData = "测试不同RSA密钥长度";

            foreach ($keySizes as $keySize) {
                $rsa = new RSACrypto(null, null, $keySize, 'sha256', false);

                $encrypted = $rsa->encrypt($testData);
                $decrypted = $rsa->decrypt($encrypted);

                if ($testData !== $decrypted) {
                    echo "密钥长度 {$keySize} 测试失败\n<br/>";
                    return false;
                }
                echo "密钥长度 {$keySize} 测试成功\n<br/>";
            }
            return true;
        });

        // 测试3: 不同填充方式
        $this->runTest('RSA-不同填充方式', function() {
            $rsa = new RSACrypto(null, null, 2048, 'sha256', true);
            $testData = "测试不同填充方式";

            $paddings = [
                OPENSSL_PKCS1_PADDING => 'PKCS1',
                OPENSSL_PKCS1_OAEP_PADDING => 'OAEP'
            ];

            foreach ($paddings as $padding => $name) {
                $encrypted = $rsa->encrypt($testData, $padding);
                $decrypted = $rsa->decrypt($encrypted, $padding);

                if ($testData !== $decrypted) {
                    echo "填充方式 {$name} 测试失败\n<br/>";
                    return false;
                }
                echo "填充方式 {$name} 测试成功\n<br/>";
            }
            return true;
        });

        // 测试4: 文件加密解密
        $this->runTest('RSA-文件加密解密', function() {
            $rsa = new RSACrypto(null, null, 2048, 'sha256', true);

            $inputFile = $this->testDataDir . '/small.txt';
            $encryptedFile = $this->testDataDir . '/small_encrypted.rsa';
            $decryptedFile = $this->testDataDir . '/small_decrypted_rsa.txt';

            // 加密文件
            $success = $rsa->encryptFile($inputFile, $encryptedFile);
            if (!$success) {
                echo "RSA文件加密失败\n<br/>";
                return false;
            }
            echo "RSA文件加密成功: " . filesize($encryptedFile) . " 字节\n<br/>";

            // 解密文件
            $success = $rsa->decryptFile($encryptedFile, $decryptedFile);
            if (!$success) {
                echo "RSA文件解密失败\n<br/>";
                return false;
            }
            echo "RSA文件解密成功: " . filesize($decryptedFile) . " 字节\n<br/>";

            // 验证内容
            $original = file_get_contents($inputFile);
            $decrypted = file_get_contents($decryptedFile);

            $result = $original === $decrypted;
            echo "RSA文件内容验证: " . ($result ? "成功" : "失败") . "\n<br/>";

            // 清理
            unlink($encryptedFile);
            unlink($decryptedFile);

            return $result;
        });

        // 测试5: 签名验证
        $this->runTest('RSA-签名验证', function() {
            $rsa = new RSACrypto(null, null, 2048, 'sha256', true);

            $data = "需要RSA签名的数据";
            $signature = $rsa->sign($data);

            echo "RSA签名成功\n<br/>";
            echo "签名: " . substr($signature, 0, 30) . "...\n<br/>";

            $verified = $rsa->verify($data, $signature);
            echo "RSA签名验证: " . ($verified ? "成功" : "失败") . "\n<br/>";

            return $verified;
        });

        // 测试6: 文件签名验证
        $this->runTest('RSA-文件签名验证', function() {
            $rsa = new RSACrypto(null, null, 2048, 'sha256', true);

            $filePath = $this->testDataDir . '/medium.txt';
            $signature = $rsa->signFile($filePath);

            echo "RSA文件签名成功\n<br/>";

            $verified = $rsa->verifyFile($filePath, $signature);
            echo "RSA文件签名验证: " . ($verified ? "成功" : "失败") . "\n<br/>";

            return $verified;
        });

        // 测试7: 密钥导出导入
        $this->runTest('RSA-密钥导出导入', function() {
            $original = new RSACrypto(null, null, 2048, 'sha256', true);

            $privateKey = $original->exportPrivateKey();
            $publicKey = $original->exportPublicKey();

            echo "RSA私钥导出成功: " . strlen($privateKey) . " 字节\n<br/>";
            echo "RSA公钥导出成功: " . strlen($publicKey) . " 字节\n<br/>";

            // 从导出的密钥创建新实例
            $restored = new RSACrypto($privateKey, $publicKey, 2048, 'sha256', true);

            $testData = "测试RSA密钥导出导入";
            $signature = $original->sign($testData);
            $verified = $restored->verify($testData, $signature);

            echo "RSA密钥恢复验证: " . ($verified ? "成功" : "失败") . "\n<br/>";

            return $verified;
        });

        // 测试8: 加密并签名
        $this->runTest('RSA-加密并签名', function() {
            $alice = new RSACrypto(null, null, 2048, 'sha256', true);
            $bob = new RSACrypto(null, null, 2048, 'sha256', true);

            $data = "Alice给Bob的加密消息";
            $package = $alice->encryptAndSign($data, $bob);

            echo "RSA加密并签名成功\n<br/>";
            echo "数据包包含: " . implode(', ', array_keys($package)) . "\n<br/>";

            $decrypted = $bob->decryptAndVerify($package, $alice);
            echo "RSA解密并验证: " . ($data === $decrypted ? "成功" : "失败") . "\n<br/>";

            return $data === $decrypted;
        });

        // 测试9: 批量文件操作
        $this->runTest('RSA-批量文件加密', function() {
            $rsa = new RSACrypto(null, null, 2048, 'sha256', true);

            $files = [
                $this->testDataDir . '/small.txt',
                $this->testDataDir . '/medium.txt'
            ];

            $outputDir = $this->testDataDir . '/rsa_batch_encrypted';
            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            $results = $rsa->encryptFiles($files, $outputDir);

            $successCount = 0;
            foreach ($results as $file => $result) {
                echo "RSA文件 {$file}: " . ($result['success'] ? "加密成功" : "加密失败") . "\n<br/>";
                if ($result['success']) {
                    $successCount++;
                }
            }

            // 清理
            $this->deleteDirectory($outputDir);

            return $successCount === count($files);
        });

        // 测试10: 性能测试
        $this->runTest('RSA-性能测试', function() {
            $rsa = new RSACrypto(null, null, 2048, 'sha256', false);

            $performance = $rsa->testKeyStrength();

            echo "RSA密钥长度: {$performance['key_size']}\n<br/>";
            echo "加密速度: {$performance['encrypt_speed']}\n<br/>";
            echo "解密速度: {$performance['decrypt_speed']}\n<br/>";
            echo "性能评级: {$performance['performance_rating']}\n<br/>";

            return !empty($performance['performance_rating']);
        });

        // 测试11: 密钥指纹
        $this->runTest('RSA-密钥指纹', function() {
            $rsa = new RSACrypto(null, null, 2048, 'sha256', true);

            $fingerprint = $rsa->getKeyFingerprint();

            echo "RSA密钥指纹: {$fingerprint}\n<br/>";
            echo "指纹长度: " . strlen($fingerprint) . " 字符\n<br/>";

            return !empty($fingerprint) && strlen($fingerprint) === 64;
        });

        // 测试12: 加密信息摘要
        $this->runTest('RSA-加密信息摘要', function() {
            $rsa = new RSACrypto(null, null, 2048, 'sha256', true);

            $info = $rsa->getCipherInfo();

            echo "RSA加密信息:\n<br/>";
            foreach ($info as $key => $value) {
                if (is_array($value)) {
                    echo "  {$key}: [" . implode(', ', $value) . "]\n<br/>";
                } else {
                    echo "  {$key}: {$value}\n<br/>";
                }
            }

            return !empty($info) && $info['has_private_key'] && $info['has_public_key'];
        });

        echo "\n<br/>";
    }

    /**
     * 运行单个测试
     */
    private function runTest(string $testName, callable $testFunction): void
    {
        echo "--- {$testName} ---\n<br/>";

        try {
            $result = $testFunction();
            $this->testResults[$testName] = $result;
            echo "结果: " . ($result ? "✓ 通过" : "✗ 失败") . "\n<br/>";
        } catch (Exception $e) {
            $this->testResults[$testName] = false;
            echo "异常: " . $e->getMessage() . "\n<br/>";
            echo "结果: ✗ 失败（异常）\n<br/>";
        }

        echo "\n<br/>";
    }

    /**
     * 删除目录
     */
    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * 打印测试摘要
     */
    private function printTestSummary(): void
    {
        echo "=== 测试摘要 ===\n<br/>";

        $totalTests = count($this->testResults);
        $passedTests = count(array_filter($this->testResults));
        $failedTests = $totalTests - $passedTests;

        echo "总测试数: {$totalTests}\n<br/>";
        echo "通过测试: {$passedTests}\n<br/>";
        echo "失败测试: {$failedTests}\n<br/>";
        echo "通过率: " . round(($passedTests / $totalTests) * 100, 2) . "%\n<br/>\n<br/>";

        echo "详细结果:\n<br/>";
        foreach ($this->testResults as $testName => $result) {
            $status = $result ? "✓ 通过" : "✗ 失败";
            echo "  {$testName}: {$status}\n<br/>";
        }
    }
}

// 运行测试套件
try {
    $testSuite = new CryptoTestSuite();
    $testSuite->runAllTests();
} catch (Exception $e) {
    echo "测试套件运行失败: " . $e->getMessage() . "\n<br/>";
}
