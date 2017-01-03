<?php
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
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

        $process = new Process('docker run --rm wpscanteam/wpscan -u ' . $data['url']);
        $process->start();
        $pid = $process->getPid();
        $process->wait();
        
        $errors = $process->getExitCode();
        $output = $process->getOutput();
        $dictionary = array(
            '[31m' => '<span style="color:red">',
            '[32m' => '<span style="color:limegreen">',
            '[33m' => '<span style="color:orange">',
            '[34m' => '<span style="color:blue">',
            '[0m'   => '</span>' ,
        );
        $output = str_replace(array_keys($dictionary), $dictionary, $output);

        return new Response($output);
    } else {
        $app->abort(500, 'Given URL is not valid');
    }
})
->bind('scan');

$app->run();
