<?php

declare(strict_types=1);

namespace zxf\Utils\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Foundation\Console\AboutCommand;
use Composer\InstalledVersions;

class UtilsServiceProvider extends ServiceProvider
{
    /**
     * 启动服务
     */
    public function boot(): void
    {
        // 发布配置文件到项目配置目录
        $this->publishes([
            __DIR__ . '/../../config/oauth2.php' => config_path('oauth2.php'),
        ], ['utils-config']);

        // 把 zxf/utils 添加到 about 命令中
        // 包未登记进 Composer 的 installed 数据（如 path 仓库、本地开发）时，
        // getPrettyVersion() 会抛 OutOfBoundsException，导致 `about` 命令整体失败，
        // 因此先判断再取值。
        AboutCommand::add('Extend', [
            'zxf/utils' => fn () => InstalledVersions::isInstalled('zxf/utils')
                ? (InstalledVersions::getPrettyVersion('zxf/utils') ?? 'unknown')
                : 'unknown',
        ]);
    }

    /**
     * 注册服务
     */
    public function register(): void
    {
        //
    }
}