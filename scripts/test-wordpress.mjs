import fs from 'node:fs';
import path from 'node:path';
import { runCLI } from '@wp-playground/cli';

const argument = (name, fallback) => process.argv.find(value => value.startsWith('--' + name + '='))?.split('=')[1] || fallback;
const server = await runCLI({
  command: 'server', port: Number(argument('port', '9400')), workers: 1, php: argument('php', '8.3'), wp: argument('wp', 'latest'),
  mount: [
    { hostPath: path.resolve('astrid'), vfsPath: '/wordpress/wp-content/plugins/astrid' },
    { hostPath: path.resolve('tests'), vfsPath: '/astrid-tests' }
  ],
  blueprint: { steps: [{ step: 'activatePlugin', pluginPath: 'astrid/astrid.php' }] }
});

try {
  const response = await server.playground.run({ scriptPath: '/astrid-tests/wordpress.php' });
  fs.mkdirSync('artifacts', { recursive: true });
  fs.writeFileSync('artifacts/php-output.txt', response.text);
  if (response.exitCode !== 0 || response.errors) throw new Error(response.errors || response.text);
  const result = JSON.parse(response.text);
  console.log(`WordPress/PHP integration: ${result.checks.length} checks passed.`);
  fs.writeFileSync('artifacts/wordpress-checks.json', JSON.stringify(result, null, 2));
  fs.writeFileSync(`artifacts/wordpress-${result.wordpress}-php-${result.php}.json`, JSON.stringify(result, null, 2));
  console.log(`Local preview: ${server.serverUrl}/astrid-preview.html`);
  if (process.argv.includes('--serve')) {
    fs.writeFileSync('artifacts/runtime.pid', String(process.pid));
    console.log('Test site remains running for browser checks.');
    process.on('SIGINT', async () => { await server[Symbol.asyncDispose](); process.exit(0); });
    await new Promise(() => {});
  }
} finally {
  if (!process.argv.includes('--serve')) await server[Symbol.asyncDispose]();
}
