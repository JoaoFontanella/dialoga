<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Gemini_indisponivel extends Exception {}

/** Cliente do Gemini com rollback de modelos: 429, 404, 5xx, timeout ou JSON inválido passam ao próximo. */
class Gemini {

    public $tentativas = array();
    public $modelo_usado = NULL;

    protected $CI;
    protected $cfg;

    public function __construct($params = array())
    {
        $this->CI =& get_instance();
        $this->CI->config->load('dialoga', TRUE);
        $this->cfg = $this->CI->config->item('gemini', 'dialoga');
        $this->CI->load->driver('cache', array('adapter' => 'file', 'backup' => 'dummy'));
    }

    public function configurado()
    {
        return $this->cfg['chave'] !== '';
    }

    public function modelos()
    {
        return $this->cfg['modelos'];
    }

    public function gerar($sistema, $mensagem, $validador = NULL)
    {
        $this->tentativas = array();
        $this->modelo_usado = NULL;

        if ( ! $this->configurado())
        {
            throw new Gemini_indisponivel('GEMINI_API_KEY não configurada.');
        }

        $inicio = microtime(TRUE);
        foreach ($this->cfg['modelos'] as $modelo)
        {
            $restante = $this->cfg['prazo_total'] - (microtime(TRUE) - $inicio);
            if ($restante < 1.5)
            {
                $this->anotar($modelo, 'sem_tempo', NULL, 0, 0, 0, 'Prazo total da IA esgotado.');
                break;
            }
            if ($this->em_pausa($modelo))
            {
                $this->anotar($modelo, 'em_pausa', NULL, 0, 0, 0, 'Pulado: falhou há pouco.');
                continue;
            }

            $r = $this->chamar($modelo, $sistema, $mensagem, min($this->cfg['timeout_tentativa'], $restante));

            if ($r['resultado'] === 'sucesso' && $validador !== NULL)
            {
                $v = call_user_func($validador, $r['texto']);
                if (empty($v['ok']))
                {
                    $r['resultado'] = 'resposta_invalida';
                    $r['detalhe'] = isset($v['erro']) ? $v['erro'] : 'Formato inesperado.';
                }
                else
                {
                    $r['dados'] = $v['dados'];
                }
            }

            $this->anotar($modelo, $r['resultado'], $r['http'], $r['latencia'], $r['tokens_in'], $r['tokens_out'], $r['detalhe']);

            if ($r['resultado'] === 'sucesso')
            {
                $this->modelo_usado = $modelo;
                return array(
                    'texto'  => $r['texto'],
                    'dados'  => isset($r['dados']) ? $r['dados'] : NULL,
                    'modelo' => $modelo,
                );
            }

            if ($r['resultado'] === 'auth' OR $r['resultado'] === 'rede')
            {
                // Chave ou rede valem para todos os modelos: não adianta tentar o próximo.
                throw new Gemini_indisponivel($r['resultado'].': '.$r['detalhe']);
            }

            $this->pausar($modelo, $r);
        }

        throw new Gemini_indisponivel('Nenhum modelo respondeu: '.$this->resumo());
    }

