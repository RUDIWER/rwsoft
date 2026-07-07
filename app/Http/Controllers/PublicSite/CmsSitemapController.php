<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Support\PublicSite\CmsSitemapBuilder;
use Illuminate\Http\Response;

class CmsSitemapController extends Controller
{
    public function __construct(private readonly CmsSitemapBuilder $sitemapBuilder) {}

    public function index(): Response
    {
        return $this->xmlResponse($this->sitemapBuilder->sitemapIndexXml());
    }

    public function pages(): Response
    {
        return $this->xmlResponse($this->sitemapBuilder->urlSetXml('pages'));
    }

    public function posts(): Response
    {
        return $this->xmlResponse($this->sitemapBuilder->urlSetXml('posts'));
    }

    public function categories(): Response
    {
        return $this->xmlResponse($this->sitemapBuilder->urlSetXml('categories'));
    }

    public function tags(): Response
    {
        return $this->xmlResponse($this->sitemapBuilder->urlSetXml('tags'));
    }

    private function xmlResponse(string $xml): Response
    {
        return response($xml, 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('Cache-Control', 'no-cache, private');
    }
}
