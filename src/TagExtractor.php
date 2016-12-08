<?php

namespace Indexer;

use TermExtractor\TermExtractor;

class TagExtractor {

    static function extract($content) {
        $tags = [];
        $content = iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $content);
        $extractor = new TermExtractor();
        $terms = $extractor->extract(html_entity_decode($content));
        foreach ($terms as $term_info) {
            list($term, $occurrence, $word_count) = $term_info;
            if ($word_count > 3) continue;
            $tag = str_replace(',', '', $term);
            $tag = strtolower($tag);
            $tag = preg_replace("/[^a-z0-9& ]/", '', $tag);
            $tag = preg_replace("/ (\d+) /", '', ' '.$tag.' ');
            $tag = trim($tag);
            if (!isset($tags[$tag]))
                $tags[$tag] = 0;
            $tags[$tag] += $occurrence;
        }
        return $tags;
    }
    
}