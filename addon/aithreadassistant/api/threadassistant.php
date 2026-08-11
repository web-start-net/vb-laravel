<?php
declare(strict_types=1);
class aithreadassistant_Api_Threadassistant extends vB_Api {
    public function fetch(): array { \VbLaravel\AddonLoader::load(DIR.'/packages/aithreadassistant'); require_once DIR.'/packages/aithreadassistant/src/Models/ThreadSummary.php'; require_once DIR.'/packages/aithreadassistant/src/Services/GeminiClient.php'; require_once DIR.'/packages/aithreadassistant/src/Controllers/ThreadAssistantController.php'; $c=new \VB\AIThreadAssistant\Controllers\ThreadAssistantController(); $id=filter_var($_POST['nodeid']??$_REQUEST['nodeid']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])?:0; return ($_POST['aithreadassistant_action']??'')==='generate'?$c->generate($id,(string)($_POST['securitytoken']??'')):$c->index($id); }
}
