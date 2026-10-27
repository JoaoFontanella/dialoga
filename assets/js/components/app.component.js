(function () {
  'use strict';
  angular.module('dialoga').component('dialogaApp', {
    templateUrl: ['dialogaConfig', function (c) { return c.assetBaseUrl + 'templates/app.html'; }],
    controller: ['dialogaApi', 'dialogaConfig', 'dialogaFormat', '$timeout', '$document', function (api, config, format, $timeout, $document) {
      const vm = this;
      let sequence = 0;
      vm.assets = config.assetBaseUrl;
      vm.format = format;
      vm.messages = [];
      vm.history = [];
      vm.busy = false;
      vm.prompt = '';
      vm.menuOpen = false;
      vm.helpOpen = false;
      vm.theme = 'claro';
      vm.announcement = '';
      try { vm.theme = localStorage.getItem('dialoga-tema') === 'escuro' ? 'escuro' : 'claro'; } catch (_) {}
      vm.$onInit = function () { vm.loadStatus(); };
      vm.loadStatus = function () {
        vm.statusLoading = true;
        vm.statusError = false;
        api.status().then(function (s) { vm.status = s; }).catch(function () { vm.status = null; vm.statusError = true; }).finally(function () { vm.statusLoading = false; });
      };
      vm.toggleTheme = function () {
        vm.theme = vm.theme === 'claro' ? 'escuro' : 'claro';
        try { localStorage.setItem('dialoga-tema', vm.theme); } catch (_) {}
      };
      vm.newConversation = function () {
        if (vm.busy) return;
        vm.messages = [];
        vm.prompt = '';
        vm.currentId = null;
        vm.menuOpen = false;
        vm.announcement = 'Nova conversa iniciada. O histórico desta sessão foi mantido.';
        focusInput();
      };
      function focusInput() {
        $timeout(function () { const input = $document[0].getElementById('pergunta'); if (input) input.focus(); }, 0, false);
      }
      function scrollToMessage(id) {
        $timeout(function () {
          const el = $document[0].getElementById('turn-' + id);
          const panel = $document[0].getElementById('conversa');
          if (el && panel) panel.scrollTop = Math.max(0, el.offsetTop - panel.offsetTop - 24);
        }, 20, false);
      }
      vm.visit = function (entry) {
        if (vm.busy) return;
        vm.messages = entry.thread;
        vm.currentId = entry.id;
        vm.menuOpen = false;
        scrollToMessage(entry.id);
      };
      vm.ask = function (text, context) {
        text = String(text || '').trim();
        if (vm.busy || !text) return;
        if (text.length > 500) { vm.inputError = 'Use até 500 caracteres para a sua pergunta.'; focusInput(); return; }
        vm.inputError = '';
        vm.prompt = '';
        vm.menuOpen = false;
        const turn = { id: ++sequence, question: text, context: angular.copy(context || null), loading: Boolean(vm.status) };
        // Preserva uma conversa ao iniciar outra pelo botão da lateral.
        vm.messages.push(turn);
        const history = { id: turn.id, question: text, thread: vm.messages };
        vm.history.unshift(history);
        vm.currentId = turn.id;
        scrollToMessage(turn.id);
        if (!vm.status) {
          vm.announcement = 'Mensagem adicionada à conversa.';
          focusInput();
          return;
        }
        vm.busy = true;
        vm.announcement = 'Consultando as vendas.';
        const started = Date.now();
        api.perguntar(text, context).then(function (r) {
          turn.response = r;
          turn.elapsed = r.tempo_ms === null ? Date.now() - started : r.tempo_ms;
          vm.announcement = (r.tipo === 'recusa' ? 'Não sei. ' : r.tipo === 'esclarecer' ? 'Uma pergunta antes. ' : 'Resposta. ') + (r.destaque ? r.destaque + '. ' : '') + r.texto;
        }).catch(function (error) {
          turn.error = error && error.status === -1 ? 'A consulta demorou mais que o esperado. Tente novamente.' : 'Não consegui consultar os dados agora. Verifique a conexão e tente novamente.';
          vm.announcement = turn.error;
        }).finally(function () {
          turn.loading = false;
          vm.busy = false;
          scrollToMessage(turn.id);
        });
      };
      vm.retry = function (turn) { vm.ask(turn.question, turn.context); };
      vm.action = function (action) { vm.ask(action.pergunta, action.contexto); };
      vm.keydown = function (event) {
        if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
          event.preventDefault();
          vm.ask(vm.prompt);
        }
      };
      vm.closePanels = function () { vm.menuOpen = false; vm.helpOpen = false; };
    }]
  });

  angular.module('dialoga').directive('dialogaFocusTrap', ['$timeout', '$document', function ($timeout, $document) {
    return {
      restrict: 'A',
      link: function (scope, el, attrs) {
        let previous;
        function keydown(e) {
          if (e.key !== 'Tab') return;
          const nodes = Array.from(el[0].querySelectorAll('button:not(:disabled), a[href], input, textarea, [tabindex="0"]')).filter(n => n.getClientRects().length);
          if (!nodes.length) { e.preventDefault(); return; }
          const first = nodes[0], last = nodes[nodes.length - 1];
          if (e.shiftKey && $document[0].activeElement === first) { e.preventDefault(); last.focus(); }
          else if (!e.shiftKey && $document[0].activeElement === last) { e.preventDefault(); first.focus(); }
        }
        const unwatch = scope.$watch(attrs.dialogaFocusTrap, function (active) {
          el.off('keydown', keydown);
          if (active) {
            previous = $document[0].activeElement;
            el.on('keydown', keydown);
            $timeout(function () { const first = el[0].querySelector('button:not(:disabled), [tabindex="0"]'); if (first) first.focus(); }, 0, false);
          } else if (previous && previous.isConnected) { previous.focus(); previous = null; }
        });
        scope.$on('$destroy', function () { unwatch(); el.off('keydown', keydown); if (previous && previous.isConnected) previous.focus(); });
      }
    };
  }]);
})();
