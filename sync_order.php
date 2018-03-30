<?php
$start = microtime(true);
include(__DIR__ . '/dia.php');

$config = json_decode(file_get_contents(__DIR__ . '/config.json'), true);

include_once($config['opencart_config']);

$log_file = DIR_STORAGE . 'dia_order_log.txt';
touch($log_file);

$mysqli = new mysqli($config['mysql_host'], $config['mysql_user'], $config['mysql_password'], $config['mysql_db']);

// Check connection
if (mysqli_connect_errno()) {
	write_log("Failed to connect to MySQL: " . mysqli_connect_error(), $log_file);
	exit();
}

$mysqli->set_charset('utf8');

// DIA init
$query = $mysqli->prepare('SELECT `key`, `value` FROM setting WHERE code = "module_dia"');
if (!$query->execute()) {
	write_log("MySQL Error: " . $query->error, $log_file);
	exit();
}

$result = $query->get_result();
$dia_api_settings = array();

while ($row = $result->fetch_assoc()) {
	$dia_api_settings[$row['key']] = $row['value'];
}

if ( !isset($dia_api_settings['module_dia_status']) || !$dia_api_settings['module_dia_status'] ) {
	write_log("DIA API sync is off!", $log_file);
	exit();
}

$dia = new Dia($dia_api_settings, 'False', 'tr');
if (!$dia->login()) {
	write_log("Unable to login!", $log_file);
	exit();
}

//zones
$query = $mysqli->prepare('SELECT * FROM zone;');
if (!$query->execute()) {
	write_log("MySQL Error: " . $query->error, $log_file);
	exit();
}

$result = $query->get_result();
$zones = array();

while ($row = $result->fetch_assoc()) {
	$zones[$row['zone_id']] = $row;
}

// customers
$query = $mysqli->prepare('SELECT * FROM customer;');
if (!$query->execute()) {
	write_log("MySQL Error: " . $query->error, $log_file);
	exit();
}

$result = $query->get_result();

while ($row = $result->fetch_assoc()) {
	var_dump($row);
	if (!empty($row['code'])) {
		// this customer already exists in DIA
		continue;
	}

	$query = $mysqli->prepare('SELECT * FROM address WHERE address_id = ? LIMIT 1');
	$query->bind_param('i', $row['address_id']);
	if (!$query->execute()) {
		write_log("MySQL Error: " . $query->error, $log_file);
		exit();
	}

	$address_result = $query->get_result();
	$row['address'] = $address_result->fetch_assoc();
	$row['address']['zone'] = $zones[$row['address']['zone_id']];

	$code = $dia->add_customer($row);

	$query = $mysqli->prepare('UPDATE customer SET code = ? WHERE customer_id = ?');
	$query->bind_param('si', $code, $row['customer_id']);
	if (!$query->execute()) {
		write_log("MySQL Error: " . $query->error, $log_file);
		exit();
	}
}

// lets get orders

function write_log($line, $log_file) {
	file_put_contents($log_file, $line . PHP_EOL, FILE_APPEND);
}