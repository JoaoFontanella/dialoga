<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Camada semântica: a IA escolhe a intenção; cada intenção tem SQL fixo e parametrizado. */
class Vendas_model extends CI_Model {

    public static $metricas = array(
        'faturadas' => array(
            'status'     => array('enviado', 'entregue'),
            'coluna'     => 'faturado_em',
            'nome'       => 'vendas faturadas',
            'origem'     => 'pedidos faturados',
            'criterio'   => 'pela data da nota · sem cancelados',
        ),
        'fechados' => array(
            'status'     => array('confirmado', 'enviado', 'entregue'),
            'coluna'     => 'criado_em',
            'nome'       => 'pedidos fechados',
            'origem'     => 'pedidos fechados',
            'criterio'   => 'pela data do pedido · sem pendentes e cancelados',
        ),
    );

    protected $erp;
    protected $cache = array();

    public function conectar()
    {
        if ($this->erp)
        {
            return TRUE;
        }
        $this->erp = $this->load->database('erp', TRUE);
        if ( ! $this->erp OR ! $this->erp->conn_id)
        {
            $this->erp = NULL;
            return FALSE;
        }
        $this->erp->simple_query("SET default_transaction_read_only = on");
        $this->erp->simple_query("SET statement_timeout = '5s'");
        return TRUE;
    }

