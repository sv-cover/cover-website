<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class Filemanager
{
    public function __construct(
        private HttpClientInterface $client,
        private string $url,
        private array $imageExtensions,
        private array $resizableExtensions,
    ) {
    }

    public function getFileUrl(string $path, ?int $width = null): string
    {
        if (empty($path))
            return '';

        if (!$width || !in_array(pathinfo($path, PATHINFO_EXTENSION), $this->resizableExtensions))
            return sprintf('%s/%s', $this->url, $path);

        return sprintf('%s/images/resize?f=%s&w=%d', $this->url, urlencode($path), $width);
    }

    public function getImageSize(string $path): ?array
    {
        if (empty($path) || !in_array(pathinfo($path, PATHINFO_EXTENSION), $this->resizableExtensions))
            return null; // Can't determine size

        try {
            $response = $this->client->request(
                'GET',
                sprintf('%s/images/size?f=%s', $this->url, urlencode($path))
            );
            $result = $response->toArray();
            return [$result['width'], $result['height']];
        } catch (\Exception $e) {
            return null;
        }
    }
}
