<?php

namespace Indexer\Renderer;

use JonnyW\PhantomJs\Client;

class ExternalRenderer implements \Indexer\Renderer {

    protected $url;

    function __construct($url) {
        $this->url = $url;
    }

    function render($url, $content) {
        return file_get_contents($this->url.'?url='.urlencode($url));
    }

}