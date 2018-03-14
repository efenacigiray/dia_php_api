<?php

include ('dia.php');

$dia = new Dia();

if (!$dia->login('demo', 'demo');) {
	exit('Unable to login');
}

$products = $dia->list_products();