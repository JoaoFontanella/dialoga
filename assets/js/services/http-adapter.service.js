(function () {
  'use strict';
  angular.module('dialoga').factory('httpAdapter', ['$http', 'dialogaConfig', function ($http, config) {
    const base = config.apiBaseUrl.replace(/\/+$/, '');
    return {
      status: function () { return $http.get(base + '/status', { timeout: config.timeoutMs }).then(r => r.data); },
      perguntar: function (pergunta, contexto, conversaId) {
        return $http.post(base + '/perguntar', { pergunta: pergunta, contexto: contexto || null, conversa_id: conversaId || null }, { timeout: config.timeoutMs }).then(r => r.data);
      },
      historico: function () { return $http.get(base + '/historico', { timeout: config.timeoutMs }).then(r => r.data); },
      conversa: function (id) { return $http.get(base + '/conversa/' + encodeURIComponent(id), { timeout: config.timeoutMs }).then(r => r.data); }
    };
  }]);
})();
