<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Ferramentas extends CI_Controller {

    public function __construct()
    {
        parent::__construct();
        if ( ! is_cli())
        {
            show_404();
        }
        $this->load->library('gemini');
    }

    public function modelos()
    {
        if ( ! $this->gemini->configurado())
        {
            echo "GEMINI_API_KEY não configurada.\n";
            return;
        }
        $disponiveis = $this->gemini->listar_modelos();
        echo "Cadeia configurada:\n";
        foreach ($this->gemini->modelos() as $m)
        {
            echo '  '.(in_array($m, $disponiveis, TRUE) ? '[ok]      ' : '[ausente] ').$m."\n";
        }
        echo "\nModelos com generateContent disponíveis para esta chave:\n  ".implode("\n  ", $disponiveis)."\n";
    }
}
