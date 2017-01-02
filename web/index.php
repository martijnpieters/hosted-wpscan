<?php
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\HttpFoundation\Request;
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

$app->get('/', function(Request $request) use ($app) {
    $form = $app['form.factory']->createBuilder(FormType::class)
        ->add('url', UrlType::class, array(
            'required' => true,
            'attr' => array('placeholder' => 'URL starting with https://'),
        ))
        ->add('save', SubmitType::class, array('label' => 'Scan'))
        ->getForm();
    
    $form->handleRequest($request);

    if ($form->isValid()) {
        $data = $form->getData();

        $process = new Process('docker run --rm wpscanteam/wpscan -u ' . $data['url']);
        $process->start();
        $pid = $process->getPid();
        $process->wait();
        
        $errors = $process->getExitCode();
        $output = $process->getOutput();
        $dictionary = array(
            '[31m' => '<span style="color:red">',
            '[32m' => '<span style="color:green">',
            '[33m' => '<span style="color:yellow">',
            '[34m' => '<span style="color:blue">',
            '[0m'   => '</span>' ,
        );
        $output = str_replace(array_keys($dictionary), $dictionary, $output);
    }
    
    return $app['twig']->render('index.twig', array(
        'form' => $form->createView(),
        'url' => $data['url'] ?? null,
        'pid' => $pid ?? null,
        'output' => $output ?? null,
    ));
})
->method('GET|POST');

$app->run();
