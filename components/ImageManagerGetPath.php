<?php

namespace noam148\imagemanager\components;

use bigbrush\tinypng\TinyPng;
use Imagine\Image\Box;
use Imagine\Image\Palette\RGB;
use Imagine\Image\Point;
use noam148\imagemanager\helpers\ImageHelper;
use noam148\imagemanager\Module;
use Yii;
use yii\base\Component;
use noam148\imagemanager\models\ImageManager as Model;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\helpers\ArrayHelper;
use yii\helpers\BaseFileHelper;
use yii\imagine\Image;
use yii\web\UploadedFile;

/**
 * Class ImageManagerGetPath
 * @package noam148\imagemanager\components
 *
 * @property S3Component $s3
 */
class ImageManagerGetPath extends Component
{
    public $yiiMediaPath;

    /**
     * @var null|string $mediaPath Folder path in which the images are stored
     */
    public $mediaPath = null;

    /**
     * @var string $cachePath cache path where store the resized images (relative from webroot (index.php))
     */
    public $cachePath = "assets/imagemanager";

    /**
     * @var boolean $useFilename use original filename in generated cache file
     */
    public $useFilename = true;

    /**
     * @var boolean $useFilename use original filename in generated cache file
     */
    public $absoluteUrl = false;

    /**
     * @var string The DB component name that the image model uses
     * This defaults to the default Yii DB component: Yii::$app->db
     * If this component is not set, the model will default to DB
     */
    public $databaseComponent = 'mongodb';

    /**
     * @var int The maximum file size for upload in KB
     */
    public $maxFileSize = 10000;

    /** @var bool */
    public $useS3 = false;

    public $s3Component = 's3';
    
    public $s3Url;

    public $s3Configuration;

    public $thumbSizes = [];
    
    public $useTinyPng = false;
    
    public $tinyPngComponent = 'tinyPng';

    /**
     * Init set config
     *
     * @return void
     * @throws InvalidConfigException
     */
    public function init()
    {
        parent::init();

        \Yii::$app->set('imageresize', [
            'class' => 'noam148\imageresize\ImageResize',
            'cachePath' => $this->cachePath,
            'useFilename' => $this->useFilename,
            'absoluteUrl' => $this->absoluteUrl,
        ]);

        if ($this->useS3) {
            if (!$this->s3Configuration) {
                throw new InvalidConfigException('S3 configuration is required');
            }

            \Yii::$app->set($this->s3Component, ArrayHelper::merge([
                'class' => 'noam148\imagemanager\components\S3Component',
            ], $this->s3Configuration));
        }

        if (is_callable($this->databaseComponent)) {
            $this->databaseComponent = call_user_func($this->databaseComponent);
        }

        $this->_checkVariables();
    }

    /**
     * Get the path for the given ImageManager_id record
     * @param Model|int $model ImageManager record for which the path needs to be generated
     * @param int $width Thumbnail image width
     * @param int $height Thumbnail image height
     * @param string $thumbnailMode Thumbnail mode
     * @param bool $timestamp
     * @return null|string Full path is returned when image is found, null if no image could be found
     * @throws Exception
     */
    public function getImagePath($model, $width = 400, $height = 400, $thumbnailMode = "inset", $timestamp = false)
    {
        if($this->useS3) {
            return $this->getImagePathS3($model, $width, $height, $thumbnailMode);
        } else {
            return $this->getImagePathLocal($model, $width, $height, $thumbnailMode, $timestamp);
        }
    }

    protected function getImagePathS3($model, $width = 400, $height = 400, $thumbnailMode = "outbound")
    {
        $result = null;

        if (is_object($model) || (is_string($model) && ($model = Model::findOne($model)))) {

            /**
             *@var $model Model
             */

            $fileExtension = ImageHelper::getFileExtension($model);
            if(in_array($fileExtension, Module::IMAGE_EXTENSIONS)){
                if(!$this->checkS3ThumbExist($model, $width, $height, $thumbnailMode)){
                    $name = ImageHelper::getFileName($model);

                    if ($object = $this->s3->getObject($name)) {


                        if ($fileExtension === 'gif') {
                            Image::getImagine()->open(ImageHelper::getTempFilePath($name))->save();
                        }
                        $this->createS3Thumb(ImageHelper::getTempFilePath($name), $model, $width, $height, $thumbnailMode);
                    }
                }

                $result = ImageHelper::getThumbS3Url($model, $width , $height, $thumbnailMode);
            }
        }

        return $result;
    }


