<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Textos montados aqui, nunca pela IA: nenhum número sai de outro lugar que não o banco. */
class Redator {

    const RS = "R\$\xC2\xA0";

    public function moeda($v)
    {
        return self::RS.number_format($v, 2, ',', '.');
    }

    public function compacto($v)
    {
        if (abs($v) >= 1000000)
        {
            return self::RS.number_format($v / 1000000, 1, ',', '.').' mi';
        }
        if (abs($v) >= 10000)
        {
            return self::RS.number_format(round($v / 1000), 0, ',', '.').' mil';
        }
        return $this->moeda($v);
    }

    public function pct($razao)
    {
        $p = abs($razao) * 100;
        return number_format($p, $p < 10 ? 1 : 0, ',', '.').'%';
    }

    /** Participação com uma casa, igual à coluna "Part." da tabela do front. */
    public function fatia($razao)
    {
        return number_format(abs($razao) * 100, 1, ',', '.').'%';
    }

    public function numero($n)
    {
        return number_format($n, 0, ',', '.');
    }

    public function variacao($atual, $anterior, $rotulo_anterior, $metrica_nome)
    {
        if ($anterior <= 0)
        {
            return 'Não houve '.$metrica_nome.' em '.$rotulo_anterior.' para comparar.';
        }
        $r = $atual / $anterior - 1;
        if (abs($r) < 0.005)
        {
            return 'Praticamente igual a '.$rotulo_anterior.' ('.$this->compacto($anterior).').';
        }
        return $this->pct($r).($r > 0 ? ' acima de ' : ' abaixo de ').$rotulo_anterior.' ('.$this->compacto($anterior).').';
    }

    public function puxou($por_filial_atual, $por_filial_anterior, $total_atual, $total_anterior)
    {
        if ($total_anterior <= 0 OR abs($total_atual / $total_anterior - 1) < 0.01)
        {
            return NULL;
        }
        $antes = array();
        foreach ($por_filial_anterior as $f)
        {
            $antes[$f['slug']] = $f['valor'];
        }
        $subiu = $total_atual > $total_anterior;
        $melhor = NULL;
        $melhor_delta = 0;
        foreach ($por_filial_atual as $f)
        {
            $delta = $f['valor'] - (isset($antes[$f['slug']]) ? $antes[$f['slug']] : 0);
            if (($subiu && $delta > $melhor_delta) OR ( ! $subiu && $delta < $melhor_delta))
            {
                $melhor = $f['nome'];
                $melhor_delta = $delta;
            }
        }
        if ($melhor === NULL)
        {
            return NULL;
        }
        return $subiu ? 'A filial '.$melhor.' puxou o crescimento.' : 'A queda veio principalmente da filial '.$melhor.'.';
    }

    public function resposta($dados)
    {
        return array_merge(array(
            'tipo' => 'resposta', 'texto' => '', 'destaque' => '', 'origem' => '',
            'barras' => array(), 'tabela' => array(), 'acoes' => array(),
        ), $dados);
    }

    public function recusa($motivo, $alternativa = NULL)
    {
        $texto = 'Essa eu não sei responder com segurança: '.rtrim($motivo, '. ').'.';
        $acoes = array();
        if ($alternativa)
        {
            $texto .= ' Posso '.rtrim($alternativa['frase'], '. ').'.';
            $acoes[] = $this->acao($alternativa['rotulo'], array('interpretacao' => $alternativa['interpretacao']));
        }
        $acoes[] = $this->acao('Por que não sei?', array('acao' => 'explicar_recusa', 'motivo' => $motivo));
        return array('tipo' => 'recusa', 'texto' => $texto, 'destaque' => '', 'origem' => '',
                     'barras' => array(), 'tabela' => array(), 'acoes' => $acoes);
    }

    public function esclarecer($pergunta, $opcoes)
    {
        $acoes = array();
        foreach ($opcoes as $o)
        {
            $acoes[] = $this->acao($o['rotulo'], $o['contexto']);
        }
        return array('tipo' => 'esclarecer', 'texto' => $pergunta, 'destaque' => '', 'origem' => '',
                     'barras' => array(), 'tabela' => array(), 'acoes' => $acoes);
    }

    public function explicar_recusa($motivo)
    {
        return $this->resposta(array(
            'texto' => 'Eu respondo só com o que está na base de vendas: valor, quantidade e número de pedidos, '
                .'por período, produto e filial. Neste caso, '.rtrim($motivo, '. ').'. '
                .'Quando falta um dado, prefiro dizer que não sei a mostrar um número estimado.',
        ));
    }

    public function acao($rotulo, $contexto)
    {
        return array('rotulo' => $rotulo, 'pergunta' => $rotulo, 'contexto' => $contexto);
    }

    public function ucfirst($s)
    {
        return mb_strtoupper(mb_substr($s, 0, 1, 'UTF-8'), 'UTF-8').mb_substr($s, 1, mb_strlen($s, 'UTF-8'), 'UTF-8');
    }
}
