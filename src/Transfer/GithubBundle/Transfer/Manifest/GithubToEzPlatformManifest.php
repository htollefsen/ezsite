<?php

namespace Transfer\GithubBundle\Transfer\Manifest;

use eZ\Publish\API\Repository\Repository;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Transfer\Adapter\LocalDirectoryAdapter;
use Transfer\Commons\Yaml\Worker\Transformer\YamlToArrayTransformer;
use Transfer\Data\ValueObject;
use Transfer\EzPlatform\Adapter\EzPlatformAdapter;
use Transfer\EzPlatform\Repository\Values\ContentTypeObject;
use Transfer\EzPlatform\Worker\Transformer\ArrayToEzPlatformContentTypeObjectTransformer;
use Transfer\GithubBundle\Transfer\Adapter\GithubAdapter;
use Transfer\GithubBundle\Transfer\Worker\GithubOrganizationsToEzPlatformContentTransformer;
use Transfer\GithubBundle\Transfer\Worker\GithubUserToEzPlatformUserTransformer;
use Transfer\Manifest\ManifestInterface;
use Transfer\Procedure\ProcedureBuilder;
use Transfer\Processor\EventDrivenProcessor;
use Transfer\Processor\ProcessorInterface;
use Transfer\Processor\SequentialProcessor;
use Transfer\Worker\SplitterWorker;

class GithubToEzPlatformManifest implements ManifestInterface
{
    /**
     * @var Repository
     */
    protected $repository;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var SequentialProcessor
     */
    protected $processor;

    /**
     * GithubAdapter constructor.
     *
     * @param Repository $repository
     * @param array      $options
     */
    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
        $this->processor = new SequentialProcessor();
    }

    /**
     * Option configuration.
     *
     * @param OptionsResolver $resolver
     */
    protected function configureOptions(OptionsResolver $resolver)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'github_to_ezplatform';
    }

    /**
     * {@inheritdoc}
     */
    public function getProcessor()
    {
        return $this->processor;
    }

    /**
     * {@inheritdoc}
     */
    public function configureProcedureBuilder(ProcedureBuilder $builder)
    {
        $builder
            ->createProcedure('setup')
                ->addSource(new LocalDirectoryAdapter(array('directory' => __DIR__.'/../../Resources/contenttypes')))
                    ->addWorker(function (ValueObject $object) { return $object->data; })
                    ->addWorker(new YamlToArrayTransformer())
                    ->split()
                    ->addWorker(function($data) {
                        return new ContentTypeObject($data);
                    })
                ->addTarget(new EzPlatformAdapter(array('repository' => $this->repository)))
            ->end()

            // https://github.com/valisj/transfer-website-generator/blob/master/generator/src/Manifest/JsonManifest.php L44-48
            /*
            ->createProcedure('setup')
                ->addSource(array(new ContentObject(), new CotnetnObject)))
                ->addTarget(new EzPlatformAdapter(array('repository' => $this->repository)))
            ->end()
            */

            ->createProcedure('import')

                // Githubusers to eZ Platform users
                // Github organizations to eZ Platform content and locations
                ->createProcedure('github_to_ezplatform')
                    ->addSource(new GithubAdapter([
                        'users' => array('htollefsen', 'valisj'),
                        'organizations' => array('transfer-framework'),
                    ]))
                        ->split()
                        ->addWorker(new GithubUserToEzPlatformUserTransformer())
                        ->addWorker(new GithubOrganizationsToEzPlatformContentTransformer())
                    ->addTarget(new EzPlatformAdapter(array('repository' => $this->repository)))
                ->end()
            ->end()
        ;
    }
    /**
     * {@inheritdoc}
     */
    public function configureProcessor(ProcessorInterface $processor)
    {
        $logger = new Logger('default');
        $logger->pushHandler(new StreamHandler(sprintf('%s/%s.log', __DIR__.'/../../../../app/logs/transfer/users', date('Y-m-d')), Logger::DEBUG));
        if ($processor instanceof EventDrivenProcessor) {
            $processor->setLogger($logger);
        }
    }
}
