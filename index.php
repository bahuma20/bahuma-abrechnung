<?php

use Dotenv\Dotenv;

require 'vendor/autoload.php';

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();
$dotenv->required([
    'PAPERLESS_URL',
    'PAPERLESS_TOKEN',
    'CUSTOM_FIELD_BETRAG',
    'CUSTOM_FIELD_RECHNUNGSNUMMER_EINGANGSRECHNUNG',
    'CUSTOM_FIELD_RECHNUNGSNUMMER_AUSGANGSRECHNUNG',
    'DOCUMENT_TYPE_EINGANGSRECHNUNG',
    'DOCUMENT_TYPE_AUSGANGSRECHNUNG'
]);


$app = new Bahuma\BahumaAbrechnung\App();
$app->run();
