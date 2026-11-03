<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Historico_model extends CI_Model {

    protected $hist;

    protected function conectar()
    {
        if ($this->hist === NULL)
        {
            $db = $this->load->database('historico', TRUE);
            $this->hist = ($db && $db->conn_id) ? $db : FALSE;
            if ( ! $this->hist)
            {
                log_message('error', 'Dialoga: banco de histórico indisponível.');
            }
        }
        return $this->hist !== FALSE;
    }

    public function dicionario()
    {
        if ( ! $this->conectar())
        {
            return array();
        }
        $q = $this->hist->query("SELECT termo, significado, tipo, valor, origem FROM dicionario ORDER BY origem DESC, termo");
        return $q ? $q->result_array() : array();
    }

    public function aprender($termo, $significado, $tipo, $valor)
    {
        if ( ! $this->conectar())
        {
            return FALSE;
        }
        return (bool) $this->hist->query(
            "INSERT INTO dicionario (termo, significado, tipo, valor, origem)
             VALUES (?, ?, ?, ?, 'gestor')
             ON CONFLICT (termo) DO UPDATE
                SET significado = EXCLUDED.significado, valor = EXCLUDED.valor,
                    origem = 'gestor', alterado_em = now()",
            array($termo, $significado, $tipo, $valor));
    }

    /** Perguntas digitadas pelo gestor (cliques em botões ficam só dentro da conversa). */
    public function recentes($cliente, $limite = 30)
    {
        if ( ! $this->conectar())
        {
            return array();
        }
        $q = $this->hist->query(
            "SELECT id, conversa, pergunta, criado_em
               FROM perguntas
              WHERE cliente = ? AND conversa IS NOT NULL AND via <> 'acao' AND tipo <> 'erro'
              ORDER BY id DESC LIMIT ?",
            array($cliente, (int) $limite));
        $linhas = array();
        foreach ($q ? $q->result_array() : array() as $r)
        {
            $linhas[] = array('id' => (int) $r['id'], 'conversa' => $r['conversa'], 'pergunta' => $r['pergunta'], 'criado_em' => $r['criado_em']);
        }
        return $linhas;
    }

    public function conversa($cliente, $conversa)
    {
        if ( ! $this->conectar())
        {
            return array();
        }
        $q = $this->hist->query(
            "SELECT id, pergunta, resposta FROM perguntas
              WHERE cliente = ? AND conversa = ? AND tipo <> 'erro'
              ORDER BY id LIMIT 200",
            array($cliente, $conversa));
        $turnos = array();
        foreach ($q ? $q->result_array() : array() as $r)
        {
            $turnos[] = array('id' => (int) $r['id'], 'pergunta' => $r['pergunta'], 'resposta' => json_decode($r['resposta'], TRUE));
        }
        return $turnos;
    }

    public function ultima_interpretacao($cliente, $conversa)
    {
        if ( ! $this->conectar())
        {
            return NULL;
        }
        $q = $this->hist->query(
            "SELECT interpretacao FROM perguntas
              WHERE cliente = ? AND conversa = ? AND interpretacao IS NOT NULL
              ORDER BY id DESC LIMIT 1",
            array($cliente, $conversa));
        $r = $q ? $q->row_array() : NULL;
        return $r ? json_decode($r['interpretacao'], TRUE) : NULL;
    }

    public function registrar($dados, $tentativas)
    {
        if ( ! $this->conectar())
        {
            return NULL;
        }
        $j = function ($v) {
            return $v === NULL ? NULL : json_encode($v, defined('JSON_UNESCAPED_UNICODE') ? JSON_UNESCAPED_UNICODE : 0);
        };

        $tokens_in = 0;
        $tokens_out = 0;
        foreach ($tentativas as $t)
        {
            $tokens_in += (int) $t['tokens_entrada'];
            $tokens_out += (int) $t['tokens_saida'];
        }

        $q = $this->hist->query(
            "INSERT INTO perguntas (cliente, conversa, pergunta, contexto, interpretacao, via, tipo, resposta,
                                    tempo_total_ms, tempo_ia_ms, tokens_entrada, tokens_saida)
             VALUES (?, ?, ?, ?::jsonb, ?::jsonb, ?, ?, ?::jsonb, ?, ?, ?, ?) RETURNING id",
            array(
                $dados['cliente'], $dados['conversa'], $dados['pergunta'], $j($dados['contexto']), $j($dados['interpretacao']),
                $dados['via'], $dados['tipo'], $j($dados['resposta']),
                (int) $dados['tempo_total_ms'], (int) $dados['tempo_ia_ms'], $tokens_in, $tokens_out,
            ));
        if ( ! $q)
        {
            log_message('error', 'Dialoga: falha ao gravar pergunta no histórico.');
            return NULL;
        }
        $id = (int) $q->row()->id;

        foreach ($tentativas as $i => $t)
        {
            $this->hist->query(
                "INSERT INTO tentativas_ia (pergunta_id, ordem, modelo, resultado, http_status, latencia_ms,
                                            tokens_entrada, tokens_saida, detalhe)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                array($id, $i + 1, $t['modelo'], $t['resultado'], $t['http_status'] ? (int) $t['http_status'] : NULL,
                      (int) $t['latencia_ms'], (int) $t['tokens_entrada'], (int) $t['tokens_saida'],
                      mb_substr((string) $t['detalhe'], 0, 1000, 'UTF-8')));
        }
        return $id;
    }
}
