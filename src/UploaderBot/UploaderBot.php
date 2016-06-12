<?php

namespace UploaderBot;

use CHH\Optparse;

/**
 * Class UploaderBot -- implements all logic for uploading pictures to aws
 *
 * @package UploaderBot
 */
class UploaderBot extends UploaderBotService
{


    /**
     * Lists images in folder, given as second argument from commando
     *
     * @return array
     */
    public function listImagesInFolder()
    {
        $folder = $this->commando[1];


        if (!$folder) {
            echo 'Missing folder argument for schedule strategy'.PHP_EOL;
            die;
        }

        $this->log("Got images folder: ".$folder);

        $images = array();

        if (substr($folder, -1)!='/') {
            $folder.='/';
        }

        foreach (glob($folder . '*') as $filename) {
            if (false !== @imagecreatefromstring(file_get_contents($filename))) {
                array_push($images, $filename);
            }
        }

        return array('ok',array('images'=>$images,'queue'=>'resize'));
    }


    /**
     * Adds images from registry to queue. Queue name is set in strategy config, or in registry
     *
     * @return array
     */
    public function addImagesToQueue()
    {
        if (isset($this->strategy['queue'])) {
            $queue = $this->strategy['queue'];
        } else {
            $queue = $this->registry['queue'];
        }

        $this->log('Got images: '.var_export($this->registry['images'], true));

        foreach ($this->registry['images'] as $image) {
            $messageBody = is_array($image)
                                ? $image
                                : array(
                                    'file'=>$image,
                                    'added'=>time(),
                                );

            Queue::pushToQueue($queue, $messageBody);
        }

        return array('ok',array());
    }


    /**
     * Adds failed images from registry to failed queue.
     *
     * @todo use addImagesToQueue to process failed images
     * @return array
     */
    public function addFailedImagesToQueue()
    {
        if (!isset($this->registry['failed'])) {
            $this->registry['failed'] = array();
        }

        $this->log('Got failed images: '.var_export($this->registry['failed'], true));

        foreach ($this->registry['failed'] as $image) {
            $messageBody = array(
                'file'=>$image,
                'added'=>time(),
            );

            Queue::pushToQueue('failed', $messageBody);
        }

        return array('ok',array());
    }

    /**
     * Lists queues and count of messages
     *
     * @return array
     */
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
            echo str_pad($key, 10, ' ', STR_PAD_RIGHT).' '.$value.PHP_EOL;
        }


        return array('ok',array());
    }

    /**
     * Adds images from queue to registry. Queue name is set in strategy config, or a strategy name is used
     *
     * @return array
     */
    public function getImagesFromQueue()
    {
        $total = 0;

        if (isset($this->strategy['queue'])) {
            $queue = $this->strategy['queue'];
        } else {
            $queue = $this->commando['command'];
        }

        $this->log('Got queue: '.$queue);

        $images = array();
        while (true) {
            $image = Queue::getItemFromQueue($queue);
            $total++;

            if (!$image) {
                return array('ok', array('images' => $images));
            } else {
                Queue::ack($queue);
            }

            array_push($images, $image);

            if ($this->count && ($total >= $this->count)) {
                return array('ok',array('images'=>$images));
            }
        }

        return array('ok',array('images'=>$images));
    }

    /**
     * Resizes images from registry.
     * 
     * @return array
     */
    public function resizeImages()
    {
        if (!file_exists('images_resized')) {
            mkdir('images_resized');
        }

        $images = array();
        
        foreach ($this->registry['images'] as $image) {
            $this->log('Resizing image: '.$image['file']);

            $im1 = @imagecreatefromstring(@file_get_contents($image['file']));

            if (!$im1) {
                if (!isset($this->registry['failed'])) {
                    $this->registry['failed'] = array();
                }
                array_push($this->registry['failed'], $image['file']);
                $this->log('Failed image: '.$image['file']);
                continue;
            }

            list($width, $height) = getimagesize($image['file']);

            if ($width>$height) {
                $dx =0;
                $dw = 640;
                $dh = round($height * ($dw/$width));
                $dy = round(320 - $dh/2);
            } else {
                $dy =0;
                $dh = 640;
                $dw = round($width * ($dh/$height));
                $dx = round(320 - $dw/2);
            }

            $im2 = imagecreatetruecolor(640, 640);
            imagefill($im2, 0, 0, imagecolorallocate($im2, 255, 255, 255));

            imagecopyresampled(
                $im2,
                $im1,
                $dx,
                $dy,
                0,
                0,
                $dw,
                $dh,
                $width,
                $height
            );

            imagejpeg($im2, 'images_resized/'.basename($image['file']), 90);
            array_push($images, 'images_resized/'.basename($image['file']));
        }

        return array('ok',array('images'=>$images,'queue'=>'upload'));
    }

    /**
     * Uploads images from registry
     *
     * @return array
     */
    public function uploadImages()
    {
        $images = array();

        if (!isset($this->registry['failed'])) {
            $this->registry['failed'] = array();
        }

        foreach ($this->registry['images'] as $image) {
            $this->log('Uploading image: '.$image['file']);

            $s3 = new \S3(
                $this->config['access']['aws']['key'],
                $this->config['access']['aws']['secret']
            );

            $res = $s3->putObjectFile($image['file'], $this->config['access']['aws']['bucket'], basename($image['file']), \S3::ACL_PUBLIC_READ);

            if ($res) {
                array_push($images, 'images_resized/' . basename($image['file']));
            } else {
                array_push($this->registry['failed'], 'images_resized/' . basename($image['file']));
            }
        }

        return array('ok',array('images'=>$images,'queue'=>'done'));
    }
}
