--
-- PostgreSQL database dump
--

\restrict jebnh59W5Ee9FZ5YH9r68m5JhhxEyC9LCyRfMerSynkE721va8UMn4UwIkJr3HM

-- Dumped from database version 16.15 (Ubuntu 16.15-0ubuntu0.24.04.1)
-- Dumped by pg_dump version 16.15 (Ubuntu 16.15-0ubuntu0.24.04.1)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: pg_stat_statements; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS pg_stat_statements WITH SCHEMA public;


--
-- Name: EXTENSION pg_stat_statements; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION pg_stat_statements IS 'track planning and execution statistics of all SQL statements executed';


--
-- Name: pgcrypto; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS pgcrypto WITH SCHEMA public;


--
-- Name: EXTENSION pgcrypto; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION pgcrypto IS 'cryptographic functions';


SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: avaliacoes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.avaliacoes (
    id integer NOT NULL,
    produto_id integer,
    cliente_id integer,
    nota smallint,
    comentario text,
    criado_em timestamp without time zone DEFAULT now(),
    CONSTRAINT avaliacoes_nota_check CHECK (((nota >= 1) AND (nota <= 5)))
);


--
-- Name: avaliacoes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.avaliacoes_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: avaliacoes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.avaliacoes_id_seq OWNED BY public.avaliacoes.id;


--
-- Name: categorias; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.categorias (
    id integer NOT NULL,
    nome character varying(60) NOT NULL,
    descricao text,
    pai_id integer
);


--
-- Name: categorias_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.categorias_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: categorias_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.categorias_id_seq OWNED BY public.categorias.id;


--
-- Name: clientes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.clientes (
    id integer NOT NULL,
    nome character varying(120) NOT NULL,
    email character varying(180) NOT NULL,
    cpf character varying(14),
    telefone character varying(20),
    cidade character varying(80),
    estado character(2),
    criado_em timestamp without time zone DEFAULT now(),
    ativo boolean DEFAULT true
);


--
-- Name: TABLE clientes; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.clientes IS 'Tabela de clientes do e-commerce';


--
-- Name: clientes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.clientes_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: clientes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.clientes_id_seq OWNED BY public.clientes.id;


--
-- Name: itens_pedido; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.itens_pedido (
    id integer NOT NULL,
    pedido_id integer NOT NULL,
    produto_id integer NOT NULL,
    quantidade integer NOT NULL,
    preco_unit numeric(10,2) NOT NULL,
    subtotal numeric(12,2) GENERATED ALWAYS AS (((quantidade)::numeric * preco_unit)) STORED,
    CONSTRAINT itens_pedido_quantidade_check CHECK ((quantidade > 0))
);


--
-- Name: itens_pedido_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.itens_pedido_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: itens_pedido_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.itens_pedido_id_seq OWNED BY public.itens_pedido.id;


--
-- Name: movimentacoes_estoque; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.movimentacoes_estoque (
    id integer NOT NULL,
    produto_id integer,
    tipo character varying(20),
    quantidade integer NOT NULL,
    motivo text,
    criado_em timestamp without time zone DEFAULT now(),
    CONSTRAINT movimentacoes_estoque_tipo_check CHECK (((tipo)::text = ANY (ARRAY['entrada'::text, 'saida'::text, 'ajuste'::text, 'devolucao'::text])))
);


--
-- Name: movimentacoes_estoque_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.movimentacoes_estoque_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: movimentacoes_estoque_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.movimentacoes_estoque_id_seq OWNED BY public.movimentacoes_estoque.id;


--
-- Name: pagamentos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.pagamentos (
    id integer NOT NULL,
    pedido_id integer NOT NULL,
    metodo character varying(30),
    valor numeric(12,2),
    status character varying(20) DEFAULT 'pendente'::character varying,
    transacao_id character varying(100),
    pago_em timestamp without time zone,
    CONSTRAINT pagamentos_metodo_check CHECK (((metodo)::text = ANY (ARRAY['cartao'::text, 'pix'::text, 'boleto'::text, 'transferencia'::text]))),
    CONSTRAINT pagamentos_status_check CHECK (((status)::text = ANY (ARRAY['pendente'::text, 'aprovado'::text, 'recusado'::text, 'estornado'::text])))
);


--
-- Name: pagamentos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.pagamentos_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: pagamentos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.pagamentos_id_seq OWNED BY public.pagamentos.id;


