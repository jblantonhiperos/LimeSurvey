<?php
namespace ls\controllers;
use Survey;
    /**
     * This class will handle survey creation and manipulation.
     */
    class SurveysController extends Controller
    {
        public $layout = 'minimal';
        public $defaultAction = 'publicList';

        public function accessRules() {
            return array_merge([
                ['allow', 'actions' => ['index'], 'users' => ['@']],
                ['allow', 'actions' => ['publicList']],
                
            ], parent::accessRules());
        }
        public function actionOrganize($surveyId)
        {
            $this->layout = 'main';
            $groups = QuestionGroup::model()->findAllByAttributes(array(
                'sid' => $surveyId
            ));
            $this->render('organize', compact('groups'));
        }

        public function actionIndex() {
            $this->layout = 'main';
            $this->render('index', ['surveys' => new \CActiveDataProvider(Survey::model()->accessible())]);
        }

        public function actionPublicList()
        {
            $this->render('publicSurveyList', array(
                'publicSurveys' => Survey::model()->active()->open()->public()->with('languagesettings')->findAll(),
                'futureSurveys' => Survey::model()->active()->registration()->public()->with('languagesettings')->findAll(),

            ));
        }
        
        public function actionUpdate($id) {

            $survey = $this->loadModel($id);
            if (App()->request->isPostRequest && isset($survey)) {
                $survey->setAttributes($_POST['Survey']);
                if ($survey->save()) {
                    App()->user->setFlash('success', gT("Survey settings updated."));
                }


            }
            $this->layout = 'survey';
            $this->survey = $survey;
            $this->render('update', ['survey' => $survey]);
        }

        public function actionActivate($id) {
            $this->layout = 'survey';
            $survey = $this->loadModel($id);
            if (App()->request->isPostRequest) {
                $survey->activate();
                App()->user->setFlash('succcess', "Survey activated.");
                $this->redirect(['surveys/update', 'id' => $survey->sid]);
            }

            $this->render('activate', ['survey' => $survey]);
        }

        public function actionDeactivate($id) {
            $this->layout = 'survey';
            $survey = $this->loadModel($id);
            if (App()->request->isPostRequest) {
                $survey->deactivate();
                App()->user->setFlash('succcess', "Survey deactivated.");
                $this->redirect(['surveys/update', 'id' => $survey->sid]);
            }

            $this->survey = $survey;
            $this->render('deactivate', ['survey' => $survey]);
        }
        public function filters()
        {
            return array_merge(parent::filters(), ['accessControl']);
        }

        /**
         * @param type $id
         * @return Survey
         * @throws CHttpException
         * @throws \CHttpException
         */
        protected function loadModel($id) {
            $survey = Survey::model()->findByPk($id);
            if (!isset($survey)) {
                throw new \CHttpException(404, "Survey not found.");
            } elseif (!App()->user->checkAccess('survey', ['crud' => 'read', 'entity' => 'survey', 'entity_id' => $id])) {
                throw new \CHttpException(403);
            }

            if ($this->layout == 'survey') {
                $this->survey = $survey;
            }
            return $survey;
        }

        /**
         * This function starts the survey.
         * If a welcome screen is active it shows the welcome screen.
         * @param $id
         */
        public function actionStart($id, $token = null)
        {
            $survey = $this->loadModel($id);
            $this->layout = 'bare';
            if (!$survey->isActive) {
                throw new \CHttpException(412, gT("The survey is not active."));
            } elseif ($survey->bool_usetokens && !isset($token)) {
                throw new \CHttpException(400, gT("Token required."));
            } elseif ($survey->bool_usetokens && null === $token = \Token::model($id)->findByAttributes(['token' => $token])) {
                throw new \CHttpException(404, gT("Token not found."));
            }

            $targetUrl = [
                'surveys/execute',
                'surveyId' => $id,
            ];

            if (App()->request->isPostRequest || $survey->format == 'A' || !$survey->bool_showwelcome) {

                // Create response.
                /**
                 * @todo Check if we shoudl resume an existing response instead.
                 */
                $response = \Response::create($id);
                if (isset($token)) {
                    /**
                     * @todo Update token and check for anonymous.
                     */
                    $response->token = $token->token;
                }
                $response->save();

                $this->render('start', ['url' => ['surveys/run', 'id' => $response->id, 'surveyId' => $id]]);
            } else {
                $this->render('welcome', ['survey' => $survey, 'id' => 'test']);
            }
        }

        public function actionRun($id, $surveyId)
        {
            $survey = $this->loadModel($surveyId);
            $response = \Response::model($survey->sid)->findByPk($id);
            if (!isset($response)) {
                throw new \CHttpException(404, gT("Response not found."));
            } else {
                $this->redirect(['survey/index', 'sid' => $surveyId]);
            }

        }

        public function actionUnexpire($id) {
            $this->layout = 'survey';

            $survey = $this->loadModel($id);
            if (App()->request->isPostRequest && $survey->unexpire()) {
                App()->user->setFlash('success', gT("Survey expiry date removed."));
                $this->redirect(['surveys/view', 'id' => $id]);
            }
            $this->render('unexpire', ['survey' => $survey]);
        }

        public function actionExpire($id)
        {
            $survey = $this->loadModel($id);

            if (App()->request->isPostRequest) {
//                $survey->deactivate();
//                App()->user->setFlash('succcess', "Survey deactivated.");
//                $this->redirect(['surveys/view', 'id' => $survey->sid]);


            }
            $this->layout = 'survey';
            $this->survey = $survey;
            $this->render('expire', ['survey' => $survey]);
        }

    }
?>
