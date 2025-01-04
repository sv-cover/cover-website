<?php

namespace App\Bridge;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpClient\ScopingHttpClient;

class Kast
{
    private HttpClientInterface $client;

    public function __construct(
        CacheInterface $cache,
        HttpClientInterface $client,
        string $url,
        string $user,
        string $password,
    ) {
        $token = $cache->get('kast_token', function (ItemInterface $item) use ($client, $url, $user, $password): string {
            $token = $this->getToken($client, $url, $user, $password);
            $item->expiresAt(new \DateTime($token['expiry']));
            return $token['token'];
        });

        $this->client = ScopingHttpClient::forBaseUri($client, $url, [
            'headers' => [
                'Authorization' => 'Token ' . $token,
            ],
        ]);
    }

    private function getToken(HttpClientInterface $client, string $url, string $user, string $password): array
    {
        $response = $client->request('POST', $url . 'auth/login/', [
            'auth_basic' => [$user, $password],
        ]);

        $data = $response->toArray();

        if (isset($data['detail']))
            throw new \RuntimeException('Could not request new token: ' . $data['detail']);

        return $data;
    }

    public function getAccount(\DataIterMember $member): array
    {
        $response = $this->client->request('GET', sprintf('users/%d/', $member['id']));
        return $response->toArray();
    }

    public function getStatus(\DataIterMember $member): array
    {
        $response = $this->client->request('GET', sprintf('users/%d/status/', $member['id']));
        return $response->toArray();
    }

    public function getHistory(\DataIterMember $member, int $limit = 10): array
    {
        $response = $this->client->request('GET', sprintf('users/%d/history/?limit=%d', $member['id'], $limit));
        return $response->toArray();
    }
}
