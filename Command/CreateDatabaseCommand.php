<?php

namespace Pouzor\MongoBundle\Command;

use Pouzor\MongoBundle\Mongo\MongoManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CreateDatabaseCommand extends Command
{
    /**
     * @var MongoManager
     */
    protected $manager;

    /**
     * @param null|string $name
     * @param MongoManager $manager
     */
    public function __construct($name, MongoManager $manager)
    {
        $this->setName($name);

        $this->manager = $manager;

        parent::__construct($this->getName());
    }

    protected function configure()
    {
        $this
        //    ->setName('mongo:database:create')
            ->setDescription('create Mongo databases')
            ->addOption('database', 'd', InputOption::VALUE_OPTIONAL, null)
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'If specified, all configured connections will be created')
            ->addOption('drop-and-create', 'dc', InputOption::VALUE_NONE, false)
            ->addOption('create-indexes', 'i', InputOption::VALUE_NONE, false)
            ->setHelp(<<<EOF
The <info>%command.name%</info> Create Mongo databases and collections with indexes

<info>php %command.full_name%</info>
EOF
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        if ($input->hasOption('database') && $input->getOption('database') !== null)
            $databases = [$input->getOption('database') => $this->manager->getDatabases()[$input->getOption('database')]];
        elseif ($input->hasOption('all'))
            $databases = $this->getManager()->getDatabases();
        else
            throw new \Exception("You must specify --all option for all databases or --database=name for a specific database");


        $createIndexes = $input->hasOption('create-indexes') ? $input->getOption('create-indexes') : false;

        $mustDrop = $input->hasOption('drop-and-create') ? $input->getOption('drop-and-create') : false;

        $dialog = $this->getHelperSet()->get('dialog');

        foreach ($databases as $name => $conf) {
            if (!$input->getOption('no-interaction')) {
                if (!$dialog->askConfirmation(
                    $output,
                    sprintf(
                        "<question>Really create MongoDB database %s for %s connection (old database will be erased) ?</question> ",
                        $conf['db'],
                        $name
                    ),
                    false
                )
                ) {
                    continue;
                }
            }

            try {
                $response = $this->manager->drop($name);
                if (isset($response['ok']) && $response['ok'] == 1) {
                    $output->writeln('Database <comment>' . $conf['db'] . '</comment> dropped');
                }
            } catch (\Exception $e) {
                $output->writeln(sprintf("<error>%s</error>", $e->getMessage()));
            }


            $db = $this->manager->create($name);

            if (null !== $db) {
                $output->writeln(sprintf('Created database <comment> %s</comment> for connection <comment>%s</comment> ...', $db->getName(), $name));

                $collections = $this->manager->createCollectionsFor($name, $mustDrop);

                if ($output->getVerbosity() > OutputInterface::VERBOSITY_QUIET) {
                    foreach ($collections as $collectionName => $object) {
                        $output->writeln("Created collection <comment>$collectionName</comment> ...");
                    }
                }

                if ($createIndexes) {
                    foreach ($collections as $collectionName => $col) {
                        $indexes = $this->manager->ensureIndexesFor($collectionName, $name);

                        if ($output->getVerbosity() > OutputInterface::VERBOSITY_QUIET) {
                            $output->writeln(sprintf("Created index for field(s):"));

                            foreach ($indexes as $indexName => $index) {
                                $output->writeln(sprintf("<comment>%s</comment> with fields: %s", $indexName, implode(', ', array_keys($index))));
                            }
                        }
                    }
                }
            }

        }
    }

    /**
     * @return \Pouzor\MongoBundle\Mongo\MongoManager
     */
    public function getManager()
    {
        return $this->manager;
    }

    /**
     * @param \Pouzor\MongoBundle\Mongo\MongoManager $manager
     */
    public function setManager($manager)
    {
        $this->manager = $manager;
    }


}
