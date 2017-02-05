<?php

class ArticleController extends AppController {

    var $articleModel = null;
    var $articleAuthModel = null;
    var $schoolModel = null;
    var $classModel = null;

    function ArticleController() {
        $this->AppController();
        $this->articleModel = $this->getModel('Article');
        $this->articleAuthModel = $this->getModel('ArticleAuth');
        $this->schoolModel = $this->getModel('School');
        $this->classModel = $this->getModel('Class');
    }

    function indexAction() {
        $sh = $this->get('sh');
        $this->view->assign('sh', $sh);
        
        $articleList = $this->articleModel->getArticleList($sh);
        foreach($articleList as $k => $v) {
            $v['auth'] = $this->articleAuthModel->getAuth($v['id']);
            $readingData = $this->articleAuthModel->getArticleReadingNum($v['id']);
            $v['readingNum'] = $readingData['reading_num'];
            $articleList[$k] = $v;
        }

        $this->view->assign('paging', $this->articleModel->paging);
        $this->view->assign('articleList', $articleList);
        $this->view->layout();
    }

    function inputAction() {

        $id = $this->get('id');
        $form = $this->post('form');
        
        if ($this->isComplete()) {
            $form['id'] = $id;
            if ($this->articleModel->validSaveArticle($form, $errors)) {
                if ($article_id = $this->articleModel->saveArticle($form)) {
                    $this->articleModel->saveArticleNum($article_id);
                    $this->redirect("?c=article&a=input&pc=article&pa=index&id={$rs}", true, '保存成功');
                    exit;
                }
                
                $this->redirect("?c=article&a=input&pc=article&pa=index&id={$id}", true, '保存失败');
                exit;
            }
            else {
                $this->view->assign('errors', $errors);
                $this->view->assign('form', $form);
            }
        } else {
            $form = $this->articleModel->getArticle($id);
            if($id && empty($form)) {
                 $this->redirect("?c=article&a=index", true, $this->lang('ID不存在'));
                 exit();
            }
            $this->view->assign('form', $form);
        }

        $this->view->layout();
    }

    function authAction() {
    
        $id = $this->get('id');
        $form = $this->post('form');

        if ($this->isComplete()) {
            $form['id'] = $id;
            if ($this->articleAuthModel->validSaveArticleAuth($form, $errors)) {
                if ($rs = $this->articleAuthModel->saveArticleAuth($form)) {
                    $this->redirect("?c=article&a=auth&pc=article&pa=index&id={$id}", true, '保存成功');
                    exit;
                }
                
                $this->redirect("?c=article&a=auth&pc=article&pa=index&id={$id}", true, '保存失败');
                exit;
            }
            else {
                $this->view->assign('errors', $errors);
                $this->view->assign('form', $form);
            }
        } else {
            $form = $this->articleAuthModel->getAuth($id);
            if($id && empty($form)) {
                 $this->redirect("?c=article&a=index", true, $this->lang('ID不存在'));
                 exit();
            }
            $this->view->assign('form', $form);
        }

        $schoolList = $this->schoolModel->getSchoolOptionList();
        $this->view->assign('schoolList', $schoolList);

        $classList = array();
        foreach($schoolList as $school) {

            if($form['school_id'] && !in_array($school['value'], $form['school_id'])) {
                continue;
            }

            $schoolClass = $this->classModel->getClassOptionList($school['value']);
            foreach($schoolClass as $k => $v) {
                $v['name'] = "{$school['name']} - {$v['name']}";
                $schoolClass[$k] = $v;
            }
            $classList += $schoolClass;
        }
        $this->view->assign('classList', $classList);
        $this->view->assign('complete', $this->isComplete());

        $this->view->layout();
    }

    function deleteAction() {
        $id = $this->get('id');
        $this->articleModel->deleteArticle($id);
        $this->redirect("?c=article&a=index", true, '删除成功');
    }

}

?>
