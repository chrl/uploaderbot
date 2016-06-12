<?php

namespace UploaderBot;

use \AMQPConnection;

class Queue
{
    private static $settings;
    private $cnn;
    private $channel;
    private $exchanges;
    private $queues;
    private $cur_envelope;


    private function __construct($name)
    {
        $this->cnn = new AMQPConnection();
        $this->cnn->setHost(self::$settings[$name]['host']);
        $this->cnn->setPort(self::$settings[$name]['port']);

        if (self::$settings[$name]['user']) {
            $this->cnn->setLogin(self::$settings[$name]['user']);
            $this->cnn->setPassword(self::$settings[$name]['pwd']);
        }

        $this->connect();
    }

    public function __destruct()
    {
        $this->cnn->disconnect();
    }

    public static function setSettings($name, $settings)
    {
        self::$settings[$name] = $settings;
    }

    /**
     * @param string $name
     * @return Queue
     */
    public static function getInstance($name = 'main')
    {
        static $instances;

        if (!isset($instances[$name])) {
            $instances[$name] = new self($name);
        }

        return $instances[$name];
    }

    private function connect()
    {
        $this->cnn->connect();

        if ($this->cnn->isConnected()) {
            $this->channel = new \AMQPChannel($this->cnn);
            $this->channel->setPrefetchCount(1);
        }
    }

    public function createExchange($name, $type, $declare=true)
    {
        if (!isset($this->exchanges[$name]) && $this->channel->isConnected()) {
            $this->exchanges[$name] = new \AMQPExchange($this->channel);
            $this->exchanges[$name]->setName($name);
            $this->exchanges[$name]->setType($type);
            $this->exchanges[$name]->setFlags(AMQP_DURABLE);
            if ($declare) {
                try {
                    $this->exchanges[$name]->declareExchange();
                } catch (\AMQPExchangeException $e) {
                }
            }
        }
    }

    public function createQueue($ex_name, $name, $routing_key, $declare=true)
    {
        $cnt = 0;
        if (!isset($this->queues[$name]) && $this->channel->isConnected()) {
            $this->queues[$name] = new \AMQPQueue($this->channel);
            $this->queues[$name]->setName($name);
            $this->queues[$name]->setFlags(AMQP_DURABLE);
            if ($declare) {
                $cnt = $this->queues[$name]->declareQueue();
            }
            $this->queues[$name]->bind($ex_name, $routing_key);
        } else {
            $cnt = 1;
        }
        return $cnt;
    }

    public function getChannel()
    {
        return $this->channel;
    }

    public function getExchange($name)
    {
        return $this->exchanges[$name];
    }

    public function getQueue($name)
    {
        return $this->queues[$name];
    }

    public function getFromQueue($name, $flags=AMQP_NOPARAM)
    {
        if ($this->queues[$name]) {
            $item = $this->queues[$name]->get($flags);

            if ($item) {
                $this->cur_envelope = $item;
                return $item->getBody();
            }
        }
    }

    public function confirm($name)
    {
        if ($this->queues[$name] && $this->cur_envelope) {
            return $this->queues[$name]->ack($this->cur_envelope->getDeliveryTag());
        }
    }


    public static $counts;

    public static function getCount($ex)
    {
        return self::$counts[$ex];
    }


    public static function initQueue($exchange, $declare = true)
    {
        static $queues;

        if (!isset($queues[$exchange])) {
            $queues[$exchange]= Queue::getInstance();
            $queues[$exchange]->createExchange('ex_' . $exchange, AMQP_EX_TYPE_DIRECT);
            self::$counts[$exchange] =
                $queues[$exchange]->createQueue('ex_' . $exchange, $exchange, $exchange . '.' . 'queue', $declare);
        }

        return $queues[$exchange];
    }

    public static function getSizes(array $queueList)
    {
        foreach ($queueList as $queue) {
            self::initQueue($queue);
        }

        return self::$counts;
    }

    public static function pushToQueue($ex, $data)
    {
        $queue = self::initQueue($ex);

        $exch = $queue->getExchange('ex_' . $ex);

        return $exch->publish(json_encode($data), $ex . '.' . 'queue');
    }

    public static function getItemFromQueue($ex)
    {
        $queue = self::initQueue($ex);

        $item = $queue->getFromQueue($ex); #AMQP_AUTOACK

        if ($item) {
            return json_decode($item, true);
        }
    }

    public static function ack($ex)
    {
        $queue = self::initQueue($ex);
        return $queue->confirm($ex);
    }
};
