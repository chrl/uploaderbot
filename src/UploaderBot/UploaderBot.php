<?php

namespace UploaderBot;

use CHH\Optparse;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;


class UploaderBot {

    public $debug = true;
    protected $commando;
    protected $count = 0;
    protected $config = array();

    protected $strategy = array();
    protected $runningAction = false;

    protected $registry = array();

    public function usage() {
        echo $this->commando->usage().PHP_EOL;
        foreach($this->config['strategy'] as $name=>$strategy) {
            echo "  ".str_pad($name,10,' ',STR_PAD_RIGHT).$strategy['desc'].PHP_EOL;
        }
        die;
    }

    public function loadConfig() {
        $this->config = require("config.php");
        return $this;
    }

    public function init() {

        // 0. Initialize Rabbit

        Queue::setSettings('main', $this->config['access']['rabbit']);

        // 1. Parse options

        $this->commando = new Optparse\Parser("Uploader Bot\nList of available commands:");

        $this->commando->addFlag("help", array("alias" => "-h"), array($this,"usage"));
        $this->commando->addFlag("number",array("alias"=> "-n", "default"=>0, "has_value"=>true, "help"=>"Set number of processed items"));
        $this->commando->addArgument("command", array("required" => true,"help"=>"Strategy to run"));

        try {
            $this->commando->parse();
        } catch (Optparse\Exception $e) {
            $this->usage();
        }

        // 2. Define strategy

        $strategy = $this->commando['command'];

        if (isset($this->config['strategy'][$strategy])) {
            $this->log("Running strategy: " . $strategy);
            if ($this->commando['number']) {
                $this->count = $this->commando['number'];
                $this->log("Setting count to: " . $this->count);
            }

            unset($this->config['strategy'][$strategy]['desc']);
            $this->setStrategy($this->config['strategy'][$strategy]);

        } else {
            $this->usage();
        }

        return $this;
    }



    public function run()
    {
        $initialAction = array_keys($this->strategy)[0];
        $this->runAction($initialAction,$this->strategy[$initialAction]);

        return $this;
    }

    public function runAction($action,$follows) {

        $this->log('Running action: '.$action);

        if (method_exists($this, $action)) {

            $this->runningAction = $action;
            $result = $this->{$action}();
            $this->runningAction = false;

            foreach($result[1] as $k=>$v) {
                $this->registry[$k]=$v;
            }

            if (isset($follows[$result[0]])) {
                $action = array_keys($follows[$result[0]])[0];
                $this->runAction($action,$follows[$result[0]][$action]);
            }

        }
    }

    /**
     * @param $message
     */
    public function log($message)
    {
        if ($this->debug) echo '['.date('Y-m-d H:i:s').'] '.($this->runningAction?'['.$this->runningAction.'] ':'').$message.PHP_EOL;
    }

    /**
     * @return boolean
     */
    public function isDebug()
    {
        return $this->debug;
    }

    /**
     * @param array $strategy
     */
    public function setStrategy($strategy)
    {
        $this->strategy = $strategy;
    }

    public function postMessageToQueue($queue,$messageBody)
    {

        $exchange = 'router';

        $connection = new AMQPStreamConnection(
            $this->config['access']['rabbit']['host'],
            $this->config['access']['rabbit']['port'],
            $this->config['access']['rabbit']['user'],
            $this->config['access']['rabbit']['pass'],
            $this->config['access']['rabbit']['vhost']
        );
        $channel = $connection->channel();
        $channel->queue_declare($queue, false, true, false, false);
        $channel->exchange_declare($exchange, 'direct', false, true, false);
        $channel->queue_bind($queue, $exchange,'routing_'.$queue);


        $message = new AMQPMessage($messageBody, array('content_type' => 'text/plain', 'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT));
        $channel->basic_publish($message, $exchange);

        $channel->close();
        $connection->close();

        return $this;
    }

    /**
     *
     */
    public function listImages()
    {
        $folder = $this->commando[1];
        $this->log("Got images folder: ".$folder);

        $images = array();

        foreach (glob($folder . '*.jpg') as $filename) {
            array_push($images, $filename);
        }

        return array('ok',array('images'=>$images));
    }


    public function addImagesToResizeQueue() {

        $this->log('Got images: '.var_export($this->registry['images'],true));

        foreach($this->registry['images'] as $image) {
            $messageBody = array(
                'file'=>$image,
                'added'=>time(),
            );

            Queue::pushToQueue('resize', $messageBody);

            var_dump(Queue::getCount('resize'));

        }

        return array('ok',array());
    }

    public function listQueues()
    {

        $sizes = Queue::getSizes(array(
            'resize',
            'upload',
            'done',
            'failed',
        ));


        echo 'Queue      Count'.PHP_EOL;

        foreach ($sizes as $key=>$value) {
            echo $key.' '.$value.PHP_EOL;
        }


        return array('ok',array());
    }

    public function __destruct()
    {
        // TODO: Implement __destruct() method.

    }
}