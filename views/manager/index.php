<?php
use yii\helpers\Url;
use yii\helpers\Html;
use yii\widgets\ListView;
use yii\widgets\Pjax;
use kartik\file\FileInput;
use yii\data\ActiveDataProvider;
use yii\bootstrap\Modal;
use yii\bootstrap\ActiveForm;
use kartik\select2\Select2;

/**
 * @var string $selectType
 * @var string $viewMode
 * @var ActiveDataProvider $dataProvider
 * @var bool $canUploadImage
 * @var bool $allowedFileExtensions
 * @var bool $canRemoveImage
 * @var bool $imageTabActive
 */

$this->title = Yii::t('imagemanager','File manager');
?>

<div id="module-imagemanager" class="container-fluid <?= $selectType ?>">
    <div class="row">
        <div class="col-xs-6 col-sm-10 col-image-editor">
            <div class="image-cropper">
                <div class="image-wrapper">
                    <img src="" id="image-cropper">
                </div>
                <div class="action-buttons">
                    <div class="btn-group">
                        <button type="button" class="btn btn-primary" id="mode-move"
                                data-toggle="tooltip" data-placement="bottom" title="Move mode">
                            <span class="fa fa-arrows"></span>
                        </button>
                        <button type="button" class="btn btn-primary" id="mode-crop"
                                data-toggle="tooltip" data-placement="bottom" title="Crop mode">
                            <span class="fa fa-crop"></span>
                        </button>
                    </div>
                    <div class="btn-group">
                        <button type="button" class="btn btn-primary" id="zoom-in"
                                data-toggle="tooltip" data-placement="bottom" title="Zoom in">
                            <span class="fa fa-search-plus"></span>
                        </button>
                        <button type="button" class="btn btn-primary" id="zoom-out"
                                data-toggle="tooltip" data-placement="bottom" title="Zoom out">
                            <span class="fa fa-search-minus"></span>
                        </button>
                    </div>
                    <div class="btn-group">
                        <button type="button" class="btn btn-primary" id="rotate-left"
                                data-toggle="tooltip" data-placement="bottom" title="Rotate left">
                            <span class="fa fa-rotate-left"></span>
                        </button>
                        <button type="button" class="btn btn-primary" id="rotate-right"
                                data-toggle="tooltip" data-placement="bottom" title="Rotate right">
                            <span class="fa fa-rotate-right"></span>
                        </button>
                    </div>
                    <div class="btn-group">
                        <button type="button" class="btn btn-primary" id="reset-crop"
                                data-toggle="tooltip" data-placement="bottom" title="Reset">
                            <span class="fa fa-refresh"></span>
                        </button>
                    </div>
                    <a href="#" class="btn btn-primary apply-crop pull-right">
                        <i class="fa fa-crop"></i>
                        <span class="hidden-xs"><?=Yii::t('imagemanager','Crop')?></span>
                    </a>
                    <?php if ($viewMode === "iframe"): ?>
                        <a href="#" class="btn btn-primary apply-crop-select pull-right">
                            <i class="fa fa-crop"></i>
                            <span class="hidden-xs"><?=Yii::t('imagemanager','Crop and select')?></span>
                        </a>
                    <?php endif; ?>
                    <a href="#" class="btn btn-default cancel-crop pull-right">
                        <i class="fa fa-undo"></i>
                        <span class="hidden-xs"><?=Yii::t('imagemanager','Cancel')?></span>
                    </a>
                </div>
            </div>
        </div>
        <div class="col-xs-6 col-sm-10 col-overview">
            <div class="bs-example bs-example-tabs" data-example-id="togglable-tabs">
                <ul class="nav nav-tabs" id="myTabs" role="tablist">
                    <li role="presentation" class="<?= $imageTabActive ? 'active' : '' ?>"><a href="#home" id="home-tab" role="tab" data-toggle="tab" aria-controls="home" aria-expanded="true">Images</a></li>
                    <li role="presentation" class="<?= $imageTabActive ? '' : 'active' ?>"><a href="#profile" role="tab" id="profile-tab" data-toggle="tab" aria-controls="profile" aria-expanded="false">Files</a></li>
                </ul>
                <div class="tab-content" id="myTabContent">
                    <div class="tab-pane fade <?= $imageTabActive ? 'active in' : '' ?>" role="tabpanel" id="home" aria-labelledby="home-tab">
                        <?php Pjax::begin([
                            'id'=>'pjax-mediamanager',
                            'timeout'=>'5000'
                        ]); ?>
                        <?= ListView::widget([
                            'dataProvider' => $imageDataProvider,
                            'itemOptions' => ['class' => 'item img-thumbnail'],
                            'layout' => "<div class='item-overview'>{items}</div> {pager}",
                            'itemView' => function ($model) {
                                return $this->render("@noam148/imagemanager/views/manager/_item", ['model' => $model]);
                            },
                        ]) ?>
                        <?php Pjax::end(); ?>
                    </div>
                    <div class="tab-pane fade <?= $imageTabActive ? '' : 'active in' ?>" role="tabpanel" id="profile" aria-labelledby="profile-tab">
                        <?php Pjax::begin([
                            'id'=>'pjax-mediamanager-files',
                            'timeout'=>'5000'
                        ]); ?>
                        <?= ListView::widget([
                            'dataProvider' => $fileDataProvider,
                            'itemOptions' => ['class' => 'item img-thumbnail'],
                            'layout' => "<div class='item-overview'>{items}</div> {pager}",
                            'itemView' => function ($model) {
                                return $this->render("@noam148/imagemanager/views/manager/_item", ['model' => $model]);
                            },
                        ]) ?>
                        <?php Pjax::end(); ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xs-6 col-sm-2 col-options">
            <div class="form-group">
                <?= Html::textInput('input-mediamanager-search', null, ['id'=>'input-mediamanager-search', 'class'=>'form-control', 'placeholder'=>Yii::t('imagemanager','Search').'...'])?>
            </div>

            <?php if ($canUploadImage):?>

                <?php Modal::begin([
                    'header'=>'Uploading files',
                    'toggleButton' => [
                        'label'=>'Upload', 'class'=>'btn btn-primary btn-block'
                    ],
                    'options' => ['tabindex' => null]
                ]); ?>

                <?php $form = ActiveForm::begin([
                    'action' => Url::to(['manager/upload']),
                    'options'=>['enctype'=>'multipart/form-data']
                ]); ?>

                <div class="form-group">
                    <?= Html::label('Tags', 'tags') ?>
                    <?= Select2::widget([
                        'name' => 'tags',
                        'theme' => Select2::THEME_BOOTSTRAP,
                        'options' => [
                            'placeholder' => 'Enter tags...',
                            'id' => 'upload-tags',
                        ],
                        'pluginOptions' => [
                            'tags' => true,
                            'multiple' => true,
                        ],
                    ]) ?>
                </div>

                <?= FileInput::widget([
                'name' => 'imagemanagerFiles[]',
                'id' => 'imagemanager-files',
                'options' => [
                    'multiple' => true,
//                    'accept' => 'image/*'
                ],
                'pluginOptions' => [
//                    'allowedFileExtensions' => $allowedFileExtensions,
                    'showCaption' => false,
                    'showCancel' => false,
                    'maxFileSize' => \Yii::$app->imagemanager->maxFileSize,
                    'uploadUrl' => Url::to(['manager/upload']),
                    'uploadExtraData' => new \yii\web\JsExpression('function() {
                            var result = {}; 
                            $.each($("#upload-tags[name=\'tags[]\']").val(), function (index, val) {
                                result["tags[" + index + "]"] = val
                            });
                            return result
                        }')
                ],
            ]) ?>
                <br>
                <?= Html::a('Complete uploading', \Yii::$app->request->url, ['class' => 'btn btn-primary btn-block']) ?>

                <?php ActiveForm::end() ?>
                <?php Modal::end() ?>

            <?php endif; ?>

            <div class="image-info hide">
                <?= Html::hiddenInput('modelId', '', ['id' => 'model-id']) ?>
                <div class="thumbnail">
                    <img src="#">
                </div>
                <div class="edit-buttons">
                    <a href="#" class="btn btn-primary btn-block crop-image-item">
                        <i class="fa fa-crop"></i>
                        <span class="hidden-xs"><?=Yii::t('imagemanager','Crop')?></span>
                    </a>
                </div>
                <hr>
                <div class="details">
                    <div class="form-group">
                        <label>Link for file </label> <button class="btn copy-link">Copy</button>
                        <hr>
                        <input title="" type="text" class="form-control image-link" readonly>
                    </div>
                    <div class="fileName"></div>

                    <div class="form-group">
                        <?= Html::label('Tags', 'imageTags') ?>
                        <div class="tags" data-id="select-tags"></div>
                    </div>
                    <?= Html::button('Update tags', ['class' => 'btn btn-primary btn-block', 'id' => 'update-tags']) ?>

                    <div class="created"></div>
                    <?php if (!\Yii::$app->imagemanager->useS3): ?>
                        <div class="fileSize"></div>
                        <div class="dimensions"><span class="dimension-width"></span> &times; <span class="dimension-height"></span></div>
                    <?php endif; ?>
                    <?php if ($canRemoveImage): ?>
                        <a href="#" class="btn btn-xs btn-danger delete-image-item" ><span class="glyphicon glyphicon-trash" aria-hidden="true"></span> <?=Yii::t('imagemanager','Delete')?></a>
                    <?php endif; ?>
                </div>
                <?php if ($viewMode === "iframe"): ?>
                    <a href="#" class="btn btn-primary btn-block pick-image-item"><?=Yii::t('imagemanager','Select')?></a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>