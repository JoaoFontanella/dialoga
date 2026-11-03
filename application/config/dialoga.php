<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'third_party/dialoga/env.php';

// Confira os nomes e limites no AI Studio ou com: php index.php ferramentas modelos
$modelos_env = trim(dialoga_env('GEMINI_MODELOS', ''));

$config['gemini'] = array(
    'chave'    => dialoga_env('GEMINI_API_KEY', ''),
    'endpoint' => 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent',
    'modelos'  => $modelos_env !== ''
        ? array_map('trim', explode(',', $modelos_env))
        : array(
            'gemini-3.5-flash-lite',   // principal: rápido e barato, basta para interpretar
            'gemini-3.1-flash-lite',
            'gemini-3.5-flash',
            'gemini-2.5-flash-lite',   // geração anterior, último recurso
        ),
    'timeout_tentativa' => 7,
    'prazo_total'       => 11,
    'temperatura'       => 0.1,
    'max_tokens'        => 2048,
    'pausas' => array(
        'quota'        => 60,     // 429 por minuto; 429 diário vira 1 h
        'indisponivel' => 3600,   // 404: modelo descontinuado
        'sobrecarga'   => 30,     // 500, 503, 504
    ),
    'ca_bundle' => dialoga_env('GEMINI_CA_BUNDLE', ''),
);

// Vazio = hoje. Congela a demonstração com DIALOGA_HOJE=AAAA-MM-DD
$config['hoje'] = dialoga_env('DIALOGA_HOJE', '');

$config['periodo_padrao'] = array('tipo' => 'relativo', 'valor' => 'mes_passado');

$config['prompt_interpretador'] = APPPATH.'prompts/interpretador.md';

$config['nome_base'] = 'Vendas · ERP demonstração';

$config['usuario'] = array(
    'nome'  => dialoga_env('DIALOGA_USUARIO', 'Marcos'),
    'cargo' => dialoga_env('DIALOGA_CARGO', 'Diretor comercial'),
);
