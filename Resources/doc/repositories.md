# How to add a mongo repository

For adding a custom mongo repository, we must do the following steps:

1. Create a repository class
2. Declare it as a service
3. Assign the corresponding tag


# 1. Create a repository class

There's un example of a class which work as repository:

    namespace Pouzor\CommonBundle\Repository;

    use Pouzor\MongoBundle\Repository\Repository;


    class ClientRepository extends Repository{

    }


Extending Repository class from mongo bundle allows you to use methods as findBy, findOneby, findMax, update, remove and save among others. Otherwise, you can
always create your own repository class on implementing the RepositoryInterface .

# 2. Declare it as a service

For declaring our new repository class as a symfony service, we go to the Resources/config/services.yml file from our bundle and write this:

    mybundle.repository.client:
        parent: mongobundle.repository.abstract
        class: Foo\CommonBundle\Repository\ClientRepository


# 3. Assign the corresponding tag

Now, our repository must know which collection it will handle. for doing this, we have to ways: calling the setCollection() method from our repository or
passing the collection name as a tag attribute.

    mybundle.repository.client:
        parent: mongobundle.repository.abstract
        class: Foo\CommonBundle\Repository\ClientRepository
        tags:
            - { name: mongo.repository, collection: "%client_collection_name%" }


The second way is to stablish it into collection scheme configuration:

    Game:
        repository: Foo\CommonBundle\Repository\GameRepository ## you can also use a service name.
        fields: ~
        indexes: ~


If a class is used, the bundle will automatically construct a service which will be available into service container under the name of **mongobundle.repository.{game}** . This class must implements
the RepositoryInterface available in bundle.
The advantages of using tags is that our dependencies injector will know the class as a mongo repository and automatically will inject a MongoManager instance.
Also, collection name can be passed as parameter.

