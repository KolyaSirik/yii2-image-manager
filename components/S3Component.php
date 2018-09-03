<?php

namespace noam148\imagemanager\components;

use Aws\S3\S3Client;
use noam148\imagemanager\helpers\ImageHelper;
use yii\base\Component;

class S3Component extends Component
{
    public $endpoint;

    public $key;

    public $secret;

    public $defaultBucket;

    /** @var S3Client */
    protected $client;

    public function init()
    {
        $this->client = new S3Client([
            'version' => 'latest',
            'region'  => 'us-east-1',
            'credentials' => [
                'key' => $this->key,
                'secret' => $this->secret,
            ],
            'endpoint' => $this->endpoint,
            'use_path_style_endpoint' => true,
        ]);
    }

    public function put($fileName, $filePath, $bucket = null)
    {
        $bucket = $bucket ?? $this->defaultBucket;
        $this->createBucketIfNotExists($bucket);

        $this->client->putObject([
            'Bucket' => $bucket,
            'Key'    => $fileName,
            'Body'   => fopen($filePath, 'r'),
            'ACL'    => 'public-read',
        ]);
    }



    public function createBucketIfNotExists($bucket)
    {
        if (!$this->client->doesBucketExist($bucket)) {
            $this->client->createBucket([
                'ACL' => 'public-read',
                'Bucket' => $bucket,
            ]);

            $this->client->putBucketPolicy([
                'Bucket' => $bucket,
                'Policy' => '{
                  "Version":"2012-10-17",
                  "Statement":[
                    {
                      "Sid":"AddPerm",
                      "Effect":"Allow",
                      "Principal": "*",
                      "Action":["s3:GetObject"],
                      "Resource":["arn:aws:s3:::' . $bucket . '/*"]
                    }
                  ]
                }',
            ]);
        }
    }

    public function getObject($fileName, $bucket = null)
    {
        $result = null;

        $bucket = $bucket ?? $this->defaultBucket;

        if ($this->client->doesBucketExist($bucket)) {
            if($this->client->doesObjectExist($bucket, $fileName)){
                $result = $this->client->getObject([
                    'Bucket' => $bucket,
                    'Key'    => $fileName,
                    'SaveAs' => ImageHelper::getTempFilePath($fileName)
                ]);
            }
        }

        return $result;
    }

    public function doesObjectExist($fileName, $bucket = null)
    {
        $result = false;

        $bucket = $bucket ?? $this->defaultBucket;

        if ($this->client->doesBucketExist($bucket)) {
            $result = $this->client->doesObjectExist($bucket, $fileName);
        }

        return $result;
    }

    public function delete($fileName, $bucket = null)
    {
        $bucket = $bucket ?? $this->defaultBucket;
        if ($this->client->doesBucketExist($bucket)) {
            if($this->client->doesObjectExist($bucket, $fileName)){
                $this->client->deleteObject([
                    'Bucket' => $bucket,
                    'Key'    => $fileName,
                    'ACL'    => 'public-read',
                ]);
            }
        }
    }
}
