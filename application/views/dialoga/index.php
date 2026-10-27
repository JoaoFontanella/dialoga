<?php
/** View de apresentação para CodeIgniter 3. Não contém consultas nem lógica de negócio.
 * Requer URL Helper e assets/ publicado na raiz pública da aplicação.
 * O controller futuro pode fornecer $dialoga_config, contendo somente configuração pública.
 */
defined('BASEPATH') OR exit('No direct script access allowed');
$public_config = array_merge(array(
    'assetBaseUrl' => base_url('assets/'),
    'apiBaseUrl' => site_url('api'),
    'timeoutMs' => 15000
), isset($dialoga_config) && is_array($dialoga_config) ? array_intersect_key($dialoga_config, array_flip(array('apiBaseUrl', 'assetBaseUrl', 'timeoutMs'))) : array());
$asset_url = rtrim($public_config['assetBaseUrl'], '/') . '/';
$safe_url = function ($relative) use ($asset_url) { return htmlspecialchars($asset_url . $relative, ENT_QUOTES, 'UTF-8'); };
?>
<!doctype html>
<html lang="pt-BR" ng-app="dialoga">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dialoga · Pergunte. Seu negócio responde.</title>
  <link rel="icon" href="<?= $safe_url('marca/favicon.png') ?>">
  <link rel="stylesheet" href="<?= $safe_url('css/app.css') ?>">
</head>
<body>
  <dialoga-app ng-cloak></dialoga-app>
  <noscript><p class="boot-message">Ative o JavaScript para usar a Dialoga.</p></noscript>
  <script>window.DIALOGA_CONFIG = <?= json_encode($public_config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?>;</script>
  <?php foreach (array('vendor/angular-1.8.3.min.js', 'js/app.config.js', 'js/app.module.js', 'js/services/http-adapter.service.js', 'js/services/dialoga-api.service.js', 'js/components/response.component.js', 'js/components/app.component.js') as $script): ?>
  <script src="<?= $safe_url($script) ?>"></script>
  <?php endforeach; ?>
</body>
</html>
