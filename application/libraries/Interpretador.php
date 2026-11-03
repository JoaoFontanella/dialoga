<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Gemini → regras locais. Tudo o que vem de fora (IA ou botões do front) passa pelo mesmo validador. */
class Interpretador {

    public static $intencoes = array('total_periodo', 'vendas_por_produto', 'ranking_produtos', 'comparacao_periodos');

    public $via = NULL;
    public $tempo_ia_ms = 0;

    protected $CI;
    protected $catalogo;

    public function __construct($params = array())
    {
        $this->CI =& get_instance();
        $this->CI->load->library(array('gemini', 'periodo', 'interpretador_local'));
    }

    public function definir_catalogo($catalogo)
    {
        $this->catalogo = $catalogo;
        $this->CI->interpretador_local->definir_catalogo($catalogo);
    }

    public function interpretar($pergunta, $anterior = NULL)
    {
        $t0 = microtime(TRUE);
        $self = $this;
        $validador = function ($texto) use ($self) {
            $dados = json_decode($texto, TRUE);
            if ( ! is_array($dados))
            {
                return array('ok' => FALSE, 'erro' => 'JSON inválido.');
            }
            $n = $self->normalizar_saida($dados);
            return is_string($n) ? array('ok' => FALSE, 'erro' => $n) : array('ok' => TRUE, 'dados' => $n);
        };

        try
        {
            $r = $this->CI->gemini->gerar($this->prompt(), $this->mensagem($pergunta, $anterior), $validador);
            $this->via = $r['modelo'];
            $this->tempo_ia_ms = (int) round((microtime(TRUE) - $t0) * 1000);
            return $r['dados'];
        }
        catch (Gemini_indisponivel $e)
        {
            log_message('error', 'Dialoga: usando regras locais. '.$e->getMessage());
        }

        $this->tempo_ia_ms = (int) round((microtime(TRUE) - $t0) * 1000);
        $this->via = 'regras-locais';
        $saida = $this->CI->interpretador_local->interpretar($pergunta, $anterior);
        $n = $this->normalizar_saida($saida);
        if (is_string($n))
        {
            log_message('error', 'Dialoga: regras locais geraram saída inválida: '.$n);
            return $this->recusa_generica();
        }
        return $n;
    }

    public function tentativas()
    {
        return $this->CI->gemini->tentativas;
    }

    public function prompt()
    {
        $c = $this->catalogo;
        $modelo = file_get_contents($this->CI->config->item('prompt_interpretador', 'dialoga'));

        $filiais = array();
        foreach ($c['filiais'] as $f)
        {
            $filiais[] = '- ['.$f['slug'].'] '.$f['nome'].' ('.$f['cidade'].')';
        }
        $produtos = array();
        foreach ($c['produtos'] as $p)
        {
            $produtos[] = '- '.$p['nome'];
        }
        $dic = array();
        foreach ($c['dicionario'] as $d)
        {
            $dic[] = '- "'.$d['termo'].'": '.$d['significado'].' (origem: '.$d['origem'].')';
        }
        $dias = array('domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado');
        $hoje = new DateTime($c['hoje']);

        return strtr($modelo, array(
            '{{HOJE}}'           => $hoje->format('d/m/Y'),
            '{{DIA_SEMANA}}'     => $dias[(int) $hoje->format('w')],
            '{{BASE_INICIO}}'    => date('d/m/Y', strtotime($c['cobertura']['inicio'])),
            '{{BASE_FIM}}'       => date('d/m/Y', strtotime($c['cobertura']['fim'])),
            '{{PERIODO_PADRAO}}' => $c['periodo_padrao_texto'],
            '{{FILIAIS}}'        => implode("\n", $filiais),
            '{{PRODUTOS}}'       => implode("\n", $produtos),
            '{{DICIONARIO}}'     => $dic ? implode("\n", $dic) : '- (vazio)',
            '{{ANO_PASSADO}}'    => (int) $hoje->format('Y') - 1,
        ));
    }

    protected function mensagem($pergunta, $anterior)
    {
        $msg = "Interpretação anterior nesta conversa: ";
        $msg .= $anterior ? json_encode($anterior, JSON_UNESCAPED_UNICODE) : 'nenhuma';
        $msg .= "\n\nPergunta do gestor:\n<<<\n".$pergunta."\n>>>";
        return $msg;
    }

