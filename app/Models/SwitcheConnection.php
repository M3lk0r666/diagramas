<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SwitcheConnection extends Model
{
    protected $fillable = [
        'src_switch_id','src_mac','src_port',
        'dst_switch_id','dst_mac','dst_port',
        'neighbor_name','age','num_vlans',
    ];

    public function srcSwitch()  { return $this->belongsTo(Switche::class, 'src_switch_id'); }
    public function dstSwitch()  { return $this->belongsTo(Switche::class, 'dst_switch_id'); }
}