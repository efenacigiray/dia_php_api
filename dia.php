<?php
class Dia { 
	const DIA_API = 'http://diademo.dia.gen.tr';
	const DIA_SIS = '/api/v3/sis/json';
	const DIA_SCF = '/api/v3/scf/json';

	private static $session_token = '';

	private $list_limit = 100;
	private $disconnect_same_user = 'False';
	private $language = 'tr';

	public function __construct($disconnect_same_user = 'False', $language = 'tr', $list_limit = 100) { 
		$this->disconnect_same_user = $disconnect_same_user;
		$this->language = $language;
		$this->list_limit = $list_limit;
	}

	public function login($username, $password) {
		$request = new stdClass();
		$request->login = new stdClass();

		$request->login->username = $username;
		$request->login->password = $password;
		$request->login->lang = $this->language;
		$request->login->disconnect_same_user = $this->disconnect_same_user;

		$request_body = json_encode($request);
		$request_url = Dia::DIA_API . Dia::DIA_SIS;

		$response = $this->curl_api($request_url, $request_body);

		if ($response && isset($response['msg']) && strlen($response['msg']) == 32) {
			$session_token = $response['msg'];
			return true;
		} else {
			return false;
		}
	}

	public function list_products($limit = 0, $offset = 0) {

	}

	public function curl_api($url, $body, $options = array()) {
		$curl = curl_init();

		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

		curl_setopt($curl, CURLOPT_HTTPHEADER, array('application/json')); 
		curl_setopt_array($curl, $options);

		$response = curl_exec($curl);

		curl_close($curl);

		return json_decode($response, true);
	}
}