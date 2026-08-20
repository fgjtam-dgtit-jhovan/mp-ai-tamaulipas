<?php

namespace App\Models\Ms;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Corporation extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';
    protected $table = 'CAT_PUSO_DISPOSICION';
    protected $primaryKey = 'ID_PUSO_DISPOSICION';
}
