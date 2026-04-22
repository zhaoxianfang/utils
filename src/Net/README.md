# Net 网络工具类

本目录提供网络相关的工具类，包含IP地址处理、域名解析与管理等功能。

## Ip - IP地址工具类

### 基础验证
```php
use zxf\Utils\Net\Ip;

Ip::isV4('192.168.1.1');        // true
Ip::isV6('::1');                // true
Ip::isValid('8.8.8.8');         // true
```

### 地址类型判断
```php
Ip::isLoopback('127.0.0.1');    // true
Ip::isPrivate('192.168.1.1');   // true
Ip::isPublic('8.8.8.8');        // true
Ip::isMulticast('224.0.0.1');   // true
Ip::isLinkLocal('169.254.1.1'); // true
Ip::isBroadcast('192.168.1.255', '255.255.255.0'); // true
```

### ABC类地址判断
```php
Ip::getClass('10.0.0.1');       // 'A'
Ip::getClass('172.16.0.1');     // 'B'
Ip::getClass('192.168.1.1');    // 'C'
Ip::isClassA('10.0.0.1');       // true
Ip::getDefaultMask('10.0.0.1'); // '255.0.0.0'
```

### CIDR与子网计算
```php
Ip::cidrInfo('192.168.1.0/24');
// ['network' => '192.168.1.0', 'broadcast' => '192.168.1.255', 'mask' => '255.255.255.0', 'hosts' => 254, ...]

Ip::inCidr('192.168.1.50', '192.168.1.0/24'); // true
Ip::rangeToCidr('192.168.1.0', '192.168.1.255'); // ['192.168.1.0/24']
Ip::getNetworkAddress('192.168.1.50', '255.255.255.0'); // '192.168.1.0'
```

### DNS与诊断
```php
Ip::resolve('example.com');     // ['93.184.216.34']
Ip::getHostname('8.8.8.8');     // 'dns.google'
Ip::isPortOpen('8.8.8.8', 53);  // true
```

### 本机网络信息
```php
Ip::getClientIp();              // 获取客户端真实IP
Ip::getLocalIp();               // 获取本机默认IP
Ip::getLocalIps();              // 获取所有网卡IP
Ip::getMacAddress('eth0');      // 获取MAC地址
```

### 综合信息
```php
Ip::info('8.8.8.8');
// 返回包含版本、类型、类别、长整型、二进制等完整信息数组
```

## Domain - 域名工具类

### 域名解析
```php
use zxf\Utils\Net\Domain;

Domain::isValid('example.com');           // true
Domain::getTld('www.example.co.uk');      // 'co.uk'
Domain::getRegisteredDomain('www.sub.example.com'); // 'example.com'
Domain::toAscii('中文域名.cn');            // xn--...格式
Domain::toUnicode('xn--...');             // 中文域名
```

### DNS记录查询
```php
Domain::resolve('example.com');           // A/AAAA记录
Domain::getMxRecords('example.com');      // MX记录
Domain::getTxtRecords('example.com');     // TXT记录
Domain::getNsRecords('example.com');      // NS记录
Domain::getSoaRecord('example.com');      // SOA记录
```

### WHOIS查询
```php
Domain::whois('example.com');
```
