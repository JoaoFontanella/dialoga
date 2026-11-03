<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** A IA descreve o período ("mês 9", "semana passada"); as datas são calculadas aqui. */
class Periodo {

    public static $meses = array(1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
        'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro');

    public static $relativos = array('hoje', 'ontem', 'esta_semana', 'semana_passada', 'este_mes',
        'mes_passado', 'este_ano', 'ano_passado', 'ultimos_7_dias', 'ultimos_30_dias', 'ultimos_12_meses');

    protected $hoje;

    public function __construct($params = array())
    {
        $this->definir_hoje(isset($params['hoje']) ? $params['hoje'] : '');
    }

    public function definir_hoje($data)
    {
        $this->hoje = ($data !== '' && $this->data_valida($data)) ? new DateTime($data) : new DateTime('today');
        $this->hoje->setTime(0, 0, 0);
    }

    public function hoje()
    {
        return $this->hoje->format('Y-m-d');
    }

    public function resolver($spec)
    {
        if ( ! is_array($spec) OR ! isset($spec['tipo']))
        {
            return 'período sem tipo';
        }
        $h = clone $this->hoje;
        $ano_atual = (int) $h->format('Y');

        switch ($spec['tipo'])
        {
            case 'relativo':
                $v = isset($spec['valor']) ? $spec['valor'] : '';
                if ( ! in_array($v, self::$relativos, TRUE))
                {
                    return 'período relativo desconhecido';
                }
                return $this->relativo($v);

            case 'dia':
                if (empty($spec['data']) OR ! $this->data_valida($spec['data']))
                {
                    return 'data inválida';
                }
                return $this->montar($spec['data'], $spec['data'], 'dia', array('tipo' => 'dia', 'data' => $spec['data']));

            case 'mes':
                $mes = isset($spec['mes']) ? (int) $spec['mes'] : 0;
                if ($mes < 1 OR $mes > 12)
                {
                    return 'mês inválido';
                }
                $ano = isset($spec['ano']) && $spec['ano'] ? (int) $spec['ano'] : NULL;
                if ($ano === NULL)
                {
                    // "setembro" sem ano = o setembro mais recente que já começou
                    $ano = $mes <= (int) $h->format('n') ? $ano_atual : $ano_atual - 1;
                }
                if ($ano < 2000 OR $ano > $ano_atual + 1)
                {
                    return 'ano inválido';
                }
                $ini = sprintf('%04d-%02d-01', $ano, $mes);
                $d = new DateTime($ini);
                return $this->montar($ini, $d->format('Y-m-t'), 'mes', array('tipo' => 'mes', 'mes' => $mes, 'ano' => $ano));

            case 'trimestre':
                $t = isset($spec['trimestre']) ? (int) $spec['trimestre'] : 0;
                if ($t < 1 OR $t > 4)
                {
                    return 'trimestre inválido';
                }
                $ano = isset($spec['ano']) && $spec['ano'] ? (int) $spec['ano'] : $ano_atual;
                $ini = new DateTime(sprintf('%04d-%02d-01', $ano, ($t - 1) * 3 + 1));
                $fim = clone $ini;
                $fim->modify('+2 months');
                return $this->montar($ini->format('Y-m-d'), $fim->format('Y-m-t'), 'trimestre',
                    array('tipo' => 'trimestre', 'trimestre' => $t, 'ano' => $ano));

            case 'ano':
                $ano = isset($spec['ano']) ? (int) $spec['ano'] : 0;
                if ($ano < 2000 OR $ano > $ano_atual + 1)
                {
                    return 'ano inválido';
                }
                return $this->montar($ano.'-01-01', $ano.'-12-31', 'ano', array('tipo' => 'ano', 'ano' => $ano));

            case 'intervalo':
                if (empty($spec['inicio']) OR empty($spec['fim'])
                    OR ! $this->data_valida($spec['inicio']) OR ! $this->data_valida($spec['fim'])
                    OR $spec['inicio'] > $spec['fim'])
                {
                    return 'intervalo inválido';
                }
                return $this->montar($spec['inicio'], $spec['fim'], 'intervalo',
                    array('tipo' => 'intervalo', 'inicio' => $spec['inicio'], 'fim' => $spec['fim']));
        }
        return 'tipo de período desconhecido';
    }

