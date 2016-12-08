<?php

namespace Indexer\Renderer;

use JonnyW\PhantomJs\Client;

class PhantomJSRenderer implements \Indexer\Renderer {

    protected $bin;

    function __construct($bin) {
        $this->bin = $bin;
    }

    function render($url, $content) {
        $client = Client::getInstance();
        $client->getEngine()->setPath($this->bin);
        $request = $client->getMessageFactory()->createRequest($url, 'GET');
        $response = $client->getMessageFactory()->createResponse();
        $client->send($request, $response);

        if ($response->getStatus() === 200)
            return $response->getContent();
        else
            return $content;
    }

}