--
-- Name: pedidos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.pedidos (
    id integer NOT NULL,
    cliente_id integer NOT NULL,
    status character varying(30) DEFAULT 'pendente'::character varying NOT NULL,
    total numeric(12,2),
    endereco_entrega text,
    criado_em timestamp without time zone DEFAULT now(),
    atualizado_em timestamp without time zone DEFAULT now(),
    CONSTRAINT pedidos_status_check CHECK (((status)::text = ANY (ARRAY['pendente'::text, 'confirmado'::text, 'enviado'::text, 'entregue'::text, 'cancelado'::text])))
);


--
-- Name: TABLE pedidos; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.pedidos IS 'Pedidos realizados pelos clientes';


--
-- Name: pedidos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.pedidos_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: pedidos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.pedidos_id_seq OWNED BY public.pedidos.id;


--
-- Name: produtos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.produtos (
    id integer NOT NULL,
    nome character varying(200) NOT NULL,
    descricao text,
    preco numeric(10,2) NOT NULL,
    estoque integer DEFAULT 0 NOT NULL,
    categoria_id integer,
    sku character varying(50),
    peso_kg numeric(6,3),
    criado_em timestamp without time zone DEFAULT now(),
    ativo boolean DEFAULT true,
    CONSTRAINT produtos_estoque_check CHECK ((estoque >= 0)),
    CONSTRAINT produtos_preco_check CHECK ((preco >= (0)::numeric))
);


--
-- Name: TABLE produtos; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.produtos IS 'Catálogo de produtos disponíveis';


--
-- Name: produtos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.produtos_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: produtos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.produtos_id_seq OWNED BY public.produtos.id;


--
-- Name: vw_estoque_critico; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.vw_estoque_critico AS
 SELECT id,
    nome,
    estoque,
    preco
   FROM public.produtos
  WHERE ((estoque < 20) AND (ativo = true))
  ORDER BY estoque;


--
-- Name: vw_pedidos_completos; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.vw_pedidos_completos AS
 SELECT p.id AS pedido_id,
    c.nome AS cliente,
    c.email,
    p.status,
    p.total,
    p.criado_em,
    count(i.id) AS qtd_itens,
    pg.status AS status_pagamento,
    pg.metodo AS metodo_pagamento
   FROM (((public.pedidos p
     JOIN public.clientes c ON ((c.id = p.cliente_id)))
     LEFT JOIN public.itens_pedido i ON ((i.pedido_id = p.id)))
     LEFT JOIN public.pagamentos pg ON ((pg.pedido_id = p.id)))
  GROUP BY p.id, c.nome, c.email, p.status, p.total, p.criado_em, pg.status, pg.metodo;


--
-- Name: avaliacoes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.avaliacoes ALTER COLUMN id SET DEFAULT nextval('public.avaliacoes_id_seq'::regclass);


--
-- Name: categorias id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categorias ALTER COLUMN id SET DEFAULT nextval('public.categorias_id_seq'::regclass);


--
-- Name: clientes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clientes ALTER COLUMN id SET DEFAULT nextval('public.clientes_id_seq'::regclass);


--
-- Name: itens_pedido id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.itens_pedido ALTER COLUMN id SET DEFAULT nextval('public.itens_pedido_id_seq'::regclass);


--
-- Name: movimentacoes_estoque id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.movimentacoes_estoque ALTER COLUMN id SET DEFAULT nextval('public.movimentacoes_estoque_id_seq'::regclass);


--
-- Name: pagamentos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pagamentos ALTER COLUMN id SET DEFAULT nextval('public.pagamentos_id_seq'::regclass);


--
-- Name: pedidos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pedidos ALTER COLUMN id SET DEFAULT nextval('public.pedidos_id_seq'::regclass);


--
-- Name: produtos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.produtos ALTER COLUMN id SET DEFAULT nextval('public.produtos_id_seq'::regclass);


--
-- Data for Name: avaliacoes; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.avaliacoes (id, produto_id, cliente_id, nota, comentario, criado_em) FROM stdin;
1	1	1	5	Excelente notebook, muito rápido!	2026-10-27 16:41:48.517653
2	2	2	5	MacBook perfeito, bateria incrível.	2026-10-27 16:41:48.517653
3	4	10	4	Teclado bom, mas switches poderiam ser melhores.	2026-10-27 16:41:48.517653
4	6	5	5	iPhone excepcional, câmera fantástica.	2026-10-27 16:41:48.517653
5	7	9	4	Ótimo Samsung, recomendo.	2026-10-27 16:41:48.517653
6	11	7	5	PS5 é incrível, vale cada centavo!	2026-10-27 16:41:48.517653
7	9	8	4	Headset com cancelamento de ruído excelente.	2026-10-27 16:41:48.517653
8	10	4	3	Cadeira confortável mas entrega demorou.	2026-10-27 16:41:48.517653
9	8	13	5	SSD ultrarrápido, boot em 5 segundos!	2026-10-27 16:41:48.517653
10	18	15	4	iPad muito bom para trabalho e entretenimento.	2026-10-27 16:41:48.517653
11	14	16	5	Clean Code mudou minha forma de programar!	2026-10-27 16:41:48.517653
12	1	29	4	Bom custo-benefício.	2026-10-27 16:41:48.517653
13	3	33	5	Monitor 4K incrível, cores vivas.	2026-10-27 16:41:48.517653
14	5	11	4	Mouse preciso, ergonômico.	2026-10-27 16:41:48.517653
\.


