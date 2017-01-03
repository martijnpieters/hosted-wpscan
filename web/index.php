<?php
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Validator\Constraints as Assert;

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

function getUrlForm() {
    global $app;
    return $app['form.factory']->createNamedBuilder(null, FormType::class)
        ->add('url', UrlType::class, array(
            'attr' => array('placeholder' => 'URL starting with http:// or https://'),
            'constraints' => array(new Assert\Url()),
            'required' => true,
        ))
        ->add('submit', SubmitType::class, array(
            'label' => 'Scan',
            'attr' => array('class' => 'btn-primary'),
        ))
        ->setMethod('GET')
        ->getForm();
}

$app->get('/', function(Request $request) use ($app) {
    $form = getUrlForm();
    $form->handleRequest($request);
    if ($form->isValid()) {
        $data = $form->getData();
    }
    
    return $app['twig']->render('index.twig', array(
        'form' => $form->createView(),
        'url' => $data['url'] ?? null,
    ));
});

$app->get('/scan', function(Request $request) use ($app) {
    $form = getUrlForm();
    $form->handleRequest($request);
    if ($form->isValid()) {
        $data = $form->getData();
        $url = $data['url'];

        $process = new Process('docker run --rm wpscanteam/wpscan -u ' . $url);
        $process->start();
        $pid = $process->getPid();
        $process->wait();
        
        $dictionary = array(
            '[31m' => '<span style="color:red">',
            '[32m' => '<span style="color:limegreen">',
            '[33m' => '<span style="color:orange">',
            '[34m' => '<span style="color:blue">',
            '[0m'   => '</span>' ,
        );
        $wpscan = $process->getOutput();
        $output = array(
            'url' => $url,
            'errors' => substr_count($wpscan, '[31m'),
            'warnings' => substr_count($wpscan, '[33m'),
            'output' => str_replace(array_keys($dictionary), $dictionary, $wpscan),
        );

        return new JsonResponse($output);
    } else {
        $app->abort(500, 'Given URL is not valid');
    }
})
->bind('scan');

$app->run();
