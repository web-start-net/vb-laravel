<?php
declare(strict_types=1);
class aithreadassistant_Api_Threadassistant extends vB_Api {
    public function fetch(?int $nodeid=null): array { \VbLaravel\AddonLoader::load(DIR.'/packages/aithreadassistant'); require_once DIR.'/packages/aithreadassistant/src/Models/ThreadSummary.php'; require_once DIR.'/packages/aithreadassistant/src/Services/GeminiClient.php'; require_once DIR.'/packages/aithreadassistant/src/Controllers/ThreadAssistantController.php'; $c=new \VB\AIThreadAssistant\Controllers\ThreadAssistantController(); $id=filter_var($nodeid?:($_POST['nodeid']??$_REQUEST['nodeid']??0),FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])?:0; if (!$id && class_exists('vB')) { $path=(string)\vB::getRequest()->getVbUrlPath(); if (preg_match('~/([0-9]+)-[^/]+/?$~',$path,$m)) $id=(int)$m[1]; } return ($_POST['aithreadassistant_action']??'')==='generate'?$c->generate($id,(string)($_POST['securitytoken']??'')):$c->index($id); }
}
