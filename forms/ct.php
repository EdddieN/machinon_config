<?php
	require_once __DIR__ . '/../includes/CTQueryManager.php';

	$sensorOptions = [
		"30,39" => "Current",
		"13,17" => "Power",
	];
	$nodeId = 3;

	$serialport = new MachinonSerial();

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
			$ctChannel = $data[1];
			// validation logic
			switch ($configName) {
                case "multiplier":
                    if (!is_numeric($value)) {
                        $errors[$ctChannel][$configName] = "Multiplier must be a numeric value";
                        continue 2;
                    }
                    $value = (float) $value;
					break;
                case "hysteresis":
                    if (!is_numeric($value)) {
                        $errors[$ctChannel][$configName] = "Hysteresis must be a numeric value";
                        continue 2;
                    }
                    $value = (float) $value;
					break;
			}
			if (!isset($channelsData[$ctChannel])) {
				$channelsData[$ctChannel] = [];
			}
			$channelsData[$ctChannel][$data[0]] = $value;
		}
		
		if (empty($errors)) {
			$queries = [];
			foreach ($channelsData as $ctId => $data) {
				$queries = array_merge($queries, CTQueryManager::generateSetQueriesForChannel($nodeId, $ctId, $data));
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
			$channelsData = CTQueryManager::readQueriesForChannel($queries);
		}
	}
	*/
	$queries = $serialport->getNodeParams($nodeId);
	//echo "queries:<br><pre>";
	//print_r($queries);
	//echo "</pre>";
	if (!empty($queries))
	{
		$channelsData = CTQueryManager::readQueriesForChannel($queries);
	}

?>
<form method="POST" class="form-inline">
    <input type="hidden" name="f" value="ct"/>
    <table class="adc-setup-table">
        <thead>
            <tr>
                <th>Input</th>
                <th>Multiplier</th>
                <th>Report Hysteresis<br>(0 to use interval)</th>
                <th>Data Type</th>
            </tr>
        </thead>
        <tbody>
            <?php for ($i = 1; $i <= 6; $i++): ?>
                <?php if (isset($errors[$i])): ?>
                    <tr>
                        <td colspan="3" class="errors">
                            <?php foreach ($errors[$i] as $configName => $error): ?>
                                <p class="error-box"><?php echo $error; ?></p>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                <?php endif; ?>
                <tr>
                    <td>CT<?php echo $i; ?></td>
                    <td>
                        <input type="text" name="multiplier-<?php echo $i; ?>"
                               value="<?php echo isset($channelsData[$i]["multiplier"])
                                   ? $channelsData[$i]["multiplier"] : ""; ?>"/>
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
            <?php if (isset($errors[7])): ?>
                <tr>
                    <td colspan="3" class="errors">
                        <?php foreach ($errors[7] as $configName => $error): ?>
                            <p class="error-box"><?php echo $error; ?></p>
                        <?php endforeach; ?>
                    </td>
                </tr>
            <?php endif; ?>
            <tr>
                <td>CT4 Freq</td>
                <td></td>
                <td>
                    <input type="text" name="hysteresis-7"
                           value="<?php echo isset($channelsData[7]["hysteresis"])
                                ? $channelsData[7]["hysteresis"] : ""; ?>"/>
                </td>
                <td></td>
            <tr>
                <td colspan="4">
                    <button type="submit" class="btn">SAVE</button>
                </td>
            </tr>
        </tbody>
    </table>
</form>
