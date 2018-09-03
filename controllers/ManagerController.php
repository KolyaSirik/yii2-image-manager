<?php

namespace noam148\imagemanager\controllers;

use kartik\select2\Select2;
use noam148\imagemanager\helpers\ImageHelper;
use Yii;
use noam148\imagemanager\models\ImageManager;
use noam148\imagemanager\models\ImageManagerSearch;
use yii\base\InvalidConfigException;
use yii\data\ActiveDataProvider;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\Response;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use noam148\imagemanager\Module;

/**
 * Manager controller for the `imagemanager` module
 * @property Module $module
 */
class ManagerController extends Controller
{
    public $enableCsrfValidation = false;

    /**
     * @inheritdoc
     */
    public function behaviors() {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['DELETE'],
                ],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function beforeAction($action)
    {
        if ($this->action->id != 'index') {
            Yii::$app->response->format = Response::FORMAT_JSON;
        }

        return parent::beforeAction($action);
    }

    /**
     * Lists all ImageManager models.
     *
     * @return string
     * @throws \Exception
     */
    public function actionIndex()
    {
        $request = Yii::$app->request;
        $selectType = $request->get("select-type", "input");
        $viewMode = $request->get("view-mode", "page");
        $multiple = (bool) $request->get("multiple", false);

        if ($viewMode == "iframe") {
            $this->layout = "blank";
        }

        \noam148\imagemanager\widgets\ImageManager::widget([
            'selectType' => $selectType,
            'viewMode' => $viewMode,
            'multiple' => $multiple,
        ]);

        $searchModel = new ImageManagerSearch();

        $imageDataProvider = new ActiveDataProvider([
            'query' => $searchModel->search($request->queryParams, false, true),
            'pagination' => [
                'pageSize' => 100,
            ],
            'sort'=> ['defaultOrder' => ['created'=>SORT_DESC]]
        ]);

        $fileDataProvider = new ActiveDataProvider([
            'query' => $searchModel->search($request->queryParams, false, false),
            'pagination' => [
                'pageSize' => 100,
            ],
            'sort'=> ['defaultOrder' => ['created'=>SORT_DESC]]
        ]);

        return $this->render('@vendor/kolyasiryk/yii2-image-manager-mongo/views/manager/index', [
            'searchModel' => $searchModel,
            'imageDataProvider' => $imageDataProvider,
            'fileDataProvider' => $fileDataProvider,
            'viewMode' => $viewMode,
            'selectType' => $selectType,
            'canUploadImage' => $this->module->canUploadImage,
            'canRemoveImage' => $this->module->canRemoveImage,
            'imageTabActive' => $request->get('tab', 'images') === 'images',
        ]);
    }

    /**
     * Creates a new ImageManager model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionUpload()
    {
        if ($this->module->canUploadImage == false) {
            return [];
        }

        Yii::$app->imagemanager->uploadImage();

        \Yii::$app->response->headers->set('Content-Type', 'application/json');
        return Json::encode([]);
    }

    /**
     * Crop image and create new ImageManager model.
     *
     * @return mixed
     * @throws NotFoundHttpException
     */
    public function actionCrop()
    {
        $model = $this->findModel(Yii::$app->request->post("ImageManager_id"));

        return Yii::$app->imagemanager->cropImage($model, Yii::$app->request->post("CropData"));
    }

    /**
     * Get view details
     *
     * @return mixed
     * @throws NotFoundHttpException|InvalidConfigException|\Exception
     */
    public function actionView($id)
    {
        $model = $this->findModel(Yii::$app->request->get("ImageManager_id"));

        $imageDetails = ImageHelper::getImageDetails($model);

        return [
            'id' => $model->id,
            'fileName' => $model->fileName,
            'created' => Yii::$app->formatter->asDate($model->created),
            'fileSize' => $imageDetails['size'],
            'dimensionWidth' => $imageDetails['width'],
            'dimensionHeight' => $imageDetails['height'],
            'originalLink' => ImageHelper::getImageUrl($model),
            'tags' => Select2::widget([
                    'value' => $model->tags ?? [],
                    'name' => 'imageTags',
                    'theme' => Select2::THEME_BOOTSTRAP,
                    'options' => [
                        'placeholder' => 'Enter tags...',
                        'id' => $id,
                    ],
                    'pluginOptions' => [
                        'tags' => true,
                        'multiple' => true,
                    ],
                ]) . Html::script(implode('', array_map('implode', $this->view->js))),
            'image' => ImageHelper::getThumbS3Url($model, 300, 300),
        ];
    }

    /**
     * @return array
     * @throws NotFoundHttpException
     */
    public function actionUpdateTags()
    {
        $model = $this->findModel(Yii::$app->request->post("modelId"));

        $model->tags = Yii::$app->request->post('tags');

        return ['result' => $model->save()];
    }

    /**
     * Get full image
     *
     * @return mixed
     * @throws NotFoundHttpException
     */
    public function actionGetOriginalImage()
    {
        $model = $this->findModel(Yii::$app->request->get("ImageManager_id"));

        return ImageHelper::getImageUrl($model);
    }

    /**
     * Deletes an existing ImageManager model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     *
     * @return array
     * @throws NotFoundHttpException|\Exception|\yii\db\StaleObjectException
     */
    public function actionDelete()
    {
        $result = false;

        if (Yii::$app->controller->module->canRemoveImage != false && $model = $this->findModel(Yii::$app->request->post("ImageManager_id"))) {
            $result = Yii::$app->imagemanager->delete($model);
        }

        return ['delete' => $result];
    }

    /**
     * Finds the ImageManager model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return ImageManager the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if ($model = ImageManager::findOne($id)) {
            /* @var $model ImageManager */
            $module = Module::getInstance();

            if ($module->setBlameableBehavior && Yii::$app->user->id != $model->createdBy) {
                throw new NotFoundHttpException(Yii::t('imagemanager', 'The requested image does not exist.'));
            }

            return $model;
        } else {
            throw new NotFoundHttpException(Yii::t('imagemanager', 'The requested image does not exist.'));
        }
    }
}
