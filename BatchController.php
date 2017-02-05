<?php

include_once(LIBRARY . 'mutex.php');

class BatchController extends AppController{
    
    var $wxPushMessageModel = null;
    var $reportWeekModel = null;
    var $reportMonthModel = null;
    var $studentDetectionModel = null;
    var $studentModel = null;
    var $smsModel = null;
    var $schoolModel = null;
    var $schoolParentModel = null;
    var $staffSignModel = null;
    
    function BatchController() {
        $this->AppController();
        $this->wxPushMessageModel = $this->getModel("WeichatPushMessage");
        $this->reportWeekModel = $this->getModel("ReportWeek");
        $this->reportMonthModel = $this->getModel("ReportMonth");
        $this->studentDetectionModel = $this->getModel("StudentDetection");
        $this->studentModel = $this->getModel("Student");
        $this->smsModel = $this->getModel("Sms");
        $this->schoolModel = $this->getModel("School");
        $this->schoolParentModel = $this->getModel("SchoolParent");
        $this->staffSignModel = $this->getModel('StaffSign');
    }

    function sendSMSAction(){
        $mutex = new Mutex("batch_send_sms");
        while(!$mutex->getLock()){
            sleep(.5);
        }

        $smsList = $this->smsModel->getSmsList();
        foreach($smsList as $sms ) {
            $result = $this->smsModel->sendMessage($sms);

            if($result['code'] == 2) {

                $status = array(
                    'status' => SEND_STATUS_SUCCESS,
                    'result' => $result['code'],
                    'data' => serialize($result),
                );

            } else {

                $status = array(
                    'status' => SEND_STATUS_FAILURE,
                    'result' => $result['code'],
                    'data' => serialize($result),
                );
            }

            $this->smsModel->updateSMS($sms['id'], $status);
        }
        
        $mutex->releaseLock();

        if(!CLI_MODE) {
            print("Send SMS: " . count($smsList));
        }
    }

    function pushWXTemplateMessageAction() {
        $mutex = new Mutex("PushWXTemplateMessage");
        
        if(!$mutex->getLock()){
            exit();
        }

        $messageList = $this->wxPushMessageModel->getMessageList();

        foreach ($messageList as $key => $message) {
            $result = $this->pushWXTemplateMessage(json_encode(unserialize($message['message'])));

            $status = array();
            if(isset($result['errcode']) && $result['errcode'] == 0) {
                $status = array(
                    'status' => SEND_STATUS_SUCCESS,
                    'result' => $result['errcode'],
                    'data' => serialize($result),
                );
            } else {
                $status = array(
                    'status' => SEND_STATUS_FAILURE,
                    'result' => $result['errcode'],
                    'data' => serialize($result),
                );
            }

            $this->wxPushMessageModel->updateMessageStatus($message['id'], $status);
        }

        $mutex->releaseLock();
    }

    //生成周报
    function reportWeekAction() {
        $mutex = new Mutex("ReportWeek");

        if(!$mutex->getLock()){
            exit();
        }

        $now = time();
        $week = date('w', $now);

        $start = date('Y-m-d 00:00:00', $now - $week*24*60*60);
        $end = date('Y-m-d 23:59:59', $now + (6-$week)*24*60*60);
        $sendTime = date('Y-m-d 09:00:00', strtotime($end)); //发送时间周六早上九点

        $studentList = $this->reportWeekModel->getStudentList($start, $end);

        foreach ($studentList as $key => $student) {
            $count = $this->studentDetectionModel->getStudentDetectionCount($student['student_id'], $start, $end);
            if($count) {
                $form = array(
                    'student_id' => $student['student_id'],
                    'start' => $start,
                    'end' => $end,
                );

                $weekReportId = $this->reportWeekModel->saveReport($form);

                $parentList = $this->studentModel->getStudentParentList($student['student_id']);
                foreach($parentList as $parent) {
                    if($parent['openid']) {
                        $form = array(
                            'open_id' => $parent['openid'],
                            'week_report_id' => $weekReportId,
                            'school' => $this->studentModel->getStudentSchool($student['student_id']),
                            'student' => $this->studentModel->getStudent($student['student_id']),
                            'send_time' => $sendTime,
                        );

                        $form['message'] = $this->wxPushMessageModel->genWeekReportMessage($form);

                        $this->wxPushMessageModel->saveMessage($form);
                    }
                }
            }
        }

        $mutex->releaseLock();

        print "Student Week Report: " . count($studentList);
    }
    
