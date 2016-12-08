<?php

namespace Indexer;

interface Renderer {

    /**
     * @param string $url
     * @param string $content
     * @return string
     */
    function render($url, $content);

}