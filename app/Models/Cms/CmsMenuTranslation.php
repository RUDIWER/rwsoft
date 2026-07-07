<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Model;

class CmsMenuTranslation extends Model
{
    protected $fillable = [
        'cms_menu_id',
        'locale',
        'title',
    ];
}
