# Installation

Using composer:

    "repositories": [
            {
                "type": "vcs",
                "url": "git@github.com:Pouzor/mongobundle.git"
            }
        ],
        "require": {
            ...
            "doctrine/mongodb-odm": "*",
            ...
            "Pouzor/mongobundle": "dev-master"
        },

# Configuration

This example shows you all the configuration options for this bundle:

    #app/config/config.yml
    mongo:
        default_connection:   ftv
        connections:
            primary:
                host:          %mongo_host%
                port:          %mongo_port%
                db:            %mongo_database%
                password:      %mongo_user%
                username:      %mongo_password%
                schema:        "%kernel.root_dir%/config/mongo/default.yml"
                options:       ~
            delay:
                host:          %mongo_host%
                port:          %mongo_port%
                db:            %mongo_delay_database%
                password:      %mongo_user%
                username:      %mongo_password%
                schema:        "%kernel.root_dir%/config/mongo/default.yml"
                options:       ~

A scheme file looks like:

    #app/config/mongo/default.yml
    #This file is mandatory for now. You need to declare all your Collections here
    Game:
        fields: ~
        indexes:
            email:
                fields:
                    source: 1
                    idExt: 1
                    uuid: 1
                options:
                    unique: true
                    sparse: false
            idExt: 1
            qualifiedAt: -1
            lastUpdatedAt: 1




Happy Coding!!