    protected function relativo($v)
    {
        $ini = clone $this->hoje;
        $fim = clone $this->hoje;
        $spec = array('tipo' => 'relativo', 'valor' => $v);
        $g = 'intervalo';

        switch ($v)
        {
            case 'hoje':           $g = 'dia'; break;
            case 'ontem':          $ini->modify('-1 day'); $fim->modify('-1 day'); $g = 'dia'; break;
            case 'esta_semana':    $ini->modify('-'.((int) $ini->format('N') - 1).' days'); $g = 'semana'; break;
            case 'semana_passada':
                $ini->modify('-'.((int) $ini->format('N') + 6).' days');
                $fim = clone $ini;
                $fim->modify('+6 days');
                $g = 'semana';
                break;
            case 'este_mes':       $ini->modify('first day of this month'); $g = 'mes'; break;
            case 'mes_passado':
                $ini->modify('first day of last month');
                $fim = clone $ini;
                $fim->modify('last day of this month');
                $g = 'mes';
                break;
            case 'este_ano':       $ini->setDate((int) $ini->format('Y'), 1, 1); $g = 'ano'; break;
            case 'ano_passado':
                $a = (int) $ini->format('Y') - 1;
                $ini->setDate($a, 1, 1);
                $fim->setDate($a, 12, 31);
                $g = 'ano';
                break;
            case 'ultimos_7_dias':   $ini->modify('-7 days');  $fim->modify('-1 day'); break;
            case 'ultimos_30_dias':  $ini->modify('-30 days'); $fim->modify('-1 day'); break;
            case 'ultimos_12_meses': $ini->modify('-12 months'); $fim->modify('-1 day'); break;
        }
        $p = $this->montar($ini->format('Y-m-d'), $fim->format('Y-m-d'), $g, $spec);
        $p['relativo'] = $v;
        return $p;
    }

    protected function montar($ini, $fim, $granularidade, $spec)
    {
        return array(
            'inicio'        => $ini,
            'fim'           => $fim,
            'granularidade' => $granularidade,
            'spec'          => $spec,
            'recortado'     => FALSE,
        );
    }

    public function recortar($p, $base_inicio, $base_fim)
    {
        if ($p['fim'] < $base_inicio OR $p['inicio'] > $base_fim)
        {
            return FALSE;
        }
        if ($p['inicio'] < $base_inicio)
        {
            $p['inicio'] = $base_inicio;
            $p['recortado'] = TRUE;
        }
        if ($p['fim'] > $base_fim)
        {
            $p['fim'] = $base_fim;
            $p['recortado'] = TRUE;
        }
        return $p;
    }

    public function anterior($p)
    {
        $ini = new DateTime($p['inicio']);
        $fim = new DateTime($p['fim']);

        if ($p['granularidade'] === 'mes' && $ini->format('d') === '01')
        {
            $mes_ant = clone $ini;
            $mes_ant->modify('first day of last month');
            if ( ! $p['recortado'] && $fim->format('Y-m-d') === $ini->format('Y-m-t'))
            {
                $novo = $this->resolver(array('tipo' => 'mes', 'mes' => (int) $mes_ant->format('n'), 'ano' => (int) $mes_ant->format('Y')));
                return $novo;
            }
            // mês em andamento: mesmos dias do mês anterior (1 a 2/11 contra 1 a 2/10)
            $dias = (int) $fim->format('j');
            $f = clone $mes_ant;
            $f->modify('+'.(min($dias, (int) $mes_ant->format('t')) - 1).' days');
            return $this->montar($mes_ant->format('Y-m-d'), $f->format('Y-m-d'), 'intervalo',
                array('tipo' => 'intervalo', 'inicio' => $mes_ant->format('Y-m-d'), 'fim' => $f->format('Y-m-d')));
        }

        if ($p['granularidade'] === 'dia')
        {
            $ini->modify('-7 days');
            return $this->montar($ini->format('Y-m-d'), $ini->format('Y-m-d'), 'dia',
                array('tipo' => 'dia', 'data' => $ini->format('Y-m-d')));
        }

        if ($p['granularidade'] === 'semana')
        {
            $ini->modify('-7 days');
            $fim->modify('-7 days');
            return $this->montar($ini->format('Y-m-d'), $fim->format('Y-m-d'), 'semana',
                array('tipo' => 'intervalo', 'inicio' => $ini->format('Y-m-d'), 'fim' => $fim->format('Y-m-d')));
        }

        $dias = (int) $ini->diff($fim)->days + 1;
        $f = clone $ini;
        $f->modify('-1 day');
        $i = clone $f;
        $i->modify('-'.($dias - 1).' days');
        return $this->montar($i->format('Y-m-d'), $f->format('Y-m-d'), 'intervalo',
            array('tipo' => 'intervalo', 'inicio' => $i->format('Y-m-d'), 'fim' => $f->format('Y-m-d')));
    }

