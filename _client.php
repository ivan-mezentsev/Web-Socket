<?php

class Client
{
	public function __construct($settings) {
		$this->settings = $settings;
	}

	/**
	 * Client
	 */
	public function run() {

		/**
		 * Post
		 */
		$post = json_encode(array('title'=>'Привет', 'url'=>'http://liamka.me', 'hubs'=>'тут хабы'));  //data to be send

		/**
		 * Header fo post
		 */
		$header = "GET / HTTP/1.1"."\r\n".
				"Upgrade: WebSocket"."\r\n".
				"Connection: Upgrade"."\r\n".
				"Origin: " . $this->settings['local'] . "\r\n".
				"Host: " . $this->settings['host'] . "\r\n".
				"Sec-WebSocket-Key: blablablablablablabla"."\r\n".
				"Content-Length: ".strlen($post)."\r\n"."\r\n";

		/**
		 * Open connection to socket
		 */

		$socket = fsockopen($this->settings['host'], $this->settings['port'], $errno, $errstr, 2);

		/**
		 * Listen to port
		 */
		fwrite($socket, $header) or die('error:'.$errno.':'.$errstr);

		/**
		 * Read headers
		 */
		fread($socket, 2000);

		/**
		 * Push new post
		 */
		fwrite($socket, $this->hybi10Encode($post)) or die('error:'.$errno.':'.$errstr);

		/**
		 * Read socket output
		 */
		fread($socket, 2000);
	}

	/**
	 * Encode post
	 */
	private function hybi10Encode($payload, $type = 'text', $masked = true) {
		$frameHead = array();
		$frame = '';
		$payloadLength = strlen($payload);

		switch ($type) {
			case 'text':
				// first byte indicates FIN, Text-Frame (10000001):
				$frameHead[0] = 129;
				break;

			case 'close':
				// first byte indicates FIN, Close Frame(10001000):
				$frameHead[0] = 136;
				break;

			case 'ping':
				// first byte indicates FIN, Ping frame (10001001):
				$frameHead[0] = 137;
				break;

			case 'pong':
				// first byte indicates FIN, Pong frame (10001010):
				$frameHead[0] = 138;
				break;
		}

		// set mask and payload length (using 1, 3 or 9 bytes)
		if ($payloadLength > 65535) {
			$payloadLengthBin = str_split(sprintf('%064b', $payloadLength), 8);
			$frameHead[1] = ($masked === true) ? 255 : 127;
			for ($i = 0; $i < 8; $i++) {
				$frameHead[$i + 2] = bindec($payloadLengthBin[$i]);
			}

			// most significant bit MUST be 0 (close connection if frame too big)
			if ($frameHead[2] > 127) {
				$this->close(1004);
				return false;
			}
		} elseif ($payloadLength > 125) {
			$payloadLengthBin = str_split(sprintf('%016b', $payloadLength), 8);
			$frameHead[1] = ($masked === true) ? 254 : 126;
			$frameHead[2] = bindec($payloadLengthBin[0]);
			$frameHead[3] = bindec($payloadLengthBin[1]);
		} else {
			$frameHead[1] = ($masked === true) ? $payloadLength + 128 : $payloadLength;
		}

		// convert frame-head to string:
		foreach (array_keys($frameHead) as $i) {
			$frameHead[$i] = chr($frameHead[$i]);
		}

		if ($masked === true) {
			// generate a random mask:
			$mask = array();
			for ($i = 0; $i < 4; $i++) {
				$mask[$i] = chr(rand(0, 255));
			}

			$frameHead = array_merge($frameHead, $mask);
		}
		$frame = implode('', $frameHead);
		// append payload to frame:
		for ($i = 0; $i < $payloadLength; $i++) {
			$frame .= ($masked === true) ? $payload[$i] ^ $mask[$i % 4] : $payload[$i];
		}

		return $frame;
	}

}

$settings = array(
	'host' => '0.0.0.0',
	'port' => 10001,
	'local' => 'http://liamka.me',
);

$Client = new Client($settings);
$Client->run();