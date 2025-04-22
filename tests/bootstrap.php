<?php

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

$kernel = new \App\Kernel('test', true);
$application = new Application($kernel);
$application->setAutoExit(false);

$input = new ArrayInput([
    'command'        => 'doctrine:migrations:migrate',
    '--no-interaction'=> true,
    '--env'          => 'test',
]);
$application->run($input);

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}
