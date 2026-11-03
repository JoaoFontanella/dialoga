<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Respondedor {

    protected $CI;
    protected $cat;
    protected $vendas;
    protected $periodo;
    protected $red;

    public function __construct($params = array())
    {
        $this->CI =& get_instance();
        $this->CI->load->library(array('periodo', 'redator'));
        $this->CI->load->model('vendas_model');
        $this->vendas = $this->CI->vendas_model;
        $this->periodo = $this->CI->periodo;
        $this->red = $this->CI->redator;
    }

    public function definir_catalogo($catalogo)
    {
        $this->cat = $catalogo;
    }

    public function responder($saida)
    {
        if ($saida['tipo'] === 'recusa')
        {
            return $this->sem_consulta($this->red->recusa($saida['motivo'], $saida['alternativa']));
        }
        if ($saida['tipo'] === 'esclarecer')
        {
            $opcoes = array();
            foreach ($saida['opcoes'] as $o)
            {
                $opcoes[] = array('rotulo' => $o['rotulo'], 'contexto' => array('interpretacao' => $o['interpretacao']));
            }
            return $this->sem_consulta($this->red->esclarecer($saida['pergunta'], $opcoes));
        }
        return $this->consultar($saida['interpretacao']);
    }

    protected function consultar($i)
    {
        if ($i['metrica'] === NULL)
        {
            $i['metrica'] = $this->metrica_do_dicionario();
            if ($i['metrica'] === NULL)
            {
                return $this->sem_consulta($this->perguntar_metrica($i));
            }
        }

        if ($i['produto_id'] OR $i['produto'] !== NULL)
        {
            $r = $this->resolver_produto($i);
            if (isset($r['tipo']))
            {
                return $this->sem_consulta($r);
            }
            $i = $r;
        }

        $filial = $i['filial'] ? $this->vendas->filial_por_slug($i['filial']) : NULL;
        if ($i['periodo'] === NULL)
        {
            $i['periodo'] = $this->cat['periodo_padrao'];
        }
        $p = $this->no_alcance($this->periodo->resolver($i['periodo']));
        if (is_string($p))
        {
            return $this->sem_consulta($this->red->recusa($p, $this->alternativa_padrao()));
        }

        switch ($i['intencao'])
        {
            case 'total_periodo':
                $resp = $i['agrupar_por'] === 'filial'
                    ? $this->por_filial($i, $p)
                    : $this->total($i, $p, $filial, $this->comparavel($this->periodo->anterior($p)));
                break;

            case 'comparacao_periodos':
                $c = $i['periodo_comparacao'] ? $this->periodo->resolver($i['periodo_comparacao']) : $this->periodo->anterior($p);
                if ($p['recortado'] && $p['inicio'] === $this->cat['cobertura']['inicio'])
                {
                    return $this->sem_consulta($this->red->recusa($this->periodo->rotulo($this->periodo->resolver($p['spec']))
                        .' não está inteiro na base, que começa em '.$this->periodo->data_curta($p['inicio'])
                        .', e a comparação ficaria distorcida', $this->alternativa_padrao()));
                }
                if ($p['recortado'] && $i['periodo_comparacao'] && ! is_string($c))
                {
                    $c = $this->periodo->mesma_duracao($c, $p);
                }
                $c = $this->no_alcance($c);
                if ( ! is_string($c) && $c['recortado'])
                {
                    $c = $this->periodo->rotulo($this->periodo->resolver($c['spec'])).' não está inteiro na base, que começa em '
                        .$this->periodo->data_curta($this->cat['cobertura']['inicio']).', e a comparação ficaria distorcida';
                }
                if (is_string($c))
                {
                    return $this->sem_consulta($this->red->recusa($c, $this->alternativa_padrao()));
                }
                $resp = $i['agrupar_por'] === 'filial'
                    ? $this->comparar_por_filial($i, $p, $c)
                    : $this->total($i, $p, $filial, $c);
                break;

            case 'ranking_produtos':
            case 'vendas_por_produto':
                $resp = $this->produtos($i, $p, $filial);
                break;
        }

        if ($resp === FALSE)
        {
            return FALSE;
        }
        return array('resposta' => $resp, 'interpretacao' => $i);
    }

    protected function total($i, $p, $filial, $ant)
    {
        $m = Vendas_model::$metricas[$i['metrica']];
        $fid = $filial ? $filial['id'] : NULL;
        $atual = $this->vendas->total($i['metrica'], $p['inicio'], $p['fim'], $fid);
        if ($atual === FALSE)
        {
            return FALSE;
        }

        $frases = array();
        $barras = array($this->barra($p, $atual['valor'], TRUE));

        if ($ant)
        {
            $antes = $this->vendas->total($i['metrica'], $ant['inicio'], $ant['fim'], $fid);
            if ($antes === FALSE)
            {
                return FALSE;
            }
            $anos_diferentes = substr($p['inicio'], 0, 4) !== substr($ant['inicio'], 0, 4);
            $barras = array(
                $this->barra($p, $atual['valor'], TRUE, $anos_diferentes),
                $this->barra($ant, $antes['valor'], FALSE, $anos_diferentes),
            );
            $rot_ant = $this->periodo->rotulo($ant);
            if ($p['granularidade'] === 'dia' && $ant['granularidade'] === 'dia' && ! $i['periodo_comparacao'])
            {
                $rot_ant .= ', mesmo dia da semana anterior';
            }
            $frases[] = $this->red->variacao($atual['valor'], $antes['valor'], $rot_ant, $m['nome']);
            if ( ! $filial)
            {
                $puxou = $this->red->puxou(
                    $this->vendas->total_por_filial($i['metrica'], $p['inicio'], $p['fim']),
                    $this->vendas->total_por_filial($i['metrica'], $ant['inicio'], $ant['fim']),
                    $atual['valor'], $antes['valor']);
                if ($puxou)
                {
                    $frases[] = $puxou;
                }
            }
        }

        $onde = $filial ? 'Na filial '.$filial['nome'].', ' : '';
        $exato = $this->red->ucfirst($onde.'foram '.$this->red->moeda($atual['valor']).' em '
            .$this->red->numero($atual['pedidos']).' '.($atual['pedidos'] === 1 ? 'pedido' : 'pedidos').'.');
        if ($filial)
        {
            array_unshift($frases, $exato);
        }
        else
        {
            $frases[] = $exato;
        }
        if ($p['recortado'])
        {
            $frases[] = $this->aviso_recorte($p);
        }

        $acoes = array();
        if ( ! $filial)
        {
            $acoes[] = $this->red->acao('Ver por filial', array('interpretacao' => array_merge($i, array('intencao' => 'total_periodo', 'agrupar_por' => 'filial', 'periodo_comparacao' => NULL))));
        }
        $um_ano = in_array($p['granularidade'], array('mes', 'trimestre', 'ano'), TRUE) && ! $p['recortado']
            ? $this->comparavel($this->periodo->ano_anterior($p)) : NULL;
        if ($um_ano && ( ! $ant OR $ant['inicio'] !== $um_ano['inicio']))
        {
            $acoes[] = $this->red->acao('Comparar com '.$this->periodo->rotulo($um_ano), array('interpretacao' => array_merge($i, array(
                'intencao' => 'comparacao_periodos', 'periodo_comparacao' => $um_ano['spec']))));
        }

        return $this->red->resposta(array(
            'destaque' => $this->red->compacto($atual['valor']),
            'texto'    => implode(' ', $frases),
            'origem'   => $this->origem($m['origem'], $p, $filial, $m['criterio']),
            'barras'   => $barras,
            'acoes'    => $acoes,
        ));
    }

    protected function por_filial($i, $p)
    {
        $m = Vendas_model::$metricas[$i['metrica']];
        $linhas = $this->vendas->total_por_filial($i['metrica'], $p['inicio'], $p['fim']);
        if ($linhas === FALSE)
        {
            return FALSE;
        }
        $total = 0;
        foreach ($linhas as $l)
        {
            $total += $l['valor'];
        }
        $partes = array();
        $barras = array();
        foreach ($linhas as $n => $l)
        {
            $fatia = $total > 0 ? ' ('.$this->red->fatia($l['valor'] / $total).')' : '';
            $partes[] = $l['nome'].' '.($n === 0 ? 'vendeu ' : '').$this->red->compacto($l['valor']).$fatia;
            $barras[] = array('rotulo' => $l['nome'], 'rotulo_curto' => $l['nome'], 'valor' => $l['valor'], 'atual' => $n === 0 && $total > 0);
        }
        $ultima = array_pop($partes);
        $texto = ($partes ? implode(', ', $partes).' e '.$ultima : $ultima).'.';
        if ($p['recortado'])
        {
            $texto .= ' '.$this->aviso_recorte($p);
        }

        $acoes = array();
        $ant = $this->comparavel($this->periodo->anterior($p));
        if ($ant)
        {
            $acoes[] = $this->red->acao('Comparar com '.$this->periodo->rotulo($ant), array('interpretacao' => array_merge($i, array(
                'intencao' => 'comparacao_periodos', 'periodo_comparacao' => $ant['spec']))));
        }

        return $this->red->resposta(array(
            'destaque' => $this->red->compacto($total),
            'texto'    => $texto,
            'origem'   => $this->origem($m['origem'], $p, NULL, 'por filial · '.$m['criterio']),
            'barras'   => $barras,
            'acoes'    => $acoes,
        ));
    }

    protected function comparar_por_filial($i, $p, $c)
    {
        $m = Vendas_model::$metricas[$i['metrica']];
        $agora = $this->vendas->total_por_filial($i['metrica'], $p['inicio'], $p['fim']);
        $antes = $this->vendas->total_por_filial($i['metrica'], $c['inicio'], $c['fim']);
        if ($agora === FALSE OR $antes === FALSE)
        {
            return FALSE;
        }
        $mapa = array();
        $t_antes = 0;
        foreach ($antes as $l)
        {
            $mapa[$l['slug']] = $l['valor'];
            $t_antes += $l['valor'];
        }
        $t_agora = 0;
        $linhas = array();
        $barras = array();
        $curto_p = $this->periodo->rotulo_curto($p);
        $curto_c = $this->periodo->rotulo_curto($c);
        foreach ($agora as $l)
        {
            $t_agora += $l['valor'];
            $v_antes = isset($mapa[$l['slug']]) ? $mapa[$l['slug']] : 0;
            $linhas[] = $l['nome'].': '.$this->red->compacto($l['valor']).', '
                .($v_antes > 0 ? $this->red->pct($l['valor'] / $v_antes - 1).($l['valor'] >= $v_antes ? ' acima' : ' abaixo') : 'sem base de comparação').'.';
            $barras[] = array('rotulo' => $l['nome'].' · '.$curto_p, 'rotulo_curto' => mb_substr($l['nome'], 0, 3, 'UTF-8').' · '.$curto_p, 'valor' => $l['valor'], 'atual' => TRUE);
            $barras[] = array('rotulo' => $l['nome'].' · '.$curto_c, 'rotulo_curto' => mb_substr($l['nome'], 0, 3, 'UTF-8').' · '.$curto_c, 'valor' => $v_antes, 'atual' => FALSE);
        }
        $texto = $this->red->variacao($t_agora, $t_antes, $this->periodo->rotulo($c), $m['nome']).' '.implode(' ', $linhas);

        return $this->red->resposta(array(
            'destaque' => $this->red->compacto($t_agora),
            'texto'    => $texto,
            'origem'   => $this->origem($m['origem'], $p, NULL, 'contra '.$this->periodo->intervalo_curto($c['inicio'], $c['fim']).' · '.$m['criterio']),
            'barras'   => $barras,
        ));
    }

    protected function produtos($i, $p, $filial)
    {
        $m = Vendas_model::$metricas[$i['metrica']];
        $unico = (bool) $i['produto_id'];
        $limite = $unico ? 1 : ($i['intencao'] === 'vendas_por_produto' ? 20 : $i['limite']);
        $r = $this->vendas->por_produto($i['metrica'], $p['inicio'], $p['fim'], $filial ? $filial['id'] : NULL,
            $i['ordenar_por'], $limite, $i['produto_id']);
        if ($r === FALSE)
        {
            return FALSE;
        }
        $rotulo = $this->periodo->rotulo($p);
        $origem = $this->origem('itens de '.$m['origem'], $p, $filial, $m['criterio']);

        if ($unico)
        {
            if ( ! $r['linhas'])
            {
                return $this->red->resposta(array(
                    'destaque' => Redator::RS.'0',
                    'texto' => $i['produto'].' não teve '.$m['nome'].' em '.$rotulo.'.',
                    'origem' => $origem,
                ));
            }
            $l = $r['linhas'][0];
            $fatia = $r['total_valor'] > 0 ? $l['valor'] / $r['total_valor'] : 0;
            return $this->red->resposta(array(
                'destaque' => $this->red->compacto($l['valor']),
                'texto' => $l['produto'].': '.$this->red->numero($l['quantidade']).' '.($l['quantidade'] === 1 ? 'unidade' : 'unidades')
                    .' em '.$rotulo.', '.$this->red->fatia($fatia).' do valor vendido no período. Valor exato: '.$this->red->moeda($l['valor']).'.',
                'origem' => $origem,
                'acoes' => array($this->red->acao('Ver os 5 mais vendidos', array('interpretacao' => array_merge($i, array(
                    'intencao' => 'ranking_produtos', 'produto' => NULL, 'produto_id' => NULL, 'limite' => 5))))),
            ));
        }

        if ( ! $r['linhas'])
        {
            return $this->red->resposta(array(
                'destaque' => Redator::RS.'0',
                'texto' => 'Não houve '.$m['nome'].' em '.$rotulo.'.',
                'origem' => $origem,
            ));
        }

        $tabela = array();
        foreach ($r['linhas'] as $l)
        {
            $tabela[] = array(
                'produto' => $l['produto'],
                'valor' => $this->red->moeda($l['valor']),
                'quantidade' => $l['quantidade'],
                'participacao' => $r['total_valor'] > 0 ? round($l['valor'] / $r['total_valor'], 4) : 0,
            );
        }
        $primeiro = $r['linhas'][0];
        $por_qtd = $i['ordenar_por'] === 'quantidade';
        $texto = $por_qtd
            ? 'O mais vendido em unidades foi '.$primeiro['produto'].', com '.$this->red->numero($primeiro['quantidade']).' unidades em '.$rotulo.'.'
            : 'O mais vendido foi '.$primeiro['produto'].', com '.$this->red->fatia($primeiro['valor'] / max(0.01, $r['total_valor'])).' do valor vendido em '.$rotulo.'.';
        return $this->red->resposta(array(
            'destaque' => $por_qtd ? $this->red->numero($primeiro['quantidade']).' un.' : $this->red->compacto($primeiro['valor']),
            'texto' => $texto,
            'origem' => $origem.' · por '.($por_qtd ? 'quantidade' : 'valor'),
            'tabela' => $tabela,
            'acoes' => array($this->red->acao($por_qtd ? 'Ver por valor' : 'Ver por quantidade', array('interpretacao' => array_merge($i, array(
                'ordenar_por' => $por_qtd ? 'valor' : 'quantidade'))))),
        ));
    }

    protected function metrica_do_dicionario()
    {
        foreach ($this->cat['dicionario'] as $d)
        {
            if ($d['tipo'] === 'metrica' && $d['termo'] === 'vendas' && in_array($d['valor'], array('faturadas', 'fechados'), TRUE))
            {
                return $d['valor'];
            }
        }
        return NULL;
    }

    protected function perguntar_metrica($i)
    {
        $opcoes = array();
        foreach (array('faturadas' => 'Vendas faturadas', 'fechados' => 'Pedidos fechados') as $valor => $rotulo)
        {
            $opcoes[] = array('rotulo' => $rotulo, 'contexto' => array(
                'interpretacao' => array_merge($i, array('metrica' => $valor)),
                'aprender' => $valor,
            ));
        }
        return $this->red->esclarecer('Você quer as vendas faturadas ou os pedidos fechados? Na sua empresa, os dois números são diferentes.', $opcoes);
    }

    protected function resolver_produto($i)
    {
        if ($i['produto_id'])
        {
            foreach ($this->vendas->produtos() as $p)
            {
                $mesmo_nome = $i['produto'] === NULL || $this->vendas->sem_acento($p['nome']) === $this->vendas->sem_acento($i['produto']);
                if ((int) $p['id'] === (int) $i['produto_id'] && $mesmo_nome)
                {
                    $i['produto'] = $p['nome'];
                    return $i;
                }
            }
            $i['produto_id'] = NULL;
            if ($i['produto'] === NULL)
            {
                return $this->red->recusa('não encontrei esse produto na base', $this->alternativa_ranking($i));
            }
        }

        $achados = $this->vendas->buscar_produto($i['produto']);
        if (count($achados) === 1)
        {
            $i['produto'] = $achados[0]['nome'];
            $i['produto_id'] = (int) $achados[0]['id'];
            if ($i['intencao'] === 'ranking_produtos')
            {
                $i['intencao'] = 'vendas_por_produto';
            }
            return $i;
        }
        if ( ! $achados)
        {
            return $this->red->recusa('não encontrei um produto chamado "'.$i['produto'].'" na base', $this->alternativa_ranking($i));
        }
        $opcoes = array();
        foreach (array_slice($achados, 0, 4) as $a)
        {
            $opcoes[] = array('rotulo' => $a['nome'], 'contexto' => array('interpretacao' => array_merge($i, array(
                'produto' => $a['nome'], 'produto_id' => (int) $a['id'], 'intencao' => 'vendas_por_produto'))));
        }
        return $this->red->esclarecer('Qual deles? Há '.count($achados).' produtos com esse nome, e cada um tem a sua venda.', $opcoes);
    }

    protected function no_alcance($p)
    {
        if (is_string($p))
        {
            return 'não consegui entender o período';
        }
        $cob = $this->cat['cobertura'];
        $r = $this->periodo->recortar($p, $cob['inicio'], $cob['fim']);
        if ($r === FALSE)
        {
            return $p['fim'] < $cob['inicio']
                ? 'a base começa em '.$this->periodo->data_curta($cob['inicio'])
                : 'a base vai até '.$this->periodo->data_curta($cob['fim']);
        }
        return $r;
    }

    protected function comparavel($p)
    {
        if (is_string($p))
        {
            return NULL;
        }
        $cob = $this->cat['cobertura'];
        return ($p['inicio'] >= $cob['inicio'] && $p['fim'] <= $cob['fim']) ? $p : NULL;
    }

    protected function aviso_recorte($p)
    {
        $cob = $this->cat['cobertura'];
        return $p['fim'] === $cob['fim'] && $p['inicio'] !== $cob['inicio']
            ? 'A base tem dados até '.$this->periodo->data_curta($cob['fim']).'.'
            : 'A base começa em '.$this->periodo->data_curta($cob['inicio']).'.';
    }

    protected function origem($o, $p, $filial, $criterio)
    {
        $partes = array($o);
        if ($filial)
        {
            $partes[] = 'filial '.$filial['nome'];
        }
        $partes[] = $p['inicio'] === $p['fim'] ? $this->periodo->data_curta($p['inicio']) : $this->periodo->intervalo_curto($p['inicio'], $p['fim']);
        $partes[] = $criterio;
        return implode(' · ', $partes);
    }

    protected function alternativa_padrao()
    {
        return array('frase' => 'mostrar as vendas do período padrão', 'rotulo' => 'Ver período padrão',
            'interpretacao' => array('intencao' => 'total_periodo', 'periodo' => $this->cat['periodo_padrao']));
    }

    protected function alternativa_ranking($i)
    {
        return array('frase' => 'mostrar os 5 produtos mais vendidos do período', 'rotulo' => 'Ver os 5 mais vendidos',
            'interpretacao' => array_merge($i, array('intencao' => 'ranking_produtos', 'produto' => NULL, 'produto_id' => NULL, 'limite' => 5)));
    }

    protected function barra($p, $valor, $atual, $com_ano = FALSE)
    {
        return array(
            'rotulo'       => $this->periodo->rotulo_barra($p, $com_ano),
            'rotulo_curto' => $this->periodo->rotulo_curto($p, $com_ano),
            'valor'        => $valor,
            'atual'        => $atual,
        );
    }

    protected function sem_consulta($resposta)
    {
        return array('resposta' => $resposta, 'interpretacao' => NULL);
    }
}
