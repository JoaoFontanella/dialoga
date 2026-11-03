<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Api extends CI_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->config->load('dialoga', TRUE);
        $this->load->library('periodo', array('hoje' => $this->config->item('hoje', 'dialoga')));
        $this->load->model(array('vendas_model', 'historico_model'));
    }

    public function status()
    {
        if ($this->input->method() !== 'get')
        {
            return $this->json(array('erro' => 'Método não permitido.'), 405);
        }
        if ( ! $this->vendas_model->conectar() OR ! ($cob = $this->vendas_model->cobertura()) OR ! $cob['inicio'])
        {
            return $this->json(array('erro' => 'Não foi possível conectar à base de vendas.'), 503);
        }
        $padrao = $this->periodo->resolver($this->config->item('periodo_padrao', 'dialoga'));
        return $this->json(array(
            'modo'                  => 'postgres',
            'hoje'                  => $this->periodo->hoje(),
            'base'                  => $cob,
            'nome_base'             => $this->config->item('nome_base', 'dialoga'),
            'periodo_padrao'        => $this->periodo->rotulo_barra($padrao, TRUE),
            'usuario'               => $this->config->item('usuario', 'dialoga'),
            'consultas_disponiveis' => TRUE,
        ));
    }

    public function perguntar()
    {
        $t0 = microtime(TRUE);
        if ($this->input->method() !== 'post')
        {
            return $this->json(array('erro' => 'Método não permitido.'), 405);
        }

        $corpo = json_decode($this->input->raw_input_stream, TRUE);
        $pergunta = is_array($corpo) && isset($corpo['pergunta']) && is_string($corpo['pergunta']) ? trim($corpo['pergunta']) : '';
        $contexto = is_array($corpo) && isset($corpo['contexto']) && is_array($corpo['contexto']) ? $corpo['contexto'] : NULL;
        $conversa = is_array($corpo) && isset($corpo['conversa_id']) ? $this->id_valido($corpo['conversa_id']) : NULL;
        if ($conversa === NULL)
        {
            $conversa = $this->novo_id();
        }
        $cliente = $this->cliente();
        if ($pergunta === '' OR mb_strlen($pergunta, 'UTF-8') > 500)
        {
            return $this->json(array('erro' => 'Envie uma pergunta de até 500 caracteres.'), 422);
        }

        if ( ! $this->vendas_model->conectar() OR ! ($catalogo = $this->catalogo()))
        {
            return $this->json(array('erro' => 'Não foi possível conectar à base de vendas.'), 503);
        }

        $this->load->library(array('interpretador', 'respondedor'));
        $this->interpretador->definir_catalogo($catalogo);
        $this->respondedor->definir_catalogo($catalogo);

        $via = 'acao';
        $resultado = NULL;

        if ($contexto && isset($contexto['acao']) && $contexto['acao'] === 'explicar_recusa')
        {
            $motivo = isset($contexto['motivo']) && is_string($contexto['motivo']) ? mb_substr(strip_tags($contexto['motivo']), 0, 220, 'UTF-8') : '';
            $resultado = array('resposta' => $this->redator->explicar_recusa($motivo), 'interpretacao' => NULL);
        }
        elseif ($contexto && isset($contexto['interpretacao']))
        {
            $i = $this->interpretador->normalizar_interpretacao($contexto['interpretacao']);
            if (is_string($i))
            {
                return $this->json(array('erro' => 'Contexto inválido.'), 422);
            }
            if (isset($contexto['aprender']) && in_array($contexto['aprender'], array('faturadas', 'fechados'), TRUE))
            {
                $this->historico_model->aprender('vendas',
                    'quando o gestor diz vendas ou vendemos, quer '.($contexto['aprender'] === 'faturadas' ? 'vendas faturadas' : 'pedidos fechados'),
                    'metrica', $contexto['aprender']);
                $i['metrica'] = $contexto['aprender'];
            }
            $resultado = $this->respondedor->responder(array('tipo' => 'consulta', 'interpretacao' => $i));
        }
        else
        {
            $anterior = $this->historico_model->ultima_interpretacao($cliente, $conversa);
            $anterior = $anterior ? $this->interpretador->normalizar_interpretacao($anterior) : NULL;
            $saida = $this->interpretador->interpretar($pergunta, is_array($anterior) ? $anterior : NULL);
            $via = $this->interpretador->via;
            $resultado = $this->respondedor->responder($saida);
        }

        if ($resultado === FALSE)
        {
            return $this->json(array('erro' => 'A consulta à base falhou.'), 503);
        }

        $resposta = $resultado['resposta'];
        $resposta['contexto'] = $resultado['interpretacao'] ? array('interpretacao' => $resultado['interpretacao']) : NULL;
        $resposta['via'] = $via;
        $resposta['conversa_id'] = $conversa;
        $resposta['tempo_ms'] = (int) round((microtime(TRUE) - $t0) * 1000);

        $this->historico_model->registrar(array(
            'cliente'        => $cliente,
            'conversa'       => $conversa,
            'pergunta'       => $pergunta,
            'contexto'       => $contexto,
            'interpretacao'  => $resultado['interpretacao'],
            'via'            => $via,
            'tipo'           => $resposta['tipo'],
            'resposta'       => $resposta,
            'tempo_total_ms' => $resposta['tempo_ms'],
            'tempo_ia_ms'    => $via === 'acao' ? 0 : $this->interpretador->tempo_ia_ms,
        ), $via === 'acao' ? array() : $this->interpretador->tentativas());

        return $this->json($resposta);
    }

    public function historico()
    {
        if ($this->input->method() !== 'get')
        {
            return $this->json(array('erro' => 'Método não permitido.'), 405);
        }
        return $this->json(array('perguntas' => $this->historico_model->recentes($this->cliente())));
    }

    public function conversa($id = '')
    {
        $id = $this->id_valido($id);
        if ($this->input->method() !== 'get' OR $id === NULL)
        {
            return $this->json(array('erro' => 'Conversa inválida.'), 404);
        }
        $turnos = $this->historico_model->conversa($this->cliente(), $id);
        if ( ! $turnos)
        {
            return $this->json(array('erro' => 'Conversa não encontrada.'), 404);
        }
        return $this->json(array('id' => $id, 'turnos' => $turnos));
    }

    /** Identifica o navegador por um cookie de longa duração; o histórico é por navegador. */
    protected function cliente()
    {
        $id = $this->id_valido($this->input->cookie('dialoga_cliente'));
        if ($id === NULL)
        {
            $id = $this->novo_id();
            $this->input->set_cookie(array('name' => 'dialoga_cliente', 'value' => $id, 'expire' => 31536000, 'httponly' => TRUE));
            $_COOKIE['dialoga_cliente'] = $id;
        }
        return $id;
    }

    protected function id_valido($id)
    {
        return is_string($id) && preg_match('/^[a-zA-Z0-9-]{8,40}$/', $id) ? $id : NULL;
    }

    protected function novo_id()
    {
        $bytes = function_exists('openssl_random_pseudo_bytes') ? openssl_random_pseudo_bytes(16) : uniqid(mt_rand(), TRUE);
        return substr(bin2hex($bytes), 0, 32);
    }

    protected function catalogo()
    {
        $cob = $this->vendas_model->cobertura();
        if ( ! $cob OR ! $cob['inicio'])
        {
            return FALSE;
        }
        $padrao = $this->config->item('periodo_padrao', 'dialoga');
        $p = $this->periodo->resolver($padrao);
        return array(
            'hoje'                 => $this->periodo->hoje(),
            'cobertura'            => $cob,
            'filiais'              => $this->vendas_model->filiais(),
            'produtos'             => $this->vendas_model->produtos(),
            'dicionario'           => $this->historico_model->dicionario(),
            'periodo_padrao'       => $padrao,
            'periodo_padrao_texto' => $this->periodo->rotulo($p).' ('.$this->periodo->intervalo_curto($p['inicio'], $p['fim']).')',
        );
    }

    protected function json($dados, $status = 200)
    {
        $this->output
            ->set_status_header($status)
            ->set_content_type('application/json', 'utf-8')
            ->set_header('Cache-Control: no-store')
            ->set_output(json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
