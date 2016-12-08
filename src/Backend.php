<?php

namespace Indexer;

interface Backend {

    /**
     * @param string $url
     * @param string $language
     * @param string $title
     * @param string $content
     * @param string $breadcrumbs
     * @return void
     */
	function index($url, $language, $title, $content, $tags, $fields);

    /**
     * @param string $language
     * @param string $query
     * @return string[]
     */
	function autoComplete($language, $query);

    /**
     * @param string $language
     * @param string $query
     * @param integer $page
     * @return mixed
     */
	function search($language, $query, $page);

}