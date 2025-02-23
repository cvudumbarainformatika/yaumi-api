import chokidar from 'chokidar';
import { exec } from 'child_process';

const watcher = chokidar.watch('.', {
    ignored: /(^|[\/\\])\..|(^|[\/\\])vendor/,
    persistent: true
});

console.log('Starting file watcher...');

watcher.on('change', (path) => {
    if (path.endsWith('.php')) {
        console.log(`File ${path} has been changed`);
        exec('curl -s -X POST http://app:8000/octane/reload?workers=true', (error, stdout, stderr) => {
            if (error) {
                console.error(`Error: ${error}`);
                return;
            }
            console.log(`Octane workers reloaded at ${new Date().toISOString()}`);
        });
    }
});
