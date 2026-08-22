import { getPhp } from './php_engine.js';
getPhp().then(async (php) => {
  const code = `<?php
    echo "Running PHP...\\n";
    try {
        $res = Fortress\\IRC\\IrcServices::processCommand('JakeDot', 'chanserv', '/msg chanserv help');
        var_dump($res);
    } catch (\\Throwable $e) {
        echo "Exception: " . $e->getMessage() . "\\n";
        echo $e->getTraceAsString();
    }
  `;
  await php.run(code);
  process.exit(0);
}).catch(console.error);
