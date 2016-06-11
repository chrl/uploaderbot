<?php

namespace UploaderBot;

use Aws\Test\PartitionEndpointProviderTest;
use CHH\Optparse;
use Eventviva\ImageResize;

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

        return array('ok',array('images'=>$images,'queue'=>'resize'));
    }


    public function addImagesToQueue() {

        $this->log('Got images: '.var_export($this->registry['images'],true));

        foreach($this->registry['images'] as $image) {
            $messageBody = array(
                'file'=>$image,
                'added'=>time(),
            );

            Queue::pushToQueue($this->registry['queue'], $messageBody);

        }

        return array('ok',array());
    }


    public function addFailedImagesToQueue() {

        if (!isset($this->registry['failed'])) $this->registry['failed'] = array();

        $this->log('Got failed images: '.var_export($this->registry['failed'],true));

        foreach($this->registry['failed'] as $image) {
            $messageBody = array(
                'file'=>$image,
                'added'=>time(),
            );

            Queue::pushToQueue('failed', $messageBody);

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

    /**
     * @return array
     */
    public function getImagesFromQueue()
    {
        $total = 0;

        $images = array();
        while(true) {
            $image = Queue::getItemFromQueue($this->commando['command']);


            $total++;

            if (!$image) {
                return array('ok', array('images' => $images));
            } else {
                Queue::ack($this->commando['command']);
            }

            array_push($images,$image);

            if ($this->count && ($total >= $this->count)) return array('ok',array('images'=>$images));
        }

        return array('ok',array('images'=>$images));
    }

    public function resizeImages()
    {

        // TODO: add failed images to fail queue

        if (!file_exists('images_resized')) mkdir('images_resized');

        $images = array();
        
        foreach($this->registry['images'] as $image)
        {

            $this->log('Resizing image: '.$image['file']);

            $im1 = @imagecreatefromstring(@file_get_contents($image['file']));

            if (!$im1) {
                if (!isset($this->registry['failed'])) $this->registry['failed'] = array();
                array_push($this->registry['failed'], $image['file']);
                $this->log('Failed image: '.$image['file']);
                continue;
            }

            list($width,$height) = getimagesize($image['file']);

            if($width>$height) {
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
            imagefill($im2,0 , 0 , imagecolorallocate($im2,255 ,255 ,255 ));

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

            imagejpeg($im2,'images_resized/'.basename($image['file']),90);
            array_push($images, 'images_resized/'.basename($image['file']));

        }

        return array('ok',array('images'=>$images,'queue'=>'upload'));

    }

}