    /** Corta $c para ter a mesma quantidade de dias de $p, a partir do início de $c. */
    public function mesma_duracao($c, $p)
    {
        $dias = (int) date_diff(new DateTime($p['inicio']), new DateTime($p['fim']))->days;
        $fim = new DateTime($c['inicio']);
        $fim->modify('+'.$dias.' days');
        if ($fim->format('Y-m-d') >= $c['fim'])
        {
            return $c;
        }
        return $this->resolver(array('tipo' => 'intervalo', 'inicio' => $c['inicio'], 'fim' => $fim->format('Y-m-d')));
    }

    public function ano_anterior($p)
    {
        $spec = $p['spec'];
        if ($spec['tipo'] === 'mes')
        {
            return $this->resolver(array('tipo' => 'mes', 'mes' => $spec['mes'], 'ano' => $spec['ano'] - 1));
        }
        $i = new DateTime($p['inicio']);
        $f = new DateTime($p['fim']);
        $i->modify('-1 year');
        $f->modify('-1 year');
        return $this->resolver(array('tipo' => 'intervalo', 'inicio' => $i->format('Y-m-d'), 'fim' => $f->format('Y-m-d')));
    }

    public function rotulo($p)
    {
        $s = $p['spec'];
        if ( ! $p['recortado'])
        {
            if ($s['tipo'] === 'mes')
            {
                return self::$meses[$s['mes']].($s['ano'] == $this->hoje->format('Y') ? '' : ' de '.$s['ano']);
            }
            if ($s['tipo'] === 'ano')
            {
                return (string) $s['ano'];
            }
            if ($s['tipo'] === 'trimestre')
            {
                return $s['trimestre'].'º trimestre de '.$s['ano'];
            }
            if ($s['tipo'] === 'relativo' && $s['valor'] === 'ontem')
            {
                return 'ontem ('.$this->data_curta($p['inicio']).')';
            }
            if ($s['tipo'] === 'relativo' && $s['valor'] === 'mes_passado')
            {
                $d = new DateTime($p['inicio']);
                return self::$meses[(int) $d->format('n')].($d->format('Y') === $this->hoje->format('Y') ? '' : ' de '.$d->format('Y'));
            }
        }
        if ($p['inicio'] === $p['fim'])
        {
            return $this->data_curta($p['inicio']);
        }
        return $this->intervalo_curto($p['inicio'], $p['fim']);
    }

    public function rotulo_barra($p, $mostrar_ano = FALSE)
    {
        $s = $p['spec'];
        $mes = NULL;
        if ($s['tipo'] === 'mes')
        {
            $mes = array($s['mes'], $s['ano']);
        }
        elseif ($s['tipo'] === 'relativo' && $s['valor'] === 'mes_passado')
        {
            $d = new DateTime($p['inicio']);
            $mes = array((int) $d->format('n'), (int) $d->format('Y'));
        }
        if ($mes && ! $p['recortado'])
        {
            $nome = self::$meses[$mes[0]];
            return $mostrar_ano ? ucfirst(mb_substr($nome, 0, 3, 'UTF-8')).'/'.$mes[1] : $this->ucfirst_utf8($nome);
        }
        return $p['inicio'] === $p['fim'] ? $this->data_curta($p['inicio']) : $this->intervalo_curto($p['inicio'], $p['fim']);
    }

    public function rotulo_curto($p, $mostrar_ano = FALSE)
    {
        $longo = $this->rotulo_barra($p, $mostrar_ano);
        if (preg_match('/^(\pL{3})\pL*(?:\/(\d{2})(\d{2}))?$/u', $longo, $m))
        {
            return $m[1].(isset($m[3]) ? '/'.$m[3] : '');
        }
        return $longo;
    }

    public function intervalo_curto($ini, $fim)
    {
        return $this->data_curta($ini).' a '.$this->data_curta($fim);
    }

    public function data_curta($data)
    {
        $d = new DateTime($data);
        return $d->format('Y') === $this->hoje->format('Y') ? $d->format('d/m') : $d->format('d/m/Y');
    }

    public function data_valida($s)
    {
        if ( ! is_string($s) OR ! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m))
        {
            return FALSE;
        }
        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    protected function ucfirst_utf8($s)
    {
        return mb_strtoupper(mb_substr($s, 0, 1, 'UTF-8'), 'UTF-8').mb_substr($s, 1, mb_strlen($s, 'UTF-8'), 'UTF-8');
    }
}
