<?php

namespace noam148\imagemanager\helpers;

use MongoDB\BSON\ObjectId;
use noam148\imagemanager\models\ImageManager;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use Yii;

/**
 * Class Image
 *
 * @package common\helpers
 */
class ImageHelper
{
    /**
     * @param ImageManager $model
     * @return string
     */
    public static function getDir($model)
    {
        return substr($model->fileHash, 0, 3);
    }

    /**
     * @param ImageManager $model
     * @return string
     */
    public static function getPathToFile($model)
    {
        return sprintf('%s/%s', \Yii::$app->imagemanager->mediaPath, self::getDir($model));
    }

    /**
     * @param ImageManager $model
     * @return string
     */
    public static function getFilePath($model)
    {
        return sprintf('%s/%s', self::getPathToFile($model), self::getFileName($model));
    }

    /**
     * @param ImageManager|string $model
     * @param string|bool|null $absolute
     * @return string
     */
    public static function getImageUrl($model, $absolute = null)
    {
        if (is_object($model) || (is_string($model) && ($model = ImageManager::findOne($model)))) {
            if (\Yii::$app->imagemanager->useS3) {
                return \Yii::$app->imagemanager->s3Url . 'dci/' . self::getFileName($model);
            }

            $url = sprintf('%s/%s/%s',
                \Yii::$app->imagemanager->yiiMediaPath,
                self::getDir($model),
                self::getFileName($model));

            return Url::to($url, $absolute ?? \Yii::$app->imagemanager->absoluteUrl);
        }

        return null;
    }

    public static function getThumbS3Url($model, $width, $height, $mode = 'inset')
    {
        if ($model && in_array(self::getSizeName($width, $height, $mode), (array) $model->sizes)) {
            return \Yii::$app->imagemanager->s3Url . self::getSizeName($width, $height, $mode) . '/' . self::getFileName($model);
        }

        return null;
    }

    /**
     * @param ImageManager $model
     * @return string
     */
    public static function getFileExtension($model)
    {
        return pathinfo($model->fileName, PATHINFO_EXTENSION);
    }

    /**
     * @param $fileName
     * @return mixed
     */
    public static function getTempFilePath($fileName)
    {
        return '/tmp/'. $fileName;
    }

    /**
     * @param ImageManager $model
     * @return string
     */
    public static function getFileName($model)
    {
        return sprintf('%s_%s.%s', $model->id, $model->fileHash, self::getFileExtension($model));
    }

    public static function getSizeName($width, $height, $mode = 'insert')
    {
        return "{$width}x{$height}-{$mode}";
    }

    /**
     * @param ImageManager $model
     * @param integer $width
     * @param integer $height
     * @return string
     */
    public static function getCropFileName($model, $width, $height)
    {
        $fileNameReplace = preg_replace("/_crop_\d+x\d+/", "", $model->fileName);
        $fileName = pathinfo($fileNameReplace, PATHINFO_FILENAME);
        $fileExtension = pathinfo($fileNameReplace, PATHINFO_EXTENSION);

        return sprintf('%s_crop_%sx%s.%s', $fileName, $width, $height, $fileExtension);
    }

    /**
     * Get image data dimension/size
     *
     * @param ImageManager $model
     * @return array The image sizes
     */
    public static function getImageDetails($model)
    {
        $return = ['width' => 0, 'height' => 0, 'size' => 0];

        $filePath = self::getFilePath($model);

        if (file_exists($filePath)) {
            $imageDimension = getimagesize($filePath);
            $return['width'] = $imageDimension[0] ?? 0;
            $return['height'] = $imageDimension[1] ?? 0;
            $return['size'] = \Yii::$app->formatter->asShortSize(filesize($filePath), 2);
        }

        return $return;
    }

    /**
     * Get image path by url
     *
     * @param string $url
     * @param string $alias
     * @return string
     */
    public static function getPathByUrl($url, $alias = '@webroot')
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (strpos($path, \Yii::$app->request->baseUrl) === 0) {
            $path = substr($path, strlen(\Yii::$app->request->baseUrl));
        }

        return $alias . $path;
    }

    /**
     * Get thumb url by origin url
     *
     * @param string|ImageManager $url
     * @param string $alias
     * @param string $mode
     * @param integer $width
     * @param integer $height
     * @return string
     */
    public static function getThumbByUrl($url, $width = 600, $height = 600, $mode = 'inset', $alias = '@frontend/web', $fileName = null)
    {
        if (!$url) return null;

        if (is_object($url)) {
            $url = self::getImageUrl($url);
        }

        if (\Yii::$app->imagemanager->useS3) {
            $tmp = '/tmp/' . ($fileName ?? pathinfo($url, PATHINFO_BASENAME));
            file_put_contents($tmp, file_get_contents($url));

            $absolute = \Yii::$app->imageresize->absoluteUrl;
            \Yii::$app->imageresize->absoluteUrl = false;
            $path = \Yii::$app->imageresize->getUrl($tmp, $width, $height, $mode);
            \Yii::$app->imageresize->absoluteUrl = $absolute;
            unlink($tmp);

            return $path;
        }

        $path = self::getPathByUrl($url, $alias);

        return file_exists(\Yii::getAlias($path)) ? \Yii::$app->imageresize->getUrl($path, $width, $height, $mode) : null;
    }

    /**
     * @param string[] $ids
     * @return ImageManager[]
     */
    public static function getModels($ids)
    {
        $images = $ids ? ImageManager::find()
            ->where(['_id' => ['$in' => $ids]])
            ->all() : [];

        $models = [];
        foreach ($images as $image) {
            $models[array_search($image->id, $ids)] = $image;
        }

        ksort($models);

        return $models;
    }

    /**
     * @param array $value
     * @return array
     */
    public static function filterFormResults($value)
    {
        if (!$value) return [];
        $value = ArrayHelper::map($value, 'id', 'order');
        asort($value);

        return array_map(function($value) {
            return new ObjectId($value);
        }, array_keys($value));
    }

    public static function getModelFromUrl($url)
    {
        $fileName = pathinfo($url, PATHINFO_BASENAME);
        $id = explode('_', $fileName)[0];

        return ImageManager::findOne($id);
    }


    /**
     * @param ImageManager $model
     * @return bool
     */
    public static function deleteS3File(ImageManager $model)
    {
        try {
            if($model->sizes) {
                foreach ($model->sizes as $key => $size){
                    \Yii::$app->imagemanager->s3->delete(self::getFileName($model), $size);
                    unset($model->sizes[$key]);
                    $model->save();
                }
            }

            \Yii::$app->imagemanager->s3->delete(self::getFileName($model));

            $result = (bool)$model->delete();
        } catch (\Exception $e) {
            $result = false;
        }

        return $result;
    }
}
