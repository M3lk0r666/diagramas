<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Switche extends Model
{
    protected $fillable = [
        'upload_batch_id','original_filename','sys_name','sys_location',
        'sys_contact','system_mac','system_type','serial_number',
        'management_ip','firmware_version','config_path','vlans','ip_routes','edp_ports',
        'active_ports','raw_sections','parse_status','parse_error','parsed_at',
        'is_stacked','stack_topology','stack_members',
    ];

    protected $casts = [
        'vlans'         => 'array',
        'ip_routes'     => 'array',
        'edp_ports'     => 'array',
        'active_ports'  => 'array',
        'raw_sections'  => 'array',
        'parsed_at'     => 'datetime',
        'is_stacked'    => 'boolean',
        'stack_members' => 'array',
    ];

    public function batch()
    {
        return $this->belongsTo(UploadBatch::class, 'upload_batch_id');
    }

    public function outgoingConnections()
    {
        return $this->hasMany(SwitcheConnection::class, 'src_switch_id');
    }

    public function incomingConnections()
    {
        return $this->hasMany(SwitcheConnection::class, 'dst_switch_id');
    }
}