    //生成月报
    function reportMonthAction() {
        $mutex = new Mutex("ReportMonth");

        if(!$mutex->getLock()){
            exit();
        }

        $now = time();
        $day = date('m', $now);

        $start = date('Y-m-01 00:00:00', $now);
        $end = date('Y-m-' . date('t', $now) . ' 23:59:59', $now);

        $reported = date('Y-m-d 00:00:00', strtotime($end) + 60*60); //报告显示时间（在日历中显示的时间）
        $sendTime = date('Y-m-d 20:00:00', strtotime($end) + 60*60); //发送时间晚上8点

        $studentList = $this->reportMonthModel->getStudentList($start, $end);

        foreach ($studentList as $key => $student) {
            $count = $this->studentDetectionModel->getStudentDetectionCount($student['student_id'], $start, $end);
            if($count) {
                $form = array(
                    'student_id' => $student['student_id'],
                    'start' => $start,
                    'end' => $end,
                    'reported' => $reported,
                );

                $monthReportId = $this->reportMonthModel->saveReport($form);

                $parentList = $this->studentModel->getStudentParentList($student['student_id']);
                foreach($parentList as $parent) {
                    if($parent['openid']) {
                        $form = array(
                            'open_id' => $parent['openid'],
                            'month_report_id' => $monthReportId,
                            'school' => $this->studentModel->getStudentSchool($student['student_id']),
                            'student' => $this->studentModel->getStudent($student['student_id']),
                            'send_time' => $sendTime,
                        );

                        $form['message'] = $this->wxPushMessageModel->genMonthReportMessage($form);

                        $this->wxPushMessageModel->saveMessage($form);
                    }
                }
            }
        }

        $mutex->releaseLock();

        print "Student Month Report: " . count($studentList);
    }
    
    /**
     * @desc 更新学校KEY
     * @desc 应配置为每天零时更新
     * @author lxs
     */
    function saveSchoolKeyAction() {
        $mutex = new Mutex("saveSchoolKey");

        if(!$mutex->getLock()){
            exit();
        }
        
        $sh = array(
            'key_new_date' => date('Y-m-d')
        );
        
        $school_list = $this->schoolModel->getSchoolList($sh, true);
        
        foreach ($school_list as $school) {
            $school_id = $school['id'];
            
            $this->schoolModel->saveSchoolKey($school_id);
        }

        $mutex->releaseLock();
    }
    
    /**
     * @desc 更新家长二维码
     * @desc 应配置为每分钟运行
     * @author lxs
     */
    function saveParentQrcodeAction() {
        $mutex = new Mutex("saveParentQrcode");

        if(!$mutex->getLock()){
            exit();
        }
        
        $school_list = $this->schoolModel->getSchoolList(array(), true);
        
        foreach ($school_list as $school) {
            $school_id = $school['id'];
            $school_key = $school['key'];
            $school_key_time = $school['key_active_time'];
            
            //1.获取该学校下面需要更新的家长
            if ($parent_list = $this->schoolParentModel->getSchoolUpdateParents($school_id, $school_key)) {
                
                $BraveCrypt = $this->load(EXTEND, 'BraveCrypt');
                $BraveCrypt->init();
                
                foreach($parent_list as $parent) {
                    $rel_id = $parent['id'];
                    $parent_id = $parent['parent_id'];
                    $student_id = $parent['student_id'];
                    
                    //2.1生成家长二维码内容
                    $qrcode_content = COMPANY_CODE . '#' . QRCODE_TYPE_TAKE_AWAY_STUDENT . '#' . strtotime($school_key_time) . '#' . $school_id . '#';
                    
                    $parent_content = COMPANY_CODE . '#' . $student_id . '#' . $parent_id;
                    $parent_aes = $BraveCrypt->encrypt($parent_content, $school_key, AES_IN_IV);
                    
                    $qrcode_content .= $parent_aes;
                    $qrcode_aes = $BraveCrypt->encrypt($qrcode_content, AES_KEY, AES_ALL_IV);
                    
                    
                    //TODO 2.2生成二维码，返回二维码地址
                    $qrcode_uri =  APP_RESOURCE_ROOT . 'Qrcode/'. $rel_id. '/' .'qrcode.png'; //二维码绝对路径
                    $qrcode_url =  'Qrcode/'. $rel_id. '/' .'qrcode.png'; //二维码绝对路径
                    $this->getWeichatQRcodeAction($qrcode_aes, $qrcode_uri);
                    
                    
                    //更新关系表中的二维码链接
                    $data = array(
                        'qrcode_url' => $qrcode_url,
                        'qrcode_school_key' => $school_key,
                    );
                    $this->schoolParentModel->saveParentQrcode($rel_id, $data);
                }
                
            }
            
        }
        
        $mutex->releaseLock();
    }
    
