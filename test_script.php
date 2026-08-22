<?php
namespace Fortress\IRC;

require_once __DIR__ . '/src/IRC/IrcServices.php';

// Check if reflection can extract processCommand safely without loading dependencies if they aren't instantiated
try {
    $ref = new \ReflectionMethod(IrcServices::class, 'processCommand');
    echo "Can reflect on processCommand.\n";
} catch (\Exception $e) {
    echo "Cannot reflect: " . $e->getMessage() . "\n";
}
