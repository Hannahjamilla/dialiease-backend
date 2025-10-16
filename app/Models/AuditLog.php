<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $table = 'audittrail';
    protected $primaryKey = 'audit_id';
    public $timestamps = false;

    protected $fillable = ['userID', 'action', 'timestamp'];

    public function user()
    {
        return $this->belongsTo(User::class, 'userID', 'userID');
    }
}
