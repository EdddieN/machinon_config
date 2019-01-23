<?php
	require_once __DIR__ . '/../includes/DINQueryManager.php';

	$modeOptions = [
		DINQueryManager::MODE_DISABLE => "Disabled",
		DINQueryManager::MODE_STATUS => "Status",
		DINQueryManager::MODE_COUNTER => "Counter",
    ];
    $sensorOptions = [
		"13,18" => "kWh",
		"21,35" => "Volume",
    ];
	$nodes = [ 1, 2 ];

	$serialport = new MachinonSerial();

	if (!empty($_POST)) {
		// collect data from post and group it by channel
		$channelsData = [];
		$errors = [];
		foreach ($_POST as $key => $value) {
			$data = explode("-", $key);
			if (count($data) !== 2 || !is_numeric($data[1]) || empty($value) && $value !== "0") {
				continue;
			}
			$configName = $data[0];
            $channel = $data[1];
            // validation logic
			switch ($configName) {
				case "multiplier":
					if (empty(implode("", $value))) {
						continue 2;
					}
					foreach ($value as $configValue) {
						if (!is_numeric($configValue)) {
							$errors[$channel][$configName] = "One of multiplier values is not specified or not number";
							continue 2;
						}
					}
					break;
				//case "invert":
					// maybe don't need to do any checks for this
				//break;
			}
			if (!isset($channelsData[$channel])) {
				$channelsData[$channel] = [];
			}
			$channelsData[$channel][$data[0]] = $value;
		}
		
		// add data for any missing checkboxes (unchecked checkboxes are not included in the POST)
		for ($i = 1; $i <= 16; $i++)
		{
			if (empty($channelsData[$i]["invert"]))
			{
				$channelsData[$i]["invert"] = 0;
			}
		}
		
		if (empty($errors)) {
			$queries = [];
			foreach ($channelsData as $channelId => $data) {
                $channelQueries = DINQueryManager::generateSetQueriesForChannel(0, $channelId, $data);
				foreach ($channelQueries as $nodeID => $nodeQueries) {
                    if (!isset($queries[$nodeID])) {
                        $queries[$nodeID] = $nodeQueries;
                    } else {
                        $queries[$nodeID] = array_merge($queries[$nodeID], $nodeQueries);
                    }
                }
            }
			if (!empty($queries)) {
				//echo "generated queries:<br><pre>";
				//print_r($queries);
				//echo "</pre>";
                foreach ($queries as $nodeID => $nodeQueries) {
					/*
					$nodeQueries = implode("\n", $nodeQueries) . "\n";
					file_put_contents("node{$nodeID}.conf", $nodeQueries);
                    // execute bash script for each node
                    shell_exec("./config-write.sh -w {$nodeID}");
					// write config lines from "node{$nodeId}.conf" file to the device
					*/
					// write the lines to serial port
					$serialport->writeParams($nodeQueries);
                }
			}
		}
	} else {
		$errors = [];
		$channelsData = [];
	}
    // read script output and make something with him
    $queries = [];
    foreach ($nodes as $nodeId) {
        /*
		shell_exec("./config-write.sh -r {$nodeID}");
        // read back device config into "node{$nodeId}.read" file for populating into form
        $readFilePath = "node{$nodeID}.read";
        if (file_exists($readFilePath)) {
            $fileContent = file_get_contents($readFilePath);
            if ($fileContent !== false) {
                $queries = array_merge($queries, explode("\n", $fileContent));
            }
        }
		*/
		//$queries[] = $serialport->getNodeParams($nodeId);
		$queries = array_merge($queries, $serialport->getNodeParams($nodeId));
    }
    if (!empty($queries)) {
        $channelsData = DINQueryManager::readQueriesForChannel($queries);
		//echo "channelsData:<pre>";
		//print_r($channelsData);
		//echo "</pre>";
    }
?>

<form method="POST" class="form-inline">
    <div class="container">
        <div class="row justify-content-center">
            <input type="hidden" name="f" value="din"/>
            <table class="adc-setup-table">
                <thead>
                    <tr>
                        <th>Input</th>
                        <th>Mode</th>
                        <th>Invert</th>
                        <th>Periodic Report</th>
                        <th>Multiplier Slope</th>
                        <th>Multiplier Offset</th>
                        <th>Data Type</th>
                    </tr>
                </thead>
                <tbody>
                    <?php for ($i = 1; $i <= 16; $i++): ?>
                        <?php if (isset($errors[$i])): ?>
                            <tr>
                                <td colspan="5" class="errors">
                                    <?php foreach ($errors[$i] as $configName => $error): ?>
                                        <p class="error-box"><?php echo $configName . " - " . $error; ?></p>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <tr>
                            <td>DIN<?php echo str_pad($i, 2, "0", STR_PAD_LEFT); ?></td>
                            <td>
                                <select name="mode-<?php echo $i; ?>">
                                    <?php foreach ($modeOptions as $value => $option): ?>
                                        <option value="<?php echo $value; ?>"
                                            <?php echo isset($channelsData[$i]["mode"]) && $channelsData[$i]["mode"] == $value
                                                ? "selected" : ""; ?>><?php echo $option; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <input type="checkbox" name="invert-<?php echo $i ?>" value="1" data-toggle="toggle"
                                    <?php echo isset($channelsData[$i]["invert"]) && $channelsData[$i]["invert"] == 1
                                        ? "checked" : ""; ?>/>
                            </td>
                            <td>
                                <input class="form-check-input position-static" type="checkbox" data-toggle="toggle"
                                       name="periodic_status_report-<?php echo $i; ?>" value="1"
                                    <?php echo isset($channelsData[$i]["periodic_status_report"]) &&
                                        $channelsData[$i]["periodic_status_report"] == 1
                                            ? "checked" : ""; ?> />
                            </td>
                            <td>
                                <input type="text" name="multiplier-<?php echo $i; ?>[]"
                                       value="<?php echo isset($channelsData[$i]["multiplier"][0])
                                           ? $channelsData[$i]["multiplier"][0] : ""; ?>"/>
                            </td>
                            <td>
                                <input type="text" name="multiplier-<?php echo $i; ?>[]"
                                       value="<?php echo isset($channelsData[$i]["multiplier"][1])
                                           ? $channelsData[$i]["multiplier"][1] : ""; ?>"/>
                            </td>
                            <td>
                                <select name="sensor-<?php echo $i; ?>">
                                    <?php foreach ($sensorOptions as $value => $option): ?>
                                        <option value="<?php echo $value; ?>"
                                            <?php echo isset($channelsData[$i]["sensor"]) && $channelsData[$i]["sensor"] == $value
                                                ? "selected" : ""; ?>><?php echo $option; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>
        <div class="row justify-content-center">
            <button type="submit" class="btn btn-primary m-3">SAVE</button>
        </div>
    </div>
</form>
