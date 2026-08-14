<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskAssignment extends Model
{
    use HasFactory;

    public $timestamps = false; // Custom created_at only if needed

    protected $fillable = [
        'task_id',
        'user_id',
        'assigned_at'
    ];

    protected $casts = [
        'assigned_at' => 'datetime'
    ];

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
