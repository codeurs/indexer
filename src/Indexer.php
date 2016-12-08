<?php

namespace Indexer;

use JonnyW\PhantomJs\Client;
use Masterminds\HTML5;

class Indexer {

    const EXT = '.cached.json';

    protected $cacheDir;
    protected $options;
    protected $url;
    protected $cached;
    protected $backend;

    function __construct($options) {
        $this->options = (object) $options;
        $this->cacheDir = isset($this->options->cacheDir) 
            ? rtrim($this->options->cacheDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR 
            : 'cache' . DIRECTORY_SEPARATOR;
        $this->backend = $this->options->backend;
        if (!isset($this->options->extractTags))
            $this->options->extractTags = ['Indexer\\TagExtractor', 'extract'];
        if (!isset($this->options->extract))
            $this->options->extract = function($content) {
                return [];
            };
    }

    function serveCache($url = null) {
        $url = $url == null ? $this->getCurrentUrl() : $url;
        $cached = $this->cached($url);
        try {
            if (!is_readable($cached)) return false;
            $fp = fopen($cached, 'r');
            if (flock($fp, LOCK_EX | LOCK_NB, $blocks)) {
                fclose($fp);
                $stat = stat($cached);
                if ($stat['size'] > 0) {
                    readfile($cached);
                    return true;
                }
            }
        } catch (\Exception $e) {}

        return false;
    }

    function respondAndIndex($language, $content, $url = null) {
        $this->close($content);
        $url = $url == null ? $this->getCurrentUrl() : $url;
        $cached = $this->cached($url);
        if (!file_exists($this->cacheDir))
            mkdir($this->cacheDir, 0775, true);
        if (file_exists($cached)) return;
        $fp = fopen($cached, 'w');
        if (flock($fp, LOCK_EX | LOCK_NB, $blocks)) {
            if (isset($this->options->renderer))
                $content = $this->options->renderer->render($url, $content);
            fwrite($fp, $content);
            fclose($fp);
            chmod($cached, 0775);
            $this->index($language, $content, $url);
        }
    }

    function autoComplete($language, $query) {
        return $this->backend->autoComplete($language, $query);
    }

    function search($language, $query, $page) {
        $limit = 380;
        $result = $this->backend->search($language, $query, $page);
        foreach ($result->results as $row) {
            $content = $row->body;
            $pos = strpos($content, $query);
            $start = 0;
            if ($pos !== false) {
                $start = ceil($pos - $limit/2);
                if ($start < 0) $start = 0;
            }
            $row->body = substr($content, $start, $limit);
            $row->body = substr($row->body, strpos($row->body, ' '));
            $row->body = substr($row->body, 0, strrpos($row->body, ' '));
        }
        return $result;
    }

    function clearCache() {
        $files = glob($this->cacheDir.'*'.self::EXT);
        foreach($files as $file)
            if(is_file($file))
                unlink($file);
    }
    
    protected function getCurrentUrl() {
        $pageURL = 'http';
        if (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == "on")
            $pageURL .= "s";
        $pageURL .= "://";
        $pageURL .= $_SERVER["SERVER_NAME"].strtok($_SERVER["REQUEST_URI"], '?');
        return $pageURL;
    }

    protected function close($response) {
        if (function_exists('fastcgi_finish_request')) {
            print($response);
            fastcgi_finish_request();
        } else {
            function length($content) {
                return ini_get('mbstring.func_overload') ? mb_strlen($content , '8bit') : strlen($content);
            }
            header('Connection: close');
            header('Content-Length: ' . length($response));
            print($response);
            ignore_user_abort(true);
            ob_end_flush();
            flush();
        }
    }

    protected function cached($url) {
        return $this->cacheDir . md5($url) . self::EXT;
    }

    protected function index($language, $html, $url) {
        $path = null;
        if (isset($this->options->selector))
            $path = $this->options->selector;

        $doc = (new HTML5())->loadHTML($html);
        $title = qp($doc, 'title')->text();
        if (isset($this->options->breadcrumbs)) {
            $breadcrumbs = qp($doc, $this->options->breadcrumbs)->innerHTML();
            qp($doc, $this->options->breadcrumbs)->html(' ');
        }
        $content = qp($doc, 'body')->text();
        if ($path != null) {
            $el = qp($doc, $path);
            if (count($el)) {
                $content = $el->text();
            }
        }
        $tags = call_user_func($this->options->extractTags, $content);
        $fields = call_user_func($this->options->extract, $doc);
        $this->backend->index($url, $language, $title, $content, $tags, $fields);
    }

}