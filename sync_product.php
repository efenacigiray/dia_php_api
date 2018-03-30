<?php
$start = microtime(true);
include(__DIR__ . '/dia.php');

$config = json_decode(file_get_contents(__DIR__ . '/config.json'), true);

include_once($config['opencart_config']);
$image_storage = DIR_IMAGE . 'dia_api/';

if (!file_exists($image_storage)) {
    mkdir($image_storage, 0777, true);
}

$log_file = DIR_STORAGE . 'dia_log.txt';
touch($log_file);

$mysqli = new mysqli($config['mysql_host'], $config['mysql_user'], $config['mysql_password'], $config['mysql_db']);

// Check connection
if (mysqli_connect_errno()) {
	write_log("Failed to connect to MySQL: " . mysqli_connect_error(), $log_file);
	exit();
}

$mysqli->set_charset('utf8');

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

//get manufacturers
$query = $mysqli->prepare('SELECT * FROM manufacturer');
if (!$query->execute()) {
	write_log("MySQL Error: " . $query->error, $log_file);
	exit();
}

$result = $query->get_result();
$manufacturers = array();

while ($row = $result->fetch_assoc()) {
	$manufacturers[$row['manufacturer_id']] = $row['name'];
}

write_log('Logged in successfully in: ' . (microtime(true) - $start), $log_file);

$opencart_products = array();
$start = microtime(true);
$products = $dia->list_products(1, array(), 500);
write_log('Listed products successfully in: ' . (microtime(true) - $start), $log_file);

foreach ($products as $product) {
	//check if product already exists
	$start = microtime(true);

	$image_path = $image_storage . pathinfo($product['aws_url'], PATHINFO_BASENAME);

	if (!empty($product['aws_url']) && !file_exists($image_path)) {
		write_log('Downloading image: ' . $product['aws_url'], $log_file);
		//image is available and does not exist on our storage, lets download it
		copy($product['aws_url'], $image_path);
		$image_path = 'dia_api/' . str_replace($image_storage, '', $image_path);
	} else {
		// there is no image available from dia, don't update it
		$image_path = $row['image'];
	}

	$query = $mysqli->prepare('SELECT product_id, quantity, image FROM product WHERE mpn = ? LIMIT 1');
	$query->bind_param('s', $product['_key']);

	if (!$query->execute()) {
		write_log("MySQL Error: " . $query->error, $log_file);
		exit();
	}

	$result = $query->get_result();
	
	$manufacturer_id = array_search($product['marka'], $manufacturers);
	$manufacturer_id = $manufacturer_id ? $manufacturer_id : 0;

	if ($result->num_rows > 0) {
		//this item already exists, update price and stock
		while ($row = $result->fetch_assoc()) {
			$query = $mysqli->prepare('UPDATE product SET quantity = ?, price = ?, sku = ?, model = ?, image = ?, manufacturer_id = ?, date_modified = NOW() WHERE product_id = ? AND mpn = ? LIMIT 1');
			$query->bind_param('idsssiis', $product['fiili_stok'], $product['fiyat1'], $product['stokkartkodu'], $product['stokkartkodu'], $image_path, $manufacturer_id, $row['product_id'], $product['_key']);
			
			if (!$query->execute()) {
				write_log("MySQL Error: " . $query->error, $log_file);
				exit();
			}

			$query = $mysqli->prepare('UPDATE product_description SET name = ? WHERE product_id = ? LIMIT 1');
			$query->bind_param('si', $product['aciklama'], $row['product_id']);
			
			if (!$query->execute()) {
				exit("MySQL Error: " . $query->error);
			}

			if ($query->affected_rows == 1) {
				write_log($row['product_id'] . '-' . $product['_key'] . ' is synced successfully in: ' . (microtime(true) - $start), $log_file);
			} else {
				write_log($row['product_id'] . '-' . $product['_key'] . ' is already up-to-date.', $log_file);
			}
		}
	} else {
		$stock_status = $config['opencart_stock_status_out_of_stock'];

		//item does not exist on storage
		$query = $mysqli->prepare('INSERT INTO product SET quantity = ?, price = ?, sku = ?, model = ?, mpn = ?, image = ?, date_available = NOW(), upc = "", ean = "", jan = "", isbn = "", tax_class_id = ?, date_added = NOW(), date_modified = NOW(), manufacturer_id = ?, stock_status_id = ?, location = "", store = 1');
		$query->bind_param('idssssiii', $product['fiili_stok'], $product['fiyat1'], $product['stokkartkodu'], $product['stokkartkodu'], $product['_key'], $image_path, $config['opencart_tax_class'], $manufacturer_id, $stock_status);
		
		if (!$query->execute()) {
			write_log("MySQL Error: " . $query->error, $log_file);
			exit();
		}

		$product_id = $mysqli->insert_id;
		write_log('Product ID: ' . $product_id, $log_file);

		$query = $mysqli->prepare('INSERT INTO product_description SET name = ?, description = ?, tag = ?, meta_title = ?, meta_description = ?, meta_keyword = ?, language_id = ?, product_id = ?');
		$query->bind_param('ssssssii', $product['aciklama'], $product['aciklama'], $product['aciklama'], $product['aciklama'], $product['aciklama'], $product['aciklama'], $config['opencart_language_id'], $product_id);
		
		if (!$query->execute()) {
			write_log("MySQL Error: " . $query->error, $log_file);
			exit();
		}

		if ($query->affected_rows == 1) {
			write_log($product_id . '-' . $product['_key'] . ' is inserted successfully in: ' . (microtime(true) - $start), $log_file);
		}
	}
	
	$query->close();
}

$mysqli->close();

function write_log($line, $log_file) {
	file_put_contents($log_file, $line . PHP_EOL, FILE_APPEND);
}