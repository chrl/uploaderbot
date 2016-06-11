<?php

namespace UploaderBot;

use CHH\Optparse;

class UploaderBot extends UploaderBotService {


    public function listImages()
    {
        $folder = $this->commando[1];


        if (!$folder) {
            echo 'Missing folder argument for schedule strategy'.PHP_EOL;
            die;
        }

        $this->log("Got images folder: ".$folder);

        $images = array();

        foreach (glob($folder . '*') as $filename) {

            if (false !== @imagecreatefromstring(file_get_contents($filename))) {
                array_push($images, $filename);
            }

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

}