<?php

use Silex\Application;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Process\Process;
use Symfony\Component\Validator\Constraints as Assert;

require_once(__DIR__ . '/../vendor/autoload.php');

const PROCESS_COMMAND_DOCKER_WPSCAN = 'docker run --rm wpscanteam/wpscan -u %s --update';
const PROCESS_OUTPUT_COLOR_REPLACEMENTS = [
    '[31m' => '<span class="text-danger">',
    '[32m' => '<span class="text-success">',
    '[33m' => '<span class="text-warning">',
    '[34m' => '<span class="text-info">',
    '[0m' => '</span>',
];

$app = new Application();
$app['debug'] = true;

$app->register(new Silex\Provider\TwigServiceProvider(), [
    'twig.path' => __DIR__ . '/views',
]);
$app->register(new Silex\Provider\FormServiceProvider());
$app->register(new Silex\Provider\LocaleServiceProvider());
$app->register(new Silex\Provider\ValidatorServiceProvider());
$app->register(new Silex\Provider\TranslationServiceProvider(), [
    'translator.domains' => [],
    'locale_fallbacks' => ['en'],
]);

function getUrlForm(Application $app): Form
{
    return $app['form.factory']->createNamedBuilder(null, FormType::class)
        ->add('url', UrlType::class, [
            'attr' => ['placeholder' => 'URL starting with http:// or https://'],
            'constraints' => [new Assert\Url()],
            'required' => true,
        ])
        ->add('submit', SubmitType::class, [
            'label' => 'Scan',
            'attr' => ['class' => 'btn-primary'],
        ])
        ->setMethod('GET')
        ->getForm();
}

$app->get('/', function (Request $request) use ($app) {
    $form = getUrlForm($app);
    $form->handleRequest($request);

    if ($form->isValid()) {
        $data = $form->getData();
    }

    return $app['twig']->render('index.twig', [
        'form' => $form->createView(),
        'url' => $data['url'] ?? null,
    ]);
});

$app->get('/scan', function (Request $request) use ($app) {
    $form = getUrlForm($app);
    $form->handleRequest($request);

    if ($form->isValid()) {
        $data = $form->getData();
        $url = $data['url'];

        $process = new Process(vsprintf(PROCESS_COMMAND_DOCKER_WPSCAN, [$url]), null, null, null, 300);
        $process->run();

        $wpscanOutput = $process->getOutput();
        $wpscanOutputHtmlColors = str_replace(
            array_keys(PROCESS_OUTPUT_COLOR_REPLACEMENTS),
            PROCESS_OUTPUT_COLOR_REPLACEMENTS,
            $wpscanOutput
        );

        return new JsonResponse([
            'url' => $url,
            'errors' => substr_count($wpscanOutput, '[31m'),
            'warnings' => substr_count($wpscanOutput, '[33m'),
            'output' => $wpscanOutputHtmlColors,
        ]);
    } else {
        $app->abort(500, 'Given URL is not valid');
    }
})->bind('scan');

$app->run();
