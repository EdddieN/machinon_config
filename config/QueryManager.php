<?php

interface QueryManager {
    
    const QUERY_TYPE_WRITE = 1;
    const QUERY_TYPE_READ = 2;

    const QUERY_USE_ACK = 1;  // 1 to request acknowledgement of queries, 0 to disable ack

    public static function generateSetQueriesForChannel($nodeId, $channelId, $data);
    public static function readQueriesForChannel($queries);

}