<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UploadBatch extends Model
{
    protected $fillable = [
        'client_id','name','status','total_files','processed','failed','error_log',
        'topology_json','topology_image_path','topology_clusters',
    ];

    protected $casts = [
        'error_log'          => 'array',
        'topology_json'      => 'array',
        'topology_clusters'  => 'array',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function switches()
    {
        return $this->hasMany(Switche::class);
    }

    public function getProgressPercentAttribute(): int
    {
        if ($this->total_files === 0) return 0;
        return (int)(($this->processed / $this->total_files) * 100);
    }
}