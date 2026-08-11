<?php
declare(strict_types=1);
namespace VB\AIThreadAssistant\Services;
use RuntimeException;
final class GeminiClient {
    private function option(string $name): string { return (string) (\vB::getDatastore()->getOption($name) ?? ''); }
    public function models(): array {
        $key=$this->option('aithreadassistant_gemini_api_key'); if ($key==='') throw new RuntimeException('Gemini API key is not configured.');
        $data=$this->request('https://generativelanguage.googleapis.com/v1beta/models',$key); $out=[];
        foreach (($data['models']??[]) as $m) if (in_array('generateContent',$m['supportedGenerationMethods']??[],true)) $out[]=(string)($m['name']??'');
        return array_values(array_filter($out));
    }
    public function summarize(string $text): string {
        $key=$this->option('aithreadassistant_gemini_api_key'); $model=$this->option('aithreadassistant_gemini_model');
        if ($key==='' || $model==='') throw new RuntimeException('Gemini API key and model must be configured in AdminCP.');
        $data=$this->request('https://generativelanguage.googleapis.com/v1beta/'.ltrim($model,'/').':generateContent',$key,['contents'=>[['parts'=>[['text'=>'Summarize this vBulletin thread concisely and neutrally:\n\n'.$text]]]]]);
        $result=(string)($data['candidates'][0]['content']['parts'][0]['text']??''); if ($result==='') throw new RuntimeException('Gemini returned no summary.'); return $result;
    }
    private function request(string $url,string $key,?array $body=null): array {
        $ch=curl_init($url); $headers=['x-goog-api-key: '.$key,'Accept: application/json']; if ($body!==null){$headers[]='Content-Type: application/json';curl_setopt($ch,CURLOPT_POST,true);curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($body));}
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>$headers,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>20]); $raw=curl_exec($ch); $error=curl_error($ch); $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
        if ($raw===false || $error!=='') throw new RuntimeException('Gemini request failed.'); $data=json_decode($raw,true); if ($status<200||$status>=300||!is_array($data)) throw new RuntimeException('Gemini request was rejected.'); return $data;
    }
}
