(function () {
  'use strict';
  angular.module('dialoga').factory('httpAdapter', ['$http', 'dialogaConfig', function ($http, config) {
    const base = config.apiBaseUrl.replace(/\/+$/, '');
    return {
      status: function () { return $http.get(base + '/status', { timeout: config.timeoutMs }).then(r => r.data); },
      perguntar: function (pergunta, contexto) {
        return $http.post(base + '/perguntar', { pergunta: pergunta, contexto: contexto || null }, { timeout: config.timeoutMs }).then(r => r.data);
      }
    };
  }]);
})();
