<?php
declare(strict_types=1);
namespace VB\AIThreadAssistant\Models;
use Illuminate\Database\Eloquent\Model;
final class ThreadSummary extends Model {
    protected $table='aithreadassistant_summary'; protected $primaryKey='nodeid'; public $incrementing=false; protected $keyType='int'; public $timestamps=false;
    protected $fillable=['nodeid','summary','model','generated_at']; protected $casts=['nodeid'=>'integer'];
    public function getTable(){ return (defined('TABLE_PREFIX')?TABLE_PREFIX:'').parent::getTable(); }
}
