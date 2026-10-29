// Localiza o Python no Windows e no Linux, mantendo npm start como entrada única.
const { spawn, spawnSync } = require('node:child_process');
const path = require('node:path');
const candidates = process.platform === 'win32'
  ? [['py', ['-3']], ['python', []], ['python3', []]]
  : [['python3', []], ['python', []]];

let selected;
for (const [command, args] of candidates) {
  const check = spawnSync(command, [...args, '-c', 'import sys; sys.exit(0 if sys.version_info >= (3, 7) else 1)'], { timeout: 5000, windowsHide: true });
  if (!check.error && check.status === 0) {
    selected = [command, args];
    break;
  }
}
if (!selected) {
  console.error('Python 3.7 ou superior não encontrado. Instale o Python e disponibilize-o no PATH (ou pelo launcher py no Windows).');
  process.exit(1);
}
const root = path.resolve(__dirname, '..');
const child = spawn(selected[0], [...selected[1], path.join(__dirname, 'serve.py'), ...process.argv.slice(2)], { cwd: root, stdio: 'inherit' });
child.on('error', () => { console.error('Não foi possível iniciar o servidor local.'); process.exitCode = 1; });
child.on('exit', (code, signal) => { process.exitCode = signal === 'SIGINT' ? 0 : (code === null ? 1 : code); });
process.on('SIGINT', () => { if (child.exitCode === null) child.kill('SIGINT'); });
