<?php
declare(strict_types=1);
namespace VB\AIThreadAssistant\Controllers;
use VB\AIThreadAssistant\Models\ThreadSummary;
use VB\AIThreadAssistant\Services\GeminiClient;
final class ThreadAssistantController {
    public function index(int $nodeid=0, string $error=''): array {
        $user=vb()->user(); $ok=(int)($user['userid']??0)>0 && (bool)\vB::getDatastore()->getOption('aithreadassistant_enabled') && vb()->can('can_use_ai_thread_assistant','genericpermissions2'); $node=$nodeid?vb()->node($nodeid):[]; $saved=$nodeid?ThreadSummary::query()->find($nodeid):null;
        return ['authorized'=>$ok,'node'=>$node,'summary'=>$saved?['summary'=>$saved->summary,'model'=>$saved->model,'generated_at'=>$saved->generated_at]:null,'error'=>$error,'securitytoken'=>(string)($user['securitytoken']??''),'url'=>vb()->url('aithreadassistant|fullurl',['nodeid'=>$nodeid])];
    }
    public function generate(int $nodeid,string $token): array {
        $user=vb()->user(); if (!$this->authorized($user)||!$this->csrf($token,$user)) return $this->index($nodeid,'Request denied.');
        try { $content=vb()->api('content','getContent',[$nodeid]); $text=$this->text($content); $client=new GeminiClient(); $summary=$client->summarize($text); ThreadSummary::query()->updateOrCreate(['nodeid'=>$nodeid],['summary'=>$summary,'model'=>(string)\vB::getDatastore()->getOption('aithreadassistant_gemini_model'),'generated_at'=>date('Y-m-d H:i:s')]); return $this->index($nodeid); } catch (\Throwable $e) { return $this->index($nodeid,$e->getMessage()); }
    }
    private function text($content): string { $rows=[]; foreach ((array)$content as $row) { if (!is_array($row)) continue; $title=(string)($row['title']??''); $body=(string)($row['rawtext']??$row['pagetext']??$row['text']??''); if($title!==''||$body!=='')$rows[]=$title."\n".$body; } return substr(implode("\n\n",$rows),0,50000); }
    private function authorized(array $u): bool{return (int)($u['userid']??0)>0&&(bool)\vB::getDatastore()->getOption('aithreadassistant_enabled')&&vb()->can('can_use_ai_thread_assistant','genericpermissions2');}
    private function csrf(string $token,array $u): bool { $n=class_exists('vB_User')?\vB_User::fetchUserinfo((int)$u['userid']):[]; return strtoupper($_SERVER['REQUEST_METHOD']??'')==='POST'&&class_exists('vB_User')&&\vB_User::verifySecurityToken($token,(string)($n['securitytoken_raw']??'')); }
}
