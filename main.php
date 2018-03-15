<?php

include ('dia.php');

$dia = new Dia(false, 'tr');

if (!$dia->login('demo', 'demo', 34)) {
	exit('Unable to login');
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
}