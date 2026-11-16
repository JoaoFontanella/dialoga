"""Inicia o frontend e o endpoint de conexão no servidor local PHP."""
from pathlib import Path
import argparse
import os
import shutil
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[1]

if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    # Pega a porta fornecida pelo Render (variável PORT) ou usa 8080 localmente por padrão
    default_port = int(os.environ.get('PORT', 8080))
    parser.add_argument('--port', type=int, default=default_port)
    args = parser.parse_args()
    
    php = shutil.which('php')
    if not php:
        sys.exit('PHP não encontrado. Instale o PHP e adicione a pasta do executável ao PATH do sistema.')
        
    check = subprocess.run([php, '-r', 'exit(extension_loaded("pdo_pgsql") ? 0 : 1);'], capture_output=True)
    if check.returncode:
        sys.exit('Driver PostgreSQL ausente. Instale ou habilite a extensão pdo_pgsql na configuração do PHP utilizado.')
        
    print(f'Dialoga: http://0.0.0.0:{args.port}/ (Ctrl+C para parar)', flush=True)
    
    # IMPORTANTE: Usamos '0.0.0.0' para o Render conseguir interceptar a porta de fora do container
    child = subprocess.Popen([php, '-d', 'display_errors=0', '-S', f'0.0.0.0:{args.port}', '-t', str(ROOT), str(ROOT / 'tools/router.php')], cwd=ROOT)
    
    try:
        sys.exit(child.wait())
    except KeyboardInterrupt:
        if child.poll() is None:
            child.terminate()
        child.wait()