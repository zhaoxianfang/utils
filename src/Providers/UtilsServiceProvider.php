<?php

declare(strict_types=1);

namespace zxf\Utils\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Foundation\Console\AboutCommand;
use Composer\InstalledVersions;

/**
 * zxf/utils 的 Laravel 服务提供者
 *
 * 负责将配置文件发布到应用配置目录，并在 Artisan `about` 命令中登记本包版本信息。
 *
 * @package zxf\Utils\Providers
 * @version 1.0.0
 * @license MIT
 */
class UtilsServiceProvider extends ServiceProvider
{
    /**
     * 启动服务
     *
     * 发布配置文件，并将 zxf/utils 版本登记到 `about` 命令的 Extend 分组。
     *
     * @return void
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
     *
     * 本包无需在容器中绑定任何服务，故此处为空实现。
     *
     * @return void
     */
    public function register(): void
    {
        //
    }
}