--
-- Data for Name: categorias; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.categorias (id, nome, descricao, pai_id) FROM stdin;
1	Eletrônicos	Produtos eletrônicos em geral	\N
2	Computadores	Computadores e acessórios	1
3	Smartphones	Celulares e acessórios	1
4	Periféricos	Teclados, mouses, monitores	1
5	Casa e Jardim	Itens para casa e jardim	\N
6	Esporte	Artigos esportivos	\N
7	Livros	Livros físicos e digitais	\N
8	Games	Jogos e consoles	1
\.


--
-- Data for Name: clientes; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.clientes (id, nome, email, cpf, telefone, cidade, estado, criado_em, ativo) FROM stdin;
1	Ana Souza	ana.souza@email.com	123.456.789-00	(48) 99901-0001	Florianópolis	SC	2026-10-27 16:41:48.517653	t
2	Bruno Lima	bruno.lima@email.com	234.567.890-11	(11) 99902-0002	São Paulo	SP	2026-10-27 16:41:48.517653	t
3	Carla Mendes	carla.mendes@email.com	345.678.901-22	(21) 99903-0003	Rio de Janeiro	RJ	2026-10-27 16:41:48.517653	t
4	Diego Castro	diego.castro@email.com	456.789.012-33	(31) 99904-0004	Belo Horizonte	MG	2026-10-27 16:41:48.517653	t
5	Eva Rodrigues	eva.rodrigues@email.com	567.890.123-44	(41) 99905-0005	Curitiba	PR	2026-10-27 16:41:48.517653	t
6	Fábio Almeida	fabio.almeida@email.com	678.901.234-55	(51) 99906-0006	Porto Alegre	RS	2026-10-27 16:41:48.517653	t
7	Gabi Ferreira	gabi.ferreira@email.com	789.012.345-66	(61) 99907-0007	Brasília	DF	2026-10-27 16:41:48.517653	t
8	Henrique Nunes	henrique.nunes@email.com	890.123.456-77	(71) 99908-0008	Salvador	BA	2026-10-27 16:41:48.517653	t
9	Isabela Costa	isabela.costa@email.com	901.234.567-88	(81) 99909-0009	Recife	PE	2026-10-27 16:41:48.517653	t
10	João Oliveira	joao.oliveira@email.com	012.345.678-99	(85) 99910-0010	Fortaleza	CE	2026-10-27 16:41:48.517653	t
11	Karen Pinto	karen.pinto@email.com	111.222.333-44	(92) 99911-0011	Manaus	AM	2026-10-27 16:41:48.517653	t
12	Lucas Barbosa	lucas.barbosa@email.com	222.333.444-55	(62) 99912-0012	Goiânia	GO	2026-10-27 16:41:48.517653	t
13	Marina Gomes	marina.gomes@email.com	333.444.555-66	(27) 99913-0013	Vitória	ES	2026-10-27 16:41:48.517653	t
14	Nathan Carvalho	nathan.carvalho@email.com	444.555.666-77	(98) 99914-0014	São Luís	MA	2026-10-27 16:41:48.517653	t
15	Olivia Araújo	olivia.araujo@email.com	555.666.777-88	(83) 99915-0015	João Pessoa	PB	2026-10-27 16:41:48.517653	t
16	Paulo Melo	paulo.melo@email.com	666.777.888-99	(68) 99916-0016	Rio Branco	AC	2026-10-27 16:41:48.517653	t
17	Quésia Teixeira	quesia.teixeira@email.com	777.888.999-00	(65) 99917-0017	Cuiabá	MT	2026-10-27 16:41:48.517653	t
18	Rafael Monteiro	rafael.monteiro@email.com	888.999.000-11	(67) 99918-0018	Campo Grande	MS	2026-10-27 16:41:48.517653	t
19	Sandra Vieira	sandra.vieira@email.com	999.000.111-22	(95) 99919-0019	Boa Vista	RR	2026-10-27 16:41:48.517653	t
20	Tiago Pereira	tiago.pereira@email.com	010.111.222-33	(96) 99920-0020	Macapá	AP	2026-10-27 16:41:48.517653	t
21	Ursula Santos	ursula.santos@email.com	020.222.333-44	(79) 99921-0021	Aracaju	SE	2026-10-27 16:41:48.517653	t
22	Victor Ramos	victor.ramos@email.com	030.333.444-55	(82) 99922-0022	Maceió	AL	2026-10-27 16:41:48.517653	t
23	Wanessa Cruz	wanessa.cruz@email.com	040.444.555-66	(86) 99923-0023	Teresina	PI	2026-10-27 16:41:48.517653	t
24	Xavier Leal	xavier.leal@email.com	050.555.666-77	(84) 99924-0024	Natal	RN	2026-10-27 16:41:48.517653	t
25	Yasmin Fonseca	yasmin.fonseca@email.com	060.666.777-88	(88) 99925-0025	Caucaia	CE	2026-10-27 16:41:48.517653	t
26	Zeca Moraes	zeca.moraes@email.com	070.777.888-99	(47) 99926-0026	Joinville	SC	2026-10-27 16:41:48.517653	t
27	Aline Ribeiro	aline.ribeiro@email.com	080.888.999-00	(44) 99927-0027	Maringá	PR	2026-10-27 16:41:48.517653	t
28	Bernardo Dias	bernardo.dias@email.com	090.999.000-11	(43) 99928-0028	Londrina	PR	2026-10-27 16:41:48.517653	t
29	Cecília Andrade	cecilia.andrade@email.com	001.010.111-22	(19) 99929-0029	Campinas	SP	2026-10-27 16:41:48.517653	t
30	Danilo Cardoso	danilo.cardoso@email.com	002.020.222-33	(16) 99930-0030	Ribeirão Preto	SP	2026-10-27 16:41:48.517653	t
31	Eliane Freitas	eliane.freitas@email.com	003.030.333-44	(17) 99931-0031	São José Rio Preto	SP	2026-10-27 16:41:48.517653	t
32	Flavio Guimarães	flavio.guimaraes@email.com	004.040.444-55	(14) 99932-0032	Bauru	SP	2026-10-27 16:41:48.517653	t
33	Glória Correia	gloria.correia@email.com	005.050.555-66	(12) 99933-0033	São José dos Campos	SP	2026-10-27 16:41:48.517653	t
34	Hélio Martins	helio.martins@email.com	006.060.666-77	(13) 99934-0034	Santos	SP	2026-10-27 16:41:48.517653	t
35	Ingrid Nogueira	ingrid.nogueira@email.com	007.070.777-88	(15) 99935-0035	Sorocaba	SP	2026-10-27 16:41:48.517653	t
36	Júlio Borges	julio.borges@email.com	008.080.888-99	(18) 99936-0036	Presidente Prudente	SP	2026-10-27 16:41:48.517653	t
37	Kelly Azevedo	kelly.azevedo@email.com	009.090.999-00	(32) 99937-0037	Juiz de Fora	MG	2026-10-27 16:41:48.517653	t
38	Leandro Cunha	leandro.cunha@email.com	011.121.131-41	(33) 99938-0038	Uberlândia	MG	2026-10-27 16:41:48.517653	t
39	Marcela Torres	marcela.torres@email.com	022.232.242-52	(34) 99939-0039	Uberaba	MG	2026-10-27 16:41:48.517653	t
40	Nicolas Vasconcelos	nicolas.vasc@email.com	033.343.353-63	(35) 99940-0040	Poços de Caldas	MG	2026-10-27 16:41:48.517653	t
41	Odete Pacheco	odete.pacheco@email.com	044.454.464-74	(38) 99941-0041	Montes Claros	MG	2026-10-27 16:41:48.517653	t
42	Pedro Cavalcanti	pedro.cavalcanti@email.com	055.565.575-85	(37) 99942-0042	Divinópolis	MG	2026-10-27 16:41:48.517653	t
43	Quitéria Macedo	quiteria.macedo@email.com	066.676.686-96	(75) 99943-0043	Feira de Santana	BA	2026-10-27 16:41:48.517653	t
44	Roberta Lopes	roberta.lopes@email.com	077.787.797-07	(73) 99944-0044	Ilhéus	BA	2026-10-27 16:41:48.517653	t
45	Sérgio Campos	sergio.campos@email.com	088.898.808-18	(74) 99945-0045	Vitória da Conquista	BA	2026-10-27 16:41:48.517653	t
46	Tatiana Rocha	tatiana.rocha@email.com	099.909.919-29	(77) 99946-0046	Barreiras	BA	2026-10-27 16:41:48.517653	t
47	Ulisses Moreira	ulisses.moreira@email.com	100.101.102-03	(91) 99947-0047	Belém	PA	2026-10-27 16:41:48.517653	t
48	Vera Nascimento	vera.nascimento@email.com	200.201.202-03	(93) 99948-0048	Santarém	PA	2026-10-27 16:41:48.517653	t
49	Wagner Pires	wagner.pires@email.com	300.301.302-03	(94) 99949-0049	Marabá	PA	2026-10-27 16:41:48.517653	t
50	Ximena Duarte	ximena.duarte@email.com	400.401.402-03	(89) 99950-0050	Parnaíba	PI	2026-10-27 16:41:48.517653	t
\.


