<?php
require_once 'src/Database/Database.php';
\Fortress\Database\Database::getConnection(); // implicitly initializes schema
\Fortress\Database\Database::registerDefaultForeignServices();
