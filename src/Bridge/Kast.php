<?php

namespace App\Bridge;

use App\DataIter\DataIterMember;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpClient\ScopingHttpClient;

class Kast
{
    private HttpClientInterface $_client;

    public function __construct(
        private CacheInterface $cache,
        private HttpClientInterface $baseClient,
        private string $url,
        private string $user,
        private string $password,
    ) {
    }

    private function getClient(): HttpClientInterface
    {
        if (!isset($this->_client)) {
            $token = $this->cache->get('kast_token', function (ItemInterface $item): string {
                $token = $this->getToken();
                $item->expiresAt(new \DateTime($token['expiry']));
                return $token['token'];
            });

            $this->_client = ScopingHttpClient::forBaseUri($this->baseClient, $this->url, [
                'headers' => [
                    'Authorization' => 'Token ' . $token,
                ],
            ]);
        }
        return $this->_client;
    }

    private function getToken(): array
    {
        $response = $this->baseClient->request('POST', $this->url . 'auth/login/', [
            'auth_basic' => [$this->user, $this->password],
        ]);

        $data = $response->toArray();

        if (isset($data['detail']))
            throw new \RuntimeException('Could not request new token: ' . $data['detail']);

        return $data;
    }

    public function getAccount(DataIterMember $member): array
    {
        $response = $this->getClient()->request('GET', sprintf('users/%d/', $member['id']));
        return $response->toArray();
    }

    public function getStatus(DataIterMember $member): array
    {
        $response = $this->getClient()->request('GET', sprintf('users/%d/status/', $member['id']));
        return $response->toArray();
    }

    public function getHistory(DataIterMember $member, int $limit = 10): array
    {
        $response = $this->getClient()->request('GET', sprintf('users/%d/history/?limit=%d', $member['id'], $limit));
        return $response->toArray();
    }
}
