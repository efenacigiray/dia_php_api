<?php
include(__DIR__ . '/dia.php');

$config = json_decode(file_get_contents(__DIR__ . '/config.json'), true);

$dia = new Dia(false, 'tr');

if (!$dia->login('demo', 'demo', 34)) {
	exit('Unable to login');
}

$mysqli = new mysqli($config['mysql_host'], $config['mysql_user'], $config['mysql_password'], $config['mysql_db']);

// Check connection
if (mysqli_connect_errno()) {
	exit("Failed to connect to MySQL: " . mysqli_connect_error());
}

$opencart_products = array();
$products = $dia->list_products(1, array(), 3);

foreach ($products as $product) {
	$opencart_products[] = array (
		'sku' => $product['stokkartkodu'],
		'mpn' => $product['_key'],
		'title' => $product['aciklama'],
		'stock' => $product['fiili_stok'],
		'price' => $product['fiyat1']
	);

	//check if product already exists
	$query = $mysqli->prepare('SELECT product_id, quantity FROM product WHERE mpn = ? LIMIT 1');
	$query->bind_param('s', $product['_key']);
	echo $product['_key'];

	$query->execute();

	$result = $query->get_result();

	if ($result->num_rows > 0) {
		//this item already exists, update price and stock
		while ($row = $result->fetch_assoc()) {
			var_dump($row);
		}
	}
	
	$query->close();
}

$mysqli->close();