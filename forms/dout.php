<?php
	require_once __DIR__ . '/../includes/DOUTQueryManager.php';

	$stateOptions = [
		"0" => "Off",
		"1" => "On",
	];
	$nodeId = 5;

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
			$channel = $data[1];
			if (!isset($channelsData[$channel])) {
				$channelsData[$channel] = [];
			}
			$channelsData[$channel][$data[0]] = $value;
		}

		// add data for any missing checkboxes (unchecked checkboxes are not included in the POST)
		for ($i = 1; $i <= 16; $i++)
		{
			if (empty($channelsData[$i]["periodic_status_report"]))
			{
				$channelsData[$i]["periodic_status_report"] = 0;
			}
		}

		if (empty($errors)) {
			$queries = [];
			foreach ($channelsData as $channelId => $data) {
				$queries = array_merge($queries,
                    DOUTQueryManager::generateSetQueriesForChannel($nodeId, $channelId, $data));
			}
			if (!empty($queries)) {
				/*
				$queries = implode("\n", $queries);
				file_put_contents("node{$nodeId}.conf", $queries . "\n");
				// execute bash script
				shell_exec("./config-write.sh -w {$nodeId}");
				// write config lines from "node{$nodeId}.conf" file to the device
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
    shell_exec("./config-write.sh -r {$nodeId}");
	// read back device config into "node{$nodeId}.read" file for populating into form
	$readFilePath = "node{$nodeId}.read";
	if (file_exists($readFilePath)) {
		$queries = file_get_contents($readFilePath);
		if ($queries !== false) {
			$queries = explode("\n", $queries);
			$channelsData = DOUTQueryManager::readQueriesForChannel($queries);
		}
	}
	*/
	$queries = $serialport->getNodeParams($nodeId);
	//echo "queries:<br><pre>";
	//print_r($queries);
	//echo "</pre>";
	if (!empty($queries))
	{
		$channelsData = DOUTQueryManager::readQueriesForChannel($queries);
	}

?>
<form method="POST" class="form-inline">
    <div class="container">
        <div class="row justify-content-center">
            <input type="hidden" name="f" value="dout"/>
            <table class="adc-setup-table">
                <thead>
                    <tr>
                        <th>Output</th>
                        <th>Default State</th>
                        <th>Periodic Report</th>
                    </tr>
                </thead>
                <tbody>
                    <?php for ($i = 1; $i <= 16; $i++): ?>
                        <?php if (isset($errors[$i])): ?>
                            <tr>
                                <td colspan="2" class="errors">
                                    <?php foreach ($errors[$i] as $configName => $error): ?>
                                        <p class="error-box"><?php echo $configName . " - " . $error; ?></p>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <tr>
                            <td>DOUT<?php echo str_pad($i, 2, "0", STR_PAD_LEFT); ?></td>
                            <td>
                                <select name="state-<?php echo $i; ?>">
                                    <?php foreach ($stateOptions as $value => $option): ?>
                                        <option value="<?php echo $value; ?>"
                                            <?php echo isset($channelsData[$i]["state"]) && $channelsData[$i]["state"] == $value
                                                ? "selected" : ""; ?>><?php echo $option; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <input class="form-check-input position-static" type="checkbox" data-toggle="toggle"
                                       name="periodic_status_report-<?php echo $i; ?>" value="1"
                                    <?php echo isset($channelsData[$i]["periodic_status_report"])
                                        && $channelsData[$i]["periodic_status_report"] == 1
                                            ? "checked" : ""; ?> />
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
