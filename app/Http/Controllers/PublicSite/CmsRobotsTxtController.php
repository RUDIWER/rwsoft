<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Support\PublicSite\CmsRobotsTxtBuilder;
use Illuminate\Http\Response;

class CmsRobotsTxtController extends Controller
{
    public function __construct(private readonly CmsRobotsTxtBuilder $robotsTxtBuilder) {}

    public function show(): Response
    {
        return response($this->robotsTxtBuilder->build(), 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8')
            ->header('Cache-Control', 'no-cache, private');
    }
}
