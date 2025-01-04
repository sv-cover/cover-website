<?php

namespace App\DataModel;

use App\DataIter\DataIterWiki;
use App\Legacy\Authentication\Authentication;
use App\Legacy\Database\DataModel;
use App\Legacy\Database\SearchProviderInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

class WikiRemoteCallException extends \Exception
{
    //
}

// TODO SFY: Wiki service
class DataModelWiki extends DataModel implements SearchProviderInterface
{
    public string $dataiter = DataIterWiki::class;

    public function __construct(
        private Authentication $auth,
        public ContainerBagInterface $params,
    ) {
    }

    public static function getName(): string
    {
        return __('wiki pages');
    }

    public function isConfigured()
    {
        return $this->params->get('wiki_url') != null;
    }

    public function search(string $query, ?int $limit = null): array
    {
        try {
            if (!$this->isConfigured())
                throw new WikiRemoteCallException('Wiki data model is not configured', 1);

            $results = $this->_call('dokuwiki.search', [$query]);

            $iters = [];

            foreach ($results as $result)
                $iters[] = new DataIterWiki(null, $result['id'], $result);

            return $iters;
        } catch (WikiRemoteCallException $e) {
            return [];
        }
    }

    private function _encodeMethodCall($method, array $args)
    {
        $body = new DOMDocument('1.0', 'UTF-8');
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

    private function _decodeMethodResponse($xml)
    {
        try {
            $doc = new \SimpleXMLElement($xml);

            $value = $doc->params[0]->param[0]->value[0]->children()[0];

            return $this->_decodeValue($value);
        } catch (\Exception $e) {
            throw new WikiRemoteCallException('Could not decode response', 2, $e);
        }
    }

    private function _decodeValue(\SimpleXMLElement $element)
    {
        switch ($element->getName())
        {
            case 'array':
                return $this->_decodeArray($element);

            case 'struct':
                return $this->_decodeStruct($element);

            case 'string':
                return $this->_decodeString($element);

            case 'int':
                return $this->_decodeInt($element);

            default:
                throw new \RuntimeException('Not implemented: ' . $element->getName());
        }
    }

    private function _decodeArray(\SimpleXMLElement $element)
    {
        $elements = [];

        foreach ($element->data[0]->value as $value)
            $elements[] = $this->_decodeValue($value->children()[0]);

        return $elements;
    }

    private function _decodeStruct(\SimpleXMLElement $element)
    {
        $data = [];

        foreach ($element->member as $member)
        {
            $name = strval($member->name[0]);
            $value = $this->_decodeValue($member->value[0]->children()[0]);
            $data[$name] = $value;
        }

        return $data;
    }

    private function _decodeString(\SimpleXMLElement $element)
    {
        return strval($element);
    }

    private function _decodeInt(\SimpleXMLElement $element)
    {
        return intval(strval($element));
    }

    private function _call($method, array $args)
    {
        $body = $this->_encodeMethodCall($method, $args);

        $headers = ['Content-Type: text/xml'];

        // If we are logged in, carry that session over to the wiki to access restricted pages
        if ($this->auth->auth->get_session())
            $headers[] = sprintf('Cookie: cover_session_id=%s', $this->auth->auth->get_session()->get('session_id'));

        $opts = [
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $body
            ]
        ];

        $context = stream_context_create($opts);

        $response = file_get_contents($this->params->get('wiki_url'), false, $context);

        return $this->_decodeMethodResponse($response);
    }
}
