<?
global $USER;
CModule::IncludeModule("socialnetwork");
CModule::IncludeModule("main");
CModule::IncludeModule("crm");
CModule::IncludeModule("tasks");
CModule::IncludeModule("extranet");
AddEventHandler("crm", "OnAfterCrmCompanyAdd", "CreateProject");
AddEventHandler("crm", "OnAfterCrmCompanyUpdate", "CreateProject");

AddEventHandler('tasks', 'OnBeforeTaskDelete', "TasksDeleteHandler");
AddEventHandler('tasks', 'OnTaskDelete', "TasksAfterDeleteHandler");
function CreateProject($arFields){
    if ($arFields['UF_PROJECT']!=''){
        foreach ($arFields['FM']['EMAIL'] as $mailData){

            $email=$mailData['VALUE'];
        }
        foreach ($arFields['FM']['PHONE'] as $mailData){
            $phone=$mailData['VALUE'];
        }
        $extranetGroupID=CExtranet::GetExtranetUserGroupID();
        $us= new CUser;
        $arUserFields['PASSWORD']=$email;
        $arUserFields['LOGIN']=$email;
        $arUserFields['EMAIL']=$email;
        $arUserFields['WORK_PHONE']=$phone;
        $userID=$us->Add($arUserFields);
        if ($extranetGroupID>0){
            CUser::AppendUserGroup($userID, $extranetGroupID);
        }
        $groupTitle=$arFields['TITLE'];
        $ownerID=$userID;
        $arGroupFields['NAME']=$groupTitle;

        $arGroupFields['SUBJECT_ID']=2;
        $arGroupFields['SITE_ID']='s1';
        $arGroupFields["INITIATE_PERMS"]='K';
        $groupID=CSocNetGroup::createGroup($ownerID, $arGroupFields, $bAutoSubscribe = true);
        $features=['wiki','files'];
        $id=$groupID;
        foreach ($features as $feature){
            CSocNetFeatures::SetFeature(
                'G',
                $id,
                $feature,
                false,
                false
            );
        }
        $task= new CTasks;
        $arTaskFields['RESPONSIBLE_ID']=$userID;
        $arTaskFields['TITLE']='Тестовая задача';
        $arTaskFields['GROUP_ID']=$groupID;
        $task->Add($arTaskFields);
        //sleep(10800);
        mail($email,'test','test');
    }
}
function TasksDeleteHandler($id){
    global $DB,$USER;
    $task= new CTasks;
    $taskRS=$task->GetByID($id, $bCheckPermissions = false, $arParams = array());
    $taskData=$taskRS->Fetch();
    if ($taskData['TITLE']==='Тестовая задача'){
        $userID=$taskData['RESPONSIBLE_ID'];
        $groupID=$taskData['GROUP_ID'];
        $us= new CUser;
        $userData=$us->GetByID($userID)->Fetch();
        $arOrder = array('DATE_CREATE' => 'DESC');
        $arSelect = array();
        $nPageTop = false;
        $arFilter = [
            "FM"=> [
                [
                    'TYPE_ID' => 'EMAIL',
                    'VALUE' => $userData['EMAIL']
                ],
            ]
        ];
        $companyData=$CCrmCompanyRS=CCrmCompany::GetList($arOrder,$arFilter,$arSelect,$nPageTop)->Fetch();
        $companyId=$companyData['ID'];
        $adminID=$USER->GetID();
        CSocNetUserToGroup::SetOwner($adminID, $groupID);
        $queryString="INSERT INTO test_table VALUES (".$id.",".$groupID.",".$userID.",".$companyId.")";
        $DB->Query($queryString);
    }
}
function TasksAfterDeleteHandler($id){
    global $DB;
    $queryString="SELECT * FROM test_table WHERE task_id='".$id."'  LIMIT 1";
    if ($DB->Query($queryString)){
        $data=$DB->Query($queryString)->Fetch();
        $companyId=$data['company_id'];
        $userID=$data['user_id'];
        $groupID=$data['group_id'];


        CSocNetGroup::Delete($groupID);
        $co = new CCrmCompany;
        $co->Delete($companyId, $arOptions = array());
        CUser::Delete($userID);
    }



}

