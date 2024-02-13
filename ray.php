<?php
return [
    'enable' => true,
	'host' => (str_ends_with(getcwd(), 'app') || str_ends_with(getcwd(), 'public')) ? 'host.docker.internal' : 'localhost',
    'port' => 23517,
    'remote_path' => '/app',
    'local_path' => $_ENV['RAY_LOCAL_DIR'],
];
