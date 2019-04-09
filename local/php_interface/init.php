//
<?
define("LOG_FILENAME", $_SERVER["DOCUMENT_ROOT"]."/.log.txt");
$APPLICATION->IncludeFile("/local/php_interface/include/config.php");
$APPLICATION->IncludeFile("/local/php_interface/include/functions.php");
$APPLICATION->IncludeFile("/local/php_interface/include/tasks.php");
CModule::IncludeModule("socialnetwork");
CModule::IncludeModule("main");
CModule::IncludeModule("crm");
CModule::IncludeModule("tasks");
$APPLICATION->addHeadScript('/local/js/1cbit/show-sub-tasks-gantt.js');
AddEventHandler("crm", "OnBeforeCrmLeadAdd", Array("MyClass", "BeforeLogin"));
class MyClass
{
  function BeforeLogin(&$arFields)
  {
//        mtLin($_SESSION);
//        mtLin($arFields);
  }
}

//AddEventHandler("main", "OnEndBufferContent", "renameGroups");
AddEventHandler("socialnetwork", "OnFillSocNetMenu", "renameGroups");

function renameGroups(&$arResult, $arEntity)
{

    $arResult["Group"]["NAME"] = $arResult["Group"]["NAME"]."&nbsp;&nbsp;".$arResult["Group"]['UF_PROGRESS']."%";
//    $content = str_replace('Workgroup', 'Project',$content);
//    $content = str_replace('>workgroup', '>project',$content);

//
//    $re = '(>[\s]*?Workgroup)([a-z]*?)[.\s]*?</m';
//    $str = $content;
//    $content = preg_replace($re, 'Project$2', $str);
}
AddEventHandler("crm", "OnAfterCrmCompanyAdd", "CreateProject");
AddEventHandler("crm", "OnAfterCrmCompanyUpdate", "CreateProject");

AddEventHandler('tasks', 'OnBeforeTaskDelete', "TasksDeleteHandler");
function CreateProject($arFields){
    if ($arFields['UF_PROJECT']!=''){
        if ($arFields['HAS_EMAIL']==='Y'){
            $email=$arFields['FM']['EMAIL']['n0']['VALUE'];
        }
        if ($arFields['HAS_PHONE']==='Y'){
            $phone=$arFields['FM']['PHONE']['n0']['VALUE'];
        }
        $extranetGroupID=CGroup::GetIDByCode('EXTRANET');
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
        $arGroupFields['UF_DEPARTMENT']=163;
        $arGroupFields['SUBJECT_ID']=2;
        $arGroupFields['SITE_ID']='s1';
        $arGroupFields["INITIATE_PERMS"]='K';
        $groupID=CSocNetGroup::createGroup($ownerID, $arGroupFields, $bAutoSubscribe = true);
        $features=['wiki','drive'];
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
        sleep(10800);
        mail($email,'test','test');
    }
}
function TasksDeleteHandler($id){
    $task= new CTasks;
    $taskData=$task->GetByID($id, $bCheckPermissions = false, $arParams = array())->Fetch();
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
        CSocNetGroup::Delete($groupID);
        $co = new CCrmCompany;
        $co->Delete($companyId, $arOptions = array());
        CUser::Delete($userID);
    }
}


