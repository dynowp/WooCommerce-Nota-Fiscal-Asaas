<?php

namespace NotaFiscalForAsaas\Config;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Config {
    const ASAAS_API_URL_PRODUCTION = 'https://api.asaas.com/api/v3/';
    const ASAAS_API_URL_SANDBOX     = 'https://sandbox.asaas.com/api/v3/';
}