--
-- Data for Name: itens_pedido; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.itens_pedido (id, pedido_id, produto_id, quantidade, preco_unit) FROM stdin;
1	1	1	1	3299.90
2	1	4	1	349.90
3	2	2	1	9499.00
4	3	3	1	2199.00
5	3	16	1	479.00
6	4	10	1	1899.00
7	5	6	1	8999.00
8	6	5	1	599.00
9	7	11	1	4499.00
10	8	9	1	2299.00
11	9	7	1	5499.00
12	10	4	1	349.90
13	11	5	1	599.00
14	12	12	1	4299.00
15	13	8	1	499.90
16	14	13	1	2799.00
17	15	18	1	4999.00
18	16	14	1	89.90
19	17	17	1	1399.00
20	18	19	1	4299.00
21	19	3	1	2199.00
22	20	4	1	349.90
23	21	5	1	599.00
24	22	7	1	5499.00
25	23	3	1	2199.00
26	24	6	1	8999.00
27	25	4	1	349.90
28	26	5	1	599.00
29	27	7	1	5499.00
30	29	9	1	2299.00
\.


--
-- Data for Name: movimentacoes_estoque; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.movimentacoes_estoque (id, produto_id, tipo, quantidade, motivo, criado_em) FROM stdin;
1	1	entrada	50	Compra inicial de estoque	2026-10-27 16:41:48.517653
2	1	saida	5	Vendas processadas	2026-10-27 16:41:48.517653
3	2	entrada	20	Compra inicial de estoque	2026-10-27 16:41:48.517653
4	2	saida	2	Vendas processadas	2026-10-27 16:41:48.517653
5	6	entrada	30	Reposição de estoque	2026-10-27 16:41:48.517653
6	6	saida	8	Vendas processadas	2026-10-27 16:41:48.517653
7	11	entrada	15	Compra inicial de estoque	2026-10-27 16:41:48.517653
8	11	saida	7	Vendas processadas	2026-10-27 16:41:48.517653
9	4	devolucao	1	Cliente devolveu produto com defeito	2026-10-27 16:41:48.517653
10	8	entrada	150	Grande reposição NVMe	2026-10-27 16:41:48.517653
11	8	saida	30	Vendas alto volume	2026-10-27 16:41:48.517653
12	10	ajuste	-2	Inventário físico - divergência encontrada	2026-10-27 16:41:48.517653
\.


