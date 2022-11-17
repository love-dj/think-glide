<?php
declare (strict_types = 1);

namespace think;

use League\Flysystem\FilesystemInterface;
use League\Glide\Responses\ResponseFactoryInterface;

class GlideFactory implements ResponseFactoryInterface
{
    /**
     * Create response.
     *
     * @param \League\Flysystem\FilesystemInterface $cache
     * @param                                    $path
     * @return \think\Response
     * @throws \League\Flysystem\FilesystemException
     */
    public function create(FilesystemInterface $cache, $path): Response
    {
        $contentType   = $cache->getMimetype($path);
        $contentLength = $cache->getSize($path);
        return response(stream_get_contents($cache->readStream($path)), 200, [
            'Content-Type'   => $contentType,
            'Content-Length' => $contentLength,
        ]);
    }
}
