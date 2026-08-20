<?php

namespace App\Models\Ms;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Modality extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';
    protected $table = 'CAT_MODALIDAD';
    protected $primaryKey = 'ID_MDLDD';
}
