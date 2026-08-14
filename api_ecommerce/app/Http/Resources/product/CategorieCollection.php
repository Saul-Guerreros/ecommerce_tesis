<?php

namespace App\Http\Resources\product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class CategorieCollection extends ResourceCollection
{
     public $collects = CategorieResource::class;
}
