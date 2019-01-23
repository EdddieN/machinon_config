<?php
require_once __DIR__ . '/PhpSerial.php';
require_once __DIR__ . '/QueryManager.php';

const MACHINON_SERIAL_PORT = '/dev/ttySC1';

function microtime_float()
{
		list($usec, $sec) = explode(" ", microtime());
		return ((float)$usec + (float)$sec);
}

class MachinonSerial
{
	//var $_device = null;
	private $_serial = null;
	//$_serial = new PhpSerial;
	
	// Constructor - set up port and open it
	public function MachinonSerial($device = MACHINON_SERIAL_PORT)
	{
		// serial port "serial3" is the config port on Machinon (symlink to /dev/ttySC1)
		$this->_serial = new PhpSerial;
		$this->_serial->deviceSet($device);
		$this->_serial->confBaudRate(115200);
		$this->_serial->confParity("none");
		$this->_serial->confCharacterLength(8);
		$this->_serial->confStopBits(1);
		$this->_serial->confFlowControl("none");
		// Then we need to open it (for MySensors messages with \n, use plain read/write mode 'r+'
		//   and phpSerial options to disable echo, disable line ending translation, disable special control char handling
		$this->_serial->_exec("stty -F " . $device . " raw -nl -echo -echoe -echok -echonl -echoprt -echoctl");
		//$this->_serial->deviceOpen($mode='r+', $raw=true, $echo=false);
		$this->_serial->deviceOpen($mode='r+b');
		// may need to set "raw" mode and/or disable echo for stty?
		$this->sendString("\n");  // send an empty line to flush the machinon serial parser
	}


	/*  NOT REQUIRED - port gets closed automatically pn PHP shutdown
	// Destructor - just close the serial port
	public function __destruct()
	{
		$_serial->deviceClose();
	}
	*/

	// send a raw string to serial port
	public function sendString($str)
	{
		// just call the corresponding PhpSerial class function with zero wait
		$this->_serial->sendMessage($str, 0);
	}


	// Get a string (terminated by \n), with specified timeout in seconds. Returns empty string if nothing received within timeout.
	// Machinon should respond well within 0.05 sec. Default is 0.25 sec in case of serial buffer backlog
	public function readString($timeout = 0.25)
	{
		$readChar = '';
		$result = '';
		$start = microtime_float();

		while (microtime_float() < $start + $timeout) 
		{
			$readChar = $this->_serial->readPort(1);  // read 1 byte at a time (or 0 if none available) to avoid eating into any following message
			if ($readChar == "\n")
			{
				// got the newline \n terminator
				//echo "got string '$result'<br>";
				return $result;
			}
			// TODO check for valid char (only anything likely to occur in MySensors message)
			elseif ($readChar != '') 
			{
				// add chars to the result string
				$result .= $readChar;
			}
		}
		return $result;
	}
	
	
	// Get all config parameters for specified node 0-6 as array of MySensors "set" messages
	public function getNodeParams($node)
	{
		$results = [];
		switch ($node)
		{
			case 1:
				// DINxx_status
				// type 24 = status report enable
				// type 25 = status input invert
				// type 26 = status input periodic status report
				for ($child = 1; $child <= 16; $child++)
				{
					foreach ([24, 25, 26] as $msgType)
					{
						$this->sendString("$node;$child;2;0;$msgType;0\n");
						// TODO break out if a response failed?
						$results[] = $this->readString();  // append response to array.
					}
				}
				break;
			case 2:
				// DINxx_count
				// type 24 = counter report enable
				// type 25 = counter offset,slope
				// type 26 = counter sensortype,valuetype
				// type 27 = counter raw value (not used here)
				for ($child = 1; $child <= 16; $child++)
				{
					foreach ([24, 25, 26] as $msgType)
					{
						$this->sendString("$node;$child;2;0;$msgType;0\n");
						// TODO break out if a response failed?
						$results[] = $this->readString();  // append response to array.
					}
				}
				break;
			case 3:
				// CTx
				// type 24 = NOT USED
				// type 25 = CT slope
				// type 26 = CT sensortype,valuetype
				// type 27 = CT calibration factor (not used here)
				// type 28 = CT report-on-change hysteresis (child 1-7)
				for ($child = 1; $child <= 6; $child++)
				{
					foreach ([25, 26, 28] as $msgType)
					{
						$this->sendString("$node;$child;2;0;$msgType;0\n");
						// TODO break out if a response failed?
						$results[] = $this->readString();  // append response to array.
					}
				}
				// get the CT4_frequency hysteresis
				$child = 7;
				$msgType = 28;
				$this->sendString("$node;$child;2;0;$msgType;0\n");
				$results[] = $this->readString();  // append response to array.
				break;
			case 4:
				// AIN1...AIN8
				// type 24 = input mode
				// type 25 = offset,slope
				// type 26 = value type
				// type 27 = NOT USED
				// type 28 = report-on-change hysteresis
				for ($child = 1; $child <= 8; $child++)
				{
					foreach ([24, 25, 26, 28] as $msgType)
					{
						$this->sendString("$node;$child;2;0;$msgType;0\n");
						// TODO break out if a response failed?
						$results[] = $this->readString();  // append response to array.
					}
				}
				break;
			case 5:
				// DOUTxx
				// type 24 = enable DOUT ON at startup
				// type 26 = enable DOUT periodic_status_report at startup
				for ($child = 1; $child <= 16; $child++)
				{
					foreach ([24,26] as $msgType)
					{
						$this->sendString("$node;$child;2;0;$msgType;0\n");
						// TODO break out if a response failed?
						$results[] = $this->readString();  // append response to array.
					}
				}
				break;
			case 6:
				// Front panel
				// Get keypad messages destination
				$this->sendString("$node;11;2;0;24;0\n");
				$results[] = $this->readString();  // append response to array.
				// Get Vsupply report-on-change hysteresis
				$this->sendString("$node;15;2;0;28;0\n");
				$results[] = $this->readString();  // append response to array.
				// Get backlight power-up state
				$this->sendString("$node;18;2;0;24;0\n");
				$results[] = $this->readString();  // append response to array.
				break;
			case 0:
				// Internal/general config
				// Get reporting interval
				$this->sendString("$node;1;2;0;24;0\n");
				$results[] = $this->readString();  // append response to array.
				// Get firmware versions
				$this->sendString("$node;1;2;0;26;0\n");
				$results[] = $this->readString();  // append response to array.
				// Get PCB serial number EUI-48/64
				$this->sendString("$node;1;2;0;27;0\n");
				$results[] = $this->readString();  // append response to array.
				break;
			default:
				// unknown node, so just return the empty array
		}
		return $results;
	}
	
	// Write array of pre-coded strings to serial port and wait for each response
	public function writeParams($messageArray)
	{
		foreach ($messageArray as $message)
		{
			// send the pre-coded string. Wait for the response (if using ACK) but ignore it
			$this->sendString($message . "\n");
            if (QueryManager::QUERY_USE_ACK == 1)
            {
                $dummy = $this->readString();
            }
		}
	}
}
