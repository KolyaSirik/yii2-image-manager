<?php
namespace noam148\imagemanager\assets;
use yii\web\AssetBundle;

/**
 * ImageManagerInputAsset.
 */
class ImageManagerInputAsset extends AssetBundle
{
    public $sourcePath = '@vendor/kolyasiryk/yii2-image-manager-mongo/assets/source';
    public $css = [
		'css/imagemanager.input.css',
    ];
    public $js = [
        'js/script.imagemanager.input.js',
    ];
    public $depends = [
		'yii\web\JqueryAsset',
        'yii\bootstrap\BootstrapPluginAsset',
    ];
}