<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpClient\ScopingHttpClient;

class Incassomatic
{
    private HttpClientInterface $client;

    public function __construct(
        HttpClientInterface $client,
        private string $url,
        private string $app,
        private string $secret,
    ) {
        $this->client = ScopingHttpClient::forBaseUri($client, $url);
    }

    private function request(string $method, string $url, array $params, array $data = []): array
    {
        $query = \http_build_query($params);
        $body = \http_build_query($data);

        $url .= ($query != '' ? ('?' . $query) : '');

        $response = $this->client->request($method, $url, [
            'headers' => [
                'Content-type' => 'application/x-www-form-urlencoded',
                'Date' => \gmdate('D, d M Y H:i:s T'),
                'Host' => \parse_url($this->url, PHP_URL_HOST),
                'X-App' => $this->app,
                'X-Hash' => \sha1($query . $body . $this->secret),
            ],
            'body' => $body,
        ]);

        return $response->toArray();
    }

    public function createContract(\DataIterMember $member) :array
    {
        $data = [
            'cover_id' => $member->get_id(),
            'start_datum' => \date('Y-m-d'),
            'method' => 'digital',
        ];
        return $this->request('POST', 'contracten/', [], $data);
    }

    public function getContracts(\DataIterMember $member): array
    {
        return $this->request('GET', 'contracten/', ['cover_id' => $member['id']]);
    }

    public function getCurrentContract(\DataIterMember $member): array|bool
    {
        $contracts = $this->getContracts($member);
        // Only show valid contracts
        return \current(\array_filter($contracts, fn($c) => $c['is_geldig']));
    }

    public function getDebits(\DataIterMember $member, ?int $limit = null) : array
    {
        $params = [
            'cover_id' => $member->get_id(),
        ];

        if ($limit !== null)
            $params['limit'] = $limit;

        return $this->request('GET', '', $params);
    }
}
