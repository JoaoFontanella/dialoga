(function () {
  'use strict';
  angular.module('dialoga').component('dialogaResponse', {
    bindings: { response: '<', busy: '<', onAction: '&' },
    templateUrl: ['dialogaConfig', function (c) { return c.assetBaseUrl + 'templates/response.html'; }],
    controller: ['dialogaFormat', '$timeout', function (format, $timeout) {
      const vm = this;
      vm.format = format;
      vm.$onChanges = function () {
        vm.label = { resposta: 'Resposta', recusa: 'Não sei', esclarecer: 'Uma pergunta antes' }[vm.response.tipo];
        vm.maximum = Math.max(1, ...vm.response.barras.map(b => b.valor));
      };
      vm.copy = function () {
        const r = vm.response;
        const text = [r.destaque, r.texto, ...r.tabela.map(l => l.produto + ': ' + l.valor + ' · ' + l.quantidade + ' un.'), r.origem].filter(Boolean).join('\n');
        if (!navigator.clipboard || !navigator.clipboard.writeText) { vm.copyStatus = 'Selecione o texto da resposta para copiar.'; return; }
        navigator.clipboard.writeText(text).then(function () { $timeout(function () { vm.copyStatus = 'Resposta copiada.'; }); }).catch(function () { $timeout(function () { vm.copyStatus = 'Não foi possível copiar. Selecione o texto da resposta.'; }); });
      };
    }]
  });
})();
