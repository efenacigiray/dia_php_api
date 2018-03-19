<?php
include(__DIR__ . '/dia.php');

$config = json_decode(file_get_contents(__DIR__ . '/config.json'), true);
$mysqli = new mysqli($config['mysql_host'], $config['mysql_user'], $config['mysql_password'], $config['mysql_db']);

$query = $mysqli->prepare('SELECT `key`, `value` FROM setting WHERE code = "module_dia"');
$query->execute();
$result = $query->get_result();

$dia_api_settings = array();

while ($row = $result->fetch_assoc()) {
	$dia_api_settings[$row['key']] = $row['value'];
}

$dia = new Dia($dia_api_settings, 'False', 'tr');

$start = microtime(true);
if (!$dia->login()) {
	exit('Unable to login');
}
echo 'Logged in successfully in: ' . (microtime(true) - $start) . PHP_EOL;
// Check connection
if (mysqli_connect_errno()) {
	exit("Failed to connect to MySQL: " . mysqli_connect_error());
}

$opencart_products = array();
$start = microtime(true);
$products = $dia->list_products(1, array(), 3);
echo 'Listed products successfully in: ' . (microtime(true) - $start) . PHP_EOL;

foreach ($products as $product) {
	//check if product already exists
	$start = microtime(true);
	$query = $mysqli->prepare('SELECT product_id, quantity FROM product WHERE mpn = ? LIMIT 1');
	$query->bind_param('s', $product['_key']);

	$query->execute();

	$result = $query->get_result();

	if ($result->num_rows > 0) {
		//this item already exists, update price and stock
		while ($row = $result->fetch_assoc()) {
			$query = $mysqli->prepare('UPDATE product SET quantity = ?, price = ?, sku = ? WHERE product_id = ? AND mpn = ? LIMIT 1');
			$query->bind_param('isdsi', $product['fiili_stok'], $product['fiyat1'], $product['stokkartkodu'], $row['product_id'], $product['_key']);
			$query->execute();

			$query = $mysqli->prepare('UPDATE product_description SET name = ? WHERE product_id = ? LIMIT 1');
			$query->bind_param('si', $product['aciklama'], $row['product_id']);
			$query->execute();

			if ($query->affected_rows == 1) {
				echo $row['product_id'] . '-' . $product['_key'] . ' is synced successfully in: ' . microtime(true) - $start . PHP_EOL;
			}
		}
	}
	
	$query->close();
}

$mysqli->close();