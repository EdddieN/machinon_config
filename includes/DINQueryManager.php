<?php

require_once __DIR__ . '/QueryManager.php';

class DINQueryManager implements QueryManager {

    const MODE_DISABLE = 0;
    const MODE_STATUS = 1;
    const MODE_COUNTER = 2;

    private static $configList = [
        "mode" => 24,
        "multiplier" => 25,
        "sensor" => 26,
		"invert" => 25,
        "periodic_status_report" => 26,
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
                switch ($type) {
                    case "multiplier":
						$value = $value[1] . "," . $value[0];
						$queries[2][] = "2;{$channelId};" . self::QUERY_TYPE_WRITE . ";" . self::QUERY_USE_ACK . ";" . static::$configList[$type] . ";{$value}";
						break;
					case "invert":
						$queries[1][] = "1;{$channelId};" . self::QUERY_TYPE_WRITE . ";" . self::QUERY_USE_ACK . ";" . static::$configList[$type] . ";{$value}";
                        break;
                    case "mode":
                        switch ($value) {
                            case static::MODE_DISABLE:
                                $queries[1][] = "1;{$channelId};" . self::QUERY_TYPE_WRITE . ";" . self::QUERY_USE_ACK . ";" . static::$configList[$type] . ";0";
                                $queries[2][] = "2;{$channelId};" . self::QUERY_TYPE_WRITE . ";" . self::QUERY_USE_ACK . ";" . static::$configList[$type] . ";0";
                                break;
                            case static::MODE_STATUS:
                                $queries[1][] = "1;{$channelId};" . self::QUERY_TYPE_WRITE . ";" . self::QUERY_USE_ACK . ";" . static::$configList[$type] . ";1";
                                $queries[2][] = "2;{$channelId};" . self::QUERY_TYPE_WRITE . ";" . self::QUERY_USE_ACK . ";" . static::$configList[$type] . ";0";
                                break;
                            case static::MODE_COUNTER:
                                $queries[1][] = "1;{$channelId};" . self::QUERY_TYPE_WRITE . ";" . self::QUERY_USE_ACK . ";" . static::$configList[$type] . ";0";
                                $queries[2][] = "2;{$channelId};" . self::QUERY_TYPE_WRITE . ";" . self::QUERY_USE_ACK . ";" . static::$configList[$type] . ";1";
                                break;
                        }
                        break;
                    case "periodic_status_report":
                        $queries[1][] = "1;{$channelId};" . self::QUERY_TYPE_WRITE . ";" . self::QUERY_USE_ACK . ";" . static::$configList[$type] . ";{$value}";
                        break;
                    case "sensor":
                    default:
                        $queries[2][] = "2;{$channelId};" . self::QUERY_TYPE_WRITE . ";" . self::QUERY_USE_ACK . ";" . static::$configList[$type] . ";{$value}";
                        break;
                }
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
                    case "multiplier":
					case "invert":
                        // extract multiplier if this is node 2 (node 1 uses same message type as "invert")
						if ($queryParts[0] == 2)
						{
							$value = explode(",", $queryParts[5]);
							if (count($value) < 2) {
								continue 2;
							}
							$data[$queryParts[1]]["multiplier"] = [ $value[1], $value[0] ];
						}
                        // extract "invert" if this is node 1 (node 2 uses same message type as "multiplier")
						if ($queryParts[0] == 1)
						{
							$data[$queryParts[1]]["invert"] = $queryParts[5];
						}
                        break;
                    case "mode":
                        if (!isset($data[$queryParts[1]][$configName])) {
                            $data[$queryParts[1]][$configName] = 0;
                        }
                        /* @ACHTUNG! Very dangerous code */
                        if ($queryParts[5] == 1) {
                            $data[$queryParts[1]][$configName] += 1 << ($queryParts[0] - 1);
                        }
                        break;
					case "sensor":
                    case "periodic_status_report":
                        // extract sensor type if this is node 2 (counters)
						if ($queryParts[0] == 2)
						{
							/*
                            $value = explode(",", $queryParts[5]);
							if (count($value) < 2) {
								continue 2;
							}
							$data[$queryParts[1]]["sensor"] = [ $value[0], $value[1] ];
                            */
                            $data[$queryParts[1]]["sensor"] = $queryParts[5];
						}
                        // periodic_status_report if it's node 1 (status inputs) (node 2 uses same message type)
						if ($queryParts[0] == 1)
						{
							$data[$queryParts[1]]["periodic_status_report"] = $queryParts[5];
						}
                        break;
                    default:
                        $data[$queryParts[1]][$configName] = $queryParts[5];
                        break;
                }
            }
        }
        return $data;
    }
}