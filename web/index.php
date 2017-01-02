<?php
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

require_once(__DIR__.'/../vendor/autoload.php');

$app = new Silex\Application();
$app['debug'] = true;

$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/views',
));
$app->register(new Silex\Provider\FormServiceProvider());
$app->register(new Silex\Provider\LocaleServiceProvider());
$app->register(new Silex\Provider\ValidatorServiceProvider());
$app->register(new Silex\Provider\TranslationServiceProvider(), array(
    'translator.domains' => array(),
    'locale_fallbacks' => array('en'),
));

$app->get('/', function() use ($app) {
    $process = new Process('ls -lsa');
    $process->run();

    // executes after the command finishes
    if (!$process->isSuccessful()) {
        throw new ProcessFailedException($process);
    }

	return $app['twig']->render('index.twig', array(
        'output' => $process->getOutput()
    ));
});

$app->run();
