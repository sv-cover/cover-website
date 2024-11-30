<?php

namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpClient\ScopingHttpClient;

class Secretary
{
    private HttpClientInterface $client;

    const FIELDS_MAP = [
        'id' => 'id',
        'voornaam' => 'first_name',
        'tussenvoegsel' => 'family_name_preposition',
        'achternaam' => 'family_name',
        'adres' => 'street_name',
        'postcode' => 'postal_code',
        'woonplaats' => 'place',
        'email' => 'email_address',
        'telefoonnummer' => 'phone_number',
        'beginjaar' => 'membership_year_of_enrollment',
        'geboortedatum' => 'birth_date',
        'geslacht' => 'gender',
        'iban' => 'iban',
        'bic' => 'bic',
        'member_from' => 'membership_started_on',
        'member_till' => 'membership_ended_on',
        'donor_from' => 'donorship_date_of_authorization',
        'donor_till' => 'donorship_ended_on',
    ];

    public function __construct(
        CacheInterface $cache,
        HttpClientInterface $client,
        private string $url,
        string $user,
        string $password,
    ) {
        $callback = function (ItemInterface $item) use ($client, $user, $password): string {
            return $this->getToken($client, $user, $password);
        };

        $token = $cache->get('secretary_token', $callback);

        if (!$this->isValidToken($client, $token)) {
            $cache->delete('secretary_token');
            $token = $cache->get('secretary_token', $callback);
        }

        list($usr, $tkn) = explode(':', $token, 2);

        $this->client = ScopingHttpClient::forBaseUri($client, $this->url, [
            // 'auth_basic' => explode(':', $token, 2),
            'query' => [
                'user' => $usr,
                'token' => $tkn,
            ],
        ]);
    }

    private function getToken(HttpClientInterface $client, string $user, string $password): string
    {
        $response = $client->request('POST', $this->url . 'token/new.json', [
            'body' => [
                'username' => $user,
                'password' => $password
            ],
        ]);

        $data = $response->toArray();

        if (isset($data['detail']))
            throw new \RuntimeException('Could not request new token: ' . $data['detail']);

        return sprintf('%d:%s', $data['user'], $data['token']);
    }

    private function isValidToken(HttpClientInterface $client, string $userTokenPair): bool
    {
        if (empty($userTokenPair) || strpos($userTokenPair, ':') === false)
            return false;

        list($user, $token) = explode(':', $userTokenPair, 2);


        $response = $client->request('GET', sprintf('%stoken/%s/%d.json', $this->url, $token, $user));

        return (bool) $response->toArray()['success'];
    }

    public function createPerson(array $data): array
    {
        $response = $this->client->request('POST', 'persons/new.json', [
            'json' => $data,
        ]);
        return $response->toArray();
    }

    public function findPerson(int $person_id): array
    {
        $response = $this->client->request('GET', 'persons/all.json?id=' . $person_id);
        return $response->toArray();
    }

    public function updatePerson(int $person_id, array $data): array
    {
        $response = $this->client->request('POST', sprintf('persons/%d.json', $person_id), [
            'json' => $data,
        ]);
        return $response->toArray();
    }

    public function updatePersonFromIterChanges(\DataIterMember $iter): array
    {
        if (!$iter->has_id())
            throw new \InvalidArgumentException('You can only submit updates for iters that have an id');

        if (!$iter->has_secretary_changes())
            return null;

        $data = [];

        foreach ($iter->secretary_changed_values() as $field => $value)
            $data[self::FIELDS_MAP[$field]] = $value;

        return $this->updatePerson($iter->get_id(), $data);
    }
}
