<?php
use think\Request;

return [
    // 本地图片文件夹的位置
    'source' => root_path('uploads'),
    // 路由前缀，匹配到该前缀时中间件开始执行
    'baseUrl' => '/images',
    // 缓存文件位置
    'cache' => runtime_path('glide'),
    // 缓存时间，示例 +2 days, 缓存期间多次请求会自动响应 304
    'cacheTime' => '+1 day',
    // 安全签名
    'signKey' => false,
    'glide' => [],
    // 异常处理handler
    'onException' => function (\Exception $exception, Request $request) {
        if ($exception instanceof \League\Glide\Signatures\SignatureException) {
            $response = response('签名错误', 403);
        } else {
            $response = response(sprintf('你访问的资源 "%s" 不存在', htmlspecialchars($request->pathinfo())), 404);
        }
        return $response;
    },
];
