CREATE TABLE `indexer` (
  `id` char(32) NOT NULL,
  `language` char(2) NOT NULL,
  `url` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  #`breadcrumbs` text NOT NULL,
  `body` text NOT NULL,
  `tags` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `indexer`
  ADD PRIMARY KEY (`id`),
  ADD KEY `language` (`language`);
ALTER TABLE `indexer` ADD FULLTEXT KEY `search` (`title`,`body`);

CREATE TABLE `indexer_tags` (
  `language` char(2) NOT NULL,
  `tag` varchar(255) NOT NULL,
  `occurrence` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `indexer_tags`
  ADD PRIMARY KEY (`tag`),
  ADD KEY `language` (`language`);