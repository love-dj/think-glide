<?php

declare(strict_types=1);

namespace think\middleware;

use Closure;
use think\GlideMiddleware;

class Glide
{
    /**
     * 图片缩放中间件
     * @param          $request
     * @param \Closure $next
     * @return mixed
     * @throws \League\Flysystem\FilesystemException
     */
    public function handle($request, Closure $next)
    {
        $config     = config('glide');
        $middleware = new GlideMiddleware($config);
        return $middleware($request, $next);
    }
}
