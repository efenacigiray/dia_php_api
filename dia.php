<?php
class Dia { 
	const DIA_SIS = '/api/v3/sis/json';
	const DIA_SCF = '/api/v3/scf/json';

	private $session_token = '';
	private $company_code = '';
	private $dia_server = '';
	private $dia_user = '';
	private $dia_password = '';

	private $disconnect_same_user = 'False';
	private $language = 'tr';

	public function __construct($dia_api_settings, $disconnect_same_user = 'False', $language = 'tr') { 
		$this->company_code = $dia_api_settings['module_dia_company_code'];
		$this->dia_server = $dia_api_settings['module_dia_server'];
		$this->dia_user = $dia_api_settings['module_dia_user'];		
		$this->dia_password = $dia_api_settings['module_dia_password'];

		$this->disconnect_same_user = $disconnect_same_user;
		$this->language = $language;
	}

	public function login() {
		$request = new stdClass();
		$request->login = new stdClass();

		$request->login->username = $this->dia_user;
		$request->login->password = $this->dia_password;
		$request->login->lang = $this->language;
		$request->login->disconnect_same_user = $this->disconnect_same_user;

		$request_body = json_encode($request);
		$request_url = $this->dia_server . Dia::DIA_SIS;

		$response = $this->curl_api($request_url, $request_body);

		if ($response && isset($response['msg']) && strlen($response['msg']) == 32) {
			$this->session_token = $response['msg'];
			echo 'Succesfull Login: ' . $this->session_token . PHP_EOL;
			return true;
		} else {
			return false;
		}
	}

	public function list_products($period, $filters = array(), $list_limit = 100, $offset = 0) {
		$request = new stdClass();
		$request->scf_stokkart_listele = new stdClass();

		$request->scf_stokkart_listele->donem_kodu = $period;
		$request->scf_stokkart_listele->session_id = $this->session_token;
		$request->scf_stokkart_listele->firma_kodu = (int)$this->company_code;

		$request->scf_stokkart_listele->limit = $list_limit;
		$request->scf_stokkart_listele->offset = $offset;

		$request_body = json_encode($request);
		$request_url = $this->dia_server . Dia::DIA_SCF;

		$response = $this->curl_api($request_url, $request_body);

		if ($response['code'] == 200) {
			$response = $response['result'];
		} else {
			var_dump($response);
			$response = array();
		}

		return $response;
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