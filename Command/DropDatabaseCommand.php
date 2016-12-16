<?php

namespace Pouzor\MongoBundle\Command;

use Pouzor\MongoBundle\Mongo\MongoManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DropDatabaseCommand extends Command
{

    /**
     * @var MongoManager
     */
    protected $manager;

    public function __construct($name, MongoManager $manager)
    {
        $this->setName($name);

        $this->manager = $manager;

        parent::__construct($this->getName());
    }


    protected function configure()
    {
        $this
            ->setDescription('Drop Mongo databases')
            ->setHelp(<<<EOF
The <info>%command.name%</info> Drop Mongo databases

<info>php %command.full_name%</info>
EOF
        )
            ->addArgument('database', InputArgument::OPTIONAL, 'database to drop', $this->manager->getDefaultConnection());
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $name = $input->getArgument('database');

        $database = $this->manager->getDatabase($name);

        $dialog = $this->getHelperSet()->get('dialog');

        $response = null;
        if($database){
            if (!$input->getOption('no-interaction')) {
                if ($dialog->askConfirmation(
                    $output,
                    '<question>Really drop MongoDB database "'.$name.'" ?</question> ',
                    false
                )) {
                    $response = $this->manager->drop($name);
                }
            }else{
                $response = $this->manager->drop($name);
            }
        }

        if (isset($response['ok']) && $response['ok'] == 1) {
            $output->writeln('Database <comment>' . $name . '</comment> dropped');
        } else {
            $output->writeln('<error>Database ' . $name . ' not dropped</error>');
            $output->writeln(print_r($response, true));
        }
    }
}
