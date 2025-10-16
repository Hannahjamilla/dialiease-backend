<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Archive extends Model
{
    use HasFactory;

    protected $primaryKey = 'archive_id';
    
    protected $fillable = [
        'archived_data',
        'archived_from_table',
        'archived_by',
        'archived_date'
    ];

    protected $casts = [
        'archived_data' => 'array',
        'archived_date' => 'datetime'
    ];

    /**
     * Relationship with user who archived the record
     */
    public function archivedByUser()
    {
        return $this->belongsTo(User::class, 'archived_by', 'userID');
    }
}