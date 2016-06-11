<?php

return array(

    'strategy' => array(
        'schedule'=> array(
            'desc'=>'Add filenames to resize queue',
            'listImages'=>array(
                'ok' => array(
                    'addImagesToQueue'=>array(
                        'ok'=>array(
                            'addFailedImagesToQueue'=>array(

                            )
                        )
                    ),
                ),
            )
        ),
        'resize' => array(
            'desc'=>'Resize next images from the queue',
            'getImagesFromQueue'=>array(
                'ok'=>array(
                    'resizeImages'=>array(
                        'ok'=>array(
                            'addImagesToQueue'=>array(
                                'ok'=>array(
                                    'addFailedImagesToQueue'=>array(
                                        
                                    )
                                )
                            )
                        )
                    )
                )
            )
        ),
        'default'=>array(
            'desc'=>'Show help and exit',
            'outputHelp'=>array(

            )
        ),
        'status'=>array(
            'desc'=>'Show queues length',
            'listQueues'=>array(

            )
        )

    ),
    'access'=>array(
        'storage'=>array(
            'aws'=> array(
                'key'=>'123',
                'secret'=>'123',
            )
        ),
        'rabbit'=>array(
            'host'=>'localhost',
            'port'=>5672,
            'user'=>'guest',
            'pwd'=>'guest',
            'vhost'=>'/',
        )
    ),
    'tempfolder'=>'/tmp/',
);