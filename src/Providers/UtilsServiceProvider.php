<?php

namespace zxf\Utils\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Foundation\Console\AboutCommand;
use Composer\InstalledVersions;

class UtilsServiceProvider extends ServiceProvider
{
    /**
     * 服务提供者是否延迟加载
     */
    protected bool $defer = false;

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
        AboutCommand::add('Extend', [
            'zxf/utils' => fn () => InstalledVersions::getPrettyVersion('zxf/utils'),
        ]);
    }
    /**
     * 注册服务
     */
    public function register(): void
    {

    }
}