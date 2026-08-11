<?php if (!defined('VB_ENTRY')) die('Access denied.');
class aithreadassistant_dB_MYSQL_QueryDefs extends vB_dB_MYSQL_QueryDefs { protected $db_type='MYSQL'; protected $query_data=[
 'create_summary'=>[vB_dB_Query::QUERYTYPE_KEY=>vB_dB_Query::QUERY_CREATE,'query_string'=>'CREATE TABLE IF NOT EXISTS {TABLE_PREFIX}aithreadassistant_summary (nodeid INT UNSIGNED NOT NULL, summary TEXT NOT NULL, model VARCHAR(191) NOT NULL, generated_at DATETIME NOT NULL, PRIMARY KEY (nodeid)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'],
 'drop_summary'=>[vB_dB_Query::QUERYTYPE_KEY=>vB_dB_Query::QUERY_DROP,'query_string'=>'DROP TABLE IF EXISTS {TABLE_PREFIX}aithreadassistant_summary']]; }
