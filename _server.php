<?php

class Server
{
	public function __construct($settings) {
		$this->settings = $settings;
	}

	/**
	 * Server constructor
	 */
	public function deploy() {

		/**
		 * Create TCP/IP sream socket
		 */
		$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

		/**
		 * Reuseable port
		 */
		socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);

		/**
		 * Bind socket to specified host
		 */

		socket_bind($socket, 0, $this->settings['port']);

		/**
		 * Listen to port
		 */
		socket_listen($socket);

		/**
		 * Create & add listning socket to the list
		 */
		global $clients;
		$clients = array($socket);

		/**
		 * Loooooooooooop
		 */
		while (true) {

			/**
			 * Manage multipal connections
			 */
			$changed = $clients;

			/**
			 * Returns the socket resources in $changed array
			 */
			socket_select($changed, $null, $null, 0, 10);

			/**
			 * Check for new socket
			 */
			if (in_array($socket, $changed)) {
				$socket_new = socket_accept($socket);								//accpet new socket
				$clients[] = $socket_new; 											//add socket to client array
				
				$header = socket_read($socket_new, 1024); 							//read data sent by the socket
				$this->perform_handshaking($header, $socket_new, $this->settings['host'], $this->settings['port']); //perform websocket handshake

				/**
				 * Make room for new socket
				 */
				$found_socket = array_search($socket, $changed);
				unset($changed[$found_socket]);
			}
			
			/**
			 * Loop through all connected sockets
			 */
			foreach ($changed as $changed_socket) {	

				/**
				 * Check for any incomming data
				 */
				while(socket_recv($changed_socket, $buf, 1024, 0) >= 1)
				{
					$new_post = $this->unmask($buf);							//unmask data
					if(strlen($new_post) > 10) {
						$post = json_decode($new_post);						//json decode 
						$post_title = $post->title;									//post title
						$post_url = $post->url;										//post url
						$post_hubs = $post->hubs;									//post hubs

						/**
						 * Prepare data to be sent to client
						 */
						if($post_title > NULL) {
							$response_text = $this->mask(json_encode(array('title'=>$post_title, 'url'=>$post_url, 'hubs'=>$post_hubs)));
							$this->send_message($response_text);					//send data
						}
						break 2;													//exist this loop
					}

				}
				
				$buf = @socket_read($changed_socket, 1024, PHP_NORMAL_READ);

				/**
				 * Check disconnected client
				 */
				if ($buf === false) {
					// remove client for $clients array
					/**
					 * Remove client for $clients array
					 */
					$found_socket = array_search($changed_socket, $clients);
					socket_getpeername($changed_socket, $ip);
					unset($clients[$found_socket]);
				}
			}
		}

		/**
		 * Close the listening socket
		 */
		socket_close($socket);
	}

	/**
	 * Send message function
	 */
	private function send_message($msg)
	{
		global $clients;
		foreach($clients as $changed_socket)
		{
			@socket_write($changed_socket,$msg,strlen($msg));
		}
		return true;
	}

	/**
	 * Unmask incoming framed message
	 */
	private function unmask($text) {
		$length = ord($text[1]) & 127;
		if($length == 126) {
			$masks = substr($text, 4, 4);
			$data = substr($text, 8);
		}
		elseif($length == 127) {
			$masks = substr($text, 10, 4);
			$data = substr($text, 14);
		}
		else {
			$masks = substr($text, 2, 4);
			$data = substr($text, 6);
		}
		$text = "";
		for ($i = 0; $i < strlen($data); ++$i) {
			$text .= $data[$i] ^ $masks[$i%4];
		}
		return $text;
	}

	/**
	 * Encode message for transfer to client
	 */
	private function mask($text)
	{
		$b1 = 0x80 | (0x1 & 0x0f);
		$length = strlen($text);
		
		if($length <= 125)
			$header = pack('CC', $b1, $length);
		elseif($length > 125 && $length < 65536)
			$header = pack('CCn', $b1, 126, $length);
		elseif($length >= 65536)
			$header = pack('CCNN', $b1, 127, $length);
		return $header.$text;
	}

	/**
	 * Handshake
	 */
	private function perform_handshaking($receved_header,$client_conn, $host, $port)
	{
		$headers = array();
		$lines = preg_split("/\r\n/", $receved_header);
		foreach($lines as $line)
		{
			$line = chop($line);
			if(preg_match('/\A(\S+): (.*)\z/', $line, $matches))
			{
				$headers[$matches[1]] = $matches[2];
			}
		}

		$secKey = $headers['Sec-WebSocket-Key'];
		$secAccept = base64_encode(pack('H*', sha1($secKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
		/**
		 * Handshaking header
		 */
		$upgrade  = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
		"Upgrade: websocket\r\n" .
		"Connection: Upgrade\r\n" .
		"WebSocket-Origin: $host\r\n" .
		"WebSocket-Location: ws://$host:$port\r\n".
		"Sec-WebSocket-Accept:$secAccept\r\n\r\n";
		socket_write($client_conn,$upgrade,strlen($upgrade));
	}

	/**
	 * Print a text to the terminal
	 * @param $text the text to display
	 * @param $exit if true, the process will exit 
	 */
	public function console($text) {
		echo $text = date('[Y-m-d H:i:s] ').$text."\r\n";
	}

}

$settings = array(
	'host' => '0.0.0.0',
	'port' => 10001,
	'null' => NULL,
);

$Server = new Server($settings);
$Server->deploy();