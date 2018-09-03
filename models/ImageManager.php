<?php

namespace noam148\imagemanager\models;

use noam148\imagemanager\helpers\ImageHelper;
use MongoDB\BSON\ObjectID;
use noam148\imagemanager\Module;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\helpers\BaseFileHelper;
use yii\mongodb\ActiveRecord;
use Yii;

/**
 * Class ImageManager
 *
 * @package common\models
 * @property ObjectID $_id
 * @property string $id
 * @property string $fileName
 * @property string $fileHash
 * @property int $created
 * @property int $modified
 * @property ObjectID $createdBy
 * @property ObjectID $modifiedBy
 * @property array $tags
 */
class ImageManager extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function collectionName()
    {
        return 'imageManager';
    }

    /**
     * @inheritdoc
     */
    public function attributes()
    {
        return [
            '_id',
            'fileName',
            'fileHash',
            'created',
            'modified',
            'createdBy',
            'modifiedBy',
            'tags',
            'sizes',
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['fileName', 'fileHash'], 'required'],
            [['created', 'modified'], 'safe'],
            [['fileName'], 'string', 'max' => 128],
            [['fileHash'], 'string', 'max' => 32],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            '_id' => Yii::t('imagemanager', 'ID'),
            'fileName' => Yii::t('imagemanager', 'File Name'),
            'fileHash' => Yii::t('imagemanager', 'File Hash'),
            'created' => Yii::t('imagemanager', 'Created'),
            'modified' => Yii::t('imagemanager', 'Modified'),
            'createdBy' => Yii::t('imagemanager', 'Created by'),
            'modifiedBy' => Yii::t('imagemanager', 'Modified by'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        $behaviors[] = [
            'class' => TimestampBehavior::className(),
            'createdAtAttribute' => 'created',
            'updatedAtAttribute' => 'modified',
        ];

        /* @var $moduleImageManager Module */
        if (($moduleImageManager = Yii::$app->getModule('imagemanager'))
            && $moduleImageManager->setBlameableBehavior) {

            $behaviors[] = [
                'class' => BlameableBehavior::className(),
                'createdByAttribute' => 'createdBy',
                'updatedByAttribute' => 'modifiedBy',
            ];
        }

        return $behaviors;
    }

    /**
     * @return string
     */
    public function getId()
    {
        return (string) $this->_id;
    }

    /**
     * @inheritdoc
     */
    public function afterDelete()
    {
        parent::afterDelete();

        if (file_exists($path = ImageHelper::getFilePath($this))) {
            unlink($path);
        }
    }

    /**
     * @param bool $insert
     * @param array $changedAttributes
     * @return void
     * @throws \yii\base\Exception
     */
    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);

        BaseFileHelper::createDirectory(ImageHelper::getPathToFile($this));
    }

    /**
     * @return bool
     * @throws \yii\base\Exception
     */
    public function beforeValidate()
    {
        $this->fileHash = $this->fileHash ?? \Yii::$app->getSecurity()->generateRandomString(32);

        return parent::beforeValidate();
    }
}