    /**
     * @desc 更新学校职工二维码
     * @desc 应配置为每分钟运行
     * @author wei
     */
    function saveStaffQrcodeAction() {
        $mutex = new Mutex("saveStaffQrcode");
        
        if(!$mutex->getLock()){
            exit();
        }
        
        $school_list = $this->schoolModel->getSchoolList(array(), true);
        
        foreach ($school_list as $school) {
            $school_id = $school['id'];
            $school_key = $school['key'];
            $school_key_time = $school['key_active_time'];
            
            //1.获取该学校下面需要更新的职工
            if ($staffSign_list = $this->staffSignModel->getSchoolStaffSign($school_id, $school_key)) {
                
                $BraveCrypt = $this->load(EXTEND, 'BraveCrypt');
                $BraveCrypt->init();
                
                foreach($staffSign_list as $staffSign) {
                    $staff_id = $staffSign['id'];
                    
                    //2.1生成职工二维码内容
                    $qrcode_content = COMPANY_CODE . '#' . QRCODE_TYPE_SCHOOL_STAFF . '#' . strtotime($school_key_time) . '#' . $school_id . '#';
                    
                    $staff_content = COMPANY_CODE . '#' . $staff_id;
                    $staff_aes = $BraveCrypt->encrypt($staff_content, $school_key, AES_IN_IV);
                    
                    $qrcode_content .= $staff_aes;
                    $qrcode_aes = $BraveCrypt->encrypt($qrcode_content, AES_KEY, AES_ALL_IV);
                    
                    //生成二维码，返回二维码地址
                    $qrcode_uri =  APP_RESOURCE_ROOT . 'staffSignQrcode/'. $staff_id. '/' .'qrcode.png'; //二维码绝对路径
                    $qrcode_url =  'staffSignQrcode/'. $staff_id. '/' .'qrcode.png'; //二维码绝对路径
                    $this->getWeichatQRcodeAction($qrcode_aes, $qrcode_uri);
                    
                    //更新职工表中的二维码链接
                    $data = array(
                        'qrcode_url' => $qrcode_url,
                        'qrcode_school_key' => $school_key,
                    );
                    $this->staffSignModel->saveStaffSignQrcode($staff_id, $data);
                }
                
            }
            
        }
        
        $mutex->releaseLock();
    }
    