    protected function getImagePathLocal($model, $width = 400, $height = 400, $thumbnailMode = "outbound", $timestamp = false)
    {
        if (is_object($model) || (is_string($model) && ($model = Model::findOne($model)))) {
            /** @var Model $model */
            $mode = $thumbnailMode == "outbound" ? "outbound" : "inset";

            $filePath = ImageHelper::getFilePath($model);
            $fileExtension = ImageHelper::getFileExtension($model);

            if (file_exists($filePath) && in_array($fileExtension, Module::IMAGE_EXTENSIONS)) {
                if ($fileExtension === 'gif') {
                    $dir = \Yii::getAlias(sprintf('@webroot/%s/%s', $this->cachePath, ImageHelper::getDir($model)));
                    BaseFileHelper::createDirectory($dir);

                    $newPath = sprintf('%s/%s', $dir, ImageHelper::getFileName($model));

                    if (!file_exists($newPath)) {
                        Image::getImagine()->open($filePath)->save($newPath);
                    }

                    $filePath = $newPath;
                }

                return \Yii::$app->imageresize->getUrl($filePath, $width, $height, $mode, null, $model->fileName) . ($timestamp ? "?t=" . time() : '');
            }
        }

        return null;
    }

    /**
     * Check if the user configurable variables match the criteria
     * @throws InvalidConfigException
     */
    private function _checkVariables()
    {
        if (!is_string($this->databaseComponent)) {
            throw new InvalidConfigException("Image Manager Component - Init: Database component '$this->databaseComponent' is not a string");
        }

        if (Yii::$app->get($this->databaseComponent, false) === null) {
            throw new InvalidConfigException("Image Manager Component - Init: Database component '$this->databaseComponent' does not exists in application configuration");
        }

        if ($this->useTinyPng) {
            if (Yii::$app->get($this->tinyPngComponent, false) === null) {
                throw new InvalidConfigException("Image Manager Component - Init: TinyPNG component '$this->tinyPngComponent' does not exists in application configuration");
            }

            if (Yii::$app->get($this->tinyPngComponent, false) instanceof TinyPng === false) {
                throw new InvalidConfigException("Image Manager Component - Init: TinyPNG component '$this->tinyPngComponent' must be extends from " . TinyPng::class);
            }
        }
    }

    public function getS3()
    {
        return \Yii::$app->get($this->s3Component);
    }

    /**
     * @param Model $modelOriginal
     * @param $cropData
     * @return null|string
     * @throws Exception
     * @throws \Exception
     * @throws \yii\db\StaleObjectException
     */
    public function cropImage(Model $modelOriginal, $cropData)
    {
        if($this->useS3) {
            return $this->cropImageS3($modelOriginal, $cropData);
        } else {
            $imagePathPrivate = ImageHelper::getFilePath($modelOriginal);

            if ($imagePathPrivate && $cropData) {
                $width = round($cropData['width']);
                $height = round($cropData['height']);

                $model = new Model([
                    'fileName' => ImageHelper::getCropFileName($modelOriginal, $width, $height),
                ]);

                if ($model->save()) {
                    try {
                        $imageOriginal = Image::getImagine()->open($imagePathPrivate);
                        $imageOriginalSize = $imageOriginal->getSize();

                        list($imageCanvasWidth, $imageOriginalPositionX, $imageCropPositionXRounded)
                            = $this->handlingCropData($cropData['x'], $cropData['width'], $imageOriginalSize->getWidth());
                        list($imageCanvasHeight, $imageOriginalPositionY, $imageCropPositionYRounded)
                            = $this->handlingCropData($cropData['y'], $cropData['height'], $imageOriginalSize->getHeight());

                        Image::getImagine()->create(new Box($imageCanvasWidth, $imageCanvasHeight), (new RGB())->color('#FFF', 0))
                            ->paste($imageOriginal, new Point($imageOriginalPositionX, $imageOriginalPositionY))
                            ->rotate(round($cropData['rotate']))
                            ->crop(new Point($imageCropPositionXRounded, $imageCropPositionYRounded), new Box($width, $height))
                            ->save(ImageHelper::getFilePath($model));

                        /** @var Module $module */
                        $module = \Yii::$app->controller->module;
                        if ($module->deleteOriginalAfterEdit) {
                            $modelOriginal->delete();
                        }
                    } catch (\Exception $e) {
                        $model->delete();
                    }
                }
            }

            return isset($model) ? $model->id : null;
        }

    }

    protected function cropImageS3(Model $modelOriginal, $cropData)
    {
        $width = round($cropData['width']);
        $height = round($cropData['height']);

        $model = new Model([
            'fileName' => ImageHelper::getCropFileName($modelOriginal, $width, $height),
        ]);

        $fileName = ImageHelper::getFileName($modelOriginal);
        if($model->save()) {
            try {
                if (!$object = $this->s3->getObject($fileName)) {
                    throw new \Exception('S3 file not found');
                }
                $fileExtension = ImageHelper::getFileExtension($model);

                if ($fileExtension === 'gif') {
                    Image::getImagine()->open(ImageHelper::getTempFilePath($fileName))->save();
                }

                $imageOriginal = Image::getImagine()->open(ImageHelper::getTempFilePath($fileName));
                $imageOriginalSize = $imageOriginal->getSize();

                list($imageCanvasWidth, $imageOriginalPositionX, $imageCropPositionXRounded)
                    = $this->handlingCropData($cropData['x'], $cropData['width'], $imageOriginalSize->getWidth());
                list($imageCanvasHeight, $imageOriginalPositionY, $imageCropPositionYRounded)
                    = $this->handlingCropData($cropData['y'], $cropData['height'], $imageOriginalSize->getHeight());

                Image::getImagine()->create(new Box($imageCanvasWidth, $imageCanvasHeight), (new RGB())->color('#FFF', 0))
                    ->paste($imageOriginal, new Point($imageOriginalPositionX, $imageOriginalPositionY))
                    ->rotate(round($cropData['rotate']))
                    ->crop(new Point($imageCropPositionXRounded, $imageCropPositionYRounded), new Box($width, $height))
                    ->save(ImageHelper::getTempFilePath($model->fileName));

                $this->s3->put(ImageHelper::getFileName($model), ImageHelper::getTempFilePath($model->fileName));

                $this->createS3Thumb(ImageHelper::getTempFilePath($model->fileName), $model);
            } catch (\Exception $exception) {
                $model->delete();
            }
        }

        return isset($model) ? $model->id : null;
    }

