<?php

namespace App\Bridge;

use App\DataIter\DataIterMember;
use App\Legacy\Authentication\Authentication;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpClient\ScopingHttpClient;

class Wiki
{
    private HttpClientInterface $client;

    public function __construct(
        private Authentication $auth,
        HttpClientInterface $client,
        private string $url,
        private string $pageUrl,
    ) {
        $this->client = ScopingHttpClient::forBaseUri($client, $url);
    }

    private function createRequestBody(string $method, array $args): string
    {
        $body = new \DOMDocument('1.0', 'UTF-8');
        $methodCall = $body->createElement('methodCall');
        $body->appendChild($methodCall);

        $methodName = $body->createElement('methodName', $method);
        $methodCall->appendChild($methodName);

        $params = $body->createElement('params');
        $methodCall->appendChild($params);

        foreach ($args as $arg) {
            $param = $body->createElement('param');
            $params->appendChild($param);

            $value = $body->createElement('value');
            $param->appendChild($value);

            if (is_int($arg))
                $value->appendChild($body->createElement('i4', $arg));
            elseif (is_string($arg))
                $value->appendChild($body->createElement('string', $arg));
            else
                throw new \Exception('Sorry, not implemented');
        }

        return $body->saveXML();
    }

    private function parseResponse(string $xml): mixed
    {
        try {
            $doc = new \SimpleXMLElement($xml);

            $value = $doc->params[0]->param[0]->value[0]->children()[0];

            return $this->parseValue($value);
        } catch (\Exception $exception) {
            throw new \Exception('Could not decode response', 2, $exception);
        }
    }

    private function parseValue(\SimpleXMLElement $element): mixed
    {
        switch ($element->getName())
        {
            case 'array':
                return $this->parseArray($element);

            case 'struct':
                return $this->parseStruct($element);

            case 'string':
                return $this->parseString($element);

            case 'int':
                return $this->parseInt($element);

            case 'dateTime.iso8601':
                return $this->parseDateTime($element);

            default:
                throw new \RuntimeException('Not implemented: ' . $element->getName());
        }
    }

    private function parseArray(\SimpleXMLElement $element): array
    {
        $elements = [];

        foreach ($element->data[0]->value as $value)
            $elements[] = $this->parseValue($value->children()[0]);

        return $elements;
    }

    private function parseStruct(\SimpleXMLElement $element): array
    {
        $data = [];

        foreach ($element->member as $member) {
            $name = strval($member->name[0]);
            $value = $this->parseValue($member->value[0]->children()[0]);
            $data[$name] = $value;
        }

        return $data;
    }

    private function parseString(\SimpleXMLElement $element): string
    {
        return strval($element);
    }

    private function parseInt(\SimpleXMLElement $element): int
    {
        return intval(strval($element));
    }

    private function parseDateTime(\SimpleXMLElement $element): \DateTime
    {
        return new \DateTime(strval($element));
    }

    private function request(string $method, array $params): array
    {
        $headers = [
            'Content-type' => 'text/xml',
        ];

        if ($this->auth->auth->get_session())
            $headers['Cookie'] = 'cover_session_id=' . $this->auth->auth->get_session()->get('session_id');

        $response = $this->client->request('POST', $this->url, [
            'headers' => $headers,
            'body' => $this->createRequestBody($method, $params),
        ]);

        return $this->parseResponse($response->getContent());
    }

    public function search(string $query): array
    {
        return $this->request('dokuwiki.search', [$query]);
    }

    public function getPageUrl(string $id): string
    {
        return strtr($this->pageUrl, ['{page_id}' => $id]);
    }
}
