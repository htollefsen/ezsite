<?php

namespace ImportBundle\Transfer\Manifest;

use eZ\Publish\API\Repository\Repository;
use Transfer\Adapter\LocalDirectoryAdapter;
use Transfer\Commons\Yaml\Worker\Transformer\YamlToArrayTransformer;
use Transfer\Data\ValueObject;
use Transfer\EzPlatform\Adapter\EzPlatformAdapter;
use Transfer\EzPlatform\Worker\Transformer\ArrayToEzPlatformContentTypeObjectTransformer;
use Transfer\Manifest\ManifestInterface;
use Transfer\Procedure\ProcedureBuilder;
use Transfer\Processor\EventDrivenProcessor;
use Transfer\Processor\ProcessorInterface;
use Transfer\Processor\SequentialProcessor;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Transfer\Worker\SplitterWorker;

class YamlFilesToEzPlatformContentTypeManifest implements ManifestInterface
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

    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
        $this->processor = new SequentialProcessor();
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'yamlfiles_to_ezplatform_contenttypes';
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
            ->createProcedure('import')
                ->createProcedure('contenttype')

                    // Here we add the source of our data.
                    ->addSource(new LocalDirectoryAdapter(array('directory' => __DIR__.'/../../Resources/contenttypes')))

                    // Source returns ValueObjects, so we'll need to extract the raw-data.
                    ->addWorker(function (ValueObject $object) {
                        return $object->data;
                    })

                    // This worker transforms the yaml-data from the files, to arrays.
                    ->addWorker(new YamlToArrayTransformer())

                    // This worker transforms the arrays into ContentTypeObjects (not eZ ContentTypes(!))
                    ->addWorker(new ArrayToEzPlatformContentTypeObjectTransformer())

                    // Incase several was passed, we split each one, because our source adapter prefers to
                    // handle one ContentTypeObject at a time.
                    ->addWorker(new SplitterWorker())

                    // And lastly we send this to the EzPlatformAdapter who talks to eZPlatform for us.
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
        $logger->pushHandler(new StreamHandler(sprintf('%s/%s.log', __DIR__.'/../../../../app/logs/transfer/contenttype', date('Y-m-d')), Logger::DEBUG));
        if ($processor instanceof EventDrivenProcessor) {
            $processor->setLogger($logger);
        }
    }
}