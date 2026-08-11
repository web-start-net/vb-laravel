<?php
error_reporting(error_reporting() & ~E_NOTICE); chdir(dirname(__FILE__).'/../../admincp'); require_once './global.php';
if (!can_administer('canadminsettings')) print_cp_no_permission();
require_once dirname(__FILE__).'/src/Services/GeminiClient.php';
if (($_REQUEST['do']??'')==='save' && isset($_POST['model'])) { vB::getDatastore()->setOption('aithreadassistant_gemini_model', trim((string)$_POST['model']), false); print_cp_message('Gemini model saved.'); }
$models=[]; $message='Configure the API key in vBulletin Options &gt; AI Thread Assistant, then fetch compatible models here.';
if (($_REQUEST['do']??'')==='fetch') { try { $models=(new \VB\AIThreadAssistant\Services\GeminiClient())->models(); $message='Select a compatible text-generation model and save it.'; } catch (Throwable $e) { $message=$e->getMessage(); } }
print_form_header('core/packages/aithreadassistant/admincp.php',$models?'save':'fetch'); print_table_header('AI Thread Assistant model selection'); print_description_row(htmlspecialchars($message)); if ($models) { $html='<select name="model">'; foreach($models as $m) $html.='<option value="'.htmlspecialchars($m).'">'.htmlspecialchars($m).'</option>'; $html.='</select>'; print_label_row('Gemini Model',$html); print_submit_row('Save selected model'); } else { print_submit_row('Fetch models'); } print_cp_footer();
