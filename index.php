<?php

use Dotenv\Dotenv;

require 'vendor/autoload.php';

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->required([
    'PAPERLESS_URL',
    'PAPERLESS_TOKEN',
    'CUSTOM_FIELD_BETRAG',
    'CUSTOM_FIELD_RECHNUNGSNUMMER',
    'DOCUMENT_TYPE_EINGANGSRECHNUNG',
    'DOCUMENT_TYPE_AUSGANGSRECHNUNG'
]);
$dotenv->safeLoad();

$app = new Bahuma\BahumaAbrechnung\App();
$app->run();
