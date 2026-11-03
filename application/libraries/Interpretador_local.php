<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Plano B sem IA: só a interpretação é por regras; a consulta continua indo ao banco. */
class Interpretador_local {

    protected $catalogo;

    protected $meses = array('janeiro' => 1, 'fevereiro' => 2, 'marco' => 3, 'abril' => 4, 'maio' => 5, 'junho' => 6,
        'julho' => 7, 'agosto' => 8, 'setembro' => 9, 'outubro' => 10, 'novembro' => 11, 'dezembro' => 12);

    public function definir_catalogo($catalogo)
    {
        $this->catalogo = $catalogo;
    }

    public function interpretar($pergunta, $anterior = NULL)
    {
        $t = ' '.$this->normalizar($pergunta).' ';

        if (preg_match('/ignore|instruc|prompt|sql|delete|drop |update |apague|altere/', $t))
        {
            return $this->recusa('eu só respondo sobre vendas por período, produto e filial', $t);
        }

        $continuacao = $anterior && preg_match('/^\s*(e|e no|e na|e em|e o|e a|agora)\b/', $t);
        if ( ! $continuacao && ! preg_match('/vend|fatur|pedid|produt|ranking|top |campe|compar|quanto|filial|nota|margem|lucro|custo|client|estoque|meta/', $t))
        {
            return $this->recusa('essa pergunta está fora do que eu consigo ler na base de vendas', $t);
        }

        $recusas = array(
            '/margem|lucro|custo|rentabilidade/'          => 'a margem e o lucro dependem do custo dos produtos e do frete, que não estão na base',
            '/frete|imposto/'                              => 'frete e impostos não estão na base',
            '/\bmetas?\b/'                               => 'as metas da empresa não estão na base',
            '/vendedor|representante/'                     => 'a base não registra quem fez cada venda',
            '/estoque/'                                    => 'nesta versão eu respondo sobre vendas, não sobre estoque',
            '/client/'                                     => 'nesta versão eu respondo sobre vendas por período, produto e filial, sem dados de clientes',
            '/devolu/'                                     => 'as devoluções não estão na base',
            '/por que|porque|motivo|culpa/'                => 'eu mostro os números, mas não explico as causas de um resultado',
            '/vamos vender|vai vender|previs|projec|devo |deveria|recomend/' => 'eu mostro o que já aconteceu; previsões e recomendações ficam com você',
        );
        foreach ($recusas as $padrao => $motivo)
        {
            if (preg_match($padrao, $t))
            {
                return $this->recusa($motivo, $t);
            }
        }

        $i = $continuacao ? $anterior : array(
            'intencao' => 'total_periodo', 'metrica' => NULL, 'periodo' => NULL, 'periodo_comparacao' => NULL,
            'filial' => NULL, 'agrupar_por' => NULL, 'produto' => NULL, 'ordenar_por' => 'valor', 'limite' => 5,
        );

        if (preg_match('/compar| x | versus | vs | contra | em relacao/', $t))
        {
            $i['intencao'] = 'comparacao_periodos';
        }
        elseif (preg_match('/mais vendid|top |ranking|campe|melhores/', $t))
        {
            $i['intencao'] = 'ranking_produtos';
            if (preg_match('/(?:top|os|as)\s+(\d{1,2})\b/', $t, $m))
            {
                $i['limite'] = (int) $m[1];
            }
        }
        elseif (preg_match('/por produto|cada produto/', $t))
        {
            $i['intencao'] = 'vendas_por_produto';
            $i['limite'] = 20;
        }
        if (preg_match('/quantidade|unidades|pecas/', $t))
        {
            $i['ordenar_por'] = 'quantidade';
        }
        elseif ($continuacao && preg_match('/valor|reais|faturamento/', $t))
        {
            $i['ordenar_por'] = 'valor';
        }
        if (preg_match('/por filial|cada filial|qual filial|filiais/', $t))
        {
            $i['agrupar_por'] = 'filial';
        }

        $genericas = array('standard', 'edition', 'series', 'gamer', 'mecanico');
        $pontos = array();
        $palavra_comum = array();
        foreach ($this->catalogo['produtos'] as $p)
        {
            foreach (preg_split('/\s+/', $this->normalizar($p['nome'])) as $palavra)
            {
                if (mb_strlen($palavra, 'UTF-8') >= 4 && ! in_array($palavra, $genericas, TRUE) && strpos($t, ' '.$palavra.' ') !== FALSE)
                {
                    $pontos[$p['nome']] = isset($pontos[$p['nome']]) ? $pontos[$p['nome']] + 1 : 1;
                    $palavra_comum[$p['nome']] = $palavra;
                }
            }
        }
        if ($pontos)
        {
            arsort($pontos);
            $nomes = array_keys($pontos);
            $empate = count($pontos) > 1 && $pontos[$nomes[0]] === $pontos[$nomes[1]];
            $i['produto'] = $empate ? $palavra_comum[$nomes[0]] : $nomes[0];
            $i['produto_id'] = NULL;
            if ($i['intencao'] !== 'comparacao_periodos')
            {
                $i['intencao'] = 'vendas_por_produto';
            }
        }
        elseif (preg_match('/\bvend(?:eu|eram|emos|e|ido)\s+(?:o|a|os|as)\s+([a-z0-9]+)(?:\s+(?!(?:em|no|na|do|da|de|nesta|neste|esta|este|ontem|hoje)\b)([a-z0-9]+))?/', $t, $m))
        {
            $nao_produto = array('mes', 'ano', 'semana', 'dia', 'filial', 'loja', 'total', 'mais', 'quantidade', 'empresa',
                'gente', 'matriz', 'centro', 'tubarao', 'ararangua', 'criciuma', 'valor', 'pedido', 'pedidos');
            if ( ! in_array($m[1], $nao_produto, TRUE))
            {
                $i['produto'] = trim($m[1].(isset($m[2]) ? ' '.$m[2] : ''));
                $i['produto_id'] = NULL;
                $i['intencao'] = 'vendas_por_produto';
            }
        }

        if (preg_match('/fatur|nota/', $t))
        {
            $i['metrica'] = 'faturadas';
        }
        elseif (preg_match('/fechad|fechamos|carteira/', $t))
        {
            $i['metrica'] = 'fechados';
        }

        foreach ($this->catalogo['filiais'] as $f)
        {
            $nomes = array($this->normalizar($f['nome']), $this->normalizar($f['cidade']), $f['slug']);
            foreach ($nomes as $n)
            {
                if (strpos($t, ' '.$n.' ') !== FALSE)
                {
                    $i['filial'] = $f['slug'];
                    $i['agrupar_por'] = NULL;
                }
            }
        }

        $periodos = $this->periodos($t);
        if ($periodos)
        {
            $i['periodo'] = $periodos[0];
            $i['periodo_comparacao'] = isset($periodos[1]) ? $periodos[1] : NULL;
            if (isset($periodos[1]) && $i['intencao'] === 'total_periodo')
            {
                $i['intencao'] = 'comparacao_periodos';
            }
        }

        return array('tipo' => 'consulta', 'interpretacao' => $i);
    }

