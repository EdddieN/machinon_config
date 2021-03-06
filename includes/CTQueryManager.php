<?php

class CTQueryManager implements QueryManager {

    private static $configList = [
        "multiplier" => 25,
        "sensor" => 26,
		"hysteresis" => 28
    ];

    /**
        * @param integer|string $nodeId
        * @param integer|string $adcId
        * @param array $data
        * @return array
        */
    public static function generateSetQueriesForChannel($nodeId, $channelId, $data) {
        $queries = [];
        foreach ($data as $type => $value) {
            if (isset(static::$configList[$type])) {
                $queries[] = "{$nodeId};{$channelId};" . self::QUERY_TYPE_WRITE . ";" . self::QUERY_USE_ACK . ";" . static::$configList[$type] . ";{$value}";
            }
        }
        return $queries;
    }

    /**
        * @param array $queries
        * @return array
        */
    public static function readQueriesForChannel($queries) {
        static $invertedConfigList = null;
        if (is_null($invertedConfigList)) {
            $invertedConfigList = array_flip(static::$configList);
        }
        $data = [];
        foreach ($queries as $query) {
            $queryParts = explode(";", $query);
            if (count($queryParts) < 6 || $queryParts[2] != self::QUERY_TYPE_WRITE) {
                continue;
            }
            if (!isset($data[$queryParts[1]])) {
                $data[$queryParts[1]] = [];
            }
            if (isset($invertedConfigList[$queryParts[4]])) {
                $configName = $invertedConfigList[$queryParts[4]];
                switch ($configName) {
                    default:
                        $data[$queryParts[1]][$configName] = $queryParts[5];
                        break;
                }
            }
        }
        return $data;
    }
}