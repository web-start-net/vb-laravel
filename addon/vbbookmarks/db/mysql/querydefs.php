<?php if (!defined('VB_ENTRY')) die('Access denied.');

class vbbookmarks_dB_MYSQL_QueryDefs extends vB_dB_MYSQL_QueryDefs
{
    protected $db_type = 'MYSQL';

    protected $query_data = [
        'create_bookmark' => [
            vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_CREATE,
            'query_string' => 'CREATE TABLE IF NOT EXISTS {TABLE_PREFIX}vbbookmarks_bookmark (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                userid INT UNSIGNED NOT NULL,
                nodeid INT UNSIGNED NOT NULL,
                created_at TIMESTAMP NULL DEFAULT NULL,
                updated_at TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (id),
                KEY userid (userid),
                KEY nodeid (nodeid),
                UNIQUE KEY userid_nodeid (userid, nodeid)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        ],
        'drop_bookmark' => [
            vB_dB_Query::QUERYTYPE_KEY => vB_dB_Query::QUERY_DROP,
            'query_string' => 'DROP TABLE IF EXISTS {TABLE_PREFIX}vbbookmarks_bookmark',
        ],
    ];
}