    protected function periodos($t)
    {
        $achados = array();
        $ano_atual = (int) date('Y', strtotime($this->catalogo['hoje']));
        $nomes_meses = implode('|', array_keys($this->meses));
        $t = preg_replace('/\b(?:no|do|o) mes de ('.$nomes_meses.')\b/', ' $1', $t);
        $t = preg_replace('/\b('.$nomes_meses.')(?: de)? (?:deste|neste|este) ano\b/', '$1 '.$ano_atual, $t);

        $relativos = array(
            'ontem' => 'ontem', 'hoje' => 'hoje', 'semana passada' => 'semana_passada', 'esta semana' => 'esta_semana',
            'nesta semana' => 'esta_semana', 'mes passado' => 'mes_passado', 'ultimo mes' => 'mes_passado',
            'este mes' => 'este_mes', 'deste mes' => 'este_mes', 'neste mes' => 'este_mes', 'do mes' => 'este_mes',
            'no mes' => 'este_mes', 'este ano' => 'este_ano', 'neste ano' => 'este_ano',
            'ultimos 7 dias' => 'ultimos_7_dias', 'ultimos 30 dias' => 'ultimos_30_dias', 'ultimos 12 meses' => 'ultimos_12_meses',
        );
        uksort($relativos, function ($a, $b) { return strlen($b) - strlen($a); });
        foreach ($relativos as $expr => $valor)
        {
            $pos = strpos($t, ' '.$expr.' ');
            if ($pos !== FALSE)
            {
                $achados[$pos] = array('tipo' => 'relativo', 'valor' => $valor);
                $t = substr_replace($t, str_repeat(' ', strlen($expr)), $pos + 1, strlen($expr));
            }
        }

        $padrao = '/\b('.$nomes_meses.')(?:\s+de)?(?:\s+(\d{4})|\s+(?:do|de)\s+(ano passado))?/';
        if (preg_match_all($padrao, $t, $ms, PREG_SET_ORDER | PREG_OFFSET_CAPTURE))
        {
            foreach ($ms as $m)
            {
                $ano = NULL;
                if ( ! empty($m[2][0]))
                {
                    $ano = (int) $m[2][0];
                }
                elseif ( ! empty($m[3][0]))
                {
                    $ano = $ano_atual - 1;
                }
                $achados[$m[0][1]] = array('tipo' => 'mes', 'mes' => $this->meses[$m[1][0]], 'ano' => $ano);
            }
        }

        if (empty($achados) && preg_match('/ ano passado /', $t, $m, PREG_OFFSET_CAPTURE))
        {
            $achados[$m[0][1]] = array('tipo' => 'relativo', 'valor' => 'ano_passado');
        }

        ksort($achados);
        return array_values($achados);
    }

