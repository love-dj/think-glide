<?php
declare (strict_types = 1);

namespace think;

use League\Glide\Server;
use League\Glide\ServerFactory;
use League\Glide\Signatures\SignatureFactory;
use League\Glide\Urls\UrlBuilderFactory;
use Symfony\Component\OptionsResolver\OptionsResolver;

class GlideMiddleware
{
    /**
     * @var array
     */
    protected array $options;

    /**
     * @var array
     */
    protected array $query;

    public function __construct(array $options = [])
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults([
            'baseUrl'     => '/images',
            'cache'       => runtime_path('glide'),
            'cacheTime'   => '+1 day',
            'signKey'     => false,
            'glide'       => [],
            'onException' => function (\Exception $exception) {
                throw $exception;
            },
        ]);
        $resolver->setRequired('source');

        $this->options = $resolver->resolve($options);

        //如果启动安全校验，需要注入服务
        if ($this->options['signKey']) {
            $urlBuilder = UrlBuilderFactory::create($this->options['baseUrl'], $this->options['signKey']);
            (new Container)->bind('glide.url_builder', $urlBuilder);
        }
    }

    /**
     * @throws \League\Flysystem\FilesystemException
     */
    public function __invoke(Request $request, $next)
    {
        $uri = urldecode(request()->pathinfo());
        parse_str($request->query(), $this->query);

        if (!preg_match("#^{$this->options['baseUrl']}#", '/' . $uri)) {
            return $next($request);
        }

        $server = $this->createGlideServer();
        try {
            //检查安全签名
            $this->checkSignature($uri);
            $response = $this->handleRequest($server, $request);
        } catch (\Exception $exception) {
            $response = call_user_func($this->options['onException'], $exception, $request, $server);
        }
        return $response;
    }

    /**
     * @param \League\Glide\Server $server
     * @param \think\Request       $request
     * @return \think\Response
     * @throws \League\Flysystem\FilesystemException
     * @throws \League\Glide\Filesystem\FileNotFoundException
     */
    protected function handleRequest(Server $server, Request $request): Response
    {
        //检查是否重新更新了
        $modifiedTime = null;
        if ($this->options['cacheTime']) {
            $modifiedTime = $server->getSource()
                ->lastModified($server->getSourcePath($request->pathinfo()));
            $response = $this->applyModified($modifiedTime, $request);
            if (false !== $response) {
                return $response;
            }
        }

        //如果已经更新了重新从缓存拉取图像
        if (null === $server->getResponseFactory()) {
            $server->setResponseFactory(new GlideFactory());
        }
        $response = $server->getImageResponse($request->pathinfo(), $this->query);

        return $this->applyCacheHeaders($response, $modifiedTime);
    }

    protected function applyCacheHeaders($response, $modifiedTime)
    {
        $expire = strtotime($this->options['cacheTime']);
        $maxAge = $expire - time();

        return $response
            ->header([
                'Cache-Control' => 'public,max-age=' . $maxAge,
                'Date'          => gmdate('D, j M Y G:i:s \G\M\T', time()),
                'Last-Modified' => gmdate('D, j M Y G:i:s \G\M\T', (int) $modifiedTime),
                'Expires'       => gmdate('D, j M Y G:i:s \G\M\T', $expire),
            ]);
    }

    /**
     * @param int     $modifiedTime
     * @param Request $request
     *
     * @return false|Response
     */
    protected function applyModified(int $modifiedTime, Request $request)
    {
        //如果没有修改直接返回
        if ($this->isNotModified($request, $modifiedTime)) {
            $response = response('', 304);

            return $this->applyCacheHeaders($response, $modifiedTime);
        }

        return false;
    }

    /**
     * @param Request $request
     * @param $modifiedTime
     *
     * @return bool
     */
    protected function isNotModified(Request $request, $modifiedTime): bool
    {
        $modifiedSince = $request->header('If-Modified-Since');

        if (!$modifiedSince) {
            return false;
        }

        return strtotime($modifiedSince) === (int) $modifiedTime;
    }

    /**
     * @param string $uri
     *
     * @throws \League\Glide\Signatures\SignatureException
     */
    protected function checkSignature(string $uri): void
    {
        if (!$this->options['signKey']) {
            return;
        }
        SignatureFactory::create($this->options['signKey'])->validateRequest(
            $uri,
            $this->query
        );
    }

    /**
     * @return \League\Glide\Server
     */
    protected function createGlideServer(): Server
    {
        return ServerFactory::create(array_merge([
            'source'   => $this->options['source'],
            'cache'    => $this->options['cache'],
            'base_url' => $this->options['baseUrl'],
        ], $this->options['glide']));
    }
}