--
-- Data for Name: pagamentos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.pagamentos (id, pedido_id, metodo, valor, status, transacao_id, pago_em) FROM stdin;
1	1	cartao	3649.80	aprovado	TXN-0001	2026-09-27 16:41:48.517653
2	2	pix	9499.00	aprovado	TXN-0002	2026-09-29 16:41:48.517653
3	3	cartao	2198.90	aprovado	TXN-0003	2026-10-22 16:41:48.517653
4	5	pix	8999.00	aprovado	TXN-0005	2026-10-07 16:41:48.517653
5	6	boleto	599.00	recusado	TXN-0006	\N
6	7	cartao	4499.00	aprovado	TXN-0007	2026-10-17 16:41:48.517653
7	9	transferencia	5499.00	aprovado	TXN-0009	2026-10-24 16:41:48.517653
8	10	pix	349.90	aprovado	TXN-0010	2026-10-15 16:41:48.517653
9	11	cartao	599.00	aprovado	TXN-0011	2026-10-02 16:41:48.517653
10	13	pix	499.90	aprovado	TXN-0013	2026-10-09 16:41:48.517653
11	15	cartao	4999.00	aprovado	TXN-0015	2026-10-05 16:41:48.517653
12	16	pix	89.90	aprovado	TXN-0016	2026-10-19 16:41:48.517653
13	20	pix	349.90	aprovado	TXN-0020	2026-10-16 16:41:48.517653
14	21	boleto	599.00	recusado	TXN-0021-R	\N
15	21	pix	599.00	aprovado	TXN-0021-OK	2026-10-24 16:41:48.517653
16	22	cartao	479.00	aprovado	TXN-0022	2026-10-20 16:41:48.517653
17	27	transferencia	5499.00	aprovado	TXN-0027	2026-10-11 16:41:48.517653
\.


