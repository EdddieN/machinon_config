<?php
	require_once __DIR__ . '/../includes/ADCQueryManager.php';

	$modeOptions = [
		"0" => "0-10V",
		"1" => "4-20mA",
		"2" => "0-20mA",
	];
	$sensorOptions = [
		"30,38" => "Voltage",
		"30,39" => "Current",
		"7,1" => "Humidity",
		"6,0" => "Temperature",
	];
	

	$serialport = new MachinonSerial();
	
	$nodeId = 4;
	if (!empty($_POST)) {
		// collect data from post and group it by adc channel
		$channelsData = [];
		$errors = [];
		foreach ($_POST as $key => $value) {
			$data = explode("-", $key);
			if (count($data) !== 2 || !is_numeric($data[1]) || empty($value) && $value !== "0") {
				continue;
			}
			$configName = $data[0];
			$adcChannel = $data[1];
			// validation logic
			switch ($configName) {
				case "multiplier":
					if (empty(implode("", $value))) {
						continue 2;
					}
					foreach ($value as $configValue) {
						if (!is_numeric($configValue)) {
							$errors[$adcChannel][$configName] = "One of the multiplier values is not specified or is not a number";
							continue 2;
						}
					}
					break;
			}
			if (!isset($channelsData[$adcChannel])) {
				$channelsData[$adcChannel] = [];
			}
			$channelsData[$adcChannel][$data[0]] = $value;
		}
		
		if (empty($errors)) {
			$queries = [];
			foreach ($channelsData as $adcId => $data) {
				$queries = array_merge($queries, ADCQueryManager::generateSetQueriesForChannel($nodeId, $adcId, $data));
			}
			if (!empty($queries)) {
				/*
				$queries = implode("\n", $queries);
				file_put_contents("node{$nodeId}.conf", $queries . "\n");
				// execute script to write config file to node
				shell_exec("./config-write.sh -w {$nodeId}");  // write config lines from "node{$nodeId}.conf" file to the device
				*/
				// write the lines to serial port
				$serialport->writeParams($queries);
			}
		}
	} else {
		$errors = [];
		$channelsData = [];
	}
	/*
	// read script output and make something with him
	shell_exec("./config-write.sh -r {$nodeId}");  // read back device config into "node{$nodeId}.read" file for populating into form
	$readFilePath = "node{$nodeId}.read";
	if (file_exists($readFilePath)) {
		$queries = file_get_contents($readFilePath);
		if ($queries !== false) {
			$queries = explode("\n", $queries);
			$channelsData = ADCQueryManager::readQueriesForChannel($queries);
		}
	}
	*/
	$queries = $serialport->getNodeParams($nodeId);
	//echo "queries:<br><pre>";
	//print_r($queries);
	//echo "</pre>";
	if (!empty($queries))
	{
		$channelsData = ADCQueryManager::readQueriesForChannel($queries);
	}
?>

<form method="POST">
    <div class="container">
        <div class="row justify-content-center">
            <input type="hidden" name="f" value="main"/>
            <table class="adc-setup-table">
                <thead>
                    <tr>
                        <th>Input</th>
                        <th>Mode</th>
                        <th>Multiplier Slope</th>
                        <th>Multiplier Offset</th>
                        <th>Report Hysteresis<br>(0 to use interval)</th>
                        <th>Data Type</th>
                    </tr>
                </thead>
                <tbody>
                    <?php for ($i = 1; $i <= 8; $i++): ?>
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
                            <td>AIN<?php echo $i; ?></td>
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
                                <input type="text" name="hysteresis-<?php echo $i; ?>"
                                       value="<?php echo isset($channelsData[$i]["hysteresis"])
                                           ? $channelsData[$i]["hysteresis"] : ""; ?>"/>
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
