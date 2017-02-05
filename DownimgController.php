<?php

class DownimgController extends AppController {

	var $DownimgModel = null;
	var $StudentModel = null;
	var $SchoolModel = null;

	function DownimgController() {
        $this->AppController();
        $this->DownimgModel = $this->getModel('Downimg');
        $this->StudentModel = $this->getModel('Student');
        $this->SchoolModel = $this->getModel('School');
    }

    function indexAction(){
        $SchoolList = $this->SchoolModel->getSchoolList($sh);
        $this->view->assign('paging', $this->SchoolModel->paging);
        $this->view->assign('schoolList', $SchoolList);
        $this->view->layout();
    }

    function downImgAction(){
        $school_id = $this->get('id');
        $save_path_dir = APP_RESOURCE_ROOT . 'Down' . DS . $school_id;
        if(!is_dir($save_path_dir))
            {
                if(!mkdir($save_path_dir,0777,true));
            }
    	$rs = $this->DownimgModel -> getSchoolDownimgList($sh,$school_id);
    	foreach ($rs as $key => $value) {
    		$student_id[] = $value['student_id'];
    	}
    	$student_id = $this->DownimgModel -> formatArray($student_id);
    	foreach ($student_id as $value) {
    		$re[] = $this->DownimgModel -> getStudentDownimgList($sh,$value,$school_id);
    	}
    	$i = null;
    	foreach ($re as $key => $value) {
    		$array=explode('.',$value[$key]['org_img_url' ]);
    		$array[0] = $value[$key]['org_img_url' ].$i++;
    		$value[$key]['org_img_url' ] = implode('.',$array);
    	}

    	foreach ($re as $key => $value) {
    		foreach ($value as $key1 => $value1) {
    			$url = APP_RESOURCE_ROOT . $value1['org_img_url'];
    			// $save_path = $_POST['save_path'] . DS . $value1['student_id'];
                $save_path = $save_path_dir . DS . $value1['student_id'];
                $filenam = substr($value1['org_img_url'],-36);
    			$down_true = $this->DownimgModel -> getImage($url,$save_dir = $save_path,$filename=$filenam,$type=0);
    		}
    	}
        if ($down_true) {
           $this->redirect("?c=downimg&a=index", true, '导出成功');
        }else{
            $this->redirect("?c=downimg&a=index", true, '导出失败');
        }
        // $zipFile = APP_RESOURCE_ROOT . $school_id . '-' . time() . '.zip';
        // $down_true = $this->DownimgModel -> zipDir($save_path_dir, $zipFile);
        // if($down_true) echo 'true';
        // else echo 'fale';
    }



}