--
-- Data for Name: pedidos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.pedidos (id, cliente_id, status, total, endereco_entrega, criado_em, atualizado_em) FROM stdin;
1	1	entregue	3649.80	Rua das Flores, 123 - Florianópolis/SC	2026-09-27 16:41:48.517653	2026-09-29 16:41:48.517653
2	2	entregue	9499.00	Av. Paulista, 1000 - São Paulo/SP	2026-09-29 16:41:48.517653	2026-10-01 16:41:48.517653
3	3	enviado	2198.90	Rua Copacabana, 55 - Rio de Janeiro/RJ	2026-10-22 16:41:48.517653	2026-10-23 16:41:48.517653
4	4	confirmado	1899.00	Av. Afonso Pena, 200 - BH/MG	2026-10-25 16:41:48.517653	2026-10-25 16:41:48.517653
5	5	entregue	8999.00	Rua XV de Novembro, 10 - Curitiba/PR	2026-10-07 16:41:48.517653	2026-10-09 16:41:48.517653
6	6	cancelado	599.00	Av. Ipiranga, 1 - Porto Alegre/RS	2026-10-12 16:41:48.517653	2026-10-13 16:41:48.517653
7	7	entregue	4499.00	SQS 308, Bloco A - Brasília/DF	2026-10-17 16:41:48.517653	2026-10-19 16:41:48.517653
8	8	pendente	2299.00	Av. ACM, 3500 - Salvador/BA	2026-10-26 16:41:48.517653	2026-10-26 16:41:48.517653
9	9	enviado	5499.00	Rua da Aurora, 444 - Recife/PE	2026-10-24 16:41:48.517653	2026-10-25 16:41:48.517653
10	10	entregue	349.90	Av. Washington Soares, 900 - Fortaleza/CE	2026-10-15 16:41:48.517653	2026-10-17 16:41:48.517653
11	1	entregue	599.00	Rua das Flores, 123 - Florianópolis/SC	2026-10-02 16:41:48.517653	2026-10-04 16:41:48.517653
12	2	confirmado	4299.00	Av. Paulista, 1000 - São Paulo/SP	2026-10-26 16:41:48.517653	2026-10-26 16:41:48.517653
13	3	entregue	499.90	Rua Copacabana, 55 - Rio de Janeiro/RJ	2026-10-09 16:41:48.517653	2026-10-11 16:41:48.517653
14	4	pendente	2799.00	Av. Afonso Pena, 200 - BH/MG	2026-10-27 16:41:48.517653	2026-10-27 16:41:48.517653
15	5	entregue	4999.00	Rua XV de Novembro, 10 - Curitiba/PR	2026-10-05 16:41:48.517653	2026-10-07 16:41:48.517653
16	11	entregue	89.90	Av. Eduardo Ribeiro, 500 - Manaus/AM	2026-10-19 16:41:48.517653	2026-10-21 16:41:48.517653
17	12	enviado	1399.00	Av. Anhanguera, 100 - Goiânia/GO	2026-10-23 16:41:48.517653	2026-10-24 16:41:48.517653
18	13	confirmado	4299.00	Av. Jerônimo Monteiro, 1 - Vitória/ES	2026-10-25 16:41:48.517653	2026-10-25 16:41:48.517653
19	14	pendente	599.00	Rua Grande, 10 - São Luís/MA	2026-10-27 16:41:48.517653	2026-10-27 16:41:48.517653
20	15	entregue	79.90	Av. Epitácio Pessoa, 200 - João Pessoa/PB	2026-10-18 16:41:48.517653	2026-10-20 16:41:48.517653
21	16	cancelado	3299.90	Av. Brasil, 50 - Rio Branco/AC	2026-10-13 16:41:48.517653	2026-10-14 16:41:48.517653
22	17	entregue	479.00	Av. Historiador Rubens Mendonça - Cuiabá/MT	2026-10-20 16:41:48.517653	2026-10-22 16:41:48.517653
23	18	enviado	2199.00	Av. Afonso Pena, 1 - Campo Grande/MS	2026-10-21 16:41:48.517653	2026-10-22 16:41:48.517653
24	19	pendente	8999.00	Av. Ville Roy, 1 - Boa Vista/RR	2026-10-27 16:41:48.517653	2026-10-27 16:41:48.517653
25	20	entregue	349.90	Rua São José, 1 - Macapá/AP	2026-10-16 16:41:48.517653	2026-10-18 16:41:48.517653
26	21	confirmado	599.00	Av. Barão de Maruim, 1 - Aracaju/SE	2026-10-24 16:41:48.517653	2026-10-24 16:41:48.517653
27	22	entregue	5499.00	Av. Fernandes Lima, 1 - Maceió/AL	2026-10-11 16:41:48.517653	2026-10-13 16:41:48.517653
28	23	pendente	9499.00	Av. Frei Serafim, 1 - Teresina/PI	2026-10-27 16:41:48.517653	2026-10-27 16:41:48.517653
29	24	enviado	2299.00	Av. Deodoro, 1 - Natal/RN	2026-10-22 16:41:48.517653	2026-10-23 16:41:48.517653
30	25	entregue	4499.00	Rua 24 de Maio, 1 - Caucaia/CE	2026-10-08 16:41:48.517653	2026-10-10 16:41:48.517653
\.


