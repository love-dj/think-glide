<?php
declare (strict_types = 1);

namespace think;

use League\Flysystem\FilesystemReader;
use League\Glide\Responses\ResponseFactoryInterface;

class GlideFactory implements ResponseFactoryInterface
{
    /**
     * Create response.
     *
     * @param \League\Flysystem\FilesystemReader $cache
     * @param                                    $path
     * @return \think\Response
     * @throws \League\Flysystem\FilesystemException
     */
    public function create(FilesystemReader $cache, $path): Response
    {
        $contentType   = $cache->mimeType($path);
        $contentLength = $cache->fileSize($path);
        return response(stream_get_contents($cache->readStream($path)), 200, [
            'Content-Type'   => $contentType,
            'Content-Length' => $contentLength,
        ]);
    }
}
