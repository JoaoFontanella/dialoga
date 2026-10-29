(function () {
  'use strict';
  angular.module('dialoga').factory('dialogaApi', ['$q', 'httpAdapter', function ($q, http) {
    function normalizar(r) {
      if (!r || !['resposta', 'recusa', 'esclarecer'].includes(r.tipo) || typeof r.texto !== 'string') {
        return $q.reject({ message: 'A resposta recebida está em um formato inesperado.' });
      }
      return {
        tipo: r.tipo,
        texto: r.texto,
        destaque: typeof r.destaque === 'string' ? r.destaque : '',
        origem: typeof r.origem === 'string' ? r.origem : '',
        contexto: r.contexto || null,
        tempo_ms: Number.isFinite(r.tempo_ms) ? r.tempo_ms : null,
        via: r.via || '',
        barras: (Array.isArray(r.barras) ? r.barras : []).filter(b => b && typeof b.rotulo === 'string' && Number.isFinite(b.valor) && b.valor >= 0),
        tabela: (Array.isArray(r.tabela) ? r.tabela : []).filter(l => l && typeof l.produto === 'string' && typeof l.valor === 'string' && Number.isFinite(l.quantidade) && Number.isFinite(l.participacao) && l.participacao >= 0 && l.participacao <= 1).map((l, i) => Object.assign({}, l, { posicao: i + 1 })),
        acoes: (Array.isArray(r.acoes) ? r.acoes : []).filter(a => a && typeof a.rotulo === 'string' && typeof a.pergunta === 'string').map(a => ({ rotulo: a.rotulo, pergunta: a.pergunta, contexto: a.contexto || r.contexto || null }))
      };
    }
    return {
      status: function () {
        return http.status().then(function (s) {
          if (!s || !s.base || !/^\d{4}-\d{2}-\d{2}$/.test(s.hoje) || !['inicio', 'fim'].every(k => s.base[k] === null || /^\d{4}-\d{2}-\d{2}$/.test(s.base[k]))) return $q.reject({ message: 'Não foi possível identificar a cobertura da base.' });
          return s;
        });
      },
      perguntar: function (q, contexto) { return http.perguntar(q, contexto).then(normalizar); }
    };
  }]);
})();