--
-- Data for Name: produtos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.produtos (id, nome, descricao, preco, estoque, categoria_id, sku, peso_kg, criado_em, ativo) FROM stdin;
1	Notebook Dell Inspiron 15	\N	3299.90	45	2	DELL-INS-001	1.850	2026-10-27 16:41:48.517653	t
2	MacBook Air M2	\N	9499.00	18	2	APPL-MBA-002	1.240	2026-10-27 16:41:48.517653	t
3	Monitor LG 27" 4K	\N	2199.00	30	4	LG-MON-003	5.200	2026-10-27 16:41:48.517653	t
4	Teclado Mecânico Redragon	\N	349.90	80	4	RED-TEC-004	0.950	2026-10-27 16:41:48.517653	t
5	Mouse Logitech MX Master 3	\N	599.00	65	4	LOG-MOU-005	0.140	2026-10-27 16:41:48.517653	t
6	iPhone 15 Pro	\N	8999.00	22	3	APPL-IPH-006	0.190	2026-10-27 16:41:48.517653	t
7	Samsung Galaxy S24	\N	5499.00	35	3	SAM-GAL-007	0.170	2026-10-27 16:41:48.517653	t
8	SSD Kingston 1TB NVMe	\N	499.90	120	2	KIN-SSD-008	0.050	2026-10-27 16:41:48.517653	t
9	Headset Sony WH-1000XM5	\N	2299.00	40	1	SON-HED-009	0.250	2026-10-27 16:41:48.517653	t
10	Cadeira Gamer ThunderX3	\N	1899.00	15	6	THU-CAD-010	18.000	2026-10-27 16:41:48.517653	t
11	PS5 Standard Edition	\N	4499.00	8	8	SON-PS5-011	3.900	2026-10-27 16:41:48.517653	t
12	Xbox Series X	\N	4299.00	10	8	MSF-XBX-012	4.450	2026-10-27 16:41:48.517653	t
13	Nintendo Switch OLED	\N	2799.00	25	8	NIN-NSW-013	0.420	2026-10-27 16:41:48.517653	t
14	Livro Clean Code	\N	89.90	200	7	LIV-CLN-014	0.520	2026-10-27 16:41:48.517653	t
15	Livro Design Patterns	\N	79.90	150	7	LIV-DES-015	0.480	2026-10-27 16:41:48.517653	t
16	Webcam Logitech C920	\N	479.00	55	4	LOG-WEB-016	0.160	2026-10-27 16:41:48.517653	t
17	Impressora HP LaserJet	\N	1399.00	20	1	HP-IMP-017	8.500	2026-10-27 16:41:48.517653	t
18	Tablet iPad Air	\N	4999.00	28	3	APPL-IPD-018	0.460	2026-10-27 16:41:48.517653	t
19	Smartwatch Apple Watch S9	\N	4299.00	32	1	APPL-AWT-019	0.040	2026-10-27 16:41:48.517653	t
20	Roteador TP-Link AX3000	\N	599.00	75	1	TPL-ROT-020	0.460	2026-10-27 16:41:48.517653	t
\.


--
-- Name: avaliacoes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.avaliacoes_id_seq', 14, true);


--
-- Name: categorias_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.categorias_id_seq', 8, true);


--
-- Name: clientes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.clientes_id_seq', 50, true);


--
-- Name: itens_pedido_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.itens_pedido_id_seq', 30, true);


--
-- Name: movimentacoes_estoque_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.movimentacoes_estoque_id_seq', 12, true);


--
-- Name: pagamentos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.pagamentos_id_seq', 17, true);


--
-- Name: pedidos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.pedidos_id_seq', 30, true);


--
-- Name: produtos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.produtos_id_seq', 20, true);


--
-- Name: avaliacoes avaliacoes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.avaliacoes
    ADD CONSTRAINT avaliacoes_pkey PRIMARY KEY (id);


--
-- Name: categorias categorias_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categorias
    ADD CONSTRAINT categorias_pkey PRIMARY KEY (id);


