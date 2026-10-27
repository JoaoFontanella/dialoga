(function () {
  'use strict';
  angular.module('dialoga', [])
    .constant('dialogaConfig', window.DIALOGA_CONFIG)
    .config(['$compileProvider', function ($compileProvider) {
      $compileProvider.debugInfoEnabled(false);
      $compileProvider.aHrefSanitizationWhitelist(/^\s*(https?|mailto|tel):|^\s*[^:]+$/i);
    }])
    .factory('dialogaFormat', function () {
      const money = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
      return {
        reais: function (n) { return money.format(n); },
        compacto: function (n) {
          if (Math.abs(n) >= 1000000) return 'R$ ' + (n / 1000000).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + ' mi';
          if (Math.abs(n) >= 10000) return 'R$ ' + Math.round(n / 1000).toLocaleString('pt-BR') + ' mil';
          return money.format(n);
        },
        data: function (s) { return typeof s === 'string' ? s.split('-').reverse().join('/') : ''; },
        numero: function (n) { return Number(n || 0).toLocaleString('pt-BR'); },
        percentual: function (n) { return (Number(n || 0) * 100).toFixed(1).replace('.', ',') + '%'; }
      };
    });
})();
