<?php

namespace Indexer\Backend;

class MysqlBackend implements \Indexer\Backend {

    const LIMIT = 50;
    protected $options;
    protected $connection;
    
    function __construct($options) {
        $this->options = (object) $options;
        if (!isset($this->options->table))
            throw new \Exception('Missing table in options');
    }

    function index($url, $language, $title, $content, $tags, $fields) {
        $db = $this->connection();
        $id = md5($url);
        $url = $db->real_escape_string($url);
        $language = $db->real_escape_string($language);
        $title = $db->real_escape_string($title);
        $body = $db->real_escape_string($content);
        
        $fieldValues = [];
        $fieldKeys = [];
        foreach ($fields as $k => $v) {
            $fieldValues[] = "'".$db->real_escape_string($v)."'";
            $fieldKeys[] = "`$k`";
        }
        $fields = count($fieldValues) > 0 ? ','.implode(',', $fieldValues) : '';
        $keys = count($fieldKeys) > 0 ? ','.implode(',', $fieldKeys) : '';

        $tagsEncoded = json_encode($tags);
        $this->remove($id);
        $db->query("
            insert 
            into `{$this->options->table}`(id, `language`, url, title, body, tags $keys)
            values ('{$id}', '{$language}', '{$url}', '{$title}', '{$body}', '{$tagsEncoded}' $fields)
        ");
        $this->insertTags($language, $tags);
    }

    function autoComplete($language, $query) {
        $db = $this->connection();
        $language = $db->real_escape_string($language);
        $query = $db->real_escape_string($query);
        $result = $db->query("
            select *
            from `{$this->options->table}_tags`
            where 
                tag like '%{$query}%' and 
                occurrence > 0 and
                language = '{$language}'
            order by 
                case 
                    when tag='$query' then 0
                    when tag like '$query%' then 1
                    when tag like '% $query %' then 2
                    when tag like '% $query' then 3
                    when tag like '% $query%' then 4
                    else 5
                end,
                occurrence
            limit 6
        ");
        $response = [];
        if ($result !== false)
            while (($row = $result->fetch_object()) !== null)
                $response[] = $row;
        return $response;
    }

    function search($language, $query, $page) {
        $db = $this->connection();
        $language = $db->real_escape_string($language);
        $query = $db->real_escape_string($query);
        $limit = ($page*self::LIMIT).', '.self::LIMIT;
        $result = $db->query("
            select 
                sql_calc_found_rows *,
                match(title, body) against('{$query}*' in boolean mode) as score
            from `{$this->options->table}`
            where 
                match(title, body) against('{$query}*' in boolean mode) and
                language = '{$language}'
            order by 
                score desc
            limit {$limit}
        ");
        list($amount) = $db->query("select found_rows()")->fetch_array();
        $response = [];
        if ($result !== false)
            while (($row = $result->fetch_object()) !== null)
                $response[] = $row;
        return (object) [
            'count' => $amount,
            'results' => $response
        ];
    }

    protected function insertTags($language, $tags) {
        $db = $this->connection();
        foreach ($tags as $tag => $occurrence) {
            $tag = $db->real_escape_string($tag);
            $db->query("
                insert into `{$this->options->table}_tags`(tag, occurrence, `language`)
                values('{$tag}', $occurrence, '{$language}')
                on duplicate key update occurrence = occurrence + values(occurrence)
            ");
        }
    }

    function remove($id) {
        $db = $this->connection();
        $result = $db->query("select * from `{$this->options->table}` where id = '{$id}' limit 1");
        if ($result === false) return;
        $item = $result->fetch_object();
        if ($item === null) return;
        $tags = json_decode($item->tags);
        foreach ($tags as $tag => $occurrence) {
            $db->query("
                update `{$this->options->table}_tags`
                set occurrence = occurrence - $occurrence
                where tag = '$tag'
            ");
        }
        $db->query("delete from `{$this->options->table}` where id = '{$id}' limit 1");
    }

    protected function connection() {
        if ($this->connection !== null)
            return $this->connection;
        return $this->connection = new \mysqli(
            $this->options->host, 
            $this->options->user, 
            $this->options->pass, 
            $this->options->name,
            isset($this->options->port) 
                ? $this->options->port 
                : 3306
        );
    }
}