    public function cobertura()
    {
        if (isset($this->cache['cobertura']))
        {
            return $this->cache['cobertura'];
        }
        $q = $this->erp->query("SELECT MIN(criado_em)::date::text AS inicio,
                                       MAX(GREATEST(criado_em, COALESCE(faturado_em, criado_em)))::date::text AS fim
                                  FROM public.pedidos");
        return $this->cache['cobertura'] = $q ? $q->row_array() : FALSE;
    }

    public function filiais()
    {
        if ( ! isset($this->cache['filiais']))
        {
            $q = $this->erp->query("SELECT id, slug, nome, cidade FROM public.filiais WHERE ativa ORDER BY id");
            $this->cache['filiais'] = $q ? $q->result_array() : array();
        }
        return $this->cache['filiais'];
    }

    public function produtos()
    {
        if ( ! isset($this->cache['produtos']))
        {
            $q = $this->erp->query("SELECT id, nome FROM public.produtos WHERE ativo ORDER BY nome");
            $this->cache['produtos'] = $q ? $q->result_array() : array();
        }
        return $this->cache['produtos'];
    }

    public function filial_por_slug($slug)
    {
        foreach ($this->filiais() as $f)
        {
            if ($f['slug'] === $slug)
            {
                return $f;
            }
        }
        return NULL;
    }

    public function buscar_produto($texto)
    {
        $texto = trim($texto);
        $alvo = $this->sem_acento($texto);
        $palavras = array_filter(preg_split('/\s+/', $alvo), function ($p) { return mb_strlen($p, 'UTF-8') >= 2; });
        $achados = array();
        foreach ($this->produtos() as $p)
        {
            $nome = $this->sem_acento($p['nome']);
            if ($nome === $alvo)
            {
                return array($p);
            }
            $todas = count($palavras) > 0;
            foreach ($palavras as $w)
            {
                if (strpos($nome, $w) === FALSE)
                {
                    $todas = FALSE;
                    break;
                }
            }
            if ($todas)
            {
                $achados[] = $p;
            }
        }
        return $achados;
    }

    public function total($metrica, $inicio, $fim, $filial_id = NULL)
    {
        list($where, $binds) = $this->filtro($metrica, $inicio, $fim, $filial_id);
        $q = $this->erp->query("SELECT COALESCE(SUM(p.total), 0)::numeric(14,2) AS valor, COUNT(*) AS pedidos
                                  FROM public.pedidos p WHERE ".$where, $binds);
        if ( ! $q)
        {
            return FALSE;
        }
        $r = $q->row_array();
        return array('valor' => (float) $r['valor'], 'pedidos' => (int) $r['pedidos']);
    }

    public function total_por_filial($metrica, $inicio, $fim)
    {
        $m = self::$metricas[$metrica];
        $marcas = implode(',', array_fill(0, count($m['status']), '?'));
        $binds = array_merge($m['status'], array($inicio, $fim));
        $q = $this->erp->query("SELECT f.slug, f.nome, COALESCE(SUM(p.total), 0)::numeric(14,2) AS valor, COUNT(p.id) AS pedidos
                                  FROM public.filiais f
                                  LEFT JOIN public.pedidos p
                                    ON p.filial_id = f.id
                                   AND p.status IN (".$marcas.")
                                   AND p.".$m['coluna']." >= ?::date
                                   AND p.".$m['coluna']." < ?::date + 1
                                 WHERE f.ativa
                                 GROUP BY f.id, f.slug, f.nome
                                 ORDER BY valor DESC", $binds);
        if ( ! $q)
        {
            return FALSE;
        }
        $linhas = array();
        foreach ($q->result_array() as $r)
        {
            $linhas[] = array('slug' => $r['slug'], 'nome' => $r['nome'], 'valor' => (float) $r['valor'], 'pedidos' => (int) $r['pedidos']);
        }
        return $linhas;
    }

    public function por_produto($metrica, $inicio, $fim, $filial_id, $ordenar, $limite, $produto_id = NULL)
    {
        list($where, $binds) = $this->filtro($metrica, $inicio, $fim, $filial_id);
        $ordem = $ordenar === 'quantidade' ? 'quantidade DESC, valor DESC' : 'valor DESC, quantidade DESC';

        $sql = "SELECT pr.id, pr.nome, SUM(i.quantidade) AS quantidade, SUM(i.subtotal)::numeric(14,2) AS valor
                  FROM public.itens_pedido i
                  JOIN public.pedidos p   ON p.id = i.pedido_id
                  JOIN public.produtos pr ON pr.id = i.produto_id
                 WHERE ".$where;
        $b = $binds;
        if ($produto_id)
        {
            $sql .= " AND pr.id = ?";
            $b[] = (int) $produto_id;
        }
        $sql .= " GROUP BY pr.id, pr.nome ORDER BY ".$ordem." LIMIT ?";
        $b[] = max(1, min(20, (int) $limite));

        $q = $this->erp->query($sql, $b);
        $t = $this->erp->query("SELECT COALESCE(SUM(i.subtotal), 0)::numeric(14,2) AS valor, COALESCE(SUM(i.quantidade), 0) AS quantidade
                                  FROM public.itens_pedido i JOIN public.pedidos p ON p.id = i.pedido_id
                                 WHERE ".$where, $binds);
        if ( ! $q OR ! $t)
        {
            return FALSE;
        }
        $tot = $t->row_array();
        $linhas = array();
        foreach ($q->result_array() as $r)
        {
            $linhas[] = array('id' => (int) $r['id'], 'produto' => $r['nome'], 'quantidade' => (int) $r['quantidade'], 'valor' => (float) $r['valor']);
        }
        return array('linhas' => $linhas, 'total_valor' => (float) $tot['valor'], 'total_quantidade' => (int) $tot['quantidade']);
    }

    protected function filtro($metrica, $inicio, $fim, $filial_id)
    {
        $m = self::$metricas[$metrica];
        $marcas = implode(',', array_fill(0, count($m['status']), '?'));
        // coluna vem da tabela de métricas acima, nunca da pergunta
        $where = "p.status IN (".$marcas.") AND p.".$m['coluna']." >= ?::date AND p.".$m['coluna']." < ?::date + 1";
        $binds = array_merge($m['status'], array($inicio, $fim));
        if ($filial_id)
        {
            $where .= " AND p.filial_id = ?";
            $binds[] = (int) $filial_id;
        }
        return array($where, $binds);
    }

    public function sem_acento($s)
    {
        $s = mb_strtolower($s, 'UTF-8');
        return strtr($s, array('á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'é' => 'e', 'ê' => 'e',
            'è' => 'e', 'í' => 'i', 'ì' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ò' => 'o', 'ú' => 'u',
            'ü' => 'u', 'ç' => 'c', 'ñ' => 'n', '"' => '', '“' => '', '”' => ''));
    }
}
