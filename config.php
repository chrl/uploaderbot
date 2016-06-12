<?php

return array(

    'strategy' => array(
        'schedule'=> array(
            'desc'=>'Add filenames to resize queue',
            'listImagesInFolder'=>array(
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
        'upload' => array(
            'desc'=>'Upload images to amazon webservice',
            'getImagesFromQueue'=>array(
                'ok'=>array(
                    'uploadImages'=>array(
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
        'retry'=>array(
            'desc'=>'Requeue failed images to resize queue',
            'getImagesFromQueue'=>array(
                'queue'=>'failed',
                'ok' => array(
                    'addImagesToQueue'=>array(
                        'queue'=>'resize',
                    )
                )

            )
        ),
        'status'=>array(
            'desc'=>'Show queues length',
            'listQueues'=>array(

            )
        ),

    ),
    'access'=>array(

        'aws'=> array(
            'bucket'=>'botuploads',
            'key'=>'AKIAJRMJFCA2JWKKEZ5Q',
            'secret'=>'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',
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