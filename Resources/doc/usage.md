# Usage

Now you have declared your repository service ```mybundle.repository.client```, you can inject it in you class.

For example :

    #services.yml
    mybundle.repository.client:
        parent: mongobundle.repository.abstract
        class: AppBundle\Repository\ClientRepository
        calls:
            - [ init, []]
        tags:
            - { name: mongo.repository, collection: "%client_collection_name%" }  

    app.command.mycommand:
        class: AppBundle\Command\MyCommand
        arguments: ["@mybundle.repository.client"]
        tags:
            - { name: console.command }

  
And now in your command


    #MyCommand.php
    public function __construct($repo)
        {
            $this->clientRepo = $repo;
        }
    
    protected function execute(InputInterface $input, OutputInterface $output) {
        $client = $this->clientRepo->find(["email" => "toto@gmail.com"]);
           ...
    
    }

Happy Coding !!