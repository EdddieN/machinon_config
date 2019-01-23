<?php
	require_once __DIR__ . '/../includes/InternalQueryManager.php';

	$keypadModeOptions = [
		InternalQueryManager::KEYPAD_MODE_DISABLE => "Disabled",
		InternalQueryManager::KEYPAD_MODE_CONFIG => "Config Port",
		InternalQueryManager::KEYPAD_MODE_DATA => "Data Port",
		InternalQueryManager::KEYPAD_MODE_BOTH => "Config + Data Ports"
    ];

	$stateOptions = [
		"0" => "Off",
		"1" => "On",
	];

	$nodes = [ 0, 6 ];

	$serialport = new MachinonSerial();

	if (!empty($_POST)) {
		// collect data from post
		$channelsData = [];
		$errors = [];
		foreach ($_POST as $key => $value) {
			$data = explode("-", $key);
			if (count($data) !== 2 || !is_numeric($data[1]) || empty($value) && $value !== "0") {
				continue;
			}
			$configName = $data[0];
			$childId = $data[1];
			// validation logic
			switch ($configName) {
                case "interval":
                    //if ($childId == 0)
					{
						if (!is_numeric($value)) {
							$errors[$childId][$configName] = "Interval must be a numeric value";
							continue 2;
						}
						elseif (($value < 10) || ($value > 24*3600)) {
							$errors[$childId][$configName] = "Interval must be 10 - 84600";
							continue 2;
						}
						$value = (float) $value;
					}
					break;
                case "vsupply_hyst":
                    if (!is_numeric($value)) {
                        $errors[$childId][$configName] = "Vsupply Hysteresis must be a numeric value";
                        continue 2;
                    }
                    elseif (($value < 0) || ($value > 10)) {
                        $errors[$childId][$configName] = "Vsupply Hysteresis must be 0 - 10";
                        continue 2;
                    }
                    $value = (float) $value;
					break;
				default:
			}
			if (!isset($channelsData[$childId])) {
				$channelsData[$childId] = [];
			}
			$channelsData[$childId][$data[0]] = $value;
		}
		
		if (empty($errors)) {
			$queries = [];
			foreach ($channelsData as $childId => $data) {
				$queries = array_merge($queries, InternalQueryManager::generateSetQueriesForChannel(0, $childId, $data));
			}
			if (!empty($queries)) {
				/*
				$queries = implode("\n", $queries);
				file_put_contents("node{$nodeId}.conf", $queries . "\n");
				// execute bash script
				shell_exec("./config-write.sh -w {$nodeId}");   // write config lines from "node{$nodeId}.conf" file to the device
				*/
				$serialport->writeParams($queries);
			}
		}
	}
	else {
		$errors = [];
		$channelsData = [];
	}

    $queries = [];
    foreach ($nodes as $nodeId) {
		$queries = array_merge($queries, $serialport->getNodeParams($nodeId));
    }
	//echo "query responses:<pre>";
	//print_r($queries);
	//echo "</pre>";

	//echo "errors:<pre>";
	//print_r($errors);
	//echo "</pre>";
	if (!empty($queries))
	{
		$channelsData = InternalQueryManager::readQueriesForChannel($queries);
		//echo "channelsData:<pre>";
		//print_r($channelsData);
		//echo "</pre>";
	}

?>
<form method="POST" class="form-inline">
    <div class="container">
        <div class="row justify-content-center">
            <input type="hidden" name="f" value="internal"/>
            <table class="adc-setup-table">
                <thead>
                    <tr>
                        <th>Parameter</th>
                        <th>Value</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (isset($errors[1]["interval"])): ?>
                        <tr>
                            <td colspan="2" class="errors">
                                <?php foreach ($errors[1] as $configName => $error): ?>
                                    <p class="error-box"><?php echo $error; ?></p>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <tr>
                        <td>Report Interval</td>
                        <td>
                            <input type="text" name="interval-1" value="<?php echo isset($channelsData[1]["interval"]) ? $channelsData[1]["interval"] : ""; ?>"/>
                        </td>
                        <td>(10-86400 secs)</td>
                    </tr>
                    <?php if (isset($errors[15]["vsupply_hyst"])): ?>
                        <tr>
                            <td colspan="2" class="errors">
                                <?php foreach ($errors[15] as $configName => $error): ?>
                                    <p class="error-box"><?php echo $error; ?></p>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <tr>
                        <td>Vsupply Report Hysteresis</td>
                        <td>
                            <input type="text" name="vsupply_hyst-15" value="<?php echo isset($channelsData[15]["vsupply_hyst"]) ? $channelsData[15]["vsupply_hyst"] : ""; ?>"/>
                        </td>
                        <td>(0 to use interval)</td>
                    </tr>
                    <tr>
                        <td>Keypad Events Port</td>
                        <td>
                            <select name="keypadmode-11">
                                <?php foreach ($keypadModeOptions as $value => $option): ?>
                                    <option value="<?php echo $value; ?>" <?php echo isset($channelsData[11]["keypadmode"]) && $channelsData[11]["keypadmode"] == $value ? "selected" : ""; ?>><?php echo $option; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td></td>
                    </tr>
                    <tr>
                        <td>Backlight Power-up State</td>
                        <td>
                            <select name="backlight_default-18">
                                <?php foreach ($stateOptions as $value => $option): ?>
                                    <option value="<?php echo $value; ?>" <?php echo isset($channelsData[18]["backlight_default"]) && $channelsData[18]["backlight_default"] == $value ? "selected" : ""; ?>><?php echo $option; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="row justify-content-center">
            <button type="submit" class="btn btn-primary m-3">SAVE</button>
        </div>
        <div class="row justify-content-center">
            <p>Firmware Version : <samp><?php echo isset($channelsData[1]["firmware"]) ? $channelsData[1]["firmware"] : "(unknown)"; ?></samp></p>
        </div>
        <div class="row justify-content-center">
            <p>PCB Serial Number : <samp><?php echo isset($channelsData[1]["pcbserial"]) ? $channelsData[1]["pcbserial"] : "(unknown)"; ?></samp></p>
        </div>
    </div>
</form>

