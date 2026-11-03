<?php
if ( ! function_exists('dialoga_env'))
{
    function dialoga_env($chave, $padrao = '')
    {
        static $valores = NULL;

        if ($valores === NULL)
        {
            $valores = array();
            $arquivo = dirname(dirname(dirname(dirname(__FILE__)))).'/.env';
            if (is_file($arquivo))
            {
                $linhas = file($arquivo, FILE_IGNORE_NEW_LINES);
                foreach ((array) $linhas as $linha)
                {
                    $linha = trim($linha);
                    if ($linha === '' OR $linha[0] === '#')
                    {
                        continue;
                    }
                    if ( ! preg_match('/^([A-Z_][A-Z0-9_]*)\s*=\s*(.*)$/', $linha, $m))
                    {
                        continue;
                    }
                    $valor = trim($m[2]);
                    $tam = strlen($valor);
                    if ($tam >= 2 && ($valor[0] === '"' OR $valor[0] === "'") && $valor[$tam - 1] === $valor[0])
                    {
                        $valor = substr($valor, 1, -1);
                    }
                    $valores[$m[1]] = $valor;
                }
            }
        }

        $ambiente = getenv($chave);
        if ($ambiente !== FALSE && $ambiente !== '')
        {
            return $ambiente;
        }
        return isset($valores[$chave]) ? $valores[$chave] : $padrao;
    }
}
