# Uploader Bot [![Build Status](https://travis-ci.org/chrl/uploaderbot.svg?branch=testing)](https://travis-ci.org/chrl/uploaderbot)

This software resizes and uploads images to AWS.

## Installation

Assuming that composer is accessible within your PATH:

    git clone git@github.com:chrl/uploaderbot.git
    cd uploaderbot; composer self-update && composer install

RabbitMQ-server and php-amqp extension are required.

## Configuration

Configuration is made with `config.php` file. The file is a global
configuration array. 

### Strategy section

Workflow for all actions is defined in strategy section of config file.
Every action in strategy is a method in UploaderBot file. This method returns exit-state as first array element.
The system decides which action to execute next, based on that exit-state.

This type of configuration simplifies changing and reusing of methods without need of code modification.

### Access section

All access params are in `access` section of configuration file. 

* Amazon webservices access params: key, secretkey, bucket name
* RabbitMQ access params.

## Running

You'll have to prepare folder with sample images, that will be resized and uploaded.

`./bot.php --help` -- outputs help, including list of available commands, and exits.

`./bot.php schedule ./images` -- schedules images from folder `./images` for resizing

`./bot.php resize [-n <count>]` -- resizes queued images. If -n option is omitted, works with all images in the queue.

`./bot.php upload [-n <count>]` -- uploads resized images. If -n option is omitted, works with all images in the queue.

`./bot.php retry [-n <count>]` -- moves images from failed queue to resize queue, to process them again. If -n option is omitted, works with all images in the queue.

`./bot.php status` -- shows number of messages in all queues.

## Debug

Add `--verbose` option for turning on verbose logging

## TODO

If this script is going to be used in production, pay attention to this aspects:

* Daemonize scripts execution
* Failsafe options (if script is killed, all messages currently processing can be lost)
* There may be unrecoverable errors in failed queue: i.e. file is deleted -> there is no reason to requeue it again
