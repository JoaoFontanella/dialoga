/* Configuração pública. Pode ser sobrescrita antes deste arquivo pela view PHP. */
(function (window) {
  'use strict';
  window.DIALOGA_CONFIG = Object.assign({
    assetBaseUrl: 'assets/',
    apiBaseUrl: 'index.php/api',
    timeoutMs: 15000
  }, window.DIALOGA_CONFIG || {});
})(window);