    /**
     * @desc 更新学校职工签到签退状态 处理前一天的数据
     * @desc 应配置为每天凌晨执行  
     * @author wei
     */
    function saveStaffSignAction(){
        $staffList = $this->staffSignModel->getStaffList();
        $sign_date = date('Y-m-d', strtotime("-1 day"));
        
        foreach($staffList as $val){
            
            //判断当天是否有签到数据
            if (!$this->staffSignModel->getStaffSignRecord($val['id'], strtotime($sign_date))) {
                //无签到数据，生成缺勤数据
                $sign_data = array(
                    'staff_id' => $val['id'],
                    'sign_timestamp' => strtotime($sign_date),
                    'sign_date' => $sign_date,
                    'set_intime' => $val['in_time'],
                    'set_outtime' => $val['out_time'],
                    'sign_status' => SIGN_STATUS_UNIN_UNOUT,//默认全部缺勤
                    'in_time' => '00:00:00',
                    'in_img' => '',
                    'out_time' => '00:00:00',
                    'out_img' =>  '',
                );
                
                if(!$isStaffSignDate = $this->staffSignModel->getIsStaffSignDate($val['id'], strtotime($sign_date))){
                   $this->staffSignModel->saveStaffSignDate($sign_data); 
                }
                
                continue;
            }
            
            //有签到数据，判断是否有签退数据
            if ($staffSignrecordOut = $this->staffSignModel->getStaffSignrecordOut($val['id'],strtotime($sign_date))) {
                //有签退数据，生成签退状态
                
                $isStaffSignDate = $this->staffSignModel->getIsStaffSignDate($val['id'], strtotime($sign_date));
                
                //设置记录为当天最后一条
                $this->staffSignModel->updateStaffSignrecord($staffSignrecordOut['id']);
                
                $sign_status = 0;
                if($isStaffSignDate['sign_status'] == SIGN_STATUS_IN_UNOUT){
                    //正常签到
                    if(date('H:i:s',strtotime($staffSignrecordOut['created'])) < $isStaffSignDate['set_outtime']){
                        //正常签到且早退
                        $sign_status = SIGN_STATUS_IN_EARLY;
                    }
                    else if(date('H:i:s',strtotime($staffSignrecordOut['created'])) >= $isStaffSignDate['set_outtime']){
                        //正常签到且正常签退
                        $sign_status = SIGN_STATUS_IN_OUT;
                    }
                }
                else if($isStaffSignDate['sign_status'] == SIGN_STATUS_LATE_UNOUT){
                    //迟到
                    if(date('H:i:s',strtotime($staffSignrecordOut['created'])) < $isStaffSignDate['set_outtime']){
                        //迟到且早退
                        $sign_status = SIGN_STATUS_LATE_EARLY;
                    }
                    if(date('H:i:s',strtotime($staffSignrecordOut['created'])) >= $isStaffSignDate['set_outtime']){
                        //迟到且正常签退
                        $sign_status = SIGN_STATUS_LATE_OUT;
                    }
                }
                
                $data = array(
                    'sign_status' => $sign_status ? $sign_status : $isStaffSignDate['sign_status'],
                    'staffSignDate_id' => $isStaffSignDate['id'],
                    'staff_id' => $val['id'],
                    'sign_timestamp' => strtotime($sign_date),
                    'out_time' => date('H:i:s',strtotime($staffSignrecordOut['created'])),
                    'out_img' =>  $staffSignrecordOut['img'],
                );
                $this->staffSignModel->updateStaffSignDate($data); 
                
            }
            
        }
        
    }
    /**
     * @desc 批量处理以前检测每次检测数据图片地址  
     * @author wei
     */
     function  saveStudentDetectionFileAction(){
         
        $studentDetectionList = $this->studentDetectionModel->studentDetectionList();
        
        foreach($studentDetectionList  as $val){
            
            $file =  $this->studentDetectionModel->getFile($val['student_id'], FILE_USAGE_TYPE_STUDENT_DETECTION, date('Y-m-d H',strtotime($val['created'])));
            $data = array(
                'id' => $val['id'],
                'file_img_id' => $file['id'],
                'org_img_url' => $file['file_path'],
            );
            //修改每日数据图地址及ID
            $this->studentDetectionModel->updataStudentDetection($data);
        }
     }
    /**
     * @desc 批量处理删除已经认领过的数据 （每天）  
     * @author wei
     */
    function saveIsDetectionClaimAction(){
        
        $isDetectionClaim = $this->studentModel->getIsDetectionClaim();
        foreach($isDetectionClaim as $var){
            
            //删除已同步数据
            $this->studentModel->deleteIsDetectionClaim($var['id']);
            
        }
    }
}


?>