    public function normalizar_saida($s)
    {
        $tipo = isset($s['tipo']) ? $s['tipo'] : '';

        if ($tipo === 'consulta')
        {
            $i = $this->normalizar_interpretacao(isset($s['interpretacao']) ? $s['interpretacao'] : NULL);
            return is_string($i) ? $i : array('tipo' => 'consulta', 'interpretacao' => $i);
        }

        if ($tipo === 'recusa')
        {
            $r = isset($s['recusa']) && is_array($s['recusa']) ? $s['recusa'] : array();
            $motivo = $this->texto(isset($r['motivo']) ? $r['motivo'] : '', 220);
            if ($motivo === '')
            {
                return 'recusa sem motivo';
            }
            $alt = NULL;
            if ( ! empty($r['alternativa']) && is_array($r['alternativa']))
            {
                $a = $r['alternativa'];
                $ai = $this->normalizar_interpretacao(isset($a['interpretacao']) ? $a['interpretacao'] : NULL);
                $frase = $this->texto(isset($a['frase']) ? $a['frase'] : '', 140);
                $rotulo = $this->texto(isset($a['rotulo']) ? $a['rotulo'] : '', 40);
                if ( ! is_string($ai) && $frase !== '' && $rotulo !== '')
                {
                    $alt = array('frase' => $frase, 'rotulo' => $rotulo, 'interpretacao' => $ai);
                }
            }
            return array('tipo' => 'recusa', 'motivo' => rtrim($motivo, '.'), 'alternativa' => $alt);
        }

        if ($tipo === 'esclarecer')
        {
            $e = isset($s['esclarecer']) && is_array($s['esclarecer']) ? $s['esclarecer'] : array();
            $pergunta = $this->texto(isset($e['pergunta']) ? $e['pergunta'] : '', 240);
            $base = isset($s['interpretacao']) && is_array($s['interpretacao']) ? $s['interpretacao'] : array();
            $opcoes = array();
            foreach (isset($e['opcoes']) && is_array($e['opcoes']) ? array_slice($e['opcoes'], 0, 4) : array() as $o)
            {
                $rotulo = $this->texto(isset($o['rotulo']) ? $o['rotulo'] : '', 40);
                $ajuste = isset($o['ajuste']) && is_array($o['ajuste']) ? $o['ajuste'] : array();
                $completa = $this->normalizar_interpretacao(array_merge($base, $ajuste));
                if ($rotulo !== '' && ! is_string($completa))
                {
                    $opcoes[] = array('rotulo' => $rotulo, 'interpretacao' => $completa);
                }
            }
            if ($pergunta === '' OR count($opcoes) < 2)
            {
                return 'esclarecimento sem pergunta ou com menos de duas opções válidas';
            }
            return array('tipo' => 'esclarecer', 'pergunta' => $pergunta, 'opcoes' => $opcoes);
        }

        return 'tipo desconhecido';
    }

    public function normalizar_interpretacao($i)
    {
        if ( ! is_array($i))
        {
            return 'interpretação ausente';
        }
        $intencao = isset($i['intencao']) ? $i['intencao'] : '';
        if ( ! in_array($intencao, self::$intencoes, TRUE))
        {
            return 'intenção fora das quatro permitidas';
        }

        $metrica = isset($i['metrica']) ? $i['metrica'] : NULL;
        if ($metrica !== NULL && ! in_array($metrica, array('faturadas', 'fechados'), TRUE))
        {
            return 'métrica desconhecida';
        }

        $periodo = isset($i['periodo']) && is_array($i['periodo']) ? $i['periodo'] : NULL;
        if ($periodo !== NULL && is_string($this->CI->periodo->resolver($periodo)))
        {
            return 'período inválido';
        }
        $comp = isset($i['periodo_comparacao']) && is_array($i['periodo_comparacao']) ? $i['periodo_comparacao'] : NULL;
        if ($comp !== NULL && is_string($this->CI->periodo->resolver($comp)))
        {
            return 'período de comparação inválido';
        }

        $filial = isset($i['filial']) && $i['filial'] !== '' ? $i['filial'] : NULL;
        if ($filial !== NULL)
        {
            $ok = FALSE;
            foreach ($this->catalogo['filiais'] as $f)
            {
                $ok = $ok || $f['slug'] === $filial;
            }
            if ( ! $ok)
            {
                return 'filial desconhecida';
            }
        }

        $produto = isset($i['produto']) && is_string($i['produto']) ? $this->texto($i['produto'], 120) : '';
        $produto_id = isset($i['produto_id']) ? (int) $i['produto_id'] : 0;

        $limite = isset($i['limite']) ? (int) $i['limite'] : 5;

        return array(
            'intencao'           => $intencao,
            'metrica'            => $metrica,
            'periodo'            => $periodo,
            'periodo_comparacao' => $comp,
            'filial'             => $filial,
            'agrupar_por'        => isset($i['agrupar_por']) && $i['agrupar_por'] === 'filial' ? 'filial' : NULL,
            'produto'            => $produto !== '' ? $produto : NULL,
            'produto_id'         => $produto_id > 0 ? $produto_id : NULL,
            'ordenar_por'        => isset($i['ordenar_por']) && $i['ordenar_por'] === 'quantidade' ? 'quantidade' : 'valor',
            'limite'             => max(1, min(20, $limite > 0 ? $limite : 5)),
        );
    }

    public function recusa_generica()
    {
        return array(
            'tipo' => 'recusa',
            'motivo' => 'não entendi o que você quer ver nas vendas',
            'alternativa' => NULL,
        );
    }

    protected function texto($s, $max)
    {
        if ( ! is_string($s))
        {
            return '';
        }
        $s = trim(preg_replace('/\s+/u', ' ', strip_tags($s)));
        return mb_substr($s, 0, $max, 'UTF-8');
    }
}