    protected function recusa($motivo, $t)
    {
        $alt = NULL;
        if (preg_match('/margem|lucro|custo|rentabilidade/', $t))
        {
            $alt = array('frase' => 'mostrar o faturamento por produto', 'rotulo' => 'Ver faturamento por produto',
                'interpretacao' => array('intencao' => 'vendas_por_produto', 'metrica' => 'faturadas',
                    'periodo' => array('tipo' => 'relativo', 'valor' => 'mes_passado'), 'limite' => 20));
        }
        elseif (preg_match('/por que|porque|motivo|culpa/', $t))
        {
            $alt = array('frase' => 'comparar o mês passado com o anterior em cada filial', 'rotulo' => 'Comparar por filial',
                'interpretacao' => array('intencao' => 'comparacao_periodos',
                    'periodo' => array('tipo' => 'relativo', 'valor' => 'mes_passado'), 'agrupar_por' => 'filial'));
        }
        elseif ( ! preg_match('/ignore|instruc|prompt|sql|delete|drop |update |apague|altere/', $t))
        {
            $alt = array('frase' => 'mostrar o total de vendas do mês passado', 'rotulo' => 'Ver vendas do mês passado',
                'interpretacao' => array('intencao' => 'total_periodo',
                    'periodo' => array('tipo' => 'relativo', 'valor' => 'mes_passado')));
        }
        return array('tipo' => 'recusa', 'recusa' => array('motivo' => $motivo, 'alternativa' => $alt));
    }

    protected function normalizar($s)
    {
        $s = mb_strtolower($s, 'UTF-8');
        $s = strtr($s, array('á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i',
            'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ü' => 'u', 'ç' => 'c', '×' => ' x '));
        $s = preg_replace('/[^a-z0-9 ]+/', ' ', $s);
        return trim(preg_replace('/\s+/', ' ', $s));
    }
}
