<?php

namespace App\Models\Ms;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Violence extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';
    protected $table = 'CAT_VIOLENCIA';
    protected $primaryKey = 'ID_VIOLENCIA';
}