--
-- Name: clientes clientes_cpf_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clientes
    ADD CONSTRAINT clientes_cpf_key UNIQUE (cpf);


--
-- Name: clientes clientes_email_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clientes
    ADD CONSTRAINT clientes_email_key UNIQUE (email);


--
-- Name: clientes clientes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clientes
    ADD CONSTRAINT clientes_pkey PRIMARY KEY (id);


--
-- Name: itens_pedido itens_pedido_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.itens_pedido
    ADD CONSTRAINT itens_pedido_pkey PRIMARY KEY (id);


--
-- Name: movimentacoes_estoque movimentacoes_estoque_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.movimentacoes_estoque
    ADD CONSTRAINT movimentacoes_estoque_pkey PRIMARY KEY (id);


--
-- Name: pagamentos pagamentos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pagamentos
    ADD CONSTRAINT pagamentos_pkey PRIMARY KEY (id);


--
-- Name: pedidos pedidos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pedidos
    ADD CONSTRAINT pedidos_pkey PRIMARY KEY (id);


--
-- Name: produtos produtos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.produtos
    ADD CONSTRAINT produtos_pkey PRIMARY KEY (id);


--
-- Name: produtos produtos_sku_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.produtos
    ADD CONSTRAINT produtos_sku_key UNIQUE (sku);


--
-- Name: idx_avaliacoes_cliente_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_avaliacoes_cliente_id ON public.avaliacoes USING btree (cliente_id);


--
-- Name: idx_avaliacoes_produto_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_avaliacoes_produto_id ON public.avaliacoes USING btree (produto_id);


--
-- Name: idx_categorias_pai_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_categorias_pai_id ON public.categorias USING btree (pai_id);


--
-- Name: idx_itens_pedido_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_itens_pedido_id ON public.itens_pedido USING btree (pedido_id);


--
-- Name: idx_itens_pedido_produto_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_itens_pedido_produto_id ON public.itens_pedido USING btree (produto_id);


--
-- Name: idx_movimentacoes_estoque_produto_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_movimentacoes_estoque_produto_id ON public.movimentacoes_estoque USING btree (produto_id);


--
-- Name: idx_pagamentos_pedido_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_pagamentos_pedido_id ON public.pagamentos USING btree (pedido_id);


--
-- Name: idx_pedidos_cliente_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_pedidos_cliente_id ON public.pedidos USING btree (cliente_id);


--
-- Name: idx_produtos_categoria_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_produtos_categoria_id ON public.produtos USING btree (categoria_id);


--
-- Name: avaliacoes avaliacoes_cliente_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.avaliacoes
    ADD CONSTRAINT avaliacoes_cliente_id_fkey FOREIGN KEY (cliente_id) REFERENCES public.clientes(id);


--
-- Name: avaliacoes avaliacoes_produto_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.avaliacoes
    ADD CONSTRAINT avaliacoes_produto_id_fkey FOREIGN KEY (produto_id) REFERENCES public.produtos(id);


--
-- Name: categorias categorias_pai_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categorias
    ADD CONSTRAINT categorias_pai_id_fkey FOREIGN KEY (pai_id) REFERENCES public.categorias(id);


--
-- Name: itens_pedido itens_pedido_pedido_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.itens_pedido
    ADD CONSTRAINT itens_pedido_pedido_id_fkey FOREIGN KEY (pedido_id) REFERENCES public.pedidos(id) ON DELETE CASCADE;


--
-- Name: itens_pedido itens_pedido_produto_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.itens_pedido
    ADD CONSTRAINT itens_pedido_produto_id_fkey FOREIGN KEY (produto_id) REFERENCES public.produtos(id);


--
-- Name: movimentacoes_estoque movimentacoes_estoque_produto_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.movimentacoes_estoque
    ADD CONSTRAINT movimentacoes_estoque_produto_id_fkey FOREIGN KEY (produto_id) REFERENCES public.produtos(id);


--
-- Name: pagamentos pagamentos_pedido_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pagamentos
    ADD CONSTRAINT pagamentos_pedido_id_fkey FOREIGN KEY (pedido_id) REFERENCES public.pedidos(id);


--
-- Name: pedidos pedidos_cliente_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pedidos
    ADD CONSTRAINT pedidos_cliente_id_fkey FOREIGN KEY (cliente_id) REFERENCES public.clientes(id);


--
-- Name: produtos produtos_categoria_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.produtos
    ADD CONSTRAINT produtos_categoria_id_fkey FOREIGN KEY (categoria_id) REFERENCES public.categorias(id);


--
-- PostgreSQL database dump complete
--

\unrestrict jebnh59W5Ee9FZ5YH9r68m5JhhxEyC9LCyRfMerSynkE721va8UMn4UwIkJr3HM

