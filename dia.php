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
		$this->company_code = (int)$dia_api_settings['module_dia_company_code'];
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
		$request->scf_stokkart_listele->firma_kodu = $this->company_code;

		$request->scf_stokkart_listele->limit = $list_limit;
		$request->scf_stokkart_listele->offset = $offset;

		$request_body = json_encode($request);
		$request_url = $this->dia_server . Dia::DIA_SCF;

		$response = $this->curl_api($request_url, $request_body);

		if ($response['code'] == 200) {
			$response = $response['result'];
		} else {
			$response = array();
		}

		return $response;
	}

	public function add_customer($customer, $period = 1) {
		$request = new stdClass();
		$request->scf_carikart_ekle = new stdClass();

		$request->scf_carikart_ekle->donem_kodu = $period;
		$request->scf_carikart_ekle->session_id = $this->session_token;
		$request->scf_carikart_ekle->firma_kodu = $this->company_code;

		$request->scf_carikart_ekle->kart = new stdClass();
		$request->scf_carikart_ekle->kart->eposta = $customer['email'];
		$request->scf_carikart_ekle->kart->il = $customer['address']['city'];
		$request->scf_carikart_ekle->kart->carikartkodu = trim($customer['customer_id'] . '-' . $customer['lastname']);

		$address = new stdClass();
		$address->adres1 = $customer['address']['address_1'];
		$address->adres2 = $customer['address']['address_2'];
		$address->adresadi = $customer['address']['zone']['name'] . ' ' . $customer['address']['city'] .  ' Adres';
		$address->adrestipi = 'F';
		$address->anaadres = '1';
		$address->ceptel = $customer['telephone'];
		$address->ilce = $customer['address']['city'];
		$address->_key_sis_sehirler = new stdClass();
		$address->_key_sis_sehirler->sehiradi = strtoupper($customer['address']['zone']['name']);
		$address->kayitturu = 'SHS';
		$address->postakodu = $customer['address']['postcode'];
		$address->unvan = $customer['address']['firstname'] . ' ' . $customer['address']['lastname'];

		$request->scf_carikart_ekle->kart->m_adresler = array( $address );
		$request->scf_carikart_ekle->kart->unvan = $customer['firstname'] . ' ' . $customer['lastname'];

		$this->fill_empty($request->scf_carikart_ekle);

		$request_body = json_encode($request);
		$request_url = $this->dia_server . Dia::DIA_SCF;

		$response = $this->curl_api($request_url, $request_body);
		echo $response;
		return $request->scf_carikart_ekle->kart->carikartkodu;
	}

	private function fill_empty(&$scf_carikart_ekle) {
		$scf_carikart_ekle->kart->_key_crm_musteri_kaynak = 0;
		$scf_carikart_ekle->kart->_key_crm_musteri_sektorler = array();
		$scf_carikart_ekle->kart->_key_risklimitidovizi = new stdClass();
		$scf_carikart_ekle->kart->_key_risklimitidovizi_faturalanmamisirs = new stdClass();;
		$scf_carikart_ekle->kart->_key_risklimitidovizi_kendics = new stdClass();
		$scf_carikart_ekle->kart->_key_risklimitidovizi_mustericirolucs = new stdClass();
		$scf_carikart_ekle->kart->_key_risklimitidovizi_musterikendics = new stdClass();
		$scf_carikart_ekle->kart->_key_risklimitidovizi_teslimolmamissip = new stdClass();
		$scf_carikart_ekle->kart->_key_rpr_tasarim = 0;
		$scf_carikart_ekle->kart->_key_rpr_tasarim_irsaliye = 0;
		$scf_carikart_ekle->kart->_key_rpr_tasarim_siparis = 0;
		$scf_carikart_ekle->kart->_key_rpr_tasarim_teklif = 0;
		$scf_carikart_ekle->kart->_key_scf_odeme_plani = 0;
		$scf_carikart_ekle->kart->_key_scf_satiselemani = 0;
		$scf_carikart_ekle->kart->_key_sis_doviz = new stdClass();
		$scf_carikart_ekle->kart->_key_sis_ozelkod1 = 0;
		$scf_carikart_ekle->kart->_key_sis_ozelkod10 = 0;
		$scf_carikart_ekle->kart->_key_sis_ozelkod11 = 0;
		$scf_carikart_ekle->kart->_key_sis_ozelkod2 = 0;
		$scf_carikart_ekle->kart->_key_sis_ozelkod3 = 0;
		$scf_carikart_ekle->kart->_key_sis_ozelkod4 = 0;
		$scf_carikart_ekle->kart->_key_sis_ozelkod5 = 0;
		$scf_carikart_ekle->kart->_key_sis_ozelkod6 = 0;
		$scf_carikart_ekle->kart->_key_sis_ozelkod7 = 0;
		$scf_carikart_ekle->kart->_key_sis_ozelkod8 = 0;
		$scf_carikart_ekle->kart->_key_sis_ozelkod9 = 0;
		$scf_carikart_ekle->kart->_key_sis_resim = 0;
		$scf_carikart_ekle->kart->_key_sis_seviyekodu = 0;
		$scf_carikart_ekle->kart->_key_sis_sube = 0;
		$scf_carikart_ekle->kart->_key_sis_uyruk = 0;
		$scf_carikart_ekle->kart->_key_sis_vergidairesi = new stdClass();
		$scf_carikart_ekle->kart->ailesirano = "";
		$scf_carikart_ekle->kart->anneadi = "";
		$scf_carikart_ekle->kart->b2c_durum = "H";
		$scf_carikart_ekle->kart->b2c_loginsayisi = 0;
		$scf_carikart_ekle->kart->b2c_sonloginip = "";
		$scf_carikart_ekle->kart->b2c_sonlogintarihi = null;
		$scf_carikart_ekle->kart->babaadi = "";
		$scf_carikart_ekle->kart->bagkurno = "";
		$scf_carikart_ekle->kart->cariyedonusmetarihi = null;
		$scf_carikart_ekle->kart->ciltno = "";
		$scf_carikart_ekle->kart->cinsiyet = "E";
		$scf_carikart_ekle->kart->dogumtarihi = null;
		$scf_carikart_ekle->kart->dogumyeri = "";
		$scf_carikart_ekle->kart->dovizkurturu = 9;
		$scf_carikart_ekle->kart->durum = "A";
		$scf_carikart_ekle->kart->efaturasenaryosu = "0";
		$scf_carikart_ekle->kart->ekalan1 = "";
		$scf_carikart_ekle->kart->ekalan10 = "";
		$scf_carikart_ekle->kart->ekalan2 = "";
		$scf_carikart_ekle->kart->ekalan3 = "";
		$scf_carikart_ekle->kart->ekalan4 = "";
		$scf_carikart_ekle->kart->ekalan5 = "";
		$scf_carikart_ekle->kart->ekalan6 = "";
		$scf_carikart_ekle->kart->ekalan7 = "";
		$scf_carikart_ekle->kart->ekalan8 = "";
		$scf_carikart_ekle->kart->ekalan9 = "";
		$scf_carikart_ekle->kart->eksayisalalan1 = "0.000000";
		$scf_carikart_ekle->kart->evliliktarihi = null;
		$scf_carikart_ekle->kart->formbagoster = "t";
		$scf_carikart_ekle->kart->formbsgoster = "t";
		$scf_carikart_ekle->kart->ilgili = "";
		$scf_carikart_ekle->kart->indirimorani = "0.000000";
		$scf_carikart_ekle->kart->karaliste = "1";
		$scf_carikart_ekle->kart->karalistenedeni = "";
		$scf_carikart_ekle->kart->kayitno = "";
		$scf_carikart_ekle->kart->kimlikbelgeturu = "Y";
		$scf_carikart_ekle->kart->kimlikilce = "";
		$scf_carikart_ekle->kart->kimlikserino = "";
		$scf_carikart_ekle->kart->kisaaciklama = "";
		$scf_carikart_ekle->kart->m_bankahesaplari = array();
		$scf_carikart_ekle->kart->m_yetkililer = array();
		$scf_carikart_ekle->kart->mahalle = "";
		$scf_carikart_ekle->kart->muafiyet_baslangic = null;
		$scf_carikart_ekle->kart->muafiyet_belgeno = "";
		$scf_carikart_ekle->kart->muafiyet_bitis = null;
		$scf_carikart_ekle->kart->mustahsil_komisyon = "0.000000";
		$scf_carikart_ekle->kart->mustahsil_uygulansin = "f";
		$scf_carikart_ekle->kart->note = "";
		$scf_carikart_ekle->kart->ontanimlifiyat = "yok";
		$scf_carikart_ekle->kart->potansiyel = "H";
		$scf_carikart_ekle->kart->potansiyeleklemetarihi = null;
		$scf_carikart_ekle->kart->rehberde = "t";
		$scf_carikart_ekle->kart->riskislemi = "1";
		$scf_carikart_ekle->kart->riskislemi_faturalanmamisirs = "1";
		$scf_carikart_ekle->kart->riskislemi_kendics = "1";
		$scf_carikart_ekle->kart->riskislemi_mustericirolucs = "1";
		$scf_carikart_ekle->kart->riskislemi_musterikendics = "1";
		$scf_carikart_ekle->kart->riskislemi_teslimolmamissip = "1";
		$scf_carikart_ekle->kart->riskislemiirs = "1";
		$scf_carikart_ekle->kart->riskislemisip = "1";
		$scf_carikart_ekle->kart->riskkontrolu = "1";
		$scf_carikart_ekle->kart->risklimiti = "0.000000";
		$scf_carikart_ekle->kart->risklimiti_faturalanmamisirs = "0.000000";
		$scf_carikart_ekle->kart->risklimiti_kendics = "0.000000";
		$scf_carikart_ekle->kart->risklimiti_mustericirolucs = "0.000000";
		$scf_carikart_ekle->kart->risklimiti_musterikendics = "0.000000";
		$scf_carikart_ekle->kart->risklimiti_teslimolmamissip = "0.000000";
		$scf_carikart_ekle->kart->riskorani_kendicek = "100.000000";
		$scf_carikart_ekle->kart->riskorani_kendisenet = "100.000000";
		$scf_carikart_ekle->kart->riskorani_mustericekasil = "100.000000";
		$scf_carikart_ekle->kart->riskorani_mustericekciro = "100.000000";
		$scf_carikart_ekle->kart->riskorani_musterisenetasil = "100.000000";
		$scf_carikart_ekle->kart->riskorani_musterisenetciro = "100.000000";
		$scf_carikart_ekle->kart->sirano = "";
		$scf_carikart_ekle->kart->tckimlikno = "";
		$scf_carikart_ekle->kart->verginumarasi = "";
		$scf_carikart_ekle->kart->verilisnedeni = "";
		$scf_carikart_ekle->kart->verilistarihi = null;
		$scf_carikart_ekle->kart->weburl = "";
		$scf_carikart_ekle->kart->carikarttipi = 'AL';
		$scf_carikart_ekle->kart->carikayitturu = 'SHS';
		$scf_carikart_ekle->kart->dovizkuru = 'TL';
	}

	private function curl_api($url, $body, $options = array()) {
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