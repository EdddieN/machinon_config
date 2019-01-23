<?php

class InternalQueryManager implements QueryManager {

    const KEYPAD_MODE_DISABLE = 0;
    const KEYPAD_MODE_CONFIG = 1;
    const KEYPAD_MODE_DATA = 2;
	const KEYPAD_MODE_BOTH = 3;

    private static $configList = [
        "interval" => 24,
		"keypadmode" => 24,
		"firmware" => 26,
		"pcbserial" => 27,
		"vsupply_hyst" => 28,
        "backlight_default" => 24
    ];

    /**
        * @param integer|string $nodeId
        * @param integer|string $channelId
        * @param array $data
        * @return array
        */
    public static function generateSetQueriesForChannel($nodeId, $channelId, $data) {
        $queries = [];
        foreach ($data as $type => $value) {
            if (isset(static::$configList[$type])) {
                switch ($type) {
					case "interval":
						$queries[] = "0;1;" . self::QUERY_TYPE_WRITE . ";" . self::QUERY_USE_ACK . ";" . static::$configList[$type] . ";{$value}";
                        break;
                    case "keypadmode":
						$queries[] = "6;11;" . self::QUERY_TYPE_WRITE . ";" . self::QUERY_USE_ACK . ";" . static::$configList[$type] . ";{$value}";
                        break;
                    case "vsupply_hyst":
						$queries[] = "6;15;" . self::QUERY_TYPE_WRITE . ";" . self::QUERY_USE_ACK . ";" . static::$configList[$type] . ";{$value}";
                        break;
                    case "backlight_default":
						$queries[] = "6;18;" . self::QUERY_TYPE_WRITE . ";" . self::QUERY_USE_ACK . ";" . static::$configList[$type] . ";{$value}";
                        break;
                    default:
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
                $msgType = $invertedConfigList[$queryParts[4]];
                switch ($msgType) {
					case "interval":
					case "keypadmode":
                    case "backlight_default":
                        // these items all use msgType type 24 (V_VAR1), so check which node/child to extract
                        // extract "interval" if this is node 0
						if ($queryParts[0] == 0)
						{
							$data[$queryParts[1]]["interval"] = $queryParts[5];
						}
                        // extract "keypadmode" if this is node 6 child 11
						if ($queryParts[0] == 6 && $queryParts[1] == 11)
						{
							$data[$queryParts[1]]["keypadmode"] = $queryParts[5];
						}
                        // extract "backlight_default" if this is node 6 child 18
						if ($queryParts[0] == 6 && $queryParts[1] == 18)
						{
							$data[$queryParts[1]]["backlight_default"] = $queryParts[5];
						}
						break;
                    default:
                        // other items have unique msgType
                        $data[$queryParts[1]][$msgType] = $queryParts[5];
                        break;
                }
            }
        }
        return $data;
    }
}