    protected function chamar($modelo, $sistema, $mensagem, $timeout)
    {
        $corpo = array(
            'systemInstruction' => array('parts' => array(array('text' => $sistema))),
            'contents' => array(array('role' => 'user', 'parts' => array(array('text' => $mensagem)))),
            'generationConfig' => array(
                'temperature'      => $this->cfg['temperatura'],
                'maxOutputTokens'  => $this->cfg['max_tokens'],
                'responseMimeType' => 'application/json',
            ),
        );

        $ch = curl_init(sprintf($this->cfg['endpoint'], rawurlencode($modelo)));
        $opcoes = array(
            CURLOPT_POST           => TRUE,
            CURLOPT_POSTFIELDS     => json_encode($corpo),
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_HTTPHEADER     => array('Content-Type: application/json', 'x-goog-api-key: '.$this->cfg['chave']),
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT_MS     => (int) max(1000, $timeout * 1000),
            CURLOPT_SSL_VERIFYPEER => TRUE,
            CURLOPT_SSL_VERIFYHOST => 2,
        );
        if ($this->cfg['ca_bundle'] !== '')
        {
            $opcoes[CURLOPT_CAINFO] = $this->cfg['ca_bundle'];
        }
        curl_setopt_array($ch, $opcoes);

        $t0 = microtime(TRUE);
        $bruto = curl_exec($ch);
        $erro_curl = curl_errno($ch);
        $msg_curl = curl_error($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $latencia = (int) round((microtime(TRUE) - $t0) * 1000);

        $r = array('resultado' => 'erro', 'http' => $http ? $http : NULL, 'latencia' => $latencia,
                   'tokens_in' => 0, 'tokens_out' => 0, 'texto' => NULL, 'detalhe' => NULL, 'corpo' => NULL);

        if ($erro_curl)
        {
            $r['resultado'] = $erro_curl === 28 ? 'timeout' : 'rede';
            $r['detalhe'] = 'cURL '.$erro_curl.': '.$msg_curl;
            return $r;
        }

        $json = json_decode($bruto, TRUE);
        $r['corpo'] = $json;

        if ($http !== 200)
        {
            $status = isset($json['error']['status']) ? $json['error']['status'] : '';
            $r['detalhe'] = 'HTTP '.$http.' '.$status.': '.mb_substr(isset($json['error']['message']) ? $json['error']['message'] : (string) $bruto, 0, 300, 'UTF-8');

            if ($http === 429)
            {
                $r['resultado'] = 'quota';
            }
            elseif ($http === 404)
            {
                $r['resultado'] = 'indisponivel';
            }
            elseif ($http === 401 OR $http === 403 OR strpos((string) $bruto, 'API_KEY_INVALID') !== FALSE)
            {
                $r['resultado'] = 'auth';
            }
            elseif ($http >= 500)
            {
                $r['resultado'] = 'sobrecarga';
            }
            else
            {
                // 400: pode ser parâmetro que só este modelo não aceita; tenta o próximo
                $r['resultado'] = 'requisicao_invalida';
            }
            return $r;
        }

        if (isset($json['usageMetadata']))
        {
            $u = $json['usageMetadata'];
            $r['tokens_in'] = isset($u['promptTokenCount']) ? (int) $u['promptTokenCount'] : 0;
            $r['tokens_out'] = (isset($u['candidatesTokenCount']) ? (int) $u['candidatesTokenCount'] : 0)
                             + (isset($u['thoughtsTokenCount']) ? (int) $u['thoughtsTokenCount'] : 0);
        }

        $cand = isset($json['candidates'][0]) ? $json['candidates'][0] : NULL;
        $fim = isset($cand['finishReason']) ? $cand['finishReason'] : '';
        $texto = '';
        if ($cand && isset($cand['content']['parts']))
        {
            foreach ($cand['content']['parts'] as $parte)
            {
                if (isset($parte['text']) && empty($parte['thought']))
                {
                    $texto .= $parte['text'];
                }
            }
        }

        if ($texto === '' OR ($fim !== '' && $fim !== 'STOP'))
        {
            $r['resultado'] = 'resposta_invalida';
            $r['detalhe'] = 'Sem texto utilizável (finishReason: '.($fim !== '' ? $fim : 'vazio').').';
            return $r;
        }

        $r['resultado'] = 'sucesso';
        $r['texto'] = $this->limpar($texto);
        return $r;
    }

    protected function limpar($texto)
    {
        $texto = trim($texto);
        $texto = preg_replace('/^```(?:json)?\s*/i', '', $texto);
        $texto = preg_replace('/\s*```$/', '', $texto);
        return trim($texto);
    }

    protected function pausar($modelo, $r)
    {
        $segundos = 0;
        if ($r['resultado'] === 'quota')
        {
            $segundos = $this->cfg['pausas']['quota'];
            $corpo = json_encode($r['corpo']);
            if (stripos($corpo, 'PerDay') !== FALSE)
            {
                $segundos = 3600;
            }
            elseif (preg_match('/"retryDelay"\s*:\s*"(\d+)(?:\.\d+)?s"/', $corpo, $m))
            {
                $segundos = max(10, min(300, (int) $m[1]));
            }
        }
        elseif (isset($this->cfg['pausas'][$r['resultado']]))
        {
            $segundos = $this->cfg['pausas'][$r['resultado']];
        }
        if ($segundos > 0)
        {
            $this->CI->cache->save($this->chave_pausa($modelo), time() + $segundos, $segundos);
        }
    }

    protected function em_pausa($modelo)
    {
        $ate = $this->CI->cache->get($this->chave_pausa($modelo));
        return $ate && $ate > time();
    }

    protected function chave_pausa($modelo)
    {
        return 'dialoga_pausa_'.preg_replace('/[^a-z0-9_.-]/i', '_', $modelo);
    }

    protected function anotar($modelo, $resultado, $http, $latencia, $tin, $tout, $detalhe)
    {
        $this->tentativas[] = array(
            'modelo' => $modelo, 'resultado' => $resultado, 'http_status' => $http,
            'latencia_ms' => $latencia, 'tokens_entrada' => $tin, 'tokens_saida' => $tout,
            'detalhe' => $detalhe,
        );
        if ($resultado !== 'sucesso')
        {
            log_message('error', 'Gemini '.$modelo.' → '.$resultado.($detalhe ? ' · '.$detalhe : ''));
        }
    }

    protected function resumo()
    {
        $partes = array();
        foreach ($this->tentativas as $t)
        {
            $partes[] = $t['modelo'].'='.$t['resultado'];
        }
        return implode(', ', $partes);
    }

    public function listar_modelos()
    {
        $ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models?pageSize=200');
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_HTTPHEADER => array('x-goog-api-key: '.$this->cfg['chave']),
            CURLOPT_TIMEOUT => 10,
        ));
        $bruto = curl_exec($ch);
        curl_close($ch);
        $json = json_decode($bruto, TRUE);
        $nomes = array();
        if (isset($json['models']))
        {
            foreach ($json['models'] as $m)
            {
                if (in_array('generateContent', isset($m['supportedGenerationMethods']) ? $m['supportedGenerationMethods'] : array(), TRUE))
                {
                    $nomes[] = str_replace('models/', '', $m['name']);
                }
            }
        }
        return $nomes;
    }
}