    public function createS3Thumb($originFilePath, Model $model, $width = 300, $height = 300, $mode = 'inset')
    {
        try {
            $fileExtension = ImageHelper::getFileExtension($model);

            if ($fileExtension === 'gif') {
                Image::getImagine()->open($originFilePath)->save();
            }

            $sizeName = ImageHelper::getSizeName($width, $height, $mode);
            $path = ImageHelper::getPathByUrl(ImageHelper::getThumbByUrl($originFilePath, $width, $height, $mode, '@frontend/web', $model->fileName));
            $this->s3->put(ImageHelper::getFileName($model), \Yii::getAlias($path), $sizeName);

            $sizeNames = [];

            if($model->sizes){
                $sizeNames = $model->sizes;
            }

            $sizeNames[] = $sizeName;

            if($model->updateAttributes(['sizes' => $sizeNames])){
                $result = true;
            } else {
                $this->s3->delete(ImageHelper::getFileName($model), $sizeName);
                throw new \Exception('Error while update attributes');
            }
        } catch (\Exception $e) {
            $result = false;
        }

        return $result;
    }

    public function checkS3ThumbExist(Model $model, $width = 300, $height = 300, $mode = 'insert')
    {
        $result = false;
        $sizeName = ImageHelper::getSizeName($width, $height, $mode);

        if(!$sizes = $model->sizes) {
            $sizes = [];
        }

        foreach ($sizes as $size)
        {
            if($size == $sizeName){
                $result = true;
                break;
            }
        }

        return $result;
    }


    /**
     * @param integer $coordinate
     * @param integer $side
     * @param integer $imageOriginalSide
     * @return array
     */
    protected function handlingCropData($coordinate, $side, $imageOriginalSide)
    {
        $imageOriginalPosition = $imageCropPosition = 0;
        $canvas = $imageOriginalSide;

        if ($coordinate < 0) {
            $absolutePos = abs($coordinate);
            $imageOriginalPosition = $absolutePos;
            $canvas += $absolutePos;
            $cropWithoutAbsolutePos = $side - $absolutePos;
            if ($cropWithoutAbsolutePos > $imageOriginalSide) {
                $canvas += $cropWithoutAbsolutePos - $imageOriginalSide;
            }
        } else {
            $imageCropPosition = round($coordinate);
            $cropHeightWithPos = $side + $coordinate;
            if ($cropHeightWithPos > $imageOriginalSide) {
                $canvas += ($cropHeightWithPos - $imageOriginalSide);
            }
        }

        return [
            round($canvas),
            round($imageOriginalPosition),
            $imageCropPosition
        ];
    }

    /**
     * @return string
     */
    public function uploadImage()
    {
        $tags = \Yii::$app->request->post('tags', []);

        foreach (UploadedFile::getInstancesByName('imagemanagerFiles') as $file) {
            if (!$file->error) {
                $model = new Model();
                $model->fileName = str_replace("_", "-", $file->getBaseName()) . '.' . mb_strtolower($file->getExtension());
                $model->tags = $tags;

                if ($model->save()) {
                    if ($this->useTinyPng && in_array($file->getExtension(), ['jpg', 'jpeg', 'png'])) {
                        \Yii::$app->get($this->tinyPngComponent)->compress($file->tempName);
                    }

                    if ($this->useS3) {
                        $fileName = ImageHelper::getFileName($model);

                        $this->s3->put($fileName, $file->tempName);

                        if (in_array(ImageHelper::getFileExtension($model), Module::IMAGE_EXTENSIONS)) {
                            $this->createS3Thumb($file->tempName, $model);
                        }
                    } else {
                        $file->saveAs(ImageHelper::getFilePath($model));
                    }
                }
            }
        }

        return isset($model) ? ImageHelper::getFileExtension($model) : 'jpg';
    }

    public function delete(Model $model)
    {
        if($this->useS3){
            return ImageHelper::deleteS3File($model);
        } else {
            return (bool)$model->delete();
        }
    }
}
